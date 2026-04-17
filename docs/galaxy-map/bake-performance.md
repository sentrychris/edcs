# Bake-time performance tradeoffs

The baker has to process ~95M rows, write ~5000 tile files, then read
them all back for downsampling. This doc walks through the choices that
keep the process O(n) on time and O(1) on memory.

The relevant code is
[GalaxyTileBakerService.php](../../app/Services/GalaxyTileBakerService.php).

## The three-pass structure

```
DB cursor  ─────────────────▶  LOD 2 tiles        (pass 1: bakeLod2)
                ↓
             LOD 2 files  ──▶  LOD 1 tiles        (pass 2: bakeLod1)
                ↓
             LOD 2 files  ──▶  LOD 0 tile         (pass 3: bakeLod0)
                ↓
                              manifest.json       (write-then-rename)
```

Only **pass 1 hits the database**. Passes 2 and 3 read the LOD 2 files
off disk, which is much faster than another 95M-row DB scan.

This is a deliberate tradeoff: we write ~760 MB of binary to disk in
pass 1 that we then immediately read back in passes 2/3. The alternative
— keeping the LOD 2 data in memory — needs 760+ MB of RAM. Disk is
cheap, RAM is less cheap, and the OS's page cache makes the read-back
essentially free for small-to-medium bake sizes.

## Pass 1: streaming the database

```php
$cursor = System::select(['id64', 'coords_x', 'coords_y', 'coords_z'])
    ->orderBy('id')
    ->cursor();

foreach ($cursor as $system) { ... }
```

Two critical things here:

### `cursor()` over `get()` / `chunk()`

Eloquent's `cursor()` returns a `LazyCollection` backed by a streaming
PDO cursor. Each row is fetched one at a time from the DB — **memory
stays flat** regardless of total rows.

`get()` would load all 95M rows into a PHP array = OOM.

`chunk(1000)` would work but makes 95,000 separate queries each with
its own OFFSET/LIMIT, and performance degrades sharply as OFFSET grows.

`cursorPaginate` is a third option but overkill; we don't need paging,
we need a firehose.

### `select(['id64', 'coords_x', 'coords_y', 'coords_z'])`

Only these four columns are needed. The `systems` table has many more
columns (station lists, population, etc.) that we'd waste bandwidth
pulling from the DB if we used `select('*')`.

## The LRU file-handle cache

This is the non-obvious performance trick. The bake needs to write ~5000
tile files, but with a naïve "open on first use, keep open" approach
you'd blow through the `ulimit -n` default of 1024.

[bakeLod2()](../../app/Services/GalaxyTileBakerService.php#L89-L161)
uses an **ordered-map LRU**:

```php
// Insertion order = recency. array_key_first gives the LRU key.
$handles = [];
```

For each system:

```php
if (isset($handles[$key])) {
    // Touch: move to MRU position.
    $fh = $handles[$key];
    unset($handles[$key]);
    $handles[$key] = $fh;
} else {
    if (count($handles) >= self::MAX_OPEN_HANDLES) {
        $evictKey = array_key_first($handles);
        fclose($handles[$evictKey]);
        unset($handles[$evictKey]);
    }

    if (isset($seen[$key])) {
        // Re-opening an evicted tile: append.
        $handles[$key] = fopen($path, 'ab');
    } else {
        $handles[$key] = fopen($path, 'wb');
        fwrite($handles[$key], pack('V', 0));  // count placeholder
        $counts[$key] = 0;
        $seen[$key] = true;
    }
}
```

### Why this works

PHP's associative arrays **preserve insertion order**. `array_key_first`
gives you the oldest key in O(1). `unset($arr[$k]); $arr[$k] = $fh;`
effectively moves a key to the end in O(1). This gives us an ordered
hash map — the same data structure Java calls `LinkedHashMap` — for
free, without importing a library.

### Why 256 handles?

Arbitrary, but well under the 1024 ulimit. Low enough to leave headroom
for whatever else PHP opens (DB connection, stdout, the CLI's own fds,
etc.), high enough that most sectors stay hot while their "neighbours
in DB iteration order" are being processed.

### Why it's a win

DB iteration order is `orderBy('id')`, which roughly correlates with
*discovery order*, which correlates with *spatial locality* (commanders
in the same part of the galaxy tend to scan nearby systems). So runs of
adjacent DB rows tend to land in the same sector. The LRU keeps those
runs hitting cached handles and evicts tiles from unrelated regions
first.

Without the LRU, you'd either hit the ulimit wall or pay a `fopen`/
`fclose` on *every single system write* — that's 95M open/close syscalls
vs. ~30k with the cache. The difference is measured in hours.

### Re-opening in append mode

When a previously-written tile is evicted and later needs more data,
we `fopen($path, 'ab')` instead of `'wb'`. The `a` mode seeks to
end-of-file on each write, so appending doesn't trample the count
placeholder at byte 0 or the data written before eviction.

## The count-fixup trick

```php
// While writing:
fwrite($handles[$key], pack('V', 0));  // placeholder
$counts[$key]++;  // ...

// After all writes:
foreach ($counts as $key => $count) {
    $fh = fopen($path, 'r+b');
    fwrite($fh, pack('V', $count));   // overwrite the first 4 bytes
    fclose($fh);
    rename($path.'.tmp', $path);
}
```

We could build the whole tile in memory, compute the count, then write
it all at once. But tiles in dense sectors can contain hundreds of
thousands of systems, and holding them all in memory while streaming the
DB would defeat the whole point.

The placeholder-and-fixup pattern is a standard binary-format technique.
It's safe because `fopen(..., 'r+b')` allows reads and writes to the
existing file without truncating, and we only touch the first 4 bytes.

## Pass 2: reading LOD 2 to build LOD 1

[bakeLod1()](../../app/Services/GalaxyTileBakerService.php#L170-L233)
groups LOD 2 sectors into 4×4×4 blocks and down-samples each block to
~10k stars.

```php
$stride = max(1, (int) ceil($totalInGroup / self::LOD1_TARGET_PER_TILE));

foreach ($sectorKeys as $sk) {
    $in = fopen($versionRoot.'/lod2/'.$sk.'.bin', 'rb');
    fread($in, 4);  // skip count header
    while (! feof($in)) {
        $buf = fread($in, 8);
        if ($buf === false || strlen($buf) < 8) break;
        if ($cursor % $stride === 0) {
            fwrite($out, $buf);
            $written++;
        }
        $cursor++;
    }
    fclose($in);
}
```

### Why we can pass through opaque bytes

Notice `fwrite($out, $buf)` — we pass the 8-byte id64 from LOD 2 straight
through to LOD 1 without decoding. The baker doesn't care *what* the
id64 represents at this stage; it only needs to sample every Nth one.

This is only possible because the binary format is uniform: fixed-size
records, no framing. A format with variable-length records (Protobuf,
etc.) would require decoding to know where each record ends.

### Stride sampling vs. random sampling

Taking every Nth record is deterministic and O(1) memory. A "true"
random sample (Fisher-Yates style) would need to hold the whole tile in
memory. For visual purposes, stride is indistinguishable from random as
long as there's no structural periodicity in id64 order — and there
isn't, because id64 is a packed bit layout, not a spatial sort.

### The `max(1, ...)` guard

```php
$stride = max(1, (int) ceil($totalInGroup / self::LOD1_TARGET_PER_TILE));
```

If a LOD 1 group has fewer than 10k stars total, `ceil(small / 10000)` is
0, and `$cursor % 0` is a division by zero. `max(1, ...)` falls back to
stride=1 (take everything), which correctly means "don't downsample a
sparse region."

## Pass 3: the global LOD 0

[bakeLod0()](../../app/Services/GalaxyTileBakerService.php#L241-L278)
is structurally identical to pass 2 but spans all LOD 2 tiles and
targets 50k globally. Same stride-sampling trick.

## Atomicity and versioning

```php
$version = $this->nextVersion($outputRoot);
$versionRoot = $outputRoot.'/v'.$version;
```

Each bake writes to `v{N}/`, so an in-progress bake never overwrites
files the running site is serving. The manifest is the only "publish
pointer":

```php
$tmp = $outputRoot.'/manifest.json.tmp';
file_put_contents($tmp, json_encode($manifest, JSON_UNESCAPED_SLASHES));
rename($tmp, $outputRoot.'/manifest.json');
```

POSIX `rename()` is atomic within a filesystem: readers either see the
old manifest (pointing at `v(N-1)/`) or the new one (pointing at
`v{N}/`), never a half-written intermediate.

The `--prune` option on
[BakeGalaxyTilesCommand](../../app/Console/Commands/BakeGalaxyTilesCommand.php)
deletes old `v*/` directories after a successful bake. Without it you
accumulate history — useful during development, wasteful in production.

## What dominates runtime

In rough order:

1. **DB scan.** Cursor latency × 95M rows. On our hardware this is the
   biggest chunk — tens of minutes. Can be improved with read replicas,
   connection tuning, or partitioning the scan by id range and running
   pass 1 in parallel workers writing to separate subdirs that get
   merged. Not currently done.
2. **Pass 2 + 3 file I/O.** Sequential read of ~760 MB. Fast on SSD,
   possibly minutes on spinning disk.
3. **Pass 1 file writes.** Lots of small writes to many files. The LRU
   mitigates syscall overhead; `fwrite` is line-buffered by default, so
   bursts of writes to the same handle aggregate into larger filesystem
   writes.

## What would break at much larger scale

If the galaxy had 1B rows instead of 95M, a few things would need
rethinking:

- **Pass 1 would need parallelism.** 10× the DB scan time is unsustainable
  as a single process. Partition by id range, run N workers writing to
  `lod2/worker{i}/`, merge at the end.
- **256 handles might not be enough.** If the sector count scaled with
  row count (it wouldn't, sectors are finite, but hypothetically),
  thrashing could kick in.
- **Pass 2/3 read pattern is sequential per group, random across groups.**
  Reading ~5000 small files is fine; reading ~100k would make the
  kernel's readahead useless. You'd want `posix_fadvise` hints or
  batching.

For the current scale, none of this matters. The baker is boring and
that's the point.

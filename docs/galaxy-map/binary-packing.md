# Binary packing — the tile wire format

Each tile is a tiny, fixed-shape binary blob. No JSON, no protobuf, no
framing — just a count followed by a flat array of 64-bit integers. This
doc covers *why* that format, *how* PHP writes it, and *how* JavaScript
reads it.

## The format

```
byte 0              byte 4                byte 12
┌──────────────────┐┌────────────────────┐┌────────────────────┐
│ uint32 count LE  ││ uint64 id64 #0 LE  ││ uint64 id64 #1 LE  │ ...
└──────────────────┘└────────────────────┘└────────────────────┘
                     \_________________ count × 8 bytes __________/
```

- **Byte 0–3:** 32-bit unsigned little-endian integer — the number of
  id64s that follow.
- **Byte 4 onward:** an array of 64-bit unsigned little-endian id64s,
  contiguous, no separators.

Total file size: `4 + count * 8` bytes.

That's it. No version number, no checksum, no tile coordinates embedded
in the file (the coordinates come from the URL / filename).

## Why binary?

The alternative is JSON:

```json
{ "count": 10000, "ids": ["10477373803", "20578934", ...] }
```

Numeric strings are needed because JSON numbers aren't safe for 64-bit
integers in JavaScript (`JSON.parse("10477373803")` happens to work here
but `10477373803000000000` would silently lose precision).

Per system:

| Encoding | Bytes per system | Notes |
|----------|------------------|-------|
| JSON string with quotes/comma | ~13 | Variable, includes punctuation |
| Binary uint64 | 8 | Fixed |
| Gzipped JSON | ~6–8 | Close to binary but needs gzip on each request |
| Gzipped binary | ~6 | Marginal win on top of binary |

Binary is ~40% smaller than raw JSON and ties with gzipped JSON — but
decoding binary with `DataView` is *also* faster than `JSON.parse` + string
→ `BigInt`. The win is size **and** speed.

It's also **random-access**. Given the binary format, you can `seek` to
the *N*th id64 in O(1) (`offset = 4 + n * 8`). This matters for the LOD 1
/ LOD 0 downsampling step in the baker — see below.

## Why `uint32` for the count?

Because 4 bytes × 10^9 = 32 GB, and no tile will ever have a billion
systems (the whole galaxy is ~95M). A 16-bit count would cap us at 65535
per tile, which the core sectors blow past. 32 bits is the smallest sane
choice.

## Why little-endian?

x86-64 is little-endian, and so are all major browser platforms. WebGL's
typed arrays default to the platform endianness. By explicitly writing
little-endian from PHP, we sidestep any "whose endianness?" ambiguity —
every reader passes `true` to `DataView.getUint32` / `getBigUint64` to
force little-endian, and it Just Works.

## PHP side: writing

The baker opens a tile file and writes a *placeholder* count, then
appends id64s, then goes back and fixes the count at the end.

```php
// Open a new tile and write a count placeholder.
$handles[$key] = fopen($path, 'wb');
fwrite($handles[$key], pack('V', 0));  // 4-byte uint32 LE, will overwrite later
$counts[$key] = 0;

// For each system that lands in this tile:
fwrite($handles[$key], $this->packUint64((int) $system->id64));
$counts[$key]++;
```

Then, after all systems are processed:

```php
foreach ($counts as $key => $count) {
    $fh = fopen($path, 'r+b');
    fwrite($fh, pack('V', $count));  // overwrite the first 4 bytes
    fclose($fh);
    rename($path.'.tmp', $path);     // atomic publish
}
```

Two PHP-specific things to know:

### `pack('V', ...)` — little-endian uint32

PHP's `pack()` is a printf-style format for binary data. `V` means
"unsigned long (always 32 bit, little-endian)". `pack('V', 42)` returns
the 4-byte string `"\x2A\x00\x00\x00"`.

### The `packUint64` workaround

PHP has a `P` format (little-endian 64-bit), but it's *signed*-aware.
id64 values can use the full 64 bits, so the high bit being set would
produce a negative number and round-trip incorrectly on some platforms.

The workaround in
[packUint64()](../../app/Services/GalaxyTileBakerService.php#L344-L347):

```php
private function packUint64(int $v): string
{
    return pack('VV', $v & 0xFFFFFFFF, ($v >> 32) & 0xFFFFFFFF);
}
```

Split into two 32-bit halves, write low-then-high (little-endian order),
mask each half to 32 bits. Safe for the full uint64 range assuming PHP's
64-bit `int` (which is the case on 64-bit systems).

### Atomic publish with rename

Tiles are written to `{key}.bin.tmp` first, then `rename()`d to `{key}.bin`.
On POSIX filesystems `rename()` within a directory is atomic, so a reader
(Nginx / Laravel serving the file) never sees a half-written tile. If the
bake crashes mid-write, the stale version stays in place until the next
successful bake.

The manifest uses the same trick:
[writeManifest()](../../app/Services/GalaxyTileBakerService.php#L287-L315)
writes `manifest.json.tmp` and renames.

## JavaScript side: reading

[decodeTile()](../../../edcs-app/app/galaxy-map/components/galaxy-map-canvas.tsx#L363-L399)
reads the tile:

```ts
function decodeTile(buffer: ArrayBuffer): DecodedTile {
  const view = new DataView(buffer);
  const count = view.getUint32(0, true);         // byte 0, LE

  const positions = new Float32Array(count * 3);
  const colors    = new Float32Array(count * 3);
  const sizes     = new Float32Array(count);

  let written = 0;
  for (let i = 0; i < count; i++) {
    const offset = 4 + i * 8;
    const id64 = view.getBigUint64(offset, true);  // LE bigint
    const coords = id64ToCoords(id64);
    if (!coords) continue;
    // ... pack into typed arrays
  }

  return { positions, colors, sizes, count: written };
}
```

Three JS-specific things:

### `ArrayBuffer` + `DataView`

- `ArrayBuffer` is the raw byte container you get from
  `fetch(...).arrayBuffer()`.
- `DataView` gives you typed read/write access to it at arbitrary offsets
  with explicit endianness.
- `Float32Array`, `Uint8Array`, etc. are *views* of the same underlying
  buffer, but typed (so `f32[0] = 1.5` writes 4 bytes at offset 0
  interpreted as float).

### `getBigUint64` for safe 64-bit reads

Regular `Number` can't hold all 64 bits. `view.getBigUint64()` returns a
`bigint`, which is the only safe integer type at that width.

### Overallocate, then `.subarray()`

The output `Float32Array`s are allocated for `count` systems up front,
but some id64s might fail to decode (the `if (!coords) continue` branch).
At the end we return `positions.subarray(0, written * 3)` — a **view**
(not a copy) clamped to what was actually written. The unused tail
memory is released when the underlying buffer is GC'd.

## Memory layout in the GPU

After `decodeTile()`, the typed arrays are uploaded as three separate
WebGL buffers in [uploadTile()](../../../edcs-app/app/galaxy-map/components/galaxy-map-canvas.tsx#L401-L415):

| Attribute | Buffer | Type | Per-vertex bytes |
|-----------|--------|------|------------------|
| `a_position` | `posBuf` | `Float32Array` × 3 | 12 |
| `a_color` | `colBuf` | `Float32Array` × 3 | 12 |
| `a_size` | `sizeBuf` | `Float32Array` | 4 |

So each star occupies **28 bytes** on the GPU. A tile with 10k systems
takes 280 KB of VRAM. The whole galaxy rendered at LOD 2 would be 95M ×
28 = 2.6 GB — which is why we don't, and why LOD exists.

You could pack position+color+size into one interleaved buffer (a single
`struct` with stride 28), which is slightly more cache-friendly and saves
two `bindBuffer` calls per tile draw. Not done here because the
performance gap is negligible for the scale we're drawing.

## Could we do better?

Size-wise, a few more tricks are possible:

- **Delta encoding.** Sort id64s within a tile, store the deltas as
  variable-width integers. Maybe 30% smaller. Dropped: decode complexity
  and you lose the O(1) stride-sampling property the baker relies on.
- **Omitting the count.** It's derivable from `buffer.byteLength - 4) / 8`.
  True, but having it inline lets the decoder preallocate typed arrays
  before reading.
- **Brotli / zstd.** HTTP content-encoding does this for us for free on
  most servers. We don't explicitly enable it, but with gzip on the wire
  the tiles compress nicely (id64s aren't random — they cluster in
  sectors, so there's structure to exploit).

The current format is the simplest thing that works, and decoding cost is
not the bottleneck — the bottleneck is *how many* points we draw, not how
we got them.

# Galaxy map — end-to-end walkthrough

## 1. The problem we're solving

The `systems` table has ~95M rows. Even at 20 bytes per row, that's nearly 2 GB
to ship to the browser. The browser would also have to draw 95M points per
frame — a non-starter.

**LOD (Level of Detail)** is the standard graphics trick for this: when
something is far away, you don't need every detail. A mountain 50 km away
doesn't need individual trees — a blurry silhouette works. Zoom in, and you
swap in progressively more detail.

For the galaxy map, "far away" means "zoomed out." When the whole galaxy fits
on screen, one pixel covers *thousands* of stars — drawing them all would be
wasted work and would just look like mush anyway. So we pre-compute a few
sampled versions.

Deep dive: [lod-and-tiling.md](lod-and-tiling.md)

## 2. The tile pyramid — like Google Maps, but 3D

If you've used map tiles (Google Maps, Leaflet), the idea is identical:
pre-bake a grid of small files, load only the ones you can see. The galaxy
version adds a Z axis and a zoom hierarchy.

Three levels, defined in
[GalaxyTileBakerService.php:36-42](../../app/Services/GalaxyTileBakerService.php#L36-L42):

| LOD | What it is | Size | When it's loaded |
|-----|------------|------|------------------|
| **LOD 2** | Every system, grouped into 1280-ly cubes ("sectors") | ~5000 files, one per cube | Close-up views (< 8000 ly from camera) |
| **LOD 1** | 4×4×4 blocks of sectors, sampled to ~10k stars each | Fewer, bigger files | Mid-zoom (8000–60000 ly) |
| **LOD 0** | Whole galaxy, sampled to ~50k stars | One file | Fully zoomed out (> 60000 ly) |

Thresholds live in
[galaxy-map-canvas.tsx:19-20](../../../edcs-app/app/galaxy-map/components/galaxy-map-canvas.tsx#L19-L20).
Only **one** LOD renders at a time — mixing them would double-draw stars,
since LOD 0 and LOD 1 are sampled *subsets* of LOD 2.

## 3. Baking (backend) — `php artisan galaxy:bake-tiles`

### LOD 2: one tile per sector

[bakeLod2()](../../app/Services/GalaxyTileBakerService.php#L89-L161) streams
every row from the DB with a cursor (so memory stays flat), and for each system:

1. Convert coords to an integer sector index: `floor((coord + BASE) / 1280)`.
   The `BASE` offsets put Sagittarius A* at a round-numbered origin.
2. Open the matching tile file (`lod2/{sx}_{sy}_{sz}.bin`) and append the
   system's `id64`.

One subtlety worth calling out: the galaxy has ~5000 populated sectors, but
Linux usually caps open files at 1024. The code keeps an **LRU cache** of 256
open file handles and reopens files in append mode when evicted ones come
back. See
[bake-performance.md](bake-performance.md).

### LOD 1: down-sample groups of LOD 2 tiles

[bakeLod1()](../../app/Services/GalaxyTileBakerService.php#L170-L233) reads the
LOD 2 files it just wrote (much faster than hitting the DB again), groups them
into 4×4×4 super-sectors, then takes every Nth star to hit ~10k per tile.

### LOD 0: one big sample of the whole galaxy

[bakeLod0()](../../app/Services/GalaxyTileBakerService.php#L241-L278) same
idea but across all sectors, targeting 50k stars total.

### The binary tile format

Each tile is just:

```
[uint32 count][uint64 id64][uint64 id64]...
```

Why binary not JSON? Size. A `uint64` is 8 bytes; the JSON
`"12345678901234567890"` is ~20 bytes plus commas/quotes. Across 95M stars
that's hundreds of MB saved.

We **only** ship the `id64` — *not* the x/y/z coords. That's because of…

Deep dive: [binary-packing.md](binary-packing.md)

## 4. The id64 trick — coordinates are already encoded in the ID

Elite Dangerous uses a scheme called "boxel encoding" where a system's 64-bit
ID literally contains its position. The frontend's
[id64ToCoords()](../../../edcs-app/app/galaxy-map/components/galaxy-map-canvas.tsx#L22-L34)
decodes it:

```
sector (integer grid cell) + boxel (sub-cell) + size → x, y, z
```

So the 8-byte id64 *is* the coordinate. Shipping coords separately would
roughly double the payload for zero gain.

Deep dive: [id64-encoding.md](id64-encoding.md)

## 5. The manifest — the index

[writeManifest()](../../app/Services/GalaxyTileBakerService.php#L287-L315)
writes `public/galaxy-tiles/manifest.json` listing every populated tile key.
The frontend fetches it once and uses it to know which tile keys *exist* (so
it doesn't 404 on empty space).

Versioning note: bakes write to `v{N}/` and update the manifest atomically
(write-then-rename). Tiles are served with `Cache-Control: immutable` at
[GalaxyController.php:105](../../app/Http/Controllers/GalaxyController.php#L105),
so old versions stay cacheable while a new bake runs.

## 6. Runtime — deciding what to load

The camera state is three numbers: `theta, phi, radius` (spherical orbit
around a `target` point). See
[buildMvp()](../../../edcs-app/app/galaxy-map/components/galaxy-map-canvas.tsx#L88-L100).

On every frame, the render loop checks whether the camera moved enough to
matter; if yes, it debounces a call to
[reconcileTiles()](../../../edcs-app/app/galaxy-map/components/galaxy-map-canvas.tsx#L539-L600).

That function:

1. Picks the LOD based on `radius`.
2. Asks
   [tileKeysForView()](../../../edcs-app/app/galaxy-map/components/galaxy-map-canvas.tsx#L453-L477)
   which tiles fall within a sphere around the camera target.
3. **Evicts** tiles not in the desired set (frees GPU memory).
4. **Fetches** missing tiles in parallel (8 at a time), decodes the binary to
   `Float32Array`s, uploads them to the GPU.

This is the streaming part — you only ever hold in memory what's on screen
(plus a margin).

Deep dive: [frustum-culling.md](frustum-culling.md)

## 7. Rendering — WebGL points

Once tiles are uploaded as GPU buffers, each frame does three passes in
[draw()](../../../edcs-app/app/galaxy-map/components/galaxy-map-canvas.tsx#L609-L677):

1. **Galaxy disk** — a flat textured quad (procedural spiral painted onto a
   1024×1024 canvas) for the background glow.
2. **Starfield** — 3500 distant seeded points, fixed pixel size, for parallax.
3. **Systems** — one `drawArrays(POINTS)` per loaded tile. The vertex shader
   projects each point through the MVP matrix; the fragment shader draws a
   round soft-edged dot via `gl_PointCoord`.

Blending is **additive** (`SRC_ALPHA, ONE`) for the stars, which is why
overlapping stars get brighter — that's what gives dense regions like the core
their glow for free.

Deep dive: [webgl-rendering.md](webgl-rendering.md)

---

## Key ideas to take away

- **LOD + tiling** — the same pattern as web map tiles, adapted to 3D.
- **Exploit the data** — the id64 *is* the coordinate, so we only ship 8
  bytes per star.
- **Pre-bake, don't compute at request time** — the Artisan command does the
  expensive work once; the runtime path is just file reads.
- **Stream based on the camera** — eviction + sphere cull keeps GPU memory
  bounded regardless of galaxy size.

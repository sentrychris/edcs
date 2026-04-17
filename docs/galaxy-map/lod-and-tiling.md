# LOD and tiling — the theory

## What LOD actually means

**LOD (Level of Detail)** is a family of techniques for rendering things with
*less* detail when they're contributing less to the final image. The payoff
is twofold:

1. **Less data to ship / store** — you don't load what you won't see.
2. **Less work for the GPU** — fewer vertices, fewer fragments, fewer draw
   calls.

The principle behind it is the **sampling theorem** applied to pixels: if a
region of 3D space maps to one pixel on screen, you only need *one sample*
from that region to draw it correctly. Drawing a thousand overlapping points
into that pixel just wastes bandwidth and blends into the same colour anyway.

Classic LOD examples you've seen without noticing:

- **3D games** swap high-poly character models for low-poly ones as the
  camera pulls back.
- **Terrain engines** (Google Earth, flight sims) use "clipmaps" or
  "quadtree" terrain — a hierarchy of increasingly coarse heightmaps.
- **Mipmapping** (built into every GPU) — each texture stores itself at half
  resolution, quarter resolution, etc., so distant surfaces sample smaller
  mip levels and avoid aliasing.

The galaxy map is a **discrete LOD** scheme: three hand-picked levels with
hard switches between them. Continuous LOD (morph between levels) is
possible but rarely worth the complexity for point data.

## Why tiling, not one big download

Even at the coarsest LOD, a naïve approach might ship "the whole galaxy as
one file." That has two problems:

1. **You download data you'll never look at.** If the camera is over the
   Bubble, the stars near Colonia are 20,000 ly away and off-screen.
2. **You can't evict it.** Once downloaded and uploaded to the GPU, it sits
   there even if you zoom to the other side of the galaxy.

**Tiling** is the fix: chop the world into a grid of independently-loadable
chunks. The runtime decides which tiles are relevant *right now* and loads
only those. This is exactly what Google Maps does at
`tile.googleapis.com/v1/2dtiles/{z}/{x}/{y}` — each URL is one 256×256 image,
and the browser fetches the ~20 that cover the current viewport.

The galaxy version adds a Z axis (tiles are 3D cubes, not 2D squares) and
keys them by `{sx}_{sy}_{sz}` instead of `{x}_{y}`.

## The three levels in this project

From [GalaxyTileBakerService.php](../../app/Services/GalaxyTileBakerService.php):

### LOD 2 — native sectors (1280 ly)

- One tile per 1280-ly cube.
- Contains **every** system in that cube (no sampling).
- ~5000 tiles populated. (The galaxy is sparse — most of the ~8000³
  potential sector coordinates have zero stars.)
- Used when the camera is zoomed in close enough that sampling would drop
  visible stars.

**Why 1280 ly?** That's the native sector size of the Elite Dangerous boxel
encoding (see [id64-encoding.md](id64-encoding.md)). Matching it means the
sector index can be derived from the id64 directly, without any extra math
or lookup table.

### LOD 1 — super-sectors (5120 ly = 4×4×4 LOD 2 sectors)

- One tile per 5120-ly cube.
- Sampled to **~10,000 stars per tile** regardless of how dense the region
  is.
- The core of the galaxy (where sectors have hundreds of thousands of stars
  each) gets heavy sampling; the rim (where most sectors have a few hundred
  stars) gets light or no sampling.

**Why 4×4×4?** Arbitrary tradeoff. Larger groups mean fewer LOD 1 tiles
(less HTTP overhead per zoom level) but each tile covers more space, so
more gets loaded when the camera moves. 4×4×4 = 64 LOD 2 sectors per LOD 1
tile was picked as a reasonable middle.

### LOD 0 — global sample

- Single file: `lod0.bin`.
- Entire galaxy sampled to ~50,000 stars.
- Always loaded when zoomed out past 60,000 ly.

**Why 50k?** Empirically, that's roughly the density where:

- The spiral structure is visible (enough points to suggest arms).
- The file is small (~400 KB on the wire).
- The GPU can draw it every frame without breaking sweat.

## The threshold dance

Threshold constants in
[galaxy-map-canvas.tsx:19-20](../../../edcs-app/app/galaxy-map/components/galaxy-map-canvas.tsx#L19-L20):

```ts
const LOD0_MIN_RADIUS = 60000;
const LOD1_MIN_RADIUS = 8000;
```

The camera `radius` (distance from camera to target) drives LOD selection:

| Radius | LOD | Rationale |
|--------|-----|-----------|
| `>= 60000 ly` | 0 | Whole galaxy fits in frame; 50k samples is plenty of visual density |
| `8000 – 60000 ly` | 1 | Regional view; per-sector detail would be invisible |
| `< 8000 ly` | 2 | Close enough that individual stars are distinguishable |

These are *hard switches*, not crossfades. When you zoom through a
threshold, one LOD unloads and the next loads. Because all LODs sample
*from the same id64 pool*, stars in dense regions appear more densely as
you zoom in — they don't pop in from nowhere, they just get friends.

Tuning note: these numbers are dependent on the FOV (45°) and the rough
size of the galaxy (~100k ly across). If you changed the projection math,
you'd need to retune.

## Why only one LOD at a time

[reconcileTiles()](../../../edcs-app/app/galaxy-map/components/galaxy-map-canvas.tsx#L539-L600)
loads *either* LOD 0 *or* a set of LOD 1 tiles *or* a set of LOD 2 tiles —
never a mix.

If you mixed them, you'd double-render: a star that's in LOD 2 is *also* in
the LOD 1 sample (with 1-in-N probability) and in the LOD 0 sample. Two
point sprites at the same coordinate would composite additively and look
brighter than their neighbours for no physical reason.

Some LOD systems do mix levels (e.g. geomorphing terrain blends between
two mip levels based on distance). Those work because the data at each
level represents different things — a coarse height sample vs. a fine one.
Here, every LOD is drawn from the same pool of stars, so they interfere.

## Alternative approaches we didn't take

- **Octree / k-d tree.** A hierarchical spatial index where each node
  subdivides into 8 (octree) or 2 (k-d tree) children. More flexible but
  way more complex to bake, and the per-node overhead (pointers,
  bounding boxes) outweighs the win for data this simple.
- **Streaming with server-side frustum query.** Frontend sends the
  camera params, the backend returns matching stars. Lower client-side
  memory but requires round-trips per camera move, can't be CDN-cached,
  and the DB can't answer the query in <16ms.
- **Point clouds with decimation shaders.** Ship all 95M stars and have
  the GPU skip most of them via a vertex shader test. Modern GPUs could
  do it, but you'd have to download 760 MB first. Not happening.

The pre-baked pyramid wins because it's dumb, cacheable, and the hard work
happens once per bake rather than per request.

## Trade-offs to know

- **Staleness.** Tiles are immutable for a bake version. New stars
  discovered between bakes don't appear until the next `galaxy:bake-tiles`
  run.
- **Sampling bias.** LOD 0 / LOD 1 take every Nth star from a DB-order
  stream. DB order ≈ insertion order, which correlates with when a system
  was discovered, which correlates with how close to the Bubble it is.
  Not a uniform spatial sample. For visual purposes it's fine; don't
  treat it as a statistical sample of the galaxy.
- **Sharp LOD boundaries.** Zooming from 8001 ly to 7999 ly swaps LOD 1
  for LOD 2 — you can sometimes see a brief "pop" if tile loads lag. The
  debounce and pre-fetch margin in
  [tileKeysForView()](../../../edcs-app/app/galaxy-map/components/galaxy-map-canvas.tsx#L453-L477)
  hide this in the common case.

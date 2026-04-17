# View-volume culling — deciding what to load

"Culling" means *not drawing things you can't see*. In this project it
happens at two levels:

1. **Tile-level culling** — on the frontend, before fetching. Tiles whose
   centre is outside the camera's sphere of interest aren't downloaded.
2. **Per-star culling** — delegated to the GPU. Every star is projected
   through the MVP; the rasterizer clips anything outside the clip-space
   cube automatically.

The first is the one that matters for bandwidth and is what this doc is
about.

## The view frustum — a quick primer

A **frustum** is a pyramid with its tip chopped off. For a 3D camera with
a perspective projection, the visible volume is a frustum:

```
                    far plane
               ╔═══════════════╗
             ╱                 ╲
           ╱        visible     ╲
         ╱         volume        ╲
       ╱                          ╲
     ╱                              ╲
    ╔══════════════════════════════╗    near plane
        ↑ camera (looking right →)
```

- **Near plane** — clip stuff closer than this.
- **Far plane** — clip stuff further than this.
- **Side planes** — bound by the field-of-view angle.

The exact frustum shape depends on the current camera position /
orientation / FOV, and changes every frame as the user pans.

For the galaxy map, the matrix that produces this frustum is built in
[buildMvp()](../../../edcs-app/app/galaxy-map/components/galaxy-map-canvas.tsx#L88-L100):

```ts
mat4Multiply(
  mat4Perspective(Math.PI / 4, aspect, 100, 600000),
  mat4LookAt(eye, target, up),
);
```

- FOV = 45° (`PI / 4`).
- Near = 100 ly, Far = 600,000 ly (well past the galaxy's ~100k diameter).
- The camera is a point on a sphere of radius `radius` around `target`,
  parameterized by `theta` (azimuth) and `phi` (polar).

## Why we don't do exact frustum culling

The "textbook" approach is:

1. Extract the six frustum planes from the projection × view matrix.
2. For each tile's bounding box, test it against each plane.
3. Reject if *all eight corners* are on the outside of *any* plane.

This is precise — loads only tiles the user could theoretically see. It's
also fiddly: you have to build the plane equations correctly, handle
AABB-vs-plane tests, and it still returns a big set of tiles when you're
looking lengthwise down a spiral arm.

We don't do this. Instead we use…

## Spherical culling — a blunt but effective approximation

[tileKeysForView()](../../../edcs-app/app/galaxy-map/components/galaxy-map-canvas.tsx#L453-L477):

```ts
function tileKeysForView(target, radius, tileSize, populated): Set<string> {
  const reach = radius * 1.5 + tileSize;
  const reach2 = reach * reach;
  const half = tileSize / 2;

  const out = new Set<string>();
  for (const key of populated) {
    const [sx, sy, sz] = key.split("_").map(Number);
    const cx = sx * tileSize + half - BASE_X;
    const cy = sy * tileSize + half - BASE_Y;
    const cz = sz * tileSize + half - BASE_Z;
    const dx = cx - target.x;
    const dy = cy - target.y;
    const dz = cz - target.z;
    if (dx * dx + dy * dy + dz * dz <= reach2) {
      out.add(key);
    }
  }
  return out;
}
```

In English: **keep every tile whose centre is within `reach` of the
camera target**, using squared distance to avoid the `sqrt`.

## The `reach` formula

```ts
const reach = radius * 1.5 + tileSize;
```

Two components:

### `radius * 1.5`

The camera is `radius` away from `target`, looking *at* `target`. The
frustum extends past the target too — you can see stars on the far side,
roughly as far as your near-side radius. So the sphere of interest has
diameter ≈ `2 * radius`, centred on `target`.

Why `1.5` and not `2`? Tighter margin. The far edge of the frustum is
bounded by the far clip plane (600,000 ly) and *also* by the FOV — at
FOV 45°, the visible slice at distance `2r` from the camera is only as
wide as `~2r / cos(22.5°)`. In practice `1.5 * radius` comfortably
covers what's on-screen without over-loading the back hemisphere where
stars are small on the screen and not doing much work.

It's a deliberate under-approximation. Spherical culling is already
generous — any direction is "visible" from its perspective — so trimming
the backside wins us load savings without visible pop-in.

### `+ tileSize`

A tile is a cube of side `tileSize`. We test against its *centre*. A
corner of that cube could be up to `sqrt(3)/2 * tileSize ≈ 0.87 *
tileSize` further from the camera than the centre. Adding `tileSize` is
a conservative over-estimate that guarantees we include every tile
whose *volume* intersects the sphere, not just its centre.

## Why a sphere, not a box

An earlier version used an axis-aligned box (min/max in each axis
independently). That produced a visible square cut-off at the cull edge
as you zoomed — stars disappeared along straight horizontal and vertical
lines because the box corners were much further out than its sides.

A sphere is radially symmetric. Tiles fade out at the same distance
regardless of direction, so the boundary is invisible when it's
pre-fetched with some slack.

## Why we don't also frustum-cull after the sphere

We *could* take the sphere-passing set and run a frustum test against each
to drop a few more tiles. It's considered and rejected on these grounds:

- **Cheap to fetch once, expensive to flicker.** If the user orbits the
  camera, a tile that was "outside the frustum" a moment ago is about to
  come into view. Spherical culling keeps it loaded through the turn.
- **Not many tiles to begin with.** At LOD 2 (tightest zoom), the sphere
  intersects ~50–200 native sectors depending on density. That's well
  under 100 HTTP requests after the first load, and the set stabilises
  as you pan.
- **CPU budget is trivial.** The sphere test runs over ~5000 populated
  LOD 2 keys — ~50 µs on any modern device.

The downside: more tiles sit in memory than strictly needed. With 28 B
per star and ~200 tiles at LOD 2, that's maybe 50 MB of VRAM — well
under any budget.

## Rebuilding the set — when does it run

From [startLoop()](../../../edcs-app/app/galaxy-map/components/galaxy-map-canvas.tsx#L679-L698):

```ts
if (Math.abs(r - lastRadius) > 200 || dx*dx + dy*dy + dz*dz > 200*200) {
  lastRadius = r;
  lastTarget = { ...targetRef.current };
  scheduleReconcile();
}
```

Reconciliation only fires if the camera moved **significantly** (>200 ly
of target drift, or >200 ly of zoom). This avoids re-running the set
calculation on every single `requestAnimationFrame` frame.

`scheduleReconcile()` then adds a **120 ms trailing debounce** so a burst
of pan/zoom events only triggers one network-hitting reconcile at the
end.

## Eviction

When the desired set is computed, anything in the existing cache not in
the new set is deleted:

```ts
for (const [key, buf] of s.tiles) {
  if (!desired.has(key)) {
    deleteTile(s.gl, buf);   // gl.deleteBuffer on the three attribute buffers
    s.tiles.delete(key);
  }
}
```

Eviction is important because WebGL buffers don't garbage-collect on
their own — they live as long as the JS-side handle does. Without
eviction, zooming around the galaxy would leak every tile you'd ever
looked at.

## The LOD switch case

When you zoom through 8000 ly or 60000 ly, the LOD changes. The desired
set is rebuilt with new keys (e.g. all `lod1:*` instead of `lod2:*`),
*all* existing tiles fall out of the desired set and get evicted, and
the new-LOD tiles fetch in. Briefly during the fetch you're looking at a
blank frame before new tiles arrive — that's the "pop" mentioned in
[lod-and-tiling.md](lod-and-tiling.md).

A more sophisticated implementation would keep the old-LOD tiles drawn
until enough of the new-LOD tiles have arrived, then swap. Not done
here because the pop is brief and the simplicity is worth more than the
smoothness.

## Per-star culling — handled implicitly by the GPU

Even for the tiles we do load, most stars in the tile are probably not
on screen. We don't care. Each star is a single vertex; the vertex
shader projects it through the MVP; if the result is outside
`-w ≤ x,y,z ≤ w` clip space, the rasterizer silently drops it.

For a modern GPU, rejecting 100,000 off-screen points takes microseconds.
The "correct" optimisation would be per-tile bounding spheres discarded
before the draw call, but with a handful of tiles each containing tens
of thousands of points, we're nowhere near the GPU's limit — so we just
let the hardware do it.

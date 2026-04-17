# Galaxy map

Documentation for the galaxy-map pipeline: backend tile baker, wire
format, and the frontend WebGL renderer.

## Documents

- **[overview.md](overview.md)** — end-to-end walkthrough from database
  to pixels. The map.
- **[lod-and-tiling.md](lod-and-tiling.md)** — why LOD exists, how the
  three-level tile pyramid is laid out, and the tradeoffs vs. other
  spatial indexing schemes.
- **[id64-encoding.md](id64-encoding.md)** — the Elite Dangerous boxel
  encoding. How a 64-bit ID contains its own coordinates, and why that
  lets us cut the wire payload in half.
- **[binary-packing.md](binary-packing.md)** — the tile wire format. PHP
  `pack()`, JS `DataView`, atomic rename, and the count-fixup trick.
- **[frustum-culling.md](frustum-culling.md)** — how the frontend
  decides which tiles to load based on the camera. Sphere-vs-frustum
  tradeoffs and the `reach` formula.
- **[webgl-rendering.md](webgl-rendering.md)** — a WebGL primer aimed
  at web devs: vertex/fragment shaders, the MVP matrix, blending
  modes, and why each star is one point sprite.
- **[bake-performance.md](bake-performance.md)** — how the baker
  handles 95M rows in bounded memory. DB cursor streaming, the LRU
  file-handle cache, atomic versioning, and where parallelism would
  go if we needed it.

## Relevant code

### Backend

- [app/Services/GalaxyTileBakerService.php](../../app/Services/GalaxyTileBakerService.php)
  — the baker (all three LOD passes + manifest writer).
- [app/Console/Commands/BakeGalaxyTilesCommand.php](../../app/Console/Commands/BakeGalaxyTilesCommand.php)
  — `php artisan galaxy:bake-tiles`.
- [app/Http/Controllers/GalaxyController.php](../../app/Http/Controllers/GalaxyController.php)
  — serves `manifest.json` and tile files through Laravel (for CORS).

### Frontend

- [app/galaxy-map/components/galaxy-map-canvas.tsx](../../../edcs-app/app/galaxy-map/components/galaxy-map-canvas.tsx)
  — the WebGL renderer, tile fetcher, and camera controls. Everything
  lives in one file.
- [core/string-utils.ts#L50](../../../edcs-app/core/string-utils.ts#L50)
  — `getBoxelDataFromId64` (id64 bit-unpacking).

## Operations

Bake a new tile set:

```bash
vendor/bin/sail artisan galaxy:bake-tiles
```

Prune old versions after a successful bake:

```bash
vendor/bin/sail artisan galaxy:bake-tiles --prune
```

Output goes to `public/galaxy-tiles/v{N}/` with `manifest.json` at the
top level. Tiles are served at `/api/galaxy/tiles/v{N}/...`.

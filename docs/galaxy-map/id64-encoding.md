# The id64 trick — coordinates inside the ID

The single most important bandwidth win in the pipeline is that **we don't
ship coordinates at all**. Elite Dangerous' `id64` is not just an opaque
primary key — it's a packed structure that encodes the star's position.
Given the 8-byte id64, the frontend reconstructs `(x, y, z)` without any
DB lookup.

This doc unpacks how that encoding works.

## The physical meaning

Frontier's galaxy is divided into a regular grid of **sectors**, each
1280 ly × 1280 ly × 1280 ly. Each sector is further subdivided into
**boxels** — smaller cubes inside a sector. A boxel is *the smallest
region that can contain a procedurally-generated system*; systems don't
have continuous coordinates, they snap to a boxel centre.

The boxel size depends on how dense the region is (the "mass code"):

| mc | Boxel size | Use |
|----|-----------|-----|
| 0 | 10 ly    | Hand-placed / dense regions |
| 1 | 20 ly    | Standard density |
| 2 | 40 ly    | |
| 3 | 80 ly    | |
| 4 | 160 ly   | |
| 5 | 320 ly   | |
| 6 | 640 ly   | Sparse / rim |
| 7 | 1280 ly  | Extreme rim; one boxel = one sector |

Bigger boxels = fewer possible boxel positions within a sector, which is
why the bit layout trades between `sector` and `boxel` bits based on `mc`
(see below).

## The bit layout of an id64

Packed little-endian into 64 bits, bottom-up:

```
bit 0         bit 3                                                  bit 64
 │             │                                                       │
 ▼             ▼                                                       ▼
┌────┐ ┌───────────┐ ┌────────┐ ┌───────────┐ ┌────────┐ ┌───────────┐ ┌────────┐ ┌──────────┐ ┌────────┐
│ mc │ │ boxel Z   │ │ sec Z  │ │ boxel Y   │ │ sec Y  │ │ boxel X   │ │ sec X  │ │ n2       │ │ body   │
│ 3b │ │ 7 - mc b  │ │ 7 b    │ │ 7 - mc b  │ │ 6 b    │ │ 7 - mc b  │ │ 7 b    │ │ variable │ │ 9 b    │
└────┘ └───────────┘ └────────┘ └───────────┘ └────────┘ └───────────┘ └────────┘ └──────────┘ └────────┘
```

Key points:

- The **mass code** (`mc`) lives in the bottom 3 bits and determines how
  wide the boxel fields are. Larger `mc` ⇒ bigger boxels ⇒ fewer boxels
  per sector ⇒ fewer bits needed to index them.
- **Sector** fields are fixed width (7 bits X/Z, 6 bits Y — the galaxy is
  flatter than it is wide).
- **Boxel** fields shrink as `mc` grows: `7 - mc` bits each.
- The remaining bits hold an unused `n2` scratch field and the 9-bit
  `body_id` identifying a specific body within the system.

## Decoding — the TS reference implementation

[getBoxelDataFromId64()](../../../edcs-app/core/string-utils.ts#L50) peels
the fields off with a right-shift-and-mask:

```ts
const rshift = (value: bigint, bits: bigint) => [
  value >> bits,             // what's left after consuming `bits`
  value & (2n ** bits - 1n), // the `bits`-wide chunk we just consumed
];
```

So `rshift(id64, 3n)` returns `[remaining, mc]` where `mc` is the bottom 3
bits. Then it proceeds up the stack:

```ts
const [i1, mc]      = rshift(id64, 3n);           // mass code
const [i2, boxelZ]  = rshift(i1, 7n - mc);        // Z boxel index
const [i3, sectorZ] = rshift(i2, 7n);             // Z sector index
const [i4, boxelY]  = rshift(i3, 7n - mc);        // Y boxel
const [i5, sectorY] = rshift(i4, 6n);             // Y sector (6, not 7)
const [i6, boxelX]  = rshift(i5, 7n - mc);        // X boxel
const [i7, sectorX] = rshift(i6, 7n);             // X sector
```

The order matters: you must consume `mc` first because every subsequent
field width depends on it.

**Why `bigint`?** JavaScript numbers are IEEE 754 doubles with 53 bits of
integer precision. An id64 needs the full 64, so we must use `BigInt`.
Paying the `BigInt` tax on every decode is fine because it's a handful of
integer operations.

## From sector/boxel to world coordinates

Once we have `sector` and `boxel`, the galactic coordinate is:

```ts
// Each sector is 1280 ly. Each boxel is `boxel.size` ly.
x = sector.x * 1280 + boxel.x * boxel.size + boxel.size / 2
y = sector.y * 1280 + boxel.y * boxel.size + boxel.size / 2
z = sector.z * 1280 + boxel.z * boxel.size + boxel.size / 2
```

The `+ size / 2` puts the system at the **centre** of its boxel, not the
corner.

Then we apply the Sgr A* offset so the black hole sits at `(0, 0, 0)`:

```ts
x -= BASE_X; // 50240
y -= BASE_Y; // 41280
z -= BASE_Z; // 50240
```

`BASE_X/Y/Z` were derived from Sgr A*'s known id64 (20578934 → sector
(39, 32, 39), boxel (0, 0, 0), size 640). Working this back:

```
sector_x * 1280 + boxel_x * 640 + 320 = 39 * 1280 + 0 + 320 = 50240
```

The whole frontend treats Sgr A* as the origin, which is a sensible
convention for a galaxy-centric view.

## Why this is a huge deal for payload size

A typical alternative would be:

```json
{ "id64": 10477373803, "x": 0, "y": 0, "z": 0 }
```

That's roughly 50 bytes of JSON per system. At 95M systems that's 4.75 GB.

Packing coordinates as floats instead — 3 × 4 = 12 bytes — would give us:

```
[uint64 id][float32 x][float32 y][float32 z] = 20 bytes per system
```

95M × 20 B = **1.9 GB**.

But since id64 *contains* (x, y, z), we only ship id64:

```
[uint64 id] = 8 bytes per system
```

95M × 8 B = **760 MB**.

And after LOD sampling (50k for LOD 0, ~10k/tile for LOD 1, native for
LOD 2), the runtime footprint is a few MB per active view.

The cost for this 2.5× saving: ~20 lines of bit-twiddling on the client.
Excellent trade.

## What the backend does with id64

On the backend, the baker treats id64 as opaque — it reads coords from
the database columns (`coords_x`, `coords_y`, `coords_z`) and writes id64
bytes into tiles. It does *not* decode id64 itself. There's an asymmetry:

- **Bake side** knows coords (from DB) → computes sector index → writes
  id64 into the matching tile file.
- **Render side** receives id64 → decodes to coords → plots.

This means the backend *could* pack (id64, x, y, z) and save the client
from bit-twiddling. But payload size won, and the decode is fast enough to
happen synchronously during tile upload.

## Encoding (the inverse)

We don't currently *encode* id64s on the frontend — decoding is enough
for rendering. If you ever need to, it's a straight reversal: shift each
field left by its width and OR them together, starting from the top. Watch
out for the variable width on boxel fields (depends on your chosen `mc`).

For finding a system by coordinate, the backend-side pattern is to
compute the sector index and look up the tile, rather than inverting id64.

## Related: Sol's id64

`SOL_ID64 = 10477373803n` is hard-coded in the frontend because Sol should
always be distinguishable regardless of whether the origin is Sgr A* or
somewhere else. The render path checks `id64 === SOL_ID64` and gives it a
distinct colour/size.

If the project ever re-origins on Sol, `BASE_X/Y/Z` would change and Sol's
render coords would be `(0, 0, 0)` — but its id64 stays the same because
id64 is an absolute galactic address.

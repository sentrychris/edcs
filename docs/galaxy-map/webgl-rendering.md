# WebGL rendering — from tile buffers to pixels

This is the graphics primer, tailored to the 350-odd lines of WebGL in
[galaxy-map-canvas.tsx](../../../edcs-app/app/galaxy-map/components/galaxy-map-canvas.tsx).
If you've only done DOM work, the mental model here is **very** different
from "the browser paints what I tell it to." This doc tries to bridge the
gap.

## What WebGL actually is

WebGL is a thin JavaScript wrapper around **OpenGL ES 2.0** — a state
machine that lives on the GPU. You don't call "draw this star." You:

1. Upload some data (vertex attributes, textures) into GPU memory.
2. Upload some programs (vertex + fragment shaders) written in GLSL.
3. Configure a bunch of state (which buffers bind to which attributes,
   which program is active, what blend mode, etc.).
4. Say "run the program across N vertices" (`gl.drawArrays`) and the
   GPU fires off the shader for each vertex, rasterises triangles /
   points, and blends the fragment shader output into the framebuffer.

The "state machine" part is the jarring bit: `gl.bindBuffer(...)` doesn't
*do* anything visible, it just remembers "this is the current buffer,"
and the next call that uses "the current buffer" picks it up. Much of the
WebGL code in this project is state setup, not drawing.

## The two shaders

Every draw call runs through:

### Vertex shader — runs once per input point

```glsl
attribute vec3  a_position;   // per-vertex: galactic coords (ly)
attribute vec3  a_color;      // per-vertex: RGB
attribute float a_size;       // per-vertex: base point size
uniform   mat4  u_mvp;        // set once per draw: camera matrix
uniform   float u_fixedSize;  // -1 → use size attribute; else force this pixel size
varying   vec3  v_color;      // pass-through to fragment shader

void main() {
  gl_Position = u_mvp * vec4(a_position, 1.0);
  v_color = a_color;
  gl_PointSize = u_fixedSize > 0.0
    ? u_fixedSize
    : clamp(a_size * (8000.0 / max(gl_Position.w, 1.0)), 0.5, 8.0);
}
```

- `gl_Position` is the special built-in output: where on screen this
  vertex lands (in homogeneous clip space, before the GPU divides by W).
- `gl_PointSize` is another built-in — for `POINTS` primitives it sets
  the size in pixels of the sprite the rasterizer will draw.
- The size formula is **perspective-correct scaling**: far stars are
  smaller. `gl_Position.w` is roughly the distance from the camera in
  clip space, so `8000 / w` gives you an inverse falloff. `clamp(0.5,
  8.0)` stops nearby stars filling the screen and distant ones shrinking
  to nothing.

### Fragment shader — runs once per pixel of each point

```glsl
precision mediump float;
varying vec3 v_color;

void main() {
  vec2 c = gl_PointCoord - vec2(0.5);     // [-0.5, 0.5] centred
  float d = length(c) * 2.0;              // [0, 1.414] radial distance
  if (d > 1.0) discard;                   // round mask
  gl_FragColor = vec4(v_color, pow(1.0 - d, 1.8) * 0.88);
}
```

- `gl_PointCoord` is `[0, 1]` across the rasterised point sprite.
- Subtracting `0.5` gives us a coord centred at the middle of the sprite.
- `discard` is a GLSL keyword meaning "don't write this pixel at all,"
  which is how we make points *round* instead of *square*.
- The alpha `pow(1 - d, 1.8)` is a soft falloff — bright centre, fuzzy
  edge. Combined with additive blending, overlapping stars glow.

That's *the entire rendering* of stars. Three attributes in, two
built-ins out. The GPU runs this for every point on every frame.

## The MVP matrix

This is where web devs usually feel most lost, so here's the crash course.

To place a 3D point on a 2D screen you compose three transforms, all as
4×4 matrices, multiplied together:

```
gl_Position = Projection × View × Model × vertex
              └───────────── MVP ──────────┘
```

- **Model**: moves a vertex from "object-local" space into world space.
  (We don't have one; stars already live in world space — call it
  identity.)
- **View**: moves from world space into "camera space" — i.e. rotates
  and translates the world so the camera sits at the origin looking down
  the −Z axis.
- **Projection**: warps camera space into "clip space" — the GPU expects
  a specific cube `[-w, w]` that the rasterizer interprets.

Because the View undoes the camera's placement and the Projection does
the perspective foreshortening, a single matrix ends up moving a vertex
from `(x_world, y_world, z_world)` to a 2D screen position (after the
automatic W-divide and viewport transform).

In this project,
[buildMvp()](../../../edcs-app/app/galaxy-map/components/galaxy-map-canvas.tsx#L88-L100)
builds it from three parameters — `theta`, `phi`, `radius`:

```ts
const ex = tx + radius * Math.sin(phi) * Math.sin(theta);
const ey = ty + radius * Math.cos(phi);
const ez = tz + radius * Math.sin(phi) * Math.cos(theta);
```

That's a standard **spherical-to-cartesian** conversion: the camera
orbits the target at a fixed distance, with `theta` around the Y axis
and `phi` pitching up/down.

Then:

```ts
mat4Multiply(
  mat4Perspective(Math.PI / 4, aspect, 100, 600000),
  mat4LookAt(ex, ey, ez, tx, ty, tz, 0, 1, 0),
);
```

- `mat4LookAt(eye, target, up)` builds the view matrix that points
  `eye` at `target` with the world's Y axis as "up."
- `mat4Perspective(fov, aspect, near, far)` builds the standard WebGL
  perspective projection. FOV is 45°, near 100 ly, far 600,000 ly.
- `mat4Multiply(P, V)` combines them. Column-major order, which means
  the multiplication looks "reversed" compared to the mathematical
  notation — matrix B is stored as columns-first, and `P × V` in code
  is written `multiply(P, V)`.

The result is uploaded once per frame as `uniform mat4 u_mvp`.

## Buffers, attributes, and draw calls

For each loaded tile we have three GPU buffers:

| JS name | GLSL attribute | Size | Use |
|---------|----------------|------|-----|
| `posBuf` | `a_position` | `vec3` (3 × f32) | Star's galactic coords |
| `colBuf` | `a_color` | `vec3` (3 × f32) | RGB |
| `sizeBuf` | `a_size` | `float` | Per-star size multiplier |

To draw a tile we:

1. `gl.bindBuffer(ARRAY_BUFFER, posBuf)` — "this is the current buffer."
2. `gl.vertexAttribPointer(a_position_loc, 3, FLOAT, false, 0, 0)` —
   "bind the current buffer to `a_position` as 3 floats per vertex,
   tightly packed, no offset."
3. Repeat for `colBuf` and `sizeBuf`.
4. `gl.drawArrays(POINTS, 0, count)` — "run the program across `count`
   points."

The attribute bindings stay until you change them, but we rebind per
tile because each tile has its own buffers.

## The render order — three passes per frame

In [draw()](../../../edcs-app/app/galaxy-map/components/galaxy-map-canvas.tsx#L609-L677):

### Pass 0 — galaxy disk

A **textured flat quad** (4 vertices, `TRIANGLE_STRIP`) lying in the XZ
plane at `y = -200`. The texture is a procedural spiral painted onto an
offscreen `<canvas>` in
[makeGalaxyTexture()](../../../edcs-app/app/galaxy-map/components/galaxy-map-canvas.tsx#L236-L314)
using 2D canvas APIs (`radialGradient`, etc.) — *not* GLSL. That canvas
is then uploaded once as a WebGL texture.

Drawn with **alpha blending** (`SRC_ALPHA, ONE_MINUS_SRC_ALPHA`) so it
sits behind everything else like a painted backdrop.

### Pass 1 — background starfield

3500 points scattered on a sphere of radius ~350,000 ly, generated with
a deterministic LCG
([buildStarfield()](../../../edcs-app/app/galaxy-map/components/galaxy-map-canvas.tsx#L208-L232)).
They're always drawn, regardless of LOD — they give you a sense of
*elsewhere* while the galaxy stars stream in.

Drawn with **additive blending** (`SRC_ALPHA, ONE`) and the shared star
shader at a forced pixel size (`u_fixedSize = 1.4`).

### Pass 2 — galaxy stars

For each tile in the current LOD's cached set:

```ts
gl.bindBuffer(ARRAY_BUFFER, tile.posBuf);
gl.vertexAttribPointer(a_position, 3, FLOAT, false, 0, 0);
// ... similarly for color, size ...
gl.drawArrays(POINTS, 0, tile.count);
```

One draw call per tile. At LOD 2 with 200 tiles on screen, that's 200
draw calls per frame. That's *fine* for modern GPUs but worth knowing —
if we ever wanted to scale further, batching multiple tiles into one
buffer (or using `ANGLE_instanced_arrays`) would help.

## Blending modes

Blending controls how a new fragment combines with the pixel already in
the framebuffer. Two are used here:

### Alpha blending — `SRC_ALPHA, ONE_MINUS_SRC_ALPHA`

```
out = src.rgb * src.a + dst.rgb * (1 - src.a)
```

The "paint on top of the existing image" mode. Used for the galaxy
disk so it obscures black space behind it without brightening the
starfield in front.

### Additive blending — `SRC_ALPHA, ONE`

```
out = src.rgb * src.a + dst.rgb
```

The "add light to the existing image" mode. Used for stars. Two stars
overlapping in the same pixel *accumulate* — you get `2 × star_brightness`,
clipped at 1.0 by the display. This is physically correct-ish for
emissive sources (two candles are brighter than one) and it's **why the
core of the galaxy naturally glows** without any special "density
shading" code. The core has more stars → more additive contributions →
brighter pixel.

Additive is also why the order of drawing star-on-star doesn't matter —
addition is commutative. That's convenient because we don't bother
sorting by depth.

## Why points and not spheres

Each star is *one vertex rendered as a point sprite*, not a 3D ball. The
tradeoffs:

- **Cheap.** One vertex, one rasterised quad of maybe 2×2 pixels.
- **Correct enough at this scale.** Stars are a point when viewed from
  100s of ly away anyway.
- **No depth sorting needed.** Additive + round falloff = looks like
  glow from any angle.

If we wanted each star to look like a *sun* up close (corona, light
rays, flares), we'd switch to billboarded quads with a more complex
fragment shader. Out of scope.

## Device pixel ratio

One WebGL gotcha worth calling out — HiDPI / retina displays:

```ts
const dpr = window.devicePixelRatio || 1;
const w = Math.round(canvas.clientWidth * dpr);
const h = Math.round(canvas.clientHeight * dpr);
if (canvas.width !== w || canvas.height !== h) {
  canvas.width = w; canvas.height = h;
  gl.viewport(0, 0, w, h);
}
```

A canvas has two sizes: its **CSS size** (what the browser lays out) and
its **backing size** (`canvas.width / height`, how many pixels WebGL
actually draws). If they mismatch, the browser scales between them and
everything looks blurry.

On a 2× retina display, we make the backing store 2× the CSS size so
WebGL draws at native resolution. Without this the stars would be blurry
and `gl_PointSize = 1.4` wouldn't be one CSS pixel, it'd be half a
native pixel (effectively invisible).

## Where to read next

- The official [WebGL2 fundamentals](https://webgl2fundamentals.org/) is
  the best progressive intro to the API. Old but excellent. (We're on
  WebGL 1 here, but the concepts carry over.)
- For the matrix math, [Learn OpenGL — Coordinate Systems](https://learnopengl.com/Getting-started/Coordinate-Systems)
  is the canonical explanation of MVP.
- For point cloud techniques specifically, Potree's documentation is a
  good window into how you scale this further than three LOD levels.

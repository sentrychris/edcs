<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Full Client Footprint</title>
<style>
  * { box-sizing: border-box; }

  body {
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    background: #0f172a;
    color: #e5e7eb;
    margin: 0;
    padding: 16px;
  }

  h1 { margin: 0 0 6px; font-size: clamp(1.5rem, 5vw, 2.2rem); }

  .sub { color: #94a3b8; margin-bottom: 20px; font-size: 0.95rem; }

  .grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 320px), 1fr));
    gap: 16px;
    width: 100%;
  }

  .card {
    background: #020617;
    border: 1px solid #1e293b;
    border-radius: 12px;
    padding: 14px;
    min-width: 0;
  }

  .card h2 { font-size: 1rem; color: #38bdf8; margin: 0 0 12px; }

  .row {
    display: grid;
    grid-template-columns: 150px minmax(0, 1fr);
    gap: 10px;
    font-size: 0.8rem;
    padding: 7px 0;
    border-bottom: 1px solid #111827;
  }

  .row:last-child { border-bottom: none; }

  .label { color: #94a3b8; }

  .value {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    overflow-wrap: anywhere;
    word-break: break-word;
  }

  .muted { color: #64748b; }

  details { margin-top: 20px; }

  summary { cursor: pointer; color: #38bdf8; margin-bottom: 10px; font-weight: 600; }

  pre {
    background: #020617;
    padding: 14px;
    border-radius: 12px;
    border: 1px solid #1e293b;
    font-size: 0.75rem;
    overflow-x: auto;
    max-width: 100%;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
  }

  @media (max-width: 640px) {
    body { padding: 12px; }
    .grid { gap: 12px; }
    .card { padding: 12px; border-radius: 10px; }
    .row { grid-template-columns: 1fr; gap: 3px; font-size: 0.78rem; }
    .label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em; }
  }
</style>
</head>
<body>

<h1>Full Client Footprint</h1>
<div class="sub">Browser, fingerprint, server and IP intelligence diagnostics.</div>

<div class="grid">
  <div class="card"><h2>Network</h2><div id="network"></div></div>
  <div class="card"><h2>Device</h2><div id="device"></div></div>
  <div class="card"><h2>Display</h2><div id="display"></div></div>
  <div class="card"><h2>Locale</h2><div id="locale"></div></div>
  <div class="card"><h2>WebGL</h2><div id="webgl"></div></div>
  <div class="card"><h2>Fingerprint</h2><div id="fp"></div></div>
  <div class="card"><h2>Storage</h2><div id="storage"></div></div>
  <div class="card"><h2>Permissions</h2><div id="permissions"></div></div>
  <div class="card"><h2>Media</h2><div id="media"></div></div>
</div>

<details>
  <summary>Raw diagnostics</summary>
  <pre id="raw">Loading...</pre>
</details>

<script>
const ECHO_URL = "{{ route('footprint.echo') }}";
const LOG_URL  = "{{ route('footprint.log') }}";

const safeValue = value => {
  if (value === undefined || value === null || value === "") {
    return '<span class="muted">N/A</span>';
  }
  if (Array.isArray(value)) {
    return value.length ? value.join(", ") : '<span class="muted">N/A</span>';
  }
  if (typeof value === "object") {
    return JSON.stringify(value);
  }
  return String(value);
};

const row = (key, value) =>
  `<div class="row"><div class="label">${key}</div><div class="value">${safeValue(value)}</div></div>`;

const render = (id, data) => {
  document.getElementById(id).innerHTML =
    data.map(([key, value]) => row(key, value)).join("");
};

async function sha256(str) {
  if (window.crypto && crypto.subtle) {
    const buf = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(str));
    return [...new Uint8Array(buf)].map(x => x.toString(16).padStart(2, "0")).join("");
  }
  let hash = 0;
  for (let i = 0; i < str.length; i++) {
    hash = ((hash << 5) - hash) + str.charCodeAt(i);
    hash |= 0;
  }
  return "fallback-" + Math.abs(hash).toString(16);
}

async function canvasFP() {
  try {
    const canvas = document.createElement("canvas");
    canvas.width = 300;
    canvas.height = 80;
    const ctx = canvas.getContext("2d");
    if (!ctx) return null;
    ctx.textBaseline = "top";
    ctx.font = "16px Arial";
    ctx.fillText("fingerprint-test", 10, 10);
    ctx.font = "18px Times New Roman";
    ctx.fillText("∆ Ω Ж @ £ € ✓", 10, 35);
    return await sha256(canvas.toDataURL());
  } catch (e) {
    return "error-" + e.message;
  }
}

function webgl() {
  try {
    const canvas = document.createElement("canvas");
    const gl = canvas.getContext("webgl") || canvas.getContext("experimental-webgl");
    if (!gl) return null;
    const debug = gl.getExtension("WEBGL_debug_renderer_info");
    return {
      vendor: gl.getParameter(gl.VENDOR),
      renderer: gl.getParameter(gl.RENDERER),
      version: gl.getParameter(gl.VERSION),
      shadingLanguageVersion: gl.getParameter(gl.SHADING_LANGUAGE_VERSION),
      unmaskedVendor: debug ? gl.getParameter(debug.UNMASKED_VENDOR_WEBGL) : null,
      unmaskedRenderer: debug ? gl.getParameter(debug.UNMASKED_RENDERER_WEBGL) : null,
      maxTextureSize: gl.getParameter(gl.MAX_TEXTURE_SIZE),
      maxViewportDims: gl.getParameter(gl.MAX_VIEWPORT_DIMS),
    };
  } catch { return null; }
}

async function permissions() {
  if (!navigator.permissions) return null;
  const names = ["geolocation", "notifications", "camera", "microphone"];
  const result = {};
  for (const name of names) {
    try {
      const permission = await navigator.permissions.query({ name });
      result[name] = permission.state;
    } catch { result[name] = "unsupported"; }
  }
  return result;
}

async function storage() {
  try {
    if (!navigator.storage || !navigator.storage.estimate) return null;
    return await navigator.storage.estimate();
  } catch { return null; }
}

async function media() {
  try {
    if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) return null;
    const devices = await navigator.mediaDevices.enumerateDevices();
    return {
      total: devices.length,
      audioInputs: devices.filter(x => x.kind === "audioinput").length,
      audioOutputs: devices.filter(x => x.kind === "audiooutput").length,
      videoInputs: devices.filter(x => x.kind === "videoinput").length,
    };
  } catch { return null; }
}

async function uaHints() {
  try {
    if (!navigator.userAgentData) return null;
    return await navigator.userAgentData.getHighEntropyValues([
      "architecture", "bitness", "brands", "fullVersionList",
      "mobile", "model", "platform", "platformVersion", "uaFullVersion", "wow64",
    ]);
  } catch { return null; }
}

function connectionInfo() {
  const c = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
  if (!c) return null;
  return { effectiveType: c.effectiveType, downlink: c.downlink, rtt: c.rtt, saveData: c.saveData };
}

function client() {
  return {
    timestamp: new Date().toISOString(),
    navigator: {
      userAgent: navigator.userAgent,
      platform: navigator.platform,
      language: navigator.language,
      languages: navigator.languages,
      cookieEnabled: navigator.cookieEnabled,
      doNotTrack: navigator.doNotTrack,
      hardwareConcurrency: navigator.hardwareConcurrency,
      deviceMemory: navigator.deviceMemory,
      maxTouchPoints: navigator.maxTouchPoints,
      webdriver: navigator.webdriver,
    },
    screen: {
      width: screen.width,
      height: screen.height,
      availWidth: screen.availWidth,
      availHeight: screen.availHeight,
      colorDepth: screen.colorDepth,
      pixelDepth: screen.pixelDepth,
    },
    viewport: {
      width: window.innerWidth,
      height: window.innerHeight,
      devicePixelRatio: window.devicePixelRatio,
    },
    locale: {
      timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
      dateString: new Date().toString(),
      utcString: new Date().toUTCString(),
    },
    page: {
      href: location.href,
      origin: location.origin,
      pathname: location.pathname,
      search: location.search,
      hash: location.hash,
      referrer: document.referrer,
    },
    connection: connectionInfo(),
  };
}

async function run() {
  const c = client();
  const hints = await uaHints();
  const gl = webgl();
  const fp = await canvasFP();
  const perm = await permissions();
  const stor = await storage();
  const med = await media();

  render("device", [
    ["User agent", c.navigator.userAgent],
    ["Platform", c.navigator.platform],
    ["UA platform", hints?.platform],
    ["UA architecture", hints?.architecture],
    ["UA bitness", hints?.bitness],
    ["UA model", hints?.model],
    ["Mobile", hints?.mobile],
    ["CPU cores", c.navigator.hardwareConcurrency],
    ["Memory GB", c.navigator.deviceMemory],
    ["Touch points", c.navigator.maxTouchPoints],
    ["Cookies", c.navigator.cookieEnabled],
    ["Do Not Track", c.navigator.doNotTrack],
    ["WebDriver", c.navigator.webdriver],
  ]);

  render("display", [
    ["Screen", `${c.screen.width} × ${c.screen.height}`],
    ["Available", `${c.screen.availWidth} × ${c.screen.availHeight}`],
    ["Viewport", `${c.viewport.width} × ${c.viewport.height}`],
    ["Pixel ratio", c.viewport.devicePixelRatio],
    ["Colour depth", c.screen.colorDepth],
    ["Pixel depth", c.screen.pixelDepth],
  ]);

  render("locale", [
    ["Timezone", c.locale.timezone],
    ["Language", c.navigator.language],
    ["Languages", c.navigator.languages],
    ["Local time", c.locale.dateString],
    ["UTC time", c.locale.utcString],
  ]);

  render("webgl", [
    ["Vendor", gl?.vendor],
    ["Renderer", gl?.renderer],
    ["Version", gl?.version],
    ["Shader version", gl?.shadingLanguageVersion],
    ["Unmasked vendor", gl?.unmaskedVendor],
    ["Unmasked renderer", gl?.unmaskedRenderer],
    ["Max texture", gl?.maxTextureSize],
    ["Max viewport", gl?.maxViewportDims],
  ]);

  render("fp", [
    ["Canvas hash", fp],
    ["UA brands", hints?.brands],
    ["Full versions", hints?.fullVersionList],
  ]);

  render("storage", [
    ["Quota", stor?.quota],
    ["Usage", stor?.usage],
    ["Usage %", stor?.quota ? ((stor.usage / stor.quota) * 100).toFixed(4) + "%" : null],
  ]);

  render("permissions", Object.entries(perm || { permissions: "Unavailable" }));

  render("media", [
    ["Devices", med?.total],
    ["Audio inputs", med?.audioInputs],
    ["Audio outputs", med?.audioOutputs],
    ["Video inputs", med?.videoInputs],
  ]);

  render("network", [["Status", "Loading server data..."]]);

  let server = null;

  try {
    const payload = { client: c, uaHints: hints, webgl: gl, fingerprint: { canvas: fp }, permissions: perm, storage: stor, media: med };
    const response = await fetch(ECHO_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content ?? "" },
      body: JSON.stringify(payload),
    });
    server = await response.json();
  } catch (e) {
    server = { error: e.message };
  }

  render("network", [
    ["IP", server?.ip?.address],
    ["Remote addr", server?.ip?.remote_addr],
    ["Remote port", server?.ip?.remote_port],
    ["Forwarded for", server?.ip?.x_forwarded_for],
    ["Real IP", server?.ip?.x_real_ip],
    ["Cloudflare IP", server?.ip?.cf_connecting_ip],
    ["ISP", server?.geo?.connection?.isp],
    ["Organisation", server?.geo?.connection?.org],
    ["ASN", server?.geo?.connection?.asn],
    ["Country", server?.geo?.country],
    ["Region", server?.geo?.region],
    ["City", server?.geo?.city],
    ["Latitude", server?.geo?.latitude],
    ["Longitude", server?.geo?.longitude],
    ["Geo timezone", server?.geo?.timezone],
    ["Proxy", server?.geo?.security?.proxy],
    ["VPN", server?.geo?.security?.vpn],
    ["Tor", server?.geo?.security?.tor],
    ["Effective type", c.connection?.effectiveType],
    ["Downlink", c.connection?.downlink],
    ["RTT", c.connection?.rtt],
    ["Save data", c.connection?.saveData],
  ]);

  document.getElementById("raw").textContent = JSON.stringify(
    { client: c, uaHints: hints, webgl: gl, fingerprint: { canvas: fp }, permissions: perm, storage: stor, media: med, server },
    null, 2
  );

  fetch(LOG_URL, {
    method: "POST",
    headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content ?? "" },
    body: JSON.stringify({ client: c, uaHints: hints, webgl: gl, fingerprint: { canvas: fp }, permissions: perm, storage: stor, media: med, server }),
  });
}

run();
</script>

</body>
</html>

/**
 * Quetta AgriLink visual regression: captures the authoritative PHP/XAMPP routes through a local Chromium browser only.
 * The runner never submits marketplace, recovery, or administrator-management forms. Authenticated captures are opt-in
 * because normal sign-in auditing updates the selected local database's login history.
 */
import { createHash, randomBytes } from "node:crypto";
import { once } from "node:events";
import { access, mkdir, readFile, rm, writeFile } from "node:fs/promises";
import { tmpdir } from "node:os";
import { join, resolve } from "node:path";
import { spawn } from "node:child_process";
import { setTimeout as delay } from "node:timers/promises";

const projectRoot = resolve(new URL("..", import.meta.url).pathname);
const argumentValue = (flag) => {
  const index = process.argv.indexOf(flag);
  return index === -1 ? null : process.argv[index + 1] || null;
};
const timestamp = new Date().toISOString().replaceAll(":", "-").replaceAll(".", "-");
const outputDirectory = resolve(argumentValue("--out") || join(projectRoot, "artifacts", "visual-regression", `current-${timestamp}`));
const comparisonDirectory = argumentValue("--compare") ? resolve(argumentValue("--compare")) : null;
const configuredBaseUrl = process.env.VISUAL_REGRESSION_BASE_URL || argumentValue("--base-url") || "http://localhost/quetta-agrilink/";
const baseUrl = new URL(configuredBaseUrl.endsWith("/") ? configuredBaseUrl : `${configuredBaseUrl}/`);
const allowedHosts = new Set(["localhost", "127.0.0.1", "::1"]);
if (!allowedHosts.has(baseUrl.hostname.toLowerCase())) {
  throw new Error("Visual regression captures are limited to localhost. Set --base-url to your local XAMPP URL.");
}

const buyerEmail = process.env.VISUAL_REGRESSION_BUYER_EMAIL || "buyer.demo@quettaagrilink.test";
const administratorEmail = process.env.VISUAL_REGRESSION_ADMIN_EMAIL || "admin.demo@quettaagrilink.test";
const password = process.env.VISUAL_REGRESSION_PASSWORD || "";
const buyerPassword = process.env.VISUAL_REGRESSION_BUYER_PASSWORD || password;
const administratorPassword = process.env.VISUAL_REGRESSION_ADMIN_PASSWORD || password;
if (process.env.VISUAL_REGRESSION_ALLOW_AUTH !== "1" || buyerPassword === "" || administratorPassword === "") {
  throw new Error("Set VISUAL_REGRESSION_ALLOW_AUTH=1 plus VISUAL_REGRESSION_PASSWORD, or role-specific buyer and administrator passwords, before capturing authenticated local workspaces.");
}

const browserCommand = process.env.CHROMIUM_BIN || "chromium";
const debugPort = Number(process.env.VISUAL_REGRESSION_DEBUG_PORT || 9322);
const profileDirectory = join(tmpdir(), `quetta-agrilink-visual-${randomBytes(6).toString("hex")}`);
const browserUrl = `http://127.0.0.1:${debugPort}`;
const freezeMotion = `(() => { const style = document.createElement("style"); style.id = "qli-visual-regression-freeze"; style.textContent = "*,*::before,*::after{animation-duration:0s!important;transition-duration:0s!important;caret-color:transparent!important}"; document.head.append(style); return true; })()`;
const normalizeRuntimeContent = `(() => { const activityList = document.querySelector(".workspace-activity-summary .activity-summary-list"); if (activityList) activityList.innerHTML = "<article><strong>Recent account activity</strong><span>Live account events are intentionally normalized for this layout snapshot.</span></article>"; return true; })()`;

function sha256(buffer) {
  return createHash("sha256").update(buffer).digest("hex");
}

function createCdpConnection(url) {
  return new Promise((resolveConnection, rejectConnection) => {
    const socket = new WebSocket(url);
    const pending = new Map();
    let sequence = 0;
    const timer = setTimeout(() => rejectConnection(new Error("Timed out while connecting to the local Chromium debugging endpoint.")), 10000);

    socket.addEventListener("open", () => {
      clearTimeout(timer);
      resolveConnection({
        send(method, params = {}, sessionId = undefined) {
          return new Promise((resolveRequest, rejectRequest) => {
            const id = ++sequence;
            pending.set(id, { resolve: resolveRequest, reject: rejectRequest });
            socket.send(JSON.stringify({ id, method, params, ...(sessionId ? { sessionId } : {}) }));
          });
        },
        close() {
          socket.close();
        },
      });
    });
    socket.addEventListener("message", (event) => {
      const message = JSON.parse(String(event.data));
      if (!message.id || !pending.has(message.id)) return;
      const request = pending.get(message.id);
      pending.delete(message.id);
      if (message.error) request.reject(new Error(`${message.error.message} (${message.error.code})`));
      else request.resolve(message.result);
    });
    socket.addEventListener("error", () => {
      clearTimeout(timer);
      rejectConnection(new Error("Chromium could not open a local debugging connection."));
    });
    socket.addEventListener("close", () => {
      for (const request of pending.values()) request.reject(new Error("Chromium debugging connection closed unexpectedly."));
      pending.clear();
    });
  });
}

async function debugVersion() {
  const response = await fetch(`${browserUrl}/json/version`);
  if (!response.ok) throw new Error(`Chromium debugging endpoint returned ${response.status}.`);
  return response.json();
}

async function waitForBrowser() {
  let lastError = null;
  for (let attempt = 0; attempt < 40; attempt += 1) {
    try {
      return await debugVersion();
    } catch (error) {
      lastError = error;
      await delay(250);
    }
  }
  throw lastError instanceof Error ? lastError : new Error("Chromium did not become available.");
}

async function waitForReady(cdp, sessionId) {
  for (let attempt = 0; attempt < 80; attempt += 1) {
    const response = await cdp.send("Runtime.evaluate", { expression: "document.readyState", returnByValue: true }, sessionId);
    if (response.result?.value === "complete") {
      await cdp.send("Runtime.evaluate", { expression: "document.fonts ? document.fonts.ready.then(() => true) : true", awaitPromise: true, returnByValue: true }, sessionId);
      await cdp.send("Runtime.evaluate", { expression: "Promise.all([...document.images].map((image) => image.complete ? true : new Promise((resolveImage) => { image.addEventListener('load', () => resolveImage(true), { once: true }); image.addEventListener('error', () => resolveImage(false), { once: true }); }))).then(() => true)", awaitPromise: true, returnByValue: true }, sessionId);
      await delay(100);
      return;
    }
    await delay(100);
  }
  throw new Error("The local page did not finish loading in time.");
}

async function evaluate(cdp, sessionId, expression) {
  const response = await cdp.send("Runtime.evaluate", { expression, awaitPromise: true, returnByValue: true }, sessionId);
  if (response.exceptionDetails) throw new Error(response.exceptionDetails.text || "The local browser evaluation failed.");
  return response.result?.value;
}

async function visit(cdp, sessionId, relativePath, viewport) {
  await cdp.send("Emulation.setDeviceMetricsOverride", {
    width: viewport.width,
    height: viewport.height,
    deviceScaleFactor: 1,
    mobile: viewport.mobile,
    screenWidth: viewport.width,
    screenHeight: viewport.height,
  }, sessionId);
  await cdp.send("Page.navigate", { url: new URL(relativePath, baseUrl).href }, sessionId);
  await waitForReady(cdp, sessionId);
  await evaluate(cdp, sessionId, freezeMotion);
  await evaluate(cdp, sessionId, normalizeRuntimeContent);
}

async function capture(cdp, sessionId, definition, records) {
  await visit(cdp, sessionId, definition.path, definition.viewport);
  const check = await evaluate(cdp, sessionId, `(() => ({ selector: Boolean(document.querySelector(${JSON.stringify(definition.selector)})), text: document.body.innerText.includes(${JSON.stringify(definition.text)}), path: location.pathname, title: document.title }))()`);
  const expectedPath = new URL(definition.path, baseUrl).pathname;
  if (!check?.selector || !check?.text || check.path !== expectedPath) {
    throw new Error(`Capture assertion failed for ${definition.name}.`);
  }
  const screenshot = await cdp.send("Page.captureScreenshot", { format: "png", captureBeyondViewport: true, fromSurface: true }, sessionId);
  const image = Buffer.from(screenshot.data, "base64");
  const fileName = `${definition.name}.png`;
  await writeFile(join(outputDirectory, fileName), image);
  records.push({ name: definition.name, file: fileName, path: definition.path, viewport: definition.viewport, title: check.title, bytes: image.length, sha256: sha256(image) });
}

async function authenticate(cdp, sessionId, email, accountPassword) {
  await cdp.send("Network.clearBrowserCookies", {}, sessionId);
  await visit(cdp, sessionId, "auth/login.php", { width: 1440, height: 1200, mobile: false });
  const result = await evaluate(cdp, sessionId, `(() => { const csrf = document.querySelector('input[name="_csrf"]')?.value; if (!csrf) throw new Error("Login CSRF token is unavailable."); const body = new URLSearchParams({ _csrf: csrf, email: ${JSON.stringify(email)}, password: ${JSON.stringify(accountPassword)} }); return fetch(location.href, { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body, credentials: "same-origin" }).then((response) => ({ ok: response.ok, url: response.url })); })()`);
  if (!result?.ok || result.url.endsWith("auth/login.php")) {
    throw new Error(`Local authentication failed for ${email}. Confirm the local test database and VISUAL_REGRESSION_PASSWORD.`);
  }
}

async function stopBrowser(browser) {
  if (browser.exitCode !== null) return;
  browser.kill("SIGTERM");
  await Promise.race([once(browser, "exit"), delay(3000)]);
  if (browser.exitCode === null) browser.kill("SIGKILL");
}

const desktop = { width: 1440, height: 1200, mobile: false };
const mobile = { width: 403, height: 874, mobile: true };
const publicCaptures = [
  { name: "public-home-desktop", path: "", selector: ".desk-home", text: "Choose the next step", viewport: desktop },
  { name: "sign-in-desktop", path: "auth/login.php", selector: ".auth-page", text: "Open your workspace", viewport: desktop },
  { name: "marketplace-desktop", path: "marketplace/index.php", selector: ".market-layout", text: "Compare available produce", viewport: desktop },
  { name: "market-prices-desktop", path: "market-prices.php", selector: ".price-register-layout", text: "Reference, not a quote", viewport: desktop },
  { name: "how-it-works-desktop", path: "how-it-works.php", selector: ".guide-workflow-section", text: "Each handover makes the next decision more specific", viewport: desktop },
  { name: "about-desktop", path: "about.php", selector: ".about-context-section", text: "One platform for everything after harvest", viewport: desktop },
  { name: "contact-desktop", path: "contact.php", selector: ".contact-context-section", text: "Support channel awaiting ownership", viewport: desktop },
  { name: "public-home-mobile", path: "", selector: ".desk-home", text: "Choose the next step", viewport: mobile },
  { name: "market-prices-mobile", path: "market-prices.php", selector: ".price-register-layout", text: "Reference, not a quote", viewport: mobile },
  { name: "how-it-works-mobile", path: "how-it-works.php", selector: ".guide-workflow-section", text: "Each handover makes the next decision more specific", viewport: mobile },
];
const buyerCaptures = [
  { name: "buyer-workspace-desktop", path: "buyer/dashboard.php", selector: ".workspace", text: "Buyer dashboard", viewport: desktop },
  { name: "buyer-workspace-mobile", path: "buyer/dashboard.php", selector: ".workspace", text: "Buyer dashboard", viewport: mobile },
];
const administratorCaptures = [
  { name: "administrator-workspace-desktop", path: "admin/dashboard.php", selector: ".workspace", text: "Administrator dashboard", viewport: desktop },
  { name: "administrator-workspace-mobile", path: "admin/dashboard.php", selector: ".workspace", text: "Administrator dashboard", viewport: mobile },
  { name: "local-operator-transition-desktop", path: "admin/operator-accounts.php", selector: ".operator-transition-intro", text: "Create a named operator account", viewport: desktop },
  { name: "local-operator-transition-mobile", path: "admin/operator-accounts.php", selector: ".operator-transition-intro", text: "Create a named operator account", viewport: mobile },
];

await mkdir(outputDirectory, { recursive: true });
const browser = spawn(browserCommand, ["--headless=new", "--disable-gpu", "--hide-scrollbars", "--no-first-run", "--no-default-browser-check", `--remote-debugging-port=${debugPort}`, `--user-data-dir=${profileDirectory}`, "--window-size=1440,1200"], { stdio: "ignore" });
let cdp;
try {
  const version = await waitForBrowser();
  cdp = await createCdpConnection(version.webSocketDebuggerUrl);
  const target = await cdp.send("Target.createTarget", { url: "about:blank" });
  const attached = await cdp.send("Target.attachToTarget", { targetId: target.targetId, flatten: true });
  const sessionId = attached.sessionId;
  await cdp.send("Page.enable", {}, sessionId);
  await cdp.send("Runtime.enable", {}, sessionId);
  await cdp.send("Network.enable", {}, sessionId);

  const records = [];
  for (const definition of publicCaptures) await capture(cdp, sessionId, definition, records);
  await authenticate(cdp, sessionId, buyerEmail, buyerPassword);
  for (const definition of buyerCaptures) await capture(cdp, sessionId, definition, records);
  await authenticate(cdp, sessionId, administratorEmail, administratorPassword);
  for (const definition of administratorCaptures) await capture(cdp, sessionId, definition, records);

  const comparison = [];
  if (comparisonDirectory) {
    for (const record of records) {
      const baselineFile = join(comparisonDirectory, record.file);
      try {
        const baseline = await readFile(baselineFile);
        comparison.push({ file: record.file, same: sha256(baseline) === record.sha256 });
      } catch {
        comparison.push({ file: record.file, same: false, missingBaseline: true });
      }
    }
  }
  const manifest = { generatedAt: new Date().toISOString(), baseUrl: baseUrl.href, browser: version.Browser, records, comparison: comparisonDirectory ? { baselineDirectory: comparisonDirectory, files: comparison } : null };
  await writeFile(join(outputDirectory, "manifest.json"), `${JSON.stringify(manifest, null, 2)}\n`);
  if (comparison.some((entry) => !entry.same)) {
    throw new Error(`Visual regression difference found. Review ${outputDirectory} against ${comparisonDirectory}.`);
  }
  console.log(`Captured ${records.length} local PHP/XAMPP visual-regression snapshots in ${outputDirectory}`);
} finally {
  if (cdp) cdp.close();
  await stopBrowser(browser);
  await rm(profileDirectory, { recursive: true, force: true, maxRetries: 8, retryDelay: 250 });
}

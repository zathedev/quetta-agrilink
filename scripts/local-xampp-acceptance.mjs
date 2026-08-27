/**
 * Final XAMPP acceptance: exercises only local authoritative PHP routes in Chromium.
 * It submits no commercial, recovery, or administrator-management form. Opt-in sign-ins
 * create standard local login audit entries, so run this against a disposable development database when needed.
 */
import { once } from "node:events";
import { randomBytes } from "node:crypto";
import { mkdir, rm, writeFile } from "node:fs/promises";
import { tmpdir } from "node:os";
import { join, resolve } from "node:path";
import { spawn } from "node:child_process";
import { setTimeout as delay } from "node:timers/promises";

const projectRoot = resolve(new URL("..", import.meta.url).pathname);
const argumentValue = (flag) => {
  const index = process.argv.indexOf(flag);
  return index === -1 ? null : process.argv[index + 1] || null;
};
const requestedBaseUrl = process.env.LOCAL_ACCEPTANCE_BASE_URL || argumentValue("--base-url") || "http://localhost/quetta-agrilink/";
const baseUrl = new URL(requestedBaseUrl.endsWith("/") ? requestedBaseUrl : `${requestedBaseUrl}/`);
if (!new Set(["localhost", "127.0.0.1", "::1"]).has(baseUrl.hostname.toLowerCase())) {
  throw new Error("Final acceptance checks are restricted to a localhost XAMPP URL.");
}
const password = process.env.LOCAL_ACCEPTANCE_PASSWORD || "";
if (process.env.LOCAL_ACCEPTANCE_ALLOW_AUTH !== "1" || password === "") {
  throw new Error("Set LOCAL_ACCEPTANCE_ALLOW_AUTH=1 and LOCAL_ACCEPTANCE_PASSWORD before running protected acceptance checks.");
}
const buyerEmail = process.env.LOCAL_ACCEPTANCE_BUYER_EMAIL || "buyer.demo@quettaagrilink.test";
const adminEmail = process.env.LOCAL_ACCEPTANCE_ADMIN_EMAIL || "admin.demo@quettaagrilink.test";
const browserCommand = process.env.CHROMIUM_BIN || "chromium";
const debugPort = Number(process.env.LOCAL_ACCEPTANCE_DEBUG_PORT || 9324);
const browserUrl = `http://127.0.0.1:${debugPort}`;
const profileDirectory = join(tmpdir(), `quetta-agrilink-acceptance-${randomBytes(6).toString("hex")}`);
const outputDirectory = resolve(argumentValue("--out") || join(projectRoot, "artifacts", "acceptance", new Date().toISOString().replaceAll(":", "-")));
const desktop = { width: 1440, height: 1200, mobile: false };
const mobile = { width: 403, height: 874, mobile: true };

function connectCdp(url) {
  return new Promise((resolveConnection, rejectConnection) => {
    const socket = new WebSocket(url);
    const pending = new Map();
    let sequence = 0;
    const timer = setTimeout(() => rejectConnection(new Error("Timed out while connecting to local Chromium.")), 10000);
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
        close() { socket.close(); },
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

async function browserVersion() {
  const response = await fetch(`${browserUrl}/json/version`);
  if (!response.ok) throw new Error(`Local Chromium endpoint returned ${response.status}.`);
  return response.json();
}

async function waitForBrowser() {
  let lastError;
  for (let attempt = 0; attempt < 40; attempt += 1) {
    try { return await browserVersion(); } catch (error) { lastError = error; await delay(250); }
  }
  throw lastError || new Error("Local Chromium did not become available.");
}

async function evaluate(cdp, sessionId, expression) {
  const response = await cdp.send("Runtime.evaluate", { expression, awaitPromise: true, returnByValue: true }, sessionId);
  if (response.exceptionDetails) throw new Error(response.exceptionDetails.text || "Browser evaluation failed.");
  return response.result?.value;
}

async function waitForReady(cdp, sessionId) {
  for (let attempt = 0; attempt < 80; attempt += 1) {
    if (await evaluate(cdp, sessionId, "document.readyState") === "complete") {
      await evaluate(cdp, sessionId, "document.fonts ? document.fonts.ready.then(() => true) : true");
      await evaluate(cdp, sessionId, "Promise.all([...document.images].map((image) => image.complete ? true : new Promise((resolveImage) => { image.addEventListener('load', () => resolveImage(true), { once: true }); image.addEventListener('error', () => resolveImage(false), { once: true }); }))).then(() => true)");
      return;
    }
    await delay(100);
  }
  throw new Error("The local PHP page did not finish loading.");
}

async function visit(cdp, sessionId, path, viewport = desktop) {
  await cdp.send("Emulation.setDeviceMetricsOverride", { width: viewport.width, height: viewport.height, deviceScaleFactor: 1, mobile: viewport.mobile, screenWidth: viewport.width, screenHeight: viewport.height }, sessionId);
  const expected = new URL(path, baseUrl);
  await cdp.send("Page.navigate", { url: expected.href }, sessionId);
  await waitForReady(cdp, sessionId);
  const actual = await evaluate(cdp, sessionId, "location.pathname");
  if (actual !== expected.pathname) throw new Error(`Unexpected redirect for ${path || "home"}: ${actual}`);
}

async function assert(cdp, sessionId, name, expression) {
  const result = await evaluate(cdp, sessionId, expression);
  if (!result) throw new Error(`Acceptance assertion failed: ${name}`);
}

async function signIn(cdp, sessionId, email) {
  await cdp.send("Network.clearBrowserCookies", {}, sessionId);
  await visit(cdp, sessionId, "auth/login.php");
  const result = await evaluate(cdp, sessionId, `(() => { const csrf = document.querySelector('input[name="_csrf"]')?.value; if (!csrf) throw new Error("Login CSRF token is unavailable."); return fetch(location.href, { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: new URLSearchParams({ _csrf: csrf, email: ${JSON.stringify(email)}, password: ${JSON.stringify(password)} }), credentials: "same-origin" }).then((response) => ({ ok: response.ok, url: response.url })); })()`);
  if (!result?.ok || result.url.endsWith("auth/login.php")) throw new Error(`Local sign-in failed for ${email}.`);
}

async function pressTab(cdp, sessionId) {
  await cdp.send("Input.dispatchKeyEvent", { type: "keyDown", key: "Tab", code: "Tab", windowsVirtualKeyCode: 9, nativeVirtualKeyCode: 9 }, sessionId);
  await cdp.send("Input.dispatchKeyEvent", { type: "keyUp", key: "Tab", code: "Tab", windowsVirtualKeyCode: 9, nativeVirtualKeyCode: 9 }, sessionId);
}

async function focusIsVisible(cdp, sessionId) {
  return evaluate(cdp, sessionId, `(() => { const active = document.activeElement; if (!active || active === document.body || !active.matches('a[href],button,input,select,textarea')) return false; const rect = active.getBoundingClientRect(); const style = getComputedStyle(active); return rect.width > 0 && rect.height > 0 && rect.bottom > 0 && rect.top < innerHeight && (style.outlineStyle !== 'none' || style.boxShadow !== 'none' || active.matches('input,select,textarea')); })()`);
}

async function stopBrowser(browser) {
  if (browser.exitCode !== null) return;
  browser.kill("SIGTERM");
  await Promise.race([once(browser, "exit"), delay(3000)]);
  if (browser.exitCode === null) browser.kill("SIGKILL");
}

await mkdir(outputDirectory, { recursive: true });
const browser = spawn(browserCommand, ["--headless=new", "--disable-gpu", "--hide-scrollbars", "--no-first-run", "--no-default-browser-check", `--remote-debugging-port=${debugPort}`, `--user-data-dir=${profileDirectory}`, "--window-size=1440,1200"], { stdio: "ignore" });
let cdp;
const completedChecks = [];
try {
  const version = await waitForBrowser();
  cdp = await connectCdp(version.webSocketDebuggerUrl);
  const target = await cdp.send("Target.createTarget", { url: "about:blank" });
  const attached = await cdp.send("Target.attachToTarget", { targetId: target.targetId, flatten: true });
  const sessionId = attached.sessionId;
  await cdp.send("Page.enable", {}, sessionId);
  await cdp.send("Runtime.enable", {}, sessionId);
  await cdp.send("Network.enable", {}, sessionId);
  await cdp.send("Network.setCacheDisabled", { cacheDisabled: true }, sessionId);

  await visit(cdp, sessionId, "");
  await assert(cdp, sessionId, "fresh local font stylesheet link", `document.querySelector('link[href*="assets/css/local-fonts.css?v="]') !== null && !document.documentElement.innerHTML.includes('fonts.googleapis.com')`);
  await assert(cdp, sessionId, "local DM Sans availability", "document.fonts.check('16px \\\"DM Sans\\\"')");
  await assert(cdp, sessionId, "local font asset request", "performance.getEntriesByType('resource').some((entry) => entry.name.includes('/assets/fonts/dm-sans-'))");
  completedChecks.push("fresh-cache local CSS and font loading");

  await cdp.send("Runtime.evaluate", { expression: "document.body.focus()" }, sessionId);
  await pressTab(cdp, sessionId);
  await assert(cdp, sessionId, "public keyboard entry", "document.activeElement.matches('a[href],button')");
  await assert(cdp, sessionId, "public focus visibility", `(${await focusIsVisible(cdp, sessionId)})`);
  await visit(cdp, sessionId, "auth/login.php");
  await evaluate(cdp, sessionId, "document.querySelector('#email').focus()");
  await pressTab(cdp, sessionId);
  await assert(cdp, sessionId, "login keyboard sequence", "document.activeElement?.id === 'password'");
  await visit(cdp, sessionId, "marketplace/index.php");
  await evaluate(cdp, sessionId, "document.querySelector('[data-marketplace-filter] select')?.focus()");
  await assert(cdp, sessionId, "marketplace control focus", `(${await focusIsVisible(cdp, sessionId)})`);
  completedChecks.push("public, sign-in, and marketplace keyboard navigation");

  for (const [path, selector] of [["", ".desk-home"], ["market-prices.php", ".price-register-layout"], ["how-it-works.php", ".guide-workflow-section"], ["auth/login.php", ".auth-page"]]) {
    await visit(cdp, sessionId, path, mobile);
    await assert(cdp, sessionId, `mobile ${path || "home"} layout`, `document.querySelector(${JSON.stringify(selector)}) !== null && document.documentElement.scrollWidth <= window.innerWidth + 1`);
  }
  completedChecks.push("responsive public and account-entry layouts");

  await signIn(cdp, sessionId, buyerEmail);
  await visit(cdp, sessionId, "buyer/dashboard.php", desktop);
  await assert(cdp, sessionId, "buyer role workspace", "document.querySelector('.workspace') !== null && document.body.innerText.includes('Buyer dashboard')");
  const buyerDenied = await evaluate(cdp, sessionId, `fetch(${JSON.stringify(new URL("admin/dashboard.php", baseUrl).href)}, { credentials: 'same-origin' }).then((response) => response.status)`);
  if (buyerDenied !== 403) throw new Error("Acceptance assertion failed: buyer access is not denied for the administrator dashboard.");
  completedChecks.push("buyer role scoping and administrator denial");

  await signIn(cdp, sessionId, adminEmail);
  await visit(cdp, sessionId, "admin/dashboard.php", mobile);
  await assert(cdp, sessionId, "administrator mobile workspace", "document.querySelector('.workspace') !== null && document.body.innerText.includes('Administrator dashboard')");
  for (const path of ["admin/contact-verification-export.php", "admin/recovery-audit-export.php"]) {
    const exportResult = await evaluate(cdp, sessionId, `fetch(${JSON.stringify(new URL(path, baseUrl).href)}, { credentials: 'same-origin' }).then(async (response) => ({ status: response.status, type: response.headers.get('content-type') || '', body: await response.text() }))`);
    if (exportResult.status !== 200 || !exportResult.type.includes("text/csv") || /password|selector|token|hash|reset[_ -]?link/i.test(exportResult.body)) {
      throw new Error(`Acceptance assertion failed: protected export boundary for ${path}.`);
    }
  }
  completedChecks.push("administrator workspace and secret-free protected exports");

  await writeFile(join(outputDirectory, "acceptance-summary.json"), `${JSON.stringify({ generatedAt: new Date().toISOString(), baseUrl: baseUrl.href, browser: version.Browser, checks: completedChecks }, null, 2)}\n`);
  console.log(`Local XAMPP acceptance passed: ${completedChecks.length} check groups completed. Evidence: ${outputDirectory}`);
} finally {
  if (cdp) cdp.close();
  await stopBrowser(browser);
  await rm(profileDirectory, { recursive: true, force: true, maxRetries: 8, retryDelay: 250 });
}

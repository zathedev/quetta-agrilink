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

const farmerEmail = process.env.VISUAL_REGRESSION_FARMER_EMAIL || "farmer.demo@quettaagrilink.test";
const buyerEmail = process.env.VISUAL_REGRESSION_BUYER_EMAIL || "buyer.demo@quettaagrilink.test";
const storageEmail = process.env.VISUAL_REGRESSION_STORAGE_EMAIL || "storage.demo@quettaagrilink.test";
const transportEmail = process.env.VISUAL_REGRESSION_TRANSPORT_EMAIL || "transport.demo@quettaagrilink.test";
const administratorEmail = process.env.VISUAL_REGRESSION_ADMIN_EMAIL || "admin.demo@quettaagrilink.test";
const password = process.env.VISUAL_REGRESSION_PASSWORD || "";
const farmerPassword = process.env.VISUAL_REGRESSION_FARMER_PASSWORD || password;
const buyerPassword = process.env.VISUAL_REGRESSION_BUYER_PASSWORD || password;
const storagePassword = process.env.VISUAL_REGRESSION_STORAGE_PASSWORD || password;
const transportPassword = process.env.VISUAL_REGRESSION_TRANSPORT_PASSWORD || password;
const administratorPassword = process.env.VISUAL_REGRESSION_ADMIN_PASSWORD || password;
if (process.env.VISUAL_REGRESSION_ALLOW_AUTH !== "1" || [farmerPassword, buyerPassword, storagePassword, transportPassword, administratorPassword].some((value) => value === "")) {
  throw new Error("Set VISUAL_REGRESSION_ALLOW_AUTH=1 plus VISUAL_REGRESSION_PASSWORD, or every role-specific password, before capturing authenticated local workspaces.");
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
  await evaluate(cdp, sessionId, "window.scrollTo(0, 0); true");
}

async function capture(cdp, sessionId, definition, records) {
  await visit(cdp, sessionId, definition.path, definition.viewport);
  const check = await evaluate(cdp, sessionId, `(() => ({ selector: Boolean(document.querySelector(${JSON.stringify(definition.selector)})), text: document.body.innerText.includes(${JSON.stringify(definition.text)}), path: location.pathname, title: document.title, horizontalOverflow: Math.max(document.documentElement.scrollWidth, document.body.scrollWidth) - window.innerWidth }))()`);
  const expectedPath = new URL(definition.path, baseUrl).pathname;
  if (!check?.selector || !check?.text || check.path !== expectedPath || check.horizontalOverflow > 1) {
    throw new Error(`Capture assertion failed for ${definition.name}: ${JSON.stringify({ expectedPath, check })}`);
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

async function assertAccountFormFeedback(cdp, sessionId) {
  await visit(cdp, sessionId, "auth/login.php", desktop);
  const login = await evaluate(cdp, sessionId, `(() => { const email = document.querySelector('[name="email"]'); const password = document.querySelector('[name="password"]'); email.value = 'not-an-email'; email.dispatchEvent(new Event('input', { bubbles: true })); password.value = ''; password.dispatchEvent(new Event('blur', { bubbles: true })); return { email: document.querySelector('[data-field-error="email"]')?.textContent.trim(), password: document.querySelector('[data-field-error="password"]')?.textContent.trim(), blocked: email.getAttribute('aria-invalid') === 'true' && password.getAttribute('aria-invalid') === 'true' }; })()`);
  if (login?.email !== "Enter a valid email address." || login?.password !== "Enter your password." || !login?.blocked) {
    throw new Error(`Sign-in form validation check failed: ${JSON.stringify(login)}`);
  }
  await visit(cdp, sessionId, "auth/register.php", desktop);
  const register = await evaluate(cdp, sessionId, `(() => { const values = { full_name: 'A', email: 'invalid', phone: 'letters', password: 'short' }; for (const [name, value] of Object.entries(values)) { const field = document.querySelector('[name="' + name + '"]'); field.value = value; field.dispatchEvent(new Event('input', { bubbles: true })); } return Object.fromEntries(Object.keys(values).map((name) => [name, document.querySelector('[data-field-error="' + name + '"]')?.textContent.trim()])); })()`);
  const expected = { full_name: "Enter at least 3 characters for your name.", email: "Enter a valid email address.", phone: "Use 7–25 digits and standard phone symbols only.", password: "Use at least 10 characters with a letter and a number." };
  if (JSON.stringify(register) !== JSON.stringify(expected)) {
    throw new Error(`Account-creation form validation check failed: ${JSON.stringify(register)}`);
  }
}

async function assertToastInteractions(cdp, sessionId) {
  await visit(cdp, sessionId, "auth/login.php", desktop);
  const result = await evaluate(cdp, sessionId, `(() => new Promise(async (resolve) => {
    document.getElementById('qli-visual-regression-freeze')?.remove();
    const wait = (milliseconds) => new Promise((done) => setTimeout(done, milliseconds));
    const toast = window.QuettaToast?.show('Interactive lifecycle check', 'success', { title: 'Toast check', timeout: 1000 });
    await wait(60);
    const visible = Boolean(toast?.classList.contains('is-visible') && toast.querySelector('[data-toast-dismiss]'));
    toast?.dispatchEvent(new Event('mouseenter'));
    await wait(340);
    const paused = Boolean(toast?.isConnected && toast.classList.contains('is-paused'));
    toast?.dispatchEvent(new Event('mouseleave'));
    await wait(1350);
    const autoClosed = Boolean(toast && !toast.isConnected);
    const manual = window.QuettaToast?.show('Manual dismissal check', 'error', { timeout: 5000 });
    await wait(40);
    manual?.querySelector('[data-toast-dismiss]')?.click();
    await wait(340);
    resolve({ api: Boolean(window.QuettaToast?.show), visible, paused, autoClosed, manualClosed: Boolean(manual && !manual.isConnected) });
  }))()`);
  if (!result?.api || !result.visible || !result.paused || !result.autoClosed || !result.manualClosed) {
    throw new Error(`Toast interaction check failed: ${JSON.stringify(result)}`);
  }
}

async function assertWorkspaceSidebarInteractions(cdp, sessionId) {
  await visit(cdp, sessionId, "farmer/dashboard.php", desktop);
  const desktopResult = await evaluate(cdp, sessionId, `(() => {
    const workspace = document.querySelector('.workspace');
    const sidebar = document.querySelector('.workspace-sidebar');
    const toggle = document.querySelector('.workspace-sidebar-toggle');
    const signout = document.querySelector('.workspace-signout button');
    const externalLinks = [...document.querySelectorAll('.workspace-nav-external')];
    const navigationLinks = [...document.querySelectorAll('.workspace-sidebar nav a')];
    if (!workspace || !sidebar || !toggle || !signout) return { ready: false };
    if (workspace.classList.contains('is-sidebar-collapsed')) toggle.click();
    const expandedWidth = sidebar.getBoundingClientRect().width;
    toggle.click();
    const collapsedWidth = sidebar.getBoundingClientRect().width;
    const collapsed = workspace.classList.contains('is-sidebar-collapsed') && toggle.getAttribute('aria-expanded') === 'false';
    toggle.click();
    return {
      ready: true,
      expandedWidth,
      collapsedWidth,
      collapsed,
      restored: !workspace.classList.contains('is-sidebar-collapsed'),
      signoutIcon: Boolean(signout.querySelector('.workspace-signout-icon')),
      signoutBackground: getComputedStyle(signout).backgroundColor,
      externalCount: externalLinks.length,
      externalSafe: externalLinks.every((link) => link.target === '_blank' && link.relList.contains('noopener') && link.relList.contains('noreferrer') && Boolean(link.querySelector('.workspace-external-icon'))),
      meaningfulIcons: navigationLinks.length > 8 && navigationLinks.every((link) => Boolean(link.querySelector('.workspace-nav-icon svg')) && link.title.length > 0) && new Set(navigationLinks.map((link) => link.querySelector('.workspace-nav-icon svg')?.innerHTML)).size > 8,
    };
  })()`);
  if (!desktopResult?.ready || desktopResult.expandedWidth < 220 || desktopResult.collapsedWidth > 100 || !desktopResult.collapsed || !desktopResult.restored || !desktopResult.signoutIcon || desktopResult.signoutBackground === 'rgba(0, 0, 0, 0)' || desktopResult.externalCount < 3 || !desktopResult.externalSafe || !desktopResult.meaningfulIcons) {
    throw new Error(`Desktop workspace sidebar interaction check failed: ${JSON.stringify(desktopResult)}`);
  }

  await visit(cdp, sessionId, "farmer/dashboard.php", tablet);
  const compactResult = await evaluate(cdp, sessionId, `(() => new Promise(async (resolve) => {
    const wait = (milliseconds) => new Promise((done) => setTimeout(done, milliseconds));
    const workspace = document.querySelector('.workspace');
    const sidebar = document.querySelector('.workspace-sidebar');
    const opener = document.querySelector('.workspace-sidebar-opener');
    const closer = document.querySelector('.workspace-sidebar-toggle');
    const backdrop = document.querySelector('.workspace-sidebar-backdrop');
    if (!workspace || !sidebar || !opener || !closer || !backdrop) return resolve({ ready: false });
    const initiallyClosed = !workspace.classList.contains('is-sidebar-open') && sidebar.getAttribute('aria-hidden') === 'true' && getComputedStyle(opener).display !== 'none';
    opener.click();
    await wait(70);
    const opened = workspace.classList.contains('is-sidebar-open') && sidebar.getAttribute('aria-hidden') === 'false' && document.body.classList.contains('workspace-navigation-open') && document.activeElement === closer;
    backdrop.click();
    await wait(20);
    const backdropClosed = !workspace.classList.contains('is-sidebar-open') && document.activeElement === opener;
    opener.click();
    await wait(70);
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    await wait(20);
    const escapeClosed = !workspace.classList.contains('is-sidebar-open') && opener.getAttribute('aria-expanded') === 'false' && document.activeElement === opener;
    resolve({ ready: true, initiallyClosed, opened, backdropClosed, escapeClosed });
  }))()`);
  if (!compactResult?.ready || !compactResult.initiallyClosed || !compactResult.opened || !compactResult.backdropClosed || !compactResult.escapeClosed) {
    throw new Error(`Compact workspace sidebar interaction check failed: ${JSON.stringify(compactResult)}`);
  }
}

async function assertHeaderMenuInteractions(cdp, sessionId) {
  await visit(cdp, sessionId, "farmer/dashboard.php", desktop);
  const desktopResult = await evaluate(cdp, sessionId, `(() => new Promise(async (resolve) => {
    const wait = (milliseconds) => new Promise((done) => setTimeout(done, milliseconds));
    const bell = document.querySelector('[data-notification-link]');
    const notificationMenu = bell?.closest('[data-header-menu]');
    const notificationPanel = document.querySelector('[data-notification-dropdown]');
    const profile = document.querySelector('.profile-menu-toggle');
    const profileMenu = profile?.closest('[data-header-menu]');
    const profilePanel = document.querySelector('.profile-dropdown');
    if (!bell || !notificationMenu || !notificationPanel || !profile || !profileMenu || !profilePanel) return resolve({ ready: false });
    bell.click();
    for (let attempt = 0; attempt < 20 && notificationPanel.classList.contains('is-loading'); attempt += 1) await wait(50);
    const unreadItems = [...notificationPanel.querySelectorAll('[data-notification-item].is-unread')];
    const notificationOpen = notificationMenu.classList.contains('is-open') && !notificationPanel.hidden && bell.getAttribute('aria-expanded') === 'true';
    const notificationContent = Boolean(notificationPanel.querySelector('[data-notification-list]') && notificationPanel.querySelector('[data-notification-mark-all-form]') && notificationPanel.querySelector('a[href*="notifications.php"]'));
    const oneByOneControls = unreadItems.every((item) => Boolean(item.querySelector('[data-notification-read-form]')));
    profile.click();
    const exclusive = !notificationMenu.classList.contains('is-open') && notificationPanel.hidden && profileMenu.classList.contains('is-open') && !profilePanel.hidden;
    const profileActions = profilePanel.querySelectorAll('nav a').length === 2 && Boolean(profilePanel.querySelector('a[href*="dashboard.php"]')) && Boolean(profilePanel.querySelector('a[href*="account/profile.php"]')) && Boolean(profilePanel.querySelector('form[action*="auth/logout.php"]'));
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    const escapeClosed = profilePanel.hidden && document.activeElement === profile;
    bell.click();
    document.body.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }));
    const outsideClosed = notificationPanel.hidden;
    const footer = document.querySelector('.site-footer');
    resolve({
      ready: true,
      bellOnly: Boolean(bell.querySelector('.notification-bell-icon') && !bell.textContent.includes('Alerts')),
      notificationOpen,
      notificationContent,
      oneByOneControls,
      exclusive,
      profileActions,
      escapeClosed,
      outsideClosed,
      footer: Boolean(footer?.querySelector('.footer-callout') && footer.querySelectorAll('.footer-links').length === 4),
    });
  }))()`);
  if (!desktopResult?.ready || !desktopResult.bellOnly || !desktopResult.notificationOpen || !desktopResult.notificationContent || !desktopResult.oneByOneControls || !desktopResult.exclusive || !desktopResult.profileActions || !desktopResult.escapeClosed || !desktopResult.outsideClosed || !desktopResult.footer) {
    throw new Error(`Desktop header menu interaction check failed: ${JSON.stringify(desktopResult)}`);
  }

  await visit(cdp, sessionId, "farmer/dashboard.php", mobile);
  const mobileResult = await evaluate(cdp, sessionId, `(() => new Promise(async (resolve) => {
    const wait = (milliseconds) => new Promise((done) => setTimeout(done, milliseconds));
    const bell = document.querySelector('[data-notification-link]');
    const panel = document.querySelector('[data-notification-dropdown]');
    const profile = document.querySelector('.profile-menu-toggle');
    if (!bell || !panel || !profile) return resolve({ ready: false });
    bell.click();
    await wait(120);
    const bounds = panel.getBoundingClientRect();
    const contained = bounds.left >= 0 && bounds.right <= innerWidth && bounds.top >= 0 && bounds.bottom <= innerHeight + 1;
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    resolve({ ready: true, controlsVisible: getComputedStyle(bell).display !== 'none' && getComputedStyle(profile).display !== 'none', contained, closed: panel.hidden, horizontalOverflow: Math.max(document.documentElement.scrollWidth, document.body.scrollWidth) - innerWidth });
  }))()`);
  if (!mobileResult?.ready || !mobileResult.controlsVisible || !mobileResult.contained || !mobileResult.closed || mobileResult.horizontalOverflow > 1) {
    throw new Error(`Mobile header menu interaction check failed: ${JSON.stringify(mobileResult)}`);
  }
}

async function captureHeaderMenuStates(cdp, sessionId, records) {
  const states = [
    { name: "authenticated-notification-menu-desktop", viewport: desktop, selector: "[data-notification-link]", panel: "[data-notification-dropdown]" },
    { name: "authenticated-profile-menu-desktop", viewport: desktop, selector: ".profile-menu-toggle", panel: ".profile-dropdown" },
    { name: "authenticated-notification-menu-mobile", viewport: mobile, selector: "[data-notification-link]", panel: "[data-notification-dropdown]" },
  ];
  for (const state of states) {
    await visit(cdp, sessionId, "farmer/dashboard.php", state.viewport);
    const opened = await evaluate(cdp, sessionId, `(() => new Promise(async (resolve) => {
      document.querySelector(${JSON.stringify(state.selector)})?.click();
      for (let attempt = 0; attempt < 20; attempt += 1) {
        const panel = document.querySelector(${JSON.stringify(state.panel)});
        if (panel && !panel.hidden && !panel.classList.contains('is-loading')) return resolve({ open: true, overflow: Math.max(document.documentElement.scrollWidth, document.body.scrollWidth) - innerWidth });
        await new Promise((done) => setTimeout(done, 50));
      }
      resolve({ open: false });
    }))()`);
    if (!opened?.open || opened.overflow > 1) throw new Error(`Interactive header capture failed for ${state.name}: ${JSON.stringify(opened)}`);
    const screenshot = await cdp.send("Page.captureScreenshot", { format: "png", captureBeyondViewport: false, fromSurface: true }, sessionId);
    const image = Buffer.from(screenshot.data, "base64");
    const fileName = `${state.name}.png`;
    await writeFile(join(outputDirectory, fileName), image);
    records.push({ name: state.name, file: fileName, path: "farmer/dashboard.php", viewport: state.viewport, title: "Authenticated header menu", bytes: image.length, sha256: sha256(image), interactiveState: true });
  }
}

async function captureCollapsedSidebarState(cdp, sessionId, records) {
  await visit(cdp, sessionId, "farmer/dashboard.php", desktop);
  const check = await evaluate(cdp, sessionId, `(() => {
    const workspace = document.querySelector('.workspace');
    const toggle = document.querySelector('.workspace-sidebar-toggle');
    if (!workspace || !toggle) return { ready: false };
    if (!workspace.classList.contains('is-sidebar-collapsed')) toggle.click();
    const links = [...document.querySelectorAll('.workspace-sidebar nav a')];
    return { ready: true, collapsed: workspace.classList.contains('is-sidebar-collapsed'), icons: links.filter((link) => link.querySelector('.workspace-nav-icon svg')).length, links: links.length, width: document.querySelector('.workspace-sidebar')?.getBoundingClientRect().width };
  })()`);
  if (!check?.ready || !check.collapsed || check.icons !== check.links || check.width > 100) throw new Error(`Collapsed sidebar capture failed: ${JSON.stringify(check)}`);
  const screenshot = await cdp.send("Page.captureScreenshot", { format: "png", captureBeyondViewport: false, fromSurface: true }, sessionId);
  const image = Buffer.from(screenshot.data, "base64");
  const fileName = "authenticated-sidebar-collapsed-desktop.png";
  await writeFile(join(outputDirectory, fileName), image);
  records.push({ name: "authenticated-sidebar-collapsed-desktop", file: fileName, path: "farmer/dashboard.php", viewport: desktop, title: "Collapsed workspace sidebar", bytes: image.length, sha256: sha256(image), interactiveState: true });
}

async function assertNotificationDropdownMutations(cdp, sessionId) {
  await visit(cdp, sessionId, "farmer/dashboard.php", desktop);
  const result = await evaluate(cdp, sessionId, `(() => new Promise(async (resolve) => {
    const panel = document.querySelector('[data-notification-dropdown]');
    if (!panel || !window.qliFetch) return resolve({ ready: false });
    try {
      const latest = await window.qliFetch(panel.dataset.notificationLatestEndpoint);
      const first = latest.data?.items?.[0];
      let single = null;
      if (first) {
        const body = new FormData();
        body.append('notification_id', String(first.id));
        single = await window.qliFetch(panel.dataset.notificationMarkReadEndpoint, { method: 'POST', body });
      }
      const all = await window.qliFetch(panel.dataset.notificationMarkAllEndpoint, { method: 'POST', body: new FormData() });
      resolve({ ready: true, latest: latest.success, single: first ? single?.success === true : null, all: all.success, remaining: Number(all.data?.summary?.count || 0) });
    } catch (error) { resolve({ ready: true, error: error.message }); }
  }))()`);
  if (!result?.ready || !result.latest || result.single === false || !result.all || result.remaining !== 0 || result.error) {
    throw new Error(`Notification dropdown mutation check failed: ${JSON.stringify(result)}`);
  }
}

async function stopBrowser(browser) {
  if (browser.exitCode !== null) return;
  browser.kill("SIGTERM");
  await Promise.race([once(browser, "exit"), delay(3000)]);
  if (browser.exitCode === null) browser.kill("SIGKILL");
}

const desktop = { width: 1440, height: 1200, mobile: false };
const tablet = { width: 834, height: 1112, mobile: false };
const mobile = { width: 403, height: 874, mobile: true };
const publicCaptures = [
  { name: "public-home-desktop", path: "", selector: ".desk-home", text: "Move produce from farm to market", viewport: desktop },
  { name: "sign-in-desktop", path: "auth/login.php", selector: ".auth-page-login", text: "Welcome back", viewport: desktop },
  { name: "sign-in-mobile", path: "auth/login.php", selector: ".auth-page-login", text: "Welcome back", viewport: mobile },
  { name: "sign-up-desktop", path: "auth/register.php", selector: ".auth-page-register", text: "Create your account", viewport: desktop },
  { name: "recovery-desktop", path: "auth/recover.php", selector: ".auth-page-recovery", text: "Reset your password", viewport: desktop },
  { name: "recovery-mobile", path: "auth/recover.php", selector: ".auth-page-recovery", text: "Reset your password", viewport: mobile },
  { name: "reset-invalid-desktop", path: "auth/reset-password.php", selector: ".auth-state-invalid", text: "Link unavailable", viewport: desktop },
  { name: "reset-invalid-mobile", path: "auth/reset-password.php", selector: ".auth-state-invalid", text: "Link unavailable", viewport: mobile },
  { name: "marketplace-desktop", path: "marketplace/index.php", selector: ".market-layout", text: "Compare trade-ready produce", viewport: desktop },
  { name: "marketplace-mobile", path: "marketplace/index.php", selector: ".market-layout", text: "Compare trade-ready produce", viewport: mobile },
  { name: "listing-detail-desktop", path: "marketplace/listing.php?id=1", selector: ".listing-detail-page", text: "Pishin Grade A Apples", viewport: desktop },
  { name: "listing-detail-mobile", path: "marketplace/listing.php?id=1", selector: ".listing-detail-page", text: "Pishin Grade A Apples", viewport: mobile },
  { name: "storage-desktop", path: "storage/index.php", selector: ".storage-discovery-layout", text: "Protect the harvest", viewport: desktop },
  { name: "transport-desktop", path: "transport/index.php", selector: ".transport-page", text: "Match each load", viewport: desktop },
  { name: "market-prices-desktop", path: "market-prices.php", selector: ".price-register-layout", text: "Reference, not a quote", viewport: desktop },
  { name: "how-it-works-desktop", path: "how-it-works.php", selector: ".guide-workflow-section", text: "Each handover makes the next decision more specific", viewport: desktop },
  { name: "about-desktop", path: "about.php", selector: ".about-context-section", text: "One accountable record across the post-harvest chain", viewport: desktop },
  { name: "contact-desktop", path: "contact.php", selector: ".contact-context-section", text: "Keep operational support inside the workspace", viewport: desktop },
  { name: "public-home-mobile", path: "", selector: ".desk-home", text: "Move produce from farm to market", viewport: mobile },
  { name: "public-home-tablet", path: "", selector: ".desk-home", text: "Move produce from farm to market", viewport: tablet },
  { name: "marketplace-tablet", path: "marketplace/index.php", selector: ".market-layout", text: "Compare trade-ready produce", viewport: tablet },
  { name: "sign-up-mobile", path: "auth/register.php", selector: ".auth-page-register", text: "Create your account", viewport: mobile },
  { name: "storage-mobile", path: "storage/index.php", selector: ".storage-discovery-layout", text: "Protect the harvest", viewport: mobile },
  { name: "transport-mobile", path: "transport/index.php", selector: ".transport-page", text: "Match each load", viewport: mobile },
  { name: "market-prices-mobile", path: "market-prices.php", selector: ".price-register-layout", text: "Reference, not a quote", viewport: mobile },
  { name: "how-it-works-mobile", path: "how-it-works.php", selector: ".guide-workflow-section", text: "Each handover makes the next decision more specific", viewport: mobile },
];
const buyerCaptures = [
  { name: "buyer-workspace-desktop", path: "buyer/dashboard.php", selector: ".workspace-role-buyer", text: "Buyer dashboard", viewport: desktop },
  { name: "buyer-workspace-mobile", path: "buyer/dashboard.php", selector: ".workspace-role-buyer", text: "Buyer dashboard", viewport: mobile },
  { name: "buyer-workspace-tablet", path: "buyer/dashboard.php", selector: ".workspace-role-buyer", text: "Buyer dashboard", viewport: tablet },
  { name: "buyer-offers-desktop", path: "buyer/offers.php", selector: ".workspace-role-buyer", text: "Offer status and counter terms", viewport: desktop },
  { name: "buyer-offers-mobile", path: "buyer/offers.php", selector: ".workspace-role-buyer", text: "Offer status and counter terms", viewport: mobile },
  { name: "buyer-notifications-desktop", path: "notifications.php", selector: ".notification-list", text: "Operational alerts", viewport: desktop },
  { name: "buyer-notifications-mobile", path: "notifications.php", selector: ".notification-list", text: "Operational alerts", viewport: mobile },
  { name: "buyer-profile-desktop", path: "account/profile.php", selector: ".profile-section", text: "Account details", viewport: desktop },
  { name: "buyer-settings-mobile", path: "account/settings.php", selector: ".workspace-role-buyer", text: "Notification preferences", viewport: mobile },
  { name: "buyer-support-desktop", path: "support.php", selector: ".support-intro", text: "Keep support work in the accountable workspace", viewport: desktop },
  { name: "buyer-support-mobile", path: "support.php", selector: ".support-intro", text: "Keep support work in the accountable workspace", viewport: mobile },
];
const farmerCaptures = [
  { name: "farmer-workspace-desktop", path: "farmer/dashboard.php", selector: ".workspace-role-farmer", text: "Farmer dashboard", viewport: desktop },
  { name: "farmer-workspace-mobile", path: "farmer/dashboard.php", selector: ".workspace-role-farmer", text: "Farmer dashboard", viewport: mobile },
  { name: "farmer-listings-desktop", path: "farmer/listings.php", selector: ".farmer-publication-layout", text: "Publish an accountable supply record", viewport: desktop },
  { name: "farmer-listings-mobile", path: "farmer/listings.php", selector: ".farmer-publication-layout", text: "Publish an accountable supply record", viewport: mobile },
  { name: "farmer-offers-desktop", path: "farmer/offers.php", selector: ".workspace-role-farmer", text: "Buyer proposals", viewport: desktop },
];
const storageProviderCaptures = [
  { name: "storage-provider-workspace-desktop", path: "storage/dashboard.php", selector: ".workspace-role-storage_provider", text: "Storage provider dashboard", viewport: desktop },
  { name: "storage-provider-workspace-mobile", path: "storage/dashboard.php", selector: ".workspace-role-storage_provider", text: "Storage provider dashboard", viewport: mobile },
  { name: "storage-facilities-desktop", path: "storage/facilities.php", selector: ".workspace-role-storage_provider", text: "Your facility records", viewport: desktop },
  { name: "storage-facilities-mobile", path: "storage/facilities.php", selector: ".workspace-role-storage_provider", text: "Your facility records", viewport: mobile },
];
const transportProviderCaptures = [
  { name: "transport-provider-workspace-desktop", path: "transport/dashboard.php", selector: ".workspace-role-transport_provider", text: "Transport provider dashboard", viewport: desktop },
  { name: "transport-provider-workspace-mobile", path: "transport/dashboard.php", selector: ".workspace-role-transport_provider", text: "Transport provider dashboard", viewport: mobile },
  { name: "transport-fleet-desktop", path: "transport/fleet.php", selector: ".workspace-role-transport_provider", text: "Your fleet records", viewport: desktop },
  { name: "transport-fleet-mobile", path: "transport/fleet.php", selector: ".workspace-role-transport_provider", text: "Your fleet records", viewport: mobile },
];
const administratorCaptures = [
  { name: "administrator-workspace-desktop", path: "admin/dashboard.php", selector: ".workspace-role-admin", text: "Administrator dashboard", viewport: desktop },
  { name: "administrator-workspace-mobile", path: "admin/dashboard.php", selector: ".workspace-role-admin", text: "Administrator dashboard", viewport: mobile },
  { name: "administrator-operations-desktop", path: "admin/management.php", selector: ".workspace-role-admin", text: "Produce categories", viewport: desktop },
  { name: "administrator-operations-mobile", path: "admin/management.php", selector: ".workspace-role-admin", text: "Produce categories", viewport: mobile },
  { name: "local-operator-transition-desktop", path: "admin/operator-accounts.php", selector: ".operator-transition-intro", text: "Create a named operator account", viewport: desktop },
  { name: "local-operator-transition-mobile", path: "admin/operator-accounts.php", selector: ".operator-transition-intro", text: "Create a named operator account", viewport: mobile },
  { name: "market-data-import-desktop", path: "admin/market-price-import.php", selector: ".market-import-intro", text: "Import approved local price references", viewport: desktop },
  { name: "market-data-import-mobile", path: "admin/market-price-import.php", selector: ".market-import-intro", text: "Import approved local price references", viewport: mobile },
  { name: "market-data-import-register-desktop", path: "admin/market-price-import.php", selector: ".market-import-history", text: "Source and batch accountability", viewport: desktop },
  { name: "market-data-import-register-mobile", path: "admin/market-price-import.php", selector: ".market-import-history", text: "Source and batch accountability", viewport: mobile },
  { name: "administrator-support-desktop", path: "support.php", selector: ".support-register", text: "Platform support oversight", viewport: desktop },
  { name: "administrator-support-mobile", path: "support.php", selector: ".support-register", text: "Platform support oversight", viewport: mobile },
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
  await assertAccountFormFeedback(cdp, sessionId);
  await assertToastInteractions(cdp, sessionId);
  for (const definition of publicCaptures) await capture(cdp, sessionId, definition, records);
  await authenticate(cdp, sessionId, farmerEmail, farmerPassword);
  await assertWorkspaceSidebarInteractions(cdp, sessionId);
  await assertHeaderMenuInteractions(cdp, sessionId);
  await captureHeaderMenuStates(cdp, sessionId, records);
  await captureCollapsedSidebarState(cdp, sessionId, records);
  for (const definition of farmerCaptures) await capture(cdp, sessionId, definition, records);
  await assertNotificationDropdownMutations(cdp, sessionId);
  await authenticate(cdp, sessionId, buyerEmail, buyerPassword);
  for (const definition of buyerCaptures) await capture(cdp, sessionId, definition, records);
  await authenticate(cdp, sessionId, storageEmail, storagePassword);
  for (const definition of storageProviderCaptures) await capture(cdp, sessionId, definition, records);
  await authenticate(cdp, sessionId, transportEmail, transportPassword);
  for (const definition of transportProviderCaptures) await capture(cdp, sessionId, definition, records);
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

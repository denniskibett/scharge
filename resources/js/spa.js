/*
 * Silent background navigation for the dashboard (like Breve: no fade, no overlay, no full reloads).
 * - Links inside the dashboard (sidebar, header, tables) load in the background and swap only
 *   <main id="app-content">; the sidebar, header and open menus stay put. Back/forward work.
 * - Hover/touch prefetch: when a pointer rests on a link (65 ms) the page is fetched ahead, so the
 *   click swaps instantly from a short-lived cache (Breve's prefetch idea, kept inside the swap).
 * - window.schargeRefresh() reloads the current page's content in the background (used after saves).
 * - Page scripts in the swapped content run again, then "spa:load" fires so charts/calendars re-init.
 * Anything that doesn't fit (other layouts, downloads, errors) falls back to a normal load, and the
 * reason is logged to the console ("[spa] full load: ...") so such pages can be fixed.
 */
const CONTENT = "#app-content";
const CACHE_MS = 15000;
const cache = new Map(); // href -> { at, promise<{ ok, html, url, type, redirected }> }

function sameAppLink(a, event) {
  if (!a || (event && (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey))) return false;
  if (a.target && a.target !== "_self") return false;
  if (a.hasAttribute("download") || a.dataset.noSpa !== undefined || a.closest("[data-no-spa]")) return false;
  const href = a.getAttribute("href");
  if (!href || href.startsWith("#") || href.startsWith("javascript:") || href.startsWith("mailto:") || href.startsWith("tel:")) return false;
  const url = new URL(a.href, location.href);
  if (url.origin !== location.origin) return false;
  if (url.pathname === location.pathname && url.search === location.search && url.hash) return false;
  // Public website, auth, files and exports keep normal loads.
  if (/^\/(logout|login|register|public|host\/register|listings|developers|airbnb|api|storage|sitemap|confirm-email)/.test(url.pathname) || url.pathname === "/") return false;
  if (/\.(pdf|csv|xlsx?|zip|png|jpe?g|webp)$/i.test(url.pathname) || /export|download|pdf|receipt/i.test(url.pathname)) return false;
  return url;
}

function load(href) {
  const hit = cache.get(href);
  if (hit && Date.now() - hit.at < CACHE_MS) return hit.promise;
  const promise = fetch(href, { headers: { "X-Requested-With": "spa", Accept: "text/html" }, credentials: "same-origin" })
    .then(async (res) => ({ ok: res.ok, url: res.url || href, redirected: res.redirected, type: res.headers.get("content-type") || "", html: await res.text() }))
    .catch(() => null);
  cache.set(href, { at: Date.now(), promise });
  return promise;
}

function prefetch(a) {
  const url = sameAppLink(a);
  if (!url || url.href === location.href) return;
  const c = navigator.connection;
  if (c && (c.saveData || /2g/.test(c.effectiveType || ""))) return;
  load(url.href);
}

/** Runs the swapped page's scripts. Returns an error message if one could not run (e.g. it
 *  re-declares a top-level const/let from an earlier visit); the caller then loads the page normally. */
function runScripts(container) {
  let failed = null;
  const onloadBefore = window.onload;
  const onError = (e) => { if (e.error instanceof SyntaxError || /already been declared/.test(e.message || "")) failed = e.message; };
  window.addEventListener("error", onError);
  container.querySelectorAll("script").forEach((old) => {
    if (old.type && !/javascript|module/.test(old.type)) return; // JSON data blocks stay as they are
    const s = document.createElement("script");
    [...old.attributes].forEach((attr) => s.setAttribute(attr.name, attr.value));
    // The page is already loaded, so "wait for DOMContentLoaded / load" means "run now".
    s.textContent = old.textContent
      .replace(/document\.addEventListener\(\s*(['"])DOMContentLoaded\1\s*,/g, "window.__spaReady(")
      .replace(/window\.addEventListener\(\s*(['"])load\1\s*,/g, "window.__spaReady(");
    try { old.replaceWith(s); } catch (e) { failed = e.message; }
  });
  window.removeEventListener("error", onError);
  if (typeof window.onload === "function" && window.onload !== onloadBefore) setTimeout(() => window.onload(new Event("load")), 0);
  return failed;
}

// Used by swapped page scripts in place of DOMContentLoaded/load listeners (see runScripts).
window.__spaReady = (fn, ...rest) => { if (typeof fn === "function") setTimeout(() => fn(new Event("DOMContentLoaded")), 0); };

let current = 0;

function fullLoad(url, reason) {
  console.info("[spa] full load:", reason, url);
  try { sessionStorage.setItem("spa:lastFullLoad", JSON.stringify({ url, reason, at: Date.now() })); } catch (e) { /* private mode */ }
  location.href = url;
}

async function visit(url, { push = true, keepScroll = false, fresh = false } = {}) {
  const target = document.querySelector(CONTENT);
  if (!target) return (location.href = url);
  const ticket = ++current;
  if (fresh) cache.delete(url);
  const res = await load(url);
  if (ticket !== current) return; // a newer click won
  cache.delete(url); // a page is shown once from the prefetch, then fetched fresh next time
  if (!res) return fullLoad(url, "network error");
  if (!res.ok || !res.type.includes("text/html")) return fullLoad(url, res.ok ? "not html: " + res.type : "error page");
  if (res.redirected && /^\/(login|confirm-email)/.test(new URL(res.url).pathname)) return fullLoad(res.url, "redirected to " + new URL(res.url).pathname);
  const doc = new DOMParser().parseFromString(res.html, "text/html");
  const next = doc.querySelector(CONTENT);
  if (!next) return fullLoad(url, "different layout");

  const scrollBox = target.closest(".overflow-y-auto");
  const y = keepScroll && scrollBox ? scrollBox.scrollTop : 0;
  const A = window.Alpine;
  if (A && A.mutateDom && A.initTree) {
    // Swap with Alpine's observer paused, tear down the old components, run the page's scripts,
    // let scripts that register components on "alpine:init" do so, then start Alpine on the new content.
    A.mutateDom(() => {
      [...target.children].forEach((el) => A.destroyTree && A.destroyTree(el));
      target.innerHTML = next.innerHTML;
    });
    const failed = runScripts(target);
    if (failed) return fullLoad(url, "page script: " + failed);
    document.dispatchEvent(new CustomEvent("alpine:init"));
    [...target.children].forEach((el) => A.initTree(el));
  } else {
    target.innerHTML = next.innerHTML;
    runScripts(target);
  }
  document.title = doc.title || document.title;
  if (push) history.pushState({ spa: true }, "", res.url || url);
  if (scrollBox) scrollBox.scrollTop = y;
  window.dispatchEvent(new CustomEvent("spa:navigated", { detail: { url: res.url || url } }));
  document.dispatchEvent(new Event("spa:load"));
}

window.spaVisit = (url) => visit(new URL(url, location.href).href);
window.schargeRefresh = () => visit(location.href, { push: false, keepScroll: true, fresh: true });

if (document.querySelector(CONTENT)) {
  history.replaceState({ spa: true }, "", location.href);
  document.addEventListener("click", (event) => {
    const a = event.target.closest("a");
    const url = sameAppLink(a, event);
    if (!url) return;
    event.preventDefault();
    visit(url.href);
  });
  // Prefetch on hover (65 ms rest) and on touch, like Breve.
  let timer;
  document.addEventListener("mouseover", (e) => {
    const a = e.target.closest?.("a");
    clearTimeout(timer);
    if (a) timer = setTimeout(() => prefetch(a), 65);
  }, { passive: true });
  document.addEventListener("touchstart", (e) => prefetch(e.target.closest?.("a")), { passive: true });

  // Back/forward. A change of only the #fragment (e.g. a href="#" toggle) is not a new page.
  let shown = location.pathname + location.search;
  window.addEventListener("spa:navigated", () => (shown = location.pathname + location.search));
  window.addEventListener("popstate", () => {
    if (location.pathname + location.search === shown) return;
    visit(location.href, { push: false });
  });
}

/** Run a page initialiser now and after every background navigation. */
export function onPageLoad(fn) {
  document.addEventListener("DOMContentLoaded", fn);
  document.addEventListener("spa:load", fn);
}

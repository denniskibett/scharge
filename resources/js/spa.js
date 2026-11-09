/*
 * Background navigation for the dashboard: no full page reloads.
 * - Links inside the dashboard (sidebar, header, tables) load the next page in the background and swap
 *   only <main id="app-content">; the sidebar, header and open menus stay put. Back/forward work.
 * - window.schargeRefresh() reloads the current page's content in the background (used after saves
 *   instead of location.reload()).
 * - Page scripts in the swapped content run again, then "spa:load" fires so charts/calendars re-init.
 * Anything that doesn't fit (other layouts like the public website, downloads, errors) falls back to a
 * normal browser load, so nothing breaks.
 */
const CONTENT = "#app-content";

function sameAppLink(a, event) {
  if (!a || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return false;
  if (a.target && a.target !== "_self") return false;
  if (a.hasAttribute("download") || a.dataset.noSpa !== undefined || a.closest("[data-no-spa]")) return false;
  const href = a.getAttribute("href");
  if (!href || href.startsWith("#") || href.startsWith("javascript:") || href.startsWith("mailto:") || href.startsWith("tel:")) return false;
  const url = new URL(a.href, location.href);
  if (url.origin !== location.origin) return false;
  if (url.pathname === location.pathname && url.search === location.search && url.hash) return false;
  // Public website, auth, files and exports keep normal loads.
  if (/^\/(logout|login|register|public|host\/register|listings|developers|airbnb|api|storage|sitemap)/.test(url.pathname) || url.pathname === "/") return false;
  if (/\.(pdf|csv|xlsx?|zip|png|jpe?g|webp)$/i.test(url.pathname) || /export|download|pdf/i.test(url.pathname)) return false;
  return url;
}

/** Runs the swapped page's scripts. Returns false if one could not run (e.g. it re-declares a
 *  top-level const/let from an earlier visit); the caller then loads the page normally. */
function runScripts(container) {
  let failed = false;
  const onError = (e) => { if (e.error instanceof SyntaxError || /already been declared/.test(e.message || "")) failed = true; };
  window.addEventListener("error", onError);
  container.querySelectorAll("script").forEach((old) => {
    const s = document.createElement("script");
    [...old.attributes].forEach((attr) => s.setAttribute(attr.name, attr.value));
    s.textContent = old.textContent;
    try { old.replaceWith(s); } catch (e) { failed = true; }
  });
  window.removeEventListener("error", onError);
  return !failed;
}

let busy = null;

async function visit(url, { push = true, keepScroll = false } = {}) {
  const target = document.querySelector(CONTENT);
  if (!target) return (location.href = url);
  busy?.abort();
  busy = new AbortController();
  document.documentElement.classList.add("spa-loading");
  try {
    const res = await fetch(url, { headers: { "X-Requested-With": "spa", Accept: "text/html" }, signal: busy.signal, credentials: "same-origin" });
    const type = res.headers.get("content-type") || "";
    if (!res.ok || !type.includes("text/html") || res.redirected && new URL(res.url).pathname.startsWith("/login")) throw new Error("fallback");
    const html = await res.text();
    const doc = new DOMParser().parseFromString(html, "text/html");
    const fresh = doc.querySelector(CONTENT);
    if (!fresh) throw new Error("fallback"); // a different layout: load it normally
    const scrollBox = target.closest(".overflow-y-auto");
    const y = keepScroll && scrollBox ? scrollBox.scrollTop : 0;
    const A = window.Alpine;
    if (A && A.mutateDom && A.initTree) {
      // Swap with Alpine's observer paused, tear down the old components, run the page's scripts,
      // let scripts that register components on "alpine:init" do so, then start Alpine on the new content.
      A.mutateDom(() => {
        [...target.children].forEach((el) => A.destroyTree && A.destroyTree(el));
        target.innerHTML = fresh.innerHTML;
      });
      if (!runScripts(target)) throw new Error("fallback"); // load this page normally instead
      document.dispatchEvent(new CustomEvent("alpine:init"));
      [...target.children].forEach((el) => A.initTree(el));
    } else {
      target.innerHTML = fresh.innerHTML;
      runScripts(target);
    }
    document.title = doc.title || document.title;
    const finalUrl = res.url || url;
    if (push) history.pushState({ spa: true }, "", finalUrl);
    if (scrollBox) scrollBox.scrollTop = y;
    window.dispatchEvent(new CustomEvent("spa:navigated", { detail: { url: finalUrl } }));
    document.dispatchEvent(new Event("spa:load"));
  } catch (e) {
    if (e.name === "AbortError") return;
    location.href = url;
  } finally {
    document.documentElement.classList.remove("spa-loading");
  }
}

window.spaVisit = (url) => visit(url);
window.schargeRefresh = () => visit(location.href, { push: false, keepScroll: true });

if (document.querySelector(CONTENT)) {
  history.replaceState({ spa: true }, "", location.href);
  document.addEventListener("click", (event) => {
    const a = event.target.closest("a");
    const url = sameAppLink(a, event);
    if (!url) return;
    event.preventDefault();
    visit(url.href);
  });
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

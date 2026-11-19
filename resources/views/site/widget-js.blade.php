/* scharge homes widget (SiteFeedController::widget). Put <div data-scharge-homes></div> where the homes should show,
   then <script src="…/embed/{slug}.js" async></script>. Optional: data-limit="6". Renders in a shadow root, so the host
   site's styles cannot break it. Viewing requests go to the company in scharge, like its own site's form. */
(() => {
  const FEED = @json($feed);
  const COLOUR = @json($colour);
  const css = `
    :host { all: initial; display: block; font: 15px/1.55 Outfit, system-ui, -apple-system, "Segoe UI", sans-serif; color: #101828; }
    .grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); }
    .card { border: 1px solid #EAECF0; border-radius: 16px; overflow: hidden; background: #fff; transition: transform .2s, box-shadow .2s; }
    .card:hover { transform: translateY(-3px); box-shadow: 0 16px 36px -22px rgb(16 24 40 / .35); }
    .ph { position: relative; aspect-ratio: 16/10; background: #F2F4F7; }
    .ph img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .tag { position: absolute; top: 10px; left: 10px; background: #ECFDF3; color: #027A48; border-radius: 999px; padding: 3px 10px; font-size: 12px; font-weight: 600; }
    .in { padding: 14px 16px 16px; }
    h3 { margin: 0 0 2px; font-size: 16px; }
    .muted { color: #667085; font-size: 13.5px; margin: 0; }
    .price { font-weight: 700; margin: 8px 0 0; }
    .price small { color: #667085; font-weight: 500; }
    button, .btn { font: inherit; font-weight: 600; border: 0; border-radius: 10px; padding: 10px 14px; cursor: pointer; background: ${COLOUR}; color: #fff; transition: transform .1s, filter .15s; }
    button:active { transform: scale(.97); }
    .link { background: none; color: ${COLOUR}; padding: 0; margin-top: 10px; }
    form { display: grid; gap: 8px; margin-top: 10px; }
    input { font: inherit; padding: 10px 12px; border: 1px solid #EAECF0; border-radius: 10px; }
    input:focus { outline: none; border-color: ${COLOUR}; }
    .hp { position: absolute; left: -9999px; }
    .msg { font-size: 13.5px; margin: 8px 0 0; }
    .empty { border: 1px dashed #D0D5DD; border-radius: 16px; padding: 24px; text-align: center; color: #667085; }
    .foot { margin-top: 12px; font-size: 12px; color: #98A2B3; text-align: right; }
    .foot a { color: inherit; }
    @media (prefers-reduced-motion: reduce) { .card:hover { transform: none; } }`;
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const money = (n) => 'KES ' + Number(n || 0).toLocaleString('en-KE');

  async function mount(host) {
    if (host.__schargeMounted) return;
    host.__schargeMounted = true;
    const root = host.attachShadow({ mode: 'open' });
    root.innerHTML = `<style>${css}</style><div class="empty">Loading homes…</div>`;
    let data;
    try { data = await (await fetch(FEED, { headers: { Accept: 'application/json' } })).json(); }
    catch (e) { root.innerHTML = `<style>${css}</style><div class="empty">Homes could not be loaded right now.</div>`; return; }
    const limit = parseInt(host.dataset.limit || '0', 10);
    const homes = limit > 0 ? data.listings.slice(0, limit) : data.listings;
    const brand = data.brand || {};
    if (!homes.length) {
      root.innerHTML = `<style>${css}</style><div class="empty">No homes free right now.${brand.phone ? ` Call <a href="tel:${esc(brand.phone)}">${esc(brand.phone)}</a> and we will tell you when one opens.` : ''}</div>`;
      return;
    }
    root.innerHTML = `<style>${css}</style><div class="grid">${homes.map((h, i) => `
      <article class="card">
        <div class="ph">${h.photo ? `<img src="${esc(h.photo)}" alt="" loading="lazy">` : ''}<span class="tag">${esc(h.availability)}</span></div>
        <div class="in">
          <h3>${esc(h.title)}</h3><p class="muted">${esc(h.area)}</p>
          <p class="price">${money(h.price)} <small>/ ${esc(h.per)}</small></p>
          <p class="muted">${esc(h.costs)}</p>
          <button class="link" type="button" data-open="${i}">Book a viewing →</button>
          <form data-form="${i}" hidden>
            <input name="name" required maxlength="120" placeholder="Your name" aria-label="Your name">
            <input name="phone" type="tel" required maxlength="40" placeholder="Phone (WhatsApp)" aria-label="Phone">
            <input name="when" maxlength="120" placeholder="Preferred day" aria-label="Preferred day">
            <input name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
            <button type="submit">Request viewing</button>
            <p class="msg" role="status"></p>
          </form>
        </div>
      </article>`).join('')}</div><div class="foot">${brand.name ? esc(brand.name) + ' · ' : ''}<a href="${esc(brand.website || '#')}" target="_blank" rel="noopener">All homes</a></div>`;

    root.querySelectorAll('[data-open]').forEach((b) => b.addEventListener('click', () => {
      const f = root.querySelector(`[data-form="${b.dataset.open}"]`); f.hidden = !f.hidden; if (!f.hidden) f.querySelector('input').focus();
    }));
    root.querySelectorAll('form[data-form]').forEach((f) => f.addEventListener('submit', async (e) => {
      e.preventDefault();
      const h = homes[+f.dataset.form];
      const msg = f.querySelector('.msg'); const btn = f.querySelector('button[type=submit]');
      const body = new FormData(f);
      body.append('listing_title', h.title); body.append('listing_area', h.area || ''); body.append('listing_slug', h.slug);
      body.append('listing_owner', brand.name || ''); body.append('listing_action', 'Book a viewing');
      btn.disabled = true; msg.textContent = 'Sending…';
      try {
        const r = await fetch(data.viewing_endpoint, { method: 'POST', body, headers: { Accept: 'application/json' } });
        const j = await r.json().catch(() => ({}));
        if (r.ok) { msg.textContent = j.message || 'Request sent. We will call you back.'; f.querySelectorAll('input').forEach((i) => (i.disabled = true)); btn.hidden = true; }
        else { msg.textContent = j.message || (j.errors ? Object.values(j.errors)[0][0] : 'Could not send. Check your details.'); btn.disabled = false; }
      } catch (err) { msg.textContent = 'Could not send. Please try again.'; btn.disabled = false; }
    }));
  }

  const run = () => document.querySelectorAll('[data-scharge-homes]').forEach(mount);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
})();

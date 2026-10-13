/* scharge website: real form submissions (waitlist, book a demo) and the alert pop-up.
   Forms marked data-remote post with fetch and show the result in a pop-up; without JS they
   post normally and the server shows the same pop-up after the redirect. */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  /* ---------- Alert pop-up ---------- */
  var box = $('[data-site-alert]'), backdrop = $('[data-site-alert-backdrop]'), lastFocus = null;

  function closeAlert() {
    if (!box || box.hidden) return;
    box.hidden = true; backdrop.hidden = true;
    document.body.classList.remove('modal-open');
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  function showAlert(opts) {
    if (!box) { window.alert((opts.title || '') + '\n' + (opts.message || '')); return; }
    var type = opts.type === 'error' ? 'error' : 'success';
    box.classList.toggle('is-error', type === 'error');
    $('[data-site-alert-icon]', box).textContent = type === 'error' ? '!' : '✓';
    $('[data-site-alert-title]', box).textContent = opts.title || (type === 'error' ? 'Something went wrong.' : 'Done.');
    $('[data-site-alert-message]', box).textContent = opts.message || '';
    var list = $('[data-site-alert-list]', box);
    list.innerHTML = '';
    (opts.items || []).forEach(function (item) { var li = document.createElement('li'); li.textContent = item; list.appendChild(li); });
    list.hidden = !(opts.items && opts.items.length);
    lastFocus = document.activeElement;
    box.hidden = false; backdrop.hidden = false;
    document.body.classList.add('modal-open');
    $('[data-site-alert-close]', box).focus();
  }

  if (box) {
    $('[data-site-alert-close]', box).addEventListener('click', closeAlert);
    backdrop.addEventListener('click', closeAlert);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAlert(); });
  }
  window.siteAlert = { show: showAlert, close: closeAlert };
  if (window.__siteAlert) showAlert(window.__siteAlert);

  /* ---------- Field errors ---------- */
  function clearErrors(form) {
    $$('[aria-invalid="true"]', form).forEach(function (el) { el.removeAttribute('aria-invalid'); });
    $$('.field-error', form).forEach(function (el) { el.remove(); });
  }

  function showErrors(form, errors) {
    var first = null;
    Object.keys(errors).forEach(function (name) {
      var field = form.elements[name];
      if (!field || !field.insertAdjacentElement) return;
      field.setAttribute('aria-invalid', 'true');
      var msg = document.createElement('span');
      msg.className = 'field-error';
      msg.id = field.id + '-error';
      msg.textContent = errors[name][0];
      field.setAttribute('aria-describedby', msg.id);
      field.insertAdjacentElement('afterend', msg);
      first = first || field;
    });
    if (first) first.focus();
  }

  /* ---------- Context sent with Open listings forms (read from the page as it is now) ---------- */
  var text = function (sel) { var el = $(sel); return el ? el.textContent.trim() : ''; };
  var setField = function (form, name, value) { if (form.elements[name]) form.elements[name].value = value || ''; };

  function fillContext(form) {
    var kind = text('#browse [data-kind][aria-selected="true"]').replace(/\s*\(\d+\)$/, ''); // drop the tab's counter
    if (form.dataset.context === 'listing') {
      var sub = text('#d-sub'), comma = sub.indexOf(', ');
      setField(form, 'listing_title', text('#d-title'));
      setField(form, 'listing_area', comma > -1 ? sub.slice(0, comma) : sub);
      setField(form, 'listing_owner', comma > -1 ? sub.slice(comma + 2) : '');
      setField(form, 'listing_kind', kind);
      setField(form, 'listing_action', text('#d-form-title'));
    } else if (form.dataset.context === 'listing-search') {
      var max = $('#lmax');
      setField(form, 'search_kind', kind);
      setField(form, 'search_area', text('#area-chips [aria-pressed="true"]'));
      setField(form, 'search_max', max ? max.value : '');
    }
  }

  /* ---------- Submit ---------- */
  var token = $('meta[name="csrf-token"]');

  $$('form[data-remote]').forEach(function (form) {
    var button = $('button[type="submit"]', form), label = button ? button.textContent : '';

    $$('[data-reset]', form).forEach(function (b) {
      b.addEventListener('click', function () { form.reset(); clearErrors(form); form.classList.remove('is-sent'); var f = $('input, select', form); if (f) f.focus(); });
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      clearErrors(form);
      if (!form.reportValidity()) return;
      fillContext(form);
      if (button) { button.disabled = true; button.textContent = 'Sending…'; }

      fetch(form.action, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token ? token.content : '' },
        body: new FormData(form),
        credentials: 'same-origin'
      }).then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (data) { return { status: res.status, data: data }; });
      }).then(function (r) {
        if (r.status >= 200 && r.status < 300) {
          form.reset();
          form.classList.add('is-sent');
          showAlert(r.data);
        } else if (r.status === 422) {
          var errors = r.data.errors || {};
          showErrors(form, errors);
          showAlert({ type: 'error', title: 'Please check the form.', message: 'Some details need fixing:', items: Object.keys(errors).map(function (k) { return errors[k][0]; }) });
        } else if (r.status === 419) {
          showAlert({ type: 'error', title: 'This page has expired.', message: 'Refresh the page and try again. Your details were not sent.' });
        } else if (r.status === 429) {
          showAlert({ type: 'error', title: 'Too many tries.', message: 'Please wait a minute and try again.' });
        } else {
          showAlert({ type: 'error', title: r.data.title || 'Something went wrong.', message: r.data.message || 'We could not send your details. Please try again, or email hello@scharge.com.' });
        }
      }).catch(function () {
        showAlert({ type: 'error', title: 'No connection.', message: 'Check your internet connection and try again.' });
      }).then(function () {
        if (button) { button.disabled = false; button.textContent = label; }
      });
    });
  });
})();

/* Rashid Ahm MCP - dashboard scripts: copy buttons and the connection check. */
(function () {
  'use strict';

  window.rahmApi = function (rest, nonce, path, method, body) {
    var opt = { method: method || 'GET', headers: { 'X-WP-Nonce': nonce }, credentials: 'same-origin' };
    if (body instanceof FormData) { opt.body = body; }
    else if (body) { opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(body); }
    return fetch(rest + path, opt).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (j) {
        if (!r.ok) { var err = new Error((j && (j.error || j.message)) || ('HTTP ' + r.status)); err.status = r.status; err.code = j && j.code; throw err; }
        return j;
      });
    });
  };

  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) { e.className = cls; }
    if (text != null) { e.textContent = text; }
    return e;
  }
  window.rahmEl = el;

  window.rahmAgo = function (t, T) {
    var s = Math.max(0, Math.round(Date.now() / 1000 - t));
    T = T || function (x) { return x; };
    if (s < 10) { return T('just now'); }
    if (s < 60) { return s + ' ' + T('seconds ago'); }
    if (s < 3600) { return Math.round(s / 60) + ' ' + T('minutes ago'); }
    if (s < 86400) { return Math.round(s / 3600) + ' ' + T('hours ago'); }
    return new Date(t * 1000).toLocaleDateString();
  };

  /* Copy buttons */
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-copy]');
    if (!b) { return; }
    var f = b.parentNode.querySelector('input,textarea');
    if (!f) { return; }
    f.focus(); f.select();
    var done = function () { var o = b.textContent; b.textContent = '\u2713'; setTimeout(function () { b.textContent = o; }, 1400); };
    if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(f.value).then(done, function () { document.execCommand('copy'); done(); }); }
    else { document.execCommand('copy'); done(); }
  });

  document.addEventListener('DOMContentLoaded', function () {
    var h = document.getElementById('rahm-health');
    if (h) {
      var out = document.getElementById('rahm-health-text');
      var run = function () {
        out.className = 'rahm-health'; out.textContent = h.getAttribute('data-wait');
        fetch(h.getAttribute('data-url'), { cache: 'no-store', credentials: 'omit' }).then(function (r) {
          if (r.ok) { out.className = 'rahm-health ok'; out.textContent = '\u2713 ' + h.getAttribute('data-ok'); return; }
          out.className = 'rahm-health bad';
          var m = h.getAttribute('data-' + r.status);
          out.textContent = '\u2717 ' + (m || (h.getAttribute('data-other') + ' ' + r.status + '.'));
        }).catch(function () { out.className = 'rahm-health bad'; out.textContent = '\u2717 ' + h.getAttribute('data-fail'); });
      };
      document.getElementById('rahm-health-run').addEventListener('click', run);
      run();
    }
  });
})();

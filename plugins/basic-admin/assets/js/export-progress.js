(() => {
  'use strict';

  if (window.AppExportProgress) return;

  let overlay = null;
  let progressBar = null;
  let message = null;
  let detail = null;
  let active = false;

  function ensureOverlay() {
    if (overlay) return;
    const style = document.createElement('style');
    style.textContent = `
      .app-export-progress{position:fixed;inset:0;z-index:30000;display:none;place-items:center;padding:1.25rem;background:rgba(15,23,42,.56);backdrop-filter:blur(3px)}
      .app-export-progress.is-open{display:grid}
      .app-export-progress__panel{width:min(100%,440px);padding:1.5rem;border:1px solid var(--bs-border-color,#dee2e6);border-radius:1rem;background:var(--bs-body-bg,#fff);color:var(--bs-body-color,#212529);box-shadow:0 1.5rem 4rem rgba(0,0,0,.24)}
      .app-export-progress__heading{display:flex;align-items:center;gap:.8rem;margin-bottom:1rem;font-size:1.05rem;font-weight:700}
      .app-export-progress__icon{display:grid;width:2.5rem;height:2.5rem;place-items:center;border-radius:.75rem;background:var(--bs-primary-bg-subtle,#e7f1ff);color:var(--bs-primary,#0d6efd)}
      .app-export-progress__message{margin:0 0 .35rem;font-weight:600}
      .app-export-progress__detail{min-height:1.25rem;margin:0;color:var(--bs-secondary-color,#6c757d);font-size:.875rem}
      .app-export-progress__track{height:.7rem;margin:1rem 0 .65rem;overflow:hidden;border-radius:999px;background:var(--bs-tertiary-bg,#e9ecef)}
      .app-export-progress__bar{width:0;height:100%;border-radius:inherit;background:var(--bs-primary,#0d6efd);transition:width .18s ease}
      .app-export-progress.is-indeterminate .app-export-progress__bar{width:35%;animation:app-export-sweep 1.15s ease-in-out infinite}
      .app-export-progress.is-error .app-export-progress__icon{background:var(--bs-danger-bg-subtle,#f8d7da);color:var(--bs-danger,#dc3545)}
      .app-export-progress.is-complete .app-export-progress__icon{background:var(--bs-success-bg-subtle,#d1e7dd);color:var(--bs-success,#198754)}
      .app-export-progress__close{display:none;margin-top:1rem}
      .app-export-progress.is-error .app-export-progress__close{display:inline-flex}
      @keyframes app-export-sweep{0%{transform:translateX(-110%)}100%{transform:translateX(310%)}}
      @media(prefers-reduced-motion:reduce){.app-export-progress__bar{transition:none}.app-export-progress.is-indeterminate .app-export-progress__bar{animation:none;width:100%;opacity:.55}}
    `;
    document.head.appendChild(style);
    overlay = document.createElement('div');
    overlay.className = 'app-export-progress';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-hidden', 'true');
    overlay.setAttribute('aria-labelledby', 'app-export-progress-title');
    overlay.innerHTML = '<div class="app-export-progress__panel"><div class="app-export-progress__heading"><span class="app-export-progress__icon" aria-hidden="true"><i class="fa fa-file-arrow-down"></i></span><span id="app-export-progress-title">Preparing your export</span></div><p class="app-export-progress__message" aria-live="polite">Preparing your file…</p><p class="app-export-progress__detail">This may take a moment.</p><div class="app-export-progress__track" role="progressbar" aria-label="Export progress" aria-valuemin="0" aria-valuemax="100" aria-valuetext="Preparing export"><div class="app-export-progress__bar"></div></div><div class="d-flex justify-content-end"><button type="button" class="btn btn-outline-secondary btn-sm app-export-progress__close">Close</button></div></div>';
    document.body.appendChild(overlay);
    progressBar = overlay.querySelector('[role="progressbar"]');
    message = overlay.querySelector('.app-export-progress__message');
    detail = overlay.querySelector('.app-export-progress__detail');
    overlay.querySelector('.app-export-progress__close').addEventListener('click', hide);
  }

  function show() {
    ensureOverlay();
    overlay.classList.add('is-open', 'is-indeterminate');
    overlay.classList.remove('is-error', 'is-complete');
    overlay.setAttribute('aria-hidden', 'false');
    progressBar.removeAttribute('aria-valuenow');
    progressBar.setAttribute('aria-valuetext', 'Preparing export');
    message.textContent = 'Preparing your file…';
    detail.textContent = 'The export is being generated. Please keep this page open.';
  }

  function hide() {
    if (!overlay) return;
    overlay.classList.remove('is-open');
    overlay.setAttribute('aria-hidden', 'true');
  }

  function beginNativeDownload(url) {
    ensureOverlay();
    show();
    message.textContent = 'Export request sent';
    detail.textContent = 'Your file is being prepared. Check your browser downloads; you can continue using the app.';

    const download = document.createElement('a');
    download.href = url;
    download.target = '_blank';
    download.rel = 'noopener';
    download.dataset.appExport = 'off';
    download.style.display = 'none';
    document.body.appendChild(download);
    download.click();
    download.remove();

    window.setTimeout(hide, 4000);
  }

  function setProgress(loaded, total) {
    const percent = Math.max(0, Math.min(100, Math.round(loaded / total * 100)));
    overlay.classList.remove('is-indeterminate');
    progressBar.setAttribute('aria-valuenow', String(percent));
    progressBar.setAttribute('aria-valuetext', percent + '% downloaded');
    progressBar.firstElementChild.style.width = percent + '%';
    message.textContent = 'Downloading export… ' + percent + '%';
    detail.textContent = 'Downloaded ' + formatBytes(loaded) + ' of ' + formatBytes(total) + '.';
  }

  function formatBytes(bytes) {
    if (!Number.isFinite(bytes) || bytes < 1024) return Math.max(0, bytes || 0) + ' B';
    const units = ['KB', 'MB', 'GB'];
    let value = bytes / 1024;
    let unit = units[0];
    for (let i = 1; value >= 1024 && i < units.length; i++) {
      value /= 1024;
      unit = units[i];
    }
    return value.toFixed(1) + ' ' + unit;
  }

  function filenameFromResponse(response, fallback) {
    const disposition = response.headers.get('Content-Disposition') || '';
    const match = disposition.match(/filename\*?=(?:UTF-8''|")?([^";]+)/i);
    if (!match) return fallback || 'export';
    try {
      return decodeURIComponent(match[1].replace(/^"|"$/g, ''));
    } catch (error) {
      return match[1].replace(/^"|"$/g, '');
    }
  }

  async function run(url, options) {
    if (active) return;
    active = true;
    show();
    const config = options || {};
    try {
      const response = await fetch(url, {
        credentials: 'same-origin',
        cache: 'no-store',
        method: config.method || 'GET',
        body: config.body || undefined,
        headers: Object.assign(
          {'Accept': 'application/pdf, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, text/csv, application/octet-stream'},
          config.headers || {}
        )
      });
      if (!response.ok) {
        const responseText = await response.text();
        let errorMessage = '';
        try {
          const payload = JSON.parse(responseText);
          errorMessage = payload.message || payload.error || errorMessage;
        } catch (error) {}
        throw new Error(errorMessage || 'The export could not be generated. Please try again.');
      }

      const contentType = response.headers.get('Content-Type') || '';
      if (/text\/html|application\/json/i.test(contentType)) {
        throw new Error('The server did not return an export file. Your session may have expired; refresh the page and try again.');
      }

      const total = Number(response.headers.get('Content-Length') || 0);
      const chunks = [];
      let loaded = 0;
      if (response.body && response.body.getReader) {
        const reader = response.body.getReader();
        while (true) {
          const part = await reader.read();
          if (part.done) break;
          chunks.push(part.value);
          loaded += part.value.byteLength;
          if (total > 0) {
            setProgress(loaded, total);
          } else {
            message.textContent = 'Receiving your file…';
            detail.textContent = formatBytes(loaded) + ' received. Preparing download.';
          }
        }
      } else {
        const blob = await response.blob();
        chunks.push(blob);
        loaded = blob.size;
      }

      const blob = chunks.length === 1 && chunks[0] instanceof Blob
        ? chunks[0]
        : new Blob(chunks, {type: contentType || 'application/octet-stream'});
      if (blob.size === 0) throw new Error('The server returned an empty export. Please try again.');
      const fileName = filenameFromResponse(response, config.filename);
      const objectUrl = URL.createObjectURL(blob);
      const download = document.createElement('a');
      download.href = objectUrl;
      download.download = fileName;
      download.style.display = 'none';
      document.body.appendChild(download);
      download.click();
      download.remove();
      window.setTimeout(() => URL.revokeObjectURL(objectUrl), 60000);

      overlay.classList.remove('is-indeterminate');
      overlay.classList.add('is-complete');
      progressBar.setAttribute('aria-valuenow', '100');
      progressBar.setAttribute('aria-valuetext', 'Export complete');
      progressBar.firstElementChild.style.width = '100%';
      message.textContent = 'Export ready';
      detail.textContent = 'Your download has started. You can continue using the app.';
      window.setTimeout(hide, 1800);
    } catch (error) {
      overlay.classList.remove('is-indeterminate');
      overlay.classList.add('is-error');
      progressBar.setAttribute('aria-valuetext', 'Export failed');
      message.textContent = 'Export could not be completed';
      detail.textContent = error && error.message ? error.message : 'Please try again or contact your administrator.';
      console.error('[export] ' + (error && error.message ? error.message : 'Export failed'));
    } finally {
      active = false;
    }
  }

  document.addEventListener('click', event => {
    const link = event.target.closest && event.target.closest('a[data-app-export], a[download], a[href*="format=pdf"], a[href*="format=xlsx"], a[href*="format=xls"], a[href*="format=csv"]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    if (link.dataset.appExport === 'off') return;
    try {
      if (new URL(link.href, window.location.href).origin !== window.location.origin) return;
    } catch (error) {
      return;
    }
    event.preventDefault();
    beginNativeDownload(link.href);
  });

  window.AppExportProgress = {run, hide, beginNativeDownload};
})();

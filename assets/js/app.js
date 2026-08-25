/** Orchard Ledger interactions: concise feedback, protected JSON actions, and accessible mobile navigation. */
(() => {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const toggle = document.querySelector('[data-menu-toggle]');
  const nav = document.querySelector('[data-primary-nav]');

  toggle?.addEventListener('click', () => {
    const open = nav.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', String(open));
  });

  window.qliFetch = async (url, options = {}) => {
    const response = await fetch(url, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf, ...(options.headers || {}) },
      ...options,
    });
    const payload = await response.json().catch(() => ({ success: false, message: 'The server returned an unreadable response.', data: {} }));
    if (!response.ok || !payload.success) throw new Error(payload.message || 'The request could not be completed.');
    return payload;
  };

  document.querySelectorAll('[data-ajax-form]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const submit = form.querySelector('[type="submit"]');
      const feedback = form.querySelector('[data-form-feedback]');
      submit.disabled = true;
      submit.dataset.originalText ||= submit.textContent;
      submit.textContent = 'Working…';
      try {
        const payload = await window.qliFetch(form.action, { method: form.method || 'POST', body: new FormData(form) });
        if (feedback) { feedback.textContent = payload.message; feedback.className = 'flash flash-success'; }
        if (payload.data?.redirect) window.location.assign(payload.data.redirect);
      } catch (error) {
        if (feedback) { feedback.textContent = error.message; feedback.className = 'flash flash-error'; }
      } finally {
        submit.disabled = false;
        submit.textContent = submit.dataset.originalText;
      }
    });
  });
})();


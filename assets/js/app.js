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

  document.querySelectorAll('[data-marketplace-filter]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const resultTarget = document.querySelector('[data-marketplace-results]');
      const feedback = document.querySelector('[data-marketplace-feedback]');
      const submit = form.querySelector('[type="submit"]');
      const query = new URLSearchParams(new FormData(form));
      submit.disabled = true;
      if (feedback) feedback.textContent = 'Refreshing available produce…';
      try {
        const payload = await window.qliFetch(`${form.dataset.endpoint}?${query.toString()}`);
        if (resultTarget) resultTarget.innerHTML = payload.data.html;
        if (feedback) feedback.textContent = payload.message;
        window.QuettaStore?.setState({ marketplaceFilters: Object.fromEntries(query) });
      } catch (error) {
        if (feedback) feedback.textContent = error.message;
      } finally {
        submit.disabled = false;
      }
    });
  });

  document.querySelectorAll('[data-favorite-toggle]').forEach((button) => {
    button.addEventListener('click', async () => {
      const original = button.textContent;
      button.disabled = true;
      try {
        const body = new FormData();
        body.append('listing_id', button.dataset.favoriteToggle);
        const payload = await window.qliFetch(button.dataset.endpoint, { method: 'POST', body });
        button.classList.toggle('is-saved', payload.data.saved);
        button.textContent = payload.data.saved ? 'Saved to favourites' : 'Save listing';
      } catch (error) {
        button.textContent = error.message;
      } finally {
        window.setTimeout(() => { if (!button.classList.contains('is-saved')) button.textContent = original; }, 1800);
        button.disabled = false;
      }
    });
  });

  document.querySelectorAll('[data-price-filter]').forEach((form) => {
    form.addEventListener('change', async () => {
      const target = document.querySelector('[data-price-results]');
      const feedback = document.querySelector('[data-price-feedback]');
      const query = new URLSearchParams(new FormData(form));
      if (feedback) feedback.textContent = 'Loading recorded prices…';
      try {
        const payload = await window.qliFetch(`${form.dataset.endpoint}?${query.toString()}`);
        if (target) target.innerHTML = payload.data.html;
        if (feedback) feedback.textContent = payload.message;
      } catch (error) {
        if (feedback) feedback.textContent = error.message;
      }
    });
  });
})();

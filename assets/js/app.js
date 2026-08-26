/** Orchard Ledger interactions: concise feedback, protected JSON actions, and accessible mobile navigation. */
(() => {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const toggle = document.querySelector('[data-menu-toggle]');
  const nav = document.querySelector('[data-primary-nav]');

  toggle?.addEventListener('click', () => {
    const open = nav.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', String(open));
  });

  document.querySelectorAll('.workspace-sidebar').forEach((sidebar, index) => {
    const workspaceNav = sidebar.querySelector('nav[aria-label="Workspace navigation"]');
    const roleLabel = sidebar.querySelector('.role-label');
    const roleName = sidebar.querySelector('h2');
    if (!workspaceNav || !roleLabel || !roleName) return;
    const summary = document.createElement('div');
    summary.className = 'workspace-nav-summary';
    workspaceNav.id = `workspace-task-menu-${index}`;
    const taskToggle = document.createElement('button');
    taskToggle.type = 'button';
    taskToggle.className = 'workspace-menu-toggle';
    taskToggle.setAttribute('aria-controls', workspaceNav.id);
    taskToggle.setAttribute('aria-expanded', 'false');
    taskToggle.textContent = 'Tasks';
    sidebar.insertBefore(summary, sidebar.firstChild);
    summary.append(roleLabel, roleName, taskToggle);
    const setTaskMenu = (open) => {
      workspaceNav.classList.toggle('is-open', open);
      taskToggle.setAttribute('aria-expanded', String(open));
      taskToggle.textContent = open ? 'Close tasks' : 'Tasks';
    };
    taskToggle.addEventListener('click', () => setTaskMenu(!workspaceNav.classList.contains('is-open')));
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && workspaceNav.classList.contains('is-open')) { setTaskMenu(false); taskToggle.focus(); } });
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
      const loading = document.querySelector('[data-marketplace-loading]');
      const resultWrap = resultTarget?.closest('.marketplace-result-wrap');
      const query = new URLSearchParams(new FormData(form));
      submit.disabled = true;
      resultWrap?.classList.add('is-loading');
      loading?.setAttribute('aria-hidden', 'false');
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
        resultWrap?.classList.remove('is-loading');
        loading?.setAttribute('aria-hidden', 'true');
      }
    });

    form.addEventListener('change', (event) => {
      if (event.target.matches('[name="category_id"], [name="sort"]')) form.requestSubmit();
    });
  });

  document.querySelectorAll('[data-saved-marketplace-filter]').forEach((form) => {
    form.addEventListener('submit', () => {
      const marketplaceForm = document.querySelector('[data-marketplace-filter]');
      if (!marketplaceForm) return;
      const currentFilters = new FormData(marketplaceForm);
      form.querySelectorAll('[data-saved-filter-value]').forEach((input) => {
        input.value = currentFilters.get(input.name) || '';
      });
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

  const notificationLink = document.querySelector('[data-notification-link]');
  const notificationChime = document.querySelector('[data-notification-chime]');
  if (notificationLink && notificationChime) {
    let latestNotificationId = Number(notificationLink.dataset.notificationLatestId || 0);
    let audioContext = null;
    let soundEnabled = notificationChime.dataset.notificationChimeEnabled === '1';
    const updateSoundToggle = () => {
      notificationChime.setAttribute('aria-pressed', String(soundEnabled));
      notificationChime.textContent = soundEnabled ? 'Sound on' : 'Sound off';
      notificationChime.title = soundEnabled ? 'Disable notification sound' : 'Enable notification sound';
    };
    const playBell = () => {
      if (!soundEnabled || !window.AudioContext && !window.webkitAudioContext) return;
      const AudioApi = window.AudioContext || window.webkitAudioContext;
      audioContext ||= new AudioApi();
      if (audioContext.state === 'suspended') audioContext.resume();
      const now = audioContext.currentTime;
      [880, 1174].forEach((frequency, index) => {
        const oscillator = audioContext.createOscillator();
        const gain = audioContext.createGain();
        oscillator.type = 'sine';
        oscillator.frequency.setValueAtTime(frequency, now + index * 0.12);
        gain.gain.setValueAtTime(0.0001, now + index * 0.12);
        gain.gain.exponentialRampToValueAtTime(0.11, now + index * 0.12 + 0.015);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + index * 0.12 + 0.28);
        oscillator.connect(gain).connect(audioContext.destination);
        oscillator.start(now + index * 0.12);
        oscillator.stop(now + index * 0.12 + 0.3);
      });
    };
    updateSoundToggle();
    notificationChime.addEventListener('click', async () => {
      const next = !soundEnabled;
      notificationChime.disabled = true;
      try {
        const body = new FormData();
        body.append('browser_chime_enabled', next ? '1' : '0');
        await window.qliFetch(notificationChime.dataset.notificationChimeEndpoint, { method: 'POST', body });
        soundEnabled = next;
        updateSoundToggle();
        if (soundEnabled) playBell();
      } catch (_) { /* The page remains usable when optional preference storage is temporarily unavailable. */ }
      finally { notificationChime.disabled = false; }
    });
    const refreshNotificationSummary = async () => {
      try {
        const response = await fetch(notificationLink.dataset.notificationEndpoint, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        const payload = await response.json();
        if (!payload.success) return;
        const count = Number(payload.data?.count || 0);
        const newest = Number(payload.data?.latest_id || 0);
        const badge = notificationLink.querySelector('[data-notification-count]');
        if (badge) { badge.hidden = count === 0; badge.textContent = count > 9 ? '9+' : String(count); }
        if (newest > latestNotificationId) playBell();
        latestNotificationId = Math.max(latestNotificationId, newest);
      } catch (_) { /* Header polling is optional feedback; no visual error is needed. */ }
    };
    window.setInterval(refreshNotificationSummary, 45000);
  }
})();

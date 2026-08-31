/** Shared product interactions: protected actions, responsive navigation, and accessible data views. */
(() => {
  document.documentElement.classList.add('js');
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const toggle = document.querySelector('[data-menu-toggle]');
  const nav = document.querySelector('[data-primary-nav]');

  toggle?.addEventListener('click', () => {
    const open = nav.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', String(open));
    toggle.textContent = open ? 'Close' : 'Navigation';
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && nav?.classList.contains('is-open')) {
      nav.classList.remove('is-open');
      toggle?.setAttribute('aria-expanded', 'false');
      if (toggle) { toggle.textContent = 'Navigation'; toggle.focus(); }
    }
  });

  const toastRegion = document.querySelector('[data-toast-region]');
  const closeToast = (toast, reason = 'dismissed') => {
    if (!toast || toast.classList.contains('is-leaving')) return;
    window.clearTimeout(toast.qliTimer);
    toast.classList.remove('is-paused');
    toast.classList.add('is-leaving');
    toast.dataset.closeReason = reason;
    window.setTimeout(() => toast.remove(), 280);
  };
  const initialiseToast = (toast) => {
    if (!toast || toast.dataset.toastReady === 'true') return toast;
    toast.dataset.toastReady = 'true';
    const timeout = Math.max(1000, Number(toast.dataset.toastTimeout || 7000));
    let remaining = timeout;
    let startedAt = performance.now();
    const progress = toast.querySelector('.toast-progress');
    toast.style.setProperty('--toast-duration', `${timeout}ms`);
    const resume = () => {
      if (toast.classList.contains('is-leaving') || remaining <= 0) return;
      startedAt = performance.now();
      toast.classList.remove('is-paused');
      window.clearTimeout(toast.qliTimer);
      toast.qliTimer = window.setTimeout(() => closeToast(toast, 'timeout'), remaining);
    };
    const pause = () => {
      if (toast.classList.contains('is-leaving') || toast.classList.contains('is-paused')) return;
      remaining = Math.max(0, remaining - (performance.now() - startedAt));
      window.clearTimeout(toast.qliTimer);
      toast.classList.add('is-paused');
    };
    toast.qliPause = pause;
    toast.qliResume = resume;
    toast.querySelector('[data-toast-dismiss]')?.addEventListener('click', () => closeToast(toast, 'manual'));
    toast.addEventListener('mouseenter', pause);
    toast.addEventListener('mouseleave', resume);
    toast.addEventListener('focusin', pause);
    toast.addEventListener('focusout', (event) => { if (!toast.contains(event.relatedTarget)) resume(); });
    if (progress) progress.addEventListener('animationend', () => { if (!toast.classList.contains('is-paused')) closeToast(toast, 'timeout'); });
    window.requestAnimationFrame(() => {
      toast.classList.add('is-visible');
      resume();
    });
    return toast;
  };
  window.QuettaToast = {
    show(message, type = 'success', options = {}) {
      if (!toastRegion || !message) return null;
      const tone = type === 'error' ? 'error' : 'success';
      const toast = document.createElement('div');
      toast.className = `flash flash-${tone} toast`;
      toast.dataset.toast = '';
      toast.dataset.toastTimeout = String(options.timeout || (tone === 'error' ? 9000 : 7000));
      toast.setAttribute('role', tone === 'error' ? 'alert' : 'status');
      toast.setAttribute('aria-atomic', 'true');
      const symbol = document.createElement('span');
      symbol.className = 'toast-symbol';
      symbol.setAttribute('aria-hidden', 'true');
      symbol.textContent = tone === 'error' ? '!' : '✓';
      const copy = document.createElement('div');
      copy.className = 'toast-message';
      const title = document.createElement('strong');
      title.textContent = options.title || (tone === 'error' ? 'Action needs attention' : 'Action completed');
      const body = document.createElement('p');
      body.textContent = message;
      copy.append(title, body);
      const dismiss = document.createElement('button');
      dismiss.className = 'toast-dismiss';
      dismiss.type = 'button';
      dismiss.dataset.toastDismiss = '';
      dismiss.setAttribute('aria-label', 'Dismiss notification');
      dismiss.textContent = '×';
      const progress = document.createElement('span');
      progress.className = 'toast-progress';
      progress.setAttribute('aria-hidden', 'true');
      toast.append(symbol, copy, dismiss, progress);
      toastRegion.append(toast);
      return initialiseToast(toast);
    },
    dismiss(toast) { closeToast(toast, 'manual'); },
  };
  toastRegion?.querySelectorAll('[data-toast]').forEach(initialiseToast);
  document.addEventListener('visibilitychange', () => {
    toastRegion?.querySelectorAll('[data-toast]').forEach((toast) => {
      if (document.hidden) toast.qliPause?.();
      else toast.qliResume?.();
    });
  });

  const marketLayout = document.querySelector('.market-layout');
  const marketFilterRail = marketLayout?.querySelector('.market-filter-rail');
  if (marketLayout && marketFilterRail) {
    marketFilterRail.id ||= 'marketplace-filter-drawer';
    const filterToggle = document.createElement('button');
    filterToggle.type = 'button';
    filterToggle.className = 'filter-drawer-toggle';
    filterToggle.textContent = 'Filters and sorting';
    filterToggle.setAttribute('aria-controls', marketFilterRail.id);
    filterToggle.setAttribute('aria-expanded', 'false');
    marketLayout.insertBefore(filterToggle, marketFilterRail);
    filterToggle.addEventListener('click', () => {
      const open = marketFilterRail.classList.toggle('is-open');
      filterToggle.setAttribute('aria-expanded', String(open));
      filterToggle.textContent = open ? 'Hide filters' : 'Filters and sorting';
    });
  }

  const storageLayout = document.querySelector('.storage-discovery-layout');
  const storageFilterRail = storageLayout?.querySelector('.storage-filter-rail');
  if (storageLayout && storageFilterRail) {
    storageFilterRail.id ||= 'storage-filter-drawer';
    const storageFilterToggle = document.createElement('button');
    storageFilterToggle.type = 'button';
    storageFilterToggle.className = 'filter-drawer-toggle';
    storageFilterToggle.textContent = 'Storage filters and sorting';
    storageFilterToggle.setAttribute('aria-controls', storageFilterRail.id);
    storageFilterToggle.setAttribute('aria-expanded', 'false');
    storageLayout.insertBefore(storageFilterToggle, storageFilterRail);
    storageFilterToggle.addEventListener('click', () => {
      const open = storageFilterRail.classList.toggle('is-open');
      storageFilterToggle.setAttribute('aria-expanded', String(open));
      storageFilterToggle.textContent = open ? 'Hide storage filters' : 'Storage filters and sorting';
    });
  }

  document.querySelectorAll('.data-table').forEach((table) => {
    const labels = [...table.querySelectorAll('thead th')].map((heading) => heading.textContent.trim());
    table.querySelectorAll('tbody tr').forEach((row) => {
      [...row.children].forEach((cell, index) => {
        if (cell.tagName === 'TD' && !cell.hasAttribute('colspan')) cell.dataset.label = labels[index] || 'Record detail';
      });
    });
  });

  document.querySelectorAll('.workspace-sidebar').forEach((sidebar, index) => {
    const workspace = sidebar.closest('.workspace');
    const workspaceMain = workspace?.querySelector('.workspace-main');
    const workspaceNav = sidebar.querySelector('nav[aria-label="Workspace navigation"]');
    const roleLabel = sidebar.querySelector('.role-label');
    const roleName = sidebar.querySelector('h2');
    if (!workspace || !workspaceMain || !workspaceNav || !roleLabel || !roleName) return;

    const storageKey = 'qli-workspace-sidebar-collapsed';
    const compactViewport = window.matchMedia('(max-width: 960px)');
    workspaceNav.id = `workspace-navigation-${index}`;
    sidebar.id = `workspace-sidebar-${index}`;

    const identity = document.createElement('div');
    identity.className = 'workspace-sidebar-identity';
    identity.append(roleLabel, roleName);

    const sidebarHeader = document.createElement('div');
    sidebarHeader.className = 'workspace-sidebar-header workspace-nav-summary';

    const sidebarToggle = document.createElement('button');
    sidebarToggle.type = 'button';
    sidebarToggle.className = 'workspace-sidebar-toggle';
    sidebarToggle.setAttribute('aria-controls', sidebar.id);
    sidebarToggle.innerHTML = '<span class="workspace-sidebar-toggle-icon" aria-hidden="true"></span><span class="workspace-sidebar-toggle-label">Collapse</span>';
    sidebarHeader.append(identity, sidebarToggle);
    sidebar.insertBefore(sidebarHeader, workspaceNav);

    const mobileOpener = document.createElement('button');
    mobileOpener.type = 'button';
    mobileOpener.className = 'workspace-sidebar-opener';
    mobileOpener.setAttribute('aria-controls', sidebar.id);
    mobileOpener.setAttribute('aria-expanded', 'false');
    const openerIcon = document.createElement('span');
    openerIcon.className = 'workspace-sidebar-opener-icon';
    openerIcon.setAttribute('aria-hidden', 'true');
    const openerCopy = document.createElement('span');
    openerCopy.className = 'workspace-sidebar-opener-copy';
    const openerEyebrow = document.createElement('small');
    openerEyebrow.textContent = roleLabel.textContent;
    const openerLabel = document.createElement('strong');
    openerLabel.textContent = 'Open workspace navigation';
    openerCopy.append(openerEyebrow, openerLabel);
    mobileOpener.append(openerIcon, openerCopy);
    workspaceMain.insertBefore(mobileOpener, workspaceMain.firstChild);

    const backdrop = document.createElement('button');
    backdrop.type = 'button';
    backdrop.className = 'workspace-sidebar-backdrop';
    backdrop.tabIndex = -1;
    backdrop.setAttribute('aria-label', 'Close workspace navigation');
    workspace.insertBefore(backdrop, workspaceMain);

    workspaceNav.querySelectorAll('a').forEach((link) => {
      const label = link.querySelector('.workspace-link-label')?.textContent?.trim() || link.textContent.trim();
      link.title = link.target === '_blank' ? `${label} (opens in a new tab)` : label;
    });

    const readStoredCollapse = () => {
      try { return window.localStorage.getItem(storageKey) === 'true'; }
      catch (_) { return false; }
    };
    const storeCollapse = (collapsed) => {
      try { window.localStorage.setItem(storageKey, String(collapsed)); }
      catch (_) { /* Sidebar persistence is an optional enhancement. */ }
    };
    const setDesktopCollapsed = (collapsed, persist = true) => {
      workspace.classList.toggle('is-sidebar-collapsed', collapsed);
      sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
      sidebarToggle.setAttribute('aria-label', collapsed ? 'Expand workspace navigation' : 'Collapse workspace navigation');
      sidebarToggle.querySelector('.workspace-sidebar-toggle-label').textContent = collapsed ? 'Expand' : 'Collapse';
      if (persist) storeCollapse(collapsed);
    };
    const setMobileOpen = (open, returnFocus = false) => {
      workspace.classList.toggle('is-sidebar-open', open);
      document.body.classList.toggle('workspace-navigation-open', open);
      mobileOpener.setAttribute('aria-expanded', String(open));
      sidebarToggle.setAttribute('aria-expanded', String(open));
      sidebarToggle.setAttribute('aria-label', 'Close workspace navigation');
      sidebarToggle.querySelector('.workspace-sidebar-toggle-label').textContent = 'Close';
      sidebar.setAttribute('aria-hidden', String(!open));
      sidebar.inert = !open;
      if (open) window.setTimeout(() => sidebarToggle.focus(), 40);
      else if (returnFocus) mobileOpener.focus();
    };
    const syncViewport = () => {
      if (compactViewport.matches) {
        workspace.classList.remove('is-sidebar-collapsed');
        setMobileOpen(false);
      } else {
        workspace.classList.remove('is-sidebar-open');
        document.body.classList.remove('workspace-navigation-open');
        sidebar.removeAttribute('aria-hidden');
        sidebar.inert = false;
        mobileOpener.setAttribute('aria-expanded', 'false');
        setDesktopCollapsed(readStoredCollapse(), false);
      }
    };

    sidebarToggle.addEventListener('click', () => {
      if (compactViewport.matches) setMobileOpen(false, true);
      else setDesktopCollapsed(!workspace.classList.contains('is-sidebar-collapsed'));
    });
    mobileOpener.addEventListener('click', () => setMobileOpen(true));
    backdrop.addEventListener('click', () => setMobileOpen(false, true));
    workspaceNav.addEventListener('click', (event) => {
      if (compactViewport.matches && event.target.closest('a')) setMobileOpen(false);
    });
    document.addEventListener('keydown', (event) => {
      if (!compactViewport.matches || !workspace.classList.contains('is-sidebar-open')) return;
      if (event.key === 'Escape') {
        event.preventDefault();
        setMobileOpen(false, true);
        return;
      }
      if (event.key !== 'Tab') return;
      const focusable = [...sidebar.querySelectorAll('a[href], button:not([disabled]), input:not([type="hidden"])')].filter((element) => !element.inert && element.offsetParent !== null);
      if (focusable.length === 0) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    compactViewport.addEventListener?.('change', syncViewport);
    syncViewport();
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
        window.QuettaToast?.show(payload.message, 'success');
        if (payload.data?.redirect) window.location.assign(payload.data.redirect);
      } catch (error) {
        if (feedback) { feedback.textContent = error.message; feedback.className = 'flash flash-error'; }
        window.QuettaToast?.show(error.message, 'error');
      } finally {
        submit.disabled = false;
        submit.textContent = submit.dataset.originalText;
      }
    });
  });

  document.querySelectorAll('[data-account-form]').forEach((form) => {
    const mode = form.dataset.accountForm;
    const errorFor = (field) => form.querySelector(`[data-field-error="${field.name}"]`);
    const messageFor = (field) => {
      const value = field.value.trim();
      if (field.name === 'full_name') return value.length < 3 ? 'Enter at least 3 characters for your name.' : '';
      if (field.name === 'email') return value === '' ? 'Enter your email address.' : (!field.validity.valid ? 'Enter a valid email address.' : '');
      if (field.name === 'phone') return value === '' ? 'Enter a contact number.' : (!/^[0-9+()\-\s]{7,25}$/.test(value) ? 'Use 7–25 digits and standard phone symbols only.' : '');
      if (field.name === 'role') return field.value === '' ? 'Choose the role for this account.' : '';
      if (field.name === 'password') {
        if (value === '') return 'Enter your password.';
        if (mode === 'register' && (value.length < 10 || !/[A-Za-z]/.test(value) || !/\d/.test(value))) return 'Use at least 10 characters with a letter and a number.';
      }
      return '';
    };
    const validate = (field) => {
      if (!field || !['full_name', 'email', 'phone', 'role', 'password'].includes(field.name)) return true;
      const message = messageFor(field);
      const error = errorFor(field);
      field.classList.toggle('is-invalid', message !== '');
      field.setAttribute('aria-invalid', String(message !== ''));
      if (error) {
        error.textContent = message;
        error.hidden = message === '';
      }
      return message === '';
    };
    form.querySelectorAll('input, select').forEach((field) => {
      field.addEventListener('blur', () => validate(field));
      field.addEventListener('input', () => validate(field));
      field.addEventListener('change', () => validate(field));
    });
    form.addEventListener('submit', (event) => {
      const invalid = [...form.querySelectorAll('input, select')].find((field) => !validate(field));
      if (invalid) {
        event.preventDefault();
        invalid.focus();
      }
    });
  });

  document.querySelectorAll('[data-marketplace-filter]').forEach((form) => {
    const heading = form.querySelector('h2');
    if (heading && !form.querySelector('[name="search"]')) {
      const field = document.createElement('div');
      field.className = 'form-field marketplace-search-field';
      const label = document.createElement('label');
      label.htmlFor = 'marketplace-search';
      label.textContent = 'Search produce';
      const input = document.createElement('input');
      input.id = 'marketplace-search';
      input.name = 'search';
      input.type = 'search';
      input.maxLength = 80;
      input.autocomplete = 'off';
      input.placeholder = 'Crop or listing name';
      const help = document.createElement('span');
      help.className = 'form-help';
      help.textContent = 'Results update as you type.';
      field.append(label, input, help);
      heading.insertAdjacentElement('afterend', field);
    }
    if (!form.querySelector('[name="harvest_from"]')) {
      const quantity = form.querySelector('[name="min_quantity"]')?.closest('.form-field');
      const from = document.createElement('div'); from.className = 'form-field'; from.innerHTML = '<label for="harvest-from">Harvested from</label><input id="harvest-from" name="harvest_from" type="date">';
      const to = document.createElement('div'); to.className = 'form-field'; to.innerHTML = '<label for="harvest-to">Harvested to</label><input id="harvest-to" name="harvest_to" type="date">';
      quantity?.insertAdjacentElement('afterend', to); quantity?.insertAdjacentElement('afterend', from);
    }
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

    let marketplaceSearchDelay;
    form.addEventListener('input', (event) => {
      if (!event.target.matches('[name="search"], [name="min_price"], [name="max_price"], [name="min_quantity"], [name="harvest_from"], [name="harvest_to"]')) return;
      window.clearTimeout(marketplaceSearchDelay);
      marketplaceSearchDelay = window.setTimeout(() => form.requestSubmit(), 280);
    });
    form.addEventListener('change', (event) => {
      if (event.target.matches('select')) form.requestSubmit();
    });
  });

  document.querySelectorAll('[data-storage-filter]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const resultTarget = document.querySelector('[data-storage-results]');
      const feedback = document.querySelector('[data-storage-feedback]');
      const submit = form.querySelector('[type="submit"]');
      const query = new URLSearchParams(new FormData(form));
      submit.disabled = true;
      if (feedback) feedback.textContent = 'Refreshing available storage…';
      try {
        const payload = await window.qliFetch(`${form.dataset.endpoint}?${query.toString()}`);
        if (resultTarget) resultTarget.innerHTML = payload.data.html;
        if (feedback) feedback.textContent = payload.message;
        window.history.replaceState({}, '', `${window.location.pathname}?${query.toString()}`);
      } catch (error) {
        if (feedback) feedback.textContent = error.message;
      } finally {
        submit.disabled = false;
      }
    });
    let storageFilterDelay;
    form.addEventListener('input', (event) => {
      if (!event.target.matches('input[type="number"]')) return;
      window.clearTimeout(storageFilterDelay);
      storageFilterDelay = window.setTimeout(() => form.requestSubmit(), 280);
    });
    form.addEventListener('change', (event) => {
      if (event.target.matches('select')) form.requestSubmit();
    });
  });

  const facilitySelect = document.querySelector('#facility_id');
  if (facilitySelect) {
    const requestedFacility = new URLSearchParams(window.location.search).get('facility');
    if (requestedFacility && facilitySelect.querySelector(`option[value="${CSS.escape(requestedFacility)}"]`)) facilitySelect.value = requestedFacility;
    document.querySelectorAll('[data-facility-id]').forEach((link) => link.addEventListener('click', () => {
      if (facilitySelect.querySelector(`option[value="${CSS.escape(link.dataset.facilityId)}"]`)) facilitySelect.value = link.dataset.facilityId;
    }));
  }

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
        window.QuettaToast?.show(`Notification sound ${soundEnabled ? 'enabled' : 'disabled'}.`, 'success', { title: 'Preference updated', timeout: 5000 });
      } catch (error) { window.QuettaToast?.show(error.message, 'error'); }
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
        if (newest > latestNotificationId) {
          playBell();
          window.QuettaToast?.show('A new workspace notification is ready to review.', 'success', { title: 'New notification', timeout: 8000 });
        }
        latestNotificationId = Math.max(latestNotificationId, newest);
      } catch (_) { /* Header polling is optional feedback; no visual error is needed. */ }
    };
    window.setInterval(refreshNotificationSummary, 45000);
  }
})();

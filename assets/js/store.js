/** Orchard Ledger UI state: a minimal pub/sub store for transient filters, selection, and loading—not server authority. */
(function (global) {
  const state = { currentUser: null, notifications: 0, marketplaceFilters: {}, pagination: {}, selectedListing: null, booking: {}, loading: false };
  const listeners = new Set();
  const snapshot = () => JSON.parse(JSON.stringify(state));
  const emit = () => listeners.forEach((listener) => listener(snapshot()));

  global.QuettaStore = {
    getState: snapshot,
    setState(patch) { Object.assign(state, patch); emit(); },
    subscribe(listener) { listeners.add(listener); return () => listeners.delete(listener); },
  };
})(window);


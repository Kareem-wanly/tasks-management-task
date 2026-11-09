// UI preferences only (theme, motion, language, views, saved filters, project colours). Auth data never goes through here: see lib/tokenStorage.ts.

export type PreferenceKey =
  | 'ui-theme'
  | 'ui-motion'
  | 'ui-language'
  | 'projects-view'
  | 'project-tasks-view'
  | 'tasks-view'
  | 'tasks-saved-views'
  | 'board-settings'
  | 'project-appearance';

export function readPreference(key: PreferenceKey): string | null {
  try {
    return localStorage.getItem(key);
  } catch {
    return null;
  }
}

export function writePreference(key: PreferenceKey, value: string): void {
  try {
    localStorage.setItem(key, value);
  } catch {
    // Storage can be unavailable (private mode); the preference then lasts for this page only.
  }
}

/** Re-renders subscribers whenever an attribute on <html> changes (class, data-motion). */
export function subscribeToRoot(callback: () => void): () => void {
  const observer = new MutationObserver(callback);
  observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-motion'] });
  return () => observer.disconnect();
}

import { useCallback, useSyncExternalStore } from 'react';
import { flushSync } from 'react-dom';
import { readPreference, subscribeToRoot, writePreference } from './storage';

export type Theme = 'light' | 'dark';

const THEME_COLORS: Record<Theme, string> = { light: '#F7F8FB', dark: '#111219' };

function currentTheme(): Theme {
  return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
}

function applyTheme(theme: Theme): void {
  const root = document.documentElement;
  root.classList.toggle('dark', theme === 'dark');
  root.style.colorScheme = theme;
  document.querySelectorAll('meta[name="theme-color"]').forEach((meta) => {
    meta.setAttribute('content', THEME_COLORS[theme]);
  });
}

interface UseThemeResult {
  theme: Theme;
  /** Toggles the theme; pass the click origin to grow the new theme as a circle from it. */
  toggleTheme: (origin?: { x: number; y: number }) => void;
}

export function useTheme(): UseThemeResult {
  const theme = useSyncExternalStore(subscribeToRoot, currentTheme, () => 'light' as Theme);

  const toggleTheme = useCallback((origin?: { x: number; y: number }) => {
    const next: Theme = currentTheme() === 'dark' ? 'light' : 'dark';
    writePreference('ui-theme', next);

    const reduced = document.documentElement.dataset.motion === 'reduced';
    if (!origin || reduced || typeof document.startViewTransition !== 'function') {
      applyTheme(next);
      return;
    }

    const { x, y } = origin;
    const radius = Math.hypot(Math.max(x, innerWidth - x), Math.max(y, innerHeight - y));
    const transition = document.startViewTransition(() => {
      flushSync(() => applyTheme(next));
    });
    transition.ready
      .then(() => {
        document.documentElement.animate(
          { clipPath: [`circle(0px at ${x}px ${y}px)`, `circle(${radius}px at ${x}px ${y}px)`] },
          { duration: 560, easing: 'cubic-bezier(0.22, 1, 0.36, 1)', pseudoElement: '::view-transition-new(root)' },
        );
      })
      .catch(() => applyTheme(next));
  }, []);

  return { theme, toggleTheme };
}

/** Follows the OS theme while the user hasn't picked one explicitly. */
export function syncSystemTheme(): () => void {
  const media = window.matchMedia('(prefers-color-scheme: dark)');
  const onChange = (event: MediaQueryListEvent) => {
    if (!readPreference('ui-theme')) applyTheme(event.matches ? 'dark' : 'light');
  };
  media.addEventListener('change', onChange);
  return () => media.removeEventListener('change', onChange);
}

import { useEffect, useRef } from 'react';

export type HotkeyHandler = (event: KeyboardEvent) => void;

/**
 * Keys are lower-case `event.key` values: 'n', '/', '?', 'mod+k' (Ctrl or ⌘), and two-key
 * sequences such as 'g p' (press g, then p within a second).
 */
export type HotkeyMap = Record<string, HotkeyHandler>;

const SEQUENCE_TIMEOUT = 1000;

/** Typing in a field (or working inside a dialog) must not trigger single-key shortcuts. */
function isTypingTarget(target: EventTarget | null): boolean {
  if (!(target instanceof Element)) return false;
  if (target.closest('input, textarea, select, [contenteditable="true"], [role="combobox"]')) return true;
  return target.closest('[role="dialog"], [role="alertdialog"], [role="menu"], [role="listbox"]') !== null;
}

/** Binds keyboard shortcuts on the window while the component is mounted. */
export default function useHotkeys(bindings: HotkeyMap, enabled = true): void {
  const latest = useRef(bindings);
  useEffect(() => {
    latest.current = bindings;
  });

  useEffect(() => {
    if (!enabled) return;
    let prefix: string | null = null;
    let timer: ReturnType<typeof setTimeout> | undefined;

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.defaultPrevented || event.isComposing || event.altKey) return;
      const mod = event.ctrlKey || event.metaKey;
      const combo = mod ? `mod+${event.key.toLowerCase()}` : event.key.toLowerCase();
      if (!mod && isTypingTarget(event.target)) return;

      const map = latest.current;
      const handler = map[prefix ? `${prefix} ${combo}` : combo] ?? (prefix ? map[combo] : undefined);
      prefix = null;
      clearTimeout(timer);

      if (handler) {
        event.preventDefault();
        handler(event);
        return;
      }
      if (!mod && Object.keys(map).some((key) => key.startsWith(`${combo} `))) {
        prefix = combo;
        timer = setTimeout(() => {
          prefix = null;
        }, SEQUENCE_TIMEOUT);
      }
    };

    window.addEventListener('keydown', onKeyDown);
    return () => {
      window.removeEventListener('keydown', onKeyDown);
      clearTimeout(timer);
    };
  }, [enabled]);
}

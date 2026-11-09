import { useEffect, useRef } from 'react';

/**
 * App-wide commands that any part of the UI can trigger without importing the part that
 * handles them (e.g. the top bar opens the command palette that lives in the app shell).
 */
export type AppEvent = 'open-command-palette' | 'open-shortcuts' | 'new-task';

const PREFIX = 'app:';

export function emitAppEvent(event: AppEvent): void {
  window.dispatchEvent(new Event(PREFIX + event));
}

/** Runs `handler` whenever `event` is emitted; the latest handler is always used. */
export function useAppEvent(event: AppEvent, handler: () => void): void {
  const latest = useRef(handler);
  useEffect(() => {
    latest.current = handler;
  });

  useEffect(() => {
    const listener = () => latest.current();
    window.addEventListener(PREFIX + event, listener);
    return () => window.removeEventListener(PREFIX + event, listener);
  }, [event]);
}

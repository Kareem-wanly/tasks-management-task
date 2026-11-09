import { useSyncExternalStore } from 'react';
import type { z } from 'zod';
import { readPreference, writePreference, type PreferenceKey } from './storage';

export interface JsonPreference<T> {
  get: () => T;
  set: (next: T | ((prev: T) => T)) => void;
  /** Re-renders on changes from this tab and from other tabs. */
  useValue: () => T;
}

/**
 * A typed, validated value in localStorage that components can subscribe to.
 * A missing or corrupt value (old shape, hand-edited) falls back instead of crashing the UI.
 */
export function createJsonPreference<S extends z.ZodType>(
  key: PreferenceKey,
  schema: S,
  fallback: z.infer<S>
): JsonPreference<z.infer<S>> {
  type T = z.infer<S>;
  const listeners = new Set<() => void>();
  // useSyncExternalStore needs the same object back while the stored text is unchanged
  let cachedRaw: string | null | undefined;
  let cachedValue: T = fallback;

  const get = (): T => {
    const raw = readPreference(key);
    if (raw === cachedRaw) return cachedValue;
    cachedRaw = raw;
    cachedValue = fallback;
    if (raw) {
      try {
        const parsed = schema.safeParse(JSON.parse(raw));
        if (parsed.success) cachedValue = parsed.data;
      } catch {
        // Not JSON: keep the fallback
      }
    }
    return cachedValue;
  };

  const set = (next: T | ((prev: T) => T)) => {
    const value = typeof next === 'function' ? (next as (prev: T) => T)(get()) : next;
    writePreference(key, JSON.stringify(value));
    listeners.forEach((listener) => listener());
  };

  const subscribe = (listener: () => void) => {
    listeners.add(listener);
    const onStorage = (event: StorageEvent) => event.key === key && listener();
    window.addEventListener('storage', onStorage);
    return () => {
      listeners.delete(listener);
      window.removeEventListener('storage', onStorage);
    };
  };

  return {
    get,
    set,
    useValue: () => useSyncExternalStore(subscribe, get, () => fallback),
  };
}

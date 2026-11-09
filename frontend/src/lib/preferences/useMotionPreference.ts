import { useCallback, useSyncExternalStore } from 'react';
import { subscribeToRoot, writePreference } from './storage';

function isReduced(): boolean {
  return document.documentElement.dataset.motion === 'reduced';
}

interface UseMotionPreferenceResult {
  reduced: boolean;
  setReduced: (reduced: boolean) => void;
}

/**
 * In-app reduced-motion switch, stored in localStorage. It deliberately ignores the OS setting,
 * because Windows often turns animations off by default.
 */
export function useMotionPreference(): UseMotionPreferenceResult {
  const reduced = useSyncExternalStore(subscribeToRoot, isReduced, () => false);

  const setReduced = useCallback((value: boolean) => {
    writePreference('ui-motion', value ? 'reduced' : 'full');
    if (value) document.documentElement.dataset.motion = 'reduced';
    else delete document.documentElement.dataset.motion;
  }, []);

  return { reduced, setReduced };
}

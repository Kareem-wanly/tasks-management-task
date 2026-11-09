/** True on Apple devices, where shortcuts use ⌘ instead of Ctrl. */
export function isApplePlatform(): boolean {
  return typeof navigator !== 'undefined' && /Mac|iPhone|iPad|iPod/.test(navigator.userAgent);
}

/** Label of the main modifier key for shortcut hints. */
export function modKeyLabel(): string {
  return isApplePlatform() ? '⌘' : 'Ctrl';
}

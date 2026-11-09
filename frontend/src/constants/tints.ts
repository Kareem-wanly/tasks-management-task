/** Hues for identity chips; each has a matching `[data-tint]` rule in styles/theme/utilities.css. */
export const TINTS = ['blue', 'violet', 'pink', 'red', 'orange', 'amber', 'green', 'teal'] as const;

export type Tint = (typeof TINTS)[number];

export function isTint(value: unknown): value is Tint {
  return TINTS.some((tint) => tint === value);
}

/** A stable tint for an id or name, so the same person or project always gets the same colour. */
export function tintFor(seed: number | string): Tint {
  const text = String(seed);
  let hash = 0;
  for (let i = 0; i < text.length; i += 1) hash = (hash * 31 + text.charCodeAt(i)) | 0;
  return TINTS[Math.abs(hash) % TINTS.length] ?? 'blue';
}

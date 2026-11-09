import type { Transition, Variants } from 'motion/react';

/** Signature ease of the design system (`ease-luxe` in Tailwind). */
export const EASE: [number, number, number, number] = [0.22, 1, 0.36, 1];

/** Spring for moving markers (nav pill, tab underline). */
export const SPRING: Transition = { type: 'spring', stiffness: 380, damping: 32, mass: 0.8 };

export const fadeUp: Variants = {
  hidden: { opacity: 0, y: 10 },
  show: { opacity: 1, y: 0, transition: { duration: 0.5, ease: EASE } },
};

/** Props for a single element that fades up on mount, optionally delayed. */
export function fadeUpProps(delay = 0) {
  return {
    initial: { opacity: 0, y: 10 },
    animate: { opacity: 1, y: 0 },
    transition: { duration: 0.5, ease: EASE, delay },
  } as const;
}

import type { ReactNode } from 'react';
import { motion } from 'motion/react';
import { EASE } from '@/lib/motion';

interface PageTransitionProps {
  children: ReactNode;
}

/** Page enter/exit. Wrap with `<AnimatePresence mode="wait">` keyed by the route. */
export default function PageTransition({ children }: PageTransitionProps) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 18, scale: 0.995 }}
      animate={{ opacity: 1, y: 0, scale: 1, transition: { duration: 0.55, ease: EASE } }}
      exit={{ opacity: 0, y: -10, transition: { duration: 0.2, ease: EASE } }}
    >
      {children}
    </motion.div>
  );
}

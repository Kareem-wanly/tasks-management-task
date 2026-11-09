import type { ReactNode } from 'react';
import { motion } from 'motion/react';

interface StaggerProps {
  children: ReactNode;
  className?: string;
  /** Delay between children, in seconds (0.04–0.08). */
  step?: number;
  delay?: number;
}

/** Fades its `StaggerItem` children up one after another. */
export default function Stagger({ children, className, step = 0.06, delay = 0 }: StaggerProps) {
  return (
    <motion.div
      className={className}
      initial="hidden"
      animate="show"
      variants={{ hidden: {}, show: { transition: { staggerChildren: step, delayChildren: delay } } }}
    >
      {children}
    </motion.div>
  );
}

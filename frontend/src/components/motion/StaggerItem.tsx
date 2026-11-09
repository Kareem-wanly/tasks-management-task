import type { ReactNode } from 'react';
import { motion } from 'motion/react';
import { fadeUp } from '@/lib/motion';

interface StaggerItemProps {
  children: ReactNode;
  className?: string;
}

export default function StaggerItem({ children, className }: StaggerItemProps) {
  return (
    <motion.div className={className} variants={fadeUp}>
      {children}
    </motion.div>
  );
}

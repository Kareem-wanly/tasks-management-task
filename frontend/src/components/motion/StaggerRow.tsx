import type { ComponentProps } from 'react';
import { motion } from 'motion/react';
import { EASE } from '@/lib/motion';
import { cn } from '@/lib/utils';

interface StaggerRowProps extends ComponentProps<typeof motion.tr> {
  index: number;
}

/** Table row that slides in after the rows above it (y 6 → 0, 0.03s apart). */
export default function StaggerRow({ index, className, ...props }: StaggerRowProps) {
  return (
    <motion.tr
      data-slot="table-row"
      initial={{ opacity: 0, y: 6 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.45, ease: EASE, delay: Math.min(index, 15) * 0.03 }}
      className={cn(
        'group border-b border-border/60 transition-colors duration-200 last:border-0 hover:bg-muted/40',
        className,
      )}
      {...props}
    />
  );
}

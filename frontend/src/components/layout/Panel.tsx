import type { ReactNode } from 'react';
import { motion } from 'motion/react';
import { EASE } from '@/lib/motion';
import { cn } from '@/lib/utils';

interface PanelProps {
  title?: ReactNode;
  description?: ReactNode;
  actions?: ReactNode;
  /** Drop the body padding, e.g. for a table that runs edge to edge. */
  flush?: boolean;
  className?: string;
  bodyClassName?: string;
  children: ReactNode;
}

/** A titled surface section that fades up once when it scrolls into view. */
export default function Panel({
  title,
  description,
  actions,
  flush = false,
  className,
  bodyClassName,
  children,
}: PanelProps) {
  const hasHeader = Boolean(title || description || actions);

  return (
    <motion.section
      initial={{ opacity: 0, y: 6 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: '-48px' }}
      transition={{ duration: 0.3, ease: EASE }}
      className={cn('surface overflow-hidden', className)}
    >
      {hasHeader && (
        <div className="flex flex-col gap-2 border-b border-border px-5 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
          <div className="min-w-0 space-y-0.5">
            {title && <h2 className="text-sm font-semibold">{title}</h2>}
            {description && <p className="text-[13px] text-muted-foreground">{description}</p>}
          </div>
          {actions && <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>}
        </div>
      )}
      <div className={cn(!flush && 'p-5', bodyClassName)}>{children}</div>
    </motion.section>
  );
}

import type { ReactNode } from 'react';
import { motion } from 'motion/react';
import { fadeUpProps } from '@/lib/motion';

interface PageHeaderProps {
  title: ReactNode;
  /** Small uppercase label above the title (section name, date). */
  eyebrow?: ReactNode;
  description?: ReactNode;
  /** Buttons at the end of the row, e.g. "Add user". */
  actions?: ReactNode;
}

export default function PageHeader({ title, eyebrow, description, actions }: PageHeaderProps) {
  return (
    <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
      <div className="min-w-0 space-y-1.5">
        {eyebrow && (
          <motion.div {...fadeUpProps(0)} className="eyebrow flex items-center gap-2">
                        {eyebrow}
          </motion.div>
        )}
        <motion.h1
          {...fadeUpProps(0.04)}
          className="text-2xl leading-tight font-semibold tracking-tight md:text-[1.75rem]"
        >
          {title}
        </motion.h1>
        {description && (
          <motion.p {...fadeUpProps(0.1)} className="max-w-2xl text-sm leading-6 text-muted-foreground">
            {description}
          </motion.p>
        )}
      </div>
      {actions && (
        <motion.div {...fadeUpProps(0.14)} className="flex shrink-0 flex-wrap items-center gap-2">
          {actions}
        </motion.div>
      )}
    </header>
  );
}

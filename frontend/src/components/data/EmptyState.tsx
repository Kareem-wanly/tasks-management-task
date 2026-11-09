import type { ReactNode } from 'react';
import { InboxIcon, type LucideIcon } from 'lucide-react';
import { motion } from 'motion/react';
import { EASE } from '@/lib/motion';
import { cn } from '@/lib/utils';

interface EmptyStateProps {
  title: ReactNode;
  description?: ReactNode;
  icon?: LucideIcon;
  action?: ReactNode;
  className?: string;
}

export default function EmptyState({ title, description, icon: Icon = InboxIcon, action, className }: EmptyStateProps) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.5, ease: EASE }}
      className={cn('flex flex-col items-center px-6 py-16 text-center', className)}
    >
      <div className="relative mb-5">
        <span aria-hidden="true" className="absolute inset-0 scale-150 rounded-full bg-brand-soft blur-xl" />
        <span className="relative grid size-12 place-items-center rounded-2xl border border-border bg-card text-brand shadow-soft">
          <Icon className="size-5" />
        </span>
      </div>
      <h3 className="font-medium">{title}</h3>
      {description && <p className="mt-1.5 max-w-sm text-sm leading-6 text-muted-foreground">{description}</p>}
      {action && <div className="mt-5 flex flex-wrap justify-center gap-2">{action}</div>}
    </motion.div>
  );
}

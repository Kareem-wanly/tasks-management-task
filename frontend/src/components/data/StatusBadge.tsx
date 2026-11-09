import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import type { Tone } from '@/constants/statuses';

interface StatusBadgeProps {
  tone?: Tone;
  /** Adds a soft ping around the dot, for live states only (e.g. in progress). */
  live?: boolean;
  /** Hide the leading dot. */
  plain?: boolean;
  className?: string;
  children: ReactNode;
}

const TONE_CLASSES: Record<Tone, { badge: string; dot: string }> = {
  success: { badge: 'bg-success-soft text-success', dot: 'bg-success' },
  warning: { badge: 'bg-warning-soft text-warning', dot: 'bg-warning' },
  danger: { badge: 'bg-danger-soft text-danger', dot: 'bg-danger' },
  info: { badge: 'bg-info-soft text-info', dot: 'bg-info' },
  brand: { badge: 'bg-brand-soft text-brand', dot: 'bg-brand' },
  neutral: { badge: 'bg-muted text-muted-foreground', dot: 'bg-muted-foreground/70' },
};

/** Soft background + strong text in the same hue, with a status dot. */
export default function StatusBadge({
  tone = 'neutral',
  live = false,
  plain = false,
  className,
  children,
}: StatusBadgeProps) {
  const classes = TONE_CLASSES[tone];

  return (
    <span
      className={cn(
        'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium whitespace-nowrap',
        classes.badge,
        className,
      )}
    >
      {!plain && (
        <span className="relative flex size-1.5">
          {live && <span className={cn('absolute size-full animate-soft-ping rounded-full opacity-60', classes.dot)} />}
          <span className={cn('relative size-1.5 rounded-full', classes.dot)} />
        </span>
      )}
      {children}
    </span>
  );
}

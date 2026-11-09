import { motion } from 'motion/react';
import { EASE } from '@/lib/motion';
import { cn } from '@/lib/utils';

export interface BreakdownSegment {
  key: string;
  label: string;
  value: number;
  /** Tailwind background class, e.g. 'bg-brand' or 'bg-chart-3'. */
  colorClass: string;
}

interface BreakdownBarProps {
  segments: BreakdownSegment[];
  /** Hide the legend under the bar. */
  compact?: boolean;
}

/** Stacked horizontal bar whose segments grow in one after another, with a legend. */
export default function BreakdownBar({ segments, compact = false }: BreakdownBarProps) {
  const total = segments.reduce((sum, segment) => sum + segment.value, 0);

  return (
    <div className="space-y-3">
      <div className="flex h-1.5 gap-px overflow-hidden rounded-full bg-muted">
        {total > 0 &&
          segments.map((segment, index) => (
            <motion.span
              key={segment.key}
              className={cn('h-full', segment.colorClass)}
              initial={{ width: 0 }}
              whileInView={{ width: `${(segment.value / total) * 100}%` }}
              viewport={{ once: true }}
              transition={{ duration: 0.8, ease: EASE, delay: index * 0.08 }}
            />
          ))}
      </div>
      {!compact && (
        <ul className="grid gap-1.5">
          {segments.map((segment) => (
            <li key={segment.key} className="flex items-center gap-2 text-[13px]">
              <span className={cn('size-2 rounded-full', segment.colorClass)} />
              <span className="flex-1 text-muted-foreground">{segment.label}</span>
              <span className="font-medium tabular-nums">{segment.value}</span>
              <span className="w-10 text-end text-xs text-muted-foreground tabular-nums">
                {total > 0 ? Math.round((segment.value / total) * 100) : 0}%
              </span>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

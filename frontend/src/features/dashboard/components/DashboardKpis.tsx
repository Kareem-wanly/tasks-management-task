import { Link } from 'react-router-dom';
import { cn } from '@/lib/utils';

export interface Kpi {
  key: string;
  label: string;
  value: string | number;
  /** Small line under the number. */
  hint: string;
  /** Opens the matching filtered list. */
  to?: string;
  tone?: 'default' | 'danger' | 'success';
}

interface DashboardKpisProps {
  items: Kpi[];
}

const VALUE_TONES = { default: '', danger: 'text-danger', success: 'text-success' };

/** One bordered strip of key numbers, split by hairlines; each cell links to its list. */
export default function DashboardKpis({ items }: DashboardKpisProps) {
  return (
    <div className="surface grid grid-cols-2 divide-border overflow-hidden sm:grid-cols-3 lg:grid-cols-5 lg:divide-x rtl:lg:divide-x-reverse">
      {items.map((kpi) => {
        const body = (
          <>
            <p className="text-xs font-medium text-muted-foreground">{kpi.label}</p>
            <p className={cn('mt-1 text-2xl font-semibold tracking-tight tabular-nums', VALUE_TONES[kpi.tone ?? 'default'])}>
              {kpi.value}
            </p>
            <p className="mt-0.5 truncate text-xs text-muted-foreground">{kpi.hint}</p>
          </>
        );
        const cell = 'block border-b border-border px-4 py-3.5 lg:border-b-0';
        return kpi.to ? (
          <Link key={kpi.key} to={kpi.to} className={cn(cell, 'transition-colors duration-150 hover:bg-muted/60')}>
            {body}
          </Link>
        ) : (
          <div key={kpi.key} className={cell}>
            {body}
          </div>
        );
      })}
    </div>
  );
}

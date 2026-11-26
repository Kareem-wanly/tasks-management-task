import { cn } from '@/lib/utils';
import type { BitrixSyncStatus } from '@/types/api';

interface SyncCountersProps {
  status: BitrixSyncStatus;
}

interface Counter {
  label: string;
  value: number;
  hint: string;
}

/** Step 5.1: the numbers that should stay at zero. */
export default function SyncCounters({ status }: SyncCountersProps) {
  const counters: Counter[] = [
    { label: 'Failed operations', value: status.failures, hint: 'Background work that failed after all its tries. Listed below.' },
    { label: 'Conflicts (24 h)', value: status.conflicts, hint: 'Our changes Bitrix24 answered with a different value.' },
    { label: 'Unconfirmed (24 h)', value: status.unconfirmed, hint: 'Our changes Bitrix24 never echoed back.' },
  ];

  return (
    <div className="grid gap-3 sm:grid-cols-3">
      {counters.map((counter) => (
        <div key={counter.label} className="surface space-y-1 px-5 py-4">
          <p className="text-xs font-medium text-muted-foreground">{counter.label}</p>
          <p className={cn('text-2xl font-semibold tabular-nums', counter.value > 0 && 'text-destructive')}>{counter.value}</p>
          <p className="text-xs text-muted-foreground">{counter.hint}</p>
        </div>
      ))}
    </div>
  );
}

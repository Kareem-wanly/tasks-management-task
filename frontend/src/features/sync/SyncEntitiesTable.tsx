import DataTable, { type DataTableColumn } from '@/components/data/DataTable';
import { Badge } from '@/components/ui/badge';
import type { BitrixSyncEntity } from '@/types/api';
import { formatDateTime, formatRelativeTime } from '@/utils/format';
import { SYNC_ENTITY_LABELS, SYNC_STATE_LABELS, SYNC_STATE_TONES } from './syncLabels';

interface SyncEntitiesTableProps {
  entities: BitrixSyncEntity[];
  isLoading: boolean;
}

const columns: DataTableColumn<BitrixSyncEntity>[] = [
  {
    key: 'entity',
    header: 'What',
    cell: (row) => <span className="font-medium">{SYNC_ENTITY_LABELS[row.entity] ?? row.entity}</span>,
  },
  {
    key: 'state',
    header: 'State',
    cell: (row) => <Badge variant={SYNC_STATE_TONES[row.state]}>{SYNC_STATE_LABELS[row.state]}</Badge>,
  },
  {
    key: 'progress',
    header: 'Progress',
    cell: (row) => <span className="text-muted-foreground tabular-nums">{progressText(row)}</span>,
  },
  {
    key: 'last',
    header: 'Last successful sync',
    cell: (row) => (
      <span title={formatDateTime(row.last_synced_at)}>{formatRelativeTime(row.last_synced_at, new Date(), 'Never')}</span>
    ),
  },
  {
    key: 'error',
    header: 'Error',
    className: 'max-w-80',
    cell: (row) =>
      row.state === 'failed' && row.error ? (
        <span className="line-clamp-2 text-sm text-destructive" title={row.error}>
          {row.error}
        </span>
      ) : (
        <span className="text-muted-foreground">—</span>
      ),
  },
];

/** Step 5.1: one row per imported entity, in import order. */
export default function SyncEntitiesTable({ entities, isLoading }: SyncEntitiesTableProps) {
  return (
    <DataTable
      columns={columns}
      rows={entities}
      getRowKey={(row) => row.entity}
      isLoading={isLoading}
      skeletonRows={9}
      emptyMessage="Nothing synced yet."
    />
  );
}

function progressText(row: BitrixSyncEntity): string {
  if (row.state === 'never') return '—';
  if (row.total === 0) return `${row.processed}`;
  return `${row.processed} / ${row.total}`;
}

import { RotateCcwIcon, XIcon } from 'lucide-react';
import DataTable, { type DataTableColumn } from '@/components/data/DataTable';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { BitrixSyncFailure } from '@/types/api';
import { formatDateTime, formatRelativeTime } from '@/utils/format';
import { failureLabel } from './syncLabels';

interface SyncFailuresTableProps {
  failures: BitrixSyncFailure[];
  isLoading: boolean;
  /** The failure whose retry or dismiss is on its way. */
  busyId: number | null;
  onRetry: (failure: BitrixSyncFailure) => void;
  onDismiss: (failure: BitrixSyncFailure) => void;
}

/** Step 5.2: the dead letters, with "Retry" and "Dismiss" on each. */
export default function SyncFailuresTable({ failures, isLoading, busyId, onRetry, onDismiss }: SyncFailuresTableProps) {
  const columns: DataTableColumn<BitrixSyncFailure>[] = [
    {
      key: 'what',
      header: 'What',
      cell: (row) => <span className="font-medium">{failureLabel(row)}</span>,
    },
    {
      key: 'error',
      header: 'Error',
      className: 'max-w-96',
      cell: (row) => (
        <span className="line-clamp-2 text-sm text-destructive" title={row.error}>
          {row.error}
        </span>
      ),
    },
    {
      key: 'attempts',
      header: 'Failures',
      className: 'text-end w-20',
      cell: (row) => <span className="tabular-nums">{row.attempts}</span>,
    },
    {
      key: 'last',
      header: 'Last failed',
      cell: (row) => <span title={formatDateTime(row.last_failed_at)}>{formatRelativeTime(row.last_failed_at)}</span>,
    },
    {
      key: 'actions',
      header: <span className="sr-only">Actions</span>,
      className: 'text-end w-56',
      cell: (row) => (
        <div className="flex items-center justify-end gap-2">
          {row.can_retry ? (
            <Button type="button" size="sm" variant="outline" disabled={busyId === row.id} onClick={() => onRetry(row)}>
              <RotateCcwIcon />
              {row.status === 'retrying' ? 'Retry again' : 'Retry'}
            </Button>
          ) : (
            <Badge variant="info">Retry queued</Badge>
          )}
          <Button
            type="button"
            size="sm"
            variant="ghost"
            disabled={busyId === row.id}
            onClick={() => onDismiss(row)}
            aria-label={`Dismiss ${failureLabel(row)}`}
          >
            <XIcon />
          </Button>
        </div>
      ),
    },
  ];

  return (
    <DataTable
      columns={columns}
      rows={failures}
      getRowKey={(row) => row.id}
      isLoading={isLoading}
      skeletonRows={2}
      framed={false}
      emptyMessage="No failed operations."
      emptyDescription="Background work that fails after all its tries shows up here."
    />
  );
}

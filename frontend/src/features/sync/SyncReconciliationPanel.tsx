import Panel from '@/components/layout/Panel';
import DataTable, { type DataTableColumn } from '@/components/data/DataTable';
import { Badge } from '@/components/ui/badge';
import type { BitrixReconciliation, ReconciliationNightState } from '@/types/api';
import { formatDateOnly, formatDateTime, formatRelativeTime } from '@/utils/format';
import { SYNC_ENTITY_LABELS } from './syncLabels';

/** The plan's launch condition: a full week of nights without a difference. */
const CLEAN_WEEK = 7;

const NIGHT_LABELS: Record<ReconciliationNightState, string> = {
  clean: 'clean',
  differences: 'differences',
  none: 'not checked',
};

/** Shape as well as colour: filled = clean, ringed = differences, hollow = not checked. */
const NIGHT_DOT: Record<ReconciliationNightState, string> = {
  clean: 'size-2.5 rounded-full bg-success',
  differences: 'size-2.5 rounded-full bg-destructive ring-2 ring-destructive/40 ring-offset-1 ring-offset-background',
  none: 'size-2.5 rounded-full border border-muted-foreground/50',
};

interface SyncReconciliationPanelProps {
  reconciliation: BitrixReconciliation[];
  isLoading: boolean;
}

const columns: DataTableColumn<BitrixReconciliation>[] = [
  {
    key: 'what',
    header: 'What',
    cell: (row) => <span className="font-medium">{SYNC_ENTITY_LABELS[row.entity] ?? row.entity}</span>,
  },
  {
    key: 'counts',
    header: 'Bitrix24 / here',
    className: 'tabular-nums',
    cell: (row) => (row.latest ? `${row.latest.bitrix_count} / ${row.latest.local_count}` : '—'),
  },
  {
    key: 'difference',
    header: 'Difference',
    className: 'max-w-96',
    cell: (row) => <DifferenceCell row={row} />,
  },
  {
    key: 'checked',
    header: 'Last check',
    cell: (row) =>
      row.latest ? <span title={formatDateTime(row.latest.checked_at)}>{formatRelativeTime(row.latest.checked_at)}</span> : 'Never',
  },
  {
    key: 'trend',
    header: 'Clean nights in a row',
    cell: (row) => <TrendCell row={row} />,
  },
];

/** Step 5.3: after each full import, the Bitrix24 IDs are compared with ours. */
export default function SyncReconciliationPanel({ reconciliation, isLoading }: SyncReconciliationPanelProps) {
  return (
    <Panel
      title="Nightly reconciliation"
      description="After every full import (each night at 03:00, or Full import), the records Bitrix24 lists are compared with ours. Launch needs 7 clean nights in a row."
      flush
    >
      <DataTable
        columns={columns}
        rows={reconciliation}
        getRowKey={(row) => row.entity}
        isLoading={isLoading}
        skeletonRows={3}
        framed={false}
      />
    </Panel>
  );
}

interface CellProps {
  row: BitrixReconciliation;
}

function DifferenceCell({ row }: CellProps) {
  const latest = row.latest;
  if (!latest) return <span className="text-muted-foreground">Not checked yet</span>;
  if (latest.missing_here_count === 0 && latest.extra_here_count === 0) return <Badge variant="success">Matches</Badge>;

  return (
    <div className="space-y-1 text-sm">
      {latest.missing_here_count > 0 && (
        <p className="text-destructive">
          {latest.missing_here_count} in Bitrix24 only: {idList(latest.missing_here, latest.missing_here_count)}
        </p>
      )}
      {latest.extra_here_count > 0 && (
        <p className="text-destructive">
          {latest.extra_here_count} here only: {idList(latest.extra_here, latest.extra_here_count)}
        </p>
      )}
    </div>
  );
}

function TrendCell({ row }: CellProps) {
  const clean = row.nights.filter((night) => night.state === 'clean').length;
  const dirty = row.nights.filter((night) => night.state === 'differences').length;

  return (
    <div className="flex items-center gap-2">
      <span className="tabular-nums">{row.clean_nights}</span>
      <div
        role="img"
        className="flex gap-1"
        aria-label={`Last ${row.nights.length} days: ${clean} clean, ${dirty} with differences, ${row.nights.length - clean - dirty} not checked`}
      >
        {row.nights.map((night) => (
          <span key={night.date} aria-hidden="true" title={`${formatDateOnly(night.date)}: ${NIGHT_LABELS[night.state]}`} className={NIGHT_DOT[night.state]} />
        ))}
      </div>
      {row.clean_nights >= CLEAN_WEEK && <Badge variant="success">Week clean</Badge>}
    </div>
  );
}

/** "IDs 27, 28" or "IDs 1, 2, … (+480 more)". Bitrix IDs, to look them up there. */
function idList(sample: number[], total: number): string {
  const more = total - sample.length;
  return `IDs ${sample.join(', ')}${more > 0 ? ` (+${more} more)` : ''}`;
}

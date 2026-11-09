import type { ReactNode } from 'react';
import { useNavigate } from 'react-router-dom';
import { ArrowDownIcon, ArrowUpIcon } from 'lucide-react';
import UserAvatar from '@/components/data/UserAvatar';
import DataTable, { type DataTableColumn } from '@/components/data/DataTable';
import DeadlineBadge from '@/components/data/DeadlineBadge';
import ProjectMark from '@/components/data/ProjectMark';
import StatusBadge from '@/components/data/StatusBadge';
import { PROJECT_STATUS_LABELS, STATUS_TONES, isLiveStatus } from '@/constants/statuses';
import { formatDateOnly } from '@/utils/format';
import { cn } from '@/lib/utils';
import type { Project } from '@/types/api';
import { isProjectOpen, isProjectOverdue } from '../utils';
import ProjectProgress from './ProjectProgress';
import ProjectRowActions, { type ProjectActionHandlers } from './ProjectRowActions';

interface ProjectsTableProps extends ProjectActionHandlers {
  projects: Project[];
  isLoading: boolean;
  currentSort: string;
  currentDirection: string;
  onSortChange: (sortBy: string) => void;
}

export default function ProjectsTable({
  projects,
  isLoading,
  currentSort,
  currentDirection,
  onSortChange,
  ...actions
}: ProjectsTableProps) {
  const navigate = useNavigate();

  const sortHeader = (column: string, label: ReactNode) => {
    const active = currentSort === column;
    const Arrow = currentDirection === 'asc' ? ArrowUpIcon : ArrowDownIcon;
    return (
      <button
        type="button"
        onClick={() => onSortChange(column)}
        className={cn(
          'inline-flex items-center gap-1 transition-colors duration-300 ease-luxe hover:text-brand',
          active && 'text-foreground',
        )}
      >
        {label}
        {active && <Arrow className="size-3.5 text-brand" />}
      </button>
    );
  };

  const columns: DataTableColumn<Project>[] = [
    {
      key: 'title',
      header: sortHeader('title', 'Project'),
      cell: (p) => (
        <span className="flex items-center gap-2 font-medium">
          <ProjectMark projectId={p.id} size="sm" />
          {p.title}
        </span>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      cell: (p) => (
        <StatusBadge tone={STATUS_TONES[p.status]} live={isLiveStatus(p.status)}>
          {PROJECT_STATUS_LABELS[p.status]}
        </StatusBadge>
      ),
    },
    {
      key: 'start_date',
      header: sortHeader('start_date', 'Start'),
      cell: (p) => formatDateOnly(p.start_date),
      className: 'tabular-nums text-muted-foreground',
    },
    {
      key: 'due_date',
      header: sortHeader('due_date', 'Due'),
      cell: (p) => (
        <div className="flex flex-col items-start gap-1">
          <span className={cn(isProjectOverdue(p) && 'font-medium text-danger')}>{formatDateOnly(p.due_date)}</span>
          <DeadlineBadge dueDate={p.due_date} overdue={isProjectOverdue(p)} done={!isProjectOpen(p)} />
        </div>
      ),
      className: 'tabular-nums text-muted-foreground',
    },
    {
      key: 'progress',
      header: 'Progress',
      className: 'w-40',
      cell: (p) => <ProjectProgress project={p} />,
    },
    {
      key: 'owner',
      header: 'Owner & team',
      cell: (p) => (
        <div className="flex items-center gap-2.5">
          <UserAvatar user={{ id: p.owner?.id, name: p.owner?.name || 'Unassigned' }} size="md" />
          <div className="min-w-0">
            <div className="truncate text-sm">{p.owner?.name || 'Unassigned'}</div>
            {(p.members_count ?? 0) > 0 && (
              <div className="text-xs text-muted-foreground tabular-nums">+{p.members_count} members</div>
            )}
          </div>
        </div>
      ),
    },
    {
      key: 'actions',
      header: <span className="sr-only">Actions</span>,
      className: 'w-40 text-end',
      cell: (p) => (
        <div onClick={(e) => e.stopPropagation()}>
          <ProjectRowActions project={p} {...actions} />
        </div>
      ),
    },
  ];

  return (
    <DataTable
      columns={columns}
      rows={projects}
      getRowKey={(p) => p.id}
      isLoading={isLoading}
      emptyMessage="No projects found"
      emptyDescription="Try adjusting your search query or status filter."
      onRowClick={(p) => navigate(`/projects/${p.id}`)}
    />
  );
}

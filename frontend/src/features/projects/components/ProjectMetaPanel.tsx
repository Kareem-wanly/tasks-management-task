import DeadlineBadge from '@/components/data/DeadlineBadge';
import Panel from '@/components/layout/Panel';
import StatusBadge from '@/components/data/StatusBadge';
import { PROJECT_STATUS_LABELS, PROJECT_STATUS_TONES, isLiveStatus } from '@/constants/statuses';
import { formatDateOnly, formatTimestampDate } from '@/utils/format';
import type { Project } from '@/types/api';
import { isProjectOpen, isProjectOverdue } from '../utils';
import ProjectProgress from './ProjectProgress';

interface ProjectMetaPanelProps {
  project: Project;
  memberCount: number;
}

/** Side panel of key/value facts about the project. */
export default function ProjectMetaPanel({ project, memberCount }: ProjectMetaPanelProps) {
  return (
    <Panel title="Details">
      <dl className="grid grid-cols-[auto_1fr] gap-x-6 gap-y-3 text-sm">
        <dt className="text-muted-foreground">Status</dt>
        <dd>
          <StatusBadge tone={PROJECT_STATUS_TONES[project.status]} live={isLiveStatus(project.status)}>
            {PROJECT_STATUS_LABELS[project.status]}
          </StatusBadge>
        </dd>

        <dt className="text-muted-foreground">Owner</dt>
        <dd className="truncate">{project.owner?.name || 'Unassigned'}</dd>

        <dt className="text-muted-foreground">Start date</dt>
        <dd className="tabular-nums">{formatDateOnly(project.start_date)}</dd>

        <dt className="text-muted-foreground">Due date</dt>
        <dd className="flex flex-wrap items-center gap-2 tabular-nums">
          {formatDateOnly(project.due_date)}
          <DeadlineBadge dueDate={project.due_date} overdue={isProjectOverdue(project)} done={!isProjectOpen(project)} />
        </dd>

        <dt className="text-muted-foreground">Members</dt>
        <dd className="tabular-nums">{memberCount}</dd>

        <dt className="text-muted-foreground">Created</dt>
        <dd className="tabular-nums">{formatTimestampDate(project.created_at)}</dd>

        <dt className="text-muted-foreground">Project ID</dt>
        <dd>
          <code dir="ltr" className="font-mono text-xs">
            #{project.id}
          </code>
        </dd>
      </dl>

      {project.tasks_count !== undefined && (
        <div className="mt-6 border-t border-border/60 pt-5">
          <ProjectProgress project={project} />
        </div>
      )}
    </Panel>
  );
}

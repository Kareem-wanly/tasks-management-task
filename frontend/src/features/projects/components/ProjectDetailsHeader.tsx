import { Link } from 'react-router-dom';
import { ArchiveIcon, ArchiveRestoreIcon, ArrowLeftIcon, EllipsisIcon, PencilIcon, Trash2Icon } from 'lucide-react';
import DeadlineBadge from '@/components/data/DeadlineBadge';
import ExtendDueDateMenu from '@/components/data/ExtendDueDateMenu';
import StatusBadge from '@/components/data/StatusBadge';
import PageHeader from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { PROJECT_STATUS_LABELS, PROJECT_STATUS_TONES, isLiveStatus } from '@/constants/statuses';
import { useAuth } from '@/lib/auth/AuthContext';
import { toDateOnly } from '@/utils/dates';
import { formatTimestampDate } from '@/utils/format';
import type { Project } from '@/types/api';
import { isProjectOpen, isProjectOverdue } from '../utils';
import ProjectAppearancePicker from './ProjectAppearancePicker';

interface ProjectDetailsHeaderProps {
  project: Project;
  onEdit: () => void;
  onArchive: () => void;
  onDelete: () => void;
  onExtend: (dueDate: string) => void;
  extending: boolean;
}

export default function ProjectDetailsHeader({
  project,
  onEdit,
  onArchive,
  onDelete,
  onExtend,
  extending,
}: ProjectDetailsHeaderProps) {
  const { can } = useAuth();
  const isArchived = project.status === 'archived';
  const overdue = isProjectOverdue(project);
  const canUpdate = can('projects.update');
  const canArchive = can('projects.archive');
  const canDelete = can('projects.delete');

  return (
    <PageHeader
      eyebrow={
        <Link to="/projects" className="inline-flex items-center gap-1 transition-colors duration-300 ease-luxe hover:text-brand">
          <ArrowLeftIcon className="size-3.5 rtl:-scale-x-100" />
          Projects
        </Link>
      }
      title={
        <span className="flex flex-wrap items-center gap-x-4 gap-y-2">
          <ProjectAppearancePicker projectId={project.id} />
          {project.title}
          <StatusBadge tone={PROJECT_STATUS_TONES[project.status]} live={isLiveStatus(project.status)}>
            {PROJECT_STATUS_LABELS[project.status]}
          </StatusBadge>
          <DeadlineBadge dueDate={project.due_date} overdue={overdue} done={!isProjectOpen(project)} className="px-2.5 py-1" />
        </span>
      }
      description={
        <>
          Created by <span className="font-medium text-foreground">{project.owner?.name || 'Unknown'}</span> on{' '}
          <span className="tabular-nums">{formatTimestampDate(project.created_at)}</span>
        </>
      }
      actions={
        <>
          {canUpdate && isProjectOpen(project) && (
            <ExtendDueDateMenu
              dueDate={project.due_date}
              minDate={toDateOnly(project.start_date)}
              onExtend={onExtend}
              pending={extending}
              urgent={overdue}
            />
          )}
          {canUpdate && (
            <Button variant="outline" onClick={onEdit}>
              <PencilIcon />
              Edit
            </Button>
          )}
          {(canArchive || canDelete) && (
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" size="icon" aria-label="More project actions">
                  <EllipsisIcon />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                {canArchive && (
                  <DropdownMenuItem onSelect={onArchive}>
                    {isArchived ? <ArchiveRestoreIcon /> : <ArchiveIcon />}
                    {isArchived ? 'Unarchive' : 'Archive'}
                  </DropdownMenuItem>
                )}
                {canArchive && canDelete && <DropdownMenuSeparator />}
                {canDelete && (
                  <DropdownMenuItem variant="destructive" onSelect={onDelete}>
                    <Trash2Icon />
                    Delete
                  </DropdownMenuItem>
                )}
              </DropdownMenuContent>
            </DropdownMenu>
          )}
        </>
      }
    />
  );
}

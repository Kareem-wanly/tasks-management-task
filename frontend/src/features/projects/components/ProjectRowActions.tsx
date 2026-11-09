import type { MouseEvent } from 'react';
import { ArchiveIcon, ArchiveRestoreIcon, PencilIcon, Trash2Icon } from 'lucide-react';
import ExtendDueDateMenu from '@/components/data/ExtendDueDateMenu';
import { Button } from '@/components/ui/button';
import { useAuth } from '@/lib/auth/AuthContext';
import { toDateOnly } from '@/utils/dates';
import type { Project } from '@/types/api';
import { isProjectOpen, isProjectOverdue } from '../utils';

export type ProjectAction = (project: Project, e?: MouseEvent) => void;

export interface ProjectActionHandlers {
  onEdit: ProjectAction;
  onArchive: ProjectAction;
  onDelete: ProjectAction;
  onExtend: (project: Project, dueDate: string) => void;
}

interface ProjectRowActionsProps extends ProjectActionHandlers {
  project: Project;
}

export default function ProjectRowActions({ project, onEdit, onArchive, onDelete, onExtend }: ProjectRowActionsProps) {
  const { can } = useAuth();
  const isArchived = project.status === 'archived';
  const archiveLabel = `${isArchived ? 'Unarchive' : 'Archive'} ${project.title}`;

  return (
    <div className="flex justify-end gap-1">
      {can('projects.update') && isProjectOpen(project) && (
        <ExtendDueDateMenu
          compact
          dueDate={project.due_date}
          minDate={toDateOnly(project.start_date)}
          onExtend={(dueDate) => onExtend(project, dueDate)}
          urgent={isProjectOverdue(project)}
        />
      )}
      {can('projects.update') && (
        <Button
          variant="ghost"
          size="icon-sm"
          onClick={(e) => onEdit(project, e)}
          aria-label={`Edit ${project.title}`}
          title="Edit project"
        >
          <PencilIcon />
        </Button>
      )}
      {can('projects.archive') && (
        <Button
          variant="ghost"
          size="icon-sm"
          onClick={(e) => onArchive(project, e)}
          aria-label={archiveLabel}
          title={isArchived ? 'Unarchive project' : 'Archive project'}
        >
          {isArchived ? <ArchiveRestoreIcon /> : <ArchiveIcon />}
        </Button>
      )}
      {can('projects.delete') && (
        <Button
          variant="ghost"
          size="icon-sm"
          onClick={(e) => onDelete(project, e)}
          aria-label={`Delete ${project.title}`}
          title="Delete project"
        >
          <Trash2Icon className="text-destructive" />
        </Button>
      )}
    </div>
  );
}

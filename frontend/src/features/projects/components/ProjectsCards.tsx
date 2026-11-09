import { useNavigate } from 'react-router-dom';
import { CalendarIcon, FolderKanbanIcon } from 'lucide-react';
import UserAvatar from '@/components/data/UserAvatar';
import DeadlineBadge from '@/components/data/DeadlineBadge';
import ProjectMark from '@/components/data/ProjectMark';
import EmptyState from '@/components/data/EmptyState';
import StatusBadge from '@/components/data/StatusBadge';
import Stagger from '@/components/motion/Stagger';
import StaggerItem from '@/components/motion/StaggerItem';
import { PROJECT_STATUS_LABELS, STATUS_TONES, isLiveStatus } from '@/constants/statuses';
import { formatDateOnly } from '@/utils/format';
import type { Project } from '@/types/api';
import { isProjectOpen, isProjectOverdue } from '../utils';
import ProjectProgress from './ProjectProgress';
import ProjectRowActions, { type ProjectActionHandlers } from './ProjectRowActions';

interface ProjectsCardsProps extends ProjectActionHandlers {
  projects: Project[];
  isLoading: boolean;
}

const GRID = 'grid gap-4 sm:grid-cols-2 xl:grid-cols-3';

export default function ProjectsCards({ projects, isLoading, ...actions }: ProjectsCardsProps) {
  const navigate = useNavigate();

  if (isLoading) {
    return (
      <div className={GRID}>
        {Array.from({ length: 6 }, (_, i) => (
          <div key={i} className="surface h-52 shimmer" />
        ))}
      </div>
    );
  }

  if (projects.length === 0) {
    return (
      <div className="surface">
        <EmptyState
          icon={FolderKanbanIcon}
          title="No projects found"
          description="Try adjusting your search query or status filter."
        />
      </div>
    );
  }

  return (
    <Stagger className={GRID}>
      {projects.map((project) => {
        const open = () => navigate(`/projects/${project.id}`);
        return (
          <StaggerItem key={project.id}>
            <div
              role="link"
              tabIndex={0}
              onClick={open}
              onKeyDown={(e) => e.key === 'Enter' && open()}
              className="group surface flex h-full cursor-pointer flex-col gap-4 p-5 transition-all duration-300 ease-luxe outline-none hover:border-foreground/20 focus-visible:border-brand/60"
            >
              <div className="flex items-start justify-between gap-3">
                <h3 className="flex items-center gap-2 text-[15px] font-semibold tracking-tight transition-colors duration-150 group-hover:text-brand">
                  <ProjectMark projectId={project.id} size="md" />
                  {project.title}
                </h3>
                <StatusBadge tone={STATUS_TONES[project.status]} live={isLiveStatus(project.status)}>
                  {PROJECT_STATUS_LABELS[project.status]}
                </StatusBadge>
              </div>

              <p className="line-clamp-2 text-sm leading-6 text-muted-foreground">
                {project.description || 'No description provided for this project.'}
              </p>

              <ProjectProgress project={project} />

              <div className="flex items-center gap-1.5 text-xs text-muted-foreground tabular-nums">
                <CalendarIcon className="size-3.5" />
                {formatDateOnly(project.start_date)} – {formatDateOnly(project.due_date)}
                <DeadlineBadge
                  dueDate={project.due_date}
                  overdue={isProjectOverdue(project)}
                  done={!isProjectOpen(project)}
                  className="ms-auto px-2 py-0.5"
                />
              </div>

              <div
                className="mt-auto flex items-center justify-between gap-3 border-t border-border/60 pt-3"
                onClick={(e) => e.stopPropagation()}
                onKeyDown={(e) => e.stopPropagation()}
              >
                <div className="flex min-w-0 items-center gap-2">
                  <UserAvatar user={{ id: project.owner?.id, name: project.owner?.name }} size="sm" />
                  <span className="truncate text-sm">{project.owner?.name || 'N/A'}</span>
                </div>
                <ProjectRowActions project={project} {...actions} />
              </div>
            </div>
          </StaggerItem>
        );
      })}
    </Stagger>
  );
}

import { Link } from 'react-router-dom';
import { ArrowRightIcon, FolderKanbanIcon } from 'lucide-react';
import { motion } from 'motion/react';
import DeadlineBadge from '@/components/data/DeadlineBadge';
import EmptyState from '@/components/data/EmptyState';
import ProjectMark from '@/components/data/ProjectMark';
import StatusBadge from '@/components/data/StatusBadge';
import Panel from '@/components/layout/Panel';
import { Button } from '@/components/ui/button';
import { PROJECT_STATUS_LABELS, PROJECT_STATUS_TONES, isLiveStatus } from '@/constants/statuses';
import { EASE } from '@/lib/motion';
import { cn } from '@/lib/utils';
import { daysUntil } from '@/utils/dates';
import { formatDateOnly } from '@/utils/format';
import type { Project } from '@/types/api';

interface ProjectProgressPanelProps {
  /** Already sorted, most urgent first. */
  projects: Project[];
}

const LIMIT = 6;

const isOpen = (p: Project) => p.status !== 'completed' && p.status !== 'archived';

/** Compact project table: status, progress from server-side counts, and the deadline. */
export default function ProjectProgressPanel({ projects }: ProjectProgressPanelProps) {
  return (
    <Panel
      flush
      title="Projects"
      description="Open projects, nearest deadline first"
      actions={
        <Button asChild variant="ghost" size="sm">
          <Link to="/projects">
            View all
            <ArrowRightIcon className="rtl:-scale-x-100" />
          </Link>
        </Button>
      }
    >
      {projects.length === 0 ? (
        <EmptyState icon={FolderKanbanIcon} title="No projects yet" description="Projects you can access will appear here." className="py-10" />
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-[13px]">
            <thead>
              <tr className="border-b border-border text-start text-xs text-muted-foreground">
                <th className="px-4 py-2 text-start font-medium">Project</th>
                <th className="px-2 py-2 text-start font-medium">Status</th>
                <th className="w-40 px-2 py-2 text-start font-medium">Progress</th>
                <th className="px-4 py-2 text-end font-medium">Due</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {projects.slice(0, LIMIT).map((project) => {
                const total = project.tasks_count ?? 0;
                const done = project.completed_tasks_count ?? 0;
                const percent = total > 0 ? Math.round((done / total) * 100) : 0;
                const days = daysUntil(project.due_date);
                const overdue = isOpen(project) && days !== null && days < 0;

                return (
                  <tr key={project.id} className="transition-colors hover:bg-muted/40">
                    <td className="max-w-56 px-4 py-2.5">
                      <Link to={`/projects/${project.id}`} className="flex min-w-0 items-center gap-2 font-medium hover:text-brand">
                        <ProjectMark projectId={project.id} size="sm" />
                        <span className="truncate">{project.title}</span>
                      </Link>
                    </td>
                    <td className="px-2 py-2.5">
                      <StatusBadge tone={PROJECT_STATUS_TONES[project.status]} live={isLiveStatus(project.status)} className="px-2 py-0.5">
                        {PROJECT_STATUS_LABELS[project.status]}
                      </StatusBadge>
                    </td>
                    <td className="px-2 py-2.5">
                      <div className="flex items-center gap-2">
                        <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                          <motion.div
                            className="h-full rounded-full bg-brand"
                            initial={{ width: 0 }}
                            animate={{ width: `${percent}%` }}
                            transition={{ duration: 0.6, ease: EASE }}
                          />
                        </div>
                        <span className="w-14 text-end text-xs text-muted-foreground tabular-nums">
                          {done}/{total}
                        </span>
                      </div>
                    </td>
                    <td className="px-4 py-2.5">
                      <div className="flex items-center justify-end gap-2 whitespace-nowrap">
                        <DeadlineBadge dueDate={project.due_date} overdue={overdue} done={!isOpen(project)} className="px-1.5 py-0 text-[10px]" />
                        <span className={cn('text-xs text-muted-foreground tabular-nums', overdue && 'font-medium text-danger')}>
                          {formatDateOnly(project.due_date)}
                        </span>
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </Panel>
  );
}

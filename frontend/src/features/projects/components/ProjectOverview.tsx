import { Link } from 'react-router-dom';
import { motion } from 'motion/react';
import { ArrowUpRightIcon } from 'lucide-react';
import Markdown from '@/components/data/Markdown';
import Panel from '@/components/layout/Panel';
import { EASE } from '@/lib/motion';
import { cn } from '@/lib/utils';
import type { Project } from '@/types/api';

interface ProjectOverviewProps {
  project: Project;
  /** Server-side count of overdue tasks; undefined while loading. */
  overdueCount: number | undefined;
  /** Links to the tasks page need tasks.view. */
  canViewTasks: boolean;
}

interface Stat {
  key: string;
  label: string;
  value: number | undefined;
  dot: string;
  /** Filters added to `/tasks?project_id=…`; null when no single filter matches. */
  query: string | null;
}

export default function ProjectOverview({ project, overdueCount, canViewTasks }: ProjectOverviewProps) {
  // Counts come from the server so they cover every task, not just one page
  const totalTasks = project.tasks_count ?? 0;
  const completedTasks = project.completed_tasks_count ?? 0;
  const inProgressTasks = project.in_progress_tasks_count ?? 0;
  const remainingTasks = Math.max(totalTasks - completedTasks - inProgressTasks, 0);
  const progressPercent = totalTasks > 0 ? Math.round((completedTasks / totalTasks) * 100) : 0;

  const stats: Stat[] = [
    { key: 'total', label: 'Total tasks', value: totalTasks, dot: 'bg-brand', query: '' },
    { key: 'completed', label: 'Completed', value: completedTasks, dot: 'bg-success', query: '&status=completed' },
    { key: 'in_progress', label: 'In progress', value: inProgressTasks, dot: 'bg-info', query: '&status=in_progress' },
    // The API has no separate To Do / Review counts, so these stay together
    { key: 'remaining', label: 'To do & review', value: remainingTasks, dot: 'bg-muted-foreground/70', query: null },
    { key: 'overdue', label: 'Overdue', value: overdueCount, dot: 'bg-danger', query: '&overdue=true' },
  ];

  return (
    <div className="grid gap-6">
      <Panel title="About this project">
        {project.description ? (
          <Markdown>{project.description}</Markdown>
        ) : (
          <p className="text-sm text-muted-foreground">No description provided for this project.</p>
        )}
      </Panel>

      <Panel title="Task statistics" description="Select a number to see those tasks.">
        <div className="space-y-6">
          <div className="space-y-3">
            <div className="flex items-baseline gap-2">
              <span className="text-3xl font-semibold tracking-tight tabular-nums">{progressPercent}%</span>
              <span className="text-sm text-muted-foreground">complete</span>
            </div>
            <div className="h-1.5 overflow-hidden rounded-full bg-muted">
              <motion.div
                className="h-full rounded-full bg-brand"
                initial={{ width: 0 }}
                animate={{ width: `${progressPercent}%` }}
                transition={{ duration: 0.9, ease: EASE }}
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
            {stats.map((s) => {
              const linked = canViewTasks && s.query !== null && (s.value ?? 0) > 0;
              const body = (
                <>
                  <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <span className={`size-1.5 rounded-full ${s.dot}`} />
                    {s.label}
                    {linked && <ArrowUpRightIcon className="ms-auto size-3.5 opacity-0 transition-opacity group-hover:opacity-100" />}
                  </div>
                  <div className={cn('mt-1 text-xl font-semibold tabular-nums', s.key === 'overdue' && (s.value ?? 0) > 0 && 'text-danger')}>
                    {s.value ?? '—'}
                  </div>
                </>
              );
              const box = 'group block rounded-xl border border-border/60 bg-muted/40 p-4';
              return linked ? (
                <Link
                  key={s.key}
                  to={`/tasks?project_id=${project.id}${s.query}`}
                  className={cn(box, 'transition-colors duration-300 ease-luxe hover:border-brand/40 hover:bg-brand-soft/40')}
                >
                  {body}
                </Link>
              ) : (
                <div key={s.key} className={box}>
                  {body}
                </div>
              );
            })}
          </div>
        </div>
      </Panel>
    </div>
  );
}

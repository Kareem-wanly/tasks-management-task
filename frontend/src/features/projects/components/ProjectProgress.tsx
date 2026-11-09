import { motion } from 'motion/react';
import { EASE } from '@/lib/motion';
import type { Project } from '@/types/api';

interface ProjectProgressProps {
  project: Project;
}

/** Completed / total tasks as a thin brand bar. Counts come from the server. */
export default function ProjectProgress({ project }: ProjectProgressProps) {
  const total = project.tasks_count ?? 0;
  const completed = project.completed_tasks_count ?? 0;
  const percent = total > 0 ? Math.round((completed / total) * 100) : 0;

  return (
    <div className="space-y-1.5">
      <div className="flex items-center justify-between text-xs text-muted-foreground">
        <span>Progress</span>
        <span className="tabular-nums">
          {completed}/{total} · {percent}%
        </span>
      </div>
      <div className="h-1.5 overflow-hidden rounded-full bg-muted">
        <motion.div
          className="h-full rounded-full bg-brand"
          initial={{ width: 0 }}
          animate={{ width: `${percent}%` }}
          transition={{ duration: 0.8, ease: EASE }}
        />
      </div>
    </div>
  );
}

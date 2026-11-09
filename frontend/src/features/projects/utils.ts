import type { Project } from '@/types/api';
import { daysUntil } from '@/utils/dates';

/** Still being worked on: neither completed nor archived. */
export function isProjectOpen(project: Project): boolean {
  return project.status !== 'completed' && project.status !== 'archived';
}

/** Open and past its due date. The API sends no overdue flag for projects, so it's derived here. */
export function isProjectOverdue(project: Project): boolean {
  const days = daysUntil(project.due_date);
  return isProjectOpen(project) && days !== null && days < 0;
}

/** Has tasks and every one of them is completed (server-side counts). */
export function allTasksDone(project: Project): boolean {
  const total = project.tasks_count ?? 0;
  return total > 0 && project.completed_tasks_count === total;
}

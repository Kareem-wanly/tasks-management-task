import { z } from 'zod';
import type { TaskInput } from '@/api/tasksApi';
import { TASK_DEFAULT_PRIORITY, TASK_REOPEN_STATUS } from '@/constants/statuses';
import type { Task } from '@/types/api';
import { toDateOnly } from '@/utils/dates';

// Keys come from /task-statuses and /task-priorities; the server rejects unknown ones
const taskStatus = z.string().min(1, 'Pick a status.');
const taskPriority = z.string().min(1, 'Pick a priority.');

export const taskFormSchema = z.object({
  /** Project id as a string; fixed when the dialog is opened from a project or for an existing task. */
  project_id: z.string().min(1, 'Please select a project for this task.'),
  title: z.string().trim().min(1, 'Task title is required.').max(255, 'Task title is too long.'),
  description: z.string(),
  status: taskStatus,
  priority: taskPriority,
  /** User id as a string; '' = unassigned. */
  assigned_to: z.string(),
  /** `YYYY-MM-DD`; '' = no due date. */
  due_date: z.string(),
});

export type TaskFormValues = z.infer<typeof taskFormSchema>;

/** Renaming a task in place on its page. */
export const titleSchema = taskFormSchema.pick({ title: true });
export type TitleFormValues = z.infer<typeof titleSchema>;

/** Editing a task's description in place on its page. */
export const descriptionSchema = taskFormSchema.pick({ description: true });
export type DescriptionFormValues = z.infer<typeof descriptionSchema>;

/** A comment on a task (StoreCommentRequest / UpdateCommentRequest: required, max 5000). */
export const commentSchema = z.object({
  body: z.string().trim().min(1, 'Write something first.').max(5000, 'Keep comments under 5000 characters.'),
});
export type CommentFormValues = z.infer<typeof commentSchema>;

export const emptyTaskForm: TaskFormValues = {
  project_id: '',
  title: '',
  description: '',
  status: TASK_REOPEN_STATUS,
  priority: TASK_DEFAULT_PRIORITY,
  assigned_to: '',
  due_date: '',
};

/** Form values for editing `task`, or for a new task copied from it (`duplicate`). */
export function taskFormValues(task: Task, duplicate: boolean): TaskFormValues {
  return {
    project_id: task.project ? String(task.project.id) : '',
    title: duplicate ? `${task.title} (copy)` : task.title,
    description: task.description ?? '',
    status: duplicate ? TASK_REOPEN_STATUS : task.status,
    priority: task.priority,
    assigned_to: task.assignee ? String(task.assignee.id) : '',
    due_date: toDateOnly(task.due_date) ?? '',
  };
}

/** Request body. Leaving `assigned_to` out on edit keeps the assignee for users who can't assign. */
export function toTaskInput(values: TaskFormValues, canAssign: boolean, isEdit: boolean): TaskInput {
  const input: TaskInput = {
    title: values.title.trim(),
    description: values.description.trim() || null,
    status: values.status,
    priority: values.priority,
    due_date: values.due_date || null,
  };
  if (canAssign) {
    input.assigned_to = values.assigned_to ? Number(values.assigned_to) : null;
  } else if (!isEdit) {
    input.assigned_to = null;
  }
  return input;
}

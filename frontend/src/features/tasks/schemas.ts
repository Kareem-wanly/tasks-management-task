import { z } from 'zod';
import type { TaskInput } from '@/api/tasksApi';
import { TASK_DEFAULT_PRIORITY, TASK_REOPEN_STATUS } from '@/constants/statuses';
import type { Task } from '@/types/api';
import { toDateOnly } from '@/utils/dates';

// Keys come from /task-statuses and /task-priorities; the server rejects unknown ones
const taskStatus = z.string().min(1, 'Pick a status.');
const taskPriority = z.string().min(1, 'Pick a priority.');

export const taskFormSchema = z.object({
  /**
   * Project id as a string; fixed when the dialog is opened from a project or for an existing task.
   * '' only for an existing task without a project (a Bitrix personal task); new tasks need one.
   */
  project_id: z.string(),
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

/** `project_id` value for "No project" in the create form (select values can't be ''). */
export const NO_PROJECT = 'none';

/** Creating a task: pick a project, or explicitly "No project". */
export const newTaskFormSchema = taskFormSchema.extend({
  project_id: z.string().min(1, 'Select a project, or "No project" for a personal task.'),
});

/** Renaming a task in place on its page. */
export const titleSchema = taskFormSchema.pick({ title: true });
export type TitleFormValues = z.infer<typeof titleSchema>;

/** Editing a task's description in place on its page. */
export const descriptionSchema = taskFormSchema.pick({ description: true });
export type DescriptionFormValues = z.infer<typeof descriptionSchema>;

/** A message in the task chat (StoreTaskChatMessageRequest / UpdateTaskChatMessageRequest: required, max 5000). */
export const chatMessageSchema = z.object({
  text: z.string().trim().min(1, 'Write something first.').max(5000, 'Keep messages under 5000 characters.'),
});
export type ChatMessageFormValues = z.infer<typeof chatMessageSchema>;

export const subtaskSchema = z.object({
  title: z.string().trim().min(1, 'Give the subtask a title.').max(255, 'Keep titles under 255 characters.'),
});
export type SubtaskFormValues = z.infer<typeof subtaskSchema>;

export const checklistItemSchema = z.object({
  title: z.string().trim().min(1, 'Write the item first.').max(2000, 'Keep items under 2000 characters.'),
});
export type ChecklistItemFormValues = z.infer<typeof checklistItemSchema>;

export const taskResultSchema = z.object({
  text: z.string().trim().min(1, 'Describe the result first.').max(10000, 'Keep results under 10000 characters.'),
});
export type TaskResultFormValues = z.infer<typeof taskResultSchema>;

/** Hours and minutes stay strings in the form (inputs); converted when sent. */
export const timeEntrySchema = z
  .object({
    hours: z.string().regex(/^\d{0,3}$/, 'Whole hours only.'),
    minutes: z.string().regex(/^([0-5]?\d)?$/, '0 to 59 minutes.'),
    comment: z.string().trim().max(2000, 'Keep comments under 2000 characters.'),
  })
  .refine((v) => Number(v.hours || 0) * 60 + Number(v.minutes || 0) > 0, { message: 'Enter some time.', path: ['minutes'] });
export type TimeEntryFormValues = z.infer<typeof timeEntrySchema>;

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

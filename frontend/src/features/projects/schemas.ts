import { z } from 'zod';
import type { ProjectInput } from '@/api/projectsApi';
import { EDITABLE_PROJECT_STATUSES } from '@/constants/statuses';
import type { Project } from '@/types/api';
import { toDateOnly } from '@/utils/dates';

type EditableStatus = (typeof EDITABLE_PROJECT_STATUSES)[number];

/** Mirrors StoreProjectRequest / UpdateProjectRequest; `archived` is only set by the archive toggle. */
export const projectFormSchema = z
  .object({
    title: z.string().trim().min(1, 'Project title is required.').max(255, 'Project title is too long.'),
    description: z.string(),
    status: z.custom<EditableStatus>((v) => EDITABLE_PROJECT_STATUSES.some((s) => s === v), 'Pick a status.'),
    /** `YYYY-MM-DD`; '' = no date. */
    start_date: z.string(),
    due_date: z.string(),
  })
  .refine((v) => !v.start_date || !v.due_date || v.due_date >= v.start_date, {
    path: ['due_date'],
    message: 'The due date must be on or after the start date.',
  });

export type ProjectFormValues = z.infer<typeof projectFormSchema>;

export const emptyProjectForm: ProjectFormValues = {
  title: '',
  description: '',
  status: 'active',
  start_date: '',
  due_date: '',
};

export function projectFormValues(project: Project): ProjectFormValues {
  return {
    title: project.title,
    description: project.description ?? '',
    // An archived project keeps its status; the form shows it read-only
    status: project.status === 'archived' ? 'active' : project.status,
    start_date: toDateOnly(project.start_date) ?? '',
    due_date: toDateOnly(project.due_date) ?? '',
  };
}

export function toProjectInput(values: ProjectFormValues, isArchived: boolean): ProjectInput {
  return {
    title: values.title.trim(),
    description: values.description.trim() || null,
    start_date: values.start_date || null,
    due_date: values.due_date || null,
    // Sending a status for an archived project would unarchive it
    ...(isArchived ? {} : { status: values.status }),
  };
}

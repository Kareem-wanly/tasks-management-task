import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import projectsApi from '@/api/projectsApi';
import { queryKeys } from '@/api/queryKeys';
import SelectField from '@/components/form/SelectField';
import TextareaField from '@/components/form/TextareaField';
import TextField from '@/components/form/TextField';
import FormDialog from '@/components/ui/FormDialog';
import { EDITABLE_PROJECT_STATUSES, PROJECT_STATUS_LABELS } from '@/constants/statuses';
import useApiMutation from '@/hooks/useApiMutation';
import { applyServerErrors } from '@/lib/forms';
import { showActionToast } from '@/lib/toast/ToastContext';
import type { Project } from '@/types/api';
import { emptyProjectForm, projectFormSchema, projectFormValues, toProjectInput, type ProjectFormValues } from '../schemas';

interface ProjectFormDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Edits this project; null creates a new one. */
  project?: Project | null;
}

const STATUS_OPTIONS = EDITABLE_PROJECT_STATUSES.map((s) => ({ value: s, label: PROJECT_STATUS_LABELS[s] }));

export default function ProjectFormDialog({ open, onOpenChange, project = null }: ProjectFormDialogProps) {
  const navigate = useNavigate();
  const isEdit = project !== null;
  const isArchived = project?.status === 'archived';

  const form = useForm<ProjectFormValues>({
    resolver: zodResolver(projectFormSchema),
    defaultValues: emptyProjectForm,
  });

  useEffect(() => {
    if (open) form.reset(project ? projectFormValues(project) : emptyProjectForm);
    // eslint-disable-next-line react-hooks/exhaustive-deps -- reset once per open; a background refetch must not wipe what the user typed
  }, [open, form]);

  const save = useApiMutation({
    mutationFn: (values: ProjectFormValues) =>
      project
        ? projectsApi.update(project.id, toProjectInput(values, isArchived))
        : projectsApi.create(toProjectInput(values, false)),
    // A new project gets an "Open" button instead of a plain toast
    successMessage: isEdit ? 'Project updated successfully.' : undefined,
    errorMessage: isEdit ? 'Failed to update project.' : 'Failed to create project.',
    invalidate: [queryKeys.projects.all],
    onSuccess: (saved) => {
      if (!isEdit) {
        showActionToast(`Project "${saved.title}" created.`, {
          label: 'Open',
          onClick: () => navigate(`/projects/${saved.id}`),
        });
      }
      onOpenChange(false);
    },
    onError: (err) => applyServerErrors(err, form.setError),
  });

  return (
    <FormDialog
      open={open}
      onOpenChange={onOpenChange}
      isDirty={form.formState.isDirty}
      title={isEdit ? 'Edit project' : 'Create new project'}
      description={isEdit ? 'Update the project details.' : 'Set up a new project for your team.'}
      onSubmit={form.handleSubmit((values) => save.mutate(values))}
      submitLabel={isEdit ? 'Save changes' : 'Create project'}
      submitting={save.isPending}
      className="sm:max-w-lg"
    >
      <TextField control={form.control} name="title" label="Project title *" placeholder="e.g. Website Redesign" autoFocus />
      <TextareaField
        control={form.control}
        name="description"
        label="Description"
        rows={4}
        placeholder="Goals, scope and links… (Markdown supported)"
      />
      <SelectField
        control={form.control}
        name="status"
        label="Status"
        options={STATUS_OPTIONS}
        disabled={isArchived}
        description={isArchived ? 'Archived. Unarchive the project to change its status.' : undefined}
      />
      <div className="grid gap-4 sm:grid-cols-2">
        <TextField control={form.control} name="start_date" label="Start date" type="date" />
        <TextField control={form.control} name="due_date" label="Due date" type="date" />
      </div>
    </FormDialog>
  );
}

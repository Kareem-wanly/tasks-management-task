import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useForm, useWatch } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import tasksApi from '@/api/tasksApi';
import { queryKeys } from '@/api/queryKeys';
import FormDialog from '@/components/ui/FormDialog';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import useApiMutation from '@/hooks/useApiMutation';
import useProject from '@/hooks/queries/useProject';
import useProjectOptions from '@/hooks/queries/useProjectOptions';
import { useAuth } from '@/lib/auth/AuthContext';
import { applyServerErrors } from '@/lib/forms';
import { showActionToast } from '@/lib/toast/ToastContext';
import type { Task, User } from '@/types/api';
import { emptyTaskForm, taskFormSchema, taskFormValues, toTaskInput, type TaskFormValues } from '../schemas';
import TaskFormFields from './TaskFormFields';

interface TaskFormDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Creates the task in this project and hides the project picker. */
  projectId?: number | null;
  /** Edits this task. */
  task?: Task | null;
  /** Prefills a new task from this one (Duplicate). */
  template?: Task | null;
}

export default function TaskFormDialog({ open, onOpenChange, projectId = null, task = null, template = null }: TaskFormDialogProps) {
  const navigate = useNavigate();
  const { can } = useAuth();
  const canAssign = can('tasks.assign');
  const isEdit = task !== null;
  const source = task ?? template;
  const fixedProjectId = projectId ?? source?.project?.id ?? null;
  const [createAnother, setCreateAnother] = useState(false);

  const form = useForm<TaskFormValues>({
    resolver: zodResolver(taskFormSchema),
    defaultValues: emptyTaskForm,
  });

  useEffect(() => {
    if (!open) return;
    const values = source ? taskFormValues(source, task === null) : emptyTaskForm;
    form.reset({ ...values, project_id: fixedProjectId ? String(fixedProjectId) : values.project_id });
    // eslint-disable-next-line react-hooks/exhaustive-deps -- reset once per open; a background refetch of the task must not wipe what the user typed
  }, [open, form]);

  const selectedProjectId = useWatch({ control: form.control, name: 'project_id' });
  const project = useProject(open ? selectedProjectId : null);
  const projectOptions = useProjectOptions({ enabled: open && fixedProjectId === null });

  const members: User[] = [];
  for (const member of [project.data?.owner, ...(project.data?.members ?? [])]) {
    if (member && !members.some((m) => m.id === member.id)) members.push(member);
  }

  const save = useApiMutation({
    mutationFn: (values: TaskFormValues) =>
      task
        ? tasksApi.update(task.id, toTaskInput(values, canAssign, true))
        : tasksApi.create(values.project_id, toTaskInput(values, canAssign, false)),
    // A new task gets an "Open" button instead of a plain toast
    successMessage: isEdit ? 'Task updated successfully.' : undefined,
    errorMessage: isEdit ? 'Failed to update task.' : 'Failed to create task.',
    invalidate: [queryKeys.tasks.all, queryKeys.projects.all],
    onSuccess: (saved) => {
      if (!isEdit) {
        showActionToast(`Task "${saved.title}" created.`, { label: 'Open', onClick: () => navigate(`/tasks/${saved.id}`) });
      }
      if (isEdit || !createAnother) {
        onOpenChange(false);
        return;
      }
      // Keep project, assignee, priority and date for the next task in a batch
      form.reset({ ...form.getValues(), title: '', description: '' });
      form.setFocus('title');
    },
    onError: (err) => applyServerErrors(err, form.setError),
  });

  return (
    <FormDialog
      open={open}
      onOpenChange={onOpenChange}
      isDirty={form.formState.isDirty}
      title={isEdit ? 'Edit task' : template ? 'Duplicate task' : 'New task'}
      description={isEdit ? 'Update the task details.' : 'Add a task to a project.'}
      onSubmit={form.handleSubmit((values) => save.mutate(values))}
      submitLabel={isEdit ? 'Save changes' : 'Create task'}
      submitting={save.isPending}
      className="sm:max-w-lg"
    >
      <TaskFormFields
        control={form.control}
        showProjectSelect={fixedProjectId === null}
        projects={projectOptions.data ?? []}
        loadingProjects={projectOptions.isPending && fixedProjectId === null}
        members={members}
        canAssign={canAssign}
        assigneeDisabled={!selectedProjectId}
        projectDueDate={project.data?.due_date}
        disabled={save.isPending}
      />

      {!isEdit && (
        <div className="flex items-center gap-2">
          <Checkbox
            id="create-another"
            checked={createAnother}
            onCheckedChange={(checked) => setCreateAnother(checked === true)}
          />
          <Label htmlFor="create-another" className="text-sm font-normal text-muted-foreground">
            Create another after this one
          </Label>
        </div>
      )}
    </FormDialog>
  );
}

import { Controller, type Control } from 'react-hook-form';
import { LockIcon } from 'lucide-react';
import SelectField from '@/components/form/SelectField';
import TextareaField from '@/components/form/TextareaField';
import TextField from '@/components/form/TextField';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import useTaskLookups from '@/hooks/queries/useTaskLookups';
import type { ProjectOption, User } from '@/types/api';
import type { TaskFormValues } from '../schemas';
import DueDateField from './DueDateField';

interface TaskFormFieldsProps {
  control: Control<TaskFormValues>;
  showProjectSelect: boolean;
  projects: ProjectOption[];
  loadingProjects: boolean;
  /** Owner and members of the selected project: the only valid assignees. */
  members: User[];
  canAssign: boolean;
  /** No project chosen yet, so there is nobody to assign. */
  assigneeDisabled: boolean;
  projectDueDate?: string | null;
  disabled: boolean;
}

// Select items can't use '' as a value
const UNASSIGNED = 'none';

export default function TaskFormFields({
  control,
  showProjectSelect,
  projects,
  loadingProjects,
  members,
  canAssign,
  assigneeDisabled,
  projectDueDate,
  disabled,
}: TaskFormFieldsProps) {
  const { statusOptions, priorityOptions } = useTaskLookups();

  return (
    <FieldGroup className="gap-4">
      {showProjectSelect && (
        <SelectField
          control={control}
          name="project_id"
          label="Project *"
          options={projects.map((p) => ({ value: String(p.id), label: p.title }))}
          placeholder={loadingProjects ? 'Loading projects...' : 'Select a project'}
          disabled={disabled || loadingProjects}
        />
      )}

      <TextField
        control={control}
        name="title"
        label="Task title *"
        placeholder="e.g. Implement authentication endpoints"
        disabled={disabled}
        autoFocus
      />

      <TextareaField
        control={control}
        name="description"
        label="Description"
        rows={3}
        placeholder="Provide context and requirements..."
        disabled={disabled}
      />

      <div className="grid gap-4 sm:grid-cols-2">
        <SelectField control={control} name="status" label="Status" options={statusOptions} disabled={disabled} />
        <SelectField control={control} name="priority" label="Priority" options={priorityOptions} disabled={disabled} />
      </div>

      <Controller
        control={control}
        name="assigned_to"
        render={({ field, fieldState }) => (
          <Field data-invalid={fieldState.invalid}>
            <FieldLabel htmlFor="assigned_to">Assignee</FieldLabel>
            <Select
              value={field.value || UNASSIGNED}
              onValueChange={(v) => field.onChange(v === UNASSIGNED ? '' : v)}
              disabled={disabled || !canAssign || assigneeDisabled}
            >
              <SelectTrigger id="assigned_to" className="w-full" aria-invalid={fieldState.invalid} onBlur={field.onBlur}>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={UNASSIGNED}>Unassigned</SelectItem>
                {members.map((m) => (
                  <SelectItem key={m.id} value={String(m.id)}>
                    {m.name} ({m.email})
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {!canAssign && (
              <FieldDescription className="flex items-center gap-1.5 text-xs">
                <LockIcon className="size-3" />
                Requires <code dir="ltr" className="font-mono text-xs">tasks.assign</code>
              </FieldDescription>
            )}
            <FieldError errors={[fieldState.error]} />
          </Field>
        )}
      />

      <DueDateField control={control} projectDueDate={projectDueDate} disabled={disabled} />
    </FieldGroup>
  );
}

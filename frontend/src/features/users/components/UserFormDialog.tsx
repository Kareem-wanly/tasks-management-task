import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import usersApi from '@/api/usersApi';
import { queryKeys } from '@/api/queryKeys';
import FormDialog from '@/components/ui/FormDialog';
import { FieldGroup } from '@/components/ui/field';
import SelectField from '@/components/form/SelectField';
import TextField from '@/components/form/TextField';
import useApiMutation from '@/hooks/useApiMutation';
import { useAuth } from '@/lib/auth/AuthContext';
import { applyServerErrors } from '@/lib/forms';
import type { Role, UserListItem } from '@/types/api';
import { emptyUserForm, userFormSchema, type UserFormValues } from '../schemas';

interface UserFormDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** null = create a new user. */
  user: UserListItem | null;
  roles: Role[];
}

export default function UserFormDialog({ open, onOpenChange, user, roles }: UserFormDialogProps) {
  const { can } = useAuth();
  const isEdit = user !== null;

  const form = useForm<UserFormValues>({
    resolver: zodResolver(userFormSchema(isEdit ? 'edit' : 'create')),
    defaultValues: emptyUserForm,
  });

  // Load the selected user (or blank values) every time the dialog opens
  useEffect(() => {
    if (open) form.reset(user ? { ...emptyUserForm, name: user.name, email: user.email } : emptyUserForm);
  }, [open, user, form]);

  const save = useApiMutation({
    mutationFn: (values: UserFormValues) =>
      user
        ? usersApi.update(user.id, { name: values.name, email: values.email })
        : usersApi.create({
            name: values.name,
            email: values.email,
            password: values.password,
            role_id: values.role_id ? Number(values.role_id) : undefined,
          }),
    successMessage: isEdit ? 'User updated successfully.' : 'User created successfully.',
    errorMessage: 'Failed to save user details.',
    invalidate: [queryKeys.users.all],
    onSuccess: () => onOpenChange(false),
    onError: (err) => applyServerErrors(err, form.setError),
  });

  const roleOptions = roles.map((role) => ({ value: String(role.id), label: role.display_name || role.name }));

  return (
    <FormDialog
      open={open}
      onOpenChange={onOpenChange}
      isDirty={form.formState.isDirty}
      title={isEdit ? 'Edit User' : 'Create New User'}
      description={isEdit ? 'Update the name and email. Roles are managed separately.' : undefined}
      submitLabel={isEdit ? 'Update User' : 'Create User'}
      submitting={save.isPending}
      onSubmit={form.handleSubmit((values) => save.mutate(values))}
    >
      <FieldGroup>
        <TextField control={form.control} name="name" label="Full name" placeholder="e.g. John Doe" autoComplete="name" />
        <TextField control={form.control} name="email" label="Email address" type="email" placeholder="e.g. john@example.com" />

        {!isEdit && (
          <>
            <SelectField
              control={form.control}
              name="role_id"
              label="System role"
              options={roleOptions}
              placeholder="Member (default)"
              disabled={!can('roles.assign')}
              description={can('roles.assign') ? undefined : 'Requires the roles.assign permission.'}
            />
            <TextField control={form.control} name="password" label="Password" type="password" autoComplete="new-password" />
            <TextField
              control={form.control}
              name="password_confirmation"
              label="Confirm password"
              type="password"
              autoComplete="new-password"
            />
          </>
        )}
      </FieldGroup>
    </FormDialog>
  );
}

import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import authApi from '@/api/authApi';
import TextField from '@/components/form/TextField';
import Panel from '@/components/layout/Panel';
import { Button } from '@/components/ui/button';
import useApiMutation from '@/hooks/useApiMutation';
import { applyServerErrors } from '@/lib/forms';
import { emptyPasswordForm, passwordSchema, type PasswordFormValues } from '../schemas';

/** Change the signed-in user's password (PUT /me/password; the server checks the current one). */
export default function ChangePasswordForm() {
  const form = useForm<PasswordFormValues>({
    resolver: zodResolver(passwordSchema),
    defaultValues: emptyPasswordForm,
  });

  const save = useApiMutation({
    mutationFn: (values: PasswordFormValues) => authApi.updatePassword(values),
    successMessage: 'Password changed.',
    errorMessage: 'Failed to change your password.',
    onSuccess: () => form.reset(emptyPasswordForm),
    onError: (err) => applyServerErrors(err, form.setError),
  });

  return (
    <Panel title="Password" description="Use at least 8 characters. You stay signed in on this device.">
      <form onSubmit={form.handleSubmit((values) => save.mutate(values))} noValidate className="grid gap-4">
        <TextField
          control={form.control}
          name="current_password"
          label="Current password"
          type="password"
          autoComplete="current-password"
        />
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField control={form.control} name="password" label="New password" type="password" autoComplete="new-password" />
          <TextField
            control={form.control}
            name="password_confirmation"
            label="Confirm new password"
            type="password"
            autoComplete="new-password"
          />
        </div>
        <div className="flex justify-end">
          <Button type="submit" variant="brand" disabled={!form.formState.isDirty || save.isPending}>
            {save.isPending ? 'Changing...' : 'Change password'}
          </Button>
        </div>
      </form>
    </Panel>
  );
}

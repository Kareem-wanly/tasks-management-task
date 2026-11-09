import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import authApi from '@/api/authApi';
import TextField from '@/components/form/TextField';
import Panel from '@/components/layout/Panel';
import { Button } from '@/components/ui/button';
import useApiMutation from '@/hooks/useApiMutation';
import { useAuth } from '@/lib/auth/AuthContext';
import { applyServerErrors } from '@/lib/forms';
import { profileSchema, type ProfileFormValues } from '../schemas';

/** Name and email of the signed-in user (PUT /me). */
export default function ProfileDetailsForm() {
  const { user, refreshUser } = useAuth();
  const form = useForm<ProfileFormValues>({
    resolver: zodResolver(profileSchema),
    defaultValues: { name: user?.name ?? '', email: user?.email ?? '' },
  });

  // Follow the saved user, e.g. after the refresh below
  useEffect(() => {
    if (user) form.reset({ name: user.name, email: user.email });
  }, [user, form]);

  const save = useApiMutation({
    mutationFn: (values: ProfileFormValues) => authApi.updateProfile(values),
    successMessage: 'Profile updated.',
    errorMessage: 'Failed to update your profile.',
    onSuccess: () => void refreshUser(),
    onError: (err) => applyServerErrors(err, form.setError),
  });

  return (
    <Panel title="Profile" description="How you appear to your team.">
      <form onSubmit={form.handleSubmit((values) => save.mutate(values))} noValidate className="grid gap-4">
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField control={form.control} name="name" label="Full name" autoComplete="name" />
          <TextField control={form.control} name="email" label="Email address" type="email" autoComplete="email" />
        </div>
        <div className="flex justify-end gap-2">
          <Button
            type="button"
            variant="ghost"
            disabled={!form.formState.isDirty || save.isPending}
            onClick={() => form.reset()}
          >
            Discard
          </Button>
          <Button type="submit" variant="brand" disabled={!form.formState.isDirty || save.isPending}>
            {save.isPending ? 'Saving...' : 'Save profile'}
          </Button>
        </div>
      </form>
    </Panel>
  );
}

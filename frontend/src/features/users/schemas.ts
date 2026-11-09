import { z } from 'zod';

/**
 * One schema for both modes so the form has a single values type.
 * Creating requires a password; editing only sends name and email (UpdateUserRequest).
 */
export const userFormSchema = (mode: 'create' | 'edit') =>
  z
    .object({
      name: z.string().trim().min(1, 'Name is required.').max(255, 'Name is too long.'),
      email: z.email('Enter a valid email address.'),
      password: z.string(),
      password_confirmation: z.string(),
      /** Role id as a string ('' = default Member role); only used when creating. */
      role_id: z.string(),
    })
    .superRefine((values, ctx) => {
      if (mode === 'edit') return;
      if (values.password.length < 8) {
        ctx.addIssue({ code: 'custom', path: ['password'], message: 'Password must be at least 8 characters.' });
      }
      if (values.password !== values.password_confirmation) {
        ctx.addIssue({ code: 'custom', path: ['password_confirmation'], message: 'Passwords do not match.' });
      }
    });

export type UserFormValues = z.infer<ReturnType<typeof userFormSchema>>;

export const emptyUserForm: UserFormValues = {
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role_id: '',
};

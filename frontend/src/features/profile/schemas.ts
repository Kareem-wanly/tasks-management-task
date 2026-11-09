import { z } from 'zod';

export const profileSchema = z.object({
  name: z.string().trim().min(1, 'Name is required.').max(255, 'Name is too long.'),
  email: z.email('Enter a valid email address.').max(255, 'Email is too long.'),
});

export type ProfileFormValues = z.infer<typeof profileSchema>;

export const passwordSchema = z
  .object({
    current_password: z.string().min(1, 'Enter your current password.'),
    password: z.string().min(8, 'Use at least 8 characters.'),
    password_confirmation: z.string(),
  })
  .refine((v) => v.password === v.password_confirmation, {
    path: ['password_confirmation'],
    message: 'Passwords do not match.',
  })
  .refine((v) => v.password !== v.current_password, {
    path: ['password'],
    message: 'Choose a password different from the current one.',
  });

export type PasswordFormValues = z.infer<typeof passwordSchema>;

export const emptyPasswordForm: PasswordFormValues = {
  current_password: '',
  password: '',
  password_confirmation: '',
};

import type { FieldValues, Path, UseFormSetError } from 'react-hook-form';
import { ApiError } from './apiClient';

/**
 * Copies Laravel 422 validation errors onto react-hook-form fields.
 * Returns `true` when it handled the error, so the caller can skip the generic toast:
 *
 *   onError: (err) => applyServerErrors(err, form.setError)
 */
export function applyServerErrors<T extends FieldValues>(err: unknown, setError: UseFormSetError<T>): boolean {
  if (!(err instanceof ApiError) || err.status !== 422) return false;

  const entries = Object.entries(err.errors);
  if (entries.length === 0) return false;

  entries.forEach(([field, messages], index) => {
    // Laravel field names match the form's keys (name, email, role_id, ...)
    setError(field as Path<T>, { type: 'server', message: messages[0] }, { shouldFocus: index === 0 });
  });
  return true;
}

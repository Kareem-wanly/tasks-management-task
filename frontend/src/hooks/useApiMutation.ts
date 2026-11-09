import { useMutation, useQueryClient, type QueryKey, type UseMutationResult } from '@tanstack/react-query';
import { errorMessage as toMessage } from '@/lib/apiClient';
import { showToast } from '@/lib/toast/ToastContext';

interface ApiMutationOptions<TData, TVariables> {
  mutationFn: (variables: TVariables) => Promise<TData>;
  /** Toast shown on success; a function receives the result and the variables. */
  successMessage?: string | ((data: TData, variables: TVariables) => string);
  /** Fallback toast text when the server sends no message. */
  errorMessage: string;
  /** Queries to refresh after success, e.g. `[queryKeys.users.all]`. */
  invalidate?: QueryKey[];
  onSuccess?: (data: TData, variables: TVariables) => void;
  /** Return `true` when the error was handled (e.g. 422 mapped onto form fields) to skip the toast. */
  onError?: (error: unknown, variables: TVariables) => boolean | void;
}

/**
 * `useMutation` with the app's defaults: success/error toasts and query invalidation.
 *
 *   const remove = useApiMutation({
 *     mutationFn: (id: number) => usersApi.delete(id),
 *     successMessage: 'User deleted.',
 *     errorMessage: 'Failed to delete user.',
 *     invalidate: [queryKeys.users.all],
 *   });
 *   remove.mutate(user.id);
 */
export default function useApiMutation<TData = unknown, TVariables = void>({
  mutationFn,
  successMessage,
  errorMessage,
  invalidate = [],
  onSuccess,
  onError,
}: ApiMutationOptions<TData, TVariables>): UseMutationResult<TData, unknown, TVariables> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn,
    onSuccess: async (data, variables) => {
      await Promise.all(invalidate.map((queryKey) => queryClient.invalidateQueries({ queryKey })));
      if (successMessage) {
        showToast(typeof successMessage === 'function' ? successMessage(data, variables) : successMessage, 'success');
      }
      onSuccess?.(data, variables);
    },
    onError: (error, variables) => {
      if (onError?.(error, variables) === true) return;
      showToast(toMessage(error, errorMessage), 'error');
    },
  });
}

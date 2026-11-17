import { useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import resultsApi from '@/api/resultsApi';
import useApiMutation from '@/hooks/useApiMutation';
import type { TaskResult, TaskResults } from '@/types/api';

type ResultChange =
  { kind: 'add'; text: string } | { kind: 'update'; target: TaskResult; text: string } | { kind: 'remove'; target: TaskResult };

export interface TaskResultsControl {
  query: UseQueryResult<TaskResults>;
  add: (text: string, onDone?: () => void) => void;
  update: (target: TaskResult, text: string, onDone?: () => void) => void;
  remove: (target: TaskResult) => void;
  isPending: boolean;
}

const MESSAGES: Record<ResultChange['kind'], string> = {
  add: 'Result added.',
  update: 'Result updated.',
  remove: 'Result deleted.',
};

/** A task's official results (Bitrix24 step 4.6), read when the task opens. */
export default function useTaskResults(taskId: number): TaskResultsControl {
  const queryClient = useQueryClient();
  const key = queryKeys.tasks.results(taskId);

  const query = useQuery({
    queryKey: key,
    queryFn: ({ signal }) => resultsApi.get(taskId, { signal }),
  });

  const mutation = useApiMutation({
    mutationFn: (change: ResultChange & { onDone?: () => void }) => {
      switch (change.kind) {
        case 'add':
          return resultsApi.add(taskId, change.text);
        case 'update':
          return resultsApi.update(change.target.id, change.text);
        case 'remove':
          return resultsApi.remove(change.target.id);
      }
    },
    successMessage: (_: TaskResults, change) => MESSAGES[change.kind],
    errorMessage: 'Failed to save the result.',
    onSuccess: (results, change) => {
      queryClient.setQueryData<TaskResults>(key, results);
      change.onDone?.();
    },
  });

  return {
    query,
    add: (text, onDone) => mutation.mutate({ kind: 'add', text, onDone }),
    update: (target, text, onDone) => mutation.mutate({ kind: 'update', target, text, onDone }),
    remove: (target) => mutation.mutate({ kind: 'remove', target }),
    isPending: mutation.isPending,
  };
}

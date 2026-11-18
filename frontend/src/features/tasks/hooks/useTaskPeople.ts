import { useQueryClient } from '@tanstack/react-query';
import tasksApi, { type TaskMembersInput } from '@/api/tasksApi';
import { queryKeys } from '@/api/queryKeys';
import useApiMutation from '@/hooks/useApiMutation';
import type { Task } from '@/types/api';

type PeopleChange =
  | { kind: 'members'; members: TaskMembersInput; message: string }
  | { kind: 'watch'; watching: boolean; message: string }
  | { kind: 'delegate'; userId: number; message: string };

export interface TaskPeopleControl {
  setMembers: (members: TaskMembersInput, message: string) => void;
  watch: (watching: boolean) => void;
  delegate: (userId: number, name: string) => void;
  isPending: boolean;
}

/**
 * Accomplices, auditors, watching and delegating (Bitrix24 step 4.1). Each
 * answer is the task as Bitrix now has it, so it replaces the cached task.
 */
export default function useTaskPeople(task: Task): TaskPeopleControl {
  const queryClient = useQueryClient();

  const mutation = useApiMutation({
    mutationFn: (change: PeopleChange) => {
      switch (change.kind) {
        case 'members':
          return tasksApi.setMembers(task.id, change.members);
        case 'watch':
          return tasksApi.watch(task.id, change.watching);
        case 'delegate':
          return tasksApi.delegate(task.id, change.userId);
      }
    },
    successMessage: (_: Task, change: PeopleChange) => change.message,
    errorMessage: 'Failed to update the people on this task.',
    invalidate: [queryKeys.tasks.lists],
    onSuccess: (updated: Task) => {
      queryClient.setQueryData<Task>(queryKeys.tasks.detail(task.id), (prev) => (prev ? { ...prev, ...updated } : updated));
    },
  });

  return {
    setMembers: (members, message) => mutation.mutate({ kind: 'members', members, message }),
    watch: (watching) =>
      mutation.mutate({
        kind: 'watch',
        watching,
        message: watching ? 'You are now following this task.' : 'You stopped following this task.',
      }),
    delegate: (userId, name) => mutation.mutate({ kind: 'delegate', userId, message: `Task delegated to ${name}.` }),
    isPending: mutation.isPending,
  };
}

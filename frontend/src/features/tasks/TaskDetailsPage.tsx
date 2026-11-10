import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import { ArrowLeftIcon } from 'lucide-react';
import tasksApi from '@/api/tasksApi';
import { queryKeys } from '@/api/queryKeys';
import { Button } from '@/components/ui/button';
import ConfirmModal from '@/components/ui/ConfirmModal';
import ErrorState from '@/components/ui/ErrorState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import { TASK_DONE_STATUS, TASK_REOPEN_STATUS } from '@/constants/statuses';
import useApiMutation from '@/hooks/useApiMutation';
import useHotkeys from '@/hooks/useHotkeys';
import useProject from '@/hooks/queries/useProject';
import useTask from '@/hooks/queries/useTask';
import useTaskLookups from '@/hooks/queries/useTaskLookups';
import { useAuth } from '@/lib/auth/AuthContext';
import type { Comment, Task, User } from '@/types/api';
import TaskComments from './components/TaskComments';
import TaskDescription from './components/TaskDescription';
import TaskDetailsHeader from './components/TaskDetailsHeader';
import TaskFormDialog from './components/TaskFormDialog';
import TaskSidebar from './components/TaskSidebar';
import useTaskQuickUpdate from './hooks/useTaskQuickUpdate';
import useTaskSiblings from './hooks/useTaskSiblings';
import useTaskStatus from './hooks/useTaskStatus';

type TaskDialog = 'edit' | 'duplicate' | 'delete' | null;

export default function TaskDetailsPage() {
  const { id = '' } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { can } = useAuth();
  const canEdit = can('tasks.update');

  const taskQuery = useTask(id);
  const task = taskQuery.data;
  const project = useProject(task?.project?.id);
  const siblings = useTaskSiblings(task?.project?.id, task?.id ?? 0);
  const status = useTaskStatus();
  const quick = useTaskQuickUpdate();
  const { isFinal } = useTaskLookups();
  const [dialog, setDialog] = useState<TaskDialog>(null);

  const remove = useApiMutation({
    mutationFn: (target: Task) => tasksApi.delete(target.id),
    successMessage: 'Task deleted successfully.',
    errorMessage: 'Failed to delete task.',
    // Lists only: refetching this task's detail would 404 before we navigate away
    invalidate: [queryKeys.tasks.lists, queryKeys.projects.all],
    onSuccess: (_, target) => navigate(target.project ? `/projects/${target.project.id}?tab=tasks` : '/tasks'),
  });

  useHotkeys(
    {
      e: () => canEdit && setDialog('edit'),
      c: () => task && (can('tasks.change_status') || canEdit) && status.change(task, isFinal(task.status) ? TASK_REOPEN_STATUS : TASK_DONE_STATUS),
      j: () => siblings.nextId && navigate(`/tasks/${siblings.nextId}`),
      k: () => siblings.previousId && navigate(`/tasks/${siblings.previousId}`),
    },
    task !== undefined && dialog === null
  );

  const updateComments = (update: (comments: Comment[]) => Comment[]) => {
    queryClient.setQueryData<Task>(queryKeys.tasks.detail(id), (prev) =>
      prev ? { ...prev, comments: update(prev.comments ?? []) } : prev
    );
  };

  if (taskQuery.isPending) {
    return <LoadingSpinner text="Loading task details..." />;
  }

  if (taskQuery.isError || !task) {
    return (
      <ErrorState
        title="Couldn't load this task"
        message={taskQuery.error}
        onRetry={() => taskQuery.refetch()}
        action={
          <Button variant="ghost" onClick={() => navigate('/tasks')}>
            <ArrowLeftIcon className="rtl:-scale-x-100" />
            Back to tasks
          </Button>
        }
      />
    );
  }

  const members: User[] = [];
  for (const member of [project.data?.owner, ...(project.data?.members ?? [])]) {
    if (member && !members.some((m) => m.id === member.id)) members.push(member);
  }

  return (
    <div className="space-y-6">
      <TaskDetailsHeader
        task={task}
        onRename={(title) => quick.update(task, { title }, 'Task renamed.')}
        onEdit={() => setDialog('edit')}
        onDuplicate={() => setDialog('duplicate')}
        onDelete={() => setDialog('delete')}
      />

      <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div className="min-w-0 space-y-4">
          <TaskDescription
            description={task.description}
            canEdit={canEdit}
            saving={quick.isPending}
            onSave={(description, notify) =>
              quick.update(task, { description }, notify ? 'Description saved.' : undefined)
            }
          />
          <TaskComments taskId={task.id} comments={task.comments ?? []} onCommentsChange={updateComments} />
        </div>

        <TaskSidebar task={task} members={members} status={status} quick={quick} />
      </div>

      <TaskFormDialog open={dialog === 'edit'} onOpenChange={(open) => setDialog(open ? 'edit' : null)} task={task} />
      <TaskFormDialog
        open={dialog === 'duplicate'}
        onOpenChange={(open) => setDialog(open ? 'duplicate' : null)}
        template={task}
      />

      <ConfirmModal
        isOpen={dialog === 'delete'}
        title="Delete task"
        message={`Are you sure you want to delete task "${task.title}"? This action cannot be undone.`}
        confirmText="Delete task"
        danger
        loading={remove.isPending}
        onConfirm={() => remove.mutate(task)}
        onClose={() => setDialog(null)}
      />
    </div>
  );
}

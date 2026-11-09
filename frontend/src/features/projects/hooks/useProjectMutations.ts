import projectsApi, { type ProjectInput } from '@/api/projectsApi';
import { queryKeys } from '@/api/queryKeys';
import useApiMutation from '@/hooks/useApiMutation';
import { showToast, showUndoToast } from '@/lib/toast/ToastContext';
import type { Project, User } from '@/types/api';
import { toDateOnly } from '@/utils/dates';
import { formatDateOnly } from '@/utils/format';

interface ProjectChange {
  project: Project;
  changes: Partial<ProjectInput>;
  message: string;
  /** Values that restore the project; adds an Undo button to the toast. */
  revert?: Partial<ProjectInput>;
}

/** Project writes shared by the list and details pages. */
export default function useProjectMutations() {
  const archive = useApiMutation({
    mutationFn: (project: Project) => projectsApi.archive(project.id),
    successMessage: (_, project) =>
      `Project "${project.title}" was ${project.status === 'archived' ? 'unarchived' : 'archived'} successfully.`,
    errorMessage: 'Failed to update archive status.',
    invalidate: [queryKeys.projects.all],
  });

  const remove = useApiMutation({
    mutationFn: (project: Project) => projectsApi.delete(project.id),
    successMessage: (_, project) => `Project "${project.title}" was deleted successfully.`,
    errorMessage: 'Failed to delete project.',
    // Not `projects.all`: refetching the deleted project's detail would 404
    invalidate: [queryKeys.projects.lists, queryKeys.projects.allOptions, queryKeys.tasks.lists],
  });

  const removeMember = useApiMutation({
    mutationFn: ({ project, member }: { project: Project; member: User }) =>
      projectsApi.removeMember(project.id, member.id),
    successMessage: (_, { member }) => `Member "${member.name}" removed successfully.`,
    errorMessage: 'Failed to remove member from project.',
    invalidate: [queryKeys.projects.all],
  });

  const update = useApiMutation({
    // `due_date` is validated as after_or_equal:start_date, so the start date always goes along
    mutationFn: ({ project, changes }: ProjectChange) =>
      projectsApi.update(project.id, { title: project.title, start_date: toDateOnly(project.start_date), ...changes }),
    errorMessage: 'Failed to update project.',
    invalidate: [queryKeys.projects.all],
    onSuccess: (_, { project, message, revert }) => {
      if (!revert) {
        showToast(message, 'info');
        return;
      }
      showUndoToast(message, () => update.mutate({ project, changes: revert, message: 'Change undone.' }));
    },
  });

  const extend = (project: Project, dueDate: string) =>
    update.mutate({
      project,
      changes: { due_date: dueDate },
      revert: { due_date: toDateOnly(project.due_date) },
      message: `"${project.title}" is now due ${formatDateOnly(dueDate)}.`,
    });

  const complete = (project: Project) => {
    const previous = project.status;
    update.mutate({
      project,
      changes: { status: 'completed' },
      revert: previous === 'archived' ? undefined : { status: previous },
      message: `"${project.title}" marked as completed.`,
    });
  };

  return { archive, remove, removeMember, extend, complete, isUpdating: update.isPending };
}

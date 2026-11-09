import { useState } from 'react';
import { PlusIcon } from 'lucide-react';
import tasksApi from '@/api/tasksApi';
import { queryKeys } from '@/api/queryKeys';
import ViewToggle from '@/components/data/ViewToggle';
import PageHeader from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/button';
import ConfirmModal from '@/components/ui/ConfirmModal';
import ErrorState from '@/components/ui/ErrorState';
import useApiMutation from '@/hooks/useApiMutation';
import useProjectOptions from '@/hooks/queries/useProjectOptions';
import useUserOptions from '@/hooks/queries/useUserOptions';
import { useAuth } from '@/lib/auth/AuthContext';
import type { Task } from '@/types/api';
import ExportTasksButton from './components/ExportTasksButton';
import SavedViewsMenu from './components/SavedViewsMenu';
import TaskFormDialog from './components/TaskFormDialog';
import TasksBoard from './components/TasksBoard';
import TasksBulkBar from './components/TasksBulkBar';
import TasksCalendar from './components/TasksCalendar';
import TasksFilters from './components/TasksFilters';
import TasksQuickFilters from './components/TasksQuickFilters';
import TasksTable from './components/TasksTable';
import useTasksList from './hooks/useTasksList';
import { stateFromSearchParams } from './tasksReducer';

export default function TasksPage() {
  const { can, user } = useAuth();
  const { state, dispatch, query, view, setView, board, calendar, urlKey, exportParams } = useTasksList();
  const { filters, sorting, page, modals } = state;
  const [selectedIds, setSelectedIds] = useState<Set<number>>(() => new Set());

  const projects = useProjectOptions({ includeArchived: true });
  // /users/options needs users.view; without it the assignee filter is hidden
  const users = useUserOptions({ enabled: can('users.view') });

  const remove = useApiMutation({
    mutationFn: (task: Task) => tasksApi.delete(task.id),
    successMessage: (_, task) => `Task "${task.title}" was deleted successfully.`,
    errorMessage: 'Failed to delete task.',
    invalidate: [queryKeys.tasks.lists, queryKeys.projects.all],
    onSuccess: () => dispatch({ type: 'CLOSE_DELETE_MODAL' }),
  });

  const tasks = query.data?.items ?? [];
  // Bulk actions apply to the selected tasks still on screen
  const selected = view === 'list' ? tasks.filter((t) => selectedIds.has(t.id)) : [];

  const showList = (changes: Partial<typeof filters>) => {
    dispatch({ type: 'SET_FILTERS', payload: changes });
    setView('list');
  };

  return (
    <div className="space-y-6">
      <PageHeader
        eyebrow="Workspace"
        title="Tasks"
        description="Search, filter and track every task across your projects."
        actions={
          can('tasks.create') && (
            <Button variant="brand" onClick={() => dispatch({ type: 'OPEN_CREATE_MODAL' })}>
              <PlusIcon />
              New task
            </Button>
          )
        }
      />

      <div className="space-y-3">
        <div className="flex flex-wrap items-center gap-2">
          <TasksQuickFilters filters={filters} userId={user?.id} dispatch={dispatch} />
          <div className="ms-auto flex items-center gap-2">
            <SavedViewsMenu
              currentQuery={urlKey}
              currentView={view}
              onApply={(saved) => {
                const { filters: savedFilters, sorting: savedSorting } = stateFromSearchParams(new URLSearchParams(saved.query));
                dispatch({ type: 'LOAD', payload: { filters: savedFilters, sorting: savedSorting } });
                setView(saved.view);
              }}
            />
            <ExportTasksButton params={exportParams} />
            <ViewToggle value={view} onChange={setView} />
          </div>
        </div>
        <TasksFilters
          filters={filters}
          sorting={sorting}
          projects={projects.data ?? []}
          users={users.data ?? []}
          dispatch={dispatch}
          hideStatus={view === 'board'}
        />

        {view === 'board' ? (
          <div className="surface overflow-hidden">
            <TasksBoard board={board} showProject onShowAll={(status) => showList({ status })} />
          </div>
        ) : view === 'calendar' ? (
          <div className="surface overflow-hidden">
            <TasksCalendar calendar={calendar} onShowDay={(day) => showList({ due_date_from: day, due_date_to: day })} />
          </div>
        ) : query.isError ? (
          <ErrorState title="Failed to load tasks" message={query.error} onRetry={() => query.refetch()} />
        ) : (
          <TasksTable
            tasks={tasks}
            loading={query.isPending}
            page={page}
            lastPage={query.data?.lastPage ?? 1}
            total={query.data?.total ?? 0}
            selectedIds={selectedIds}
            onSelectionChange={setSelectedIds}
            onDelete={(task) => dispatch({ type: 'OPEN_DELETE_MODAL', payload: task })}
            onPageChange={(next) => dispatch({ type: 'SET_PAGE', payload: next })}
          />
        )}
      </div>

      {selected.length > 0 && <TasksBulkBar selected={selected} onClear={() => setSelectedIds(new Set())} />}

      <TaskFormDialog
        open={modals.isCreateOpen}
        onOpenChange={(open) => dispatch({ type: open ? 'OPEN_CREATE_MODAL' : 'CLOSE_CREATE_MODAL' })}
      />

      <ConfirmModal
        isOpen={modals.taskToDelete !== null}
        title="Delete task"
        message={`Are you sure you want to delete task "${modals.taskToDelete?.title}"?`}
        confirmText="Delete"
        danger
        loading={remove.isPending}
        onConfirm={() => modals.taskToDelete && remove.mutate(modals.taskToDelete)}
        onClose={() => dispatch({ type: 'CLOSE_DELETE_MODAL' })}
      />
    </div>
  );
}

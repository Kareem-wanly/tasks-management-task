import { useState } from 'react';
import { PlusIcon } from 'lucide-react';
import usersApi from '@/api/usersApi';
import { queryKeys } from '@/api/queryKeys';
import Pagination from '@/components/data/Pagination';
import PageHeader from '@/components/layout/PageHeader';
import Panel from '@/components/layout/Panel';
import ConfirmModal from '@/components/ui/ConfirmModal';
import ErrorState from '@/components/ui/ErrorState';
import { Button } from '@/components/ui/button';
import useApiMutation from '@/hooks/useApiMutation';
import useRoles from '@/hooks/queries/useRoles';
import { useAuth } from '@/lib/auth/AuthContext';
import type { UserListItem } from '@/types/api';
import AssignRolesDialog from './components/AssignRolesDialog';
import UserFormDialog from './components/UserFormDialog';
import UsersFilters from './components/UsersFilters';
import UsersTable from './components/UsersTable';
import useUsersList from './hooks/useUsersList';

export default function UsersPage() {
  const { user: currentUser, can } = useAuth();
  const list = useUsersList();
  const roles = useRoles();

  // undefined = closed, null = creating, a user = editing
  const [formUser, setFormUser] = useState<UserListItem | null | undefined>(undefined);
  const [userForRoles, setUserForRoles] = useState<UserListItem | null>(null);
  const [userToToggle, setUserToToggle] = useState<UserListItem | null>(null);
  const deactivating = userToToggle?.is_active ?? false;

  const toggleActive = useApiMutation({
    mutationFn: (user: UserListItem) => usersApi.setActive(user.id, !user.is_active),
    successMessage: (_, user) => `User "${user.name}" was ${user.is_active ? 'deactivated' : 'activated'}.`,
    errorMessage: 'Failed to change the user.',
    invalidate: [queryKeys.users.all],
    onSuccess: () => setUserToToggle(null),
  });

  return (
    <div className="space-y-8">
      <PageHeader
        eyebrow="Management"
        title="Users"
        description="Manage team members, accounts, and system role assignments."
        actions={
          can('users.create') && (
            <Button variant="brand" onClick={() => setFormUser(null)}>
              <PlusIcon />
              Add New User
            </Button>
          )
        }
      />

      <div className="space-y-4">
        <UsersFilters
          searchTerm={list.searchTerm}
          selectedRole={list.selectedRole}
          roles={roles.data ?? []}
          onSearchChange={list.setSearchTerm}
          onRoleChange={list.setSelectedRole}
        />

        {list.query.isError ? (
          <ErrorState message="Failed to fetch users list." onRetry={() => list.query.refetch()} />
        ) : (
          <>
            <Panel flush>
              <UsersTable
                users={list.users}
                isLoading={list.query.isPending}
                currentUserId={currentUser?.id}
                canEdit={can('users.update')}
                canToggleActive={can('users.delete')}
                canAssignRoles={can('roles.assign')}
                onEdit={setFormUser}
                onManageRoles={setUserForRoles}
                onToggleActive={setUserToToggle}
              />
            </Panel>
            <Pagination
              page={list.page}
              lastPage={list.lastPage}
              total={list.total}
              itemLabel="users"
              onPageChange={list.setPage}
            />
          </>
        )}
      </div>

      <UserFormDialog
        // The schema differs between create and edit, so remount when the mode changes
        key={formUser ? 'edit' : 'create'}
        open={formUser !== undefined}
        onOpenChange={(open) => !open && setFormUser(undefined)}
        user={formUser ?? null}
        roles={roles.data ?? []}
      />

      <AssignRolesDialog user={userForRoles} onClose={() => setUserForRoles(null)} />

      <ConfirmModal
        isOpen={userToToggle !== null}
        title={deactivating ? 'Deactivate User' : 'Activate User'}
        message={
          deactivating
            ? `Deactivate "${userToToggle?.name ?? ''}"? They will be signed out and can't sign in here or in Bitrix24 until activated again.`
            : `Activate "${userToToggle?.name ?? ''}" again, here and in Bitrix24?`
        }
        confirmText={deactivating ? 'Deactivate' : 'Activate'}
        danger={deactivating}
        loading={toggleActive.isPending}
        onConfirm={() => userToToggle && toggleActive.mutate(userToToggle)}
        onClose={() => setUserToToggle(null)}
      />
    </div>
  );
}

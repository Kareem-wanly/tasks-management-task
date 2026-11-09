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
  const [userToDelete, setUserToDelete] = useState<UserListItem | null>(null);

  const remove = useApiMutation({
    mutationFn: (user: UserListItem) => usersApi.delete(user.id),
    successMessage: (_, user) => `User "${user.name}" was deleted successfully.`,
    errorMessage: 'Failed to delete user.',
    invalidate: [queryKeys.users.all],
    onSuccess: () => {
      setUserToDelete(null);
      // Deleting the last row of a page would leave an empty page
      if (list.users.length === 1 && list.page > 1) list.setPage(list.page - 1);
    },
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
                canDelete={can('users.delete')}
                canAssignRoles={can('roles.assign')}
                onEdit={setFormUser}
                onManageRoles={setUserForRoles}
                onDelete={setUserToDelete}
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
        isOpen={userToDelete !== null}
        title="Delete User"
        message={`Are you sure you want to delete "${userToDelete?.name ?? ''}"? This action cannot be undone.`}
        confirmText="Delete User"
        danger
        loading={remove.isPending}
        onConfirm={() => userToDelete && remove.mutate(userToDelete)}
        onClose={() => setUserToDelete(null)}
      />
    </div>
  );
}

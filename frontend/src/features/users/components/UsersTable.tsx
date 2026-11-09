import { PencilIcon, ShieldIcon, Trash2Icon } from 'lucide-react';
import UserAvatar from '@/components/data/UserAvatar';
import DataTable, { type DataTableColumn } from '@/components/data/DataTable';
import StatusBadge from '@/components/data/StatusBadge';
import { Button } from '@/components/ui/button';
import { formatTimestampDate } from '@/utils/format';
import type { UserListItem } from '@/types/api';

interface UsersTableProps {
  users: UserListItem[];
  isLoading: boolean;
  currentUserId: number | undefined;
  canEdit: boolean;
  canDelete: boolean;
  canAssignRoles: boolean;
  onEdit: (user: UserListItem) => void;
  onManageRoles: (user: UserListItem) => void;
  onDelete: (user: UserListItem) => void;
}

export default function UsersTable({
  users,
  isLoading,
  currentUserId,
  canEdit,
  canDelete,
  canAssignRoles,
  onEdit,
  onManageRoles,
  onDelete,
}: UsersTableProps) {
  const columns: DataTableColumn<UserListItem>[] = [
    {
      key: 'user',
      header: 'User',
      cell: (u) => (
        <div className="flex items-center gap-3">
          <UserAvatar user={u} size="md" />
          <div className="min-w-0">
            <div className="font-medium">
              {u.name}
              {u.id === currentUserId && <span className="ms-1.5 text-xs font-medium text-brand">(You)</span>}
            </div>
            <div className="text-xs text-muted-foreground">{u.email}</div>
          </div>
        </div>
      ),
    },
    {
      key: 'roles',
      header: 'Roles',
      cell: (u) =>
        u.roles.length > 0 ? (
          <div className="flex flex-wrap gap-1">
            {u.roles.map((role) => (
              <StatusBadge key={role.id} plain>
                {role.display_name || role.name}
              </StatusBadge>
            ))}
          </div>
        ) : (
          <span className="text-muted-foreground">No role</span>
        ),
    },
    {
      key: 'joined',
      header: 'Joined',
      cell: (u) => formatTimestampDate(u.created_at, 'N/A'),
      className: 'text-muted-foreground tabular-nums',
    },
  ];

  if (canEdit || canDelete || canAssignRoles) {
    columns.push({
      key: 'actions',
      header: <span className="sr-only">Actions</span>,
      className: 'w-32 text-end',
      cell: (u) => {
        // Users can't delete themselves or change their own roles (backend R7)
        const isSelf = u.id === currentUserId;
        return (
          <div className="flex justify-end gap-1">
            {canEdit && (
              <Button variant="ghost" size="icon-sm" onClick={() => onEdit(u)} aria-label={`Edit ${u.name}`}>
                <PencilIcon />
              </Button>
            )}
            {canAssignRoles && !isSelf && (
              <Button variant="ghost" size="icon-sm" onClick={() => onManageRoles(u)} aria-label={`Manage roles of ${u.name}`}>
                <ShieldIcon />
              </Button>
            )}
            {canDelete && !isSelf && (
              <Button variant="ghost" size="icon-sm" onClick={() => onDelete(u)} aria-label={`Delete ${u.name}`}>
                <Trash2Icon className="text-destructive" />
              </Button>
            )}
          </div>
        );
      },
    });
  }

  return (
    <DataTable
      columns={columns}
      rows={users}
      getRowKey={(u) => u.id}
      isLoading={isLoading}
      emptyMessage="No users found"
      emptyDescription="Try adjusting your search or filters."
      framed={false}
    />
  );
}

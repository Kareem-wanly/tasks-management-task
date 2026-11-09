import { useEffect, useState } from 'react';
import usersApi from '@/api/usersApi';
import { queryKeys } from '@/api/queryKeys';
import FormDialog from '@/components/ui/FormDialog';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import useApiMutation from '@/hooks/useApiMutation';
import useRoles from '@/hooks/queries/useRoles';
import type { UserListItem } from '@/types/api';

interface AssignRolesDialogProps {
  /** The user whose roles are edited; null closes the dialog. */
  user: UserListItem | null;
  onClose: () => void;
}

export default function AssignRolesDialog({ user, onClose }: AssignRolesDialogProps) {
  const rolesQuery = useRoles({ enabled: user !== null });
  const [selectedIds, setSelectedIds] = useState<number[]>([]);

  useEffect(() => {
    if (user) setSelectedIds(user.roles.map((role) => role.id));
  }, [user]);

  const save = useApiMutation({
    mutationFn: ({ userId, roleIds }: { userId: number; roleIds: number[] }) => usersApi.syncRoles(userId, roleIds),
    successMessage: `Roles for "${user?.name ?? ''}" were updated successfully.`,
    errorMessage: 'Failed to update user roles.',
    invalidate: [queryKeys.users.all, queryKeys.roles.all],
    onSuccess: onClose,
  });

  const toggle = (roleId: number, checked: boolean) =>
    setSelectedIds((prev) => (checked ? [...prev, roleId] : prev.filter((id) => id !== roleId)));

  return (
    <FormDialog
      open={user !== null}
      onOpenChange={(open) => !open && onClose()}
      title={`Manage roles for ${user?.name ?? ''}`}
      description={user?.email}
      submitLabel="Save Roles"
      submitting={save.isPending}
      onSubmit={(e) => {
        e.preventDefault();
        if (user) save.mutate({ userId: user.id, roleIds: selectedIds });
      }}
    >
      {rolesQuery.isPending && (
        <div className="grid gap-2">
          <Skeleton className="h-12" />
          <Skeleton className="h-12" />
        </div>
      )}
      {rolesQuery.isError && <p className="text-sm text-destructive">Failed to load available roles.</p>}

      <div className="grid gap-2">
        {rolesQuery.data?.map((role) => (
          <Label
            key={role.id}
            htmlFor={`role-${role.id}`}
            className="flex cursor-pointer items-start gap-3 rounded-xl border bg-card px-3 py-2.5 font-normal transition-colors duration-300 ease-luxe hover:border-brand/40 has-data-[state=checked]:border-brand/40 has-data-[state=checked]:bg-brand-soft/40"
          >
            <Checkbox
              id={`role-${role.id}`}
              checked={selectedIds.includes(role.id)}
              onCheckedChange={(checked) => toggle(role.id, checked === true)}
              disabled={save.isPending}
            />
            <div className="grid gap-0.5">
              <span className="font-medium">{role.display_name || role.name}</span>
              {role.description && <span className="text-xs text-muted-foreground">{role.description}</span>}
            </div>
          </Label>
        ))}
      </div>
    </FormDialog>
  );
}

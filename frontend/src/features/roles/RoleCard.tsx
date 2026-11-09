import { Link } from 'react-router-dom';
import { PencilIcon, Trash2Icon, UsersIcon } from 'lucide-react';
import StatusBadge from '@/components/data/StatusBadge';
import { Button } from '@/components/ui/button';
import type { Role } from '@/types/api';

const MAX_CHIPS = 4;

interface RoleCardProps {
  role: Role;
  isProtected: boolean;
  canEdit: boolean;
  canDelete: boolean;
  onDelete: (role: Role) => void;
}

export default function RoleCard({ role, isProtected, canEdit, canDelete, onDelete }: RoleCardProps) {
  const label = role.display_name || role.name;
  const permissions = role.permissions ?? [];
  const permissionsCount = role.permissions_count ?? permissions.length;
  const chips = permissions.slice(0, MAX_CHIPS);
  const hiddenCount = permissionsCount - chips.length;

  return (
    <article className="group surface flex h-full flex-col p-5 transition-colors duration-150 ease-luxe hover:border-foreground/20">
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0 space-y-1">
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="truncate text-[15px] font-semibold tracking-tight transition-colors duration-300 group-hover:text-brand">
              {label}
            </h3>
            {isProtected && (
              <StatusBadge tone="brand" plain>
                System
              </StatusBadge>
            )}
          </div>
          <code dir="ltr" className="font-mono text-xs text-muted-foreground">
            {role.name}
          </code>
        </div>

        {(canEdit || (canDelete && !isProtected)) && (
          <div className="flex shrink-0 items-center gap-1">
            {canEdit && (
              <Button asChild variant="ghost" size="icon-sm">
                <Link to={`/roles/${role.id}`} aria-label={`Edit ${label}`}>
                  <PencilIcon />
                </Link>
              </Button>
            )}
            {canDelete && !isProtected && (
              <Button variant="ghost" size="icon-sm" onClick={() => onDelete(role)} aria-label={`Delete ${label}`}>
                <Trash2Icon className="text-destructive" />
              </Button>
            )}
          </div>
        )}
      </div>

      <p className="mt-3 flex-1 text-sm leading-6 text-muted-foreground">
        {role.description || 'No description provided for this role.'}
      </p>

      {chips.length > 0 && (
        <div className="mt-4 flex flex-wrap gap-1.5">
          {chips.map((permission) => (
            <span key={permission.id} dir="ltr" className="rounded-full bg-muted px-2 py-0.5 font-mono text-xs">
              {permission.name}
            </span>
          ))}
          {hiddenCount > 0 && (
            <span className="rounded-full px-2 py-0.5 text-xs text-muted-foreground tabular-nums">
              +{hiddenCount} more
            </span>
          )}
        </div>
      )}

      <div className="mt-5 flex items-center gap-3 border-t border-border/60 pt-4 text-sm text-muted-foreground">
        <StatusBadge plain>
          <span className="tabular-nums">{permissionsCount}</span> permissions
        </StatusBadge>
        <span className="inline-flex items-center gap-1.5">
          <UsersIcon className="size-4" />
          <span className="tabular-nums">{role.users_count ?? 0}</span> users
        </span>
      </div>
    </article>
  );
}

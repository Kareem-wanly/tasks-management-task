import type { Permission } from '@/types/api';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

interface RolePermissionGroupProps {
  category: string;
  permissions: Permission[];
  selectedIds: number[];
  onToggle: (permId: number) => void;
  onToggleCategory: (perms: Permission[]) => void;
}

export default function RolePermissionGroup({
  category,
  permissions,
  selectedIds,
  onToggle,
  onToggleCategory,
}: RolePermissionGroupProps) {
  const selectedInCat = permissions.filter((p) => selectedIds.includes(p.id)).length;
  const isCatAllSelected = selectedInCat === permissions.length;

  return (
    <section className="space-y-3">
      <div className="flex items-center justify-between gap-3">
        <div className="flex items-center gap-2">
          <span className="eyebrow">{category}</span>
          <span className="text-xs text-muted-foreground tabular-nums">
            {selectedInCat}/{permissions.length}
          </span>
        </div>
        <Button type="button" variant="ghost" size="xs" onClick={() => onToggleCategory(permissions)}>
          {isCatAllSelected ? 'Deselect group' : 'Select group'}
        </Button>
      </div>

      <div className="grid gap-2 sm:grid-cols-2">
        {permissions.map((perm) => {
          const isChecked = selectedIds.includes(perm.id);
          const inputId = `permission-${perm.id}`;

          return (
            <Label
              key={perm.id}
              htmlFor={inputId}
              className={cn(
                'flex cursor-pointer items-start gap-3 rounded-xl border bg-card px-3 py-2.5 font-normal transition-colors duration-300 ease-luxe hover:border-brand/40',
                isChecked && 'border-brand/40 bg-brand-soft/40',
              )}
            >
              <Checkbox
                id={inputId}
                className="mt-0.5"
                checked={isChecked}
                onCheckedChange={() => onToggle(perm.id)}
              />
              <span className="grid min-w-0 gap-0.5">
                <code dir="ltr" className="truncate font-mono text-xs">
                  {perm.name}
                </code>
                {perm.description && (
                  <span className="text-xs leading-5 text-muted-foreground">{perm.description}</span>
                )}
              </span>
            </Label>
          );
        })}
      </div>
    </section>
  );
}

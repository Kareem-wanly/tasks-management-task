import { useMemo, useState } from 'react';
import { SearchIcon } from 'lucide-react';
import type { Permission } from '@/types/api';
import Panel from '@/components/layout/Panel';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import RolePermissionGroup from './RolePermissionGroup';

interface RolePermissionsPanelProps {
  allPermissions: Permission[];
  selectedIds: number[];
  onChange: (ids: number[]) => void;
  error?: string;
}

export default function RolePermissionsPanel({ allPermissions, selectedIds, onChange, error }: RolePermissionsPanelProps) {
  const [searchQuery, setSearchQuery] = useState('');

  const groupedPermissions = useMemo(() => {
    const filtered = allPermissions.filter((p) => {
      if (!searchQuery.trim()) return true;
      const query = searchQuery.toLowerCase();
      return (
        p.name.toLowerCase().includes(query) ||
        (p.description && p.description.toLowerCase().includes(query))
      );
    });

    return filtered.reduce<Record<string, Permission[]>>((acc, perm) => {
      const group = perm.name.split('.')[0] || 'general';
      if (!acc[group]) acc[group] = [];
      acc[group].push(perm);
      return acc;
    }, {});
  }, [allPermissions, searchQuery]);

  const handleTogglePermission = (permId: number) => {
    onChange(selectedIds.includes(permId) ? selectedIds.filter((i) => i !== permId) : [...selectedIds, permId]);
  };

  const handleToggleCategory = (permsInGroup: Permission[]) => {
    const groupIds = permsInGroup.map((p) => p.id);
    const allSelected = groupIds.every((gid) => selectedIds.includes(gid));

    if (allSelected) {
      onChange(selectedIds.filter((gid) => !groupIds.includes(gid)));
    } else {
      onChange(Array.from(new Set([...selectedIds, ...groupIds])));
    }
  };

  const handleSelectAllGlobal = () => {
    if (selectedIds.length === allPermissions.length) {
      onChange([]);
    } else {
      onChange(allPermissions.map((p) => p.id));
    }
  };

  const groups = Object.entries(groupedPermissions);

  return (
    <Panel
      title="Permissions"
      description={
        <span className="tabular-nums">
          {selectedIds.length} of {allPermissions.length} enabled
        </span>
      }
      actions={
        <>
          <div className="relative">
            <SearchIcon className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              type="search"
              placeholder="Filter permissions..."
              aria-label="Filter permissions"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-48 ps-9"
            />
          </div>
          <Button type="button" variant="outline" size="sm" onClick={handleSelectAllGlobal}>
            {selectedIds.length === allPermissions.length ? 'Deselect all' : 'Select all'}
          </Button>
        </>
      }
    >
      {error && (
        <p role="alert" className="mb-5 rounded-xl bg-danger-soft px-4 py-3 text-sm text-danger">
          {error}
        </p>
      )}

      {groups.length === 0 ? (
        <p className="py-10 text-center text-sm text-muted-foreground">No permissions matching "{searchQuery}"</p>
      ) : (
        <div className="space-y-6">
          {groups.map(([category, perms]) => (
            <RolePermissionGroup
              key={category}
              category={category}
              permissions={perms}
              selectedIds={selectedIds}
              onToggle={handleTogglePermission}
              onToggleCategory={handleToggleCategory}
            />
          ))}
        </div>
      )}
    </Panel>
  );
}

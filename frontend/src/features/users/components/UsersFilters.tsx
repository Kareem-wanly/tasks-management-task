import { SearchIcon, XIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { Role } from '@/types/api';

// Select items can't use '' as a value, so "all roles" gets its own token
const ALL_ROLES = 'all';

interface UsersFiltersProps {
  searchTerm: string;
  selectedRole: string;
  roles: Role[];
  onSearchChange: (value: string) => void;
  onRoleChange: (value: string) => void;
}

export default function UsersFilters({
  searchTerm,
  selectedRole,
  roles,
  onSearchChange,
  onRoleChange,
}: UsersFiltersProps) {
  return (
    <div className="flex flex-wrap items-center gap-2">
      <div className="relative min-w-60 flex-1">
        <SearchIcon className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          type="search"
            data-page-search
          placeholder="Search by name or email..."
          value={searchTerm}
          onChange={(e) => onSearchChange(e.target.value)}
          className="ps-9 pe-9"
          aria-label="Search users"
        />
        {searchTerm && (
          <Button
            type="button"
            variant="ghost"
            size="icon-xs"
            className="absolute end-1.5 top-1/2 -translate-y-1/2"
            onClick={() => onSearchChange('')}
            aria-label="Clear search"
          >
            <XIcon />
          </Button>
        )}
      </div>

      <Select
        value={selectedRole || ALL_ROLES}
        onValueChange={(value) => onRoleChange(value === ALL_ROLES ? '' : value)}
      >
        <SelectTrigger className="w-40" aria-label="Filter by role">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value={ALL_ROLES}>All roles</SelectItem>
          {roles.map((role) => (
            <SelectItem key={role.id} value={role.name}>
              {role.display_name || role.name}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  );
}

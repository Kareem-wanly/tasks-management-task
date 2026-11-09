import { ArrowDownIcon, ArrowUpIcon, LayoutGridIcon, Rows3Icon, SearchIcon, XIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { EDITABLE_PROJECT_STATUSES, PROJECT_STATUS_LABELS } from '@/constants/statuses';
import { cn } from '@/lib/utils';
import type { ProjectStatus, UserOption } from '@/types/api';

export type ProjectsViewMode = 'table' | 'cards';

// Select items can't use '' as a value, so "all" gets its own token
const ALL = 'all';
const STATUS_OPTIONS: ProjectStatus[] = [...EDITABLE_PROJECT_STATUSES, 'archived'];
const SORT_OPTIONS = [
  { value: 'created_at', label: 'Created date' },
  { value: 'start_date', label: 'Start date' },
  { value: 'due_date', label: 'Due date' },
  { value: 'title', label: 'Title' },
];

interface ProjectsFiltersProps {
  searchInput: string;
  onSearchChange: (value: string) => void;
  currentStatus: string;
  currentMember: string;
  currentSort: string;
  currentDirection: string;
  members: UserOption[];
  viewMode: ProjectsViewMode;
  onViewModeChange: (mode: ProjectsViewMode) => void;
  onFilterChange: (key: string, value: string) => void;
}

export default function ProjectsFilters({
  searchInput,
  onSearchChange,
  currentStatus,
  currentMember,
  currentSort,
  currentDirection,
  members,
  viewMode,
  onViewModeChange,
  onFilterChange,
}: ProjectsFiltersProps) {
  const isAsc = currentDirection === 'asc';
  const toAll = (value: string) => (value === ALL ? '' : value);

  const viewButton = (mode: ProjectsViewMode, label: string, Icon: typeof Rows3Icon) => (
    <Button
      type="button"
      variant="ghost"
      size="icon-sm"
      aria-label={label}
      aria-pressed={viewMode === mode}
      onClick={() => onViewModeChange(mode)}
      className={cn('text-muted-foreground', viewMode === mode && 'bg-muted text-foreground')}
    >
      <Icon />
    </Button>
  );

  return (
    <div className="flex flex-wrap items-center gap-2">
      <div className="relative min-w-60 flex-1">
        <SearchIcon className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          type="search"
            data-page-search
          name="search"
          placeholder="Search projects by title or description..."
          value={searchInput}
          onChange={(e) => onSearchChange(e.target.value)}
          className="ps-9"
          aria-label="Search projects"
        />
        {searchInput && (
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

      <Select value={currentStatus || ALL} onValueChange={(v) => onFilterChange('status', toAll(v))}>
        <SelectTrigger className="w-40" aria-label="Filter by status">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value={ALL}>All statuses</SelectItem>
          {STATUS_OPTIONS.map((s) => (
            <SelectItem key={s} value={s}>
              {PROJECT_STATUS_LABELS[s]}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>

      {members.length > 0 && (
        <Select value={currentMember || ALL} onValueChange={(v) => onFilterChange('member_id', toAll(v))}>
          <SelectTrigger className="w-40" aria-label="Filter by member">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ALL}>All members</SelectItem>
            {members.map((m) => (
              <SelectItem key={m.id} value={String(m.id)}>
                {m.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}

      <Select value={currentSort} onValueChange={(v) => onFilterChange('sort', v)}>
        <SelectTrigger className="w-40" aria-label="Sort by">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {SORT_OPTIONS.map((o) => (
            <SelectItem key={o.value} value={o.value}>
              Sort: {o.label}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>

      <Button
        type="button"
        variant="outline"
        size="icon"
        onClick={() => onFilterChange('direction', isAsc ? 'desc' : 'asc')}
        aria-label={isAsc ? 'Sort ascending (switch to descending)' : 'Sort descending (switch to ascending)'}
        title={`Direction: ${currentDirection.toUpperCase()}`}
      >
        {isAsc ? <ArrowUpIcon /> : <ArrowDownIcon />}
      </Button>

      <div className="hidden rounded-lg border bg-card p-0.5 md:inline-flex" role="group" aria-label="View mode">
        {viewButton('table', 'Table view', Rows3Icon)}
        {viewButton('cards', 'Cards view', LayoutGridIcon)}
      </div>
    </div>
  );
}

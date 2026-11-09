import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { BOARD_GROUPS, BOARD_SORTS, type BoardGroup, type BoardSort } from '../boardSettings';

interface BoardToolbarProps {
  sort: BoardSort;
  group: BoardGroup;
  onSortChange: (sort: BoardSort) => void;
  onGroupChange: (group: BoardGroup) => void;
}

const isSort = (value: string): value is BoardSort => value in BOARD_SORTS;
const isGroup = (value: string): value is BoardGroup => value in BOARD_GROUPS;

/** Board layout controls: card order inside columns, and swimlanes. */
export default function BoardToolbar({ sort, group, onSortChange, onGroupChange }: BoardToolbarProps) {
  return (
    <div className="flex flex-wrap items-center gap-2 px-3 pt-3">
      <Select value={sort} onValueChange={(v) => isSort(v) && onSortChange(v)}>
        <SelectTrigger size="sm" className="w-40" aria-label="Order cards by">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {Object.entries(BOARD_SORTS).map(([value, label]) => (
            <SelectItem key={value} value={value}>
              Order: {label}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
      <Select value={group} onValueChange={(v) => isGroup(v) && onGroupChange(v)}>
        <SelectTrigger size="sm" className="w-44" aria-label="Group into swimlanes by">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {Object.entries(BOARD_GROUPS).map(([value, label]) => (
            <SelectItem key={value} value={value}>
              {value === 'none' ? label : `Lanes: ${label}`}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  );
}

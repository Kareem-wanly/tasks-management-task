import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';

// Select items can't use '' as a value, so "all" gets its own token
const ALL = 'all';

export interface FilterOption {
  value: string;
  label: string;
}

interface FilterSelectProps {
  value: string;
  onChange: (value: string) => void;
  options: FilterOption[];
  /** Label of the "no filter" item; omit when the select always has a value (e.g. sorting). */
  allLabel?: string;
  ariaLabel: string;
  className?: string;
}

/** A filter-bar Select where '' means "no filter". */
export default function FilterSelect({ value, onChange, options, allLabel, ariaLabel, className }: FilterSelectProps) {
  return (
    <Select
      value={value || (allLabel ? ALL : undefined)}
      onValueChange={(next) => onChange(next === ALL ? '' : next)}
    >
      <SelectTrigger className={cn('w-40', className)} aria-label={ariaLabel}>
        <SelectValue />
      </SelectTrigger>
      <SelectContent>
        {allLabel && <SelectItem value={ALL}>{allLabel}</SelectItem>}
        {options.map((option) => (
          <SelectItem key={option.value} value={option.value}>
            {option.label}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  );
}

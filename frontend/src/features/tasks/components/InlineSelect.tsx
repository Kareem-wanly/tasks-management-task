import { Loader2Icon } from 'lucide-react';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

export interface InlineOption {
  value: string;
  label: string;
}

interface InlineSelectProps {
  value: string;
  options: InlineOption[];
  onChange: (value: string) => void;
  ariaLabel: string;
  pending?: boolean;
}

/** A compact select that saves as soon as a value is picked (task side panel). */
export default function InlineSelect({ value, options, onChange, ariaLabel, pending = false }: InlineSelectProps) {
  return (
    <div className="flex min-w-0 items-center gap-2">
      <Select value={value} onValueChange={(next) => next !== value && onChange(next)} disabled={pending}>
        <SelectTrigger size="sm" className="w-full max-w-52 min-w-0" aria-label={ariaLabel}>
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {options.map((option) => (
            <SelectItem key={option.value} value={option.value}>
              {option.label}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
      {pending && <Loader2Icon className="size-4 shrink-0 animate-spin text-muted-foreground" aria-label="Saving" />}
    </div>
  );
}

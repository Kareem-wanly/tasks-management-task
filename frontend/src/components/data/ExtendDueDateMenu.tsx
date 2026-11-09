import { useState } from 'react';
import { CalendarPlusIcon, Loader2Icon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { addDays, extensionBase } from '@/utils/dates';
import { formatDateOnly } from '@/utils/format';
import CustomDateDialog from './CustomDateDialog';

interface ExtendDueDateMenuProps {
  dueDate: string | null;
  /** Earliest allowed date for "Pick a date", e.g. the project's start date. */
  minDate?: string | null;
  onExtend: (dueDate: string) => void;
  pending?: boolean;
  /** Icon-only trigger for table rows and side panels. */
  compact?: boolean;
  /** Draws attention to the trigger, e.g. when the deadline has passed. */
  urgent?: boolean;
}

const PRESETS = [
  { days: 3, label: '+3 days' },
  { days: 7, label: '+1 week' },
  { days: 14, label: '+2 weeks' },
];

/** One-click deadline extension. Presets count from the due date, or from today when it has passed. */
export default function ExtendDueDateMenu({
  dueDate,
  minDate,
  onExtend,
  pending = false,
  compact = false,
  urgent = false,
}: ExtendDueDateMenuProps) {
  const [customOpen, setCustomOpen] = useState(false);
  const base = extensionBase(dueDate);
  const Icon = pending ? Loader2Icon : CalendarPlusIcon;
  const label = dueDate ? 'Extend due date' : 'Set due date';

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button
            type="button"
            variant={compact ? 'ghost' : 'outline'}
            size={compact ? 'icon-sm' : 'default'}
            disabled={pending}
            aria-label={label}
            title={label}
            className={cn(urgent && 'text-warning hover:text-warning', urgent && !compact && 'border-warning/40')}
          >
            <Icon className={cn(pending && 'animate-spin')} />
            {!compact && (dueDate ? 'Extend' : 'Set due date')}
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" className="w-56">
          <DropdownMenuLabel>{dueDate ? `Due ${formatDateOnly(dueDate)}` : 'No due date'}</DropdownMenuLabel>
          <DropdownMenuSeparator />
          {PRESETS.map((preset) => {
            const next = addDays(base, preset.days);
            return (
              <DropdownMenuItem key={preset.days} onSelect={() => onExtend(next)}>
                {preset.label}
                <span className="ms-auto text-xs text-muted-foreground tabular-nums">{formatDateOnly(next)}</span>
              </DropdownMenuItem>
            );
          })}
          <DropdownMenuSeparator />
          <DropdownMenuItem onSelect={() => setCustomOpen(true)}>Pick a date…</DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <CustomDateDialog
        open={customOpen}
        onOpenChange={setCustomOpen}
        title={label}
        initialDate={addDays(base, 7)}
        minDate={minDate}
        onSubmit={onExtend}
      />
    </>
  );
}

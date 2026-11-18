import { PlusIcon, XIcon } from 'lucide-react';
import UserAvatar from '@/components/data/UserAvatar';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { User, UserOption } from '@/types/api';

interface TaskPeopleListProps {
  label: string;
  /** Shown when nobody is in the list. */
  emptyText: string;
  people: User[];
  /** Who can be added; those already in the list are left out. */
  candidates: UserOption[];
  canEdit: boolean;
  pending: boolean;
  onAdd: (person: UserOption) => void;
  onRemove: (person: User) => void;
}

/** One role on a task (accomplices or auditors): avatars with remove buttons and an add menu. */
export default function TaskPeopleList({
  label,
  emptyText,
  people,
  candidates,
  canEdit,
  pending,
  onAdd,
  onRemove,
}: TaskPeopleListProps) {
  const addable = candidates.filter((c) => !people.some((p) => p.id === c.id));

  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between gap-2">
        <h3 className="text-xs font-medium text-muted-foreground">{label}</h3>
        {canEdit && addable.length > 0 && (
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button type="button" variant="ghost" size="icon-sm" disabled={pending} aria-label={`Add to ${label}`} title={`Add to ${label}`}>
                <PlusIcon />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="max-h-72 w-56 overflow-y-auto">
              {addable.map((person) => (
                <DropdownMenuItem key={person.id} onSelect={() => onAdd(person)}>
                  <UserAvatar user={person} size="xs" />
                  <span className="truncate">{person.name}</span>
                </DropdownMenuItem>
              ))}
            </DropdownMenuContent>
          </DropdownMenu>
        )}
      </div>

      {people.length === 0 ? (
        <p className="text-[13px] text-muted-foreground">{emptyText}</p>
      ) : (
        <ul className="space-y-1.5">
          {people.map((person) => (
            <li key={person.id} className="flex min-w-0 items-center gap-2 text-[13px]">
              <UserAvatar user={person} size="xs" />
              <span className="min-w-0 flex-1 truncate">{person.name}</span>
              {canEdit && (
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-xs"
                  disabled={pending}
                  onClick={() => onRemove(person)}
                  aria-label={`Remove ${person.name} from ${label}`}
                  title={`Remove ${person.name}`}
                >
                  <XIcon />
                </Button>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

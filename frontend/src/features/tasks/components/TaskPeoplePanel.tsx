import { EyeIcon, EyeOffIcon, ForwardIcon } from 'lucide-react';
import UserAvatar from '@/components/data/UserAvatar';
import Panel from '@/components/layout/Panel';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAuth } from '@/lib/auth/AuthContext';
import type { Task, User, UserOption } from '@/types/api';
import useTaskPeople from '../hooks/useTaskPeople';
import TaskPeopleList from './TaskPeopleList';

interface TaskPeoplePanelProps {
  task: Task;
  /** Who can be added or delegated to. */
  candidates: UserOption[];
}

const ids = (people: User[]): number[] => people.map((p) => p.id);

/** Accomplices and auditors (Bitrix24 step 4.1), with Watch and Delegate. */
export default function TaskPeoplePanel({ task, candidates }: TaskPeoplePanelProps) {
  const { can, user } = useAuth();
  const people = useTaskPeople(task);
  const canEdit = can('tasks.update');
  const accomplices = task.accomplices ?? [];
  const auditors = task.auditors ?? [];
  const watching = user !== null && auditors.some((a) => a.id === user.id);
  const isAssignee = user !== null && task.assignee?.id === user.id;
  const delegates = candidates.filter((c) => c.id !== user?.id);

  const save = (next: { accomplices?: number[]; auditors?: number[] }, message: string) =>
    people.setMembers(
      { accomplice_ids: next.accomplices ?? ids(accomplices), auditor_ids: next.auditors ?? ids(auditors) },
      message
    );

  const actions = (
    <div className="flex items-center gap-1">
      {user && (
        <Button
          type="button"
          variant="ghost"
          size="sm"
          disabled={people.isPending}
          onClick={() => people.watch(!watching)}
        >
          {watching ? <EyeOffIcon /> : <EyeIcon />}
          {watching ? 'Stop watching' : 'Watch'}
        </Button>
      )}
      {isAssignee && delegates.length > 0 && (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button type="button" variant="ghost" size="sm" disabled={people.isPending}>
              <ForwardIcon />
              Delegate
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="max-h-72 w-56 overflow-y-auto">
            <DropdownMenuLabel>Hand over to…</DropdownMenuLabel>
            <DropdownMenuSeparator />
            {delegates.map((person) => (
              <DropdownMenuItem key={person.id} onSelect={() => people.delegate(person.id, person.name)}>
                <UserAvatar user={person} size="xs" />
                <span className="truncate">{person.name}</span>
              </DropdownMenuItem>
            ))}
          </DropdownMenuContent>
        </DropdownMenu>
      )}
    </div>
  );

  return (
    <Panel title="People" actions={actions} bodyClassName="space-y-4">
      <TaskPeopleList
        label="Participants"
        emptyText="Only the assignee works on this task."
        people={accomplices}
        candidates={candidates}
        canEdit={canEdit}
        pending={people.isPending}
        onAdd={(person) => save({ accomplices: [...ids(accomplices), person.id] }, `${person.name} added as a participant.`)}
        onRemove={(person) =>
          save({ accomplices: ids(accomplices).filter((id) => id !== person.id) }, `${person.name} removed from participants.`)
        }
      />
      <TaskPeopleList
        label="Auditors"
        emptyText="Nobody is following this task."
        people={auditors}
        candidates={candidates}
        canEdit={canEdit}
        pending={people.isPending}
        onAdd={(person) => save({ auditors: [...ids(auditors), person.id] }, `${person.name} added as an auditor.`)}
        onRemove={(person) =>
          save({ auditors: ids(auditors).filter((id) => id !== person.id) }, `${person.name} removed from auditors.`)
        }
      />
    </Panel>
  );
}

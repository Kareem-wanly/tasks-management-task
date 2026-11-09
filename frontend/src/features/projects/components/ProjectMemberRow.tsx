import { Link } from 'react-router-dom';
import { ListChecksIcon, UserMinusIcon } from 'lucide-react';
import UserAvatar from '@/components/data/UserAvatar';
import StatusBadge from '@/components/data/StatusBadge';
import { Button } from '@/components/ui/button';
import type { User } from '@/types/api';

interface ProjectMemberRowProps {
  user: User;
  isOwner?: boolean;
  onRemove?: () => void;
  /** Link to this member's tasks in the project, when the user can view tasks. */
  tasksHref?: string;
}

export default function ProjectMemberRow({ user, isOwner = false, onRemove, tasksHref }: ProjectMemberRowProps) {
  return (
    <li className="flex items-center gap-3 px-6 py-4">
      <UserAvatar user={user} size="md" />
      <div className="min-w-0 flex-1">
        <div className="truncate text-sm font-medium">{user.name}</div>
        <div className="truncate text-xs text-muted-foreground">{user.email}</div>
      </div>
      <StatusBadge tone={isOwner ? 'brand' : 'neutral'} plain>
        {isOwner ? 'Owner' : 'Member'}
      </StatusBadge>
      {tasksHref && (
        <Button variant="ghost" size="sm" asChild>
          <Link to={tasksHref} aria-label={`Tasks assigned to ${user.name}`}>
            <ListChecksIcon />
            Tasks
          </Link>
        </Button>
      )}
      {onRemove && (
        <Button variant="ghost" size="icon-sm" onClick={onRemove} aria-label={`Remove ${user.name} from project`}>
          <UserMinusIcon className="text-destructive" />
        </Button>
      )}
    </li>
  );
}

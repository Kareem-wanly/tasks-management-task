import { UserPlusIcon, UsersIcon } from 'lucide-react';
import Panel from '@/components/layout/Panel';
import EmptyState from '@/components/data/EmptyState';
import { Button } from '@/components/ui/button';
import type { User } from '@/types/api';
import ProjectMemberRow from './ProjectMemberRow';

interface ProjectMembersTabProps {
  projectId: number;
  owner?: User;
  members: User[];
  canManageMembers: boolean;
  canViewTasks: boolean;
  onAddMember: () => void;
  onRemoveMember: (member: User) => void;
}

export default function ProjectMembersTab({
  projectId,
  owner,
  members,
  canManageMembers,
  canViewTasks,
  onAddMember,
  onRemoveMember,
}: ProjectMembersTabProps) {
  const tasksHref = (user: User) =>
    canViewTasks ? `/tasks?project_id=${projectId}&assigned_to=${user.id}` : undefined;

  return (
    <Panel
      flush
      title="Team members"
      actions={
        canManageMembers && (
          <Button size="sm" onClick={onAddMember}>
            <UserPlusIcon />
            Add member
          </Button>
        )
      }
    >
      {members.length === 0 && !owner ? (
        <EmptyState icon={UsersIcon} title="No members assigned to this project." />
      ) : (
        <ul className="divide-y divide-border/60">
          {owner && <ProjectMemberRow user={owner} isOwner tasksHref={tasksHref(owner)} />}
          {members.map((member) => (
            <ProjectMemberRow
              key={member.id}
              user={member}
              tasksHref={tasksHref(member)}
              onRemove={canManageMembers ? () => onRemoveMember(member) : undefined}
            />
          ))}
        </ul>
      )}
    </Panel>
  );
}

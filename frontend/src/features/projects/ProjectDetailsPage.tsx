import { useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import { ArrowLeftIcon } from 'lucide-react';
import { queryKeys } from '@/api/queryKeys';
import { Button } from '@/components/ui/button';
import ConfirmModal from '@/components/ui/ConfirmModal';
import ErrorState from '@/components/ui/ErrorState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { TaskFormDialog } from '@/features/tasks';
import useProject from '@/hooks/queries/useProject';
import { useAuth } from '@/lib/auth/AuthContext';
import type { User } from '@/types/api';
import AddMemberModal from './components/AddMemberModal';
import ProjectActivityTab from './components/ProjectActivityTab';
import ProjectBanner from './components/ProjectBanner';
import ProjectDetailsHeader from './components/ProjectDetailsHeader';
import ProjectFormDialog from './components/ProjectFormDialog';
import ProjectMembersTab from './components/ProjectMembersTab';
import ProjectMetaPanel from './components/ProjectMetaPanel';
import ProjectOverview from './components/ProjectOverview';
import ProjectTasksTab from './components/ProjectTasksTab';
import useOverdueTaskCount from './hooks/useOverdueTaskCount';
import useProjectActivities from './hooks/useProjectActivities';
import useProjectMutations from './hooks/useProjectMutations';
import useProjectTasks from './hooks/useProjectTasks';

const DETAILS_TABS = ['overview', 'tasks', 'members', 'activity'] as const;
type DetailsTab = (typeof DETAILS_TABS)[number];
type DetailsDialog = 'edit' | 'delete' | 'archive' | 'addMember' | 'createTask' | null;

const toTab = (value: string | null): DetailsTab => DETAILS_TABS.find((tab) => tab === value) ?? 'overview';

export default function ProjectDetailsPage() {
  const { id = '' } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { can } = useAuth();
  const canViewTasks = can('tasks.view');

  // The tab lives in the URL so Back from a task returns to it
  const [searchParams, setSearchParams] = useSearchParams();
  const activeTab = toTab(searchParams.get('tab'));
  const setActiveTab = (tab: string) =>
    setSearchParams(
      (prev) => {
        const next = new URLSearchParams(prev);
        next.set('tab', toTab(tab));
        return next;
      },
      { replace: true }
    );

  const projectQuery = useProject(id);
  const tasks = useProjectTasks(id, canViewTasks && activeTab === 'tasks');
  const activities = useProjectActivities(id, activeTab === 'activity');
  const overdueCount = useOverdueTaskCount(id, canViewTasks);
  const mutations = useProjectMutations();

  const [dialog, setDialog] = useState<DetailsDialog>(null);
  const [memberToRemove, setMemberToRemove] = useState<User | null>(null);
  const closeDialog = () => setDialog(null);
  const refreshProject = () => queryClient.invalidateQueries({ queryKey: queryKeys.projects.all });

  if (projectQuery.isPending) {
    return <LoadingSpinner size="lg" text="Loading project details..." className="py-24" />;
  }

  if (projectQuery.isError) {
    return (
      <ErrorState
        title="Error loading project"
        message={projectQuery.error}
        onRetry={() => projectQuery.refetch()}
        action={
          <Button variant="outline" asChild>
            <Link to="/projects">
              <ArrowLeftIcon className="rtl:-scale-x-100" />
              Back to projects
            </Link>
          </Button>
        }
      />
    );
  }

  const project = projectQuery.data;
  const members = (project.members || []).filter((m) => m.id !== project.owner?.id);
  const memberCount = members.length + (project.owner ? 1 : 0);
  const isArchived = project.status === 'archived';
  const canCreateTasks = can('tasks.create') && !isArchived;
  // The board and calendar need the full width; the details panel comes back with the other tabs
  const wide = activeTab === 'tasks' && tasks.view !== 'list';

  return (
    <div className="space-y-8">
      <ProjectDetailsHeader
        project={project}
        onEdit={() => setDialog('edit')}
        onArchive={() => setDialog('archive')}
        onDelete={() => setDialog('delete')}
        onExtend={(dueDate) => mutations.extend(project, dueDate)}
        extending={mutations.isUpdating}
      />

      <ProjectBanner
        project={project}
        onUnarchive={() => mutations.archive.mutate(project)}
        onComplete={() => mutations.complete(project)}
        pending={mutations.archive.isPending || mutations.isUpdating}
      />

      <div className={wide ? 'grid' : 'grid items-start gap-6 lg:grid-cols-[1.6fr_1fr]'}>
        <Tabs value={activeTab} onValueChange={setActiveTab} className="min-w-0 gap-6">
          <TabsList variant="line">
            <TabsTrigger value="overview">Overview</TabsTrigger>
            {canViewTasks && (
              <TabsTrigger value="tasks">
                Tasks <span className="text-xs text-muted-foreground tabular-nums">{project.tasks_count ?? 0}</span>
              </TabsTrigger>
            )}
            <TabsTrigger value="members">
              Members <span className="text-xs text-muted-foreground tabular-nums">{memberCount}</span>
            </TabsTrigger>
            <TabsTrigger value="activity">Activity</TabsTrigger>
          </TabsList>

          <TabsContent value="overview">
            <ProjectOverview project={project} overdueCount={overdueCount} canViewTasks={canViewTasks} />
          </TabsContent>
          {canViewTasks && (
            <TabsContent value="tasks">
              <ProjectTasksTab
                projectId={project.id}
                list={tasks}
                canCreateTasks={canCreateTasks}
                onCreateTask={() => setDialog('createTask')}
              />
            </TabsContent>
          )}
          <TabsContent value="members">
            <ProjectMembersTab
              projectId={project.id}
              owner={project.owner}
              members={members}
              canManageMembers={can('projects.manage_members')}
              canViewTasks={canViewTasks}
              onAddMember={() => setDialog('addMember')}
              onRemoveMember={setMemberToRemove}
            />
          </TabsContent>
          <TabsContent value="activity">
            <ProjectActivityTab activities={activities} />
          </TabsContent>
        </Tabs>

        {!wide && <ProjectMetaPanel project={project} memberCount={memberCount} />}
      </div>

      <ProjectFormDialog open={dialog === 'edit'} onOpenChange={(open) => setDialog(open ? 'edit' : null)} project={project} />

      <AddMemberModal
        isOpen={dialog === 'addMember'}
        onClose={closeDialog}
        onSuccess={refreshProject}
        projectId={project.id}
        existingMembers={members}
        ownerId={project.owner?.id}
      />

      <TaskFormDialog
        open={dialog === 'createTask'}
        onOpenChange={(open) => setDialog(open ? 'createTask' : null)}
        projectId={project.id}
      />

      <ConfirmModal
        isOpen={memberToRemove !== null}
        onClose={() => setMemberToRemove(null)}
        onConfirm={() =>
          memberToRemove &&
          mutations.removeMember.mutate({ project, member: memberToRemove }, { onSuccess: () => setMemberToRemove(null) })
        }
        title="Remove Team Member"
        message={`Are you sure you want to remove ${memberToRemove?.name} from this project?`}
        confirmText="Remove Member"
        danger
        loading={mutations.removeMember.isPending}
      />

      <ConfirmModal
        isOpen={dialog === 'delete'}
        onClose={closeDialog}
        onConfirm={() => mutations.remove.mutate(project, { onSuccess: () => navigate('/projects') })}
        title="Delete Project"
        message={`Are you sure you want to delete "${project.title}"?`}
        confirmText="Delete"
        danger
        loading={mutations.remove.isPending}
      />

      <ConfirmModal
        isOpen={dialog === 'archive'}
        onClose={closeDialog}
        onConfirm={() => mutations.archive.mutate(project, { onSuccess: closeDialog })}
        title={isArchived ? 'Unarchive Project' : 'Archive Project'}
        message={`Are you sure you want to ${isArchived ? 'unarchive' : 'archive'} "${project.title}"?`}
        confirmText={isArchived ? 'Unarchive' : 'Archive'}
        loading={mutations.archive.isPending}
      />
    </div>
  );
}

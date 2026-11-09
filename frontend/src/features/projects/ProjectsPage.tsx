import { useEffect, useState, type MouseEvent } from 'react';
import { PlusIcon } from 'lucide-react';
import Pagination from '@/components/data/Pagination';
import PageHeader from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/button';
import ConfirmModal from '@/components/ui/ConfirmModal';
import ErrorState from '@/components/ui/ErrorState';
import { useIsMobile } from '@/hooks/use-mobile';
import useUserOptions from '@/hooks/queries/useUserOptions';
import { useAuth } from '@/lib/auth/AuthContext';
import { readPreference, writePreference } from '@/lib/preferences/storage';
import type { Project } from '@/types/api';
import ProjectFormDialog from './components/ProjectFormDialog';
import ProjectsCards from './components/ProjectsCards';
import ProjectsFilters, { type ProjectsViewMode } from './components/ProjectsFilters';
import ProjectsTable from './components/ProjectsTable';
import useProjectMutations from './hooks/useProjectMutations';
import useProjectsList from './hooks/useProjectsList';

const initialViewMode = (): ProjectsViewMode => (readPreference('projects-view') === 'cards' ? 'cards' : 'table');

export default function ProjectsPage() {
  const { can } = useAuth();
  const { filters, query, updateFilter, handleSortChange } = useProjectsList();
  // /users/options needs users.view; without it the member filter is simply hidden
  const members = useUserOptions({ enabled: can('users.view') });
  const mutations = useProjectMutations();
  // Tables don't fit a phone; cards do
  const isMobile = useIsMobile();

  const [viewMode, setViewModeState] = useState<ProjectsViewMode>(initialViewMode);
  const [searchInput, setSearchInput] = useState(filters.currentSearch);

  const [formModalOpen, setFormModalOpen] = useState(false);
  const [selectedProject, setSelectedProject] = useState<Project | null>(null);
  const [projectToDelete, setProjectToDelete] = useState<Project | null>(null);
  const [projectToArchive, setProjectToArchive] = useState<Project | null>(null);

  useEffect(() => {
    const timer = setTimeout(() => {
      if (searchInput !== filters.currentSearch) updateFilter('search', searchInput);
    }, 400);
    return () => clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps -- only react to typing; URL changes would push a stale input back
  }, [searchInput]);

  const setViewMode = (mode: ProjectsViewMode) => {
    setViewModeState(mode);
    writePreference('projects-view', mode);
  };

  const handleOpenCreate = () => {
    setSelectedProject(null);
    setFormModalOpen(true);
  };

  const withoutBubbling = (open: (project: Project) => void) => (project: Project, e?: MouseEvent) => {
    e?.stopPropagation();
    open(project);
  };

  const actions = {
    onEdit: withoutBubbling((project) => {
      setSelectedProject(project);
      setFormModalOpen(true);
    }),
    onArchive: withoutBubbling(setProjectToArchive),
    onDelete: withoutBubbling(setProjectToDelete),
    onExtend: mutations.extend,
  };

  const projects = query.data?.items ?? [];
  const archiveVerb = projectToArchive?.status === 'archived' ? 'Unarchive' : 'Archive';

  return (
    <div className="space-y-8">
      <PageHeader
        eyebrow="Workspace"
        title="Projects"
        description="Manage, track, and collaborate on your team projects."
        actions={
          can('projects.create') && (
            <Button variant="brand" onClick={handleOpenCreate}>
              <PlusIcon />
              New project
            </Button>
          )
        }
      />

      <div className="space-y-4">
        <ProjectsFilters
          searchInput={searchInput}
          onSearchChange={setSearchInput}
          currentStatus={filters.currentStatus}
          currentMember={filters.currentMember}
          currentSort={filters.currentSort}
          currentDirection={filters.currentDirection}
          members={members.data ?? []}
          viewMode={viewMode}
          onViewModeChange={setViewMode}
          onFilterChange={updateFilter}
        />

        {query.isError ? (
          <ErrorState title="Failed to load projects" message={query.error} onRetry={() => query.refetch()} />
        ) : viewMode === 'table' && !isMobile ? (
          <ProjectsTable
            projects={projects}
            isLoading={query.isPending}
            currentSort={filters.currentSort}
            currentDirection={filters.currentDirection}
            onSortChange={handleSortChange}
            {...actions}
          />
        ) : (
          <ProjectsCards projects={projects} isLoading={query.isPending} {...actions} />
        )}

        {query.isSuccess && (
          <Pagination
            page={query.data.currentPage}
            lastPage={query.data.lastPage}
            total={query.data.total}
            itemLabel="projects"
            onPageChange={(page) => updateFilter('page', String(page))}
          />
        )}
      </div>

      <ProjectFormDialog open={formModalOpen} onOpenChange={setFormModalOpen} project={selectedProject} />

      <ConfirmModal
        isOpen={projectToDelete !== null}
        onClose={() => setProjectToDelete(null)}
        onConfirm={() =>
          projectToDelete && mutations.remove.mutate(projectToDelete, { onSuccess: () => setProjectToDelete(null) })
        }
        title="Delete Project"
        message={`Are you sure you want to delete "${projectToDelete?.title}"? This action cannot be undone.`}
        confirmText="Delete Project"
        danger
        loading={mutations.remove.isPending}
      />

      <ConfirmModal
        isOpen={projectToArchive !== null}
        onClose={() => setProjectToArchive(null)}
        onConfirm={() =>
          projectToArchive && mutations.archive.mutate(projectToArchive, { onSuccess: () => setProjectToArchive(null) })
        }
        title={`${archiveVerb} Project`}
        message={`Are you sure you want to ${archiveVerb.toLowerCase()} "${projectToArchive?.title}"?`}
        confirmText={`${archiveVerb} Project`}
        loading={mutations.archive.isPending}
      />
    </div>
  );
}

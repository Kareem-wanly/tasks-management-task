import { Fragment } from 'react';
import { Link, useLocation } from 'react-router-dom';
import ProjectMark from '@/components/data/ProjectMark';
import useProject from '@/hooks/queries/useProject';
import useTask from '@/hooks/queries/useTask';
import { sectionLabel } from './navItems';

interface Crumb {
  label: string;
  to?: string;
  projectId?: number;
}

/**
 * "Projects / Website redesign" or "Projects / Website redesign / Fix login". Names come from
 * the same queries the page uses, so they appear as soon as the page has loaded.
 */
export default function TopBarBreadcrumb() {
  const { pathname } = useLocation();
  const [section = '', id] = pathname.split('/').filter(Boolean);
  const project = useProject(section === 'projects' ? id : null);
  const task = useTask(section === 'tasks' ? id : null);

  const crumbs: Crumb[] = [{ label: sectionLabel(pathname), to: id ? `/${section}` : undefined }];
  if (section === 'projects' && id) {
    crumbs.push({ label: project.data?.title ?? '…', projectId: project.data?.id });
  }
  if (section === 'tasks' && id) {
    const parent = task.data?.project;
    if (parent) {
      crumbs[0] = { label: 'Projects', to: '/projects' };
      crumbs.push({ label: parent.title, to: `/projects/${parent.id}?tab=tasks`, projectId: parent.id });
    }
    crumbs.push({ label: task.data?.title ?? '…' });
  }
  if (section === 'roles' && id) crumbs.push({ label: id === 'create' ? 'New role' : 'Edit role' });

  return (
    <nav aria-label="Breadcrumb" className="flex min-w-0 items-center gap-1.5 text-[13px]">
      {crumbs.map((crumb, index) => {
        const last = index === crumbs.length - 1;
        const content = (
          <>
            {crumb.projectId !== undefined && <ProjectMark projectId={crumb.projectId} size="xs" />}
            <span className="truncate">{crumb.label}</span>
          </>
        );
        return (
          <Fragment key={`${index}-${crumb.label}`}>
            {index > 0 && (
              <span aria-hidden="true" className="text-muted-foreground/60">
                /
              </span>
            )}
            {crumb.to && !last ? (
              <Link
                to={crumb.to}
                className="flex min-w-0 items-center gap-1.5 text-muted-foreground transition-colors hover:text-foreground"
              >
                {content}
              </Link>
            ) : (
              <span
                aria-current={last ? 'page' : undefined}
                className="flex min-w-0 max-w-64 items-center gap-1.5 font-medium"
              >
                {content}
              </span>
            )}
          </Fragment>
        );
      })}
    </nav>
  );
}

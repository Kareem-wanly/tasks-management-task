import { HistoryIcon } from 'lucide-react';
import UserAvatar from '@/components/data/UserAvatar';
import Panel from '@/components/layout/Panel';
import EmptyState from '@/components/data/EmptyState';
import Pagination from '@/components/data/Pagination';
import { Skeleton } from '@/components/ui/skeleton';
import { formatDateTime } from '@/utils/format';
import type { ProjectActivities } from '../hooks/useProjectActivities';

interface ProjectActivityTabProps {
  activities: ProjectActivities;
}

export default function ProjectActivityTab({ activities }: ProjectActivityTabProps) {
  const { query, page, setPage } = activities;
  const items = query.data?.items ?? [];

  return (
    <Panel title="Activity" description="Recent changes to this project.">
      {query.isPending ? (
        <div className="space-y-5">
          {Array.from({ length: 4 }, (_, i) => (
            <div key={i} className="flex gap-3">
              <Skeleton className="size-8 rounded-lg" />
              <div className="flex-1 space-y-2">
                <Skeleton className="h-3.5 w-40" />
                <Skeleton className="h-3.5 w-full max-w-sm" />
              </div>
            </div>
          ))}
        </div>
      ) : query.isError ? (
        <p role="alert" className="text-sm text-destructive">
          Failed to load project activity.
        </p>
      ) : items.length === 0 ? (
        <EmptyState icon={HistoryIcon} title="No recent activity recorded for this project." className="py-10" />
      ) : (
        <div className="space-y-6">
          <ol className="ms-1 border-s border-border">
            {items.map((act) => (
              <li key={act.id} className="relative flex gap-3 ps-6 pb-6 last:pb-0">
                <span
                  aria-hidden="true"
                  className="absolute -start-[5px] top-3 size-2.5 rounded-full bg-brand ring-4 ring-card"
                />
                <UserAvatar user={{ id: act.user?.id, name: act.user?.name || 'System' }} size="md" />
                <div className="min-w-0 flex-1">
                  <div className="flex flex-wrap items-baseline gap-x-2">
                    <span className="text-sm font-medium">{act.user?.name || 'System'}</span>
                    <time className="text-xs text-muted-foreground tabular-nums">{formatDateTime(act.created_at)}</time>
                  </div>
                  <p className="mt-0.5 text-sm leading-6 text-muted-foreground">
                    {act.description || 'Updated project status'}
                  </p>
                </div>
              </li>
            ))}
          </ol>
          <Pagination
            page={page}
            lastPage={query.data?.lastPage ?? 1}
            total={query.data?.total}
            itemLabel="events"
            onPageChange={setPage}
          />
        </div>
      )}
    </Panel>
  );
}

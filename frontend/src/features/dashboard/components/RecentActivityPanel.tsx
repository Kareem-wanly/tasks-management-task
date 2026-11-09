import { Link } from 'react-router-dom';
import { HistoryIcon } from 'lucide-react';
import EmptyState from '@/components/data/EmptyState';
import UserAvatar from '@/components/data/UserAvatar';
import Panel from '@/components/layout/Panel';
import { Skeleton } from '@/components/ui/skeleton';
import { formatDateTime, formatRelativeTime } from '@/utils/format';
import type { ActivityLog } from '@/types/api';
import useRecentActivity from '../hooks/useRecentActivity';

const LIMIT = 8;

/** Where an entry leads: its task, else its project. */
function linkFor(entry: ActivityLog): string | null {
  if (entry.task_id) return `/tasks/${entry.task_id}`;
  if (entry.project_id) return `/projects/${entry.project_id}`;
  return null;
}

/** Latest changes across the user's projects ("Sara completed Fix login · 5 minutes ago"). */
export default function RecentActivityPanel() {
  const activity = useRecentActivity(LIMIT, true);
  const entries = activity.data?.items ?? [];

  return (
    <Panel flush title="Recent activity" description="Latest changes across your projects">
      {activity.isPending ? (
        <div className="space-y-3 p-4">
          {Array.from({ length: 4 }, (_, i) => (
            <Skeleton key={i} className="h-8 rounded-md" />
          ))}
        </div>
      ) : activity.isError ? (
        <p role="alert" className="p-4 text-[13px] text-destructive">
          Couldn't load recent activity.
        </p>
      ) : entries.length === 0 ? (
        <EmptyState icon={HistoryIcon} title="No activity yet" description="Changes to projects and tasks show up here." className="py-10" />
      ) : (
        <ol className="divide-y divide-border">
          {entries.map((entry) => {
            const to = linkFor(entry);
            const text = entry.description || 'Updated a project';
            return (
              <li key={entry.id} className="flex items-start gap-3 px-4 py-2.5">
                <UserAvatar user={{ id: entry.user?.id, name: entry.user?.name || 'System' }} size="sm" className="mt-0.5" />
                <p className="min-w-0 flex-1 text-[13px] leading-5">
                  <span className="font-medium">{entry.user?.name || 'System'}</span>{' '}
                  {to ? (
                    <Link to={to} className="text-muted-foreground hover:text-brand">
                      {text}
                    </Link>
                  ) : (
                    <span className="text-muted-foreground">{text}</span>
                  )}
                </p>
                <time
                  dateTime={entry.created_at ?? undefined}
                  title={formatDateTime(entry.created_at)}
                  className="shrink-0 text-xs text-muted-foreground tabular-nums"
                >
                  {formatRelativeTime(entry.created_at)}
                </time>
              </li>
            );
          })}
        </ol>
      )}
    </Panel>
  );
}

import type { ReactNode } from 'react';
import { ArchiveIcon, ArchiveRestoreIcon, CheckIcon, PartyPopperIcon, type LucideIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useAuth } from '@/lib/auth/AuthContext';
import { cn } from '@/lib/utils';
import type { Project } from '@/types/api';
import { allTasksDone, isProjectOpen } from '../utils';

interface ProjectBannerProps {
  project: Project;
  onUnarchive: () => void;
  onComplete: () => void;
  pending: boolean;
}

/** One contextual nudge above the tabs: an archived notice, or "all tasks done, close the project?". */
export default function ProjectBanner({ project, onUnarchive, onComplete, pending }: ProjectBannerProps) {
  const { can } = useAuth();

  if (project.status === 'archived') {
    return (
      <Banner icon={ArchiveIcon} tone="neutral" text="This project is archived. Unarchive it to add or change tasks.">
        {can('projects.archive') && (
          <Button size="sm" variant="outline" onClick={onUnarchive} disabled={pending}>
            <ArchiveRestoreIcon />
            Unarchive
          </Button>
        )}
      </Banner>
    );
  }

  if (isProjectOpen(project) && allTasksDone(project)) {
    return (
      <Banner
        icon={PartyPopperIcon}
        tone="success"
        text={`All ${project.tasks_count} tasks are completed. Ready to close this project?`}
      >
        {can('projects.update') && (
          <Button size="sm" onClick={onComplete} disabled={pending}>
            <CheckIcon />
            Mark project completed
          </Button>
        )}
      </Banner>
    );
  }

  return null;
}

interface BannerProps {
  icon: LucideIcon;
  tone: 'neutral' | 'success';
  text: string;
  children: ReactNode;
}

function Banner({ icon: Icon, tone, text, children }: BannerProps) {
  return (
    <div
      role="status"
      className={cn(
        'flex flex-wrap items-center gap-3 rounded-xl border px-4 py-3 text-sm',
        tone === 'success' ? 'border-success/30 bg-success-soft text-success' : 'bg-muted/60 text-muted-foreground'
      )}
    >
      <Icon className="size-4 shrink-0" />
      <p className="flex-1">{text}</p>
      {children}
    </div>
  );
}

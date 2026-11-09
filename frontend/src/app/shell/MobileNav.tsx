import { NavLink } from 'react-router-dom';
import { FolderKanbanIcon, LayoutGridIcon, ListChecksIcon, PlusIcon, SearchIcon, type LucideIcon } from 'lucide-react';
import { emitAppEvent } from '@/lib/appEvents';
import { useAuth } from '@/lib/auth/AuthContext';
import { cn } from '@/lib/utils';

const LINKS: { to: string; label: string; icon: LucideIcon; end?: boolean }[] = [
  { to: '/', label: 'Home', icon: LayoutGridIcon, end: true },
  { to: '/projects', label: 'Projects', icon: FolderKanbanIcon },
  { to: '/tasks', label: 'Tasks', icon: ListChecksIcon },
];

const item = 'flex flex-1 flex-col items-center gap-0.5 py-2 text-[10px] font-medium';

/** Thumb-reach navigation on phones; the sidebar stays behind the menu button. */
export default function MobileNav() {
  const { can } = useAuth();

  return (
    <nav
      aria-label="Quick navigation"
      className="fixed inset-x-0 bottom-0 z-30 flex border-t border-border bg-card/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden"
    >
      {LINKS.map(({ to, label, icon: Icon, end }) => (
        <NavLink
          key={to}
          to={to}
          end={end}
          className={({ isActive }) => cn(item, isActive ? 'text-brand' : 'text-muted-foreground')}
        >
          <Icon className="size-5" />
          {label}
        </NavLink>
      ))}
      <button type="button" onClick={() => emitAppEvent('open-command-palette')} className={cn(item, 'text-muted-foreground')}>
        <SearchIcon className="size-5" />
        Search
      </button>
      {can('tasks.create') && (
        <button type="button" onClick={() => emitAppEvent('new-task')} className={cn(item, 'text-muted-foreground')}>
          <span className="grid size-5 place-items-center rounded-md bg-brand text-brand-foreground">
            <PlusIcon className="size-3.5" />
          </span>
          New task
        </button>
      )}
    </nav>
  );
}

import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { KeyboardIcon, LanguagesIcon, Loader2Icon, LogOutIcon, MoonIcon, PlusIcon, SunIcon, UserRoundIcon } from 'lucide-react';
import ProjectMark from '@/components/data/ProjectMark';
import { NAV_GROUPS } from '@/components/layout/navItems';
import {
  Command,
  CommandDialog,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
  CommandSeparator,
  CommandShortcut,
} from '@/components/ui/command';
import { PROJECT_STATUS_LABELS, TASK_STATUS_LABELS } from '@/constants/statuses';
import { emitAppEvent } from '@/lib/appEvents';
import { useAuth } from '@/lib/auth/AuthContext';
import { LANGUAGES, getLanguage, setLanguage, t } from '@/lib/i18n';
import { useTheme } from '@/lib/preferences/useTheme';
import useCommandSearch from './useCommandSearch';

interface CommandPaletteProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

// Without a search term the palette shows a short list instead of every project
const IDLE_PROJECTS = 5;

/** Ctrl/⌘ + K: jump to any page, project or task, or run a command. */
export default function CommandPalette({ open, onOpenChange }: CommandPaletteProps) {
  const navigate = useNavigate();
  const { can, logout } = useAuth();
  const { theme, toggleTheme } = useTheme();
  const [search, setSearch] = useState('');
  const { projects, tasks, searchingTasks } = useCommandSearch(search, open);

  const close = (next: boolean) => {
    onOpenChange(next);
    if (!next) setSearch('');
  };
  const run = (action: () => void) => {
    close(false);
    action();
  };

  const pages = NAV_GROUPS.flatMap((group) => group.items).filter((item) => !item.permission || can(item.permission));
  const shownProjects = search.trim() ? projects : projects.slice(0, IDLE_PROJECTS);

  return (
    <CommandDialog open={open} onOpenChange={close} title="Command palette" description="Search pages, projects and tasks">
      <Command loop>
        <CommandInput value={search} onValueChange={setSearch} placeholder="Search or type a command…" />
        <CommandList>
          <CommandEmpty>{searchingTasks ? 'Searching…' : 'No results.'}</CommandEmpty>

          <CommandGroup heading="Actions">
            {can('tasks.create') && (
              <CommandItem value="new task create" onSelect={() => run(() => emitAppEvent('new-task'))}>
                <PlusIcon />
                New task
                <CommandShortcut>N</CommandShortcut>
              </CommandItem>
            )}
            <CommandItem value="toggle theme dark light mode" onSelect={() => run(() => toggleTheme())}>
              {theme === 'dark' ? <SunIcon /> : <MoonIcon />}
              Switch to {theme === 'dark' ? 'light' : 'dark'} theme
            </CommandItem>
            {LANGUAGES.filter((l) => l.value !== getLanguage()).map((language) => (
              <CommandItem
                key={language.value}
                value={`language ${language.label} ${language.value}`}
                onSelect={() => run(() => setLanguage(language.value))}
              >
                <LanguagesIcon />
                {t('shell.switchLanguage', { language: language.label })}
              </CommandItem>
            ))}
            <CommandItem value="keyboard shortcuts help" onSelect={() => run(() => emitAppEvent('open-shortcuts'))}>
              <KeyboardIcon />
              Keyboard shortcuts
              <CommandShortcut>?</CommandShortcut>
            </CommandItem>
          </CommandGroup>

          <CommandGroup heading="Go to">
            {pages.map(({ to, label, icon: Icon }) => (
              <CommandItem key={to} value={`go ${label}`} onSelect={() => run(() => navigate(to))}>
                <Icon />
                {label}
              </CommandItem>
            ))}
            <CommandItem value="go profile password account" onSelect={() => run(() => navigate('/profile'))}>
              <UserRoundIcon />
              Profile & password
            </CommandItem>
          </CommandGroup>

          {shownProjects.length > 0 && (
            <CommandGroup heading="Projects">
              {shownProjects.map((project) => (
                <CommandItem
                  key={project.id}
                  value={`project ${project.id} ${project.title}`}
                  onSelect={() => run(() => navigate(`/projects/${project.id}`))}
                >
                  <ProjectMark projectId={project.id} size="sm" />
                  <span className="truncate">{project.title}</span>
                  <CommandShortcut className="tracking-normal">{PROJECT_STATUS_LABELS[project.status]}</CommandShortcut>
                </CommandItem>
              ))}
            </CommandGroup>
          )}

          {(tasks.length > 0 || searchingTasks) && (
            <CommandGroup heading="Tasks">
              {searchingTasks && tasks.length === 0 && (
                <div className="flex items-center gap-2 px-2 py-1.5 text-[13px] text-muted-foreground">
                  <Loader2Icon className="size-4 animate-spin" />
                  Searching tasks…
                </div>
              )}
              {tasks.map((task) => (
                <CommandItem
                  key={task.id}
                  // The server already matched these; adding the term keeps cmdk's own filter from hiding them
                  value={`task ${task.id} ${task.title} ${search}`}
                  onSelect={() => run(() => navigate(`/tasks/${task.id}`))}
                >
                  <span className="w-10 shrink-0 font-mono text-[11px] text-muted-foreground">#{task.id}</span>
                  <span className="truncate">{task.title}</span>
                  <CommandShortcut className="tracking-normal">{TASK_STATUS_LABELS[task.status]}</CommandShortcut>
                </CommandItem>
              ))}
            </CommandGroup>
          )}

          <CommandSeparator />
          <CommandGroup>
            <CommandItem value="sign out log out" onSelect={() => run(() => void logout())}>
              <LogOutIcon />
              Sign out
            </CommandItem>
          </CommandGroup>
        </CommandList>
      </Command>
    </CommandDialog>
  );
}

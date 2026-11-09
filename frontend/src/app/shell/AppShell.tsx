import { lazy, Suspense, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import AppLayout from '@/components/layout/AppLayout';
import useHotkeys from '@/hooks/useHotkeys';
import { emitAppEvent, useAppEvent } from '@/lib/appEvents';
import { useAuth } from '@/lib/auth/AuthContext';
import MobileNav from './MobileNav';
import ShortcutsDialog from './ShortcutsDialog';

// Loaded on first use, so the palette and the task form (with its editors) stay out of the startup bundle
const CommandPalette = lazy(() => import('./CommandPalette'));
const TaskFormDialog = lazy(() => import('@/features/tasks').then((m) => ({ default: m.TaskFormDialog })));

/** Focuses the page's own search box (marked with data-page-search), or opens the palette. */
function focusPageSearch(): void {
  const input = document.querySelector<HTMLInputElement>('[data-page-search]');
  if (input) {
    input.focus();
    input.select();
  } else {
    emitAppEvent('open-command-palette');
  }
}

/** The signed-in layout plus app-wide tools: command palette, shortcuts, quick "New task", mobile nav. */
export default function AppShell() {
  const navigate = useNavigate();
  const { pathname } = useLocation();
  const { can } = useAuth();
  const [paletteOpen, setPaletteOpen] = useState(false);
  const [shortcutsOpen, setShortcutsOpen] = useState(false);
  const [newTaskOpen, setNewTaskOpen] = useState(false);
  // Stay mounted after the first open so closing can animate
  const [paletteUsed, setPaletteUsed] = useState(false);
  const [newTaskUsed, setNewTaskUsed] = useState(false);

  const togglePalette = (open?: boolean) => {
    setPaletteUsed(true);
    setPaletteOpen((current) => open ?? !current);
  };

  // On a project page, a new task goes into that project
  const [section, id] = pathname.split('/').filter(Boolean);
  const currentProjectId = section === 'projects' && id ? Number(id) || null : null;

  const openNewTask = () => {
    if (!can('tasks.create')) return;
    setNewTaskUsed(true);
    setNewTaskOpen(true);
  };

  useAppEvent('open-command-palette', () => togglePalette(true));
  useAppEvent('open-shortcuts', () => setShortcutsOpen(true));
  useAppEvent('new-task', openNewTask);

  useHotkeys({
    'mod+k': () => togglePalette(),
    '/': focusPageSearch,
    n: openNewTask,
    '?': () => setShortcutsOpen(true),
    'g d': () => navigate('/'),
    'g p': () => navigate('/projects'),
    'g t': () => navigate('/tasks'),
    'g o': () => navigate('/profile'),
  });

  return (
    <AppLayout
      bottomNav={<MobileNav />}
      overlays={
        <>
          <Suspense fallback={null}>
            {paletteUsed && <CommandPalette open={paletteOpen} onOpenChange={setPaletteOpen} />}
            {newTaskUsed && (
              <TaskFormDialog open={newTaskOpen} onOpenChange={setNewTaskOpen} projectId={currentProjectId} />
            )}
          </Suspense>
          <ShortcutsDialog open={shortcutsOpen} onOpenChange={setShortcutsOpen} />
        </>
      }
    />
  );
}

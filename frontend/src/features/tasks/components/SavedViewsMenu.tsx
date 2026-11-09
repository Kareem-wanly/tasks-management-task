import { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { BookmarkIcon, BookmarkPlusIcon, XIcon } from 'lucide-react';
import TextField from '@/components/form/TextField';
import type { TasksView } from '@/components/data/ViewToggle';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import FormDialog from '@/components/ui/FormDialog';
import { showToast } from '@/lib/toast/ToastContext';
import {
  addSavedView,
  removeSavedView,
  savedViewsStore,
  saveViewSchema,
  type SavedView,
  type SaveViewFormValues,
} from '../savedViews';

interface SavedViewsMenuProps {
  /** The current filters and sort, as a query string. */
  currentQuery: string;
  currentView: TasksView;
  onApply: (view: SavedView) => void;
}

/** Save the current filters as a named view, and switch between saved views. */
export default function SavedViewsMenu({ currentQuery, currentView, onApply }: SavedViewsMenuProps) {
  const views = savedViewsStore.useValue();
  const [saveOpen, setSaveOpen] = useState(false);
  const form = useForm<SaveViewFormValues>({ resolver: zodResolver(saveViewSchema), defaultValues: { name: '' } });
  const active = views.find((v) => v.query === currentQuery && v.view === currentView);

  useEffect(() => {
    if (saveOpen) form.reset({ name: '' });
  }, [saveOpen, form]);

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="outline" size="sm">
            <BookmarkIcon />
            {active ? active.name : 'Views'}
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" className="w-60">
          <DropdownMenuLabel>Saved views</DropdownMenuLabel>
          {views.length === 0 && (
            <p className="px-3 pb-2 text-xs text-muted-foreground">Save filters you use often to reapply them in one click.</p>
          )}
          {views.map((view) => (
            <DropdownMenuItem key={view.id} onSelect={() => onApply(view)} className="pe-1">
              <span className="min-w-0 flex-1 truncate">{view.name}</span>
              <Button
                variant="ghost"
                size="icon-xs"
                aria-label={`Delete view ${view.name}`}
                onPointerDown={(e) => e.stopPropagation()}
                onClick={(e) => {
                  e.preventDefault();
                  e.stopPropagation();
                  removeSavedView(view.id);
                }}
              >
                <XIcon />
              </Button>
            </DropdownMenuItem>
          ))}
          <DropdownMenuSeparator />
          <DropdownMenuItem onSelect={() => setSaveOpen(true)}>
            <BookmarkPlusIcon />
            Save current view…
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <FormDialog
        open={saveOpen}
        onOpenChange={setSaveOpen}
        title="Save view"
        description="Keeps the current filters, sort and layout under a name."
        submitLabel="Save view"
        onSubmit={form.handleSubmit(({ name }) => {
          addSavedView(name, currentQuery, currentView);
          showToast(`View "${name}" saved.`, 'success');
          setSaveOpen(false);
        })}
      >
        <TextField control={form.control} name="name" label="Name" placeholder="e.g. My urgent work" autoFocus />
      </FormDialog>
    </>
  );
}

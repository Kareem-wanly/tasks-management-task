import { useEffect, useState, type FormEvent, type ReactNode } from 'react';
import { Loader2Icon } from 'lucide-react';
import { modKeyLabel } from '@/lib/platform';
import { cn } from '@/lib/utils';
import { Button } from './button';
import ConfirmModal from './ConfirmModal';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from './dialog';

interface FormDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title: ReactNode;
  description?: ReactNode;
  /** Usually `form.handleSubmit(onValid)`. */
  onSubmit: (event: FormEvent<HTMLFormElement>) => void;
  submitLabel?: string;
  submitting?: boolean;
  /** Usually `form.formState.isDirty`: closing then asks before throwing the edits away. */
  isDirty?: boolean;
  /** Tailwind max-width for wider forms, e.g. 'sm:max-w-lg'. */
  className?: string;
  children: ReactNode;
}

/**
 * A dialog containing a form, in three bands: a header, a scrolling body for the fields,
 * and a footer with Cancel / Submit (Ctrl/⌘ + Enter submits from anywhere in the form).
 */
export default function FormDialog({
  open,
  onOpenChange,
  title,
  description,
  onSubmit,
  submitLabel = 'Save',
  submitting = false,
  isDirty = false,
  className = 'sm:max-w-md',
  children,
}: FormDialogProps) {
  const [confirmDiscard, setConfirmDiscard] = useState(false);

  // Leaving the page (reload, closing the tab) would lose the edits too
  useEffect(() => {
    if (!open || !isDirty) return;
    const onBeforeUnload = (event: BeforeUnloadEvent) => event.preventDefault();
    window.addEventListener('beforeunload', onBeforeUnload);
    return () => window.removeEventListener('beforeunload', onBeforeUnload);
  }, [open, isDirty]);

  const requestClose = () => {
    if (submitting) return;
    if (isDirty) setConfirmDiscard(true);
    else onOpenChange(false);
  };

  return (
    <>
      <Dialog open={open} onOpenChange={(next) => (next ? onOpenChange(true) : requestClose())}>
        <DialogContent className={cn('gap-0 overflow-hidden p-0', className)}>
          <form
            onSubmit={onSubmit}
            onKeyDown={(e) => {
              if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) e.currentTarget.requestSubmit();
            }}
            noValidate
            className="flex max-h-[min(85svh,48rem)] flex-col"
          >
            <DialogHeader className="gap-1 border-b border-border px-5 py-4 pe-12">
              <DialogTitle>{title}</DialogTitle>
              {description && <DialogDescription className="text-[13px]">{description}</DialogDescription>}
            </DialogHeader>

            <div className="grid gap-4 overflow-y-auto px-5 py-4">{children}</div>

            <div className="flex flex-col-reverse items-center gap-2 border-t border-border bg-muted/50 px-5 py-3 sm:flex-row">
              <span className="hidden text-xs text-muted-foreground sm:block">
                <kbd className="rounded-sm border border-border bg-card px-1 font-mono text-[10px]">{modKeyLabel()}</kbd>
                {' + '}
                <kbd className="rounded-sm border border-border bg-card px-1 font-mono text-[10px]">Enter</kbd> to save
              </span>
              <div className="flex w-full flex-col-reverse gap-2 sm:ms-auto sm:w-auto sm:flex-row">
                <Button type="button" variant="outline" disabled={submitting} onClick={requestClose}>
                  Cancel
                </Button>
                <Button type="submit" variant="brand" disabled={submitting}>
                  {submitting && <Loader2Icon className="animate-spin" />}
                  {submitLabel}
                </Button>
              </div>
            </div>
          </form>
        </DialogContent>
      </Dialog>

      <ConfirmModal
        isOpen={confirmDiscard}
        onClose={() => setConfirmDiscard(false)}
        onConfirm={() => {
          setConfirmDiscard(false);
          onOpenChange(false);
        }}
        title="Discard changes?"
        message="Your edits in this form haven't been saved."
        confirmText="Discard"
        danger
      />
    </>
  );
}

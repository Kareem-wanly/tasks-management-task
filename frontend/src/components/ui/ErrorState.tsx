import type { ReactNode } from 'react';
import { RotateCwIcon, TriangleAlertIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from './button';

interface ErrorStateProps {
  title?: string;
  message?: string | Error | null;
  /** Shows a "Try again" button. */
  onRetry?: (() => void) | null;
  retryText?: string;
  icon?: ReactNode;
  action?: ReactNode;
  className?: string;
}

/** Inline error for a failed page or section load, with an optional retry. */
export default function ErrorState({
  title = 'Failed to load content',
  message = 'An unexpected error occurred while loading this section. Please try again.',
  onRetry = null,
  retryText = 'Try again',
  icon,
  action = null,
  className,
}: ErrorStateProps) {
  const displayMessage = message instanceof Error ? message.message || 'An unexpected error occurred.' : message;

  return (
    <div role="alert" className={cn('surface flex flex-col items-center px-6 py-14 text-center', className)}>
      <span className="mb-5 grid size-12 place-items-center rounded-2xl bg-danger-soft text-danger" aria-hidden="true">
        {icon ?? <TriangleAlertIcon className="size-5" />}
      </span>
      <h3 className="font-medium">{title}</h3>
      {displayMessage && <p className="mt-1.5 max-w-md text-sm leading-6 text-muted-foreground">{displayMessage}</p>}

      {(onRetry || action) && (
        <div className="mt-5 flex flex-wrap justify-center gap-2">
          {onRetry && (
            <Button type="button" variant="outline" onClick={onRetry}>
              <RotateCwIcon />
              {retryText}
            </Button>
          )}
          {action}
        </div>
      )}
    </div>
  );
}

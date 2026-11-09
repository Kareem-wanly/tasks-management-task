import { cn } from '@/lib/utils';

interface LoadingSpinnerProps {
  size?: 'sm' | 'md' | 'lg';
  text?: string;
  /** Covers the whole viewport (route-level loading). */
  fullPage?: boolean;
  center?: boolean;
  className?: string;
}

const SIZES: Record<NonNullable<LoadingSpinnerProps['size']>, string> = {
  sm: 'size-4 border-[1.5px]',
  md: 'size-7 border-2',
  lg: 'size-10 border-2',
};

export default function LoadingSpinner({
  size = 'md',
  text = '',
  fullPage = false,
  center = true,
  className,
}: LoadingSpinnerProps) {
  return (
    <div
      role="status"
      aria-live="polite"
      className={cn(
        'flex flex-col items-center gap-3 text-sm text-muted-foreground',
        fullPage && 'fixed inset-0 z-50 justify-center bg-background',
        center && !fullPage && 'justify-center py-16',
        className,
      )}
    >
      <span className={cn('animate-spin rounded-full border-border border-t-brand', SIZES[size])} />
      {text && <p>{text}</p>}
      <span className="sr-only">Loading...</span>
    </div>
  );
}

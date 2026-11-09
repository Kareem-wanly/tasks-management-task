import { cn } from '@/lib/utils';

interface BrandMarkProps {
  className?: string;
  letter?: string;
}

/** Signal-blue square with the initial: the app's logo mark. */
export default function BrandMark({ className, letter = 'T' }: BrandMarkProps) {
  return (
    <span
      aria-hidden="true"
      className={cn(
        'grid size-8 shrink-0 place-items-center rounded-lg bg-brand',
        className,
      )}
    >
      <span className="text-[15px] leading-none font-bold text-brand-foreground">{letter}</span>
    </span>
  );
}

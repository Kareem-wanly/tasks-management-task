import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface PaginationProps {
  page: number;
  lastPage: number;
  total?: number;
  /** Shown after the total, e.g. 'users'. */
  itemLabel?: string;
  onPageChange: (page: number) => void;
}

/** Previous / Next pager for `Page<T>` results. Renders nothing when there is one page. */
export default function Pagination({ page, lastPage, total, itemLabel = 'items', onPageChange }: PaginationProps) {
  if (lastPage <= 1) return null;

  return (
    <div className="flex flex-wrap items-center justify-between gap-4 pt-1 text-sm text-muted-foreground">
      <span className="tabular-nums">
        Page <span className="font-medium text-foreground">{page}</span> of{' '}
        <span className="font-medium text-foreground">{lastPage}</span>
        {total !== undefined && <span className="ms-1.5">· {total} {itemLabel}</span>}
      </span>
      <div className="flex gap-2">
        <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => onPageChange(page - 1)}>
          <ChevronLeftIcon className="rtl:-scale-x-100" />
          Previous
        </Button>
        <Button variant="outline" size="sm" disabled={page >= lastPage} onClick={() => onPageChange(page + 1)}>
          Next
          <ChevronRightIcon className="rtl:-scale-x-100" />
        </Button>
      </div>
    </div>
  );
}

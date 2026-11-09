import type { Key, ReactNode } from 'react';
import StaggerRow from '@/components/motion/StaggerRow';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { cn } from '@/lib/utils';
import EmptyState from './EmptyState';

export interface DataTableColumn<T> {
  key: string;
  header: ReactNode;
  cell: (row: T) => ReactNode;
  /** Applied to both the header and the cells, e.g. 'text-end w-24'. */
  className?: string;
}

interface DataTableProps<T> {
  columns: DataTableColumn<T>[];
  rows: T[];
  getRowKey: (row: T) => Key;
  isLoading?: boolean;
  emptyMessage?: ReactNode;
  emptyDescription?: ReactNode;
  skeletonRows?: number;
  /** Draws its own surface; set false when the table sits inside a flush `Panel`. */
  framed?: boolean;
  onRowClick?: (row: T) => void;
}

/**
 * Simple typed table: describe columns once, pass rows.
 *
 *   <DataTable columns={columns} rows={users} getRowKey={(u) => u.id} isLoading={isPending} />
 */
export default function DataTable<T>({
  columns,
  rows,
  getRowKey,
  isLoading = false,
  emptyMessage = 'No results found.',
  emptyDescription,
  skeletonRows = 5,
  framed = true,
  onRowClick,
}: DataTableProps<T>) {
  return (
    <div className={cn(framed && 'surface overflow-hidden')}>
      <Table>
        <TableHeader>
          <TableRow>
            {columns.map((column) => (
              <TableHead key={column.key} className={column.className}>
                {column.header}
              </TableHead>
            ))}
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading &&
            Array.from({ length: skeletonRows }, (_, i) => (
              <TableRow key={`skeleton-${i}`}>
                {columns.map((column) => (
                  <TableCell key={column.key} className={column.className}>
                    <Skeleton className="h-4 w-full max-w-40" />
                  </TableCell>
                ))}
              </TableRow>
            ))}

          {!isLoading && rows.length === 0 && (
            <TableRow className="hover:bg-transparent">
              <TableCell colSpan={columns.length} className="p-0">
                <EmptyState title={emptyMessage} description={emptyDescription} className="py-12" />
              </TableCell>
            </TableRow>
          )}

          {!isLoading &&
            rows.map((row, index) => (
              <StaggerRow
                key={getRowKey(row)}
                index={index}
                onClick={onRowClick ? () => onRowClick(row) : undefined}
                className={cn(onRowClick && 'cursor-pointer')}
              >
                {columns.map((column) => (
                  <TableCell key={column.key} className={column.className}>
                    {column.cell(row)}
                  </TableCell>
                ))}
              </StaggerRow>
            ))}
        </TableBody>
      </Table>
    </div>
  );
}

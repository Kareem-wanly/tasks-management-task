import { DownloadIcon, Loader2Icon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import useApiMutation from '@/hooks/useApiMutation';
import type { QueryParams } from '@/lib/paginate';
import { exportTasksCsv } from '../exportTasks';

interface ExportTasksButtonProps {
  /** The current filters and sort, without paging. */
  params: QueryParams;
}

/** Downloads the tasks matching the current filters as a CSV file (opens in Excel). */
export default function ExportTasksButton({ params }: ExportTasksButtonProps) {
  const exporter = useApiMutation({
    mutationFn: (query: QueryParams) => exportTasksCsv(query),
    successMessage: (count) => (count === 0 ? 'No tasks match these filters.' : `Exported ${count} tasks.`),
    errorMessage: 'Failed to export tasks.',
  });

  return (
    <Button
      variant="outline"
      size="sm"
      onClick={() => exporter.mutate(params)}
      disabled={exporter.isPending}
      title="Download the filtered tasks as CSV"
    >
      {exporter.isPending ? <Loader2Icon className="animate-spin" /> : <DownloadIcon />}
      Export
    </Button>
  );
}

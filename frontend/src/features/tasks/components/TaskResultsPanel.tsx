import { CloudOffIcon } from 'lucide-react';
import Panel from '@/components/layout/Panel';
import { Skeleton } from '@/components/ui/skeleton';
import { errorMessage } from '@/lib/apiClient';
import useTaskResults from '../hooks/useTaskResults';
import TaskResultForm from './TaskResultForm';
import TaskResultItem from './TaskResultItem';

interface TaskResultsPanelProps {
  taskId: number;
}

/** The task's official results (Bitrix24 step 4.6): what was achieved, on record. */
export default function TaskResultsPanel({ taskId }: TaskResultsPanelProps) {
  const results = useTaskResults(taskId);
  const { data, isPending, isError, error } = results.query;
  const canAdd = data?.can_add ?? false;

  if (data && data.results.length === 0 && !canAdd) return null;

  return (
    <Panel title="Results" description={data && data.results.length > 0 ? `${data.results.length} recorded` : undefined}>
      {isPending && <Skeleton className="h-16 w-full" />}
      {isError && <p className="text-[13px] text-danger">{errorMessage(error, "Couldn't load the results.")}</p>}
      {data?.stale && (
        <p className="mb-2 flex items-center gap-1.5 text-xs text-muted-foreground">
          <CloudOffIcon className="size-3.5" />
          Bitrix24 is unreachable; this may be out of date.
        </p>
      )}
      {data && data.results.length > 0 && (
        <ul className="mb-3 divide-y divide-border">
          {data.results.map((result) => (
            <TaskResultItem key={result.id} result={result} results={results} />
          ))}
        </ul>
      )}
      {canAdd && (
        <TaskResultForm
          pending={results.isPending}
          submitLabel="Add result"
          onSubmit={(text, reset) => results.add(text, reset)}
        />
      )}
    </Panel>
  );
}

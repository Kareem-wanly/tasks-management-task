import { useRef, useState } from 'react';
import { CloudOffIcon, Loader2Icon, PaperclipIcon } from 'lucide-react';
import Panel from '@/components/layout/Panel';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { errorMessage } from '@/lib/apiClient';
import { showToast } from '@/lib/toast/ToastContext';
import { cn } from '@/lib/utils';
import useTaskFiles from '../hooks/useTaskFiles';
import TaskFileRow from './TaskFileRow';

interface TaskFilesPanelProps {
  taskId: number;
}

/** Files attached to the task (Bitrix24 step 4.5): pick or drop files to attach them. */
export default function TaskFilesPanel({ taskId }: TaskFilesPanelProps) {
  const files = useTaskFiles(taskId);
  const input = useRef<HTMLInputElement>(null);
  const [dragging, setDragging] = useState<boolean>(false);
  const { data, isPending, isError, error } = files.query;
  const canManage = data?.can_manage ?? false;

  if (data && data.files.length === 0 && !canManage) return null;

  const attach = (list: FileList | null) => {
    if (!list || list.length === 0 || !data) return;
    const picked = Array.from(list);
    const tooBig = picked.filter((f) => f.size > data.max_kb * 1024);
    if (tooBig.length > 0) {
      showToast(`${tooBig.map((f) => f.name).join(', ')}: files can be up to ${Math.round(data.max_kb / 1024)} MB.`, 'error');
    }
    const ok = picked.filter((f) => f.size <= data.max_kb * 1024);
    if (ok.length > 0) files.upload(ok);
  };

  const actions = canManage && (
    <>
      <input
        ref={input}
        type="file"
        multiple
        hidden
        onChange={(e) => {
          attach(e.target.files);
          e.target.value = '';
        }}
      />
      <Button type="button" variant="outline" size="sm" disabled={files.isPending} onClick={() => input.current?.click()}>
        {files.isPending ? <Loader2Icon className="animate-spin" /> : <PaperclipIcon />}
        Attach
      </Button>
    </>
  );

  return (
    <Panel
      title="Files"
      description={data && data.files.length > 0 ? `${data.files.length} attached` : undefined}
      actions={actions}
    >
      <div
        onDragOver={(e) => {
          if (!canManage) return;
          e.preventDefault();
          setDragging(true);
        }}
        onDragLeave={() => setDragging(false)}
        onDrop={(e) => {
          if (!canManage) return;
          e.preventDefault();
          setDragging(false);
          attach(e.dataTransfer.files);
        }}
        className={cn('-m-2 rounded-lg p-2 transition-colors', dragging && 'bg-brand/5 ring-2 ring-brand/40')}
      >
        {isPending && <Skeleton className="h-12 w-full" />}
        {isError && <p className="text-[13px] text-danger">{errorMessage(error, "Couldn't load the files.")}</p>}
        {data?.stale && (
          <p className="mb-2 flex items-center gap-1.5 text-xs text-muted-foreground">
            <CloudOffIcon className="size-3.5" />
            Bitrix24 is unreachable; this may be out of date.
          </p>
        )}
        {data && data.files.length === 0 && (
          <p className="py-3 text-center text-[13px] text-muted-foreground">
            Drop files here or use Attach (up to {Math.round(data.max_kb / 1024)} MB each).
          </p>
        )}
        {data && data.files.length > 0 && (
          <ul className="divide-y divide-border">
            {data.files.map((file) => (
              <TaskFileRow key={file.id} file={file} canManage={canManage} files={files} />
            ))}
          </ul>
        )}
      </div>
    </Panel>
  );
}

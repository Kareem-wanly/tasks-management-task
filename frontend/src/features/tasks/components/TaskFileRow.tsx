import {
  FileArchiveIcon,
  FileIcon,
  FileImageIcon,
  FileSpreadsheetIcon,
  FileTextIcon,
  FileVideoIcon,
  XIcon,
  type LucideIcon,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { TaskFile } from '@/types/api';
import { formatFileSize, formatTimestampDate } from '@/utils/format';
import type { TaskFilesControl } from '../hooks/useTaskFiles';

interface TaskFileRowProps {
  file: TaskFile;
  canManage: boolean;
  files: TaskFilesControl;
}

/** An icon for the kind of file. */
function iconFor(mime: string | null): LucideIcon {
  if (!mime) return FileIcon;
  if (mime.startsWith('image/')) return FileImageIcon;
  if (mime.startsWith('video/')) return FileVideoIcon;
  if (mime.includes('spreadsheet') || mime.includes('excel') || mime === 'text/csv') return FileSpreadsheetIcon;
  if (mime.includes('zip') || mime.includes('compressed') || mime.includes('tar')) return FileArchiveIcon;
  if (mime.startsWith('text/') || mime.includes('pdf') || mime.includes('word') || mime.includes('document')) return FileTextIcon;
  return FileIcon;
}

/** One attached file: click the name to download it. */
export default function TaskFileRow({ file, canManage, files }: TaskFileRowProps) {
  const Icon = iconFor(file.mime);

  return (
    <li className="group flex min-w-0 items-center gap-3 py-2 text-[13px]">
      <Icon className="size-5 shrink-0 text-muted-foreground" aria-hidden="true" />
      <div className="min-w-0 flex-1">
        <button
          type="button"
          onClick={() => void files.download(file)}
          title={`Download ${file.name}`}
          className="block max-w-full truncate text-start font-medium transition-colors hover:text-brand"
        >
          {file.name}
        </button>
        <p className="truncate text-xs text-muted-foreground">
          {formatFileSize(file.size)}
          {file.uploader && ` · ${file.uploader.name}`}
          {file.created_at && ` · ${formatTimestampDate(file.created_at)}`}
        </p>
      </div>
      {canManage && (
        <Button
          type="button"
          variant="ghost"
          size="icon-xs"
          disabled={files.isPending}
          onClick={() => files.remove(file)}
          aria-label={`Remove ${file.name} from the task`}
          title="Remove from the task"
          className="opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100"
        >
          <XIcon />
        </Button>
      )}
    </li>
  );
}

import { useState } from 'react';
import { FileIcon, PencilIcon, Trash2Icon } from 'lucide-react';
import UserAvatar from '@/components/data/UserAvatar';
import { Button } from '@/components/ui/button';
import type { TaskChatMessage } from '@/types/api';
import { bbcodeToText, formatFileSize, formatDateTime } from '@/utils/format';
import type { TaskChatControl } from '../hooks/useTaskChat';
import TaskChatForm from './TaskChatForm';

interface TaskChatMessageItemProps {
  message: TaskChatMessage;
  isOwn: boolean;
  chat: TaskChatControl;
  onDelete: () => void;
}

/** One chat message: Bitrix's system lines in grey; people's messages with author, time, text and files. */
export default function TaskChatMessageItem({ message, isOwn, chat, onDelete }: TaskChatMessageItemProps) {
  const [editing, setEditing] = useState<boolean>(false);
  const text = bbcodeToText(message.text);

  if (message.is_system) {
    return (
      <li className="py-1.5 text-center text-xs text-muted-foreground">
        <span className="whitespace-pre-wrap break-words">{text}</span>
        <span className="ms-2 tabular-nums">{formatDateTime(message.created_at)}</span>
      </li>
    );
  }

  return (
    <li className="group flex gap-3 py-2.5 text-[13px]">
      <UserAvatar user={{ id: message.author?.id ?? null, name: message.author?.name ?? 'Unknown' }} size="sm" className="mt-0.5" />
      <div className="min-w-0 flex-1 space-y-1">
        <div className="flex items-center gap-2">
          <span className="font-medium">{message.author?.name ?? 'Unknown'}</span>
          {isOwn && <span className="text-xs text-muted-foreground">(You)</span>}
          <span className="text-xs text-muted-foreground tabular-nums">{formatDateTime(message.created_at)}</span>
          {message.can_edit && !editing && (
            <span className="ms-auto flex opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
              <Button type="button" variant="ghost" size="icon-xs" onClick={() => setEditing(true)} aria-label="Edit message">
                <PencilIcon />
              </Button>
              <Button type="button" variant="ghost" size="icon-xs" disabled={chat.isPending} onClick={onDelete} aria-label="Delete message">
                <Trash2Icon className="text-destructive" />
              </Button>
            </span>
          )}
        </div>
        {editing ? (
          <TaskChatForm
            // The raw text: Bitrix mentions, links and formatting survive the edit.
            initial={message.text}
            pending={chat.isPending}
            onSubmit={(value) => chat.update(message, value, () => setEditing(false))}
            onCancel={() => setEditing(false)}
          />
        ) : (
          text !== '' && <p className="whitespace-pre-wrap break-words">{text}</p>
        )}
        {message.files.length > 0 && (
          <ul className="flex flex-wrap gap-2 pt-1">
            {message.files.map((file) => (
              <li key={file.id}>
                <Button type="button" variant="outline" size="sm" onClick={() => void chat.download(message, file)}>
                  <FileIcon />
                  <span className="max-w-48 truncate">{file.name}</span>
                  <span className="text-muted-foreground">{formatFileSize(file.size)}</span>
                </Button>
              </li>
            ))}
          </ul>
        )}
      </div>
    </li>
  );
}

import { Link } from 'react-router-dom';
import { ArrowLeftIcon, CopyIcon, EllipsisIcon, PencilIcon, Trash2Icon } from 'lucide-react';
import PageHeader from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAuth } from '@/lib/auth/AuthContext';
import type { Task } from '@/types/api';
import InlineTitle from './InlineTitle';
import TaskCompleteButton from './TaskCompleteButton';
import TaskSiblingNav from './TaskSiblingNav';

interface TaskDetailsHeaderProps {
  task: Task;
  onRename: (title: string) => void;
  onEdit: () => void;
  onDuplicate: () => void;
  onDelete: () => void;
}

const crumb = 'transition-colors duration-300 ease-luxe hover:text-brand';

/** Breadcrumb back to the task's project, the title, and the task actions. */
export default function TaskDetailsHeader({ task, onRename, onEdit, onDuplicate, onDelete }: TaskDetailsHeaderProps) {
  const { can } = useAuth();
  const canDuplicate = can('tasks.create') && Boolean(task.project);
  const canDelete = can('tasks.delete');

  const eyebrow = task.project ? (
    <nav aria-label="Breadcrumb" className="flex min-w-0 items-center gap-1.5">
      <Link to="/projects" className={crumb}>
        Projects
      </Link>
      <span aria-hidden="true">/</span>
      <Link to={`/projects/${task.project.id}?tab=tasks`} className={`${crumb} truncate`}>
        {task.project.title}
      </Link>
    </nav>
  ) : (
    <Link to="/tasks" className={`inline-flex items-center gap-1.5 ${crumb}`}>
      <ArrowLeftIcon className="size-3.5 rtl:-scale-x-100" />
      Tasks
    </Link>
  );

  return (
    <PageHeader
      eyebrow={eyebrow}
      title={<InlineTitle title={task.title} canEdit={can('tasks.update')} onSave={onRename} />}
      description={<span className="font-mono text-xs">#{task.id}</span>}
      actions={
        <>
          <TaskSiblingNav projectId={task.project?.id} taskId={task.id} />
          {(can('tasks.change_status') || can('tasks.update')) && <TaskCompleteButton task={task} variant="button" />}
          {can('tasks.update') && (
            <Button variant="outline" onClick={onEdit}>
              <PencilIcon />
              Edit
            </Button>
          )}
          {(canDuplicate || canDelete) && (
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" size="icon" aria-label="More task actions">
                  <EllipsisIcon />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                {canDuplicate && (
                  <DropdownMenuItem onSelect={onDuplicate}>
                    <CopyIcon />
                    Duplicate
                  </DropdownMenuItem>
                )}
                {canDuplicate && canDelete && <DropdownMenuSeparator />}
                {canDelete && (
                  <DropdownMenuItem variant="destructive" onSelect={onDelete}>
                    <Trash2Icon />
                    Delete
                  </DropdownMenuItem>
                )}
              </DropdownMenuContent>
            </DropdownMenu>
          )}
        </>
      }
    />
  );
}

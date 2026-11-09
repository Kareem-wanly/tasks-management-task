export { default as TasksPage } from './TasksPage';
export { default as TaskDetailsPage } from './TaskDetailsPage';

// Used by projects: create a task from the project page, and list or board the project's tasks
export { default as TaskFormDialog } from './components/TaskFormDialog';
export { default as TasksTable } from './components/TasksTable';
export { default as TasksBoard } from './components/TasksBoard';
export { default as useTasksBoard, type TasksBoardData } from './hooks/useTasksBoard';
export { default as TasksCalendar } from './components/TasksCalendar';
export { default as useTasksCalendar, type TasksCalendarData } from './hooks/useTasksCalendar';
export { default as TasksBulkBar } from './components/TasksBulkBar';

// Used by the dashboard's "My work" list
export { default as TaskCompleteButton } from './components/TaskCompleteButton';
export { default as PriorityIcon } from './components/PriorityIcon';

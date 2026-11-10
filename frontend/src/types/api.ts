// Shapes returned by the Laravel API. Keep in sync with backend/app/Http/Resources and the controllers.

export type TaskStatus = 'todo' | 'in_progress' | 'review' | 'completed';
export type TaskPriority = 'low' | 'medium' | 'high' | 'urgent';
export type ProjectStatus = 'active' | 'in_progress' | 'on_hold' | 'completed' | 'archived';

/** Normalized paginated list, built by `toPage` from either a Resource collection or a raw paginator. */
export interface Page<T> {
  items: T[];
  currentPage: number;
  lastPage: number;
  perPage: number;
  total: number;
}

/** Raw list bodies as the API sends them, before `toPage`. */
export interface PaginatedBody<T> {
  data: T[];
  meta?: PaginationMeta;
  current_page?: number;
  last_page?: number;
  per_page?: number;
  total?: number;
}

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface DataBody<T> {
  data: T;
  message?: string;
}

/** UserResource: /me, /login, /register, and users nested in projects and tasks. */
export interface User {
  id: number;
  name: string;
  email: string;
  /** Profile photo and job title from Bitrix24; null for local-only users. */
  avatar_url?: string | null;
  position?: string | null;
  /** Role names; only present when roles were loaded. */
  roles?: string[];
  /** Only on /me, /login and /register. */
  effective_permissions?: string[];
  created_at?: string;
  updated_at?: string;
}

export interface RoleRef {
  id: number;
  name: string;
  display_name: string | null;
}

/** GET /users returns models, not UserResource, so roles are objects here. */
export interface UserListItem {
  id: number;
  name: string;
  email: string;
  roles: RoleRef[];
  created_at: string | null;
}

export interface UserOption {
  id: number;
  name: string;
  email: string;
}

export interface Project {
  id: number;
  title: string;
  description: string | null;
  status: ProjectStatus;
  start_date: string | null;
  due_date: string | null;
  owner?: User;
  members?: User[];
  members_count?: number;
  tasks_count?: number;
  completed_tasks_count?: number;
  in_progress_tasks_count?: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface ProjectOption {
  id: number;
  title: string;
  status: ProjectStatus;
}

export interface Comment {
  id: number;
  body: string;
  task_id: number;
  user: { id: number | null; name: string | null; email: string | null };
  created_at: string | null;
  updated_at: string | null;
}

export interface Task {
  id: number;
  title: string;
  description: string | null;
  status: TaskStatus;
  priority: TaskPriority;
  due_date: string | null;
  is_overdue: boolean;
  completed_at: string | null;
  project?: Pick<Project, 'id' | 'title'> & Partial<Project>;
  assignee?: User | null;
  creator?: User | null;
  comments?: Comment[];
  created_at: string | null;
  updated_at: string | null;
}

export interface Permission {
  id: number;
  name: string;
  display_name?: string | null;
  description: string | null;
}

export interface Role {
  id: number;
  name: string;
  display_name: string | null;
  description: string | null;
  permissions?: Permission[];
  permissions_count?: number;
  users_count?: number;
}

export interface ActivityLog {
  id: number;
  description: string | null;
  action?: string;
  /** What the entry is about, for linking to it. */
  project_id?: number | null;
  task_id?: number | null;
  user?: UserOption | null;
  created_at: string | null;
}

export interface AuthResponse {
  message?: string;
  access_token: string;
  token_type: string;
  user: User;
}

/** Laravel 422 body: field name → messages. */
export type ValidationErrors = Record<string, string[]>;

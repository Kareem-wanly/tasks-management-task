import { FolderKanbanIcon, LayoutGridIcon, ListChecksIcon, ShieldCheckIcon, UsersIcon, type LucideIcon } from 'lucide-react';

export interface NavItem {
  to: string;
  label: string;
  icon: LucideIcon;
  /** Permission needed to see the link; omitted means every signed-in user. */
  permission?: string;
  end?: boolean;
}

export interface NavGroup {
  label: string;
  items: NavItem[];
}

export const NAV_GROUPS: NavGroup[] = [
  {
    label: 'Overview',
    items: [
      { to: '/', label: 'Dashboard', icon: LayoutGridIcon, end: true },
      { to: '/projects', label: 'Projects', icon: FolderKanbanIcon },
      { to: '/tasks', label: 'Tasks', icon: ListChecksIcon },
    ],
  },
  {
    label: 'Management',
    items: [
      { to: '/users', label: 'Users', icon: UsersIcon, permission: 'users.view' },
      { to: '/roles', label: 'Roles', icon: ShieldCheckIcon, permission: 'roles.view' },
    ],
  },
];

/** Label of the nav section that owns a path, for the top bar breadcrumb. */
export function sectionLabel(pathname: string): string {
  const first = `/${pathname.split('/')[1] ?? ''}`;
  for (const group of NAV_GROUPS) {
    const match = group.items.find((item) => item.to === first);
    if (match) return match.label;
  }
  if (first === '/profile') return 'Profile';
  return 'Dashboard';
}

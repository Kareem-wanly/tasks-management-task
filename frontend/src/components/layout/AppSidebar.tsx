import { Link } from 'react-router-dom';
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarRail,
} from '@/components/ui/sidebar';
import { useAuth } from '@/lib/auth/AuthContext';
import BrandMark from './BrandMark';
import NavLinkItem from './NavLinkItem';
import UserMenu from './UserMenu';
import { NAV_GROUPS } from './navItems';

export default function AppSidebar() {
  const { can } = useAuth();
  const side = document.documentElement.dir === 'rtl' ? 'right' : 'left';

  const groups = NAV_GROUPS.map((group) => ({
    ...group,
    items: group.items.filter((item) => !item.permission || can(item.permission)),
  })).filter((group) => group.items.length > 0);

  return (
    <Sidebar variant="inset" collapsible="icon" side={side}>
      <SidebarHeader className="pt-3">
        <Link to="/" className="flex items-center gap-2.5 rounded-lg px-1 py-1 outline-none focus-visible:ring-3 focus-visible:ring-ring/30">
          <BrandMark />
          <span className="grid leading-tight transition-opacity duration-300 group-data-[collapsible=icon]:opacity-0">
            <span className="text-[15px] font-semibold tracking-tight">Task Manager</span>
            <span className="text-[11px] text-muted-foreground">Project workspace</span>
          </span>
        </Link>
      </SidebarHeader>

      <SidebarContent className="pt-2">
        {groups.map((group) => (
          <SidebarGroup key={group.label}>
            <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
            <SidebarGroupContent>
              <SidebarMenu className="gap-1">
                {group.items.map((item) => (
                  <NavLinkItem key={item.to} item={item} />
                ))}
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>
        ))}
      </SidebarContent>

      <SidebarFooter className="pb-3">
        <UserMenu />
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
  );
}

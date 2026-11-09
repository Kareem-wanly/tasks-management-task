import { NavLink } from 'react-router-dom';
import { motion } from 'motion/react';
import { SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { SPRING } from '@/lib/motion';
import { cn } from '@/lib/utils';
import type { NavItem } from './navItems';

interface NavLinkItemProps {
  item: NavItem;
}

/** Sidebar link; the active one carries the shared card pill and brand dot, which glide between items. */
export default function NavLinkItem({ item }: NavLinkItemProps) {
  const Icon = item.icon;

  return (
    <SidebarMenuItem>
      <NavLink to={item.to} end={item.end}>
        {({ isActive }) => (
          <SidebarMenuButton asChild isActive={isActive} tooltip={item.label} className="relative">
            <span>
              {isActive && (
                <motion.span
                  layoutId="nav-pill"
                  transition={SPRING}
                  className="absolute inset-0 rounded-xl border border-border bg-card shadow-soft"
                />
              )}
              <Icon
                className={cn(
                  'relative transition-colors duration-200',
                  isActive ? 'text-brand' : 'group-hover/menu-button:text-brand',
                )}
              />
              <span className="relative flex-1 truncate transition-[opacity,translate] duration-300 ease-luxe group-data-[collapsible=icon]:-translate-x-1.5 group-data-[collapsible=icon]:opacity-0 rtl:group-data-[collapsible=icon]:translate-x-1.5">
                {item.label}
              </span>
              {isActive && (
                <motion.span
                  layoutId="nav-dot"
                  transition={SPRING}
                  className="relative size-1.5 rounded-full bg-brand group-data-[collapsible=icon]:hidden"
                />
              )}
            </span>
          </SidebarMenuButton>
        )}
      </NavLink>
    </SidebarMenuItem>
  );
}

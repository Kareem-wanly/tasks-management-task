import { Link } from 'react-router-dom';
import { ChevronsUpDownIcon, KeyboardIcon, LanguagesIcon, LogOutIcon, SparklesIcon, UserRoundIcon } from 'lucide-react';
import UserAvatar from '@/components/data/UserAvatar';
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { useAuth } from '@/lib/auth/AuthContext';
import { emitAppEvent } from '@/lib/appEvents';
import { LANGUAGES, getLanguage, setLanguage, t } from '@/lib/i18n';
import { useMotionPreference } from '@/lib/preferences/useMotionPreference';

export default function UserMenu() {
  const { user, roles, logout } = useAuth();
  const { reduced, setReduced } = useMotionPreference();
  const name = user?.name ?? 'User';
  const roleText = roles.length > 0 ? roles.join(', ') : 'Member';
  // The Bitrix24 job title says more about a person than their local role.
  const subtitle = user?.position || roleText;

  return (
    <SidebarMenu>
      <SidebarMenuItem>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <SidebarMenuButton size="lg" className="data-[state=open]:bg-sidebar-accent">
              <UserAvatar user={{ id: user?.id, name, avatar_url: user?.avatar_url }} size="md" />
              <span className="grid min-w-0 flex-1 text-start leading-tight group-data-[collapsible=icon]:opacity-0">
                <span className="truncate text-sm font-medium text-foreground">{name}</span>
                <span className="truncate text-xs text-muted-foreground">{subtitle}</span>
              </span>
              <ChevronsUpDownIcon className="text-muted-foreground" />
            </SidebarMenuButton>
          </DropdownMenuTrigger>
          <DropdownMenuContent side="top" align="start" className="w-(--radix-dropdown-menu-trigger-width) min-w-56">
            <DropdownMenuLabel className="grid gap-0.5">
              <span className="truncate text-sm font-medium text-foreground">{name}</span>
              {user?.email && <span className="truncate text-xs font-normal text-muted-foreground">{user.email}</span>}
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
              <Link to="/profile">
                <UserRoundIcon />
                Profile & password
              </Link>
            </DropdownMenuItem>
            <DropdownMenuItem onSelect={() => emitAppEvent('open-shortcuts')}>
              <KeyboardIcon />
              Keyboard shortcuts
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuLabel className="flex items-center gap-2 text-xs font-normal text-muted-foreground">
              <LanguagesIcon className="size-3.5" />
              {t('shell.language')}
            </DropdownMenuLabel>
            <DropdownMenuRadioGroup
              value={getLanguage()}
              onValueChange={(value) => {
                const next = LANGUAGES.find((l) => l.value === value);
                if (next) setLanguage(next.value);
              }}
            >
              {LANGUAGES.map((language) => (
                <DropdownMenuRadioItem key={language.value} value={language.value}>
                  {language.label}
                </DropdownMenuRadioItem>
              ))}
            </DropdownMenuRadioGroup>
            <DropdownMenuSeparator />
            <DropdownMenuCheckboxItem checked={reduced} onCheckedChange={(checked) => setReduced(checked === true)}>
              <SparklesIcon />
              Reduce motion
            </DropdownMenuCheckboxItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem variant="destructive" onSelect={() => void logout()}>
              <LogOutIcon />
              Sign out
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </SidebarMenuItem>
    </SidebarMenu>
  );
}

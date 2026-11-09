import { CheckIcon } from 'lucide-react';
import ProjectMark from '@/components/data/ProjectMark';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { PROJECT_ICON_NAMES, PROJECT_ICONS } from '@/constants/projectIcons';
import { TINTS } from '@/constants/tints';
import { setProjectAppearance, useProjectAppearance } from '@/lib/preferences/projectAppearance';
import { cn } from '@/lib/utils';

interface ProjectAppearancePickerProps {
  projectId: number;
}

/** The project's mark; click it to pick a colour and icon (saved in this browser). */
export default function ProjectAppearancePicker({ projectId }: ProjectAppearancePickerProps) {
  const { tint, icon } = useProjectAppearance(projectId);

  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        aria-label="Change project colour and icon"
        title="Change colour and icon"
        className="rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-ring/40"
      >
        <ProjectMark projectId={projectId} size="lg" className="transition-transform hover:scale-105" />
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" className="w-64">
        <DropdownMenuLabel>Colour</DropdownMenuLabel>
        <DropdownMenuGroup className="grid grid-cols-8 gap-1 px-1.5 pb-1.5">
          {TINTS.map((value) => (
            <DropdownMenuItem
              key={value}
              onSelect={(e) => {
                e.preventDefault();
                setProjectAppearance(projectId, { tint: value });
              }}
              aria-label={`${value} colour`}
              data-tint={value}
              className="tint-solid grid size-6 place-items-center rounded-full p-0 focus:ring-2 focus:ring-ring/50"
            >
              {tint === value && <CheckIcon className="size-3.5" />}
            </DropdownMenuItem>
          ))}
        </DropdownMenuGroup>
        <DropdownMenuSeparator />
        <DropdownMenuLabel>Icon</DropdownMenuLabel>
        <DropdownMenuGroup className="grid grid-cols-6 gap-1 px-1.5 pb-1.5">
          {PROJECT_ICON_NAMES.map((name) => {
            const Icon = PROJECT_ICONS[name];
            return (
              <DropdownMenuItem
                key={name}
                onSelect={(e) => {
                  e.preventDefault();
                  setProjectAppearance(projectId, { icon: name });
                }}
                aria-label={`${name} icon`}
                className={cn('grid size-8 place-items-center p-0', icon === name && 'bg-muted ring-1 ring-border')}
              >
                <Icon />
              </DropdownMenuItem>
            );
          })}
        </DropdownMenuGroup>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

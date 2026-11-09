import { PROJECT_ICONS } from '@/constants/projectIcons';
import { useProjectAppearance } from '@/lib/preferences/projectAppearance';
import { cn } from '@/lib/utils';

interface ProjectMarkProps {
  projectId: number;
  size?: 'xs' | 'sm' | 'md' | 'lg';
  className?: string;
}

const SIZES = {
  xs: 'size-4 rounded-[3px] [&_svg]:size-2.5',
  sm: 'size-5 rounded-sm [&_svg]:size-3',
  md: 'size-7 rounded-md [&_svg]:size-4',
  lg: 'size-9 rounded-lg [&_svg]:size-5',
};

/** The project's colour and icon, so it reads the same on every list, card and board. */
export default function ProjectMark({ projectId, size = 'sm', className }: ProjectMarkProps) {
  const { tint, icon } = useProjectAppearance(projectId);
  const Icon = PROJECT_ICONS[icon];
  return (
    <span aria-hidden="true" data-tint={tint} className={cn('tint-soft grid shrink-0 place-items-center', SIZES[size], className)}>
      <Icon />
    </span>
  );
}

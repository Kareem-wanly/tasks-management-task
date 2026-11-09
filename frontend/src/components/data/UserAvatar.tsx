import { tintFor } from '@/constants/tints';
import { cn } from '@/lib/utils';
import { initials } from '@/utils/format';

interface UserAvatarProps {
  user: { id?: number | null; name?: string | null };
  size?: 'xs' | 'sm' | 'md';
  className?: string;
}

const SIZES = {
  xs: 'size-5 text-[9px]',
  sm: 'size-6 text-[10px]',
  md: 'size-8 text-xs',
};

/** Round initials chip in a colour derived from the user, so people are easy to tell apart. */
export default function UserAvatar({ user, size = 'sm', className }: UserAvatarProps) {
  const name = user.name || 'Unknown user';
  return (
    <span
      title={name}
      aria-label={name}
      role="img"
      data-tint={tintFor(user.id ?? name)}
      className={cn('tint-soft grid shrink-0 place-items-center rounded-full font-semibold', SIZES[size], className)}
    >
      {initials(name)}
    </span>
  );
}

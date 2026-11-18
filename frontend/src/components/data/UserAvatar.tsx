import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { tintFor } from '@/constants/tints';
import { cn } from '@/lib/utils';
import { initials } from '@/utils/format';

interface UserAvatarProps {
  user: { id?: number | null; name?: string | null; avatar_url?: string | null };
  size?: 'xs' | 'sm' | 'md';
  className?: string;
}

const SIZES = {
  xs: 'size-5 text-[9px]',
  sm: 'size-6 text-[10px]',
  md: 'size-8 text-xs',
};

/** Profile photo when there is one, otherwise a round initials chip in a colour derived from the user. */
export default function UserAvatar({ user, size = 'sm', className }: UserAvatarProps) {
  const name = user.name || 'Unknown user';
  const chip = (
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

  if (!user.avatar_url) return chip;

  // The initials stay as the fallback while the photo loads or if it fails.
  return (
    <Avatar title={name} className={cn(SIZES[size], className)}>
      <AvatarImage src={user.avatar_url} alt={name} />
      <AvatarFallback className="bg-transparent">{chip}</AvatarFallback>
    </Avatar>
  );
}

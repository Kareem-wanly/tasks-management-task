import PageHeader from '@/components/layout/PageHeader';
import UserAvatar from '@/components/data/UserAvatar';
import { useAuth } from '@/lib/auth/AuthContext';
import ChangePasswordForm from './components/ChangePasswordForm';
import ProfileDetailsForm from './components/ProfileDetailsForm';

export default function ProfilePage() {
  const { user, roles } = useAuth();

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <PageHeader
        eyebrow="Account"
        title={
          <span className="flex items-center gap-3">
            {user && <UserAvatar user={user} size="md" className="size-10 text-sm" />}
            {user?.name ?? 'Your profile'}
          </span>
        }
        description={roles.length > 0 ? `Signed in as ${user?.email} · ${roles.join(', ')}` : user?.email}
      />
      <ProfileDetailsForm />
      <ChangePasswordForm />
    </div>
  );
}

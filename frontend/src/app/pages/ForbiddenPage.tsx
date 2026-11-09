import { ShieldAlertIcon } from 'lucide-react';
import StatusPage from './StatusPage';

export default function ForbiddenPage() {
  return (
    <StatusPage
      code="403"
      eyebrow="Access denied"
      icon={ShieldAlertIcon}
      title="You don't have access to this page."
      description="Your role doesn't include the permission this page needs. Ask an administrator if you think this is a mistake."
    />
  );
}

import { CompassIcon } from 'lucide-react';
import StatusPage from './StatusPage';

export default function NotFoundPage() {
  return (
    <StatusPage
      code="404"
      eyebrow="Not found"
      icon={CompassIcon}
      title="This page doesn't exist."
      description="It may have been moved or removed, or the link may be wrong."
    />
  );
}

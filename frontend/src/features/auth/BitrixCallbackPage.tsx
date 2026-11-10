import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import { useAuth } from '@/lib/auth/AuthContext';
import AuthShell from './components/AuthShell';

/** Reasons the backend puts in #error=... */
const ERROR_MESSAGES: Record<string, string> = {
  invalid_state: 'The sign-in link expired or was opened twice. Please try again.',
  unknown_portal: 'This Bitrix24 portal is not connected to the app.',
  exchange_failed: 'Bitrix24 did not confirm the sign-in. Please try again.',
  bitrix_rejected: 'Bitrix24 did not accept the sign-in. Please try again.',
  inactive: 'Your account is deactivated. Contact your administrator.',
};

const DEFAULT_ERROR = 'Sign-in with Bitrix24 failed. Please try again.';

/** Landing page of the Bitrix24 sign-in: the backend redirects here with #token=... or #error=... */
export default function BitrixCallbackPage() {
  const { loginWithToken } = useAuth();
  const navigate = useNavigate();
  const [error, setError] = useState<string | null>(null);
  const started = useRef(false);

  // A one-time auth hand-off, not data loading, so React Query doesn't fit here.
  useEffect(() => {
    // StrictMode runs effects twice in development; the token must be used once.
    if (started.current) return;
    started.current = true;

    const params = new URLSearchParams(window.location.hash.slice(1));
    // Keep the token out of the address bar and browser history.
    window.history.replaceState(null, '', window.location.pathname);

    const token = params.get('token');
    if (!token) {
      setError(ERROR_MESSAGES[params.get('error') ?? ''] ?? DEFAULT_ERROR);
      return;
    }

    loginWithToken(token)
      .then(() => navigate('/', { replace: true }))
      .catch((err: unknown) => {
        console.error('Bitrix24 sign-in failed:', err);
        setError(DEFAULT_ERROR);
      });
  }, [loginWithToken, navigate]);

  if (!error) {
    return <LoadingSpinner fullPage text="Signing you in with Bitrix24..." />;
  }

  return (
    <AuthShell
      eyebrow="Bitrix24"
      title="Couldn't sign you in"
      description={error}
      footer={
        <Link to="/login" className="text-foreground underline decoration-brand/50 underline-offset-4 hover:decoration-brand">
          Back to sign in
        </Link>
      }
    >
      <p role="alert" className="text-sm text-destructive">
        {error}
      </p>
    </AuthShell>
  );
}

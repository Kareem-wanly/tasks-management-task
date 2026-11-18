import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import authApi from '@/api/authApi';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import { ApiError } from '@/lib/apiClient';
import { useAuth } from '@/lib/auth/AuthContext';
import { bitrixFrameAuth } from '@/lib/auth/bitrixFrame';
import AuthShell from './components/AuthShell';
import BitrixLoginButton from './components/BitrixLoginButton';
import { BITRIX_SIGN_IN_DEFAULT_ERROR, BITRIX_SIGN_IN_ERRORS } from './bitrixSignInErrors';

/** Why the automatic sign-in didn't happen; null while it is still trying. */
type Outcome = { message: string | null } | null;

/**
 * Where the app opens inside Bitrix24. It signs in the Bitrix user around it, with the tokens Bitrix's JS
 * SDK hands this frame (a forged POST can't plant someone else's). If that isn't possible, e.g. the page
 * was opened outside Bitrix, the normal "Sign in with Bitrix24" button is shown.
 */
export default function BitrixAppPage() {
  const { loginWithToken } = useAuth();
  const navigate = useNavigate();
  const [outcome, setOutcome] = useState<Outcome>(null);
  const started = useRef(false);

  // A one-time auth hand-off, not data loading, so React Query doesn't fit here.
  useEffect(() => {
    // StrictMode runs effects twice in development; sign in once.
    if (started.current) return;
    started.current = true;

    bitrixFrameAuth()
      .then(async (auth) => {
        if (!auth) {
          setOutcome({ message: null });
          return;
        }
        await loginWithToken(await authApi.bitrixAppSession(auth));
        navigate('/', { replace: true });
      })
      .catch((err: unknown) => {
        console.error('Bitrix24 app sign-in failed:', err);
        const reason = err instanceof ApiError ? err.data?.reason : undefined;
        setOutcome({ message: BITRIX_SIGN_IN_ERRORS[reason ?? ''] ?? BITRIX_SIGN_IN_DEFAULT_ERROR });
      });
  }, [loginWithToken, navigate]);

  if (!outcome) {
    return <LoadingSpinner fullPage text="Signing you in with Bitrix24..." />;
  }

  return (
    <AuthShell
      eyebrow="Bitrix24"
      title="Sign in"
      description="Continue with your Bitrix24 account."
      footer={
        <Link to="/login" className="text-foreground underline decoration-brand/50 underline-offset-4 hover:decoration-brand">
          Sign in with a password instead
        </Link>
      }
    >
      {outcome.message && (
        <p role="alert" className="mb-4 text-sm text-destructive">
          {outcome.message}
        </p>
      )}
      <BitrixLoginButton />
    </AuthShell>
  );
}

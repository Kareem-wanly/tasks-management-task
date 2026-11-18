import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import authApi from '@/api/authApi';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import { useAuth } from '@/lib/auth/AuthContext';
import { takeBitrixLoginNonce } from '@/lib/auth/bitrixLoginNonce';
import AuthShell from './components/AuthShell';
import { BITRIX_SIGN_IN_DEFAULT_ERROR, BITRIX_SIGN_IN_ERRORS, BITRIX_SIGN_IN_OTHER_BROWSER } from './bitrixSignInErrors';

/** Landing page of the Bitrix24 sign-in: the backend redirects here with a one-time #code=... or #error=... */
export default function BitrixCallbackPage() {
  const { loginWithToken } = useAuth();
  const navigate = useNavigate();
  const [error, setError] = useState<string | null>(null);
  const started = useRef(false);

  // A one-time auth hand-off, not data loading, so React Query doesn't fit here.
  useEffect(() => {
    // StrictMode runs effects twice in development; the code works only once.
    if (started.current) return;
    started.current = true;

    const params = new URLSearchParams(window.location.hash.slice(1));
    // Keep the code out of the address bar and browser history.
    window.history.replaceState(null, '', window.location.pathname);

    const code = params.get('code');
    const nonce = takeBitrixLoginNonce();
    if (!code) {
      setError(BITRIX_SIGN_IN_ERRORS[params.get('error') ?? ''] ?? BITRIX_SIGN_IN_DEFAULT_ERROR);
      return;
    }
    // A code without this tab's nonce was not started here (e.g. a link someone sent).
    if (!nonce) {
      setError(BITRIX_SIGN_IN_OTHER_BROWSER);
      return;
    }

    authApi
      .exchangeBitrixCode(code, nonce)
      .then((token) => loginWithToken(token))
      .then(() => navigate('/', { replace: true }))
      .catch((err: unknown) => {
        console.error('Bitrix24 sign-in failed:', err);
        setError(BITRIX_SIGN_IN_DEFAULT_ERROR);
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

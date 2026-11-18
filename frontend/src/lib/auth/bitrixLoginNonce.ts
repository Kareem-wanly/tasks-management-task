/**
 * The nonce that binds a Bitrix24 sign-in to this browser (login-CSRF
 * protection). Made when the user clicks "Sign in with Bitrix24", kept in
 * sessionStorage (this tab only), and sent back with the one-time code; a
 * code that reaches another browser can't be traded there.
 */
const KEY = 'bitrix_login_nonce';

/** Makes and remembers a new nonce for the sign-in about to start. */
export function createBitrixLoginNonce(): string {
  const nonce = crypto.randomUUID();
  try {
    sessionStorage.setItem(KEY, nonce);
  } catch {
    // Storage blocked: the sign-in will be refused at the end, which is the safe outcome.
  }
  return nonce;
}

/** The nonce this tab made, removed so it is used once; null when this tab didn't start a sign-in. */
export function takeBitrixLoginNonce(): string | null {
  try {
    const nonce = sessionStorage.getItem(KEY);
    sessionStorage.removeItem(KEY);
    return nonce;
  } catch {
    return null;
  }
}

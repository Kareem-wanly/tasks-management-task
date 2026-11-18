/** Why a Bitrix24 sign-in failed, by the reason the backend gives (#error=… or the JSON `reason`). */
export const BITRIX_SIGN_IN_ERRORS: Record<string, string> = {
  invalid_state: 'The sign-in link expired or was opened twice. Please try again.',
  unknown_portal: 'This Bitrix24 portal is not connected to the app.',
  exchange_failed: 'Bitrix24 did not confirm the sign-in. Please try again.',
  bitrix_rejected: 'Bitrix24 did not accept the sign-in. Please try again.',
  inactive: 'Your account is deactivated. Contact your administrator.',
  account_not_linked:
    'An account with your email already exists here but is not linked to Bitrix24. Ask your administrator to link it, or sign in with your password.',
};

export const BITRIX_SIGN_IN_DEFAULT_ERROR = 'Sign-in with Bitrix24 failed. Please try again.';

export const BITRIX_SIGN_IN_OTHER_BROWSER =
  'This sign-in was not started in this browser. Click "Sign in with Bitrix24" here to sign in.';

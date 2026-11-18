import type { BitrixFrameAuth } from '@/api/authApi';

/** The part of Bitrix24's JS SDK (api.bitrix24.com/api/v1/) this app uses. */
interface Bx24 {
  init: (callback: () => void) => void;
  getAuth: () => BitrixFrameAuth | false;
}

declare global {
  interface Window {
    BX24?: Bx24;
  }
}

const SDK_URL = 'https://api.bitrix24.com/api/v1/';

/** How long to wait for the Bitrix page around us to answer before showing the sign-in button. */
const TIMEOUT_MS = 6000;

function isInsideFrame(): boolean {
  try {
    return window.self !== window.top;
  } catch {
    // A cross-origin parent: we are framed.
    return true;
  }
}

/**
 * The tokens of the Bitrix24 user signed in around this page, from Bitrix's JS SDK: Bitrix itself hands
 * them to its app frame, so another site can't plant someone else's. Null outside Bitrix, or when the
 * SDK doesn't answer in time.
 */
export function bitrixFrameAuth(): Promise<BitrixFrameAuth | null> {
  if (!isInsideFrame()) return Promise.resolve(null);

  return new Promise((resolve) => {
    const timer = window.setTimeout(() => resolve(null), TIMEOUT_MS);
    const finish = (auth: BitrixFrameAuth | null): void => {
      window.clearTimeout(timer);
      resolve(auth);
    };
    const start = (): void => {
      const sdk = window.BX24;
      if (!sdk) {
        finish(null);
        return;
      }
      sdk.init(() => {
        const auth = sdk.getAuth();
        finish(auth && auth.access_token ? auth : null);
      });
    };

    if (window.BX24) {
      start();
      return;
    }
    const script = document.createElement('script');
    script.src = SDK_URL;
    script.onload = start;
    script.onerror = () => finish(null);
    document.head.appendChild(script);
  });
}

import { motion } from 'motion/react';
import authApi from '@/api/authApi';
import { Button } from '@/components/ui/button';
import { createBitrixLoginNonce } from '@/lib/auth/bitrixLoginNonce';
import { fadeUpProps } from '@/lib/motion';

interface BitrixLoginButtonProps {
  delay?: number;
}

/**
 * Leaves the SPA for the backend, which sends the user to Bitrix24's consent screen. The nonce made here
 * stays in this tab and must come back with the sign-in, so a sign-in can't be finished in another browser.
 */
export default function BitrixLoginButton({ delay = 0 }: BitrixLoginButtonProps) {
  return (
    <motion.div {...fadeUpProps(delay)}>
      <Button
        type="button"
        variant="outline"
        size="lg"
        className="w-full"
        onClick={() => window.location.assign(authApi.bitrixLoginUrl(createBitrixLoginNonce()))}
      >
        Sign in with Bitrix24
      </Button>
    </motion.div>
  );
}

import { motion } from 'motion/react';
import authApi from '@/api/authApi';
import { Button } from '@/components/ui/button';
import { fadeUpProps } from '@/lib/motion';

interface BitrixLoginButtonProps {
  delay?: number;
}

/** Leaves the SPA for the backend, which sends the user to Bitrix24's consent screen. */
export default function BitrixLoginButton({ delay = 0 }: BitrixLoginButtonProps) {
  return (
    <motion.div {...fadeUpProps(delay)}>
      <Button asChild variant="outline" size="lg" className="w-full">
        <a href={authApi.bitrixLoginUrl()}>Sign in with Bitrix24</a>
      </Button>
    </motion.div>
  );
}

import type { ReactNode } from 'react';
import { motion } from 'motion/react';
import BrandMark from '@/components/layout/BrandMark';
import { fadeUpProps } from '@/lib/motion';

interface AuthShellProps {
  eyebrow: string;
  title: string;
  description: string;
  /** Small muted line under the card body, e.g. the link to the other auth page. */
  footer: ReactNode;
  children: ReactNode;
}

/** Centered card used by the login and register screens. */
export default function AuthShell({ eyebrow, title, description, footer, children }: AuthShellProps) {
  return (
    <main className="grid min-h-svh place-items-center bg-background px-4 py-10">
      <motion.div {...fadeUpProps(0)} className="surface w-full max-w-md p-8">
        <motion.div {...fadeUpProps(0.05)}>
          <BrandMark />
        </motion.div>
        <div className="mt-6 space-y-2">
          <motion.p {...fadeUpProps(0.1)} className="eyebrow">
            {eyebrow}
          </motion.p>
          <motion.h1 {...fadeUpProps(0.14)} className="text-3xl leading-tight font-semibold tracking-tight">
            {title}
          </motion.h1>
          <motion.p {...fadeUpProps(0.18)} className="text-sm leading-6 text-muted-foreground">
            {description}
          </motion.p>
        </div>
        <div className="mt-8">{children}</div>
        <motion.p {...fadeUpProps(0.4)} className="mt-6 text-center text-sm text-muted-foreground">
          {footer}
        </motion.p>
      </motion.div>
    </main>
  );
}

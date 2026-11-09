import { useEffect, type ReactNode } from 'react';
import { MotionConfig } from 'motion/react';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useMotionPreference } from '@/lib/preferences/useMotionPreference';
import { syncSystemTheme } from '@/lib/preferences/useTheme';

interface UiProvidersProps {
  children: ReactNode;
}

/** Motion preference, tooltip delay and OS theme sync for the whole app. */
export default function UiProviders({ children }: UiProvidersProps) {
  const { reduced } = useMotionPreference();

  useEffect(() => syncSystemTheme(), []);

  return (
    <MotionConfig reducedMotion={reduced ? 'always' : 'never'}>
      <TooltipProvider delayDuration={250}>{children}</TooltipProvider>
    </MotionConfig>
  );
}

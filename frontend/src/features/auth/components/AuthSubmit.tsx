import { Loader2Icon } from 'lucide-react';
import { motion } from 'motion/react';
import { Button } from '@/components/ui/button';
import { fadeUpProps } from '@/lib/motion';

interface AuthSubmitProps {
  label: string;
  pendingLabel: string;
  isSubmitting: boolean;
  delay?: number;
}

/** Full-width brand submit button: the single CTA of an auth screen. */
export default function AuthSubmit({ label, pendingLabel, isSubmitting, delay = 0 }: AuthSubmitProps) {
  return (
    <motion.div {...fadeUpProps(delay)} className="pt-2">
      <Button type="submit" variant="brand" size="lg" className="w-full" disabled={isSubmitting}>
        {isSubmitting ? (
          <>
            <Loader2Icon className="animate-spin" />
            {pendingLabel}
          </>
        ) : (
          label
        )}
      </Button>
    </motion.div>
  );
}

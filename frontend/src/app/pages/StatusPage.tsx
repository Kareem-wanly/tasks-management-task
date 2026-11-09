import { Link } from 'react-router-dom';
import { ArrowLeftIcon, type LucideIcon } from 'lucide-react';
import { motion } from 'motion/react';
import BrandMark from '@/components/layout/BrandMark';
import { Button } from '@/components/ui/button';
import { fadeUpProps } from '@/lib/motion';

interface StatusPageProps {
  code: string;
  eyebrow: string;
  title: string;
  description: string;
  icon: LucideIcon;
}

/** Full-screen message for HTTP-style states (403, 404). */
export default function StatusPage({ code, eyebrow, title, description, icon: Icon }: StatusPageProps) {
  return (
    <div className="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-background px-4 text-center">
      <span className="absolute top-6 start-6">
        <BrandMark />
      </span>
      <motion.div {...fadeUpProps(0)} className="relative mb-6">
        <span aria-hidden="true" className="absolute inset-0 scale-150 rounded-full bg-brand-soft blur-xl" />
        <span className="relative grid size-14 place-items-center rounded-2xl border border-border bg-card text-brand shadow-soft">
          <Icon className="size-6" />
        </span>
      </motion.div>
      <motion.div {...fadeUpProps(0.04)} className="eyebrow flex items-center gap-2">
        <span className="h-px w-5 bg-brand" />
        {eyebrow} · {code}
      </motion.div>
      <motion.h1
        {...fadeUpProps(0.08)}
        className="mt-3 max-w-xl text-3xl leading-tight font-semibold tracking-tight md:text-4xl"
      >
        {title}
      </motion.h1>
      <motion.p {...fadeUpProps(0.12)} className="mt-3 max-w-md text-sm leading-6 text-muted-foreground">
        {description}
      </motion.p>
      <motion.div {...fadeUpProps(0.16)} className="mt-8">
        <Button asChild>
          <Link to="/">
            <ArrowLeftIcon className="rtl:-scale-x-100" />
            Back to dashboard
          </Link>
        </Button>
      </motion.div>
    </div>
  );
}

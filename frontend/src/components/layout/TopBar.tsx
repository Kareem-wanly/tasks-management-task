import type { RefObject } from 'react';
import { motion, useScroll, useTransform } from 'motion/react';
import { SearchIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { emitAppEvent } from '@/lib/appEvents';
import { modKeyLabel } from '@/lib/platform';
import ThemeToggle from './ThemeToggle';
import TopBarBreadcrumb from './TopBarBreadcrumb';

interface TopBarProps {
  /** The element that scrolls the page content. */
  scrollRef: RefObject<HTMLDivElement | null>;
}

/** Sticky, translucent bar that tightens from 56px to 48px and gains a shadow as the page scrolls. */
export default function TopBar({ scrollRef }: TopBarProps) {
  const { scrollY } = useScroll({ container: scrollRef });
  const height = useTransform(scrollY, [0, 64], [56, 48]);
  const shadowOpacity = useTransform(scrollY, [0, 64], [0, 1]);

  return (
    <motion.header
      style={{ height }}
      className="sticky top-0 z-20 flex shrink-0 items-center gap-3 border-b border-border/70 bg-card/80 px-3 backdrop-blur-xl md:px-5"
    >
      <motion.span
        aria-hidden="true"
        style={{ opacity: shadowOpacity }}
        className="pointer-events-none absolute inset-0 -z-10 shadow-soft"
      />
      <SidebarTrigger className="-ms-1" />
      <Separator orientation="vertical" className="h-4!" />
      <TopBarBreadcrumb />
      <div className="ms-auto flex items-center gap-1.5">
        <Button
          variant="outline"
          size="sm"
          onClick={() => emitAppEvent('open-command-palette')}
          className="hidden w-52 justify-start font-normal text-muted-foreground sm:inline-flex"
        >
          <SearchIcon />
          Search…
          <kbd className="ms-auto rounded-sm border border-border bg-muted px-1 font-mono text-[10px]">{modKeyLabel()} K</kbd>
        </Button>
        <Button
          variant="ghost"
          size="icon-sm"
          onClick={() => emitAppEvent('open-command-palette')}
          aria-label="Search"
          className="sm:hidden"
        >
          <SearchIcon />
        </Button>
        <ThemeToggle />
      </div>
    </motion.header>
  );
}

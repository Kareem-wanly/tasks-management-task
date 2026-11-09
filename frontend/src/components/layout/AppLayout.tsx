import { Suspense, useRef, type ReactNode } from 'react';
import { useLocation, useOutlet } from 'react-router-dom';
import { AnimatePresence } from 'motion/react';
import PageTransition from '@/components/motion/PageTransition';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import AppSidebar from './AppSidebar';
import TopBar from './TopBar';

interface AppLayoutProps {
  /** Dialogs owned by the app shell (command palette, shortcuts, quick create). */
  overlays?: ReactNode;
  /** Fixed bottom navigation for small screens. */
  bottomNav?: ReactNode;
}

export default function AppLayout({ overlays, bottomNav }: AppLayoutProps) {
  const { pathname } = useLocation();
  const outlet = useOutlet();
  const scrollRef = useRef<HTMLDivElement>(null);

  return (
    <SidebarProvider>
      <AppSidebar />
      <SidebarInset>
        <div ref={scrollRef} className="relative h-svh overflow-y-auto md:h-full">
          <TopBar scrollRef={scrollRef} />
          <main className={cn('mx-auto w-full max-w-7xl px-4 py-6 md:px-8 md:py-8', bottomNav && 'pb-24 md:pb-8')}>
            <AnimatePresence mode="wait" initial={false}>
              <PageTransition key={pathname}>
                <Suspense fallback={<LoadingSpinner />}>{outlet}</Suspense>
              </PageTransition>
            </AnimatePresence>
          </main>
        </div>
        {bottomNav}
      </SidebarInset>
      {overlays}
    </SidebarProvider>
  );
}

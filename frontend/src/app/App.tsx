import { BrowserRouter } from 'react-router-dom';
import { QueryClientProvider } from '@tanstack/react-query';
import { ReactQueryDevtools } from '@tanstack/react-query-devtools';
import { Direction } from 'radix-ui';
import { AuthProvider } from '@/lib/auth/AuthContext';
import { getDirection } from '@/lib/i18n';
import { ToastProvider } from '@/lib/toast/ToastContext';
import { queryClient } from '@/lib/queryClient';
import UiProviders from './providers/UiProviders';
import AppRoutes from './routes/AppRoutes';

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      {/* Menus, selects and tooltips position and navigate by the page direction (Arabic is RTL) */}
      <Direction.Provider dir={getDirection()}>
        <BrowserRouter>
          <UiProviders>
            <ToastProvider>
              <AuthProvider>
                <AppRoutes />
              </AuthProvider>
            </ToastProvider>
          </UiProviders>
        </BrowserRouter>
      </Direction.Provider>
      {/* Rendered only in development; tree-shaken from production builds */}
      <ReactQueryDevtools initialIsOpen={false} />
    </QueryClientProvider>
  );
}

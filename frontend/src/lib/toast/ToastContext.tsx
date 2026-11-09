import type { ReactNode } from 'react';
import { toast } from 'sonner';
import { Toaster } from '@/components/ui/sonner';
import { getDirection } from '@/lib/i18n';

export type ToastType = 'success' | 'error' | 'info';

/** Shows a toast. Usable anywhere, including outside React (e.g. mutation callbacks). */
export function showToast(message: string, type: ToastType = 'success', duration = 3500): void {
  toast[type](message, { duration });
}

export interface ToastAction {
  label: string;
  onClick: () => void;
}

/** Toast with one button, e.g. "Open" after creating something. */
export function showActionToast(message: string, action: ToastAction, type: ToastType = 'success', duration = 6000): void {
  toast[type](message, { duration, action });
}

/** Success toast with an Undo button, for quick actions that skip a confirmation. */
export function showUndoToast(message: string, onUndo: () => void, duration = 6000): void {
  showActionToast(message, { label: 'Undo', onClick: onUndo }, 'success', duration);
}

interface ToastContextValue {
  showToast: typeof showToast;
}

const value: ToastContextValue = { showToast };

/** Renders the sonner toaster once at the app root. */
export function ToastProvider({ children }: { children: ReactNode }) {
  return (
    <>
      {children}
      <Toaster position={getDirection() === 'rtl' ? 'bottom-left' : 'bottom-right'} dir={getDirection()} closeButton />
    </>
  );
}

/** Kept so components can keep calling `const { showToast } = useToast()`. */
export function useToast(): ToastContextValue {
  return value;
}

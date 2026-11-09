import type { ReactNode } from 'react';

interface DetailRowProps {
  label: string;
  children: ReactNode;
}

/** One key/value pair inside a `dl` with `grid-cols-[auto_1fr]`. */
export default function DetailRow({ label, children }: DetailRowProps) {
  return (
    <>
      <dt className="text-muted-foreground">{label}</dt>
      <dd className="min-w-0">{children}</dd>
    </>
  );
}

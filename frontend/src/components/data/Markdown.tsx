import type { ComponentProps } from 'react';
import ReactMarkdown, { type Components } from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { cn } from '@/lib/utils';

interface MarkdownProps {
  children: string;
  /** Makes "- [ ]" items clickable; receives the item's 1-based source line. */
  onToggleItem?: (line: number) => void;
  /** Disables the checkboxes while a toggle is saving. */
  busy?: boolean;
  className?: string;
}

const base: Components = {
  h1: ({ node: _node, ...props }) => <h3 className="mt-4 mb-2 text-base font-semibold first:mt-0" {...props} />,
  h2: ({ node: _node, ...props }) => <h4 className="mt-4 mb-2 text-[15px] font-semibold first:mt-0" {...props} />,
  h3: ({ node: _node, ...props }) => <h5 className="mt-3 mb-1.5 text-sm font-semibold first:mt-0" {...props} />,
  p: ({ node: _node, ...props }) => <p className="my-2 first:mt-0 last:mb-0" {...props} />,
  a: ({ node: _node, ...props }) => (
    <a className="text-brand underline underline-offset-2 hover:no-underline" target="_blank" rel="noreferrer noopener" {...props} />
  ),
  ul: ({ node: _node, className, ...props }) => (
    <ul className={cn('my-2 list-disc space-y-1 ps-5', className?.includes('contains-task-list') && 'list-none ps-0')} {...props} />
  ),
  ol: ({ node: _node, ...props }) => <ol className="my-2 list-decimal space-y-1 ps-5" {...props} />,
  li: ({ node: _node, className, ...props }) => (
    <li className={cn(className?.includes('task-list-item') && 'flex items-start gap-2')} {...props} />
  ),
  blockquote: ({ node: _node, ...props }) => (
    <blockquote className="my-2 border-s-2 border-border ps-3 text-muted-foreground" {...props} />
  ),
  code: ({ node: _node, className, ...props }) => (
    <code
      className={cn(
        'rounded-sm bg-muted px-1 py-0.5 font-mono text-[0.85em]',
        className?.startsWith('language-') && 'block overflow-x-auto bg-transparent p-0'
      )}
      {...props}
    />
  ),
  pre: ({ node: _node, ...props }) => <pre className="my-2 overflow-x-auto rounded-md bg-muted p-3 text-xs" {...props} />,
  hr: () => <hr className="my-4 border-border" />,
  table: ({ node: _node, ...props }) => (
    <div className="my-2 overflow-x-auto">
      <table className="w-full border-collapse text-[13px]" {...props} />
    </div>
  ),
  th: ({ node: _node, ...props }) => <th className="border border-border bg-muted px-2 py-1 text-start font-medium" {...props} />,
  td: ({ node: _node, ...props }) => <td className="border border-border px-2 py-1" {...props} />,
};

/**
 * Renders user-written Markdown (GitHub flavour: lists, tables, task lists). Raw HTML is never
 * rendered, so a description or comment can't inject markup.
 */
export default function Markdown({ children, onToggleItem, busy = false, className }: MarkdownProps) {
  const components: Components = {
    ...base,
    input: ({ node, type, checked, ...props }: ComponentProps<'input'> & { node?: { position?: { start: { line: number } } } }) => {
      if (type !== 'checkbox') return <input type={type} {...props} />;
      const line = node?.position?.start.line;
      return (
        <input
          type="checkbox"
          checked={Boolean(checked)}
          disabled={!onToggleItem || busy || line === undefined}
          onChange={() => line !== undefined && onToggleItem?.(line)}
          aria-label={checked ? 'Mark item as not done' : 'Mark item as done'}
          className="mt-1 size-3.5 shrink-0 accent-brand disabled:cursor-default enabled:cursor-pointer"
        />
      );
    },
  };

  return (
    <div className={cn('text-sm leading-6 break-words', className)}>
      <ReactMarkdown remarkPlugins={[remarkGfm]} components={components} skipHtml>
        {children}
      </ReactMarkdown>
    </div>
  );
}

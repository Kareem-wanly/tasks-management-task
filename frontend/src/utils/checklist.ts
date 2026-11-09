// Checklists live inside a task description as Markdown task items: "- [ ] step" / "- [x] step".

const ITEM = /^(\s*(?:[-*+]|\d+[.)])\s+\[)([ xX])(\])/;

export interface ChecklistProgress {
  done: number;
  total: number;
}

/** Done / total checklist items in a description, or null when it has none. */
export function checklistProgress(text: string | null | undefined): ChecklistProgress | null {
  if (!text) return null;
  let done = 0;
  let total = 0;
  for (const line of text.split('\n')) {
    const match = ITEM.exec(line);
    if (!match) continue;
    total += 1;
    if (match[2] !== ' ') done += 1;
  }
  return total > 0 ? { done, total } : null;
}

/** Flips the checklist item on `lineNumber` (1-based, as Markdown positions report it). */
export function toggleChecklistLine(text: string, lineNumber: number): string {
  const lines = text.split('\n');
  const line = lines[lineNumber - 1];
  if (line === undefined) return text;
  lines[lineNumber - 1] = line.replace(ITEM, (_, start: string, mark: string, end: string) =>
    `${start}${mark === ' ' ? 'x' : ' '}${end}`
  );
  return lines.join('\n');
}

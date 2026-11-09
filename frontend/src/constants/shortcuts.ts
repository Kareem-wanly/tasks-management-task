/** Shortcut help, grouped as shown in the "Keyboard shortcuts" dialog. `mod` is shown as Ctrl or ⌘. */
export interface ShortcutHelp {
  keys: string[];
  label: string;
}

export const SHORTCUT_GROUPS: { title: string; items: ShortcutHelp[] }[] = [
  {
    title: 'Anywhere',
    items: [
      { keys: ['mod', 'K'], label: 'Search and run commands' },
      { keys: ['/'], label: 'Focus the search box on this page' },
      { keys: ['N'], label: 'New task' },
      { keys: ['?'], label: 'Show keyboard shortcuts' },
    ],
  },
  {
    title: 'Go to',
    items: [
      { keys: ['G', 'D'], label: 'Dashboard' },
      { keys: ['G', 'P'], label: 'Projects' },
      { keys: ['G', 'T'], label: 'Tasks' },
      { keys: ['G', 'O'], label: 'Your profile' },
    ],
  },
  {
    title: 'On a task',
    items: [
      { keys: ['E'], label: 'Edit the task' },
      { keys: ['C'], label: 'Complete or reopen' },
      { keys: ['J'], label: 'Next task in the project' },
      { keys: ['K'], label: 'Previous task in the project' },
    ],
  },
  {
    title: 'In forms',
    items: [
      { keys: ['mod', 'Enter'], label: 'Save' },
      { keys: ['Esc'], label: 'Close or cancel' },
    ],
  },
];

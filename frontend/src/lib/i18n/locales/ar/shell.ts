import type en from '../en/shell';
import type { Translation } from '../types';

/** Arabic UI text: shell. Must cover every English key; Arabic plurals may add _zero, _two, _few, _many. */
const shell: Translation<typeof en> = {
  language: 'اللغة',
  switchLanguage: 'التبديل إلى {language}',
};

export default shell;

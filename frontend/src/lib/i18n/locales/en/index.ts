import common from './common';
import shell from './shell';
import dashboard from './dashboard';
import projects from './projects';
import tasks from './tasks';
import users from './users';
import roles from './roles';
import auth from './auth';
import profile from './profile';

/** English is the source of truth: its keys define TranslationKey. */
const en = { common, shell, dashboard, projects, tasks, users, roles, auth, profile };

export type Dictionary = typeof en;
export default en;

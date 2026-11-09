import common from './common';
import shell from './shell';
import dashboard from './dashboard';
import projects from './projects';
import tasks from './tasks';
import users from './users';
import roles from './roles';
import auth from './auth';
import profile from './profile';
import type { Dictionary } from '../en';

const ar: Record<keyof Dictionary, Record<string, string>> = { common, shell, dashboard, projects, tasks, users, roles, auth, profile };

export default ar;

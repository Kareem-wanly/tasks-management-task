import { z } from 'zod';
import { isProjectIconName, type ProjectIconName } from '@/constants/projectIcons';
import { isTint, tintFor, type Tint } from '@/constants/tints';
import { createJsonPreference } from './jsonPreference';

export interface ProjectAppearance {
  tint: Tint;
  icon: ProjectIconName;
}

const appearanceSchema = z.record(
  z.string(),
  z.object({
    tint: z.custom<Tint>(isTint),
    icon: z.custom<ProjectIconName>(isProjectIconName),
  })
);

/**
 * Per-project colour and icon. A visual preference only, so it lives in this browser
 * (no backend field); projects without a choice get a stable colour from their id.
 */
const store = createJsonPreference('project-appearance', appearanceSchema, {});

export function defaultAppearance(projectId: number): ProjectAppearance {
  return { tint: tintFor(`project-${projectId}`), icon: 'folder' };
}

export function useProjectAppearance(projectId: number): ProjectAppearance {
  const all = store.useValue();
  return all[String(projectId)] ?? defaultAppearance(projectId);
}

export function setProjectAppearance(projectId: number, change: Partial<ProjectAppearance>): void {
  store.set((all) => {
    const current = all[String(projectId)] ?? defaultAppearance(projectId);
    return { ...all, [String(projectId)]: { ...current, ...change } };
  });
}

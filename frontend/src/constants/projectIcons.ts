import {
  BookOpenIcon,
  BriefcaseIcon,
  ChartColumnIcon,
  CodeIcon,
  FolderIcon,
  GlobeIcon,
  HeartIcon,
  MegaphoneIcon,
  PaletteIcon,
  RocketIcon,
  StarIcon,
  WrenchIcon,
  type LucideIcon,
} from 'lucide-react';

/** Icons a project can wear (chosen per browser, see lib/preferences/projectAppearance.ts). */
export const PROJECT_ICONS = {
  folder: FolderIcon,
  rocket: RocketIcon,
  code: CodeIcon,
  palette: PaletteIcon,
  megaphone: MegaphoneIcon,
  briefcase: BriefcaseIcon,
  chart: ChartColumnIcon,
  book: BookOpenIcon,
  wrench: WrenchIcon,
  globe: GlobeIcon,
  heart: HeartIcon,
  star: StarIcon,
} satisfies Record<string, LucideIcon>;

export type ProjectIconName = keyof typeof PROJECT_ICONS;

export function isProjectIconName(value: unknown): value is ProjectIconName {
  return typeof value === 'string' && value in PROJECT_ICONS;
}

export const PROJECT_ICON_NAMES: ProjectIconName[] = Object.keys(PROJECT_ICONS).filter(isProjectIconName);

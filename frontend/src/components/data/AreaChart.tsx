import { useId, useState, type MouseEvent } from 'react';
import { motion } from 'motion/react';
import { EASE } from '@/lib/motion';

export interface ChartPoint {
  /** Full label for the tooltip, e.g. "Oct 14". */
  label: string;
  value: number;
}

interface AreaChartProps {
  points: ChartPoint[];
  /** Unit after the value in the tooltip, e.g. "tasks". */
  unit?: string;
  height?: number;
}

const W = 1000;
const PAD_TOP = 12;
const GRID_LINES = 4;

/** Single-series brand area chart: the line draws itself, a dashed grid, an HTML tooltip. No legend. */
export default function AreaChart({ points, unit = '', height = 240 }: AreaChartProps) {
  const gradientId = useId();
  const [active, setActive] = useState<number | null>(null);

  if (points.length < 2) return null;

  const max = Math.max(...points.map((p) => p.value), 1);
  const ceiling = Math.ceil(max / GRID_LINES) * GRID_LINES;
  const xAt = (i: number) => (i / (points.length - 1)) * W;
  const yAt = (v: number) => PAD_TOP + (1 - v / ceiling) * (height - PAD_TOP);
  const line = smoothPath(points.map((p, i) => [xAt(i), yAt(p.value)]));
  const area = `${line} L ${W} ${height} L 0 ${height} Z`;

  const onMove = (event: MouseEvent<HTMLDivElement>) => {
    const rect = event.currentTarget.getBoundingClientRect();
    const ratio = (event.clientX - rect.left) / rect.width;
    const physical = Math.round(ratio * (points.length - 1));
    setActive(Math.min(points.length - 1, Math.max(0, physical)));
  };

  const activePoint = active === null ? null : points[active];

  return (
    <div className="relative" dir="ltr" onMouseMove={onMove} onMouseLeave={() => setActive(null)}>
      <svg viewBox={`0 0 ${W} ${height}`} preserveAspectRatio="none" className="block w-full" style={{ height }} role="img" aria-label={`Chart of ${unit || 'values'} over time`}>
        <defs>
          <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor="var(--brand)" stopOpacity={0.28} />
            <stop offset="100%" stopColor="var(--brand)" stopOpacity={0} />
          </linearGradient>
        </defs>
        {Array.from({ length: GRID_LINES + 1 }, (_, i) => {
          const y = PAD_TOP + (i / GRID_LINES) * (height - PAD_TOP);
          return <line key={i} x1={0} x2={W} y1={y} y2={y} stroke="var(--border)" strokeDasharray="3 4" vectorEffect="non-scaling-stroke" />;
        })}
        <motion.path
          d={area}
          fill={`url(#${gradientId})`}
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ duration: 0.8, ease: EASE, delay: 0.5 }}
        />
        <motion.path
          d={line}
          fill="none"
          stroke="var(--brand)"
          strokeWidth={2}
          strokeLinecap="round"
          vectorEffect="non-scaling-stroke"
          initial={{ pathLength: 0 }}
          animate={{ pathLength: 1 }}
          transition={{ duration: 1.4, ease: EASE }}
        />
        {active !== null && activePoint && (
          <line x1={xAt(active)} x2={xAt(active)} y1={PAD_TOP} y2={height} stroke="var(--brand)" strokeOpacity={0.4} vectorEffect="non-scaling-stroke" />
        )}
      </svg>

      {active !== null && activePoint && (
        <>
          <span
            className="pointer-events-none absolute size-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-card bg-brand shadow-soft"
            style={{ left: `${(xAt(active) / W) * 100}%`, top: yAt(activePoint.value) }}
          />
          <div
            className="pointer-events-none absolute top-0 z-10 rounded-lg border border-border bg-popover px-3 py-2 text-xs shadow-lift"
            style={{
              left: `${(xAt(active) / W) * 100}%`,
              transform: `translateX(${active > points.length / 2 ? 'calc(-100% - 12px)' : '12px'})`,
            }}
          >
            <div className="text-muted-foreground">{activePoint.label}</div>
            <div className="mt-0.5 font-medium tabular-nums">
              {activePoint.value} {unit}
            </div>
          </div>
        </>
      )}

      <div className="mt-3 flex justify-between text-[11px] text-muted-foreground tabular-nums">
        <span>{points[0]?.label}</span>
        <span>{points[Math.floor(points.length / 2)]?.label}</span>
        <span>{points[points.length - 1]?.label}</span>
      </div>
    </div>
  );
}

/** Smooth curve through the points (Catmull-Rom converted to cubic Béziers). */
function smoothPath(pts: [number, number][]): string {
  const [first] = pts;
  if (!first) return '';
  let d = `M ${first[0]} ${first[1]}`;
  for (let i = 0; i < pts.length - 1; i += 1) {
    const p0 = pts[i - 1] ?? pts[i];
    const p1 = pts[i];
    const p2 = pts[i + 1];
    const p3 = pts[i + 2] ?? p2;
    if (!p0 || !p1 || !p2 || !p3) continue;
    const c1 = [p1[0] + (p2[0] - p0[0]) / 6, p1[1] + (p2[1] - p0[1]) / 6];
    const c2 = [p2[0] - (p3[0] - p1[0]) / 6, p2[1] - (p3[1] - p1[1]) / 6];
    d += ` C ${c1[0]} ${c1[1]}, ${c2[0]} ${c2[1]}, ${p2[0]} ${p2[1]}`;
  }
  return d;
}

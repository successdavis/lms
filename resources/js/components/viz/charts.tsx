import { useRef, useState, type ReactNode } from 'react';

/*
 * Small chart kit following the dataviz method: one data hue per chart
 * (categorical slot 1), thin marks with 4px rounded data-ends square at the
 * baseline, hairline solid gridlines, text in text tokens (never the series
 * colour), selective direct labels, a per-mark hover/focus tooltip, and a
 * table-view twin for every chart. Colours come from the .viz-root tokens.
 */

export interface Datum {
    label: string;
    value: number;
}

export const compact = (n: number) => new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 }).format(n);
export const nairaCompact = (n: number) => `₦${compact(n)}`;
export const naira = (n: number) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', maximumFractionDigits: 0 }).format(n);

interface TipState {
    x: number;
    y: number;
    value: string;
    label: string;
}

/** Tooltip shared by a chart: value leads (strong), label follows, keyed by a short line. */
function useTooltip() {
    const containerRef = useRef<HTMLDivElement>(null);
    const [tip, setTip] = useState<TipState | null>(null);

    const show = (target: Element, value: string, label: string) => {
        const box = containerRef.current?.getBoundingClientRect();
        const mark = target.getBoundingClientRect();
        if (!box) return;
        setTip({ x: mark.left - box.left + mark.width / 2, y: mark.top - box.top, value, label });
    };

    const node = tip ? (
        <div
            role="status"
            className="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full rounded-md border px-2.5 py-1.5 text-xs shadow-sm"
            style={{ left: tip.x, top: tip.y - 6, background: 'var(--viz-surface)', borderColor: 'var(--viz-border)' }}
        >
            <div className="font-semibold" style={{ color: 'var(--viz-text-primary)' }}>
                {tip.value}
            </div>
            <div className="flex items-center gap-1.5 whitespace-nowrap" style={{ color: 'var(--viz-text-secondary)' }}>
                <span className="inline-block h-0.5 w-3 rounded" style={{ background: 'var(--viz-series-1)' }} />
                {tip.label}
            </div>
        </div>
    ) : null;

    return { containerRef, show, hide: () => setTip(null), node };
}

/** Figure shell: title, optional subtitle, and the chart <-> table toggle. */
export function ChartCard({ title, subtitle, table, children }: { title: string; subtitle?: string; table: Datum[] | null; children: ReactNode }) {
    const [showTable, setShowTable] = useState(false);

    return (
        <figure className="rounded-xl border p-4" style={{ background: 'var(--viz-surface)', borderColor: 'var(--viz-border)' }}>
            <div className="mb-3 flex items-start justify-between gap-2">
                <div>
                    <h3 className="text-sm font-semibold" style={{ color: 'var(--viz-text-primary)' }}>
                        {title}
                    </h3>
                    {subtitle && (
                        <p className="text-xs" style={{ color: 'var(--viz-text-secondary)' }}>
                            {subtitle}
                        </p>
                    )}
                </div>
                {table && table.length > 0 && (
                    <button
                        type="button"
                        aria-pressed={showTable}
                        onClick={() => setShowTable(!showTable)}
                        className="rounded border px-2 py-0.5 text-xs"
                        style={{ color: 'var(--viz-text-secondary)', borderColor: 'var(--viz-border)' }}
                    >
                        {showTable ? 'Chart' : 'Table'}
                    </button>
                )}
            </div>
            {showTable && table ? <DataTable rows={table} /> : children}
        </figure>
    );
}

function DataTable({ rows, format = (n: number) => n.toLocaleString() }: { rows: Datum[]; format?: (n: number) => string }) {
    return (
        <table className="w-full text-sm" style={{ color: 'var(--viz-text-primary)' }}>
            <tbody>
                {rows.map((row) => (
                    <tr key={row.label} className="border-b last:border-0" style={{ borderColor: 'var(--viz-grid)' }}>
                        <td className="py-1.5" style={{ color: 'var(--viz-text-secondary)' }}>
                            {row.label}
                        </td>
                        <td className="py-1.5 text-right" style={{ fontVariantNumeric: 'tabular-nums' }}>
                            {format(row.value)}
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

export function EmptyState({ children }: { children: ReactNode }) {
    return (
        <p className="py-6 text-center text-sm" style={{ color: 'var(--viz-text-muted)' }}>
            {children}
        </p>
    );
}

/**
 * Horizontal bars for ordered or named categories: one series, one hue,
 * value at the bar tip. Labels sit above their bars so long names never
 * truncate and bars keep the full card width. Rows are focusable so keyboard users get the tooltip.
 */
export function HBarChart({
    data,
    format = (n: number) => n.toLocaleString(),
    unit = '',
}: {
    data: Datum[];
    format?: (n: number) => string;
    unit?: string;
}) {
    const { containerRef, show, hide, node } = useTooltip();
    const max = Math.max(0, ...data.map((d) => d.value));

    if (data.length === 0 || max === 0) {
        return <EmptyState>No data yet.</EmptyState>;
    }

    return (
        <div ref={containerRef} className="relative space-y-2">
            {data.map((d) => {
                const ratio = d.value / max;
                return (
                    <div
                        key={d.label}
                        tabIndex={0}
                        role="img"
                        aria-label={`${d.label}: ${format(d.value)}${unit}`}
                        className="group block rounded outline-none focus-visible:ring-2"
                        onPointerEnter={(e) => show(e.currentTarget.querySelector('[data-mark]')!, `${format(d.value)}${unit}`, d.label)}
                        onPointerLeave={hide}
                        onFocus={(e) => show(e.currentTarget.querySelector('[data-mark]')!, `${format(d.value)}${unit}`, d.label)}
                        onBlur={hide}
                    >
                        <span className="block text-xs" style={{ color: 'var(--viz-text-secondary)' }}>
                            {d.label}
                        </span>
                        <span className="flex min-h-6 items-center gap-2">
                            <span
                                data-mark
                                className="h-4 rounded-r-[4px] transition-colors group-hover:[background:var(--viz-series-1-hover)]"
                                style={{
                                    width: `calc((100% - 4.5rem) * ${ratio})`,
                                    minWidth: d.value > 0 ? 2 : 0,
                                    background: 'var(--viz-series-1)',
                                }}
                            />
                            <span className="text-xs font-medium" style={{ color: 'var(--viz-text-primary)' }}>
                                {format(d.value)}
                            </span>
                        </span>
                    </div>
                );
            })}
            {node}
        </div>
    );
}

function niceTicks(max: number, count = 3): number[] {
    if (max <= 0) return [0];
    const raw = max / count;
    const magnitude = 10 ** Math.floor(Math.log10(raw));
    const step = [1, 2, 2.5, 5, 10].map((m) => m * magnitude).find((s) => s >= raw) ?? raw;
    const ticks: number[] = [];
    for (let t = 0; t <= max + step * 0.0001; t += step) ticks.push(t);
    if (ticks[ticks.length - 1] < max) ticks.push(ticks[ticks.length - 1] + step);
    return ticks;
}

/**
 * Columns over time: hairline grid at clean ticks, columns <= 24px wide with
 * 4px rounded caps, direct labels only on the latest and the peak column.
 */
export function ColumnChart({
    data,
    format = compact,
    tooltipFormat = (n: number) => n.toLocaleString(),
}: {
    data: Datum[];
    format?: (n: number) => string;
    tooltipFormat?: (n: number) => string;
}) {
    const { containerRef, show, hide, node } = useTooltip();
    const max = Math.max(0, ...data.map((d) => d.value));

    if (data.length === 0 || max === 0) {
        return <EmptyState>No payments recorded in this period.</EmptyState>;
    }

    const ticks = niceTicks(max);
    const top = ticks[ticks.length - 1];
    const plotHeight = 160;
    const latest = data.length - 1;
    const peak = data.reduce((best, d, i) => (d.value > data[best].value ? i : best), 0);

    return (
        <div ref={containerRef} className="relative">
            <div className="flex">
                {/* y-axis tick labels */}
                <div className="relative mr-2 w-12 shrink-0" style={{ height: plotHeight }}>
                    {ticks.map((t) => (
                        <span
                            key={t}
                            className="absolute right-0 text-[10px]"
                            style={{
                                bottom: `${(t / top) * plotHeight}px`,
                                color: 'var(--viz-text-muted)',
                                fontVariantNumeric: 'tabular-nums',
                                transform: 'translateY(50%)',
                            }}
                        >
                            {format(t)}
                        </span>
                    ))}
                </div>
                <div className="flex-1">
                    <div className="relative flex items-end justify-around" style={{ height: plotHeight }}>
                        {/* gridlines share the plot box with the columns; the zero line is the baseline */}
                        {ticks.map((t) => (
                            <div
                                key={t}
                                aria-hidden
                                className="pointer-events-none absolute right-0 left-0"
                                style={{
                                    bottom: `${(t / top) * plotHeight}px`,
                                    height: 1,
                                    background: t === 0 ? 'var(--viz-axis)' : 'var(--viz-grid)',
                                }}
                            />
                        ))}
                        {data.map((d, i) => {
                            const labelled = i === latest || i === peak;
                            return (
                                <div
                                    key={d.label}
                                    tabIndex={0}
                                    role="img"
                                    aria-label={`${d.label}: ${tooltipFormat(d.value)}`}
                                    className="group relative flex h-full flex-1 items-end justify-center rounded outline-none focus-visible:ring-2"
                                    onPointerEnter={(e) =>
                                        show(e.currentTarget.querySelector('[data-mark]') ?? e.currentTarget, tooltipFormat(d.value), d.label)
                                    }
                                    onPointerLeave={hide}
                                    onFocus={(e) =>
                                        show(e.currentTarget.querySelector('[data-mark]') ?? e.currentTarget, tooltipFormat(d.value), d.label)
                                    }
                                    onBlur={hide}
                                >
                                    {labelled && d.value > 0 && (
                                        <span
                                            className="absolute text-[10px] font-medium"
                                            style={{ bottom: `${(d.value / top) * plotHeight + 4}px`, color: 'var(--viz-text-primary)' }}
                                        >
                                            {format(d.value)}
                                        </span>
                                    )}
                                    <span
                                        data-mark
                                        className="w-full max-w-6 rounded-t-[4px] transition-colors group-hover:[background:var(--viz-series-1-hover)]"
                                        style={{
                                            height: `${(d.value / top) * plotHeight}px`,
                                            minHeight: d.value > 0 ? 2 : 0,
                                            background: 'var(--viz-series-1)',
                                        }}
                                    />
                                </div>
                            );
                        })}
                    </div>
                    {/* x-axis band sits inside the card, so no nested scroll */}
                    <div className="mt-1 flex justify-around">
                        {data.map((d) => (
                            <span key={d.label} className="flex-1 text-center text-[10px]" style={{ color: 'var(--viz-text-muted)' }}>
                                {d.label}
                            </span>
                        ))}
                    </div>
                </div>
            </div>
            {node}
        </div>
    );
}

/** Stat tile: sentence-case label, proportional-figure value, optional hint. */
export function StatTile({ label, value, hint, children }: { label: string; value: ReactNode; hint?: ReactNode; children?: ReactNode }) {
    return (
        <div className="rounded-xl border p-4" style={{ background: 'var(--viz-surface)', borderColor: 'var(--viz-border)' }}>
            <div className="text-xs" style={{ color: 'var(--viz-text-secondary)' }}>
                {label}
            </div>
            <div className="mt-1 text-2xl font-semibold" style={{ color: 'var(--viz-text-primary)' }}>
                {value}
            </div>
            {hint && (
                <div className="mt-1 text-xs" style={{ color: 'var(--viz-text-muted)' }}>
                    {hint}
                </div>
            )}
            {children}
        </div>
    );
}

/**
 * Meter: fill on a lighter step of the same ramp. With warnAt set, the fill
 * turns to the warning status colour at that ratio, always with a text label so colour
 * never carries the state alone.
 */
export function Meter({ value, max, label, warnAt }: { value: number; max: number; label: string; warnAt?: number }) {
    const ratio = max > 0 ? Math.min(1, value / max) : 0;
    const warn = warnAt !== undefined && max > 0 && ratio >= warnAt;

    return (
        <div className="mt-2">
            <div
                className="h-2 w-full overflow-hidden rounded-full"
                style={{ background: 'var(--viz-track)' }}
                role="meter"
                aria-label={label}
                aria-valuemin={0}
                aria-valuemax={max}
                aria-valuenow={value}
            >
                <div
                    className="h-full rounded-full"
                    style={{ width: `${ratio * 100}%`, background: warn ? 'var(--viz-warning)' : 'var(--viz-series-1)' }}
                />
            </div>
            {warn && (
                <div className="mt-1 flex items-center gap-1 text-xs" style={{ color: 'var(--viz-text-secondary)' }}>
                    <span aria-hidden>⚠</span> Nearly full
                </div>
            )}
        </div>
    );
}

/**
 * Tailwind's rose/amber/emerald/sky/violet/fuchsia 500s, as literal values —
 * carets are drawn with inline styles, so the palette can't live in a class.
 */
const palette = [
    '#f43f5e',
    '#f59e0b',
    '#10b981',
    '#0ea5e9',
    '#8b5cf6',
    '#d946ef',
];

/** One stable colour per person, so their caret and their avatar always match. */
export function presenceColor(id: number): string {
    return palette[id % palette.length];
}

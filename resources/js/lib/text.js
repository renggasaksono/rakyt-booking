/**
 * Copy lookup with Laravel-style ":name" replacements. All copy comes from
 * lang/id via page props (ADR-0007).
 */
export function t(copy, path, replacements = {}) {
    const value = path.split('.').reduce((node, key) => (node == null ? undefined : node[key]), copy);

    if (typeof value !== 'string') {
        return path;
    }

    return Object.entries(replacements)
        .sort(([a], [b]) => b.length - a.length)
        .reduce((text, [key, replacement]) => text.split(`:${key}`).join(String(replacement)), value);
}

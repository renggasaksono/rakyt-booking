/**
 * "Tampilkan lebih banyak" lists: merge a freshly loaded page into what is
 * shown. Rows already shown are updated in place (e.g. after a quick edit);
 * new rows are appended in the order the server sent them.
 */
export function mergeById(shown, incoming) {
    const byId = new Map(incoming.map((item) => [item.id, item]));
    const merged = shown.map((item) => byId.get(item.id) ?? item);
    const known = new Set(shown.map((item) => item.id));

    return [...merged, ...incoming.filter((item) => !known.has(item.id))];
}

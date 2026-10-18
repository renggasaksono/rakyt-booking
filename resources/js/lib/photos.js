/**
 * Whether a new photo order puts a different photo first, i.e. changes the
 * trip's cover (first = cover, AC-7). Used to confirm cover changes on
 * published trips (F-003 Q13).
 */
export function changesCover(beforeIds, afterIds) {
    return beforeIds.length > 0 && afterIds.length > 0 && beforeIds[0] !== afterIds[0];
}

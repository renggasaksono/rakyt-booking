/**
 * Draft autosave (F-003 AC-3): saves changed fields 2 s after the last change
 * (or right away on blur), one request at a time. A failed save keeps the
 * changes, reports "error" and is retried with retry() (e.g. on reconnect).
 */
export function createAutosave({ save, delay = 2000, onChange = () => {}, timers = globalThis }) {
    let pending = {};
    let timer = null;
    let inFlight = null;
    let state = 'idle';
    let savedAt = null;

    const emit = () => onChange({ state, savedAt });

    const setState = (next) => {
        state = next;
        emit();
    };

    async function flush() {
        if (timer !== null) {
            timers.clearTimeout(timer);
            timer = null;
        }

        if (inFlight) {
            await inFlight.catch(() => {});

            return flush();
        }

        if (Object.keys(pending).length === 0) {
            return;
        }

        const batch = pending;
        pending = {};
        setState('saving');

        try {
            inFlight = Promise.resolve(save(batch));
        } catch (error) {
            inFlight = Promise.reject(error);
        }

        try {
            const result = await inFlight;
            savedAt = result?.savedAt ?? savedAt;
            inFlight = null;
            setState(Object.keys(pending).length > 0 ? 'dirty' : 'saved');
        } catch (error) {
            inFlight = null;
            // Keep the failed changes unless newer edits replaced them.
            pending = { ...batch, ...pending };
            setState(error?.status === 422 ? 'invalid' : 'error');
        }
    }

    return {
        queue(fields) {
            pending = { ...pending, ...fields };
            setState('dirty');

            if (timer !== null) {
                timers.clearTimeout(timer);
            }

            timer = timers.setTimeout(() => {
                timer = null;
                flush();
            }, delay);
        },
        flush,
        retry: flush,
        hasUnsaved: () => Object.keys(pending).length > 0 || inFlight !== null,
        get state() {
            return state;
        },
        get savedAt() {
            return savedAt;
        },
    };
}

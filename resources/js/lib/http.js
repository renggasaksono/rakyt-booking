/**
 * JSON requests for autosave and photo uploads, which must not re-render the
 * page (Inertia visits do). Sends Laravel's XSRF token from its cookie.
 */

function xsrfToken() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

function headers(extra = {}) {
    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': xsrfToken(),
        ...extra,
    };
}

export class HttpError extends Error {
    constructor(status, body) {
        super(body?.message || `HTTP ${status}`);
        this.status = status;
        this.body = body;
    }
}

export async function sendJson(method, url, data) {
    let response;

    try {
        response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: headers({ 'Content-Type': 'application/json' }),
            body: data === undefined ? undefined : JSON.stringify(data),
        });
    } catch {
        throw new HttpError(0, null);
    }

    const body = await response.json().catch(() => null);

    if (!response.ok) {
        throw new HttpError(response.status, body);
    }

    return body;
}

/** Upload one file with progress (0–100). */
export function upload(url, field, file, onProgress) {
    return new Promise((resolve, reject) => {
        const request = new XMLHttpRequest();
        const form = new FormData();
        form.append(field, file);

        request.open('POST', url);
        request.withCredentials = true;
        Object.entries(headers()).forEach(([name, value]) => request.setRequestHeader(name, value));

        request.upload.onprogress = (event) => {
            if (event.lengthComputable) {
                onProgress(Math.round((event.loaded / event.total) * 100));
            }
        };
        request.onload = () => {
            let body;

            try {
                body = JSON.parse(request.responseText);
            } catch {
                body = null;
            }

            if (request.status >= 200 && request.status < 300) {
                resolve(body);
            } else {
                reject(new HttpError(request.status, body));
            }
        };
        request.onerror = () => reject(new HttpError(0, null));
        request.send(form);
    });
}

/** First validation message from a Laravel 422 body. */
export function firstError(error, fallback) {
    const errors = error?.body?.errors;

    if (errors) {
        const first = Object.values(errors)[0];

        return Array.isArray(first) ? first[0] : String(first);
    }

    return error?.body?.message || fallback;
}

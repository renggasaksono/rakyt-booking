/**
 * F-002 AC-33 / ADR-0024: a public page loads only the shared runtime and its
 * own chunk, at most 150 KB of gzipped JavaScript. Reads the Vite manifest of
 * `npm run build` and follows static imports from the app entry and each
 * public page. Run with `npm run check:bundle`.
 */
import { readFileSync } from 'node:fs';
import { gzipSync } from 'node:zlib';

const BUILD = 'public/build';
const BUDGET = 150 * 1024;
const ENTRY = 'resources/js/app.js';
// F-005: the booking form and the customer booking page are public phone pages too.
const PUBLIC_PAGES = [
    'resources/js/pages/Public/Seller.vue',
    'resources/js/pages/Public/Trip.vue',
    'resources/js/pages/Public/BookingForm.vue',
    'resources/js/pages/Public/Booking.vue',
];

const manifest = JSON.parse(readFileSync(`${BUILD}/manifest.json`, 'utf8'));
let failed = false;

function closure(keys) {
    const seen = new Set();
    const visit = (key) => {
        if (seen.has(key)) {
            return;
        }

        if (!manifest[key]) {
            throw new Error(`${key} is not in the Vite manifest; run npm run build first.`);
        }

        seen.add(key);
        (manifest[key].imports ?? []).forEach(visit);
    };

    keys.forEach(visit);

    return [...seen];
}

for (const page of PUBLIC_PAGES) {
    const chunks = closure([ENTRY, page]);
    const gzip = chunks.reduce((total, key) => total + gzipSync(readFileSync(`${BUILD}/${manifest[key].file}`)).length, 0);
    const otherPages = chunks.filter((key) => manifest[key].src?.startsWith('resources/js/pages/') && !PUBLIC_PAGES.includes(manifest[key].src));

    console.log(`${page}: ${(gzip / 1024).toFixed(1)} KB gzip in ${chunks.length} chunks (budget ${BUDGET / 1024} KB)`);

    if (gzip > BUDGET) {
        console.error(`  over budget by ${((gzip - BUDGET) / 1024).toFixed(1)} KB`);
        failed = true;
    }

    if (otherPages.length) {
        console.error(`  loads other pages: ${otherPages.join(', ')}`);
        failed = true;
    }
}

process.exit(failed ? 1 : 0);

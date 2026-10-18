import { defineConfig } from 'vitest/config';

// Unit tests for the seller UI's pure helpers (resources/js/lib). No DOM.
export default defineConfig({
    test: {
        include: ['tests/js/**/*.test.js'],
        environment: 'node',
    },
});

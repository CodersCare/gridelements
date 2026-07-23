import { defineConfig } from 'vitest/config';
import { fileURLToPath, URL } from 'node:url';

const mock = (name) => fileURLToPath(new URL(`./Tests/JavaScript/__mocks__/${name}`, import.meta.url));

export default defineConfig({
    test: {
        environment: 'jsdom',
        include: ['Tests/JavaScript/**/*.spec.js'],
    },
    resolve: {
        alias: {
            'interactjs': mock('interactjs.js'),
            '@typo3/core/document-service.js': mock('document-service.js'),
            '@typo3/core/event/regular-event.js': mock('regular-event.js'),
            '@typo3/backend/ajax-data-handler.js': mock('ajax-data-handler.js'),
            '@typo3/backend/icons.js': mock('icons.js'),
            '@typo3/backend/modal.js': mock('modal.js'),
            '@typo3/backend/severity.js': mock('severity.js'),
            '@typo3/backend/enum/severity.js': mock('enum-severity.js'),
            '@typo3/backend/viewport.js': mock('viewport.js'),
            '@typo3/backend/storage/persistent.js': mock('persistent-storage.js'),
            '@typo3/backend/element/icon-element.js': mock('icon-element.js'),
            '@typo3/core/literals.js': mock('literals.js'),
            'bootstrap': mock('bootstrap.js'),
            '@typo3/core/security-utility.js': mock('security-utility.js'),
        },
    },
});

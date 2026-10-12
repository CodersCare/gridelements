import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { existsSync, readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import jQuery from 'jquery';

// CMS 11 core from the branch dev toolchain (composer web-dir "public")
const extRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../..') + '/';
const sysext = extRoot + 'public/typo3/sysext/';
const requireJsFile = sysext + 'core/Resources/Public/JavaScript/Contrib/require.js';
const coreContextMenuFile = sysext + 'backend/Resources/Public/JavaScript/ContextMenu.js';
const hasCms11Core = existsSync(requireJsFile)
    && existsSync(coreContextMenuFile)
    && readFileSync(coreContextMenuFile, 'utf8').includes('define([');

const moduleName = 'TYPO3/CMS/Gridelements/ContextMenuActions';
const returnUrl = encodeURIComponent('/typo3/module/web/layout?id=92');
const actionUrl = '/typo3/record/commit?token=abc&CB%5Bpaste%5D=tt_content%7C-22&CB%5Bpad%5D=normal'
    + '&CB%5Bupdate%5D%5BcolPos%5D=2&CB%5Bupdate%5D%5Bsys_language_uid%5D=0'
    + '&CB%5Bupdate%5D%5Btx_gridelements_container%5D=0&CB%5Bupdate%5D%5Btx_gridelements_columns%5D=0';
const referenceUrl = actionUrl + '&reference=1';

const viewport = { ContentContainer: { setUrl: vi.fn() } };
const modal = {
    confirm: vi.fn((title, message, severity, buttons) => {
        const $modal = jQuery('<div class="modal"></div>');
        buttons.forEach((button) => $modal.append(jQuery('<button></button>').attr('name', button.name)));
        jQuery(document.body).append($modal);
        modal.current = $modal;
        return $modal;
    }),
    dismiss: vi.fn(() => {
        modal.current?.remove();
        modal.current = null;
    }),
    clickButton: (name) => modal.current.find('button[name="' + name + '"]').trigger('button.clicked'),
    current: null,
};

const requireModule = (name) => new Promise((resolve, reject) => globalThis.requirejs([name], resolve, reject));

/** Menu item as rendered by the CMS 11 core (HTML string, attributes passed as provided by ItemProvider) */
const menuItem = (attributes) => {
    const attributeString = Object.entries(attributes).map(([key, value]) => ' ' + key + '="' + value + '"').join('');
    return jQuery('<li class="list-group-item"' + attributeString + '></li>');
};

describe.skipIf(!hasCms11Core)('CMS 11 AMD context menu actions', () => {
    let actions;

    beforeAll(async () => {
        globalThis.TYPO3 = { lang: {}, settings: { ajaxUrls: {} } };
        globalThis.list_frame = { document: { location: { pathname: '/typo3/module/web/layout', search: '?id=92' } } };
        (0, eval)(readFileSync(requireJsFile, 'utf8'));
        // file system loader replacing the <script> loader of the real RequireJS
        globalThis.requirejs.load = (context, name, url) => {
            (0, eval)(readFileSync(url.replace(/\?.*$/, ''), 'utf8'));
            context.completeLoad(name);
        };
        globalThis.requirejs.config({
            paths: {
                'TYPO3/CMS/Core': sysext + 'core/Resources/Public/JavaScript',
                'TYPO3/CMS/Backend': sysext + 'backend/Resources/Public/JavaScript',
                'TYPO3/CMS/Gridelements': extRoot + 'Resources/Public/JavaScript',
            },
        });
        globalThis.define('jquery', [], () => jQuery);
        globalThis.define('TYPO3/CMS/Core/Ajax/AjaxRequest', [], () => class {});
        globalThis.define('TYPO3/CMS/Backend/ContextMenuActions', [], () => ({}));
        globalThis.define('TYPO3/CMS/Backend/Modal', [], () => modal);
        globalThis.define('TYPO3/CMS/Backend/Viewport', [], () => viewport);
        actions = await requireModule(moduleName);
    });

    beforeEach(() => {
        viewport.ContentContainer.setUrl.mockClear();
        modal.confirm.mockClear();
        modal.dismiss.mockClear();
    });

    it('is a real AMD module exporting the callback actions of the provider', () => {
        expect(typeof actions.pasteAfter).toBe('function');
        expect(typeof actions.pasteReference).toBe('function');
        expect(typeof actions.pasteInto).toBe('function');
    });

    it('navigates to the decoded action URL including the explicit zero destination and the return URL', () => {
        actions.pasteAfter.bind(menuItem({ 'data-action-url': actionUrl.replaceAll('&', '&amp;') }))('tt_content', 22);

        expect(modal.confirm).not.toHaveBeenCalled();
        expect(viewport.ContentContainer.setUrl).toHaveBeenCalledWith(actionUrl + '&redirect=' + returnUrl);
    });

    it('delegates pasteAfter and pasteReference to pasteInto with the negative anchor uid and the menu item', () => {
        const pasteInto = actions.pasteInto;
        const calls = [];
        actions.pasteInto = function (...args) {
            calls.push([this, ...args]);
        };
        try {
            const $item = menuItem({ 'data-action-url': actionUrl });
            actions.pasteAfter.bind($item)('tt_content', 22);
            actions.pasteReference.bind($item)('tt_content', 22);
            expect(calls.map((call) => call.slice(1))).toEqual([['tt_content', -22], ['tt_content', -22]]);
            expect(calls.every((call) => call[0].get(0) === $item.get(0))).toBe(true);
        } finally {
            actions.pasteInto = pasteInto;
        }
    });

    it('asks for confirmation with the core texts and only navigates on "ok"', () => {
        const $item = menuItem({
            'data-action-url': referenceUrl.replaceAll('&', '&amp;'),
            'data-title': 'Paste',
            'data-message': 'Copy &quot;A&quot; after &quot;B&quot;?',
            'data-button-close-text': 'Cancel it',
            'data-button-ok-text': 'Do it',
        });

        actions.pasteReference.bind($item)('tt_content', 22);
        const [title, message, severity, buttons] = modal.confirm.mock.calls[0];
        expect([title, message, severity]).toEqual(['Paste', 'Copy "A" after "B"?', 1]);
        expect(buttons.map((button) => [button.name, button.text])).toEqual([['cancel', 'Cancel it'], ['ok', 'Do it']]);

        modal.clickButton('cancel');
        expect(viewport.ContentContainer.setUrl).not.toHaveBeenCalled();
        expect(modal.dismiss).toHaveBeenCalledTimes(1);

        actions.pasteReference.bind($item)('tt_content', 22);
        modal.clickButton('ok');
        expect(viewport.ContentContainer.setUrl).toHaveBeenCalledWith(referenceUrl + '&redirect=' + returnUrl);
        expect(modal.dismiss).toHaveBeenCalledTimes(2);
    });

    it('is loaded and dispatched by the real CMS 11 core context menu', async () => {
        const contextMenu = await requireModule('TYPO3/CMS/Backend/ContextMenu');
        const attributes = { 'data-callback-module': moduleName, 'data-action-url': actionUrl.replaceAll('&', '&amp;') };
        contextMenu.record = { table: 'tt_content', uid: 22 };
        contextMenu.populateData({
            pasteAfter: { type: 'item', label: 'Paste after', icon: '', callbackAction: 'pasteAfter', additionalAttributes: attributes },
            pastereference: {
                type: 'item',
                label: 'Paste reference',
                icon: '',
                callbackAction: 'pasteReference',
                additionalAttributes: { ...attributes, 'data-action-url': referenceUrl.replaceAll('&', '&amp;') },
            },
        }, 0);

        document.querySelector('#contentMenu0 [data-callback-action="pasteAfter"]').click();
        await vi.waitFor(() => expect(viewport.ContentContainer.setUrl).toHaveBeenCalledTimes(1));
        document.querySelector('#contentMenu0 [data-callback-action="pasteReference"]').click();
        await vi.waitFor(() => expect(viewport.ContentContainer.setUrl).toHaveBeenCalledTimes(2));

        expect(viewport.ContentContainer.setUrl.mock.calls).toEqual([
            [actionUrl + '&redirect=' + returnUrl],
            [referenceUrl + '&redirect=' + returnUrl],
        ]);
    });
});

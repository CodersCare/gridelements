import { beforeEach, describe, expect, it } from 'vitest';

let ContextMenuActions;
let Viewport;
let Modal;

beforeEach(async () => {
    globalThis.TYPO3 = { lang: {} };
    globalThis.list_frame = {
        document: { location: { pathname: '/typo3/module/web/layout', search: '?id=92' } },
    };
    const module = await import('../../Resources/Public/JavaScript/context-menu-actions.js?t=' + Math.random());
    ContextMenuActions = module.default;
    Viewport = (await import('@typo3/backend/viewport.js')).default;
    Modal = (await import('@typo3/backend/modal.js')).default;
    Viewport.ContentContainer.setUrl.mockClear();
});

describe('pasteInto', () => {
    it('navigates immediately when the dataset has no confirmation title', () => {
        ContextMenuActions.pasteInto('tt_content', 5, { actionUrl: '/typo3/paste?uid=5' });

        expect(Viewport.ContentContainer.setUrl).toHaveBeenCalledTimes(1);
        const calledWith = Viewport.ContentContainer.setUrl.mock.calls[0][0];
        expect(calledWith).toContain('/typo3/paste?uid=5&redirect=');
        expect(calledWith).toContain(encodeURIComponent('/typo3/module/web/layout'));
    });

    it('shows a confirmation modal and only navigates when "ok" is clicked', () => {
        ContextMenuActions.pasteInto('tt_content', 5, {
            actionUrl: '/typo3/paste?uid=5',
            title: 'Confirm paste',
            message: 'Are you sure?',
        });

        expect(Viewport.ContentContainer.setUrl).not.toHaveBeenCalled();
        expect(Modal.currentModal.title).toBe('Confirm paste');

        Modal.currentModal.clickButton('cancel');
        expect(Viewport.ContentContainer.setUrl).not.toHaveBeenCalled();

        Modal.currentModal.clickButton('ok');
        expect(Viewport.ContentContainer.setUrl).toHaveBeenCalledTimes(1);
    });
});

describe('pasteReference', () => {
    it('delegates to pasteInto with a negated uid', () => {
        ContextMenuActions.pasteReference('tt_content', 5, { actionUrl: '/typo3/paste?uid=-5' });
        expect(Viewport.ContentContainer.setUrl).toHaveBeenCalledTimes(1);
    });
});

describe('pasteAfter', () => {
    it('navigates to the prepared action URL, which keeps the negative anchor target and the destination', () => {
        const actionUrl = '/typo3/record/commit?token=abc&CB%5Bpaste%5D=tt_content%7C-22&CB%5Bpad%5D=normal'
            + '&CB%5Bupdate%5D%5BcolPos%5D=2&CB%5Bupdate%5D%5Bsys_language_uid%5D=0'
            + '&CB%5Bupdate%5D%5Btx_gridelements_container%5D=0&CB%5Bupdate%5D%5Btx_gridelements_columns%5D=0';

        ContextMenuActions.pasteAfter('tt_content', 22, { actionUrl });

        expect(Viewport.ContentContainer.setUrl).toHaveBeenCalledTimes(1);
        const calledWith = Viewport.ContentContainer.setUrl.mock.calls[0][0];
        expect(calledWith.startsWith(actionUrl + '&redirect=')).toBe(true);
        expect(calledWith).toContain('CB%5Bupdate%5D%5Btx_gridelements_container%5D=0');
        expect(calledWith).toContain('CB%5Bupdate%5D%5Btx_gridelements_columns%5D=0');
    });

    it('delegates to pasteInto with the negated uid of the anchor record', () => {
        const pasteInto = ContextMenuActions.pasteInto;
        const calls = [];
        ContextMenuActions.pasteInto = (...args) => calls.push(args);
        try {
            const dataset = { actionUrl: '/typo3/record/commit?CB%5Bpaste%5D=tt_content%7C-22' };
            ContextMenuActions.pasteAfter('tt_content', 22, dataset);
            expect(calls).toEqual([['tt_content', -22, dataset]]);
        } finally {
            ContextMenuActions.pasteInto = pasteInto;
        }
    });

    it('asks for confirmation first when the menu item carries a confirmation title', () => {
        ContextMenuActions.pasteAfter('tt_content', 22, {
            actionUrl: '/typo3/record/commit?CB%5Bpaste%5D=tt_content%7C-22',
            title: 'Paste',
            message: 'Move after?',
        });

        expect(Viewport.ContentContainer.setUrl).not.toHaveBeenCalled();
        Modal.currentModal.clickButton('ok');
        expect(Viewport.ContentContainer.setUrl).toHaveBeenCalledTimes(1);
    });
});

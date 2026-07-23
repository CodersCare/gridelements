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

import { beforeEach, describe, expect, it } from 'vitest';
import $ from 'jquery';

let Paste;

beforeEach(async () => {
    document.body.innerHTML = '';
    globalThis.TYPO3 = { lang: {}, settings: { gridelements: {} } };
    const module = await import('../../Resources/Public/JavaScript/paste.js?t=' + Math.random());
    Paste = module.default;
});

function makeGridCell(attributes) {
    const cell = document.createElement('td');
    for (const [key, value] of Object.entries(attributes)) {
        cell.setAttribute(key, value);
    }
    document.body.appendChild(cell);
    return cell;
}

describe('getPasteState', () => {
    it('allows pasting the clipboard CType when nothing restricts the column', () => {
        const paste = new Paste({ itemOnClipboardUid: 5, itemOnClipboardTitle: 'x', copyMode: '' });
        globalThis.TYPO3.settings.gridelements.clipBoardElementCType = 'text';

        const { canPaste } = paste.getPasteState(makeGridCell({}));
        expect(canPaste).toBe(true);
    });

    it('allows pasting a grid container into a column that only restricts via allowed.tx_gridelements_backend_layout', () => {
        const paste = new Paste({ itemOnClipboardUid: 5, itemOnClipboardTitle: 'x', copyMode: '' });
        globalThis.TYPO3.settings.gridelements = {
            clipBoardElementCType: 'gridelements_pi1',
            clipBoardElementTxGridelementsBackendLayout: 'container',
        };

        const gridCell = makeGridCell({
            'data-allowed-ctype': 'header,text,shortcut',
            'data-allowed-tx_gridelements_backend_layout': 'container',
        });

        const { canPaste } = paste.getPasteState(gridCell);
        expect(canPaste).toBe(true);
    });

    it('rejects paste for a CType that is not allowed on the column', () => {
        const paste = new Paste({ itemOnClipboardUid: 5, itemOnClipboardTitle: 'x', copyMode: '' });
        globalThis.TYPO3.settings.gridelements.clipBoardElementCType = 'image';

        const { canPaste } = paste.getPasteState(makeGridCell({ 'data-allowed-ctype': 'text' }));
        expect(canPaste).toBe(false);
    });

    it('offers paste-reference independently of paste, gated by pasteReferenceAllowed', () => {
        const paste = new Paste({ itemOnClipboardUid: 5, itemOnClipboardTitle: 'x', copyMode: '' });
        globalThis.TYPO3.settings.gridelements = {
            clipBoardElementCType: 'image',
            pasteReferenceAllowed: true,
        };

        const { canPaste, canPasteReference } = paste.getPasteState(makeGridCell({ 'data-allowed-ctype': 'text,shortcut' }));
        expect(canPaste).toBe(false);
        expect(canPasteReference).toBe(true);
    });

    it('withholds paste-reference when pasteReferenceAllowed is false', () => {
        const paste = new Paste({ itemOnClipboardUid: 5, itemOnClipboardTitle: 'x', copyMode: '' });
        globalThis.TYPO3.settings.gridelements = {
            clipBoardElementCType: 'image',
            pasteReferenceAllowed: false,
        };

        const { canPasteReference } = paste.getPasteState(makeGridCell({ 'data-allowed-ctype': 'text,shortcut' }));
        expect(canPasteReference).toBe(false);
    });

    it('treats a missing grid cell as fully unrestricted', () => {
        const paste = new Paste({ itemOnClipboardUid: 5, itemOnClipboardTitle: 'x', copyMode: '' });
        globalThis.TYPO3.settings.gridelements.clipBoardElementCType = 'text';

        const { canPaste } = paste.getPasteState(null);
        expect(canPaste).toBe(true);
    });
});

describe('determineColumn / determineGridColumn / determineGridContainer', () => {
    it('reads colPos from the closest [data-colpos] ancestor', () => {
        document.body.innerHTML = '<div data-colpos="2"><span id="target"></span></div>';
        const $target = $('#target');
        expect(Paste.determineColumn($target)).toBe(2);
    });

    it('returns 0 when there is no [data-colpos] ancestor', () => {
        document.body.innerHTML = '<span id="target"></span>';
        expect(Paste.determineColumn($('#target'))).toBe(0);
    });

    it('reads the grid container uid from the closest [data-container] ancestor', () => {
        document.body.innerHTML = '<div data-container="42"><span id="target"></span></div>';
        expect(Paste.determineGridContainer($('#target'))).toBe(42);
    });
});

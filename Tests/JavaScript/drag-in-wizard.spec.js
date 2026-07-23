import { beforeEach, describe, expect, it, vi } from 'vitest';

let DragInWizard;

beforeEach(async () => {
    vi.resetModules();
    document.body.innerHTML = '';
    sessionStorage.clear();
    globalThis.TYPO3 = { lang: {} };
    const module = await import('../../Resources/Public/JavaScript/drag-in-wizard.js');
    DragInWizard = Object.getPrototypeOf(module.default).constructor;
});

describe('columnAllows', () => {
    const unrestricted = {
        allowedCtype: null, disallowedCtype: null,
        allowedListType: null, disallowedListType: null,
        allowedGridType: null, disallowedGridType: null,
    };

    it('allows anything on a fully unrestricted column', () => {
        expect(DragInWizard.columnAllows(unrestricted, 'text', '', '')).toBe(true);
        expect(DragInWizard.columnAllows(unrestricted, 'gridelements_pi1', '', 'container')).toBe(true);
    });

    it('rejects a CType that is not in the allow list', () => {
        const col = { ...unrestricted, allowedCtype: ['text', 'textmedia'] };
        expect(DragInWizard.columnAllows(col, 'image', '', '')).toBe(false);
    });

    it('rejects a grid type that is not in the allow list', () => {
        const col = { ...unrestricted, allowedGridType: ['container'] };
        expect(DragInWizard.columnAllows(col, 'gridelements_pi1', '', 'backgroundBox')).toBe(false);
    });
});

describe('isDropAllowed', () => {
    function makeColumnZone(attributes) {
        const column = document.createElement('td');
        column.className = 't3js-page-column';
        for (const [key, value] of Object.entries(attributes)) {
            column.setAttribute(key, value);
        }
        const zone = document.createElement('div');
        column.appendChild(zone);
        document.body.appendChild(column);
        return zone;
    }

    it('allows dropping a grid container into a column that only restricts via allowed.tx_gridelements_backend_layout (real page-92 colPos=21 data)', () => {
        const zone = makeColumnZone({
            'data-allowed-ctype': 'header,text,shortcut',
            'data-allowed-list_type': 'something',
            'data-allowed-tx_gridelements_backend_layout': 'container',
        });
        expect(DragInWizard.isDropAllowed(zone, 'gridelements_pi1', '', 'container')).toBe(true);
    });

    it('still rejects a CType that is genuinely not allowed on that same column', () => {
        const zone = makeColumnZone({
            'data-allowed-ctype': 'header,text,shortcut',
            'data-allowed-list_type': 'something',
            'data-allowed-tx_gridelements_backend_layout': 'container',
        });
        expect(DragInWizard.isDropAllowed(zone, 'image', '', '')).toBe(false);
    });

    it('accepts a DB-record grid layout (numeric identifier) on a column restricting by that same numeric id', () => {
        const zone = makeColumnZone({
            'data-allowed-ctype': 'text',
            'data-allowed-tx_gridelements_backend_layout': '1',
        });
        expect(DragInWizard.isDropAllowed(zone, 'gridelements_pi1', '', 1)).toBe(true);
    });

    it('rejects any drop on a column flagged as disabled for new content', () => {
        const zone = makeColumnZone({});
        zone.parentElement.classList.add('t3-page-ce-disable-new-ce');
        expect(DragInWizard.isDropAllowed(zone, 'text', '', '')).toBe(false);
    });

    it('allows any drop when the zone is not inside a recognised page column', () => {
        const zone = document.createElement('div');
        document.body.appendChild(zone);
        expect(DragInWizard.isDropAllowed(zone, 'anything', '', '')).toBe(true);
    });

    it('rejects any drop inside a shortcut/reference preview, even on an otherwise-unrestricted nested column', () => {
        // ShortcutPreviewRenderer nests a referenced gridelements container's real, live
        // grid markup (drop zones and all) inside a `.reference`-wrapped preview box - that
        // markup must never be a real drop target, it's a read-only preview of someone else's content.
        const reference = document.createElement('div');
        reference.className = 'reference';
        document.body.appendChild(reference);
        const zone = makeColumnZone({ 'data-allowed-ctype': '*' });
        reference.appendChild(zone.closest('.t3js-page-column'));

        expect(DragInWizard.isDropAllowed(zone, 'text', '', '')).toBe(false);
    });
});

describe('onDragStart', () => {
    it('does not activate a drop zone or reveal its add button when nested inside a reference preview', () => {
        const reference = document.createElement('div');
        reference.className = 'reference';
        document.body.appendChild(reference);

        const column = document.createElement('td');
        column.className = 't3js-page-column';
        reference.appendChild(column);

        const addBtn = document.createElement('button');
        addBtn.className = 't3js-page-new-ce';
        column.appendChild(addBtn);

        const zone = document.createElement('div');
        zone.className = 't3js-page-ce-dropzone-available';
        column.appendChild(zone);

        const draggedItem = document.createElement('div');
        draggedItem.dataset.defaultValues = JSON.stringify({ CType: 'text' });
        document.body.appendChild(draggedItem);

        DragInWizard.onDragStart({ target: draggedItem });

        expect(zone.classList.contains('active')).toBe(false);
        expect(addBtn.hidden).toBe(false);
    });
});

describe('buildColumnRestrictions', () => {
    it('reads every non-disabled .t3js-page-column and skips disabled ones', () => {
        const allowed = document.createElement('td');
        allowed.className = 't3js-page-column';
        allowed.setAttribute('data-allowed-ctype', 'text');
        document.body.appendChild(allowed);

        const disabled = document.createElement('td');
        disabled.className = 't3js-page-column t3-page-ce-disable-new-ce';
        document.body.appendChild(disabled);

        const restrictions = DragInWizard.buildColumnRestrictions();
        expect(restrictions).toHaveLength(1);
        expect(restrictions[0].allowedCtype).toEqual(['text']);
    });
});

describe('showWizard URL construction', () => {
    it('keeps uid_pid in the fetch URL so the server resolves the correct page for TSconfig-defined grid layouts', async () => {
        const button = document.createElement('typo3-backend-new-content-element-wizard-button');
        button.setAttribute(
            'url',
            'https://example.test/typo3/record/content/wizard/new?id=92&uid_pid=92&colPos=21&tx_gridelements_container=0&tx_gridelements_columns=0'
        );
        document.body.appendChild(button);

        const fetchMock = vi.fn().mockResolvedValue({
            text: () => Promise.resolve('<html><body></body></html>'),
        });
        vi.stubGlobal('fetch', fetchMock);

        DragInWizard.showWizard();
        await Promise.resolve();

        expect(fetchMock).toHaveBeenCalledTimes(1);
        const requestedUrl = new URL(fetchMock.mock.calls[0][0]);
        expect(requestedUrl.searchParams.get('uid_pid')).toBe('92');
        expect(requestedUrl.searchParams.has('colPos')).toBe(false);
        expect(requestedUrl.searchParams.has('tx_gridelements_container')).toBe(false);
        expect(requestedUrl.searchParams.has('tx_gridelements_columns')).toBe(false);

        vi.unstubAllGlobals();
    });

    it('does nothing when no wizard button is present on the page', async () => {
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);

        DragInWizard.showWizard();
        await Promise.resolve();

        expect(fetchMock).not.toHaveBeenCalled();
        vi.unstubAllGlobals();
    });
});

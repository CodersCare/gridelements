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

    function makeColumnWithZone(allowedCtype) {
        const column = document.createElement('td');
        column.className = 't3js-page-column';
        column.setAttribute('data-allowed-ctype', allowedCtype);

        const addBtn = document.createElement('button');
        addBtn.className = 't3js-page-new-ce';
        column.appendChild(addBtn);

        const zone = document.createElement('div');
        zone.className = 't3js-page-ce-dropzone-available';
        zone.hidden = true;
        column.appendChild(zone);

        document.body.appendChild(column);
        return { addBtn, zone };
    }

    it('hides the add button but keeps the dropzone inactive (reserved but invisible) when the column disallows the dragged item', () => {
        const { addBtn, zone } = makeColumnWithZone('header');
        const draggedItem = document.createElement('div');
        draggedItem.dataset.defaultValues = JSON.stringify({ CType: 'text' });
        document.body.appendChild(draggedItem);

        DragInWizard.onDragStart({ target: draggedItem });

        expect(addBtn.hidden).toBe(true);
        expect(zone.hidden).toBe(false);
        expect(zone.classList.contains('active')).toBe(false);
    });

    it('reveals and activates the dropzone box when the column allows the dragged item', () => {
        const { addBtn, zone } = makeColumnWithZone('text');
        const draggedItem = document.createElement('div');
        draggedItem.dataset.defaultValues = JSON.stringify({ CType: 'text' });
        document.body.appendChild(draggedItem);

        DragInWizard.onDragStart({ target: draggedItem });

        expect(addBtn.hidden).toBe(true);
        expect(zone.hidden).toBe(false);
        expect(zone.classList.contains('active')).toBe(true);
    });
});

describe('onDragEnd', () => {
    it('re-hides the dropzone box and restores the add button', () => {
        const column = document.createElement('td');
        column.className = 't3js-page-column';

        const addBtn = document.createElement('button');
        addBtn.className = 't3js-page-new-ce';
        addBtn.hidden = true;
        column.appendChild(addBtn);

        const zone = document.createElement('div');
        zone.className = 't3js-page-ce-dropzone-available active';
        zone.hidden = false;
        column.appendChild(zone);

        document.body.appendChild(column);

        DragInWizard.onDragEnd({ target: document.createElement('div') });

        expect(addBtn.hidden).toBe(false);
        expect(zone.hidden).toBe(true);
        expect(zone.classList.contains('active')).toBe(false);
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

describe('showWizard response parsing', () => {
    it('renders the drag panel from a real TYPO3 13 NewContentElementController response', async () => {
        const button = document.createElement('typo3-backend-new-content-element-wizard-button');
        button.setAttribute('url', 'https://example.test/typo3/record/content/wizard/new?id=92&uid_pid=92');
        document.body.appendChild(button);

        const categories = {
            common: {
                identifier: 'common',
                label: 'Common',
                items: [{
                    identifier: 'text',
                    icon: 'content-text',
                    iconOverlay: '',
                    label: 'Text',
                    description: 'A simple text element',
                    defaultValues: { CType: 'text' },
                }],
            },
        };
        const responseHtml = `<html><body><typo3-backend-new-record-wizard categories='${JSON.stringify(categories)}'></typo3-backend-new-record-wizard></body></html>`;

        const fetchMock = vi.fn().mockResolvedValue({ text: () => Promise.resolve(responseHtml) });
        vi.stubGlobal('fetch', fetchMock);

        DragInWizard.showWizard();
        await new Promise(resolve => setTimeout(resolve, 0));

        const panel = document.getElementById('gridelements-drag-in-wizard');
        expect(panel).not.toBeNull();
        expect(panel.querySelectorAll('.gridelements-drag-in-wizard-item')).toHaveLength(1);

        vi.unstubAllGlobals();
    });

    it('renders the drag panel from a real TYPO3 12 NewContentElementController response', async () => {
        const button = document.createElement('typo3-backend-new-content-element-wizard-button');
        button.setAttribute('url', 'https://example.test/typo3/record/content/wizard/new?id=92&uid_pid=92');
        document.body.appendChild(button);

        const categories = {
            common: {
                identifier: 'common',
                label: 'Common',
                items: [{
                    identifier: 'text',
                    icon: 'content-text',
                    label: 'Text',
                    description: 'A simple text element',
                    defaultValues: { CType: 'text' },
                }],
            },
        };
        const responseHtml = `<html><body><typo3-backend-new-content-element-wizard categories='${JSON.stringify(categories)}'></typo3-backend-new-content-element-wizard></body></html>`;

        const fetchMock = vi.fn().mockResolvedValue({ text: () => Promise.resolve(responseHtml) });
        vi.stubGlobal('fetch', fetchMock);

        DragInWizard.showWizard();
        await new Promise(resolve => setTimeout(resolve, 0));

        const panel = document.getElementById('gridelements-drag-in-wizard');
        expect(panel).not.toBeNull();
        expect(panel.querySelectorAll('.gridelements-drag-in-wizard-item')).toHaveLength(1);

        vi.unstubAllGlobals();
    });
});

describe('setupDraggable', () => {
    it('points autoScroll at the scrollable module body on CMS13 (.t3js-module-body has overflow:auto)', async () => {
        const module = document.createElement('div');
        module.className = 'module';
        document.body.appendChild(module);
        const moduleBody = document.createElement('div');
        moduleBody.className = 't3js-module-body';
        moduleBody.style.overflowY = 'auto';
        module.appendChild(moduleBody);

        const { default: interact } = await import('interactjs');
        interact.calls.length = 0;

        DragInWizard.setupDraggable();

        const call = interact.calls.find(c => c.method === 'draggable');
        expect(call).toBeDefined();
        expect(call.args[0].autoScroll.container).toBe(moduleBody);
    });

    it('points autoScroll at .module on CMS12, where .t3js-module-body has no overflow of its own', async () => {
        const module = document.createElement('div');
        module.className = 'module';
        module.style.overflowY = 'auto';
        document.body.appendChild(module);
        const moduleBody = document.createElement('div');
        moduleBody.className = 't3js-module-body';
        module.appendChild(moduleBody);

        const { default: interact } = await import('interactjs');
        interact.calls.length = 0;

        DragInWizard.setupDraggable();

        const call = interact.calls.find(c => c.method === 'draggable');
        expect(call).toBeDefined();
        expect(call.args[0].autoScroll.container).toBe(module);
    });
});

describe('positionPanel', () => {
    function makeDocHeaderAndPageTitle(pageTitleRight, docHeaderBottom) {
        const docHeader = document.createElement('div');
        docHeader.className = 't3js-module-docheader';
        docHeader.getBoundingClientRect = () => ({ bottom: docHeaderBottom });
        document.body.appendChild(docHeader);

        const pageTitle = document.createElement('typo3-backend-editable-page-title');
        pageTitle.getBoundingClientRect = () => ({ right: pageTitleRight });
        document.body.appendChild(pageTitle);

        return { docHeader, pageTitle };
    }

    it("moves the panel's right edge past the page title's right edge by the gap, and its top edge below the doc-header by the same gap, not a fixed guess", () => {
        const panel = document.createElement('div');
        panel.id = 'gridelements-drag-in-wizard';
        document.body.appendChild(panel);
        makeDocHeaderAndPageTitle(1180, 75);
        vi.stubGlobal('innerWidth', 1200);

        DragInWizard.positionPanel();

        expect(panel.style.right).toBe('10px');
        expect(panel.style.top).toBe('85px');
        vi.unstubAllGlobals();
    });

    it('leaves the panel position untouched when the doc-header or page title cannot be found', () => {
        const panel = document.createElement('div');
        panel.id = 'gridelements-drag-in-wizard';
        document.body.appendChild(panel);

        DragInWizard.positionPanel();

        expect(panel.style.right).toBe('');
        expect(panel.style.top).toBe('');
    });
});

import { beforeEach, describe, expect, it } from 'vitest';

let DragDrop;

beforeEach(async () => {
    document.body.innerHTML = '';
    sessionStorage.clear();
    globalThis.TYPO3 = { lang: {} };
    const module = await import('../../Resources/Public/JavaScript/drag-drop-cms12.js?t=' + Math.random());
    DragDrop = Object.getPrototypeOf(module.default).constructor;
});

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

describe('isTypeAllowed / isDropAllowed', () => {
    it('allows dropping a grid container into a column that only restricts via allowed.tx_gridelements_backend_layout (real page-92 colPos=21 data)', () => {
        const zone = makeColumnZone({
            'data-allowed-ctype': 'header,text,shortcut',
            'data-allowed-list_type': 'something',
            'data-allowed-tx_gridelements_backend_layout': 'container',
        });
        expect(DragDrop.isDropAllowed(zone, 'gridelements_pi1', '', 'container', null)).toBe(true);
    });

    it('rejects a CType that is not allowed on that column', () => {
        const zone = makeColumnZone({ 'data-allowed-ctype': 'text' });
        expect(DragDrop.isDropAllowed(zone, 'image', '', '', null)).toBe(false);
    });

    it('rejects a drop into a disabled column, unless it is the element\'s own source column', () => {
        const zone = makeColumnZone({});
        const column = zone.parentElement;
        column.classList.add('t3-page-ce-disable-new-ce');

        expect(DragDrop.isDropAllowed(zone, 'text', '', '', null)).toBe(false);
        expect(DragDrop.isDropAllowed(zone, 'text', '', '', column)).toBe(true);
    });

    it('allows any drop when the zone is not inside a recognised page column', () => {
        const zone = document.createElement('div');
        document.body.appendChild(zone);
        expect(DragDrop.isDropAllowed(zone, 'anything', '', '', null)).toBe(true);
    });
});

describe('getColumnPositionForElement / getGridColumnPositionForElement', () => {
    it('reads colPos from the closest [data-colpos] ancestor', () => {
        const column = document.createElement('div');
        column.setAttribute('data-colpos', '3');
        const child = document.createElement('div');
        column.appendChild(child);
        document.body.appendChild(column);

        expect(DragDrop.getColumnPositionForElement(child)).toBe(3);
    });

    it('only reports a grid column position inside a .t3-grid-element-container', () => {
        const column = document.createElement('div');
        column.setAttribute('data-colpos', '1');
        const child = document.createElement('div');
        column.appendChild(child);
        document.body.appendChild(column);

        expect(DragDrop.getGridColumnPositionForElement(child)).toBe(false);

        const container = document.createElement('div');
        container.className = 't3-grid-element-container';
        container.appendChild(column);
        document.body.appendChild(container);

        expect(DragDrop.getGridColumnPositionForElement(child)).toBe(1);
    });
});

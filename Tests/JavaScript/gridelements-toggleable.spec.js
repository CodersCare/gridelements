import { beforeEach, describe, expect, it } from 'vitest';

let GridelementsToggleable;
let PersistentStorage;

beforeEach(async () => {
    document.body.innerHTML = '';
    globalThis.TYPO3 = { lang: {} };
    const module = await import('../../Resources/Public/JavaScript/gridelements-toggleable.js?t=' + Math.random());
    GridelementsToggleable = Object.getPrototypeOf(module.default).constructor;
    PersistentStorage = (await import('@typo3/backend/storage/persistent.js')).default;
    PersistentStorage._reset();
});

describe('activateCollapseIcons', () => {
    function makeToggleFixture() {
        const gridCell = document.createElement('div');
        gridCell.className = 't3js-page-column t3-grid-cell expanded';
        gridCell.setAttribute('data-columnkey', '313_0');

        const toggle = document.createElement('a');
        toggle.className = 't3js-toggle-gridelements-column';
        toggle.setAttribute('data-state', 'expanded');
        toggle.setAttribute('title', 'Collapse');
        toggle.setAttribute('data-toggle-title', 'Expand');

        gridCell.appendChild(toggle);
        document.body.appendChild(gridCell);
        return { gridCell, toggle };
    }

    it('toggles the column state, persists it, and swaps the title on click', async () => {
        GridelementsToggleable.activateCollapseIcons();
        const { gridCell, toggle } = makeToggleFixture();

        toggle.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        await Promise.resolve();
        await Promise.resolve();

        expect(PersistentStorage.get('moduleData.page.gridelementsCollapsedColumns')).toEqual({ '313_0': 1 });
        expect(toggle.getAttribute('data-state')).toBe('collapsed');
        expect(toggle.getAttribute('title')).toBe('Expand');
        expect(toggle.getAttribute('data-toggle-title')).toBe('Collapse');
        expect(gridCell.classList.contains('collapsed')).toBe(true);
        expect(gridCell.classList.contains('expanded')).toBe(false);
    });

    it('merges into previously stored column state rather than replacing it', async () => {
        await PersistentStorage.set('moduleData.page.gridelementsCollapsedColumns', { '100_0': 1 });
        GridelementsToggleable.activateCollapseIcons();
        const { toggle } = makeToggleFixture();

        toggle.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        await Promise.resolve();
        await Promise.resolve();

        expect(PersistentStorage.get('moduleData.page.gridelementsCollapsedColumns')).toEqual({
            '100_0': 1,
            '313_0': 1,
        });
    });

    it('ignores clicks that do not originate from a toggle element', async () => {
        GridelementsToggleable.activateCollapseIcons();
        const { toggle } = makeToggleFixture();
        const unrelated = document.createElement('button');
        document.body.appendChild(unrelated);

        unrelated.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        await Promise.resolve();

        expect(toggle.getAttribute('data-state')).toBe('expanded');
        expect(PersistentStorage.isset('moduleData.page.gridelementsCollapsedColumns')).toBe(false);
    });

    it('promotes the header icon links to Bootstrap button styling', () => {
        const header = document.createElement('div');
        header.className = 't3-page-column-header-icons';
        const link = document.createElement('a');
        header.appendChild(link);
        document.body.appendChild(header);

        GridelementsToggleable.activateCollapseIcons();

        expect(header.classList.contains('btn-group')).toBe(true);
        expect(header.classList.contains('btn-group-sm')).toBe(true);
        expect(link.classList.contains('btn')).toBe(true);
        expect(link.classList.contains('btn-default')).toBe(true);
    });
});

describe('activateAllCollapseIcons', () => {
    function makeDocHeaderFixture() {
        document.body.innerHTML = `
            <div class="module-docheader-bar-column-left">
                <div class="btn-group">
                    <button class="icon" id="last-icon"></button>
                </div>
            </div>
            <button class="t3js-toggle-gridelements-column" onclick="foo()">
                <span class="icon-actions-view-list-collapse"></span>
                <span class="icon-actions-view-list-expand"></span>
            </button>
        `;
    }

    it('clones the toggle button into separate expand-all/collapse-all icons next to the doc header', () => {
        makeDocHeaderFixture();
        GridelementsToggleable.activateAllCollapseIcons();

        const expandAll = document.querySelector('.t3js-gridcolumn-toggle.t3js-gridcolumn-expand');
        const collapseAll = document.querySelector('.t3js-gridcolumn-toggle:not(.t3js-gridcolumn-expand)');

        expect(expandAll).not.toBeNull();
        expect(expandAll.querySelector('.icon-actions-view-list-collapse')).toBeNull();
        expect(expandAll.querySelector('.icon-actions-view-list-expand')).not.toBeNull();
        expect(expandAll.hasAttribute('onclick')).toBe(false);

        expect(collapseAll).not.toBeNull();
        expect(collapseAll.querySelector('.icon-actions-view-list-expand')).toBeNull();
    });

    it('does nothing when there is no doc header icon group to attach to', () => {
        document.body.innerHTML = '<button class="t3js-toggle-gridelements-column"></button>';
        expect(() => GridelementsToggleable.activateAllCollapseIcons()).not.toThrow();
        expect(document.querySelector('.t3js-gridcolumn-toggle')).toBeNull();
    });
});

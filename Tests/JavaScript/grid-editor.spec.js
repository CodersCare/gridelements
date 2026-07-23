import { beforeEach, describe, expect, it } from 'vitest';
import $ from 'jquery';

let GridEditor;

function makeCell(overrides = {}) {
    return {
        spanned: 0,
        rowspan: 1,
        colspan: 1,
        name: '',
        colpos: '',
        column: undefined,
        allowed: { CType: '', list_type: '', tx_gridelements_backend_layout: '' },
        disallowed: { CType: '', list_type: '', tx_gridelements_backend_layout: '' },
        maxitems: 0,
        ...overrides,
    };
}

function makeGrid(colCount, rowCount) {
    const data = [];
    for (let row = 0; row < rowCount; row++) {
        const rowCells = [];
        for (let col = 0; col < colCount; col++) {
            rowCells.push(makeCell({ name: `${col}x${row}` }));
        }
        data.push(rowCells);
    }
    return data;
}

function createEditor({ colCount = 2, rowCount = 1, readOnly = false, data } = {}) {
    document.body.innerHTML = '<input name="myfield" />';
    const el = document.createElement('div');
    el.className = 't3js-grideditor';
    document.body.appendChild(el);

    $(el).data({
        colcount: colCount,
        rowcount: rowCount,
        readonly: readOnly,
        field: 'myfield',
        data: data ?? makeGrid(colCount, rowCount),
    });

    return new GridEditor();
}

beforeEach(async () => {
    globalThis.TYPO3 = { lang: {} };
    globalThis.Gridelements = {
        BackendLayout: {
            availableCTypes: [{ key: 'text', label: 'Text' }, { key: 'gridelements_pi1', label: 'Container' }],
            availableListTypes: [{ key: 'news_pi1', label: 'News' }],
            availableGridTypes: [{ key: 'container', label: 'Container layout' }],
        },
    };
    globalThis.IntersectionObserver = class {
        observe() {}
        unobserve() {}
        disconnect() {}
    };
    const module = await import('../../Resources/Public/JavaScript/grid-editor.js?t=' + Math.random());
    GridEditor = module.GridEditor;
});

describe('construction', () => {
    it('reads colCount/rowCount/data from the target element and renders the grid', () => {
        const editor = createEditor({ colCount: 2, rowCount: 1 });

        expect(editor.colCount).toBe(2);
        expect(editor.rowCount).toBe(1);
        expect(document.querySelectorAll('.grideditor-cell')).toHaveLength(2);
    });

    it('writes the TypoScript preview into the bound hidden field', () => {
        createEditor({ colCount: 1, rowCount: 1 });
        const value = $('input[name="myfield"]').val();

        expect(value).toContain('colCount = 1');
        expect(value).toContain('rowCount = 1');
    });
});

describe('stripMarkup (XSS prevention)', () => {
    it('strips script tags along with their content entirely', () => {
        expect(GridEditor.stripMarkup('<script>alert(1)</script>evil')).toBe('evil');
    });

    it('strips arbitrary HTML tags but keeps the text', () => {
        expect(GridEditor.stripMarkup('<img src=x onerror=alert(1)>Column A')).toBe('Column A');
    });

    it('leaves plain text untouched', () => {
        expect(GridEditor.stripMarkup('Column A')).toBe('Column A');
    });
});

describe('setName / setAllowed / setDisallowed apply stripMarkup', () => {
    it('sanitizes a malicious cell name before storing it', () => {
        const editor = createEditor({ colCount: 1, rowCount: 1 });
        editor.setName('<img src=x onerror=alert(1)>evil name', 0, 0);
        expect(editor.getCell(0, 0).name).toBe('evil name');
    });

    it('sanitizes the allowed CType list and defaults empty input to "*"', () => {
        const editor = createEditor({ colCount: 1, rowCount: 1 });
        editor.setAllowed('<script>x</script>text,textmedia', 0, 0);
        expect(editor.getCell(0, 0).allowed.CType).toBe('text,textmedia');

        editor.setAllowed('', 0, 0);
        expect(editor.getCell(0, 0).allowed.CType).toBe('*');
    });

    it('returns false for a cell outside the grid bounds', () => {
        const editor = createEditor({ colCount: 1, rowCount: 1 });
        expect(editor.setName('x', 5, 5)).toBe(false);
    });
});

describe('row/column mutation', () => {
    it('addRowTop inserts a new row at index 0 and increments rowCount', () => {
        const editor = createEditor({ colCount: 2, rowCount: 1 });
        editor.addRowTop();

        expect(editor.rowCount).toBe(2);
        expect(editor.data).toHaveLength(2);
        expect(editor.data[0][0].name).toBe('0x1');
    });

    it('addColumn appends a new column to every row and increments colCount', () => {
        const editor = createEditor({ colCount: 1, rowCount: 2 });
        editor.addColumn();

        expect(editor.colCount).toBe(2);
        expect(editor.data[0]).toHaveLength(2);
        expect(editor.data[1]).toHaveLength(2);
    });

    it('removeRowTop refuses to go below one row', () => {
        const editor = createEditor({ colCount: 1, rowCount: 1 });
        expect(editor.removeRowTop()).toBe(false);
        expect(editor.rowCount).toBe(1);
    });

    it('removeColumn refuses to go below one column', () => {
        const editor = createEditor({ colCount: 1, rowCount: 1 });
        expect(editor.removeColumn()).toBe(false);
        expect(editor.colCount).toBe(1);
    });

    it('removeRowBottom removes the last row and decrements rowCount', () => {
        const editor = createEditor({ colCount: 1, rowCount: 2 });
        expect(editor.removeRowBottom()).toBe(true);
        expect(editor.rowCount).toBe(1);
        expect(editor.data).toHaveLength(1);
        expect(editor.data[0][0].name).toBe('0x0');
    });
});

describe('cell spanning', () => {
    it('addColspan merges the cell to the right and marks it spanned', () => {
        const editor = createEditor({ colCount: 2, rowCount: 1 });
        expect(editor.cellCanSpanRight(0, 0)).toBe(true);

        editor.addColspan(0, 0);

        expect(editor.getCell(0, 0).colspan).toBe(2);
        expect(editor.getCell(1, 0).spanned).toBe(1);
        expect(editor.cellCanSpanRight(0, 0)).toBe(false);
    });

    it('removeColspan reverses addColspan', () => {
        const editor = createEditor({ colCount: 2, rowCount: 1 });
        editor.addColspan(0, 0);

        expect(editor.cellCanShrinkLeft(0, 0)).toBe(true);
        editor.removeColspan(0, 0);

        expect(editor.getCell(0, 0).colspan).toBe(1);
        expect(editor.getCell(1, 0).spanned).toBe(0);
    });

    it('addRowspan merges the cell below and marks it spanned', () => {
        const editor = createEditor({ colCount: 1, rowCount: 2 });
        expect(editor.cellCanSpanDown(0, 0)).toBe(true);

        editor.addRowspan(0, 0);

        expect(editor.getCell(0, 0).rowspan).toBe(2);
        expect(editor.getCell(0, 1).spanned).toBe(1);
    });

    it('a cell already at the grid edge cannot span further in that direction', () => {
        const editor = createEditor({ colCount: 1, rowCount: 1 });
        expect(editor.cellCanSpanRight(0, 0)).toBe(false);
        expect(editor.cellCanSpanDown(0, 0)).toBe(false);
        expect(editor.addColspan(0, 0)).toBe(false);
    });
});

describe('export2LayoutRecord', () => {
    it('serializes a single unrestricted cell into TypoScript', () => {
        const editor = createEditor({ colCount: 1, rowCount: 1 });
        editor.setName('Main', 0, 0);
        editor.setColumn(0, 0, 0);

        const output = editor.export2LayoutRecord();

        expect(output).toContain('colCount = 1');
        expect(output).toContain('rowCount = 1');
        expect(output).toContain('name = Main');
        expect(output).toContain('colPos = 0');
        expect(output).not.toContain('allowed {');
    });

    it('includes an allowed block only when a restriction is actually set', () => {
        const editor = createEditor({ colCount: 1, rowCount: 1 });
        editor.setAllowed('text,textmedia', 0, 0);

        const output = editor.export2LayoutRecord();

        expect(output).toContain('allowed {');
        expect(output).toContain('CType = text,textmedia');
        expect(output).not.toContain('disallowed {');
    });
});

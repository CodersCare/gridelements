import { beforeEach, describe, expect, it } from 'vitest';
import $ from 'jquery';
import Modal from '@typo3/backend/modal.js';

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

const restrictionSetters = [
    ['setAllowed', 'allowed', 'CType', 'text'],
    ['setAllowedListTypes', 'allowed', 'list_type', 'news_pi1'],
    ['setAllowedGridTypes', 'allowed', 'tx_gridelements_backend_layout', 'container'],
    ['setDisallowed', 'disallowed', 'CType', 'text'],
    ['setDisallowedListTypes', 'disallowed', 'list_type', 'news_pi1'],
    ['setDisallowedGridTypes', 'disallowed', 'tx_gridelements_backend_layout', 'container'],
];

const emptyRestriction = { CType: '', list_type: '', tx_gridelements_backend_layout: '' };

function createEditorWithCell(cell) {
    return createEditor({ colCount: 1, rowCount: 1, data: [[cell]] });
}

// Opens the real cell dialog, selects the given values and clicks "ok". In TYPO3 the clicked button is the
// event target and the modal element (which renders the content and carries userData) the currentTarget.
function submitCellDialog(editor, col, row, selections) {
    expect(editor.showOptions(col, row)).toBe(true);
    const modal = Modal.currentModal;
    const modalElement = document.createElement('div');
    $(modalElement).append(modal.content);
    modalElement.userData = modal.userData;
    modalElement.hideModal = () => { modal.hidden = true; };
    Object.entries(selections).forEach(([field, values]) => {
        $(modalElement).find('.t3js-grideditor-field-' + field).val(values);
    });
    modal._listeners['button.clicked'].forEach(cb => cb({ target: { name: 'ok' }, currentTarget: modalElement }));
    expect(modal.hidden).toBe(true);
}

describe('allowed/disallowed setters', () => {
    describe.each(restrictionSetters)('%s', (setter, restriction, field, value) => {
        it.each([
            { label: 'a missing', invalid: undefined },
            { label: 'a null', invalid: null },
            { label: 'an empty string as', invalid: '' },
            { label: 'a string as', invalid: 'text' },
            { label: 'a number as', invalid: 42 },
        ])('initializes $label structure and returns true', ({ invalid }) => {
            const cell = makeCell();
            if (invalid === undefined) {
                delete cell[restriction];
            } else {
                cell[restriction] = invalid;
            }
            const editor = createEditorWithCell(cell);

            expect(editor[setter](value, 0, 0)).toBe(true);
            expect(editor.getCell(0, 0)[restriction]).toEqual({ ...emptyRestriction, [field]: value });
        });

        it('keeps the other options of an existing structure', () => {
            const cell = makeCell({
                allowed: { CType: 'textmedia', list_type: 'other_pi1', tx_gridelements_backend_layout: 'other', custom: 'keep' },
                disallowed: { CType: 'header', list_type: 'other_pi2', tx_gridelements_backend_layout: 'other2', custom: 'keep' },
            });
            const untouched = restriction === 'allowed' ? 'disallowed' : 'allowed';
            const expectedUntouched = { ...cell[untouched] };
            const editor = createEditorWithCell(cell);

            expect(editor[setter](value, 0, 0)).toBe(true);
            expect(editor.getCell(0, 0)[restriction]).toEqual({ ...cell[restriction], [field]: value });
            expect(editor.getCell(0, 0)[untouched]).toEqual(expectedUntouched);
        });

        it('accepts the empty array PHP sends for a column without restrictions', () => {
            const editor = createEditorWithCell(makeCell({ allowed: [], disallowed: [] }));

            expect(editor[setter](value, 0, 0)).toBe(true);
            expect(editor.getCell(0, 0)[restriction][field]).toBe(value);
            expect(editor.export2LayoutRecord()).toContain(restriction + ' {\n\t\t\t\t\t\t' + field + ' = ' + value + '\n');
        });

        it('returns true for an empty value', () => {
            const editor = createEditorWithCell(makeCell());

            expect(editor[setter]('', 0, 0)).toBe(true);
            expect(editor.getCell(0, 0)[restriction][field]).toBe(setter === 'setAllowed' ? '*' : '');
        });

        it('strips markup from the stored value', () => {
            const editor = createEditorWithCell(makeCell());

            editor[setter]('<img src=x onerror=alert(1)>' + value, 0, 0);
            expect(editor.getCell(0, 0)[restriction][field]).toBe(value);
        });

        it('keeps a wildcard', () => {
            const editor = createEditorWithCell(makeCell());

            expect(editor[setter]('*', 0, 0)).toBe(true);
            expect(editor.getCell(0, 0)[restriction][field]).toBe('*');
        });

        it('returns false for a cell outside the grid and changes nothing', () => {
            const editor = createEditorWithCell(makeCell());
            const before = JSON.stringify(editor.data);

            expect(editor[setter](value, 5, 5)).toBe(false);
            expect(JSON.stringify(editor.data)).toBe(before);
        });

        it('does not leak into other new cells or the default cell', () => {
            const editor = createEditor({ colCount: 1, rowCount: 1 });
            editor.addColumn();
            editor.addColumn();

            editor[setter](value, 1, 0);

            expect(editor.getCell(1, 0)[restriction][field]).toBe(value);
            expect(editor.getCell(2, 0)[restriction][field]).toBe('');
            expect(editor.defaultCell[restriction][field]).toBe('');
        });
    });
});

describe('cell dialog applies allowed and disallowed pairs', () => {
    it('stores the disallowed list and grid types when the allowed ones are left empty', () => {
        const editor = createEditorWithCell(makeCell({
            column: 0,
            allowed: { CType: 'text', list_type: 'news_pi1', tx_gridelements_backend_layout: 'container' },
        }));

        submitCellDialog(editor, 0, 0, {
            'allowed': [],
            'disallowed': ['gridelements_pi1'],
            'allowed-list-types': [],
            'disallowed-list-types': ['news_pi1'],
            'allowed-grid-types': [],
            'disallowed-grid-types': ['container'],
        });

        expect(editor.getCell(0, 0).allowed).toEqual({ CType: '*', list_type: '', tx_gridelements_backend_layout: '' });
        expect(editor.getCell(0, 0).disallowed).toEqual({
            CType: 'gridelements_pi1', list_type: 'news_pi1', tx_gridelements_backend_layout: 'container',
        });
        expect(editor.export2LayoutRecord()).toContain('disallowed {\n\t\t\t\t\t\tCType = gridelements_pi1\n\t\t\t\t\t\tlist_type = news_pi1\n\t\t\t\t\t\ttx_gridelements_backend_layout = container\n');
    });

    it('works on a cell without allowed/disallowed structure (column without colPos)', () => {
        const cell = makeCell({ name: 'No colPos' });
        delete cell.allowed;
        delete cell.disallowed;
        const editor = createEditorWithCell(cell);

        submitCellDialog(editor, 0, 0, {
            'allowed': ['text'],
            'disallowed': [],
            'allowed-list-types': ['news_pi1'],
            'disallowed-list-types': [],
            'allowed-grid-types': [],
            'disallowed-grid-types': ['container'],
        });

        expect(editor.getCell(0, 0).allowed).toEqual({ CType: 'text', list_type: 'news_pi1', tx_gridelements_backend_layout: '' });
        expect(editor.getCell(0, 0).disallowed).toEqual({ CType: '', list_type: '', tx_gridelements_backend_layout: 'container' });
    });
});

describe('column (colPos) from the cell dialog', () => {
    function exportedColPos(editor) {
        const match = editor.export2LayoutRecord().match(/^\t+colPos = (.*)$/m);
        return match ? match[1] : null;
    }

    function dialogColPos(editor) {
        editor.showOptions(0, 0);
        return $(Modal.currentModal.content).find('.t3js-grideditor-field-colpos').val();
    }

    beforeEach(() => {
        globalThis.TYPO3.lang.grid_notSet = 'not set';
    });

    it('keeps a new cell without content column when the field is left empty', () => {
        const editor = createEditor({ colCount: 1, rowCount: 1 });

        submitCellDialog(editor, 0, 0, { 'colpos': '' });

        expect(editor.getCell(0, 0).column).toBeUndefined();
        expect(exportedColPos(editor)).toBeNull();
        expect(document.querySelector('.grideditor-cell-info').textContent).toContain('not set');
        expect(dialogColPos(editor)).toBe('');
    });

    it('removes the content column when an existing value is cleared', () => {
        const editor = createEditorWithCell(makeCell({ column: 3 }));
        expect(exportedColPos(editor)).toBe('3');

        submitCellDialog(editor, 0, 0, { 'colpos': '' });

        expect(editor.getCell(0, 0).column).toBeUndefined();
        expect(exportedColPos(editor)).toBeNull();
        expect(dialogColPos(editor)).toBe('');
    });

    it.each([
        ['0', 0],
        ['5', 5],
        ['12', 12],
        [' 7 ', 7],
        ['-1', -1],
    ])('stores the integer %j unchanged and round-trips it', (input, expected) => {
        const editor = createEditor({ colCount: 1, rowCount: 1 });

        submitCellDialog(editor, 0, 0, { 'colpos': input });

        expect(editor.getCell(0, 0).column).toBe(expected);
        expect(exportedColPos(editor)).toBe(String(expected));
        expect(dialogColPos(editor)).toBe(String(expected));
    });

    it.each(['abc', '3abc', '1.5', '1e3', '-'])('does not export the invalid input %j as a column', (input) => {
        const editor = createEditorWithCell(makeCell({ column: 3 }));

        submitCellDialog(editor, 0, 0, { 'colpos': input });

        expect(editor.getCell(0, 0).column).toBeUndefined();
        expect(exportedColPos(editor)).toBeNull();
        expect(editor.export2LayoutRecord()).not.toContain('NaN');
    });

    it('setColumn treats NaN as no content column and returns false outside the grid', () => {
        const editor = createEditorWithCell(makeCell({ column: 3 }));

        expect(editor.setColumn(NaN, 0, 0)).toBe(true);
        expect(editor.getCell(0, 0).column).toBeUndefined();
        expect(editor.setColumn(0, 0, 0)).toBe(true);
        expect(editor.getCell(0, 0).column).toBe(0);
        expect(editor.setColumn(1, 5, 5)).toBe(false);
    });
});

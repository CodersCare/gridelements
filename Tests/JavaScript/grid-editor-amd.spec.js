import { beforeEach, describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import $ from 'jquery';

const source = readFileSync(resolve(dirname(fileURLToPath(import.meta.url)), '../../Resources/Public/JavaScript/GridEditor.js'), 'utf8');

// Data of GridelementsBackendLayoutWizardElement11 for a layout with a cell without colPos
const rows = [[
    { colspan: 1, rowspan: 1, name: 'Free', column: 0, allowed: { CType: '*' }, disallowed: [] },
    { colspan: 1, rowspan: 1, name: 'No colPos' },
    { colspan: 1, rowspan: 1, name: 'Restricted', column: 1, allowed: { CType: 'text' }, disallowed: { list_type: 'news_pi1' } },
]];

// TYPO3 11 Modal.show() returns the jQuery modal, its buttons trigger "button.clicked"
const Modal = {
    currentModal: null,
    show(title, content, severity, buttons) {
        const $modal = $('<div class="modal"></div>').append(content);
        buttons.forEach((button) => $modal.append($('<button></button>').attr('name', button.name)));
        $(document.body).append($modal);
        Modal.currentModal = $modal;
        return $modal;
    },
};
const Severity = { notice: 0, getCssClass: () => 'notice' };

function createEditor() {
    document.body.innerHTML = '<input type="hidden" name="myfield" />';
    $(document.body).append($('<div class="t3js-grideditor"></div>').attr({
        'data-data': JSON.stringify(rows),
        'data-colcount': rows[0].length,
        'data-rowcount': rows.length,
        'data-field': 'myfield',
    }));
    let GridEditor;
    new Function('define', source)((dependencies, factory) => {
        GridEditor = factory($, Modal, Severity);
    });
    return GridEditor;
}

function saveColPos(editor, col, value) {
    editor.showOptions(col, 0);
    Modal.currentModal.find('.t3js-grideditor-field-colpos').val(value);
    Modal.currentModal.find('button[name="ok"]').trigger('button.clicked');
    return $('input[name="myfield"]').val();
}

const colPosOf = (field, name) => {
    const block = field.split(/\n\s*\d+ \{\n/).find(part => new RegExp('^\\s*name = ' + name + '\\n').test(part));
    const match = block && block.match(/^\s*colPos = (.*)$/m);
    return match ? match[1] : null;
};

beforeEach(() => {
    $(document).off();
    globalThis.TYPO3 = { lang: {}, settings: {} };
});

describe('TYPO3 11 GridEditor colPos', () => {
    it.each(['', 'abc', '3abc', '1.5', '-'])('input %j means no content column', (value) => {
        const editor = createEditor();
        const field = saveColPos(editor, 0, value);

        expect(editor.getCell(0, 0).column).toBeUndefined();
        expect(colPosOf(field, 'Free')).toBeNull();
    });

    it.each([['0', '0'], [' 7 ', '7'], ['-1', '-1']])('input %j is the content column %s', (value, expected) => {
        const field = saveColPos(createEditor(), 2, value);

        expect(colPosOf(field, 'Restricted')).toBe(expected);
    });

    it('a cell without colPos gets a content column', () => {
        const field = saveColPos(createEditor(), 1, '4');

        expect(colPosOf(field, 'No colPos')).toBe('4');
    });
});

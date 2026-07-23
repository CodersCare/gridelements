import { describe, expect, it } from 'vitest';
import {
    getColumnRestriction,
    parseTypeList,
    resolveEffectiveAllowedCtype,
    typeOk,
} from '../../Resources/Public/JavaScript/gridelements-column-restrictions.js';

describe('parseTypeList', () => {
    it('returns null for missing/empty values', () => {
        expect(parseTypeList(null)).toBeNull();
        expect(parseTypeList(undefined)).toBeNull();
        expect(parseTypeList('')).toBeNull();
    });

    it('splits and trims a comma list', () => {
        expect(parseTypeList('text, textmedia ,header')).toEqual(['text', 'textmedia', 'header']);
    });

    it('drops empty entries produced by stray commas', () => {
        expect(parseTypeList('text,,header,')).toEqual(['text', 'header']);
    });

    it('coerces non-string input via toString()', () => {
        expect(parseTypeList(1)).toEqual(['1']);
    });
});

describe('typeOk', () => {
    it('allows any value when both lists are absent', () => {
        expect(typeOk(null, null, 'text')).toBe(true);
    });

    it('rejects a value missing from a restrictive allow list', () => {
        expect(typeOk(['text', 'header'], null, 'image')).toBe(false);
    });

    it('accepts "*" in the allow list as allow-everything', () => {
        expect(typeOk(['*'], null, 'anything')).toBe(true);
    });

    it('rejects a value present in the disallow list even if also allowed', () => {
        expect(typeOk(['text', 'image'], ['image'], 'image')).toBe(false);
    });

    it('treats "*" in the disallow list as block-everything', () => {
        expect(typeOk(null, ['*'], 'text')).toBe(false);
    });

    it('compares numeric and string identifiers as equal (DB-record grid layout uids)', () => {
        expect(typeOk(['1'], null, 1)).toBe(true);
        expect(typeOk(null, ['1'], 1)).toBe(false);
    });
});

describe('resolveEffectiveAllowedCtype', () => {
    it('leaves an unrestricted column (null) untouched', () => {
        expect(resolveEffectiveAllowedCtype(null, ['something'], ['container'])).toBeNull();
    });

    it('leaves a wildcard column untouched', () => {
        expect(resolveEffectiveAllowedCtype(['*'], null, ['container'])).toEqual(['*']);
    });

    it('adds gridelements_pi1 when the column restricts grid types but does not already list it', () => {
        const result = resolveEffectiveAllowedCtype(['header', 'text', 'shortcut'], ['something'], ['container']);
        expect(result).toEqual(['header', 'text', 'shortcut', 'list', 'gridelements_pi1']);
    });

    it('does not duplicate gridelements_pi1 if it is already listed', () => {
        expect(resolveEffectiveAllowedCtype(['gridelements_pi1'], null, ['container'])).toEqual(['gridelements_pi1']);
    });

    it('does not add list/gridelements_pi1 when there is no list_type/grid type restriction', () => {
        expect(resolveEffectiveAllowedCtype(['text'], null, null)).toEqual(['text']);
    });

    it('does not mutate the input array', () => {
        const input = ['header'];
        resolveEffectiveAllowedCtype(input, null, ['container']);
        expect(input).toEqual(['header']);
    });
});

describe('getColumnRestriction', () => {
    it('reads all six data attributes and applies the CType merge', () => {
        const column = document.createElement('td');
        column.setAttribute('data-allowed-ctype', 'header,text,shortcut');
        column.setAttribute('data-allowed-list_type', 'something');
        column.setAttribute('data-allowed-tx_gridelements_backend_layout', 'container');
        column.setAttribute('data-disallowed-ctype', 'text');

        const restriction = getColumnRestriction(column);

        expect(restriction).toEqual({
            allowedCtype: ['header', 'text', 'shortcut', 'list', 'gridelements_pi1'],
            disallowedCtype: ['text'],
            allowedListType: ['something'],
            disallowedListType: null,
            allowedGridType: ['container'],
            disallowedGridType: null,
        });
    });

    it('returns an all-null restriction for a null element', () => {
        expect(getColumnRestriction(null)).toEqual({
            allowedCtype: null,
            disallowedCtype: null,
            allowedListType: null,
            disallowedListType: null,
            allowedGridType: null,
            disallowedGridType: null,
        });
    });
});

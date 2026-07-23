/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

export function parseTypeList(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }
    return value.toString().split(',').map(s => s.trim()).filter(Boolean);
}

export function typeOk(allowedList, disallowedList, value) {
    const stringValue = value === '' || value === null || value === undefined ? '' : String(value);
    const allowed = !allowedList || allowedList.includes('*') || allowedList.includes(stringValue);
    const disallowed = !!disallowedList && (disallowedList.includes('*') || disallowedList.includes(stringValue));
    return allowed && !disallowed;
}

/**
 * The plain page column template exposes the raw, unmerged backend layout "allowed.CType"
 * definition. It does not carry over the auto-permission gridelements grants for its own
 * CType whenever a column restricts allowed/disallowed grid layouts (mirrors the merge done
 * in GridElementsHelper::mergeAllowedDisallowedSettings() / GridelementsGridColumn.php, which
 * columns nested inside a grid container already benefit from). Without this, a column that
 * only configures "allowed.tx_gridelements_backend_layout" (the common case, since admins
 * aren't required to also list "gridelements_pi1" in "allowed.CType") would reject that
 * container - or, for paste, only offer "Paste Reference" - since the CType check alone
 * already rejects it before the grid type check is ever consulted.
 */
export function resolveEffectiveAllowedCtype(allowedCtype, allowedListType, allowedGridType) {
    if (!allowedCtype || allowedCtype.includes('*')) {
        return allowedCtype;
    }
    const list = [...allowedCtype];
    if (allowedListType && !list.includes('list')) {
        list.push('list');
    }
    if (allowedGridType && !list.includes('gridelements_pi1')) {
        list.push('gridelements_pi1');
    }
    return list;
}

export function getColumnRestriction(element) {
    const getTypes = (attr) => element ? parseTypeList(element.getAttribute(attr)) : null;
    const allowedListType = getTypes('data-allowed-list_type');
    const allowedGridType = getTypes('data-allowed-tx_gridelements_backend_layout');
    return {
        allowedCtype: resolveEffectiveAllowedCtype(getTypes('data-allowed-ctype'), allowedListType, allowedGridType),
        disallowedCtype: getTypes('data-disallowed-ctype'),
        allowedListType,
        disallowedListType: getTypes('data-disallowed-list_type'),
        allowedGridType,
        disallowedGridType: getTypes('data-disallowed-tx_gridelements_backend_layout'),
    };
}

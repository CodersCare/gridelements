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
import interact from "interactjs";
import DocumentService from "@typo3/core/document-service.js";
import DataHandler from "@typo3/backend/ajax-data-handler.js";
import Icons from "@typo3/backend/icons.js";
import RegularEvent from "@typo3/core/event/regular-event.js";

class DragDrop {
    constructor() {
        DocumentService.ready().then((() => {
            DragDrop.initialize()
        }))
    }

    static initialize() {
        const moduleBody = document.querySelector('.module');

        // Pipe scroll attempt to parent element
        new RegularEvent('wheel', (e) => {
            moduleBody.scrollLeft += e.deltaX;
            moduleBody.scrollTop += e.deltaY;
        }).delegateTo(document, '.draggable-dragging');

        interact(DragDrop.draggableContentIdentifier)
            .draggable({
                allowFrom: DragDrop.draggableContentHandleIdentifier,
                inertia: true,
                onstart: DragDrop.onDragStart,
                onmove: DragDrop.onDragMove,
                onend: DragDrop.onDragEnd,
            })
            .pointerEvents({
                allowFrom: DragDrop.draggableContentHandleIdentifier,
            })
            .on('move', function (event) {
                const interaction = event.interaction;
                const currentTarget = event.currentTarget;
                if (interaction.pointerIsDown && !interaction.interacting() && currentTarget.getAttribute('clone') != 'false') {
                    const clone = currentTarget.cloneNode(true);
                    clone.setAttribute('data-dragdrop-clone', 'true');
                    currentTarget.parentNode.insertBefore(clone, currentTarget.nextSibling);
                    // This placeholder stays behind in the item's original grid slot while
                    // currentTarget itself is turned into the floating, cursor-following ghost.
                    // Its own (cloned) drop zone is what represents "copy right here" - see
                    // showDropZones()/hideDropZones(), which keep the ghost's copy hidden always.
                    DragDrop.placeholderClone = clone;
                    interaction.start({ name: 'drag' }, event.interactable, currentTarget);
                }
            });

        interact(DragDrop.dropZoneIdentifier).dropzone({
            accept: this.draggableContentIdentifier,
            ondrop: DragDrop.onDrop,
            checker: (
                dragEvent,
                event,
                dropped,
                dropzone,
                dropElement
            ) => {
                const dropzoneRect = dropElement.getBoundingClientRect();

                const withinBounds = (event.pageX >= dropzoneRect.left && event.pageX <= dropzoneRect.left + dropzoneRect.width) // is cursor in boundaries of x-axis
                    && (event.pageY >= dropzoneRect.top && event.pageY <= dropzoneRect.top + dropzoneRect.height); // is cursor in boundaries of y-axis;

                if (!withinBounds) {
                    return false;
                }

                if (!DragDrop.isAllowedDropZone(dropElement)) {
                    return false;
                }

                // The floating ghost following the cursor is a visual preview only - its own
                // (nested) drop zones are never valid targets.
                if (DragDrop.draggedElement && DragDrop.draggedElement.contains(dropElement)) {
                    return false;
                }

                // The placeholder left behind in the item's original slot represents "copy right
                // here" and only accepts drops while copying; moving into your own column (nesting
                // a container inside itself) stays blocked. Read the modifier key live off this
                // check's own event so a key pressed/released mid-drag is honored immediately.
                if (DragDrop.placeholderClone && DragDrop.placeholderClone.contains(dropElement) && !DragDrop.isCopyModifier(dragEvent)) {
                    return false;
                }

                // Same reasoning for the zone immediately preceding the item's current position -
                // it's the same no-op position, just approached from the other side.
                if (dropElement === DragDrop.prevDropZone && !DragDrop.isCopyModifier(dragEvent)) {
                    return false;
                }

                return true;
            }
        }).on('dragenter', (e) => {
            e.target.classList.add(DragDrop.dropPossibleHoverClass);
        }).on('dragleave', (e) => {
            e.target.classList.remove(DragDrop.dropPossibleHoverClass);
        });
    }

    static onDragStart(e) {
        e.target.dataset.dragStartX = (e.client.x - e.rect.left).toString();
        e.target.dataset.dragStartY = (e.client.y - e.rect.top).toString();

        // Configure styling of element
        e.target.style.width = getComputedStyle(e.target).getPropertyValue('width');
        e.target.classList.add('draggable-dragging');
        e.target.style.position = 'fixed';

        const copyMessage = document.createElement('div');
        copyMessage.classList.add('draggable-copy-message');
        copyMessage.textContent = TYPO3.lang['dragdrop.copy.message'];
        e.target.append(copyMessage);

        e.target.closest(DragDrop.columnIdentifier).classList.remove('active');

        DragDrop.draggedElement = e.target;
        // The drop zone immediately preceding the item's own position - either the previous
        // sibling's own zone, or (if it's the first item) the column's own top-of-column zone -
        // represents the exact same position the item is already in and is a no-op for a plain
        // move; only offer it while copying, same as the item's own trailing zone.
        DragDrop.prevDropZone = DragDrop.findPrevDropZone(e.target);
        // Core Record.html (used for page column elements) does not put data-ctype on .t3js-page-ce;
        // fall back to .t3-ctype-identifier which gridelements' header partial always renders.
        const ctypeIdentifier = e.target.querySelector('.t3-ctype-identifier');
        DragDrop.draggedCType = e.target.dataset.ctype || ctypeIdentifier?.dataset.ctype || '';
        DragDrop.draggedListType = e.target.dataset.list_type || ctypeIdentifier?.dataset.list_type || '';
        DragDrop.draggedGridType = e.target.dataset.tx_gridelements_backend_layout || ctypeIdentifier?.dataset.tx_gridelements_backend_layout || '';
        DragDrop.copyMode = DragDrop.isCopyModifier(e);

        document.addEventListener('keydown', DragDrop.onModifierKeyChange);
        document.addEventListener('keyup', DragDrop.onModifierKeyChange);

        DragDrop.showDropZones();
    }

    static onDragMove(e) {
        const scrollSensitivity = 20;
        const scrollSpeed = 20;
        const moduleContainer = document.querySelector('.module');

        // Re-calculate position of draggable element
        e.target.style.left = `${e.client.x - parseInt(e.target.dataset.dragStartX, 10)}px`;
        e.target.style.top = `${e.client.y - parseInt(e.target.dataset.dragStartY, 10)}px`;

        // Scroll when draggable leaves the viewport
        if (e.delta.x < 0 && e.pageX - scrollSensitivity < 0) {
            // Scroll left
            moduleContainer.scrollLeft -= scrollSpeed;
        } else if (e.delta.x > 0 && e.pageX + scrollSensitivity > moduleContainer.offsetWidth) {
            // Scroll right
            moduleContainer.scrollLeft += scrollSpeed;
        }

        if (e.delta.y < 0 && e.pageY - scrollSensitivity - document.querySelector('.t3js-module-docheader').clientHeight < 0) {
            // Scroll up
            moduleContainer.scrollTop -= scrollSpeed;
        } else if (e.delta.y > 0 && e.pageY + scrollSensitivity > moduleContainer.offsetHeight) {
            // Scroll down
            moduleContainer.scrollTop += scrollSpeed;
        }
    }

    static onDragEnd(e) {
        e.target.dataset.dragStartX = '';
        e.target.dataset.dragStartY = '';

        e.target.classList.remove('draggable-dragging');
        e.target.style.width = 'unset';
        e.target.style.left = 'unset';
        e.target.style.top = 'unset';
        e.target.style.position = 'unset';

        // Show create new element button
        e.target.closest(DragDrop.columnIdentifier).classList.add('active');
        e.target.querySelector('.draggable-copy-message').remove();

        document.removeEventListener('keydown', DragDrop.onModifierKeyChange);
        document.removeEventListener('keyup', DragDrop.onModifierKeyChange);

        DragDrop.hideDropZones();

        DragDrop.draggedElement = null;
        DragDrop.placeholderClone = null;
        DragDrop.prevDropZone = null;
        DragDrop.draggedCType = '';
        DragDrop.draggedListType = '';
        DragDrop.draggedGridType = '';
        DragDrop.copyMode = false;

        // Remove clones
        document.querySelectorAll(DragDrop.draggableContentCloneIdentifier).forEach((element) => {
            element.remove();
        });
    }

    /**
     * Windows uses CTRL, macOS conventionally uses ALT/Option as the "copy while dragging" modifier.
     * Both are treated the same: whichever one matches the current platform triggers copy mode.
     */
    static isCopyModifier(e) {
        return (navigator.userAgent.includes('Mac') ? e.altKey : e.ctrlKey) || false;
    }

    static onModifierKeyChange(e) {
        if (e.key !== 'Control' && e.key !== 'Alt') {
            return;
        }
        const newCopyMode = DragDrop.isCopyModifier(e);
        if (newCopyMode === DragDrop.copyMode) {
            return;
        }
        DragDrop.copyMode = newCopyMode;
        // Re-evaluate which drop zones are valid now that move/copy intent changed
        // (this is what unlocks/locks a container's own sub-columns as drop targets).
        DragDrop.hideDropZones();
        DragDrop.showDropZones();
    }

    /**
     * Finds the drop zone that sits immediately before the given (dragged) element: the previous
     * sibling item's own trailing zone, or - if there's no preceding item in this column - the
     * column's own top-of-column zone (rendered by ColumnHeader.html for both grid container and
     * page columns). Dropping there would place the item exactly where it already sits.
     */
    static findPrevDropZone(element) {
        const prevSibling = element.previousElementSibling;
        if (prevSibling) {
            const dz = prevSibling.querySelector(':scope > ' + DragDrop.dropZoneIdentifier);
            if (dz) {
                return dz;
            }
        }
        let node = element.parentElement?.previousElementSibling;
        while (node) {
            const dz = node.querySelector(':scope > ' + DragDrop.dropZoneIdentifier);
            if (dz) {
                return dz;
            }
            node = node.previousElementSibling;
        }
        return null;
    }

    /**
     * Checks whether the currently dragged element's CType/list_type/grid layout type is
     * permitted in the column the given drop zone belongs to, mirroring the allowed/disallowed
     * restrictions configured for that grid column (the data-allowed-... / data-disallowed-... attributes).
     */
    static isAllowedDropZone(dropZone) {
        const column = dropZone.closest(DragDrop.columnIdentifier);
        if (!column) {
            return true;
        }
        const ctype = DragDrop.draggedCType || '';
        const allowedCtype = column.getAttribute('data-allowed-ctype') || '';
        const disallowedCtype = column.getAttribute('data-disallowed-ctype') || '';
        const allowedGridType = column.getAttribute('data-allowed-tx_gridelements_backend_layout') || '';
        if (disallowedCtype === '*') {
            return false;
        }
        if (disallowedCtype && disallowedCtype.split(',').includes(ctype)) {
            return false;
        }
        if (allowedCtype && allowedCtype !== '*' && !allowedCtype.split(',').includes(ctype)) {
            // Mirror PHP GridelementsGridColumn::setRestrictions(): when specific grid layouts are
            // allowed on a column, gridelements_pi1 is implicitly permitted even without explicit
            // CType listing (page column backend layouts don't auto-add it like gridelements does).
            if (!(ctype === 'gridelements_pi1' && allowedGridType)) {
                return false;
            }
        }
        if (ctype === 'list') {
            const listType = DragDrop.draggedListType || '';
            const allowedListType = column.getAttribute('data-allowed-list_type') || '';
            const disallowedListType = column.getAttribute('data-disallowed-list_type') || '';
            if (disallowedListType === '*') {
                return false;
            }
            if (disallowedListType && disallowedListType.split(',').includes(listType)) {
                return false;
            }
            if (allowedListType && allowedListType !== '*' && !allowedListType.split(',').includes(listType)) {
                return false;
            }
        }
        if (ctype === 'gridelements_pi1') {
            const gridType = DragDrop.draggedGridType || '';
            const disallowedGridType = column.getAttribute('data-disallowed-tx_gridelements_backend_layout') || '';
            if (disallowedGridType === '*') {
                return false;
            }
            if (disallowedGridType && disallowedGridType.split(',').includes(gridType)) {
                return false;
            }
            if (allowedGridType && allowedGridType !== '*' && !allowedGridType.split(',').includes(gridType)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Reveals drop zones that are valid targets for the drag currently in progress (allowed/
     * disallowed CType/list_type/grid type, and - unless copying - not nested inside the dragged
     * element itself, which would nest a container inside its own column).
     *
     * Every "create new content" button is hidden unconditionally while dragging - no button
     * should stay visible mid-drag - but every button/drop-zone pair occupies the same space
     * (see Record.html/ColumnHeader.html), so hiding a button without something taking its place
     * collapses that column's whitespace. Zones that become real drop targets swap in for their
     * button 1:1 (hidden via the `hidden` attribute, i.e. removed from flow, same as before);
     * zones that stay invalid instead hide their button via `visibility: hidden`, which keeps its
     * box (and the column's height) reserved without showing it or a misleadingly "available"
     * drop zone.
     */
    static showDropZones() {
        document.querySelectorAll(DragDrop.addContentIdentifier).forEach((button) => {
            button.style.visibility = 'hidden';
        });
        document.querySelectorAll(DragDrop.dropZoneIdentifier).forEach((element) => {
            const isGhostZone = DragDrop.draggedElement && DragDrop.draggedElement.contains(element);
            if (isGhostZone) {
                // The floating ghost is position:fixed and no longer part of the page layout -
                // nothing needs its space reserved, so its own zone/button are simply left alone.
                return;
            }
            const isOwnPlaceholderZone = DragDrop.placeholderClone && DragDrop.placeholderClone.contains(element);
            const isPrevDropZone = element === DragDrop.prevDropZone;
            if ((isOwnPlaceholderZone || isPrevDropZone) && !DragDrop.copyMode) {
                return;
            }
            if (!DragDrop.isAllowedDropZone(element)) {
                return;
            }
            element.hidden = false;
            element.classList.add(DragDrop.validDropZoneClass);
            // :scope > restricts this to the zone's own sibling button. A plain querySelector()
            // would also match buttons nested deep inside a grid container's own sub-columns,
            // hiding the wrong one and leaving the container's real button visibly stuck in
            // front of its now-active zone.
            const addContentButton = element.parentElement.querySelector(':scope > ' + DragDrop.addContentIdentifier);
            if (addContentButton !== null) {
                addContentButton.hidden = true;
            }
        });
    }

    static hideDropZones() {
        document.querySelectorAll(DragDrop.dropZoneIdentifier + '.' + DragDrop.validDropZoneClass).forEach((element) => {
            element.classList.remove(DragDrop.validDropZoneClass);
            element.hidden = true;
        });
        document.querySelectorAll(DragDrop.addContentIdentifier).forEach((button) => {
            button.hidden = false;
            button.style.visibility = '';
        });
    }

    static onDrop(e) {
        const dropContainer = e.target, draggedElement = e.relatedTarget,
            contentElementUid = parseInt(draggedElement.dataset.uid, 10);

        const isCopyAction = (DragDrop.isCopyModifier(e.dragEvent) || dropContainer.classList.contains('t3js-paste-copy'));

        // Defense in depth: the drop zone should already be hidden/rejected by showDropZones()
        // and the dropzone checker, but never let a container be moved into one of its own
        // (nested) columns - that orphans/self-nests the record. Copying into itself is fine.
        if (!isCopyAction && (draggedElement.contains(dropContainer) || (DragDrop.placeholderClone && DragDrop.placeholderClone.contains(dropContainer)) || dropContainer === DragDrop.prevDropZone)) {
            return;
        }

        if (!DragDrop.isAllowedDropZone(dropContainer)) {
            return;
        }

        let newColumn = DragDrop.getColumnPositionForElement(dropContainer),
            gridColumn = DragDrop.getGridColumnPositionForElement(dropContainer);
        if ("number" == typeof contentElementUid && contentElementUid > 0) {
            const parameters = {};
            if (gridColumn !== false && gridColumn !== '') {
                newColumn = -1;
            } else {
                gridColumn = 0;
            }

            // add the information about a possible column position change
            const targetFound = (dropContainer.closest(DragDrop.contentIdentifier)).dataset.uid;
            // the item was moved to the top of the colPos, so the page ID is used here
            let targetPid;
            if (targetFound === undefined) {
                // the actual page is needed. Read it from the container into which the element was dropped.
                targetPid = parseInt((dropContainer.closest('[data-page]'))?.dataset.page, 10);
            } else {
                // the negative value of the content element after where it should be moved
                targetPid = 0 - parseInt(targetFound, 10);
            }

            // the dragged elements language uid
            let language = parseInt(draggedElement.dataset.languageUid, 10);
            if (language !== -1) {
                // new elements language must be the same as the column the element is dropped in if element is not -1
                language = parseInt((dropContainer.closest('[data-language-uid]'))?.dataset.languageUid ?? '-1', 10);
            }

            const container = parseInt(dropContainer?.closest('.t3-grid-element-container')?.closest(DragDrop.contentIdentifier)?.dataset?.uid) || 0;

            let colPos = 0;
            if (container > 0 && gridColumn !== false && gridColumn !== '') {
                colPos = -1;
            } else if (targetPid !== 0) {
                colPos = newColumn;
            }

            const datahandlerCommand = isCopyAction ? 'copy' : 'move';
            parameters.cmd = {
                tt_content: {
                    [contentElementUid]: {
                        [datahandlerCommand]: {
                            action: 'paste',
                            target: targetPid,
                            update: {
                                colPos: colPos,
                                sys_language_uid: language,
                                tx_gridelements_container: container,
                                tx_gridelements_columns: gridColumn
                            },
                        }
                    }
                }
            };

            DragDrop.ajaxAction(dropContainer, draggedElement, parameters, isCopyAction).then(() => {
                const languageDescriber = document.querySelector(`.t3-page-column-lang-name[data-language-uid="${language}"]`);
                if (languageDescriber === null) {
                    return;
                }

                const newFlagIdentifier = languageDescriber.dataset.flagIdentifier;
                const newLanguageTitle = languageDescriber.dataset.languageTitle;

                Icons.getIcon(newFlagIdentifier, Icons.sizes.small).then((markup) => {
                    const flagIcon = draggedElement.querySelector('.t3js-flag');
                    flagIcon.title = newLanguageTitle;
                    flagIcon.innerHTML = markup;
                });
            });
        }
    }

    static ajaxAction(e, t, r, a) {
        const o = Object.keys(r.cmd).shift(), n = parseInt(Object.keys(r.cmd[o]).shift(), 10),
            s = {component: "dragdrop", action: a ? "copy" : "move", table: o, uid: n};
        return DataHandler.process(r, s).then((r => {
            if (r.hasErrors) throw r.messages;
            e.parentElement.classList.contains(DragDrop.contentIdentifier.substring(1)) ? e.closest(DragDrop.contentIdentifier).after(t) : e.closest(DragDrop.dropZoneIdentifier).after(t), a && self.location.reload()
        }))
    }

    static getColumnPositionForElement(e) {
        const t = e.closest("[data-colpos]");
        return null !== t && void 0 !== t.dataset.colpos && parseInt(t.dataset.colpos, 10)
    }

    static getGridColumnPositionForElement(e) {
        const gc =  e.closest(".t3-grid-element-container");
        const t = e.closest("[data-colpos]");
        return gc !== null && null !== t && void 0 !== t.dataset.colpos && parseInt(t.dataset.colpos, 10)
    }
}

DragDrop.contentIdentifier = ".t3js-page-ce", DragDrop.draggableContentIdentifier = ".t3js-page-ce-sortable", DragDrop.draggableContentHandleIdentifier = ".t3js-page-ce-draghandle", DragDrop.draggableContentCloneIdentifier = "[data-dragdrop-clone]", DragDrop.dropZoneIdentifier = ".t3js-page-ce-dropzone-available", DragDrop.columnIdentifier = ".t3js-page-column", DragDrop.validDropZoneClass = "active", DragDrop.dropPossibleHoverClass = "t3-page-ce-dropzone-possible", DragDrop.addContentIdentifier = ".t3js-page-new-ce";
DragDrop.draggedElement = null;
DragDrop.placeholderClone = null;
DragDrop.prevDropZone = null;
DragDrop.draggedCType = '';
DragDrop.draggedListType = '';
DragDrop.draggedGridType = '';
DragDrop.copyMode = false;
export default new DragDrop;

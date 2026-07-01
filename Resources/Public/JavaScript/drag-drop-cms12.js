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

        new RegularEvent('wheel', (e) => {
            moduleBody.scrollLeft += e.deltaX;
            moduleBody.scrollTop += e.deltaY;
        }).delegateTo(document, '.draggable-dragging');

        // Header.html renders draggable="true" for CMS13's native drag-drop.js; this CMS12
        // script drives dragging via interact.js instead, so cancel native drag here.
        new RegularEvent('dragstart', (e) => {
            e.preventDefault();
        }).delegateTo(document, DragDrop.draggableContentHandleIdentifier);

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
                    // Deep clone still matches the draggable selector - mark it so it can't start its own drag.
                    clone.setAttribute('clone', 'false');
                    currentTarget.parentNode.insertBefore(clone, currentTarget.nextSibling);
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

                const withinBounds = (event.pageX >= dropzoneRect.left && event.pageX <= dropzoneRect.left + dropzoneRect.width)
                    && (event.pageY >= dropzoneRect.top && event.pageY <= dropzoneRect.top + dropzoneRect.height);

                if (!withinBounds) {
                    return false;
                }

                if (!DragDrop.isAllowedDropZone(dropElement)) {
                    return false;
                }

                // The floating ghost's own nested drop zones are never valid targets.
                if (DragDrop.draggedElement && DragDrop.draggedElement.contains(dropElement)) {
                    return false;
                }

                // The placeholder's own zone is a no-op move; only valid while copying.
                if (DragDrop.placeholderClone && DragDrop.placeholderClone.contains(dropElement) && !DragDrop.isCopyModifier(dragEvent)) {
                    return false;
                }

                // Same no-op reasoning for the zone immediately preceding the item's current position.
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

        e.target.style.width = getComputedStyle(e.target).getPropertyValue('width');
        e.target.classList.add('draggable-dragging');
        e.target.style.position = 'fixed';

        const copyMessage = document.createElement('div');
        copyMessage.classList.add('draggable-copy-message');
        copyMessage.textContent = TYPO3.lang['dragdrop.copy.message'];
        e.target.append(copyMessage);

        e.target.closest(DragDrop.columnIdentifier).classList.remove('active');

        DragDrop.draggedElement = e.target;
        DragDrop.prevDropZone = DragDrop.findPrevDropZone(e.target);
        // Core Record.html doesn't put data-ctype on .t3js-page-ce; fall back to .t3-ctype-identifier.
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

        e.target.style.left = `${e.client.x - parseInt(e.target.dataset.dragStartX, 10)}px`;
        e.target.style.top = `${e.client.y - parseInt(e.target.dataset.dragStartY, 10)}px`;

        if (e.delta.x < 0 && e.pageX - scrollSensitivity < 0) {
            moduleContainer.scrollLeft -= scrollSpeed;
        } else if (e.delta.x > 0 && e.pageX + scrollSensitivity > moduleContainer.offsetWidth) {
            moduleContainer.scrollLeft += scrollSpeed;
        }

        if (e.delta.y < 0 && e.pageY - scrollSensitivity - document.querySelector('.t3js-module-docheader').clientHeight < 0) {
            moduleContainer.scrollTop -= scrollSpeed;
        } else if (e.delta.y > 0 && e.pageY + scrollSensitivity > moduleContainer.offsetHeight) {
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

        document.querySelectorAll(DragDrop.draggableContentCloneIdentifier).forEach((element) => {
            element.remove();
        });
    }

    /** Windows uses CTRL, macOS uses ALT/Option as the copy-while-dragging modifier. */
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
        DragDrop.hideDropZones();
        DragDrop.showDropZones();
    }

    /** Finds the drop zone immediately before the given element (a no-op drop target). */
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
            // Mirrors PHP GridelementsGridColumn::setRestrictions(): allowed grid layouts implicitly permit gridelements_pi1.
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

    /** Reveals valid drop zones for the drag in progress, swapping each 1:1 for its "create new content" button. */
    static showDropZones() {
        document.querySelectorAll(DragDrop.addContentIdentifier).forEach((button) => {
            button.style.visibility = 'hidden';
        });
        document.querySelectorAll(DragDrop.dropZoneIdentifier).forEach((element) => {
            const isGhostZone = DragDrop.draggedElement && DragDrop.draggedElement.contains(element);
            if (isGhostZone) {
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
            // :scope > avoids matching buttons nested in a grid container's own sub-columns.
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

        // Defense in depth: never let a container be moved into one of its own nested columns.
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

            const targetFound = (dropContainer.closest(DragDrop.contentIdentifier)).dataset.uid;
            let targetPid;
            if (targetFound === undefined) {
                targetPid = parseInt((dropContainer.closest('[data-page]'))?.dataset.page, 10);
            } else {
                // Negative target = insert after this uid (TYPO3 paste convention).
                targetPid = 0 - parseInt(targetFound, 10);
            }

            let language = parseInt(draggedElement.dataset.languageUid, 10);
            if (language !== -1) {
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

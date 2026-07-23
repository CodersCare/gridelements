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
import { getColumnRestriction, typeOk } from "./gridelements-column-restrictions.js";

class DragDrop {
    constructor() {
        DocumentService.ready().then((() => {
            DragDrop.initialize()
        }))
    }

    static initialize() {
        const moduleBody = document.querySelector('.module');

        document.body.classList.add('gridelements-dragdrop-cms12');

        // Pipe scroll attempt to parent element
        new RegularEvent('wheel', (e) => {
            moduleBody.scrollLeft += e.deltaX;
            moduleBody.scrollTop += e.deltaY;
        }).delegateTo(document, '.draggable-dragging');

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
                    currentTarget.parentNode.insertBefore(clone, currentTarget.nextSibling);
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

                return (event.pageX >= dropzoneRect.left && event.pageX <= dropzoneRect.left + dropzoneRect.width) // is cursor in boundaries of x-axis
                    && (event.pageY >= dropzoneRect.top && event.pageY <= dropzoneRect.top + dropzoneRect.height); // is cursor in boundaries of y-axis;
            }
        }).on('dragenter', (e) => {
            if (e.target.classList.contains(DragDrop.validDropZoneClass)) {
                e.target.classList.add(DragDrop.dropPossibleHoverClass);
            }
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

        e.target.closest(DragDrop.columnIdentifier)?.classList.remove('active');

        DragDrop.dragTarget = e.target;
        DragDrop.dragCtype = e.target.dataset.ctype || '';
        DragDrop.dragGridType = e.target.dataset['tx_gridelements_backend_layout'] || '';
        DragDrop.dragSourceColumn = e.target.closest('.t3js-page-column');

        // Compute the "adjacent before" zone once (same-position drop — no-op for move)
        const _prevSib = DragDrop.dragTarget.previousElementSibling;
        if (_prevSib?.matches(DragDrop.draggableContentIdentifier)) {
            DragDrop.dragPrevZone = _prevSib.querySelector(DragDrop.dropZoneIdentifier) ?? null;
        } else if (!_prevSib && DragDrop.dragSourceColumn) {
            // Element is first in its column — column header zone is the "before" zone
            DragDrop.dragPrevZone = DragDrop.dragSourceColumn.querySelector(
                `:scope > .t3js-page-ce:not(.t3js-page-ce-sortable) ${DragDrop.dropZoneIdentifier}`
            ) ?? null;
        } else {
            DragDrop.dragPrevZone = null;
        }

        // Hide all add content buttons
        document.querySelectorAll(DragDrop.addContentIdentifier).forEach(btn => { btn.hidden = true; });

        // Show all allowed drop zones; adjacent zones toggled separately by CTRL/ALT
        DragDrop.showDropZones();

        DragDrop.onKeyChange = (evt) => {
            DragDrop.toggleAdjacentZones(evt.ctrlKey || evt.altKey);
        };
        document.addEventListener('keydown', DragDrop.onKeyChange);
        document.addEventListener('keyup', DragDrop.onKeyChange);
    }

    static showDropZones() {
        document.querySelectorAll(DragDrop.dropZoneIdentifier).forEach(element => {
            if (DragDrop.dragTarget?.contains(element)) return;
            if (element.closest(DragDrop.draggableContentCloneIdentifier)) return;
            if (element === DragDrop.dragPrevZone) return;
            if (DragDrop.isDropAllowed(element, DragDrop.dragCtype, '', DragDrop.dragGridType, DragDrop.dragSourceColumn)) {
                element.classList.add(DragDrop.validDropZoneClass);
            }
        });
    }

    static toggleAdjacentZones(withCtrl) {
        // Toggle the "adjacent before" zone (same-position — hidden without CTRL)
        if (DragDrop.dragPrevZone) {
            if (withCtrl && DragDrop.isDropAllowed(DragDrop.dragPrevZone, DragDrop.dragCtype, '', DragDrop.dragGridType, DragDrop.dragSourceColumn)) {
                DragDrop.dragPrevZone.classList.add(DragDrop.validDropZoneClass);
            } else {
                DragDrop.dragPrevZone.classList.remove(DragDrop.validDropZoneClass, DragDrop.dropPossibleHoverClass);
            }
        }
        document.querySelectorAll(`${DragDrop.draggableContentCloneIdentifier} ${DragDrop.dropZoneIdentifier}`).forEach(z => {
            if (withCtrl && DragDrop.isDropAllowed(z, DragDrop.dragCtype, '', DragDrop.dragGridType, DragDrop.dragSourceColumn)) {
                z.classList.add(DragDrop.validDropZoneClass);
            } else {
                z.classList.remove(DragDrop.validDropZoneClass, DragDrop.dropPossibleHoverClass);
            }
        });
    }

    static hideDropZones() {
        document.querySelectorAll(DragDrop.dropZoneIdentifier).forEach(element => {
            element.classList.remove(DragDrop.validDropZoneClass, DragDrop.dropPossibleHoverClass);
        });
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
        e.target.closest(DragDrop.columnIdentifier)?.classList.add('active');
        e.target.querySelector('.draggable-copy-message').remove();

        // Remove key listeners and clear drop zones
        if (DragDrop.onKeyChange) {
            document.removeEventListener('keydown', DragDrop.onKeyChange);
            document.removeEventListener('keyup', DragDrop.onKeyChange);
            DragDrop.onKeyChange = null;
        }
        DragDrop.hideDropZones();
        DragDrop.dragTarget = null;
        DragDrop.dragPrevZone = null;

        // Restore all add content buttons
        document.querySelectorAll(DragDrop.addContentIdentifier).forEach(btn => { btn.hidden = false; });

        // Remove clones
        document.querySelectorAll(DragDrop.draggableContentCloneIdentifier).forEach((element) => {
            element.remove();
        });
    }

    static onDrop(e) {
        const dropContainer = e.target;
        if (!dropContainer.classList.contains(DragDrop.validDropZoneClass)) return;
        const draggedElement = e.relatedTarget,
            contentElementUid = parseInt(draggedElement.dataset.uid, 10);
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
                language = parseInt((dropContainer.closest('[data-language-uid]')).dataset.languageUid, 10);
            }

            const container = parseInt(dropContainer?.closest('.t3-grid-element-container')?.closest(DragDrop.contentIdentifier).dataset.uid) || 0;

            let colPos = 0;
            if (container > 0 && gridColumn !== false && gridColumn !== '') {
                colPos = -1;
            } else if (targetPid !== 0) {
                colPos = newColumn;
            }

            const isCopyAction = (e.dragEvent.ctrlKey || dropContainer.classList.contains('t3js-paste-copy'));
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
        const moduleEl = document.querySelector('.module');
        const scrollTarget = a
            ? (moduleEl
                ? moduleEl.scrollTop + e.getBoundingClientRect().top - moduleEl.getBoundingClientRect().top - 20
                : e.getBoundingClientRect().top + window.scrollY - 20)
            : null;
        return DataHandler.process(r, s).then((r => {
            if (r.hasErrors) throw r.messages;
            e.parentElement.classList.contains(DragDrop.contentIdentifier.substring(1)) ? e.closest(DragDrop.contentIdentifier).after(t) : e.closest(DragDrop.dropZoneIdentifier).after(t);
            if (a) {
                sessionStorage.setItem('gridelements-drag-drop-scroll', Math.round(scrollTarget).toString());
                self.location.reload();
            }
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

    static isDropAllowed(zone, ctype, listType, gridType, sourceColumn) {
        const column = zone.closest('.t3js-page-column');
        if (!column) return true;
        if (column !== sourceColumn && column.classList.contains('t3-page-ce-disable-new-ce')) return false;
        return DragDrop.isTypeAllowed(column, ctype, listType, gridType);
    }

    static isTypeAllowed(column, ctype, listType, gridType) {
        const restriction = getColumnRestriction(column);
        if (!typeOk(restriction.allowedCtype, restriction.disallowedCtype, ctype)) return false;
        if (listType && !typeOk(restriction.allowedListType, restriction.disallowedListType, listType)) return false;
        if (gridType && !typeOk(restriction.allowedGridType, restriction.disallowedGridType, gridType)) return false;
        return true;
    }
}

DragDrop.contentIdentifier = ".t3js-page-ce", DragDrop.draggableContentIdentifier = ".t3js-page-ce-sortable", DragDrop.draggableContentHandleIdentifier = ".t3js-page-ce-draghandle", DragDrop.draggableContentCloneIdentifier = "[data-dragdrop-clone]", DragDrop.dropZoneIdentifier = ".t3js-page-ce-dropzone-available", DragDrop.columnIdentifier = ".t3js-page-column", DragDrop.validDropZoneClass = "active", DragDrop.dropPossibleHoverClass = "t3-page-ce-dropzone-possible", DragDrop.addContentIdentifier = ".t3js-page-new-ce";
DragDrop.dragTarget = null;
DragDrop.dragCtype = '';
DragDrop.dragGridType = '';
DragDrop.dragSourceColumn = null;
DragDrop.dragPrevZone = null;
DragDrop.onKeyChange = null;

const _dragDropScroll = sessionStorage.getItem('gridelements-drag-drop-scroll');
if (_dragDropScroll !== null) {
    sessionStorage.removeItem('gridelements-drag-drop-scroll');
    const _scrollTo = parseInt(_dragDropScroll, 10);
    let _scrollAttempts = 20;
    const _applyScroll = () => {
        document.querySelector('.module')?.scrollTo({ top: _scrollTo, behavior: 'instant' });
        if (--_scrollAttempts > 0) {
            requestAnimationFrame(_applyScroll);
        }
    };
    requestAnimationFrame(_applyScroll);
}

export default new DragDrop;

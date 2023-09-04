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
        (e.target.querySelector(DragDrop.dropZoneIdentifier)).hidden = true;

        document.querySelectorAll(DragDrop.dropZoneIdentifier).forEach((element) => {
            const addContentButton = element.parentElement.querySelector(DragDrop.addContentIdentifier);
            if (addContentButton !== null) {
                addContentButton.hidden = true;
                element.classList.add(DragDrop.validDropZoneClass);
            }
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
        e.target.closest(DragDrop.columnIdentifier).classList.add('active');
        (e.target.querySelector(DragDrop.dropZoneIdentifier)).hidden = false;
        e.target.querySelector('.draggable-copy-message').remove();

        document.querySelectorAll(DragDrop.dropZoneIdentifier + '.' + DragDrop.validDropZoneClass).forEach((element) => {
            const addContentButton = element.parentElement.querySelector(DragDrop.addContentIdentifier);
            if (addContentButton !== null) {
                addContentButton.hidden = false;
            }
            element.classList.remove(DragDrop.validDropZoneClass);
        });

        // Remove clones
        document.querySelectorAll(DragDrop.draggableContentCloneIdentifier).forEach((element) => {
            element.remove();
        });
    }

    static onDrop(e) {
        const dropContainer = e.target, draggedElement = e.relatedTarget,
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
export default new DragDrop;

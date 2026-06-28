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

// CMS 12 drag & drop: interact.js based, enhanced for gridelements.
// Replaces @typo3/backend/layout-module/drag-drop.js via JavaScriptModules.php import mapping.

import interact from "interactjs";
import DocumentService from "@typo3/core/document-service.js";
import DataHandler from "@typo3/backend/ajax-data-handler.js";
import Icons from "@typo3/backend/icons.js";
import RegularEvent from "@typo3/core/event/regular-event.js";
import BroadcastService from "@typo3/backend/broadcast-service.js";
import {BroadcastMessage} from "@typo3/backend/broadcast-message.js";

var Identifiers, Classes;
!function(e) {
    e.content = ".t3js-page-ce";
    e.draggable = ".t3js-page-ce-sortable";
    e.dragHandle = ".t3js-page-ce-draghandle";
    e.dragClone = "[data-dragdrop-clone]";
    e.dropZone = ".t3js-page-ce-dropzone-available";
    e.column = ".t3js-page-column";
    e.addContent = ".t3js-page-new-ce";
    e.gridContainer = ".t3-grid-element-container";
}(Identifiers || (Identifiers = {}));
!function(e) {
    e.validDropZoneClass = "active";
    e.dropPossibleHoverClass = "t3-page-ce-dropzone-possible";
    e.draggingClass = "draggable-dragging";
    e.copyMessageClass = "draggable-copy-message";
}(Classes || (Classes = {}));

class DragDrop {
    dragging = false;
    copyMode = false;
    draggedCType = '';
    draggedListType = '';
    draggedGridType = '';
    ownDropZone = null;
    prevDropZone = null;
    cloneDropZone = null;

    constructor() {
        DocumentService.ready().then(() => { this.initialize(); });
    }

    initialize() {
        // Scroll to last dropped element after page reload
        const dropUid = sessionStorage.getItem('gridelements-drop-uid');
        if (dropUid !== null) {
            sessionStorage.removeItem('gridelements-drop-uid');
            document.getElementById(`element-tt_content-${dropUid}`)?.scrollIntoView({ block: 'center' });
        }

        // Prevent native HTML5 drag from conflicting with interact.js on hybrid templates
        // (draggable="true" is present for CMS 13 compatibility but must not fire here)
        new RegularEvent("dragstart", (e) => { e.preventDefault(); }).delegateTo(document, Identifiers.draggable);
        new RegularEvent("dragstart", (e) => { e.preventDefault(); }).delegateTo(document, Identifiers.dragHandle);

        // Horizontal wheel scroll while an element is being dragged
        new RegularEvent("wheel", (e) => {
            const m = document.querySelector(".module");
            if (m) { m.scrollLeft += e.deltaX; m.scrollTop += e.deltaY; }
        }).delegateTo(document, "." + Classes.draggingClass);

        // interact.js: make content elements draggable
        interact(Identifiers.draggable)
            .draggable({
                allowFrom: Identifiers.dragHandle,
                onstart: this.onDragStart.bind(this),
                onmove:  this.onDragMove.bind(this),
                onend:   this.onDragEnd.bind(this)
            })
            .pointerEvents({ allowFrom: Identifiers.dragHandle })
            // Create a placeholder clone at the original position when drag starts
            .on("move", (e) => {
                const interaction = e.interaction;
                const target = e.currentTarget;
                if (interaction.pointerIsDown && !interaction.interacting() && "false" !== target.getAttribute("clone")) {
                    const clone = target.cloneNode(true);
                    clone.setAttribute("data-dragdrop-clone", "true");
                    target.parentNode.insertBefore(clone, target.nextSibling);
                    interaction.start({ name: "drag" }, e.interactable, target);
                }
            });

        // interact.js: make drop zones accept drops
        interact(Identifiers.dropZone)
            .dropzone({
                accept: Identifiers.draggable,
                ondrop: this.onDrop.bind(this),
                // Geometric check: pointer must be physically over the drop zone
                checker: (dragEvent, event, dropped, dropzone, dropElement) => {
                    const r = dropElement.getBoundingClientRect();
                    return event.pageX >= r.left && event.pageX <= r.left + r.width
                        && event.pageY >= r.top && event.pageY <= r.top + r.height;
                }
            })
            .on("dragenter", (e) => { e.target.classList.add(Classes.dropPossibleHoverClass); })
            .on("dragleave", (e) => { e.target.classList.remove(Classes.dropPossibleHoverClass); });

        // Track CTRL/ALT key state to toggle copy mode and drop zone visibility during drag
        const onModifierChange = (e) => {
            if (!this.dragging) return;
            const nowCopy = navigator.userAgent.includes("Mac") ? e.altKey : e.ctrlKey;
            if (nowCopy !== this.copyMode) {
                this.copyMode = nowCopy;
                this.hideDropZones();
                this.showDropZones();
            }
        };
        document.addEventListener("keydown", onModifierChange);
        document.addEventListener("keyup", onModifierChange);

        // Listen for broadcasts from other windows/tabs (e.g. element moved away)
        new RegularEvent("typo3:page-layout-drag-drop:elementChanged", this.onBroadcastElementChanged.bind(this)).bindTo(top.document);
    }

    onDragStart(e) {
        this.dragging = true;
        this.copyMode = false; // keydown handler updates this if CTRL/ALT is held

        const target = e.target; // .t3js-page-ce-sortable
        target.dataset.dragStartX = (e.client.x - e.rect.left).toString();
        target.dataset.dragStartY = (e.client.y - e.rect.top).toString();
        target.style.width = getComputedStyle(target).getPropertyValue("width");
        target.classList.add(Classes.draggingClass);

        const copyMsg = document.createElement("div");
        copyMsg.classList.add(Classes.copyMessageClass);
        copyMsg.textContent = TYPO3.lang["dragdrop.copy.message"];
        target.append(copyMsg);

        // Read element type from outer data-* attrs (set by Gridelements Record.html)
        // or fall back to inner .t3-ctype-identifier (set by core page module template)
        const ctypeEl = target.querySelector(".t3-ctype-identifier");
        this.draggedCType = target.dataset.ctype || ctypeEl?.dataset.ctype || '';
        this.draggedListType = target.dataset.list_type || ctypeEl?.dataset.list_type || '';
        this.draggedGridType = target.dataset.tx_gridelements_backend_layout || ctypeEl?.dataset.tx_gridelements_backend_layout || '';

        target.closest(Identifiers.column)?.classList.remove("active");

        // .draggable-dragging uses position:absolute, which is relative to the offset parent.
        // Inside a grid container the offset parent is not at the viewport origin, so
        // left/top (computed from viewport-relative e.client.*) would place the element far off.
        // Switching to position:fixed makes left/top relative to the viewport, matching the math,
        // and also escapes any ancestor overflow:hidden clipping.
        target.style.position = 'fixed';

        // Own drop zone: the zone at the bottom of the dragged element itself
        this.ownDropZone = target.querySelector(Identifiers.dropZone);

        // Clone drop zone: the clone is inserted immediately after target;
        // its zone represents the same original position
        const maybeClone = target.nextElementSibling;
        if (maybeClone?.hasAttribute("data-dragdrop-clone")) {
            this.cloneDropZone = maybeClone.querySelector(Identifiers.dropZone);
        }

        // Previous drop zone: bottom of the element above, or the column top zone
        const prevSibling = target.previousElementSibling;
        if (prevSibling !== null) {
            this.prevDropZone = prevSibling.querySelector(Identifiers.dropZone);
        } else {
            // First item in column — walk backwards from the items wrapper to find the top drop zone
            let node = target.parentElement?.previousElementSibling;
            while (node) {
                const dz = node.querySelector(Identifiers.dropZone);
                if (dz) { this.prevDropZone = dz; break; }
                node = node.previousElementSibling;
            }
        }

        this.showDropZones();
    }

    onDragMove(e) {
        const module = document.querySelector(".module");
        const docHeader = document.querySelector(".t3js-module-docheader");
        const target = e.target;
        target.style.left = `${e.client.x - parseInt(target.dataset.dragStartX, 10)}px`;
        target.style.top  = `${e.client.y - parseInt(target.dataset.dragStartY, 10)}px`;
        if (module) {
            if (e.delta.x < 0 && e.pageX - 20 < 0) module.scrollLeft -= 20;
            else if (e.delta.x > 0 && e.pageX + 20 > module.offsetWidth) module.scrollLeft += 20;
            const headerH = docHeader?.clientHeight ?? 0;
            if (e.delta.y < 0 && e.pageY - 20 - headerH < 0) module.scrollTop -= 20;
            else if (e.delta.y > 0 && e.pageY + 20 > module.offsetHeight) module.scrollTop += 20;
        }
    }

    onDragEnd(e) {
        this.dragging = false;
        this.copyMode = false;

        const target = e.target;
        target.dataset.dragStartX = "";
        target.dataset.dragStartY = "";
        target.classList.remove(Classes.draggingClass);
        target.style.position = '';
        target.style.width = "unset";
        target.style.left  = "unset";
        target.style.top   = "unset";
        target.querySelector("." + Classes.copyMessageClass)?.remove();
        target.closest(Identifiers.column)?.classList.add("active");

        this.draggedCType = '';
        this.draggedListType = '';
        this.draggedGridType = '';
        this.ownDropZone = null;
        this.prevDropZone = null;
        this.cloneDropZone = null;

        this.hideDropZones();
        document.querySelectorAll(Identifiers.dragClone).forEach(el => el.remove());
    }

    onDrop(e) {
        const dropZone = e.target;       // .t3js-page-ce-dropzone-available
        const dragged  = e.relatedTarget; // .t3js-page-ce-sortable

        dropZone.classList.remove(Classes.dropPossibleHoverClass);

        const colPos     = this.getColumnPositionForElement(dropZone);
        const gridColPos = this.getGridColumnPositionForElement(dropZone);
        const uid        = parseInt(dragged.dataset.uid, 10);

        if (typeof uid === "number" && uid > 0) {
            const cmd = {};
            const targetContentEl = dropZone.closest(Identifiers.content);
            const s = targetContentEl?.dataset.uid;

            let n = colPos;
            let gc = gridColPos;
            if (gc !== false && gc !== '') {
                n = -1;
            } else {
                gc = 0;
            }

            // Positive target = insert as first on page; negative = insert after element |s|
            let i;
            i = s === undefined
                ? parseInt(dropZone.closest("[data-page]").dataset.page, 10)
                : 0 - parseInt(s, 10);

            let langUid = parseInt(dragged.dataset.languageUid, 10);
            if (langUid !== -1) {
                langUid = parseInt(dropZone.closest("[data-language-uid]")?.dataset.languageUid ?? langUid, 10);
            }

            // Gridelements container uid (0 if dropping to a regular page column)
            const v = parseInt(dropZone.closest(Identifiers.gridContainer)?.closest(Identifiers.content)?.dataset.uid) || 0;

            let l = 0;
            if (v > 0 && gc !== false && gc !== '') {
                l = -1; // colPos = -1 signals a gridelements child
            } else if (i !== 0) {
                l = n;
            }

            const isCopy = this.copyMode || dropZone.classList.contains("t3js-paste-copy");
            const action = isCopy ? "copy" : "move";

            cmd.cmd = {
                tt_content: {
                    [uid]: {
                        [action]: {
                            action: "paste",
                            target: i,
                            update: {
                                colPos: l,
                                sys_language_uid: langUid,
                                tx_gridelements_container: v,
                                tx_gridelements_columns: gc
                            }
                        }
                    }
                }
            };

            this.ajaxAction(dropZone, dragged, cmd, isCopy).then(() => {
                const langEl = document.querySelector(`.t3-page-column-lang-name[data-language-uid="${langUid}"]`);
                if (langEl === null) return;
                Icons.getIcon(langEl.dataset.flagIdentifier, Icons.sizes.small).then(icon => {
                    const flagEl = dragged.querySelector(".t3js-flag");
                    if (flagEl) { flagEl.title = langEl.dataset.languageTitle; flagEl.innerHTML = icon; }
                });
            });
        }
    }

    ajaxAction(dropZone, dragged, cmd, isCopy) {
        const table  = Object.keys(cmd.cmd).shift();
        const uid    = parseInt(Object.keys(cmd.cmd[table]).shift(), 10);
        const stateObj = { component: "dragdrop", action: isCopy ? "copy" : "move", table, uid };
        const gridContainer = document.querySelector(".t3-grid-container");

        return DataHandler.process(cmd, stateObj).then(result => {
            if (result.hasErrors) throw result.messages;

            // Move the dragged element to its new DOM position
            dropZone.parentElement.classList.contains(Identifiers.content.substring(1))
                ? dropZone.closest(Identifiers.content).after(dragged)
                : dropZone.after(dragged);

            this.broadcast("elementChanged", {
                pid: this.getCurrentPageId(),
                uid,
                targetPid: this.getCurrentPageId(),
                action: isCopy ? "copy" : "move"
            });

            if (isCopy || "1" === gridContainer?.dataset.defaultLanguageBinding) {
                sessionStorage.setItem("gridelements-drop-uid", String(uid));
                self.location.reload();
            }
        });
    }

    onBroadcastElementChanged(e) {
        const p = e.detail.payload;
        if (p.pid === this.getCurrentPageId() && p.targetPid !== p.pid && p.action === "move") {
            document.querySelector(`${Identifiers.content}[data-uid="${p.uid}"]`)?.remove();
        }
    }

    // Show eligible drop zones; hide own-position and previous-position zones unless in copy mode
    showDropZones() {
        // Blanket-hide all add-content buttons for the duration of the drag —
        // even zones whose drop zone is not shown must not show the add button.
        document.querySelectorAll(Identifiers.addContent).forEach(btn => { btn.hidden = true; });
        document.querySelectorAll(Identifiers.dropZone).forEach(dz => {
            // Skip drop zones nested inside the clone, EXCEPT cloneDropZone itself —
            // cloneDropZone represents the position immediately after the original and
            // should appear when CTRL/ALT is held so the user can copy-to-same-position.
            if (dz.closest(Identifiers.dragClone) && dz !== this.cloneDropZone) return;
            if (dz === this.ownDropZone) return; // never show — inside the dragged element
            if (!this.copyMode && (dz === this.prevDropZone || dz === this.cloneDropZone)) return;
            if (!this.isAllowedDropZone(dz)) return;
            dz.classList.add(Classes.validDropZoneClass);
        });
    }

    hideDropZones() {
        // Restore all add-content buttons and clear all active drop zone highlights.
        document.querySelectorAll(Identifiers.addContent).forEach(btn => { btn.hidden = false; });
        document.querySelectorAll(Identifiers.dropZone).forEach(dz => {
            dz.classList.remove(Classes.validDropZoneClass);
        });
    }

    isAllowedDropZone(dropZone) {
        const column = dropZone.closest(Identifiers.column);
        if (!column) return true;

        const ctype          = this.draggedCType;
        const allowedCtype   = column.getAttribute("data-allowed-ctype") || '';
        const disallowedCtype = column.getAttribute("data-disallowed-ctype") || '';

        if (disallowedCtype === '*') return false;
        if (disallowedCtype && disallowedCtype.split(',').includes(ctype)) return false;
        if (allowedCtype && allowedCtype !== '*' && !allowedCtype.split(',').includes(ctype)) return false;

        if (ctype === 'list') {
            const listType          = this.draggedListType;
            const allowedListType   = column.getAttribute("data-allowed-list_type") || '';
            const disallowedListType = column.getAttribute("data-disallowed-list_type") || '';
            if (disallowedListType === '*') return false;
            if (disallowedListType && disallowedListType.split(',').includes(listType)) return false;
            if (allowedListType && allowedListType !== '*' && !allowedListType.split(',').includes(listType)) return false;
        }

        if (ctype === 'gridelements_pi1') {
            const gridType          = this.draggedGridType;
            const allowedGridType   = column.getAttribute("data-allowed-tx_gridelements_backend_layout") || '';
            const disallowedGridType = column.getAttribute("data-disallowed-tx_gridelements_backend_layout") || '';
            if (disallowedGridType === '*') return false;
            if (disallowedGridType && disallowedGridType.split(',').includes(gridType)) return false;
            if (allowedGridType && allowedGridType !== '*' && !allowedGridType.split(',').includes(gridType)) return false;
        }

        return true;
    }

    getColumnPositionForElement(e) {
        const t = e.closest("[data-colpos]");
        return null !== t && void 0 !== t.dataset.colpos && parseInt(t.dataset.colpos, 10);
    }

    getGridColumnPositionForElement(e) {
        const gc = e.closest(Identifiers.gridContainer);
        const t  = e.closest("[data-colpos]");
        return gc !== null && null !== t && void 0 !== t.dataset.colpos && parseInt(t.dataset.colpos, 10);
    }

    getCurrentPageId() {
        return parseInt(document.querySelector("[data-page]")?.dataset.page ?? "0", 10);
    }

    broadcast(event, data) {
        BroadcastService.post(new BroadcastMessage("page-layout-drag-drop", event, data || {}));
    }
}

export default new DragDrop;

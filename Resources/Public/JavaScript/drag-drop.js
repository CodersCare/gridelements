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
import DocumentService from "@typo3/core/document-service.js";
import DataHandler from "@typo3/backend/ajax-data-handler.js";
import Icons from "@typo3/backend/icons.js";
import RegularEvent from "@typo3/core/event/regular-event.js";
import {DataTransferTypes} from "@typo3/backend/enum/data-transfer-types.js";
import BroadcastService from "@typo3/backend/broadcast-service.js";
import {BroadcastMessage} from "@typo3/backend/broadcast-message.js";
import DragDropUtility from "@typo3/backend/utility/drag-drop-utility.js";

var Identifiers, Classes;
!function (e) {
    e.content = ".t3js-page-ce", e.draggableContentHandle = '.t3js-page-ce-header[draggable="true"]', e.dropZone = ".t3js-page-ce-dropzone-available", e.column = ".t3js-page-column", e.addContent = ".t3js-page-new-ce"
}(Identifiers || (Identifiers = {})), function (e) {
    e.validDropZoneClass = "active", e.dropPossibleHoverClass = "t3-page-ce-dropzone-possible"
}(Classes || (Classes = {}));

class DragDrop {
    draggedCType = '';
    draggedListType = '';
    draggedGridType = '';
    ownDropZone = null;
    prevDropZone = null;

    constructor() {
        DocumentService.ready().then((() => {
            this.initialize()
        }))
    }

    initialize() {
        new RegularEvent("mousedown", ((e, t) => {
            const a = e.target.closest("a,img");
            null === a || t.contains(a)
        })).delegateTo(document, Identifiers.draggableContentHandle), new RegularEvent("dragstart", this.onDragStart.bind(this)).delegateTo(document, Identifiers.draggableContentHandle), new RegularEvent("dragenter", this.onDragEnter.bind(this)).delegateTo(document, Identifiers.draggableContentHandle), new RegularEvent("dragend", this.onDragEnd.bind(this)).delegateTo(document, Identifiers.draggableContentHandle), new RegularEvent("dragenter", ((e, t) => {
            t.classList.add(Classes.dropPossibleHoverClass), DragDropUtility.updateEventAndTooltipToReflectCopyMoveIntention(e)
        })).delegateTo(document, Identifiers.dropZone), new RegularEvent("dragover", (e => {
            e.preventDefault(), DragDropUtility.updateEventAndTooltipToReflectCopyMoveIntention(e)
        })).delegateTo(document, Identifiers.dropZone), new RegularEvent("dragleave", ((e, t) => {
            e.preventDefault(), t.classList.remove(Classes.dropPossibleHoverClass)
        })).delegateTo(document, Identifiers.dropZone), new RegularEvent("drop", this.onDrop.bind(this), {
            capture: !0,
            passive: !0
        }).delegateTo(document, Identifiers.dropZone), new RegularEvent("typo3:page-layout-drag-drop:elementChanged", this.onBroadcastElementChanged.bind(this)).bindTo(top.document)
    }

    onDragEnter(e) {
        e.preventDefault(), DragDropUtility.updateEventAndTooltipToReflectCopyMoveIntention(e), this.showDropZones()
    }

    onDragStart(e, t) {
        const a = t.closest(Identifiers.content);
        this.draggedCType = a.dataset.ctype || '';
        this.draggedListType = a.dataset.list_type || '';
        this.draggedGridType = a.dataset.tx_gridelements_backend_layout || '';
        e.dataTransfer.setData(DataTransferTypes.content, JSON.stringify({
            pid: this.getCurrentPageId(),
            uid: parseInt(a.dataset.uid, 10),
            language: parseInt(a.dataset.languageUid, 10),
            content: a.outerHTML,
            moveElementUrl: a.dataset.moveElementUrl
        }));
        const n = this.getDragTooltipMetadataFromContentElement(a);
        e.dataTransfer.setData(DataTransferTypes.dragTooltip, JSON.stringify(n)), e.dataTransfer.effectAllowed = "copyMove", DragDropUtility.updateEventAndTooltipToReflectCopyMoveIntention(e);
        this.ownDropZone = a.querySelector(Identifiers.dropZone);
        if (this.ownDropZone) this.ownDropZone.hidden = true;
        const prevSibling = a.previousElementSibling;
        if (prevSibling !== null) {
            this.prevDropZone = prevSibling.querySelector(Identifiers.dropZone);
        } else {
            let node = a.parentElement?.previousElementSibling;
            while (node) {
                const dz = node.querySelector(Identifiers.dropZone);
                if (dz) { this.prevDropZone = dz; break; }
                node = node.previousElementSibling;
            }
        }
        if (this.prevDropZone) this.prevDropZone.hidden = true;
    }

    onDragEnd() {
        this.draggedCType = '';
        this.draggedListType = '';
        this.draggedGridType = '';
        this.ownDropZone = null;
        this.prevDropZone = null;
        this.hideDropZones()
    }

    onDrop(e, t) {
        let a;
        if (t.classList.remove(Classes.dropPossibleHoverClass), !e.dataTransfer.types.includes(DataTransferTypes.content)) return;
        let n = this.getColumnPositionForElement(t),
            u = this.getGridColumnPositionForElement(t);
        const o = JSON.parse(e.dataTransfer.getData(DataTransferTypes.content));

        if (a = document.querySelector(`${Identifiers.content}[data-uid="${o.uid}"]`), a || (a = document.createRange().createContextualFragment(o.content).firstElementChild), "number" == typeof o.uid && o.uid > 0) {
            const r = {}, s = t.closest(Identifiers.content).dataset.uid;
            if (u !== false && u !== '') {
                n = -1;
            } else {
                u = 0;
            }
            let i;
            i = void 0 === s ? parseInt(t.closest("[data-page]").dataset.page, 10) : 0 - parseInt(s, 10);
            let d = o.language;
            -1 !== d && (d = parseInt(t.closest("[data-language-uid]").dataset.languageUid, 10));
            const v = parseInt(t?.closest('.t3-grid-element-container')?.closest(Identifiers.content).dataset.uid) || 0;
            let l = 0;
            if (v > 0 && u !== false && u !== '') {
                l = -1;
            } else if (i !== 0) {
                l = n;
            }
            const c = DragDropUtility.isCopyModifierFromEvent(e) || t.classList.contains("t3js-paste-copy"),
                p = c ? "copy" : "move";
            r.cmd = {
                tt_content: {
                    [o.uid]: {
                        [p]: {
                            action: "paste",
                            target: i,
                            update: {colPos: l, sys_language_uid: d, tx_gridelements_container: v, tx_gridelements_columns: u}
                        }
                    }
                }
            }, this.ajaxAction(r, c).then((() => {
                t.parentElement.classList.contains(Identifiers.content.substring(1)) ? t.closest(Identifiers.content).after(a) : t.closest(Identifiers.dropZone).after(a), this.broadcast("elementChanged", {
                    pid: o.pid,
                    uid: o.uid,
                    targetPid: this.getCurrentPageId(),
                    action: c ? "copy" : "move"
                });
                const e = document.querySelector(`.t3-page-column-lang-name[data-language-uid="${d}"]`);
                if (null === e) return;
                const n = e.dataset.flagIdentifier, r = e.dataset.languageTitle;
                Icons.getIcon(n, Icons.sizes.small).then((e => {
                    const t = a.querySelector(".t3js-flag");
                    t.title = r, t.innerHTML = e
                }))
            }))
        }
    }

    onBroadcastElementChanged(e) {
        e.detail.payload.pid === this.getCurrentPageId() && e.detail.payload.targetPid !== e.detail.payload.pid && "move" === e.detail.payload.action && document.querySelector(`${Identifiers.content}[data-uid="${e.detail.payload.uid}"]`).remove()
    }

    ajaxAction(e, t) {
        const a = Object.keys(e.cmd).shift(), n = parseInt(Object.keys(e.cmd[a]).shift(), 10),
            o = {component: "dragdrop", action: t ? "copy" : "move", table: a, uid: n},
            r = document.querySelector(".t3-grid-container");
        return DataHandler.process(e, o).then((e => {
            if (e.hasErrors) throw e.messages;
            (t || "1" === r?.dataset.defaultLanguageBinding) && self.location.reload()
        }))
    }

    getColumnPositionForElement(e) {
        const t = e.closest("[data-colpos]");
        return null !== t && void 0 !== t.dataset.colpos && parseInt(t.dataset.colpos, 10)
    }

    getGridColumnPositionForElement(e) {
        const gc =  e.closest(".t3-grid-element-container");
        const t = e.closest("[data-colpos]");
        return gc !== null && null !== t && void 0 !== t.dataset.colpos && parseInt(t.dataset.colpos, 10)
    }

    getDragTooltipMetadataFromContentElement(e) {
        let t, a;
        const n = [], o = e.querySelector(".t3-page-ce-header-title").innerText,
            r = e.querySelector(".element-preview");
        r && (t = r.innerText, t.length > 80 && (t = t.substring(0, 80) + "..."));
        const s = e.querySelector(".t3js-icon");
        s && (a = s.dataset.identifier);
        const i = e.querySelectorAll(".preview-thumbnails-element-image img");
        return i.length > 0 && i.forEach((e => {
            n.push({src: e.src, height: e.height, width: e.width})
        })), {
            statusIconIdentifier: "actions-move",
            tooltipIconIdentifier: a,
            tooltipLabel: o,
            tooltipDescription: t,
            thumbnails: n
        }
    }

    getCurrentPageId() {
        return parseInt(document.querySelector("[data-page]").dataset.page, 10)
    }

    broadcast(e, t) {
        BroadcastService.post(new BroadcastMessage("page-layout-drag-drop", e, t || {}))
    }

    showDropZones() {
        document.querySelectorAll(Identifiers.dropZone).forEach((e => {
            if (e === this.ownDropZone || e === this.prevDropZone) return;
            if (!this.isAllowedDropZone(e)) return;
            e.hidden = !1;
            const t = e.parentElement.querySelector(Identifiers.addContent);
            null !== t && (t.hidden = !0, e.classList.add(Classes.validDropZoneClass))
        }))
    }

    isAllowedDropZone(dropZone) {
        const column = dropZone.closest('.t3js-page-column');
        if (!column) return true;
        const ctype = this.draggedCType || '';
        const allowedCtype = column.getAttribute('data-allowed-ctype') || '';
        const disallowedCtype = column.getAttribute('data-disallowed-ctype') || '';
        if (disallowedCtype === '*') return false;
        if (disallowedCtype && disallowedCtype.split(',').includes(ctype)) return false;
        if (allowedCtype && allowedCtype !== '*' && !allowedCtype.split(',').includes(ctype)) return false;
        if (ctype === 'list') {
            const listType = this.draggedListType || '';
            const allowedListType = column.getAttribute('data-allowed-list_type') || '';
            const disallowedListType = column.getAttribute('data-disallowed-list_type') || '';
            if (disallowedListType === '*') return false;
            if (disallowedListType && disallowedListType.split(',').includes(listType)) return false;
            if (allowedListType && allowedListType !== '*' && !allowedListType.split(',').includes(listType)) return false;
        }
        if (ctype === 'gridelements_pi1') {
            const gridType = this.draggedGridType || '';
            const allowedGridType = column.getAttribute('data-allowed-tx_gridelements_backend_layout') || '';
            const disallowedGridType = column.getAttribute('data-disallowed-tx_gridelements_backend_layout') || '';
            if (disallowedGridType === '*') return false;
            if (disallowedGridType && disallowedGridType.split(',').includes(gridType)) return false;
            if (allowedGridType && allowedGridType !== '*' && !allowedGridType.split(',').includes(gridType)) return false;
        }
        return true;
    }

    hideDropZones() {
        document.querySelectorAll(Identifiers.dropZone).forEach((e => {
            e.hidden = !0;
            const t = e.parentElement.querySelector(Identifiers.addContent);
            null !== t && (t.hidden = !1), e.classList.remove(Classes.validDropZoneClass)
        }))
    }

}

export default new DragDrop;

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
import $ from "jquery";
import DataHandler from "@typo3/backend/ajax-data-handler.js";
import {default as Modal} from "@typo3/backend/modal.js";
import Severity from "@typo3/backend/severity.js";
import "@typo3/backend/element/icon-element.js";
import {SeverityEnum} from "@typo3/backend/enum/severity.js";

class Paste {
    constructor(t) {
        this.itemOnClipboardUid = 0, this.itemOnClipboardTitle = "", this.copyMode = "", this.elementIdentifier = ".t3js-page-ce", this.pasteAfterLinkTemplate = "", this.pasteIntoLinkTemplate = "", this.itemOnClipboardUid = t.itemOnClipboardUid, this.itemOnClipboardTitle = t.itemOnClipboardTitle, this.copyMode = t.copyMode, DocumentService.ready().then((() => {
            $(".t3js-page-columns").length && (this.generateButtonTemplates(), this.activatePasteIcons(), this.initializeEvents())
        }))
    }

    static determineColumn(t) {
        const e = t.closest("[data-colpos]");
        return e.length && "undefined" !== e.data("colpos") ? e.data("colpos") : 0
    }
    static determineGridColumn(t) {
        const gc =  t.closest(".t3-grid-element-container");
        const e = t.closest("[data-colpos]");
        return gc !== null && null !== e && void 0 !== e.data("colpos") ? parseInt(e.data("colpos"), 10) : 0;
    }

    static determineGridContainer(t) {
        const e = t.closest("[data-container]");
        return e.length && "undefined" !== e.data("container") ? e.data("container") : 0
    }

    initializeEvents() {
        $(document).on('click', '.t3js-paste', (evt) => {
            evt.preventDefault();
            this.activatePasteModal($(evt.currentTarget));
        });
    }

    generateButtonTemplates() {
        if (!this.itemOnClipboardUid) {
            return;
        }
        this.pasteAfterLinkTemplate = '<button'
            + ' type="button"'
            + ' class="t3js-paste t3js-paste' + (this.copyMode ? '-' + this.copyMode : '') + ' t3js-paste-after btn btn-default btn-sm"'
            + ' title="' + TYPO3.lang?.pasteAfterRecord + '">'
            + '<typo3-backend-icon identifier="actions-document-paste-into" size="small"></typo3-backend-icon>'
            + '</button>';
        this.pasteIntoLinkTemplate = '<button'
            + ' type="button"'
            + ' class="t3js-paste t3js-paste' + (this.copyMode ? '-' + this.copyMode : '') + ' t3js-paste-into btn btn-default btn-sm"'
            + ' title="' + TYPO3.lang?.pasteIntoColumn + '">'
            + '<typo3-backend-icon identifier="actions-document-paste-into" size="small"></typo3-backend-icon>'
            + '</button>';
    }

    activatePasteIcons() {
        this.pasteAfterLinkTemplate && this.pasteIntoLinkTemplate && document.querySelectorAll(".t3js-page-new-ce").forEach((t => {
            const e = t.parentElement.dataset.page ? this.pasteIntoLinkTemplate : this.pasteAfterLinkTemplate;
            t.append(document.createRange().createContextualFragment(e))
        }))
    }

    activatePasteModal($element) {
        const title = (TYPO3.lang['paste.modal.title.paste'] || 'Paste record') + ': "' + this.itemOnClipboardTitle + '"';
        const content = TYPO3.lang['paste.modal.paste'] || 'Do you want to paste the record to this position?';

        let buttons = [];
        buttons = [
            {
                text: TYPO3.lang['paste.modal.button.cancel'] || 'Cancel',
                active: true,
                btnClass: 'btn-default',
                trigger: (e, modal) => modal.hideModal(),
            },
            {
                text: TYPO3.lang['paste.modal.button.paste'] || 'Paste',
                btnClass: 'btn-' + Severity.getCssClass(SeverityEnum.warning),
                trigger: (e, modal) => {
                    modal.hideModal();
                    this.execute($element);
                },
            },
            {
                text: TYPO3.lang['paste.modal.button.paste_reference'] || 'Paste Reference',
                btnClass: 'btn-' + Severity.getCssClass(SeverityEnum.warning),
                trigger: (e, modal) => {
                    modal.hideModal();
                    this.execute($element, true);
                },
            },
        ];

        Modal.show(title, content, SeverityEnum.warning, buttons);
    }

    execute($element, pasteReference= false) {
        let colPos = Paste.determineColumn($element);
        const gridContainer = Paste.determineGridContainer($element);
        const gridColPos = Paste.determineGridColumn($element);
        if(gridContainer > 0) {
            colPos = -1;
        }
        const closestElement = $element.closest(this.elementIdentifier);
        console.log(colPos, gridContainer, gridColPos);

        const targetFound = closestElement.data('uid');
        let targetPid;
        if (typeof targetFound === 'undefined') {
            targetPid = parseInt(closestElement.data('page'), 10);
        } else {
            targetPid = 0 - parseInt(targetFound, 10);
        }
        const language = parseInt($element.closest('[data-language-uid]').data('language-uid'), 10);
        const parameters = {
            CB: {
                paste: 'tt_content|' + targetPid,
                pad: 'normal',
                update: {
                    colPos: colPos,
                    sys_language_uid: language,
                    tx_gridelements_container: gridContainer,
                    tx_gridelements_columns: gridColPos
                },
            },
        };

        if (pasteReference) {
            parameters['reference'] = 1;
        }

        DataHandler.process(parameters).then((result) => {
            if (!result.hasErrors) {
                window.location.reload();
            }
        });
    }
}

export default Paste;

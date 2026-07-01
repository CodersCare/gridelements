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

    /**
     * Returns true when value is permitted by the given allowed/disallowed attribute strings.
     * An absent attribute (null/undefined) means no restriction applies.
     */
    isTypeAllowed(allowedAttr, disallowedAttr, value) {
        if (!value) {
            return true;
        }
        if (allowedAttr != null) {
            const a = allowedAttr.toString().split(',');
            if (!a.includes(value) && !a.includes('*')) {
                return false;
            }
        }
        if (disallowedAttr != null) {
            const d = disallowedAttr.toString().split(',');
            if (d.includes(value) || d.includes('*')) {
                return false;
            }
        }
        return true;
    }

    /**
     * The plain page column template exposes the raw, unmerged backend layout "allowed.CType"
     * definition. It does not carry over the auto-permission gridelements grants for its own
     * CType whenever a column restricts allowed/disallowed grid layouts (mirrors the merge done
     * in GridElementsHelper::mergeAllowedDisallowedSettings() / GridelementsGridColumn.php, which
     * columns nested inside a grid container already benefit from). Without this, a column that
     * only configures "allowed.tx_gridelements_backend_layout" (the common case, since admins
     * aren't required to also list "gridelements_pi1" in "allowed.CType") would never offer a
     * real "Paste" for such containers - only "Paste Reference", since the CType check alone
     * already rejects it before the grid type check is ever consulted.
     */
    static resolveEffectiveAllowedCtype(allowedCtype, allowedListType, allowedGridType) {
        if (!allowedCtype) {
            return allowedCtype;
        }
        const list = allowedCtype.toString().split(',');
        if (list.includes('*')) {
            return allowedCtype;
        }
        if (allowedListType && !list.includes('list')) {
            list.push('list');
        }
        if (allowedGridType && !list.includes('gridelements_pi1')) {
            list.push('gridelements_pi1');
        }
        return list.join(',');
    }

    getPasteState(gridCell) {
        const allowedCtype = gridCell ? gridCell.getAttribute('data-allowed-ctype') : null;
        const disallowedCtype = gridCell ? gridCell.getAttribute('data-disallowed-ctype') : null;
        const allowedListType = gridCell ? gridCell.getAttribute('data-allowed-list_type') : null;
        const disallowedListType = gridCell ? gridCell.getAttribute('data-disallowed-list_type') : null;
        const allowedGridType = gridCell ? gridCell.getAttribute('data-allowed-tx_gridelements_backend_layout') : null;
        const disallowedGridType = gridCell ? gridCell.getAttribute('data-disallowed-tx_gridelements_backend_layout') : null;

        const effectiveAllowedCtype = Paste.resolveEffectiveAllowedCtype(allowedCtype, allowedListType, allowedGridType);

        const clipBoardCType = TYPO3.settings?.gridelements?.clipBoardElementCType || '';
        const clipBoardListType = TYPO3.settings?.gridelements?.clipBoardElementListType || '';
        const clipBoardGridType = TYPO3.settings?.gridelements?.clipBoardElementTxGridelementsBackendLayout || '';
        const pasteReferenceAllowed = TYPO3.settings?.gridelements?.pasteReferenceAllowed !== false;

        const canPaste = this.isTypeAllowed(effectiveAllowedCtype, disallowedCtype, clipBoardCType)
            && this.isTypeAllowed(allowedListType, disallowedListType, clipBoardListType)
            && this.isTypeAllowed(allowedGridType, disallowedGridType, clipBoardGridType);
        const canPasteReference = pasteReferenceAllowed
            && this.isTypeAllowed(effectiveAllowedCtype, disallowedCtype, 'shortcut');

        return {canPaste, canPasteReference};
    }

    activatePasteIcons() {
        if (!this.pasteAfterLinkTemplate || !this.pasteIntoLinkTemplate) {
            return;
        }

        document.querySelectorAll(".t3js-page-new-ce").forEach((el) => {
            const gridCell = el.closest('.t3-grid-cell') || el.closest('td');
            const {canPaste, canPasteReference} = this.getPasteState(gridCell);

            if (!canPaste && !canPasteReference) {
                return;
            }

            const template = el.parentElement.dataset.page ? this.pasteIntoLinkTemplate : this.pasteAfterLinkTemplate;
            el.append(document.createRange().createContextualFragment(template));
        });
    }

    activatePasteModal($element) {
        const title = (TYPO3.lang['paste.modal.title.paste'] || 'Paste record') + ': "' + this.itemOnClipboardTitle + '"';
        const content = TYPO3.lang['paste.modal.paste'] || 'Do you want to paste the record to this position?';

        const gridCell = $element[0].closest('.t3-grid-cell') || $element[0].closest('td');
        const {canPaste, canPasteReference} = this.getPasteState(gridCell);

        let buttons = [];
        buttons.push({
            text: TYPO3.lang['paste.modal.button.cancel'] || 'Cancel',
            active: true,
            btnClass: 'btn-default',
            trigger: (e, modal) => modal.hideModal(),
        });

        if (canPaste) {
            buttons.push({
                text: TYPO3.lang['paste.modal.button.paste'] || 'Paste',
                btnClass: 'btn-' + Severity.getCssClass(SeverityEnum.warning),
                trigger: (e, modal) => {
                    modal.hideModal();
                    this.execute($element);
                },
            });
        }

        if (canPasteReference) {
            buttons.push({
                text: TYPO3.lang['paste.modal.button.paste_reference'] || 'Paste Reference',
                btnClass: 'btn-' + Severity.getCssClass(SeverityEnum.warning),
                trigger: (e, modal) => {
                    modal.hideModal();
                    this.execute($element, true);
                },
            });
        }

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

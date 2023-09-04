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

import{SeverityEnum}from "@typo3/backend/enum/severity.js";
import Modal from "@typo3/backend/modal.js";
import Viewport from "@typo3/backend/viewport.js";


/**
 * @exports @gridelementsteam/gridelements/context-menu-actions
 */
class ContextMenuActions {
    /**
     * Paste db record after another
     *
     * @param {string} table any db table except sys_file
     * @param {number} uid uid of the record after which record from the clipboard will be pasted
     * @param {DOMStringMap} dataset The data attributes of the invoked menu item
     */
    static pasteReference(table, uid, dataset) {
        ContextMenuActions.pasteInto(table, -uid, dataset);
    }

    /**
     * @returns {string}
     */
     static getReturnUrl() {
        return encodeURIComponent(top.list_frame.document.location.pathname + top.list_frame.document.location.search);
    }

    /**
     * Paste page into another page
     *
     * @param {string} table any db table except sys_file
     * @param {number} uid uid of the record after which record from the clipboard will be pasted
     * @param {DOMStringMap} dataset The data attributes of the invoked menu item
     */
    static pasteInto(table, uid, dataset) {
        console.log(dataset);
        const performPaste = () => {
            const url = dataset.actionUrl + '&redirect=' + ContextMenuActions.getReturnUrl();

            Viewport.ContentContainer.setUrl(
                url,
            );
        };
        if (!dataset.title) {
            performPaste();
            return;
        }
        const modal = Modal.confirm(
            dataset.title,
            dataset.message,
            SeverityEnum.warning, [
                {
                    text: dataset.buttonCloseText || TYPO3.lang['button.cancel'] || 'Cancel',
                    active: true,
                    btnClass: 'btn-default',
                    name: 'cancel',
                },
                {
                    text: dataset.buttonOkText || TYPO3.lang['button.ok'] || 'OK',
                    btnClass: 'btn-warning',
                    name: 'ok',
                },
            ]);

        modal.addEventListener('button.clicked', (e) => {
            if ((e.target).getAttribute('name') === 'ok') {
                performPaste();
            }
            modal.hideModal();
        });
    }
}

export default ContextMenuActions;

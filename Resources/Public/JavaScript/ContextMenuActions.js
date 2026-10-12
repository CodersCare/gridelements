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

/**
 * Module: TYPO3/CMS/Gridelements/ContextMenuActions
 * TYPO3 11 (RequireJS) counterpart of context-menu-actions.js
 */
define(['jquery', 'TYPO3/CMS/Backend/Enum/Severity', 'TYPO3/CMS/Backend/Modal', 'TYPO3/CMS/Backend/Viewport'], function ($, Severity, Modal, Viewport) {
    'use strict';

    var ContextMenuActions = {};

    ContextMenuActions.getReturnUrl = function () {
        return encodeURIComponent(top.list_frame.document.location.pathname + top.list_frame.document.location.search);
    };

    /**
     * Navigates to the action URL prepared by ItemProvider; "this" is the menu item
     *
     * @param {string} table
     * @param {number} uid
     */
    ContextMenuActions.pasteInto = function (table, uid) {
        var $anchorElement = $(this);
        var performPaste = function () {
            Viewport.ContentContainer.setUrl($anchorElement.data('action-url') + '&redirect=' + ContextMenuActions.getReturnUrl());
        };
        if (!$anchorElement.data('title')) {
            performPaste();
            return;
        }
        Modal.confirm(
            $anchorElement.data('title'),
            $anchorElement.data('message'),
            Severity.SeverityEnum.warning, [
                {
                    text: $anchorElement.data('button-close-text') || TYPO3.lang['button.cancel'] || 'Cancel',
                    active: true,
                    btnClass: 'btn-default',
                    name: 'cancel'
                },
                {
                    text: $anchorElement.data('button-ok-text') || TYPO3.lang['button.ok'] || 'OK',
                    btnClass: 'btn-warning',
                    name: 'ok'
                }
            ]
        ).on('button.clicked', function (e) {
            if (e.target.getAttribute('name') === 'ok') {
                performPaste();
            }
            Modal.dismiss();
        });
    };

    ContextMenuActions.pasteAfter = function (table, uid) {
        ContextMenuActions.pasteInto.bind($(this))(table, -uid);
    };

    ContextMenuActions.pasteReference = function (table, uid) {
        ContextMenuActions.pasteInto.bind($(this))(table, -uid);
    };

    return ContextMenuActions;
});

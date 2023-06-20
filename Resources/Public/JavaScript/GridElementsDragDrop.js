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
 * this JS code does the drag+drop logic for the Layout module (Web => Page)
 * based on jQuery UI
 */
define(['jquery', 'jquery-ui/droppable', 'TYPO3/CMS/Backend/LayoutModule/DragDrop', 'TYPO3/CMS/Backend/LayoutModule/Paste'], function ($, Droppable, DragDrop, Paste) {
    'use strict';

    /**
     * @exports TYPO3/CMS/Gridelements/DragDrop
     */
    DragDrop.default.draggableIdentifier = '.t3js-page-ce:has(.t3-page-ce-header-draggable)';
    DragDrop.default.columnHolderIdentifier = '.t3-grid-container';
    DragDrop.default.gridContainerIdentifier = '.t3-grid-element-container';
    DragDrop.default.newContentElementWizardIdentifier = '#new-element-drag-in-wizard';
    DragDrop.default.cTypeIdentifier = '.t3-ctype-identifier';
    DragDrop.default.contentWrapperIdentifier = '.t3-page-ce-wrapper';
    DragDrop.default.disabledNewContentIdentifier = '.t3-page-ce-disable-new-ce';
    DragDrop.default.newContentElementOnclick = '';
    DragDrop.default.newContentElementDefaultValues = {};
    DragDrop.default.drag = {};
    DragDrop.default.types = {};
    DragDrop.default.ownDropZone = {};
    DragDrop.default.column = {};

    DragDrop.getContentType = function (closestColumn, identifier) {
        return closestColumn.data(identifier) ? $.map(closestColumn.data(identifier).toString().split(','), $.trim) : null;
    };

    /**
     * initializes Drag+Drop for all content elements on the page
     */
    DragDrop.default.initialize = function () {
        $(DragDrop.default.draggableIdentifier).draggable({
            scope: 'tt_content',
            cursor: 'move',
            distance: 20,
            addClasses: 'active-drag',
            revert: 'invalid',
            cancel: false,
            start: function () {
                DragDrop.default.onDragStart($(this));
            },
            stop: function () {
                DragDrop.default.onDragStop($(this));
            }
        });
        $(DragDrop.default.dropZoneIdentifier).droppable({
            accept: this.contentIdentifier,
            scope: 'tt_content',
            tolerance: 'pointer',
            over: function (evt, ui) {
                DragDrop.default.onDropHoverOver($(ui.draggable), $(this));
            },
            out: function (evt, ui) {
                DragDrop.default.onDropHoverOut($(ui.draggable), $(this));
            },
            drop: function (evt, ui) {
                DragDrop.default.onDrop($(ui.draggable), $(this), evt);
            }
        });
    };

    /**
     * called when a draggable is selected to be moved
     * @param $element a jQuery object for the draggable
     * @private
     */
    DragDrop.default.onDragStart = function ($element) {
        // Add css class for the drag shadow
        DragDrop.default.originalStyles = $element.get(0).style.cssText;
        DragDrop.default.drag = $element.children(DragDrop.default.drag);
        DragDrop.default.drag.addClass('dragitem-shadow');
        DragDrop.default.types = $element.find(DragDrop.default.cTypeIdentifier);
        if ($element.closest(DragDrop.default.newContentElementWizardIdentifier).length === 0) {
            $element.append('<div class="ui-draggable-copy-message">' + TYPO3.lang['dragdrop.copy.message'] + '</div>');
        } else {
            // all information about CType, list_type and other default values has to be fetched from onclick
            DragDrop.default.newContentElementOnclick = $element.find('a:first').attr('onclick');
            if (typeof DragDrop.default.newContentElementOnclick !== 'undefined') {
                // this is the relevant part defining the default values for tt_content
                // while creating content with the new element wizard the usual way
                DragDrop.default.newContentElementOnclick = unescape(DragDrop.default.newContentElementOnclick.split('document.editForm.defValues.value=unescape(\'%26')[1].split('\');')[0]);
                if (DragDrop.default.newContentElementOnclick.length) {
                    // if there are any default values, they have to be reformatted to an object/array
                    // this can be passed on as parameters during the onDrop action after dragging in new content
                    // CType is available for each element in the wizard, so this will be the identifier later on
                    DragDrop.default.newContentElementDefaultValues = $.parseJSON(
                        '{' + DragDrop.default.newContentElementOnclick.replace(/\&/g, '",').replace(/defVals\[tt_content\]\[/g, '"').replace(/\]\=/g, '":"') + '"}'
                    );
                }
            } else {
                DragDrop.default.newContentElementPositionMapArguments = $element.find('button:first').data('position-map-arguments');
                if (typeof DragDrop.default.newContentElementPositionMapArguments !== 'undefined') {
                    DragDrop.default.newContentElementDefaultValues = DragDrop.default.newContentElementPositionMapArguments.defVals;
                }
            }
            DragDrop.default.types.data('ctype', DragDrop.default.newContentElementDefaultValues.CType);
            DragDrop.default.types.data('list_type', DragDrop.default.newContentElementDefaultValues.list_type);
            DragDrop.default.types.data('tx_gridelements_backend_layout', DragDrop.default.newContentElementDefaultValues.tx_gridelements_backend_layout);
        }
        // Hide create new element button
        DragDrop.default.ownDropZone = $element.children(DragDrop.default.dropZoneIdentifier);
        DragDrop.default.ownDropZone.addClass('drag-start');
        DragDrop.default.column = $element.closest(DragDrop.default.columnIdentifier);
        DragDrop.default.column.removeClass('active');

        $element.parents(DragDrop.default.draggableIdentifier).addClass('move-to-front');
        $element.parents(DragDrop.default.columnHolderIdentifier).find(DragDrop.default.addContentIdentifier).hide();
        $element.find(DragDrop.default.dropZoneIdentifier).hide();

        // make the drop zones visible
        var siblingsDropZones = $element.parents(DragDrop.default.disabledNewContentIdentifier).find(DragDrop.default.dropZoneIdentifier);
        var disabledDropZones = $(DragDrop.default.disabledNewContentIdentifier + ' > ' + DragDrop.default.contentWrapperIdentifier + ' > ' + DragDrop.default.contentIdentifier + ' > ' + DragDrop.default.dropZoneIdentifier);
        var contentType = DragDrop.default.types.data('ctype');
        contentType = contentType ? contentType.toString() : '';
        var listType = DragDrop.default.types.data('list_type');
        listType = listType ? listType.toString() : '';
        var gridType = DragDrop.default.types.data('tx_gridelements_backend_layout');
        gridType = gridType ? gridType.toString() : '';
        $(DragDrop.default.dropZoneIdentifier).not(DragDrop.default.ownDropZone).each(function () {
            var closestColumn = $(this).closest(DragDrop.default.columnIdentifier);
            var allowedContentType = DragDrop.getContentType(closestColumn, 'allowed-ctype');
            var disallowedContentType = DragDrop.getContentType(closestColumn, 'disallowed-ctype');
            var allowedListType = DragDrop.getContentType(closestColumn, 'allowed-list_type');
            var disallowedListType = DragDrop.getContentType(closestColumn, 'disallowed-list_type');
            var allowedGridType = DragDrop.getContentType(closestColumn, 'allowed-tx_gridelements_backend_layout');
            var disallowedGridType = DragDrop.getContentType(closestColumn, 'disallowed-tx_gridelements_backend_layout');
            if ((
                    (!allowedContentType || $.inArray('*', allowedContentType) > -1 || $.inArray(contentType, allowedContentType) > -1) &&
                    (!disallowedContentType || ($.inArray('*', disallowedContentType) === -1 && $.inArray(contentType, disallowedContentType) === -1))
                ) && (listType === '' || (
                    (!allowedListType || $.inArray('*', allowedListType) > -1 || $.inArray(listType, allowedListType) > -1) &&
                    (!disallowedListType || ($.inArray('*', disallowedListType) === -1 && $.inArray(listType, disallowedListType) === -1))
                )) && (gridType === '' || (
                    (!allowedGridType || $.inArray('*', allowedGridType) > -1 || $.inArray(gridType, allowedGridType) > -1) &&
                    (!disallowedGridType || ($.inArray('*', disallowedGridType) === -1 && $.inArray(gridType, disallowedGridType) === -1))
                ))
                &&
                (
                    $(this).not(disabledDropZones).length ||
                    siblingsDropZones.length
                ) &&
                $(this).parent().find('.icon-actions-add').length
            ) {
                $(this).addClass(DragDrop.default.validDropZoneClass);
            } else {
                $(this).closest(DragDrop.default.contentIdentifier).find('> ' + DragDrop.default.addContentIdentifier + ', > > ' + DragDrop.default.addContentIdentifier).show();
            }
        });
    };


    /**
     * called when a draggable is released
     * @param $element a jQuery object for the draggable
     * @private
     */
    DragDrop.default.onDragStop = function ($element) {
        // Remove css class for the drag shadow
        DragDrop.default.drag.removeClass('dragitem-shadow');
        // Show create new element button
        DragDrop.default.ownDropZone.removeClass('drag-start');
        DragDrop.default.column.addClass('active');
        $element.parents(DragDrop.default.draggableIdentifier).removeClass('move-to-front');
        $element.parents(DragDrop.default.columnHolderIdentifier).find(DragDrop.default.addContentIdentifier).show();
        $element.find(DragDrop.default.dropZoneIdentifier).show();
        $element.find('.ui-draggable-copy-message').remove();

        // Reset inline style
        $element.get(0).style.cssText = DragDrop.default.originalStyles.replace('z-index: 100;', '');

        $(DragDrop.default.dropZoneIdentifier + '.' + DragDrop.default.validDropZoneClass).removeClass(DragDrop.default.validDropZoneClass);
    };

    /**
     * this method does the whole logic when a draggable is dropped on to a dropzone
     * sending out the request and afterwards move the HTML element in the right place.
     *
     * @param $draggableElement
     * @param $droppableElement
     * @param {Event} evt the event
     * @param reference if content should be pasted as copy or reference
     * @private
     */
    DragDrop.default.onDrop = function ($draggableElement, $droppableElement, evt, reference) {
        var newColumn = DragDrop.default.getColumnPositionForElement($droppableElement),
            gridColumn = DragDrop.default.getGridColumnPositionForElement($droppableElement);
        if (gridColumn !== false && gridColumn !== '') {
            newColumn = -1;
        } else {
            gridColumn = 0;
        }

        $droppableElement.removeClass(DragDrop.default.dropPossibleHoverClass);
        var $pasteAction = (typeof $draggableElement === 'number' || typeof $draggableElement === 'undefined');
        var $pasteElement = typeof Paste.itemOnClipboardUid === 'number' && Paste.itemOnClipboardUid > 0  && evt !== 'copyFromAnotherPage' ? Paste.itemOnClipboardUid : $draggableElement;

        // send an AJAX request via the AjaxDataHandler
        var contentElementUid = $pasteAction ? $pasteElement : parseInt($draggableElement.data('uid'));
        if (contentElementUid > 0 || (DragDrop.default.newContentElementDefaultValues.CType && !$pasteAction)) {
            var parameters = {};
            // add the information about a possible column position change
            var targetFound = $droppableElement.closest(DragDrop.default.contentIdentifier).data('uid');
            // the item was moved to the top of the colPos, so the page ID is used here
            var targetPid = 0;
            if (typeof targetFound === 'undefined') {
                // the actual page is needed
                targetPid = $('.t3js-page-ce[data-page]').first().data('page');
            } else {
                // the negative value of the content element after where it should be moved
                targetPid = 0 - (parseInt(targetFound) || 0);
            }
            var container = parseInt($droppableElement.closest(DragDrop.default.gridContainerIdentifier).closest(DragDrop.default.contentIdentifier).data('uid')) || 0;
            var language = parseInt($droppableElement.closest('[data-language-uid]').data('language-uid')) || 0;
            var colPos = 0;
            if (container > 0 && gridColumn !== false && gridColumn !== '') {
                colPos = -1;
            } else if (targetPid !== 0) {
                colPos = newColumn;
            }
            parameters['cmd'] = {tt_content: {}};
            parameters['data'] = {tt_content: {}};
            var copyAction = (evt && evt.originalEvent && evt.originalEvent.ctrlKey || $droppableElement.hasClass('t3js-paste-copy') || evt === 'copyFromAnotherPage');
            if (DragDrop.default.newContentElementDefaultValues.CType) {
                parameters['data']['tt_content']['NEW234134'] = DragDrop.default.newContentElementDefaultValues;
                parameters['data']['tt_content']['NEW234134']['pid'] = targetPid;
                parameters['data']['tt_content']['NEW234134']['colPos'] = colPos;
                parameters['data']['tt_content']['NEW234134']['tx_gridelements_container'] = container;
                parameters['data']['tt_content']['NEW234134']['tx_gridelements_columns'] = gridColumn;
                parameters['data']['tt_content']['NEW234134']['sys_language_uid'] = language;

                if (!parameters['data']['tt_content']['NEW234134']['header']) {
                    parameters['data']['tt_content']['NEW234134']['header'] = TYPO3.l10n.localize('tx_gridelements_js.newcontentelementheader');
                }

                if (language > -1) {
                    parameters['data']['tt_content']['NEW234134']['sys_language_uid'] = language;
                }
                parameters['DDinsertNew'] = 1;

                // fire the request, and show a message if it has failed
                require(['TYPO3/CMS/Backend/AjaxDataHandler'], function (DataHandler) {
                    DataHandler.process(parameters).done(function (result) {
                        if (!result.hasErrors) {
                            // insert draggable on the new position
                            if (!$pasteAction) {
                                if (!$droppableElement.parent().hasClass(DragDrop.default.contentIdentifier.substring(1))) {
                                    $draggableElement.detach().css({top: 0, left: 0})
                                        .insertAfter($droppableElement.closest(DragDrop.default.dropZoneIdentifier));
                                } else {
                                    $draggableElement.detach().css({top: 0, left: 0})
                                        .insertAfter($droppableElement.closest(DragDrop.default.contentIdentifier));
                                }
                            }
                            self.location.hash = $droppableElement.closest(DragDrop.default.contentIdentifier).attr('id');
                            self.location.reload(true);
                        }
                    });
                });
            } else if (copyAction) {
                parameters['cmd']['tt_content'][contentElementUid] = {
                    copy: {
                        action: 'paste',
                        target: targetPid,
                        update: {
                            colPos: colPos,
                            tx_gridelements_container: container,
                            tx_gridelements_columns: gridColumn
                        }
                    }
                };
                if (reference === 'reference') {
                    parameters['reference'] = 1;
                }
                if (language > -1) {
                    parameters['cmd']['tt_content'][contentElementUid]['copy']['update']['sys_language_uid'] = language;
                }
                // fire the request, and show a message if it has failed
                require(['TYPO3/CMS/Backend/AjaxDataHandler'], function (DataHandler) {
                    DataHandler.process(parameters).done(function (result) {
                        if (!result.hasErrors) {
                            // insert draggable on the new position
                            if (!$pasteAction) {
                                if (!$droppableElement.parent().hasClass(DragDrop.default.contentIdentifier.substring(1))) {
                                    $draggableElement.detach().css({top: 0, left: 0})
                                        .insertAfter($droppableElement.closest(DragDrop.default.dropZoneIdentifier));
                                } else {
                                    $draggableElement.detach().css({top: 0, left: 0})
                                        .insertAfter($droppableElement.closest(DragDrop.default.contentIdentifier));
                                }
                            }
                            self.location.hash = $droppableElement.closest(DragDrop.default.contentIdentifier).attr('id');
                            self.location.reload(true);
                        }
                    });
                });
            } else {
                if (!$pasteAction) {
                    parameters['cmd']['tt_content'][contentElementUid] = {
                        move: {
                            action: 'paste',
                            target: targetPid,
                            update: {
                                colPos: colPos,
                                tx_gridelements_container: container,
                                tx_gridelements_columns: gridColumn
                            }
                        },
                    };
                    if (language > -1) {
                        parameters['cmd']['tt_content'][contentElementUid]['move']['update']['sys_language_uid'] = language;
                    }
                } else {
                    parameters['CB'] = {
                        paste: 'tt_content|' + targetPid,
                        update: {
                            colPos: colPos,
                            tx_gridelements_container: container,
                            tx_gridelements_columns: gridColumn
                        }
                    };
                    if (language > -1) {
                        parameters['CB']['update']['sys_language_uid'] = language;
                    }
                }
                // fire the request, and show a message if it has failed
                require(['TYPO3/CMS/Backend/AjaxDataHandler'], function (DataHandler) {
                    DataHandler.process(parameters).done(function (result) {
                        if (!result.hasErrors) {
                            // insert draggable on the new position
                            if (!$pasteAction) {
                                if (!$droppableElement.parent().hasClass(DragDrop.default.contentIdentifier.substring(1))) {
                                    $draggableElement.detach().css({top: 0, left: 0})
                                        .insertAfter($droppableElement.closest(DragDrop.default.dropZoneIdentifier));
                                } else {
                                    $draggableElement.detach().css({top: 0, left: 0})
                                        .insertAfter($droppableElement.closest(DragDrop.default.contentIdentifier));
                                }
                            }
                            self.location.hash = $droppableElement.closest(DragDrop.default.contentIdentifier).attr('id');
                            self.location.reload(true);
                        }
                    });
                });
            }
        }
    };

    /**
     * returns the next "upper" container colPos parameter inside the code
     * @param $element
     * @return int|boolean the colPos
     */
    DragDrop.default.getColumnPositionForElement = function ($element) {
        var $gridContainer = $element.closest(DragDrop.default.gridContainerIdentifier);
        if ($gridContainer.length) {
            return -1;
        }
        var $columnContainer = $element.closest('[data-colpos]');
        if ($columnContainer.length && $columnContainer.data('colpos') !== 'undefined') {
            return $columnContainer.data('colpos');
        } else {
            return false;
        }
    };

    /**
     * returns the next "upper" container colPos parameter inside the code
     * @param $element
     * @return int|boolean the colPos
     */
    DragDrop.default.getGridColumnPositionForElement = function ($element) {
        var $gridContainer = $element.closest(DragDrop.default.gridContainerIdentifier);
        var $columnContainer = $element.closest(DragDrop.default.columnIdentifier);
        if ($gridContainer.length && $columnContainer.length && $columnContainer.data('colpos') !== 'undefined') {
            return $columnContainer.data('colpos');
        } else {
            return false;
        }
    };

    return DragDrop;
});

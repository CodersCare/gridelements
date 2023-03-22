<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Hooks;

/***************************************************************
 *  Copyright notice
 *  (c) 2013 Jo Hasenau <info@cybercraft.de>, Tobias Ferger <tobi@tt36.de>
 *  All rights reserved
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use GridElementsTeam\Gridelements\Backend\LayoutSetup;
use GridElementsTeam\Gridelements\Helper\Helper;
use TYPO3\CMS\Backend\Clipboard\Clipboard;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendLayoutView;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class/Function which adds the necessary ExtJS and pure JS stuff for the grid elements.
 *
 * @author Jo Hasenau <info@cybercraft.de>, Tobias Ferger <tobi@tt36.de>
 */
class PageLayoutController
{
    /**
     * @var array
     */
    protected $extensionConfiguration;

    /**
     * @var Helper
     */
    protected Helper $helper;

    /**
     * @var PageRenderer
     */
    protected $pageRenderer;

    public function __construct()
    {
        $this->extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('gridelements');
        $this->helper = Helper::getInstance();
        $this->pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
    }

    /**
     * wrapper function called by hook (\TYPO3\CMS\Backend\Controller\PageLayoutController->renderContent)
     *
     * @param array $parameters An array of available parameters
     * @param \TYPO3\CMS\Backend\Controller\PageLayoutController $pageLayoutController The parent object that triggered this hook
     */
    public function drawHeaderHook(array $parameters, \TYPO3\CMS\Backend\Controller\PageLayoutController $pageLayoutController)
    {
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Gridelements/GridElementsOnReady');
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Gridelements/GridElementsDragDrop');
        if (
            !(isset($this->extensionConfiguration['disableDragInWizard'])
                && (boolean)$this->extensionConfiguration['disableDragInWizard'] === true
            || isset($this->helper->getBackendUser()->uc['disableDragInWizard'])
                && (boolean)$this->helper->getBackendUser()->uc['disableDragInWizard'] === true)
        ) {
            $typo3Version = new Typo3Version();
            if ($typo3Version->getMajorVersion() >= 11) {
                $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Gridelements/GridElementsDragInWizard');
            } else {
                $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Gridelements/GridElementsDragInWizard10');
            }
        }

        /** @var Clipboard $clipObj */
        $clipObj = GeneralUtility::makeInstance(Clipboard::class); // Start clipboard
        $clipObj->initializeClipboard();
        $clipObj->lockToNormal();
        $clipBoard = $clipObj->clipData['normal'];
        if (!$this->pageRenderer->getCharSet()) {
            $this->pageRenderer->setCharSet('utf-8');
        }

        // pull locallang_db.xml to JS side - only the tx_gridelements_js-prefixed keys
        $this->pageRenderer->addInlineLanguageLabelFile(
            'EXT:gridelements/Resources/Private/Language/locallang_db.xlf',
            'tx_gridelements_js'
        );

        $pAddExtOnReadyCode = '
                TYPO3.l10n = {
                    localize: function(langKey){
                        return TYPO3.lang[langKey];
                    }
                }
            ';

        $id = (int)GeneralUtility::_GP('id');
        $layout = GeneralUtility::callUserFunction(
            BackendLayoutView::class . '->getSelectedBackendLayout',
            $id,
            $this
        );
        if (is_array($layout) && !empty($layout['__config']['backend_layout.']['rows.'])) {
            /** @var LayoutSetup $layoutSetup */
            $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class)->init(0);
            $layout = [
                'config' => $layout['__config']['backend_layout.'],
                'allowed' => [],
                'disallowed' => [],
                'maxitems' => [],
            ];
            $columns = $layoutSetup->checkAvailableColumns($layout, true);
            if (isset($columns['allowed']) || isset($columns['disallowed']) || isset($columns['maxitems'])) {
                $layout['columns'] = $columns;
                unset($layout['columns']['allowed']);
                $layout['allowed'] = $columns['allowed'] ?? [];
                $layout['disallowed'] = $columns['disallowed'] ?? [];
                $layout['maxitems'] = $columns['maxitems'] ?? [];
            }
        }

        // add Ext.onReady() code from file
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        try {
            $pAddExtOnReadyCode .= '
                top.pageColumnsAllowed = ' . json_encode($layout['allowed']) . ';
                top.pageColumnsDisallowed = ' . json_encode($layout['disallowed']) . ';
                top.pageColumnsMaxitems = ' . json_encode($layout['maxitems']) . ';
                top.pasteReferenceAllowed = ' . ($this->getBackendUser()->checkAuthMode(
                'tt_content',
                'CType',
                'shortcut',
                $GLOBALS['TYPO3_CONF_VARS']['BE']['explicitADmode'] ?? ''
            ) ? 'true' : 'false') . ';
                top.skipDraggableDetails = ' . (
                isset($this->getBackendUser()->uc['dragAndDropHideNewElementWizardInfoOverlay'])
                && $this->getBackendUser()->uc['dragAndDropHideNewElementWizardInfoOverlay']
                    ? 'true' : 'false'
            ) . ';
                top.browserUrl = ' . json_encode((string)$uriBuilder->buildUriFromRoute('wizard_element_browser')) . ';';
        } catch (RouteNotFoundException $e) {
        }

        if (!empty($clipBoard) && !empty($clipBoard['el'])) {
            $clipBoardElement = GeneralUtility::trimExplode('|', key($clipBoard['el']));
            $clipBoardElementData = [];
            if ($clipBoardElement[0] === 'tt_content') {
                $clipBoardElementData = BackendUtility::getRecord('tt_content', (int)$clipBoardElement[1]);
            }
            if (!empty($clipBoardElementData)) {
                $pAddExtOnReadyCode .= '
                    top.clipBoardElementCType = ' . json_encode($clipBoardElementData['CType']) . ';
                    top.clipBoardElementTxGridelementsBackendLayout = ' . json_encode(($clipBoardElementData['CType'] === 'gridelements_pi1') ? $clipBoardElementData['tx_gridelements_backend_layout'] : '') . ';
                    top.clipBoardElementListType = ' . json_encode($clipBoardElementData['list_type']) . ';';
            } else {
                $pAddExtOnReadyCode .= "
                    top.clipBoardElementCType = '';
                    top.clipBoardElementTxGridelementsBackendLayout = '';
                    top.clipBoardElementListType = '';";
            }
        }

        if (empty($this->extensionConfiguration['disableCopyFromPageButton'])
            && empty($this->helper->getBackendUser()->uc['disableCopyFromPageButton'])) {
            $pAddExtOnReadyCode .= '
                    top.copyFromAnotherPageLinkTemplate = ' . json_encode('<a class="t3js-paste-new btn btn-default" title="' . $this->getLanguageService()->sL('LLL:EXT:gridelements/Resources/Private/Language/locallang_db.xlf:tx_gridelements_js.copyfrompage') . '">' . $iconFactory->getIcon(
                'actions-insert-reference',
                Icon::SIZE_SMALL
            )->render() . '</a>') . ';';
        }

        $this->pageRenderer->addJsInlineCode('gridelementsExtOnReady', $pAddExtOnReadyCode);
    }

    /**
     * Gets the current backend user.
     *
     * @return BackendUserAuthentication
     */
    public function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * getter for language service
     *
     * @return LanguageService
     */
    public function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}

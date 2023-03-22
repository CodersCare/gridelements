<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Hooks;

/***************************************************************
 *  Copyright notice
 *  (c) 2013 Jo Hasenau <info@cybercraft.de>
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
use TYPO3\CMS\Backend\Controller\ContentElement\NewContentElementController;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\Wizard\NewContentElementWizardHookInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * Class/Function which manipulates the rendering of items within the new content element wizard
 *
 * @author Jo Hasenau <info@cybercraft.de>, Tobias Ferger <tobi@tt36.de>
 */
class WizardItems implements NewContentElementWizardHookInterface
{
    /**
     * @var LayoutSetup
     */
    protected LayoutSetup $layoutSetup;

    /**
     * inject layout setup
     *
     * @param LayoutSetup $layoutSetup
     */
    public function injectLayoutSetup(LayoutSetup $layoutSetup)
    {
        $this->layoutSetup = $layoutSetup;
    }

    /**
     * Processes the items of the new content element wizard
     * and inserts necessary default values for items created within a grid
     *
     * @param array $wizardItems The array containing the current status of the wizard item list before rendering
     * @param NewContentElementController $parentObject The parent object that triggered this hook
     */
    public function manipulateWizardItems(&$wizardItems, &$parentObject)
    {
        if (!$this->getBackendUser()->checkAuthMode('tt_content', 'CType', 'gridelements_pi1', $GLOBALS['TYPO3_CONF_VARS']['BE']['explicitADmode'])) {
            return;
        }
        $pageInfo = $parentObject->getPageInfo();
        $pageId = (int)$pageInfo['uid'];
        $this->init($pageId);

        $container = (int)GeneralUtility::_GP('tx_gridelements_container');
        $column = (int)GeneralUtility::_GP('tx_gridelements_columns');
        $allowed_GP = (string)GeneralUtility::_GP('tx_gridelements_allowed');
        $disallowed_GP = (string)GeneralUtility::_GP('tx_gridelements_disallowed');
        if (!empty($allowed_GP) || !empty($disallowed_GP)) {
            $allowed = json_decode(base64_decode($allowed_GP), true) ?: [];
            if (!empty($allowed)) {
                foreach ($allowed as &$item) {
                    if (!is_array($item)) {
                        $item = array_flip(GeneralUtility::trimExplode(',', $item));
                    }
                }
            }
            $disallowed = json_decode(base64_decode($disallowed_GP), true) ?: [];
            if (!empty($disallowed)) {
                foreach ($disallowed as &$item) {
                    if (!is_array($item)) {
                        $item = array_flip(GeneralUtility::trimExplode(',', $item));
                    }
                }
            }
            $this->removeDisallowedWizardItems($allowed, $disallowed, $wizardItems);
        } else {
            $allowed = null;
            $disallowed = null;
        }
        if (
            (empty($allowed['CType']) || isset($allowed['CType']['gridelements_pi1']) || isset($allowed['CType']['*']))
            && !isset($disallowed['CType']['gridelements_pi1'])
            && !isset($disallowed['tx_gridelements_backend_layout']['*'])
        ) {
            $allowedGridTypes = $allowed['tx_gridelements_backend_layout'] ?? [];
            $disallowedGridTypes = $disallowed['tx_gridelements_backend_layout'] ?? [];
            $excludeLayouts = $this->getExcludeLayouts($container, $pageId);

            $gridItems = $this->layoutSetup->getLayoutWizardItems(
                (int)$parentObject->getColPos(),
                $excludeLayouts,
                $allowedGridTypes,
                $disallowedGridTypes
            );
            $this->addGridItemsToWizard($gridItems, $wizardItems);
        }

        $this->addGridValuesToWizardItems($wizardItems, $container, $column);

        $this->removeEmptyHeadersFromWizard($wizardItems);
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
     * initializes this class
     *
     * @param int $pageUid
     */
    public function init(int $pageUid)
    {
        $this->layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class)->init($pageUid);
    }

    /**
     * remove disallowed content elements from wizard items
     *
     * @param array $allowed
     * @param array $disallowed
     * @param array $wizardItems
     */
    public function removeDisallowedWizardItems(array $allowed, array $disallowed, array &$wizardItems)
    {
        foreach ($wizardItems as $key => $wizardItem) {
            if (empty($wizardItem['header'])) {
                if (
                    (
                        !empty($allowed['CType'])
                        && !isset($allowed['CType'][$wizardItem['tt_content_defValues']['CType']])
                        && !isset($allowed['CType']['*'])
                    ) || (
                        !empty($disallowed) && (
                            isset($disallowed['CType'][$wizardItem['tt_content_defValues']['CType']])
                            || isset($disallowed['CType']['*'])
                        )
                    ) || (
                        isset($wizardItem['tt_content_defValues']['list_type'])
                            && !empty($allowed['list_type'])
                            && !isset($allowed['list_type'][$wizardItem['tt_content_defValues']['list_type']])
                            && !isset($allowed['list_type']['*'])
                    ) || (
                        isset($wizardItem['tt_content_defValues']['list_type'])
                        && !empty($disallowed) && (
                            isset($disallowed['list_type'][$wizardItem['tt_content_defValues']['list_type']])
                            || isset($disallowed['list_type']['*'])
                        )
                    )
                ) {
                    unset($wizardItems[$key]);
                }
            }
        }
    }

    /**
     * retrieve layouts to exclude from pageTSconfig
     *
     * @param int $container
     * @param int $pageId The ID of the page that triggered this hook
     *
     * @return string
     */
    public function getExcludeLayouts(int $container, int $pageId)
    {
        $excludeLayouts = 0;
        $excludeArray = [];

        $TSconfig = BackendUtility::getPagesTSconfig($pageId);

        if ($container && !empty($TSconfig['TCEFORM.']['tt_content.']['tx_gridelements_backend_layout.']['itemsProcFunc.']['topLevelLayouts'])) {
            $excludeArray[] = trim($TSconfig['TCEFORM.']['tt_content.']['tx_gridelements_backend_layout.']['itemsProcFunc.']['topLevelLayouts']);
        }

        $excludeLayoutsTS = $TSconfig['TCEFORM.']['tt_content.']['tx_gridelements_backend_layout.']['itemsProcFunc.']['excludeLayouts'] ?? '';

        if ($excludeLayoutsTS) {
            $excludeArray[] = trim($excludeLayoutsTS);
        }

        $userExcludeLayoutsTS = $TSconfig['TCEFORM.']['tt_content.']['tx_gridelements_backend_layout.']['itemsProcFunc.']['userExcludeLayouts'] ?? '';

        if ($userExcludeLayoutsTS) {
            $excludeArray[] = trim($userExcludeLayoutsTS);
        }

        if (!empty($excludeArray)) {
            $excludeLayouts = implode(',', $excludeArray);
        }

        return (string)$excludeLayouts;
    }

    /**
     * add gridelements to wizard items
     *
     * @param array $gridItems
     * @param array $wizardItems
     */
    public function addGridItemsToWizard(array &$gridItems, array &$wizardItems)
    {
        if (empty($gridItems)) {
            return;
        }
        // create gridelements node
        $wizardItems['gridelements'] = [];

        // set header label
        $wizardItems['gridelements']['header'] = $this->getLanguageService()->sL(
            'LLL:EXT:gridelements/Resources/Private/Language/locallang_db.xlf:tx_gridelements_backend_layout_wizard_label'
        );

        $iconRegistry = GeneralUtility::makeInstance(IconRegistry::class);

        // traverse the gridelements and create wizard item for each gridelement
        foreach ($gridItems as $key => $item) {
            $largeIcon = '';
            if (empty($item['iconIdentifierLarge'])) {
                if (!empty($item['icon']) && is_array($item['icon']) && isset($item['icon'][1])) {
                    $item['iconIdentifierLarge'] = 'gridelements-large-' . $key;
                    $largeIcon = $item['icon'][1];
                    if (StringUtility::beginsWith($largeIcon, '../typo3conf/ext/')) {
                        $largeIcon = str_replace('../typo3conf/ext/', 'EXT:', $largeIcon);
                    }
                    if (StringUtility::beginsWith($largeIcon, '../uploads/tx_gridelements/')) {
                        $largeIcon = str_replace('../', '', $largeIcon);
                    } else {
                        if (!StringUtility::beginsWith($largeIcon, 'EXT:') && strpos(
                            $largeIcon,
                            '/'
                        ) === false
                        ) {
                            $largeIcon = GeneralUtility::resolveBackPath($item['icon'][1]);
                        }
                    }
                    if (!empty($largeIcon)) {
                        if (StringUtility::endsWith($largeIcon, '.svg')) {
                            $iconRegistry->registerIcon($item['iconIdentifierLarge'], SvgIconProvider::class, [
                                'source' => $largeIcon,
                            ]);
                        } else {
                            $iconRegistry->registerIcon(
                                $item['iconIdentifierLarge'],
                                BitmapIconProvider::class,
                                [
                                    'source' => $largeIcon,
                                ]
                            );
                        }
                    }
                } else {
                    $item['iconIdentifierLarge'] = 'gridelements-large-' . $key;
                    $iconRegistry->registerIcon($item['iconIdentifierLarge'], SvgIconProvider::class, [
                        'source' => 'EXT:gridelements/Resources/Public/Icons/gridelements.svg',
                    ]);
                }
            }

            // Traverse defVals
            $defVals = '';

            if (!empty($item['tt_content_defValues'])) {
                foreach ($item['tt_content_defValues'] as $field => $value) {
                    $value = $this->getLanguageService()->sL($value);
                    $defVals .= '&defVals[tt_content][' . $field . ']=' . $value;
                }
            }

            $itemIdentifier = $item['alias'] ?? $item['uid'];
            $wizardItems['gridelements_' . $itemIdentifier] = [
                'title' => $item['title'] ?? '',
                'description' => $item['description'] ?? '',
                'params' => ($largeIcon ? '&largeIconImage=' . $largeIcon : '')
                    . '&defVals[tt_content][CType]=gridelements_pi1' . $defVals . '&defVals[tt_content][tx_gridelements_backend_layout]=' . $item['uid']
                    . ($item['tll'] ? '&isTopLevelLayout' : ''),
                'tt_content_defValues' => [
                    'CType' => 'gridelements_pi1',
                    'tx_gridelements_backend_layout' => $item['uid'],
                ],
            ];
            $icon = '';
            if (!empty($item['iconIdentifier'])) {
                $wizardItems['gridelements_' . $itemIdentifier]['iconIdentifier'] = $item['iconIdentifier'];
            } elseif (!empty($item['icon']) && is_array($item['icon']) && isset($item['icon'][0])) {
                $item['iconIdentifier'] = 'gridelements-' . $key;
                $icon = $item['icon'][0];
                if (StringUtility::beginsWith($icon, '../typo3conf/ext/')) {
                    $icon = str_replace('../typo3conf/ext/', 'EXT:', $icon);
                }
                if (StringUtility::beginsWith($icon, '../uploads/tx_gridelements/')) {
                    $icon = str_replace('../', '', $icon);
                } else {
                    if (!StringUtility::beginsWith($icon, 'EXT:') && strpos($icon, '/') !== false) {
                        $icon = GeneralUtility::resolveBackPath($item['icon'][0]);
                    }
                }
                if (StringUtility::endsWith($icon, '.svg')) {
                    $iconRegistry->registerIcon($item['iconIdentifier'], SvgIconProvider::class, [
                        'source' => $icon,
                    ]);
                } else {
                    $iconRegistry->registerIcon($item['iconIdentifier'], BitmapIconProvider::class, [
                        'source' => $icon,
                    ]);
                }
            } else {
                $item['iconIdentifier'] = 'gridelements-' . $key;
                $iconRegistry->registerIcon($item['iconIdentifier'], SvgIconProvider::class, [
                    'source' => 'EXT:gridelements/Resources/Public/Icons/gridelements.svg',
                ]);
            }
            if ($icon && !isset($wizardItems['gridelements_' . $itemIdentifier]['iconIdentifier'])) {
                $wizardItems['gridelements_' . $itemIdentifier]['iconIdentifier'] = 'gridelements-' . $key;
            } else {
                if (!isset($wizardItems['gridelements_' . $itemIdentifier]['iconIdentifier'])) {
                    $wizardItems['gridelements_' . $itemIdentifier]['iconIdentifier'] = 'gridelements-default';
                }
            }
        }
    }

    /**
     * @return LanguageService
     */
    public function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    /**
     * initializes wizard items
     *
     * @param array $wizardItems
     * @param int $container
     * @param int $column
     */
    public function addGridValuesToWizardItems(array &$wizardItems, int $container, int $column)
    {
        foreach ($wizardItems as $key => $wizardItem) {
            if (!isset($wizardItems[$key]['params'])) {
                $wizardItems[$key]['params'] = '';
            }
            if (empty($wizardItem['header'])) {
                if ($container !== 0) {
                    if (!isset($wizardItem['tt_content_defValues'])) {
                        $wizardItems[$key]['tt_content_defValues'] = [];
                    }
                    $wizardItems[$key]['tt_content_defValues']['tx_gridelements_container'] = $container;
                    $wizardItems[$key]['params'] .= '&defVals[tt_content][tx_gridelements_container]=' . $container;
                }
                $wizardItems[$key]['tt_content_defValues']['tx_gridelements_columns'] = $column;
                $wizardItems[$key]['params'] .= '&defVals[tt_content][tx_gridelements_columns]=' . $column;
            }
            if (isset($wizardItem['tt_content_defValues']['CType']) && $wizardItem['tt_content_defValues']['CType'] === 'table') {
                $wizardItems[$key]['tt_content_defValues']['bodytext'] = '';
                $wizardItems[$key]['params'] .= '&defVals[tt_content][bodytext]=';
            }
            if (empty($wizardItems[$key]['params'])) {
                unset($wizardItems[$key]['params']);
            }
        }
    }

    /**
     * remove unnecessary headers from wizard items
     *
     * @param array $wizardItems
     */
    public function removeEmptyHeadersFromWizard(array &$wizardItems)
    {
        $headersWithElements = [];
        foreach ($wizardItems as $key => $wizardItem) {
            $keyParts = GeneralUtility::trimExplode('_', $key);
            if (!empty($keyParts[1])) {
                $keyChunk = '';
                foreach ($keyParts as $keyPart) {
                    $keyChunk .= $keyChunk ? '_' . $keyPart : $keyPart;
                    $headersWithElements[$keyChunk] = true;
                }
            }
        }
        foreach ($wizardItems as $key => $wizardItem) {
            if (!empty($wizardItem['header'])) {
                if (!isset($headersWithElements[$key])) {
                    unset($wizardItems[$key]);
                }
            }
        }
    }
}

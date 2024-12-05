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

use Doctrine\DBAL\Exception;
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

use function str_ends_with;
use function str_starts_with;

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
    public function injectLayoutSetup(LayoutSetup $layoutSetup): void
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
    public function manipulateWizardItems(array &$wizardItems, NewContentElementController &$parentObject): void
    {
        if (!$this->getBackendUser()->checkAuthMode('tt_content', 'CType', 'gridelements_pi1')) {
            return;
        }
        $pageInfo = $parentObject->getPageInfo();
        $pageId = (int)$pageInfo['uid'];
        $this->init($pageId);

        $container = (int)($GLOBALS['TYPO3_REQUEST']->getParsedBody()['tx_gridelements_container'] ?? $GLOBALS['TYPO3_REQUEST']->getQueryParams()['tx_gridelements_container'] ?? null);
        $column = (int)($GLOBALS['TYPO3_REQUEST']->getParsedBody()['tx_gridelements_columns'] ?? $GLOBALS['TYPO3_REQUEST']->getQueryParams()['tx_gridelements_columns'] ?? null);
        $allowed_GP = (string)($GLOBALS['TYPO3_REQUEST']->getParsedBody()['tx_gridelements_allowed'] ?? $GLOBALS['TYPO3_REQUEST']->getQueryParams()['tx_gridelements_allowed'] ?? null);
        $disallowed_GP = (string)($GLOBALS['TYPO3_REQUEST']->getParsedBody()['tx_gridelements_disallowed'] ?? $GLOBALS['TYPO3_REQUEST']->getQueryParams()['tx_gridelements_disallowed'] ?? null);
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
     * @throws Exception
     */
    public function init(int $pageUid): void
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
    public function removeDisallowedWizardItems(array $allowed, array $disallowed, array &$wizardItems): void
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
    public function getExcludeLayouts(int $container, int $pageId): string
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
    public function addGridItemsToWizard(array &$gridItems, array &$wizardItems): void
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
                    if (str_starts_with($largeIcon, '../typo3conf/ext/')) {
                        $largeIcon = str_replace('../typo3conf/ext/', 'EXT:', $largeIcon);
                    }
                    if (str_starts_with($largeIcon, '../uploads/tx_gridelements/')) {
                        $largeIcon = str_replace('../', '', $largeIcon);
                    } else {
                        if (!str_starts_with($largeIcon, 'EXT:') && !str_contains(
                            $largeIcon,
                            '/'
                        )
                        ) {
                            $largeIcon = GeneralUtility::resolveBackPath($item['icon'][1]);
                        }
                    }
                    if (!empty($largeIcon)) {
                        if (str_ends_with($largeIcon, '.svg')) {
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
                if (str_starts_with($icon, '../typo3conf/ext/')) {
                    $icon = str_replace('../typo3conf/ext/', 'EXT:', $icon);
                }
                if (str_starts_with($icon, '../uploads/tx_gridelements/')) {
                    $icon = str_replace('../', '', $icon);
                } else {
                    if (!str_starts_with($icon, 'EXT:') && str_contains($icon, '/')) {
                        $icon = GeneralUtility::resolveBackPath($item['icon'][0]);
                    }
                }
                if (str_ends_with($icon, '.svg')) {
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
    public function addGridValuesToWizardItems(array &$wizardItems, int $container, int $column): void
    {
        foreach ($wizardItems as $key => $wizardItem) {
            if (!isset($wizardItem['params'])) {
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
    public function removeEmptyHeadersFromWizard(array &$wizardItems): void
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

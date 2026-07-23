<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\EventListener;

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

use function str_ends_with;
use function str_starts_with;
use TYPO3\CMS\Backend\Controller\Event\ModifyNewContentElementWizardItemsEvent;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendLayoutView;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Core\Imaging\IconRegistry;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class ModifyNewContentElementWizardItemsListener
{
    /**
     * @param array $gridElementsExtensionConfiguration
     * @param LayoutSetup|null $layoutSetup
     */
    public function __construct(
        private readonly array $gridElementsExtensionConfiguration,
        private LayoutSetup|null $layoutSetup = null
    ) {
        if (empty($layoutSetup)) {
            $this->layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class);
        }
    }

    /**
     * This listener should only be processed if the user has permissions to use gridelements
     *
     * @return bool
     */
    private function canBeExecuted(): bool
    {
        return $this->getBackendUser()->checkAuthMode('tt_content', 'CType', 'gridelements_pi1');
    }

    /**
     * @param ModifyNewContentElementWizardItemsEvent $event
     * @throws Exception
     */
    public function __invoke(ModifyNewContentElementWizardItemsEvent $event): void
    {
        if (!$this->canBeExecuted()) {
            return;
        }

        $this->layoutSetup->init($event->getUidPid());

        $requestArguments = $this->getRequestArguments();
        if ($requestArguments === null) {
            return;
        }

        $wizardItems = $event->getWizardItems();

        $allowed = $requestArguments['allowed'] ?? [];
        $disallowed = $requestArguments['disallowed'] ?? [];

        if (!empty($allowed) || !empty($disallowed)) {
            $this->removeDisallowedWizardItems($allowed, $disallowed, $wizardItems);
        } else {
            $allowed = [];
            $disallowed = [];
        }

        if (
            (empty($allowed['CType']) || isset($allowed['CType']['gridelements_pi1']) || isset($allowed['CType']['*']))
            && (!isset($disallowed['CType']['gridelements_pi1']))
            && (!isset($disallowed['tx_gridelements_backend_layout']['*']))
        ) {
            $allowedGridTypes = $allowed['tx_gridelements_backend_layout'] ?? [];
            $disallowedGridTypes = $disallowed['tx_gridelements_backend_layout'] ?? [];
            $excludeLayouts = $this->getExcludeLayouts((int)$requestArguments['container'], $event->getUidPid());

            $gridItems = $this->layoutSetup->getLayoutWizardItems(
                (int)$event->getColPos(),
                $excludeLayouts,
                $allowedGridTypes,
                $disallowedGridTypes
            );
            $this->addGridItemsToWizard($gridItems, $wizardItems);
        }
        $this->addGridValuesToWizardItems($wizardItems, (int)$requestArguments['container'], (int)$requestArguments['column']);
        $event->setWizardItems($wizardItems);
    }

    /**
     * @return array|null
     */
    private function getRequestArguments(): array|null
    {
        $request = $this->getServerRequest();
        if ($request === null) {
            return null;
        }

        $queryParams = $request->getQueryParams();

        if (isset($queryParams['colPos']) && (int)$queryParams['colPos'] > -1) {
            $restrictions = $this->getRestrictionsFromBackendLayout($queryParams, (int)$queryParams['colPos']);
        } elseif (!empty($queryParams['tx_gridelements_container'])) {
            $restrictions = $this->getRestrictionsFromGridContainer(
                (int)$queryParams['tx_gridelements_container'],
                (int)($queryParams['tx_gridelements_columns'] ?? 0)
            );
        } else {
            $restrictions = ['allowed' => [], 'disallowed' => []];
        }

        if (!empty($restrictions['allowed'])) {
            foreach ($restrictions['allowed'] as &$item) {
                if (!is_array($item)) {
                    $item = array_flip(GeneralUtility::trimExplode(',', $item));
                }
            }
        }

        if (!empty($restrictions['disallowed'])) {
            foreach ($restrictions['disallowed'] as &$item) {
                if (!is_array($item)) {
                    $item = array_flip(GeneralUtility::trimExplode(',', $item));
                }
            }
        }

        return [
            'container' => $queryParams['tx_gridelements_container'] ?? 0,
            'column' => $queryParams['tx_gridelements_columns'] ?? 0,
            'allowed' => $restrictions['allowed'],
            'disallowed' => $restrictions['disallowed'],
        ];
    }

    /**
     * @param $queryParams
     * @param $colPos
     * @return array[]
     */
    protected function getRestrictionsFromBackendLayout($queryParams, $colPos): array
    {
        $backendLayoutView = GeneralUtility::makeInstance(BackendLayoutView::class);
        $backendLayout = $backendLayoutView->getSelectedBackendLayout((int)$queryParams['id'] ?? 0);
        $allowed = [];
        $disallowed = [];
        $configuration = [];
        if (in_array($colPos, array_map('intval', $backendLayout['__colPosList']), true)) {
            foreach ($backendLayout['__config']['backend_layout.']['rows.'] as $row) {
                if (empty($row['columns.'])) {
                    continue;
                }
                foreach ($row['columns.'] as $column) {
                    if (isset($column['colPos']) && $column['colPos'] !== '' && $colPos === (int)$column['colPos']) {
                        $configuration = $column;
                        break;
                    }
                }
            }
            $activateGridelements = false;
            $deactivateGridelements = false;
            $activatePlugins = false;
            $deactivatePlugins = false;
            if (!empty($configuration['disallowed.'])) {
                foreach ($configuration['disallowed.'] as $key => $disallowedString) {
                    if ($disallowedString === '*' && $key === 'Ctype') {
                        return [];
                    }
                    if ($disallowedString === '*' && !empty($configuration['allowed.'][$key])) {
                        unset($configuration['allowed.'][$key]);
                    }
                    if ($disallowedString === '*' && $key === 'list_type') {
                        $deactivatePlugins = true;
                    }
                    if ($disallowedString === '*' && $key === 'tx_gridelements_backend_layout') {
                        $deactivateGridelements = true;
                    }
                    $disallowed[$key] = array_flip(GeneralUtility::trimExplode(',', $disallowedString));
                }
            }
            if (!empty($configuration['allowed.'])) {
                foreach ($configuration['allowed.'] as $key => $allowedString) {
                    $allowed[$key] = array_flip(GeneralUtility::trimExplode(',', $allowedString));
                    if ($key === 'list_type' && !empty($allowedString) && !$deactivatePlugins) {
                        $activatePlugins = true;
                    }
                    if ($key === 'tx_gridelements_backend_layout' && !empty($allowedString) && !$deactivateGridelements) {
                        $activateGridelements = true;
                    }
                }
            }
            if ($activatePlugins) {
                $allowed['CType']['list'] = 1;
            }
            if ($activateGridelements) {
                $allowed['CType']['gridelements_pi1'] = 1;
            }
        }
        return [
            'allowed' => $allowed,
            'disallowed' => $disallowed
        ];
    }

    /**
     * Derive allowed/disallowed restrictions from the grid container record and its layout config.
     */
    protected function getRestrictionsFromGridContainer(int $containerId, int $columnNumber): array
    {
        $container = BackendUtility::getRecord('tt_content', $containerId, 'tx_gridelements_backend_layout,pid');
        if (empty($container)) {
            return ['allowed' => [], 'disallowed' => []];
        }
        $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class)->init((int)$container['pid']);
        $layoutColumns = $layoutSetup->getLayoutColumns((string)$container['tx_gridelements_backend_layout']);
        return [
            'allowed' => $layoutColumns['allowed'][$columnNumber] ?? [],
            'disallowed' => $layoutColumns['disallowed'][$columnNumber] ?? [],
        ];
    }

    /**
     * Get the server request used for fetching query params
     *
     * @return ServerRequest|null
     */
    private function getServerRequest(): ServerRequest|null
    {
        return $GLOBALS['TYPO3_REQUEST'] ?? null;
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
                $values = $this->getWizardItemDefaultValues($wizardItem);
                if (
                    (
                        !empty($allowed['CType'])
                        && !isset($allowed['CType'][$values['CType'] ?? null])
                        && !isset($allowed['CType']['*'])
                    ) || (
                        !empty($disallowed) && (
                            isset($disallowed['CType'][$values['CType'] ?? null])
                            || isset($disallowed['CType']['*'])
                        )
                    ) || (
                        isset($values['list_type'])
                        && !empty($allowed['list_type'])
                        && !isset($allowed['list_type'][$values['list_type']])
                        && !isset($allowed['list_type']['*'])
                    ) || (
                        isset($values['list_type'])
                        && !empty($disallowed) && (
                            isset($disallowed['list_type'][$values['list_type']])
                            || isset($disallowed['list_type']['*'])
                        )
                    )
                ) {
                    unset($wizardItems[$key]);
                }
            }
        }
        $previousKey = '';
        foreach ($wizardItems as $key => $wizardItem) {
            if (!empty($wizardItem['header']) && !empty($wizardItems[$previousKey]['header'])) {
                unset($wizardItems[$previousKey]);
            }
            $previousKey = $key;
        }
        if (!empty($wizardItems[$previousKey]['header'])) {
            unset($wizardItems[$previousKey]);
        }
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
            $values = $this->getWizardItemDefaultValues($wizardItem);
            $changed = false;

            if (empty($wizardItem['header'])) {
                if ($container !== 0) {
                    $values['tx_gridelements_container'] = $container;
                }
                $values['tx_gridelements_columns'] = $column;
                $changed = true;
            }
            if (($values['CType'] ?? null) === 'table') {
                $values['bodytext'] = '';
                $changed = true;
            }
            if ($changed) {
                $this->setWizardItemDefaultValues($wizardItems, $key, $values);
            }
        }
    }

    /**
     * TYPO3 v12's NewContentElementController reads wizard item default values from
     * 'tt_content_defValues', while TYPO3 v13's reads 'defaultValues'. Wizard items built
     * by this listener (and by core, depending on which version is running) may carry
     * either key, so read/write both to stay compatible with both major versions.
     */
    private function getWizardItemDefaultValues(array $wizardItem): array
    {
        return (array)($wizardItem['defaultValues'] ?? $wizardItem['tt_content_defValues'] ?? []);
    }

    private function setWizardItemDefaultValues(array &$wizardItems, string|int $key, array $values): void
    {
        $wizardItems[$key]['defaultValues'] = $values;
        $wizardItems[$key]['tt_content_defValues'] = $values;
    }

    /**
     * add gridelements to wizard items
     *
     * @param array $gridItems
     * @param array $wizardItems
     */
    public function addGridItemsToWizard(array $gridItems, array &$wizardItems): void
    {
        if (empty($gridItems)) {
            return;
        }
        // create gridelements node
        $wizardItems['gridelements'] = [];

        // set header label
        $wizardItems['gridelements']['header'] = LocalizationUtility::translate(
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

            // Traverse default values
            $defaultValues = [
                'CType' => 'gridelements_pi1',
                'tx_gridelements_backend_layout' => $item['uid'],
                'isTopLevelLayout' => $item['tll'] ?? '',
                'largeIconImage' => $largeIcon ?? ''
            ];
            if (!empty($item['defaultValues'])) {
                $defaultValues = array_merge($defaultValues, $item['defaultValues']);
            }
            $itemIdentifier = $item['alias'] ?? $item['uid'];
            $wizardItems['gridelements_' . $itemIdentifier] = [
                'title' => $item['title'] ?? '',
                'description' => $item['description'] ?? '',
                'defaultValues' => $defaultValues,
                'tt_content_defValues' => $defaultValues,
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
}

<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Backend;

/***************************************************************
 *  Copyright notice
 *  (c) 2013 Arno Dudek <webmaster@adgrafik.at>
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

use GridElementsTeam\Gridelements\Helper\Helper;
use PDO;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DefaultRestrictionContainer;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * Utilities for gridelements.
 *
 * @author Arno Dudek <webmaster@adgrafik.at>
 */
class LayoutSetup
{
    /**
     * @var DefaultRestrictionContainer
     */
    protected DefaultRestrictionContainer $restrictions;

    /**
     * @var array
     */
    protected array $layoutSetup = [];

    /**
     * @var LanguageService
     */
    protected LanguageService $languageService;

    /**
     * @var array
     */
    protected array $typoScriptSetup;

    /**
     * @var string
     */
    protected string $flexformConfigurationPathAndFileName = 'EXT:gridelements/Configuration/FlexForms/default_flexform_configuration.xml';

    /**
     * @var int
     */
    protected int $realPid = 0;

    /**
     * Load page TSconfig
     *
     * @param int $pageId The current page ID
     * @param array $typoScriptSetup The PlugIn configuration
     *
     * @return LayoutSetup
     */
    public function init(int $pageId, array $typoScriptSetup = []): LayoutSetup
    {
        $this->setLanguageService($GLOBALS['LANG'] ?? null);
        $pageId = (strpos((string)$pageId, 'NEW') === 0) ? 0 : $pageId;
        if ($pageId < 0) {
            $pageId = Helper::getInstance()->getPidFromUid($pageId);
        }
        $this->realPid = $pageId;
        $this->loadLayoutSetup($pageId);
        foreach ($this->layoutSetup as $key => $setup) {
            $columns = $this->getLayoutColumns((string)$key);
            if (!empty($columns['allowed']) || !empty($columns['disallowed']) || !empty($columns['maxitems'])) {
                $this->layoutSetup[$key]['columns'] = $columns;
                unset($this->layoutSetup[$key]['columns']['allowed']);
                $this->layoutSetup[$key]['allowed'] = $columns['allowed'] ?? [];
                $this->layoutSetup[$key]['disallowed'] = $columns['disallowed'] ?? [];
                $this->layoutSetup[$key]['maxitems'] = $columns['maxitems'] ?? [];
            }
        }
        $this->setTypoScriptSetup($typoScriptSetup);
        return $this;
    }

    /**
     * Returns the page TSconfig merged with the grid layout records
     *
     * @param int $pageId The uid of the page we are currently working on
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function loadLayoutSetup(int $pageId)
    {
        // Load page TSconfig.
        if (TYPO3_MODE === 'FE') {
            $pageTSconfig = $GLOBALS['TSFE']->getPagesTSconfig();
        } else {
            $pageTSconfig = BackendUtility::getPagesTSconfig($pageId);
        }
        $excludeLayoutIds = !empty($pageTSconfig['tx_gridelements.']['excludeLayoutIds'])
            ? array_flip(GeneralUtility::trimExplode(',', $pageTSconfig['tx_gridelements.']['excludeLayoutIds']))
            : [];

        $overruleRecords = isset($pageTSconfig['tx_gridelements.']['overruleRecords'])
            && (int)$pageTSconfig['tx_gridelements.']['overruleRecords'] === 1;

        $gridLayoutConfig = [];
        if (!empty($pageTSconfig['tx_gridelements.']['setup.'])) {
            foreach ($pageTSconfig['tx_gridelements.']['setup.'] as $layoutId => $item) {
                // remove tailing dot of layout ID
                $layoutId = rtrim($layoutId, '.');

                // Continue if layout is excluded.
                if (isset($excludeLayoutIds[$layoutId])) {
                    continue;
                }

                // Parse icon path for records.
                if (!empty($item['icon'])) {
                    $icons = explode(',', $item['icon']);
                    $item['icon'] = $icons;
                }

                // remove tailing dot of config
                if (!empty($item['config.'])) {
                    $item['config'] = $item['config.'];
                    unset($item['config.']);
                    if (isset($item['backend_layout.'])) {
                        $item['config'] = $item['backend_layout.'];
                    }
                    unset($item['backend_layout.']);
                }

                // Change topLevelLayout to top_level_layout.
                if (isset($item['topLevelLayout'])) {
                    $item['top_level_layout'] = $item['topLevelLayout'];
                    unset($item['topLevelLayout']);
                }

                // Change flexformDS to pi_flexform_ds.
                if (isset($item['flexformDS'])) {
                    $item['pi_flexform_ds'] = $item['flexformDS'];
                    unset($item['flexformDS']);
                }

                $gridLayoutConfig[$layoutId] = $item;
            }
        }

        $storagePid = isset($pageTSconfig['TCEFORM.']['pages.']['_STORAGE_PID'])
            ? (int)$pageTSconfig['TCEFORM.']['pages.']['_STORAGE_PID']
            : 0;
        $pageTSconfigId = isset($pageTSconfig['TCEFORM.']['tt_content.']['tx_gridelements_backend_layout.']['PAGE_TSCONFIG_ID'])
            ? implode(',', GeneralUtility::intExplode(
                ',',
                $pageTSconfig['TCEFORM.']['tt_content.']['tx_gridelements_backend_layout.']['PAGE_TSCONFIG_ID']
            ))
            : 0;

        // Load records.
        $queryBuilder = $this->getQueryBuilder();
        $layoutQuery = $queryBuilder
            ->select('*')
            ->from('tx_gridelements_backend_layout')
            ->where(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->andX(
                        $queryBuilder->expr()->comparison($pageTSconfigId, '=', 0),
                        $queryBuilder->expr()->comparison($storagePid, '=', 0)
                    ),
                    $queryBuilder->expr()->orX(
                        $queryBuilder->expr()->eq(
                            'pid',
                            $queryBuilder->createNamedParameter((int)$pageTSconfigId, PDO::PARAM_INT)
                        ),
                        $queryBuilder->expr()->eq(
                            'pid',
                            $queryBuilder->createNamedParameter($storagePid, PDO::PARAM_INT)
                        )
                    ),
                    $queryBuilder->expr()->andX(
                        $queryBuilder->expr()->comparison($pageTSconfigId, '=', 0),
                        $queryBuilder->expr()->eq(
                            'pid',
                            $queryBuilder->createNamedParameter($pageId, PDO::PARAM_INT)
                        )
                    )
                )
            )
            ->orderBy('sorting', 'ASC');

        $layoutItems = $layoutQuery->execute()->fetchAll();

        $gridLayoutRecords = [];

        $fileRepository = GeneralUtility::makeInstance(FileRepository::class);

        foreach ($layoutItems as $item) {
            if (isset($item['alias']) && (string)$item['alias'] !== '') {
                $layoutId = $item['alias'];
            } else {
                $layoutId = $item['uid'];
            }
            // Continue if layout is excluded.
            if (isset($excludeLayoutIds[$layoutId])) {
                continue;
            }

            // Prepend icon path for records.
            if (!empty($item['icon'])) {
                if (MathUtility::canBeInterpretedAsInteger($item['icon'])) {
                    $icons = [];
                    $fileObjects = $fileRepository->findByRelation('tx_gridelements_backend_layout', 'icon', $item['uid']);
                    foreach ($fileObjects as $fileObject) {
                        $icons[] = $fileObject->getPublicUrl();
                    }
                } else {
                    $icons = explode(',', $item['icon']);
                }
                $item['icon'] = $icons;
            }

            // parse config
            if (!empty($item['config'])) {
                $parser = GeneralUtility::makeInstance(TypoScriptParser::class);
                $parser->parse($parser::checkIncludeLines($item['config']));
                if (isset($parser->setup['backend_layout.'])) {
                    $item['config'] = $parser->setup['backend_layout.'];
                }
            }

            $gridLayoutRecords[$layoutId] = $item;
        }

        if ($overruleRecords === true) {
            ArrayUtility::mergeRecursiveWithOverrule($gridLayoutRecords, $gridLayoutConfig, true, false);
            $this->setLayoutSetup($gridLayoutRecords);
        } else {
            ArrayUtility::mergeRecursiveWithOverrule($gridLayoutConfig, $gridLayoutRecords, true, false);
            $this->setLayoutSetup($gridLayoutConfig);
        }
    }

    /**
     * getter for queryBuilder
     *
     * @return QueryBuilder queryBuilder
     */
    public function getQueryBuilder(): QueryBuilder
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_gridelements_backend_layout');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(HiddenRestriction::class));
        return $queryBuilder;
    }

    /**
     * fetches all available columns for a certain grid container
     *
     * @param string $layoutId The selected backend layout of the grid container
     *
     * @return array first key is 'CSV' The columns available for the selected layout as CSV list and the allowed elements for each of the columns
     */
    public function getLayoutColumns(string $layoutId): array
    {
        if (!isset($this->layoutSetup[$layoutId])) {
            return [];
        }

        if (empty($GLOBALS['tx_gridelements']['ceBackendLayoutData'][$layoutId])) {
            if (!empty($this->layoutSetup[$layoutId]['config']['rows.'])) {
                $GLOBALS['tx_gridelements']['ceBackendLayoutData'][$layoutId] = $this->checkAvailableColumns($this->layoutSetup[$layoutId]);
            }
        }

        return $GLOBALS['tx_gridelements']['ceBackendLayoutData'][$layoutId] ?? [];
    }

    /**
     * @param array $setup
     * @param bool $csvValues
     * @return array
     */
    public function checkAvailableColumns(array $setup, bool $csvValues = false): array
    {
        $availableColumns = ['CSV' => '-2,-1'];
        $allowed = [];
        $disallowed = [];
        $maxItems = [];
        $availableColumns['columns'] = [];
        foreach ($setup['config']['rows.'] as $row) {
            if (!empty($row['columns.'])) {
                foreach ($row['columns.'] as $column) {
                    if (!isset($column['colPos'])) {
                        continue;
                    }
                    $colPos = (int)$column['colPos'];
                    $availableColumns['columns'][$colPos] = $column['name'] ?? $colPos;
                    if (isset($column['allowed.'])) {
                        $column['allowed'] = $column['allowed.'];
                    }
                    if (isset($column['disallowed.'])) {
                        $column['disallowed'] = $column['disallowed.'];
                    }
                    if (!empty($column['allowed']) && !is_array($column['allowed'])) {
                        $allowed[$colPos] = ['CType' => $column['allowed']];
                    } else {
                        if (empty($column['allowed'])) {
                            $allowed[$colPos] = ['CType' => '*'];
                        } else {
                            $allowed[$colPos] = $column['allowed'];
                        }
                    }
                    if (!empty($column['allowedGridTypes'])) {
                        $allowed[$colPos]['tx_gridelements_backend_layout'] = $column['allowedGridTypes'];
                    }
                    if (!empty($column['disallowed'])) {
                        $disallowed[$colPos] = $column['disallowed'];
                    }
                    if (!empty($column['maxitems'])) {
                        $maxItems[$colPos] = $column['maxitems'];
                    }
                    $availableColumns['CSV'] .= ',' . $colPos;
                }
            }
        }
        $availableColumns['allowed'] = $allowed;
        if (!empty($disallowed)) {
            $availableColumns['disallowed'] = $disallowed;
        }
        if (!empty($maxItems)) {
            $availableColumns['maxitems'] = $maxItems;
        }
        return Helper::getInstance()->mergeAllowedDisallowedSettings($availableColumns, $csvValues);
    }

    /**
     * fetches the setup for each of the columns
     * assigns a default setup if there is none available
     *
     * @param string $layoutId The selected backend layout of the grid container
     *
     * @return array The adjusted TypoScript setup for the container or a default setup
     */
    public function getTypoScriptSetup(string $layoutId): array
    {
        $typoScriptSetup = [];

        if ($layoutId == '0' && isset($this->typoScriptSetup['setup.']['default.'])) {
            $typoScriptSetup = $this->typoScriptSetup['setup.']['default.'];
        } elseif ($layoutId && isset($this->typoScriptSetup['setup.'][$layoutId . '.'])) {
            $typoScriptSetup = $this->typoScriptSetup['setup.'][$layoutId . '.'];
        } elseif ($layoutId && isset($this->typoScriptSetup['setup.']['default.'])) {
            $typoScriptSetup = $this->typoScriptSetup['setup.']['default.'];
        }

        // if there is none, we will use a reference to the tt_content setup as a default renderObj
        // without additional stdWrap functionality
        if (empty($typoScriptSetup)) {
            $typoScriptSetup['columns.']['default.']['renderObj'] = '<tt_content';
        }

        return $typoScriptSetup;
    }

    /**
     * setter for typoscript setup
     *
     * @param array $typoScriptSetup
     */
    public function setTypoScriptSetup(array $typoScriptSetup)
    {
        $this->typoScriptSetup = $typoScriptSetup;
    }

    /**
     * Returns the flexformConfigurationPathAndFileName
     *
     * @return string $flexformConfigurationPathAndFileName
     */
    public function getFlexformConfigurationPathAndFileName(): string
    {
        return $this->flexformConfigurationPathAndFileName;
    }

    /**
     * Sets the flexformConfigurationPathAndFileName
     *
     * @param string $flexformConfigurationPathAndFileName
     */
    public function setFlexformConfigurationPathAndFileName(string $flexformConfigurationPathAndFileName)
    {
        $this->flexformConfigurationPathAndFileName = $flexformConfigurationPathAndFileName;
    }

    /**
     * Returns the item array for form field selection.
     *
     * @param int $colPos The selected content column position.
     * @param int $gridColPos
     * @param int $containerId
     * @param int $pageId
     * @return array
     */
    public function getLayoutSelectItems(int $colPos, int $gridColPos = 0, int $containerId = 0, int $pageId = 0): array
    {
        $allowed = ['*' => '*'];
        $disallowed = [];
        $selectItems = [];
        if ($containerId > 0) {
            $container = $this->cacheCurrentParent($containerId, true);
            if (!empty($container)) {
                $containerLayout = $this->layoutSetup[$container['tx_gridelements_backend_layout']] ?? [];
                $allowed = $containerLayout['allowed'][$gridColPos]['tx_gridelements_backend_layout'] ?? [];
                $disallowed = $containerLayout['disallowed'][$gridColPos]['tx_gridelements_backend_layout'] ?? [];
            }
        } elseif ($pageId > 0) {
            $pageLayout = Helper::getInstance()->getSelectedBackendLayout($pageId);
            if (!empty($pageLayout)) {
                $allowed = $pageLayout['allowed'][$colPos]['tx_gridelements_backend_layout'] ?? [];
                $disallowed = $pageLayout['disallowed'][$colPos]['tx_gridelements_backend_layout'] ?? [];
            }
        }
        foreach ($this->layoutSetup as $layoutId => $item) {
            if (
                (
                    $colPos === -1
                    && !empty($item['top_level_layout'])
                ) || (
                    !empty($allowed)
                    && !isset($allowed['*'])
                    && !isset($allowed[$layoutId])
                ) || (
                    !empty($disallowed)
                    && (
                        isset($disallowed['*'])
                        || isset($disallowed[$layoutId])
                    )
                )
            ) {
                continue;
            }
            $icon = 'gridelements-default';
            if (!empty($item['iconIdentifier'])) {
                $icon = $item['iconIdentifier'];
            } elseif (!empty($item['icon'])) {
                $fileRepository = GeneralUtility::makeInstance(FileRepository::class);
                $source = '';
                if (MathUtility::canBeInterpretedAsInteger($item['icon'])) {
                    $icons = [];
                    if (MathUtility::canBeInterpretedAsInteger($layoutId)) {
                        $icons = $fileRepository->findByRelation('tx_gridelements_backend_layout', 'icon', $layoutId);
                    }
                    if (!empty($icons)) {
                        $source = '/' . $icons[0]->getPublicUrl();
                    }
                } elseif (is_array($item['icon']) && !empty($item['icon'][0])) {
                    $source = $item['icon'][0];
                } else {
                    $source = $item['icon'];
                }

                if ($source !== '') {
                    $iconRegistry = GeneralUtility::makeInstance(IconRegistry::class);
                    if (StringUtility::endsWith($source, '.svg')) {
                        $iconRegistry->registerIcon('gridelements-select-icon-' . $layoutId, SvgIconProvider::class, [
                            'source' => $source,
                        ]);
                    } else {
                        $iconRegistry->registerIcon(
                            'gridelements-select-icon-' . $layoutId,
                            BitmapIconProvider::class,
                            [
                                'source' => $source,
                            ]
                        );
                    }
                }
                $icon = 'gridelements-select-icon-' . $layoutId;
            }
            if (!empty($item['title'])) {
                $selectItems[] = [$this->languageService->sL($item['title']), $layoutId, $icon];
            }
        }

        return $selectItems;
    }

    /**
     * Caches Container-Records and their setup to avoid multiple selects of the same record during a single request
     *
     * @param int $gridContainerId The ID of the current grid container
     * @param bool $doReturn
     *
     * @return array|null
     */
    public function cacheCurrentParent(int $gridContainerId = 0, bool $doReturn = false): ?array
    {
        if ($gridContainerId > 0) {
            if (empty($GLOBALS['tx_gridelements']['parentElement'][$gridContainerId])) {
                $GLOBALS['tx_gridelements']['parentElement'][$gridContainerId] = BackendUtility::getRecordWSOL(
                    'tt_content',
                    $gridContainerId
                );
            }
            if ($doReturn) {
                return $GLOBALS['tx_gridelements']['parentElement'][$gridContainerId];
            }
        }

        return null;
    }

    /**
     * Returns the item array for form field selection
     *
     * @param string $layoutId : The selected layout ID of the grid container
     *
     * @return array
     */
    public function getLayoutColumnsSelectItems(string $layoutId): array
    {
        $setup = $this->getLayoutSetup($layoutId);
        if (empty($setup['config']['rows.'])) {
            return [];
        }

        $selectItems = [];
        foreach ($setup['config']['rows.'] as $row) {
            if (empty($row['columns.'])) {
                continue;
            }
            foreach ($row['columns.'] as $column) {
                $selectItems[] = [
                    $this->languageService->sL($column['name']),
                    $column['colPos'] ?? 0,
                    null,
                    $column['allowed'] ?? '*',
                ];
            }
        }

        return $selectItems;
    }

    /**
     * Returns the grid layout setup
     *
     * @param string $layoutId If set only requested layout setup, else all layout setups will be returned.
     *
     * @return array
     */
    public function getLayoutSetup(string $layoutId = ''): array
    {
        // Continue only if setup for given layout ID found.
        if (isset($this->layoutSetup[$layoutId])) {
            return $this->layoutSetup[$layoutId];
        }

        return $this->layoutSetup;
    }

    /**
     * setter for layout setup
     *
     * @param array $layoutSetup
     */
    public function setLayoutSetup(array $layoutSetup)
    {
        $this->layoutSetup = $layoutSetup;
    }

    /**
     * setter for layout setup
     *
     * @param string $key
     * @param array $layoutSetup
     */
    public function setSingleLayoutSetup(string $key, array $layoutSetup)
    {
        $this->layoutSetup[$key] = $layoutSetup;
    }

    /**
     * Returns the item array for form field selection
     *
     * @param int $colPos
     * @param string $excludeLayouts
     * @param array $allowedGridTypes
     * @param array $disallowedGridTypes
     *
     * @return array
     */
    public function getLayoutWizardItems(
        int $colPos,
        string $excludeLayouts = '',
        array $allowedGridTypes = [],
        array $disallowedGridTypes = []
    ): array {
        $wizardItems = [];
        $excludeLayouts = array_flip(explode(',', $excludeLayouts));
        if (empty($this->layoutSetup)) {
            return [];
        }
        foreach ($this->layoutSetup as $layoutId => $item) {
            if (
                (
                    !empty($allowedGridTypes)
                    && !isset($allowedGridTypes[$layoutId])
                ) || isset($disallowedGridTypes[$layoutId])
            ) {
                continue;
            }
            if (isset($excludeLayouts[$layoutId]) || $colPos === -1 && !empty($item['top_level_layout'])) {
                continue;
            }

            $source = '';
            if (!empty($item['icon'])) {
                $fileRepository = GeneralUtility::makeInstance(FileRepository::class);
                if (MathUtility::canBeInterpretedAsInteger($item['icon'])) {
                    $icons = [];
                    if (MathUtility::canBeInterpretedAsInteger($layoutId)) {
                        $icons = $fileRepository->findByRelation('tx_gridelements_backend_layout', 'icon', $layoutId);
                    }
                    if (!empty($icons)) {
                        $source = '/' . $icons[0]->getPublicUrl();
                    }
                } elseif (is_array($item['icon']) && !empty($item['icon'][0])) {
                    $source = $item['icon'][0];
                } else {
                    $source = $item['icon'];
                }
            }
            $item['icon'] = $source;

            $wizardItems[] = [
                'uid' => $layoutId,
                'title' => $this->languageService->sL($item['title'] ?? ''),
                'description' => $this->languageService->sL($item['description'] ?? ''),
                'icon' => [$item['icon']],
                'iconIdentifier' => $item['iconIdentifier'] ?? '',
                'tll' => $item['top_level_layout'] ?? '',
                'tt_content_defValues' => $item['tt_content_defValues.'] ?? '',
            ];
        }

        return $wizardItems;
    }

    /**
     * Returns the FlexForm configuration of a grid layout
     *
     * @param string $layoutId The current layout ID of the grid container
     *
     * @return string
     */
    public function getFlexformConfiguration(string $layoutId): string
    {
        $layoutSetup = $this->getLayoutSetup($layoutId);
        // Get flexform file from pi_flexform_ds if pi_flexform_ds_file not set and "FILE:" found in pi_flexform_ds for backward compatibility.
        if (!empty($layoutSetup['pi_flexform_ds_file'])) {
            $flexformConfiguration = GeneralUtility::getUrl(GeneralUtility::getFileAbsFileName($layoutSetup['pi_flexform_ds_file']));
        } elseif (!empty($layoutSetup['pi_flexform_ds']) && strpos($layoutSetup['pi_flexform_ds'], 'FILE:') === 0) {
            $flexformConfiguration = GeneralUtility::getUrl(GeneralUtility::getFileAbsFileName(substr(
                $layoutSetup['pi_flexform_ds'],
                5
            )));
        } elseif (!empty($layoutSetup['pi_flexform_ds'])) {
            $flexformConfiguration = $layoutSetup['pi_flexform_ds'];
        } else {
            $flexformConfiguration = GeneralUtility::getUrl(GeneralUtility::getFileAbsFileName($this->flexformConfigurationPathAndFileName));
        }

        return $flexformConfiguration;
    }

    /**
     * getter for restrictions
     *
     * @return DefaultRestrictionContainer restrictions
     */
    public function getRestrictions()
    {
        return GeneralUtility::makeInstance(DefaultRestrictionContainer::class);
    }

    /**
     * getter for languageService
     *
     * @return LanguageService $languageService
     */
    public function getLanguageService(): LanguageService
    {
        return $this->languageService;
    }

    /**
     * setter for languageService object
     *
     * @param LanguageService|null $languageService
     */
    public function setLanguageService(LanguageService $languageService = null)
    {
        $this->languageService = $languageService instanceof LanguageService ? $languageService : GeneralUtility::makeInstance(LanguageService::class);
        if ($this->getBackendUser()) {
            $this->languageService->init($this->getBackendUser()->uc['lang'] ?? '');
        }
    }

    /**
     * Gets the current backend user.
     *
     * @return BackendUserAuthentication
     */
    public function getBackendUser(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * Gets the current real pid.
     *
     * @return int
     */
    public function getRealPid(): int
    {
        return $this->realPid;
    }
}

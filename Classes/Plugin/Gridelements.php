<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Plugin;

/***************************************************************
 *  Copyright notice
 *  (c) 2011 Jo Hasenau <info@cybercraft.de>
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

use Doctrine\DBAL\DBALException;
use GridElementsTeam\Gridelements\Backend\LayoutSetup;
use GridElementsTeam\Gridelements\Helper\FlexFormTools;
use PDO;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Plugin 'Grid Element' for the 'gridelements' extension.
 * @deprecated use the recommended static file based on DataProcessing instead of a USER cObject, will be removed in Gridelements v12.0
 * @author Jo Hasenau <info@cybercraft.de>
 */
class Gridelements extends ContentObjectRenderer
{
    /**
     * Same as class name
     *
     * @var string
     */
    public string $prefixId = 'Gridelements';

    /**
     * Path to this script relative to the extension dir
     *
     * @var string
     */
    public string $scriptRelPath = 'Classes/Plugin/Gridelements.php';

    /**
     * The extension key
     *
     * @var string
     */
    public string $extKey = 'gridelements';

    /**
     * @var ContentObjectRenderer
     */
    protected ContentObjectRenderer $cObj;

    /**
     * @var PageRenderer
     */
    protected PageRenderer $pageRenderer;

    /**
     * @var LanguageAspect
     */
    protected LanguageAspect $languageAspect;

    /**
     * @var FlexFormTools
     */
    protected FlexFormTools $flexFormTools;

    /**
     * The main method of the PlugIn
     *
     * @param string $content The PlugIn content
     * @param array $conf The PlugIn configuration
     * @return string The content that is displayed on the website
     * @deprecated use the recommended static file based on DataProcessing instead of a USER cObject, will be removed in Gridelements v12.0
     */
    public function main(string $content = '', array $conf = []): string
    {
        // first we have to take care of possible flexform values containing additional information
        // that is not available via DB relations. It will be added as "virtual" key to the existing data Array
        // so that you can easily get the values with TypoScript
        $this->initPluginFlexForm();
        $this->getPluginFlexFormData();

        $this->languageAspect = GeneralUtility::makeInstance(Context::class)->getAspect('language');

        // now we have to find the children of this grid container regardless of their column
        // so we can get them within a single DB query instead of doing a query per column
        // but we will only fetch those columns that are used by the current grid layout
        if ($this->languageAspect->getLegacyOverlayType() && !empty($this->cObj->data['l18n_parent']) && !empty($this->cObj->data['sys_language_uid'])) {
            $element = $this->cObj->data['l18n_parent'];
        } else {
            $element = $this->cObj->data['uid'] ?? 0;
        }
        $pid = $this->cObj->data['pid'] ?? 0;
        $layout = $this->cObj->data['tx_gridelements_backend_layout'] ?? '';

        /** @var LayoutSetup $layoutSetup */
        $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class);
        $layoutSetup->init($pid, $conf);

        $availableColumns = $layoutSetup->getLayoutColumns($layout);
        $csvColumns = ltrim(str_replace('-2,-1', '', $availableColumns['CSV'] ?? ''), ',');
        $this->getChildren($element, $pid, $csvColumns);

        // and we have to determine the frontend setup related to the backend layout record which is assigned to this container
        $typoScriptSetup = $layoutSetup->getTypoScriptSetup($layout);

        // we need a sorting columns array to make sure that the columns are rendered in the order
        // that they have been created in the grid wizard but still be able to get all children
        // within just one SELECT query
        $sortColumns = explode(',', $csvColumns);

        $this->renderChildrenIntoParentColumns($typoScriptSetup, $sortColumns);
        unset($sortColumns);

        // if there are any columns available, we can go on with the render process
        if (!empty($this->cObj->data['tx_gridelements_view_columns'])) {
            $content = $this->renderColumnsIntoParentGrid($typoScriptSetup);
        }

        unset($availableColumns);
        unset($csvColumns);

        if (isset($typoScriptSetup['jsFooterInline']) || isset($typoScriptSetup['jsFooterInline.'])) {
            $jsFooterInline = isset($typoScriptSetup['jsFooterInline.']) ? $this->cObj->stdWrap(
                $typoScriptSetup['jsFooterInline'],
                $typoScriptSetup['jsFooterInline.']
            ) : $typoScriptSetup['jsFooterInline'];

            $this->getPageRenderer()->addJsFooterInlineCode('gridelements' . $element, $jsFooterInline);
            unset($typoScriptSetup['jsFooterInline']);
            unset($typoScriptSetup['jsFooterInline.']);
        }

        // finally we can unset the columns setup as well and apply stdWrap operations to the overall result
        // before returning the content
        unset($typoScriptSetup['columns.']);

        return !empty($typoScriptSetup) ? $this->cObj->stdWrap($content, $typoScriptSetup) : $content;
    }

    /**
     * Converts $this->cObj->data['pi_flexform'] from XML string to flexForm array.
     * @param string $field Field name to convert
     * @param array|null $child
     * @deprecated use the recommended static file based on DataProcessing instead of a USER cObject, will be removed in Gridelements v11.0
     */
    public function initPluginFlexForm(string $field = 'pi_flexform', array &$child = null)
    {
        $this->flexFormTools = GeneralUtility::makeInstance(FlexFormTools::class);
        // Converting flexform data into array:
        if (!empty($child)) {
            if (!is_array($child[$field]) && $child[$field]) {
                $child[$field . '_content'] = GeneralUtility::makeInstance(FlexFormService::class)->convertFlexFormContentToArray($child[$field]);
                if (!is_array($child[$field . '_content'])) {
                    $child[$field . '_content'] = [];
                }
                $child[$field] = GeneralUtility::xml2array($child[$field]);
                if (!is_array($child[$field])) {
                    $child[$field] = [];
                }
            }
        } elseif (!is_array($this->cObj->data[$field]) && $this->cObj->data[$field]) {
            $this->cObj->data[$field . '_content'] = GeneralUtility::makeInstance(FlexFormService::class)->convertFlexFormContentToArray($this->cObj->data[$field]);
            if (!is_array($this->cObj->data[$field . '_content'])) {
                $this->cObj->data[$field . '_content'] = [];
            }
            $this->cObj->data[$field] = GeneralUtility::xml2array($this->cObj->data[$field]);
            if (!is_array($this->cObj->data[$field])) {
                $this->cObj->data[$field] = [];
            }
        }
    }

    /**
     * fetches values from the grid flexform and assigns them to virtual fields in the data array
     * @param array $child
     * @deprecated use the recommended static file based on DataProcessing instead of a USER cObject, will be removed in Gridelements v12.0
     */
    public function getPluginFlexFormData(array &$child = [])
    {
        if (!empty($child)) {
            $cObjData = $child;
        } else {
            $cObjData = $this->cObj->data;
        }

        $pluginFlexForm = $cObjData['pi_flexform'] ?? '';

        if (is_array($pluginFlexForm) && !empty($pluginFlexForm['data'])) {
            foreach ($pluginFlexForm['data'] as $sheet => $data) {
                if (is_array($data)) {
                    foreach ($data as $value) {
                        if (is_array($value)) {
                            foreach ($value as $key => $val) {
                                $cObjData['flexform_' . $key] = $this->flexFormTools->getFlexFormValue(
                                    $pluginFlexForm,
                                    $key,
                                    $sheet
                                );
                            }
                        }
                    }
                }
            }
        }

        unset($pluginFlexForm);

        if (!empty($child)) {
            $child = $cObjData;
        } else {
            $this->cObj->data = $cObjData;
        }

        unset($cObjData);
    }

    /**
     * fetches all available children for a certain grid container
     *
     * @param int $element The uid of the grid container
     * @param int $pid
     * @param string $csvColumns A list of available column IDs
     * @throws DBALException
     * @deprecated use the recommended static file based on DataProcessing instead of a USER cObject, will be removed in Gridelements v12.0
     */
    public function getChildren(int $element = 0, int $pid = 0, string $csvColumns = '')
    {
        if (!$element || $csvColumns === '') {
            return;
        }
        $csvColumns = GeneralUtility::intExplode(',', $csvColumns);
        $queryBuilder = $this->getQueryBuilder();
        $where = $queryBuilder->expr()->andX(
            $queryBuilder->expr()->eq(
                'tx_gridelements_container',
                $queryBuilder->createNamedParameter($element, PDO::PARAM_INT)
            ),
            $queryBuilder->expr()->neq('colPos', $queryBuilder->createNamedParameter(-2, PDO::PARAM_INT)),
            $queryBuilder->expr()->eq(
                'pid',
                $queryBuilder->createNamedParameter($pid, PDO::PARAM_INT)
            ),
            $queryBuilder->expr()->in(
                'tx_gridelements_columns',
                $queryBuilder->createNamedParameter($csvColumns, Connection::PARAM_INT_ARRAY)
            )
        );
        $translationOverlay = [];
        $translationNoOverlay = [];

        if ($this->languageAspect->getContentId() > 0) {
            if ($this->languageAspect->getLegacyOverlayType()) {
                if (isset($this->cObj->data['_LOCALIZED_UID']) && $this->cObj->data['_LOCALIZED_UID'] !== 0) {
                    $element = (int)$this->cObj->data['_LOCALIZED_UID'];
                }
                if ($element) {
                    $translationOverlay = $queryBuilder->expr()->andX(
                        $queryBuilder->expr()->eq(
                            'tx_gridelements_container',
                            $queryBuilder->createNamedParameter($element, PDO::PARAM_INT)
                        ),
                        $queryBuilder->expr()->neq('colPos', $queryBuilder->createNamedParameter(-2, PDO::PARAM_INT)),
                        $queryBuilder->expr()->eq(
                            'pid',
                            $queryBuilder->createNamedParameter($pid, PDO::PARAM_INT)
                        ),
                        $queryBuilder->expr()->in(
                            'tx_gridelements_columns',
                            $queryBuilder->createNamedParameter($csvColumns, Connection::PARAM_INT_ARRAY)
                        ),
                        $queryBuilder->expr()->in(
                            'sys_language_uid',
                            $queryBuilder->createNamedParameter(
                                [-1, $this->languageAspect->getContentId()],
                                Connection::PARAM_INT_ARRAY
                            )
                        ),
                        $queryBuilder->expr()->eq(
                            'l18n_parent',
                            $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)
                        )
                    );
                }
            } else {
                $translationNoOverlay = $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->eq(
                        'tx_gridelements_container',
                        $queryBuilder->createNamedParameter($element, PDO::PARAM_INT)
                    ),
                    $queryBuilder->expr()->neq('colPos', $queryBuilder->createNamedParameter(-2, PDO::PARAM_INT)),
                    $queryBuilder->expr()->eq(
                        'pid',
                        $queryBuilder->createNamedParameter($pid, PDO::PARAM_INT)
                    ),
                    $queryBuilder->expr()->in(
                        'tx_gridelements_columns',
                        $queryBuilder->createNamedParameter($csvColumns, Connection::PARAM_INT_ARRAY)
                    ),
                    $queryBuilder->expr()->in(
                        'sys_language_uid',
                        $queryBuilder->createNamedParameter(
                            [-1, $this->languageAspect->getContentId()],
                            Connection::PARAM_INT_ARRAY
                        )
                    )
                );
            }
        }

        $children = $queryBuilder
            ->select('*')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->orX(
                    $where,
                    $translationOverlay,
                    $translationNoOverlay
                )
            )
            ->orderBy('sorting', 'ASC')
            ->execute();

        $this->cObj->data['tx_gridelements_view_children'] = [];
        while ($child = $children->fetch(PDO::FETCH_BOTH)) {
            // Versioning preview:
            $sorting = $child['sorting'] ?? '';
            $this->getTSFE()->sys_page->versionOL('tt_content', $child, true);

            // Language overlay:
            if (is_array($child)) {
                $child['sorting'] = $sorting;
                if ($this->languageAspect->getLegacyOverlayType()) {
                    $child = $this->getTSFE()->sys_page->getRecordOverlay(
                        'tt_content',
                        $child,
                        $this->languageAspect->getContentId(),
                        $this->languageAspect->getLegacyOverlayType()
                    );
                }
                if (!empty($child)) {
                    if ($child['CType'] === 'gridelements_pi1') {
                        $this->initPluginFlexForm('pi_flexform', $child);
                        $this->getPluginFlexFormData($child);
                    }
                    $this->cObj->data['tx_gridelements_view_children'][] = $child;
                    unset($child);
                }
            }
        }

        $compareFunction = function ($child_a, $child_b) {
            if (isset($child_a['sorting']) && isset($child_b['sorting'])) {
                if ($child_a['sorting'] > $child_b['sorting']) {
                    return 1;
                }
                if ($child_a['sorting'] === $child_b['sorting']) {
                    return 0;
                }
            }
            return -1;
        };

        usort($this->cObj->data['tx_gridelements_view_children'], $compareFunction);
    }

    /**
     * getter for queryBuilder
     *
     * @return QueryBuilder queryBuilder
     * @deprecated use the recommended static file based on DataProcessing instead of a USER cObject, will be removed in Gridelements v12.0
     */
    public function getQueryBuilder(): QueryBuilder
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');
        $queryBuilder->setRestrictions(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));
        return $queryBuilder;
    }

    /**
     * @return TypoScriptFrontendController
     * @deprecated use the recommended static file based on DataProcessing instead of a USER cObject, will be removed in Gridelements v12.0
     */
    public function getTSFE(): TypoScriptFrontendController
    {
        return $GLOBALS['TSFE'];
    }

    /**
     * renders the children of the grid container and
     * puts them into their respective columns
     *
     * @param array $typoScriptSetup
     * @param array $sortColumns An Array of column positions within the grid container in the order they got in the grid setup
     * @deprecated use the recommended static file based on DataProcessing instead of a USER cObject, will be removed in Gridelements v12.0
     */
    public function renderChildrenIntoParentColumns(array $typoScriptSetup = [], array $sortColumns = [])
    {
        // first we have to make a backup copy of the original data array
        // and we have to modify the depth counter to avoid stopping too early

        $currentParentGrid = $this->copyCurrentParentGrid();
        $columns = $this->getUsedColumns($sortColumns);
        $parentGridData = $this->getParentGridData($currentParentGrid['data'] ?? []);
        $parentGridData['tx_gridelements_view_columns'] = $columns;

        $counter = count($this->cObj->data['tx_gridelements_view_children'] ?? []);
        $parentRecordNumbers = [];
        $this->getTSFE()->cObjectDepthCounter += $counter;

        // each of the children will now be rendered separately and the output will be added to it's particular column
        $rawColumns = [];
        if ($counter) {
            foreach ($this->cObj->data['tx_gridelements_view_children'] as $child) {
                $rawColumns[$child['tx_gridelements_columns']][] = $child;
                $renderedChild = $child;
                $this->renderChildIntoParentColumn(
                    $columns,
                    $renderedChild,
                    $parentGridData,
                    $parentRecordNumbers,
                    $typoScriptSetup
                );
                $currentParentGrid['data']['tx_gridelements_view_child_' . $child['uid']] = $renderedChild;
                unset($renderedChild);
            }
            $currentParentGrid['data']['tx_gridelements_view_raw_columns'] = $rawColumns;
        }

        // now we can reset the depth counter and the data array so that the element will behave just as usual
        // it will just contain the additional tx_gridelements_view section with the prerendered elements
        // it is important to do this before any stdWrap functions are applied to the grid container
        // since they will depend on the original data
        $this->getTSFE()->cObjectDepthCounter -= $counter;

        $this->cObj->currentRecord = $currentParentGrid['record'] ?? [];
        $this->cObj->data = $currentParentGrid['data'] ?? [];
        $this->cObj->parentRecordNumber = $currentParentGrid['parentRecordNumber'] ?? 0;

        if (!empty($sortColumns)) {
            $this->cObj->data['tx_gridelements_view_columns'] = [];
            foreach ($sortColumns as $sortKey) {
                $sortKey = trim($sortKey);
                if (isset($parentGridData['tx_gridelements_view_columns'][$sortKey])) {
                    $this->cObj->data['tx_gridelements_view_columns'][$sortKey] = $parentGridData['tx_gridelements_view_columns'][$sortKey];
                }
            }
        }
        unset($parentGridData);
        unset($currentParentGrid);
    }

    /**
     * @return array
     * @deprecated use the recommended static file based on DataProcessing instead of a USER cObject, will be removed in Gridelements v12.0
     */
    public function copyCurrentParentGrid(): array
    {
        return [
            'record' => $this->cObj->currentRecord,
            'data' => $this->cObj->data,
            'parentRecordNumber' => $this->cObj->parentRecordNumber,
        ];
    }

    /**
     * @param array $sortColumns
     *
     * @return array
     * @deprecated use the recommended static file based on DataProcessing instead of a USER cObject, will be removed in Gridelements v12.0
     */
    public function getUsedColumns(array $sortColumns = []): array
    {
        $columns = [];
        // we need the array values as keys
        if (!empty($sortColumns)) {
            foreach ($sortColumns as $column_number) {
                $columns[$column_number] = '';
            }
        }
        return $columns;
    }

    /**
     * @param array $data
     *
     * @return array
     * @deprecated use the recommended static file based on DataProcessing instead of a USER cObject, will be removed in Gridelements v12.0
     */
    public function getParentGridData(array $data = []): array
    {
        // filter out existing superfluous keys to reduce memory load
        // String comparisons are way too expensive, so we go for unset within some loops
        if (!empty($data['tx_gridelements_view_children'])) {
            foreach ($data['tx_gridelements_view_children'] as $child) {
                unset($data['tx_gridelements_view_child_' . $child['uid']]);
            }
        }
        if (!empty($data['tx_gridelements_view_columns'])) {
            foreach ($data['tx_gridelements_view_columns'] as $column => $content) {
                unset($data['tx_gridelements_view_column_' . $column]);
            }
        }

        unset($data['tx_gridelements_view_children']);
        unset($data['tx_gridelements_view_columns']);

        // Set parent grid data for the first time
        $parentGridData = $this->setParentGridData($data);

        // Now we can remove any parentgrid_parentgrid_ keys
        if (!empty($parentGridData)) {
            foreach ($parentGridData as $key => $value) {
                unset($data[$key]);
            }
        }

        // Set parentgrid data for the first time
        return $this->setParentGridData($data);
    }

    /**
     * @param array $data
     *
     * @return array
     * @deprecated use the recommended static file based on DataProcessing instead of a USER cObject, will be removed in Gridelements v12.0
     */
    public function setParentGridData(array $data = []): array
    {
        $parentGridData = [];
        foreach ($data as $key => $value) {
            $parentGridData['parentgrid_' . $key] = $value;
        }
        return $parentGridData;
    }

    /**
     * renders the columns of the grid container and returns the actual content
     *
     * @param array $columns
     * @param array $child
     * @param array $parentGridData
     * @param array $parentRecordNumbers
     * @param array $typoScriptSetup
     * @deprecated use the recommended static file based on DataProcessing instead of a USER cObject, will be removed in Gridelements v12.0
     */
    public function renderChildIntoParentColumn(
        array $columns,
        array &$child,
        array &$parentGridData,
        array &$parentRecordNumbers,
        array $typoScriptSetup = []
    ) {
        $column_number = (int)($child['tx_gridelements_columns'] ?? 0);
        $columnKey = $column_number . '.';
        $columnSetupKey = isset($typoScriptSetup['columns.'][$columnKey]) ? $columnKey : 'default.';

        if (isset($child['uid']) && $child['uid'] <= 0) {
            return;
        }
        // update SYS_LASTCHANGED if necessary
        $this->cObj->lastChanged($child['tstamp'] ?? 0);
        $this->cObj->start(array_merge($child, $parentGridData), 'tt_content');

        if (isset($parentRecordNumbers[$columnKey])) {
            $parentRecordNumbers[$columnKey]++;
        } else {
            $parentRecordNumbers[$columnKey] = 1;
        }
        $this->cObj->parentRecordNumber = $parentRecordNumbers[$columnKey];

        // we render each child into the children key to provide them prerendered for usage with your own templating
        $child = $this->cObj->cObjGetSingle(
            $typoScriptSetup['columns.'][$columnSetupKey]['renderObj'] ?? '',
            $typoScriptSetup['columns.'][$columnSetupKey]['renderObj.'] ?? []
        );
        // then we assign the prerendered child to the appropriate column
        if (isset($columns[$column_number])) {
            $parentGridData['tx_gridelements_view_columns'][$column_number] .= $child;
        }
    }

    /**
     * renders the columns of the grid container and returns the actual content
     *
     * @param array $setup The adjusted setup of the grid container
     *
     * @return string $content The raw HTML output of the grid container before stdWrap functions will be applied to it
     * @deprecated use the recommended static file based on DataProcessing instead of a USER cObject, will be removed in Gridelements v12.0
     */
    public function renderColumnsIntoParentGrid(array $setup = []): string
    {
        if (empty($this->cObj->data['tx_gridelements_view_columns'])) {
            return '';
        }
        $content = '';
        foreach ($this->cObj->data['tx_gridelements_view_columns'] as $column => $columnContent) {
            // if there are any columns available, we have to determine the corresponding TS setup
            // and if there is none we are going to use the default setup
            $tempSetup = $setup['columns.'][$column . '.'] ?? $setup['columns.']['default.'];
            // now we just have to unset the renderObj
            // before applying the rest of the keys via the usual stdWrap operations
            unset($tempSetup['renderObj']);
            unset($tempSetup['renderObj.']);

            // we render each column into the column key to provide them prerendered for usage  with your own templating
            $this->cObj->data['tx_gridelements_view_column_' . $column] = empty($tempSetup)
                ? $columnContent
                : $this->cObj->stdWrap($columnContent, $tempSetup);
            $content .= $this->cObj->data['tx_gridelements_view_column_' . $column];
        }
        return $content;
    }

    /**
     * @return PageRenderer
     * @deprecated use the recommended static file based on DataProcessing instead of a USER cObject, will be removed in Gridelements v12.0
     */
    public function getPageRenderer(): PageRenderer
    {
        if ($this->pageRenderer === null) {
            $this->pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        }
        return $this->pageRenderer;
    }

    /**
     * renders a recursive pidList to reference content from a list of pages
     * @deprecated use the recommended static file based on DataProcessing instead of a USER cObject, will be removed in Gridelements v12.0
     */
    public function user_getTreeList()
    {
        $pidList = !empty($this->getTSFE()->register['tt_content_shortcut_recursive'])
            ? $this->cObj->getTreeList(
                $this->cObj->data['uid'],
                $this->getTSFE()->register['tt_content_shortcut_recursive']
            )
            : '';
        $this->getTSFE()->register['pidInList'] = trim($this->cObj->data['uid'] . ',' . $pidList, ',');
    }
}

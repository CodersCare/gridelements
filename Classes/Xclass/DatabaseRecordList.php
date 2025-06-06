<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Xclass;

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

use GridElementsTeam\Gridelements\Backend\LayoutSetup;
use GridElementsTeam\Gridelements\Event\ModifyRecordListElementDataEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\RecordList\Event\ModifyRecordListHeaderColumnsEvent;
use TYPO3\CMS\Backend\RecordList\Event\ModifyRecordListRecordActionsEvent;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendLayoutView;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

use function trim;

/**
 * Class for rendering of Web>List module
 * @internal This class is a specific TYPO3 Backend implementation and is not part of the TYPO3's Core API.
 */
class DatabaseRecordList extends \TYPO3\CMS\Backend\RecordList\DatabaseRecordList
{
    /**
     * @var array
     */
    protected array $currentIdList;

    /**
     * @var array
     */
    protected array $currentContainerIdList;

    /**
     * @var bool
     */
    protected bool $showMoveUp;

    /**
     * @var bool
     */
    protected bool $showMoveDown;

    /**
     * select fields for the query which fetches the translations of the current
     * record
     *
     * @var string
     */
    public string $selectFields;

    /**
     * @var int[]
     */
    protected array $expandedGridelements = [];

    /**
     * Count of record columns in view
     *
     * @var int
     */
    public int $totalColumnCount;

    /**
     * @var int
     */
    protected int $maxDepth = 10;

    /**
     * If set this is <td> CSS-classname for odd columns in addElement. Used with db_layout / pages section
     *
     * @var string
     */
    public $oddColumnsCssClass = '';

    /**
     * @var bool
     */
    protected bool $no_noWrap = false;

    /**
     * Keys are fieldnames and values are td-parameters to add in addElement(), please use $addElement_tdCSSClass for CSS-classes;
     *
     * @var array
     */
    public $addElement_tdParams = [];

    /**
     * Gridelements backend layouts to provide container column information
     *
     * @var LayoutSetup
     */
    protected LayoutSetup $gridelementsBackendLayouts;

    /**
     * Creates the listing of records from a single table
     *
     * @param string $table Table name
     * @return string HTML table with the listing for the record.
     * @throws \Doctrine\DBAL\Exception
     */
    public function getTable($table)
    {
        $backendLayout = $this->getBackendLayoutView()->getSelectedBackendLayout($this->id);
        $backendLayoutColumns = [];
        if (is_array($backendLayout['__items'])) {
            foreach ($backendLayout['__items'] as $backendLayoutItem) {
                $backendLayoutColumns[$backendLayoutItem['value']] = htmlspecialchars($backendLayoutItem['label']);
            }
        }
        // Finding the total amount of records on the page
        $queryBuilderTotalItems = $this->getQueryBuilder($table, ['*'], false, 0, 1);
        // @todo Switch to `resetOrderBy()` as soon as the QueryBuilder facade has that method on board.
        $queryBuilderTotalItems->resetQueryPart('orderBy');
        $totalItems = (int)$queryBuilderTotalItems
                ->count('*')
                ->executeQuery()
                ->fetchOne();
        if ($totalItems===0) {
            return '';
        }
        // Setting the limits for the amount of records to be displayed in the list and single table view.
        // Using the default value and overwriting with page TSconfig and TCA config. The limit is forced
        // to be in the range of 0 - 10000.

        // default 100 for single table view
        $itemsLimitSingleTable = MathUtility::forceIntegerInRange(
                (int)(
                        $GLOBALS['TCA'][$table]['interface']['maxSingleDBListItems'] ??
                        $this->modTSconfig['itemsLimitSingleTable'] ??
                        100
                ),
                0,
                10000
        );

        // default 20 for list view
        $itemsLimitPerTable = MathUtility::forceIntegerInRange(
                (int)(
                        $GLOBALS['TCA'][$table]['interface']['maxDBListItems'] ??
                        $this->modTSconfig['itemsLimitPerTable'] ??
                        20
                ),
                0,
                10000
        );

        // Set limit depending on the view (single table vs. default)
        $itemsPerPage = $this->table ? $itemsLimitSingleTable:$itemsLimitPerTable;

        // Set limit defined by calling code
        if ($this->showLimit) {
            $itemsPerPage = $this->showLimit;
        }

        // Set limit from search
        if ($this->searchString) {
            $itemsPerPage = $totalItems;
        }

        // Init
        $titleCol = $GLOBALS['TCA'][$table]['ctrl']['label'];
        $l10nEnabled = BackendUtility::isTableLocalizable($table);

        $this->fieldArray = $this->getColumnsToRender($table, true);
        // Creating the list of fields to include in the SQL query
        $selectFields = $this->getFieldsToSelect($table, $this->fieldArray);
        if ($table==='tt_content') {
            $selectFields[] = 'tx_gridelements_backend_layout';
            $this->gridelementsBackendLayouts = GeneralUtility::makeInstance(LayoutSetup::class)->init($this->id);
        }
        $this->selectFields = implode(',', $selectFields);

        $firstElement = ($this->page - 1) * $itemsPerPage;
        if ($firstElement > 2 && $itemsPerPage > 0) {
            // Get the two previous rows for sorting if displaying page > 1
            $firstElement -= 2;
            $itemsPerPage += 2;
            $queryBuilder = $this->getQueryBuilder($table, $selectFields, true, $firstElement, $itemsPerPage);
            $firstElement += 2;
            $itemsPerPage -= 2;
        } else {
            $queryBuilder = $this->getQueryBuilder($table, $selectFields, true, $firstElement, $itemsPerPage);
        }

        $queryResult = $queryBuilder->executeQuery();
        $columnsOutput = '';
        $onlyShowRecordsInSingleTableMode = $this->listOnlyInSingleTableMode && !$this->table;
        // Fetch records only if not in single table mode
        if ($onlyShowRecordsInSingleTableMode) {
            $dbCount = $totalItems;
        } elseif ($firstElement + $itemsPerPage <= $totalItems) {
            $dbCount = $itemsPerPage + 2;
        } else {
            $dbCount = $totalItems - $firstElement + 2;
        }
        // If any records was selected, render the list:
        if ($dbCount===0) {
            return '';
        }

        // Get configuration of collapsed tables from user uc
        $lang = $this->getLanguageService();

        $tableIdentifier = $table;
        // Use a custom table title for translated pages
        if ($table==='pages' && $this->showOnlyTranslatedRecords) {
            // pages records in list module are split into two own sections, one for pages with
            // sys_language_uid = 0 "Page" and an own section for sys_language_uid > 0 "Page Translation".
            // This if sets the different title for the page translation case and a unique table identifier
            // which is used in DOM as id.
            $tableTitle = htmlspecialchars(
                    $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:pageTranslation')
            );
            $tableIdentifier = 'pages_translated';
        } else {
            $tableTitle = htmlspecialchars($lang->sL($GLOBALS['TCA'][$table]['ctrl']['title']));
            if ($tableTitle==='') {
                $tableTitle = $table;
            }
        }

        $backendUser = $this->getBackendUserAuthentication();
        $tableCollapsed = (bool)($this->moduleData?->get('collapsedTables')[$tableIdentifier] ?? false);

        // Header line is drawn
        $theData = [];
        if ($this->disableSingleTableView) {
            $theData[$titleCol] = $tableTitle . ' (<span class="t3js-table-total-items">' . $totalItems . '</span>)';
        } else {
            $icon = $this->table // @todo separate table header from contract/expand link
                    ? $this->iconFactory
                            ->getIcon('actions-view-table-collapse', Icon::SIZE_SMALL)
                            ->setTitle(
                                    $lang->sL(
                                            'LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf:contractView'
                                    )
                            )
                            ->render()
                    :$this->iconFactory
                            ->getIcon('actions-view-table-expand', Icon::SIZE_SMALL)
                            ->setTitle(
                                    $lang->sL(
                                            'LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf:expandView'
                                    )
                            )
                            ->render();
            $theData[$titleCol] = $this->linkWrapTable(
                    $table,
                    $tableTitle . ' (<span class="t3js-table-total-items">' . $totalItems . '</span>) ' . $icon
            );
        }
        $tableActions = '';
        $tableHeader = $theData[$titleCol];
        if (!$onlyShowRecordsInSingleTableMode) {
            // Add the "new record" button
            $tableActions .= $this->createActionButtonNewRecord($table);
            // Render collapse button if in multi table mode
            if (!$this->table) {
                $title = sprintf(
                        htmlspecialchars(
                                $lang->sL(
                                        'LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf:collapseExpandTable'
                                )
                        ),
                        $tableTitle
                );
                $icon = '<span class="collapseIcon">' . $this->iconFactory->getIcon(
                                ($tableCollapsed ? 'actions-view-list-expand':'actions-view-list-collapse'),
                                Icon::SIZE_SMALL
                        )->render() . '</span>';
                $tableActions .= '<button type="button"'
                        . ' class="btn btn-default btn-sm pull-right t3js-toggle-recordlist"'
                        . ' title="' . $title . '"'
                        . ' aria-label="' . $title . '"'
                        . ' aria-expanded="' . ($tableCollapsed ? 'false':'true') . '"'
                        . ' data-table="' . htmlspecialchars($tableIdentifier) . '"'
                        . ' data-bs-toggle="collapse"'
                        . ' data-bs-target="#recordlist-' . htmlspecialchars($tableIdentifier) . '">'
                        . $icon
                        . '</button>';
            }
            // Show the select box
            $tableActions .= $this->createActionButtonColumnSelector($table) ?? '';
            // Create the Download button
            $tableActions .= $this->createActionButtonDownload($table, $totalItems) ?? '';
            // Render collapse button if in multi table mode
            $tableActions .= $this->createActionButtonCollapse($table) ?? '';
        }
        // Check if gridelements containers are expanded or collapsed
        if ($table==='tt_content') {
            $this->expandedGridelements = [];
            $backendUser = $this->getBackendUserAuthentication();
            if (!empty($backendUser->uc['moduleData']['list']['gridelementsExpanded'])
                    && is_array($backendUser->uc['moduleData']['list']['gridelementsExpanded'])) {
                $this->expandedGridelements = $backendUser->uc['moduleData']['list']['gridelementsExpanded'];
            }
            $expandOverride = $this->request->getQueryParams()['gridelementsExpand'] ?? null;
            if (is_array($expandOverride)) {
                foreach ($expandOverride as $expandContainer => $expandValue) {
                    if ($expandValue) {
                        $this->expandedGridelements[$expandContainer] = 1;
                    } else {
                        unset($this->expandedGridelements[$expandContainer]);
                    }
                }
                if (!isset($backendUser->uc['moduleData']['list'])) {
                    $backendUser->uc['moduleData']['list'] = [];
                }
                $backendUser->uc['moduleData']['list']['gridelementsExpanded'] = $this->expandedGridelements;
                // Save modified user uc
                $backendUser->writeUC();
                $returnUrl = GeneralUtility::sanitizeLocalUrl(
                        $this->request->getParsedBody()['returnUrl'] ?? $this->request->getQueryParams(
                )['returnUrl'] ?? ''
                );
                if ($returnUrl!=='') {
                    $responseFactory = GeneralUtility::makeInstance(
                            ResponseFactoryInterface::class
                    );

                    $response = $responseFactory
                            ->createResponse()
                            ->withAddedHeader('location', $returnUrl);

                    throw new PropagateResponseException($response);
                }
            }
        }
        $currentIdList = [];
        // Render table rows only if in multi table view or if in single table view
        $rowOutput = '';
        if (!$onlyShowRecordsInSingleTableMode || $this->table) {
            // Fixing an order table for sortby tables
            $this->currentTable = [];
            $allowManualSorting = ($GLOBALS['TCA'][$table]['ctrl']['sortby'] ?? false) && !$this->sortField;
            $prevUid = 0;
            $prevPrevUid = 0;
            // Get first two rows and initialize prevPrevUid and prevUid if on page > 1
            if ($firstElement > 2 && $itemsPerPage > 0) {
                $row = $queryResult->fetchAssociative();
                $prevPrevUid = -((int)$row['uid']);
                $row = $queryResult->fetchAssociative();
                $prevUid = $row['uid'];
            }
            $accRows = [];
            // Accumulate rows here
            while ($row = $queryResult->fetchAssociative()) {
                if (!$this->isRowListingConditionFulfilled($table, $row)) {
                    continue;
                }
                // In offline workspace, look for alternative record
                BackendUtility::workspaceOL($table, $row, $backendUser->workspace, true);
                if (is_array($row)) {
                    $accRows[] = $row;
                    $currentIdList[] = $row['uid'];
                    if ($allowManualSorting) {
                        if ($prevUid) {
                            $this->currentTable['prev'][$row['uid']] = $prevPrevUid;
                            $this->currentTable['next'][$prevUid] = '-' . $row['uid'];
                            $this->currentTable['prevUid'][$row['uid']] = $prevUid;
                        }
                        $prevPrevUid = isset($this->currentTable['prev'][$row['uid']]) ? -$prevUid:$row['pid'];
                        $prevUid = $row['uid'];
                    }
                }
            }
            // Render items:
            $this->CBnames = [];
            $this->duplicateStack = [];
            $cc = 0;
            $lastColPos = null;

            // If no search happened it means that the selected
            // records are either default or All language and here we will not select translations
            // which point to the main record:
            $listTranslatedRecords = $l10nEnabled && $this->searchString==='' && !($this->hideTranslations==='*' || GeneralUtility::inList(
                                    $this->hideTranslations,
                                    $table
                            ));
            foreach ($accRows as $key => $row) {
                // Render item row if counter < limit
                if ($cc < $itemsPerPage) {
                    $cc++;
                    // Reset translations
                    $translations = [];
                    // Initialize with FALSE which causes the localization panel to not be displayed as
                    // the record is already localized, in free mode or has sys_language_uid -1 set.
                    // Only set to TRUE if TranslationConfigurationProvider::translationInfo() returns
                    // an array indicating the record can be translated.
                    $translationEnabled = false;
                    // Guard clause so we can quickly return if a record is localized to "all languages"
                    // It should only be possible to localize a record off default (uid 0)
                    if ($l10nEnabled && ($row[$GLOBALS['TCA'][$table]['ctrl']['languageField'] ?? null] ?? false)!==-1) {
                        $translationsRaw = $this->translateTools->translationInfo(
                                $table,
                                $row['uid'],
                                0,
                                $row,
                                $selectFields
                        );
                        if (is_array($translationsRaw)) {
                            $translationEnabled = true;
                            $translations = $translationsRaw['translations'] ?? [];
                        }
                    }
                    if ($table==='tt_content' && isset($row['colPos']) && ($row['colPos']!==$lastColPos)) {
                        $lastColPos = $row['colPos'];
                        $this->showMoveUp = false;
                        $rowOutput .= '<tr>
                                    <td colspan="2"></td>
                                    <td colspan="' . (count($this->fieldArray) - 1 + $this->maxDepth) . '" style="padding:5px;">
                                        <br />
                                        <strong>'
                                . $this->getLanguageService()->sL(
                                        'LLL:EXT:gridelements/Resources/Private/Language/locallang_db.xlf:list.columnName'
                                )
                                . ' ' . ($backendLayoutColumns[$row['colPos']] ?:(int)$row['colPos']) . '</strong>
                                    </td>
                                </tr>';
                    } else {
                        $this->showMoveUp = true;
                    }
                    $this->showMoveDown = !isset($row['colPos']) || !isset($accRows[$key + 1])
                            || $row['colPos']==$accRows[$key + 1]['colPos'];

                    if ($table==='tt_content') {
                        $rowOutput .= $this->renderListRowGridelements(
                                $table,
                                $row,
                                0,
                                $translations,
                                $translationEnabled
                        );
                    } else {
                        $rowOutput .= $this->renderListRow($table, $row, 0, $translations, $translationEnabled);
                    }
                    if ($listTranslatedRecords) {
                        foreach ($translations ?? [] as $lRow) {
                            if (!$this->isRowListingConditionFulfilled($table, $lRow)) {
                                continue;
                            }
                            // In offline workspace, look for alternative record:
                            BackendUtility::workspaceOL($table, $lRow, $backendUser->workspace, true);
                            if (is_array($lRow) && $backendUser->checkLanguageAccess(
                                            $lRow[$GLOBALS['TCA'][$table]['ctrl']['languageField']]
                                    )) {
                                $currentIdList[] = $lRow['uid'];
                                if ($table==='tt_content') {
                                    $rowOutput .= $this->renderListRowGridelements($table, $lRow, 0, [], false);
                                } else {
                                    $rowOutput .= $this->renderListRow($table, $lRow, 0, [], false);
                                }
                            }
                        }
                    }
                }
            }
            // Record navigation is added to the beginning and end of the table if in single table mode
            if ($this->table) {
                $pagination = $this->renderListNavigation($this->table, $totalItems, $itemsPerPage);
                $rowOutput = $pagination . $rowOutput . $pagination;
            } elseif ($totalItems > $itemsLimitPerTable) {
                // Show that there are more records than shown
                $rowOutput .= '
                    <tr data-multi-record-selection-element="true">
                        <td colspan="' . (count($this->fieldArray)) . '">
                            <a href="' . htmlspecialchars(
                                $this->listURL() . '&table=' . rawurlencode($tableIdentifier)
                        ) . '" class="btn btn-sm btn-default">
                                ' . $this->iconFactory->getIcon('actions-caret-down', Icon::SIZE_SMALL)->render() . '
                                ' . $this->getLanguageService()->sL(
                                'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.expandTable'
                        ) . '
                            </a>
                        </td>
                    </tr>';
            }
            // The header row for the table is now created
            if ($table==='tt_content') {
                $columnsOutput = $this->renderListHeaderGridelements($table, $currentIdList);
            } else {
                $columnsOutput = $this->renderListHeader($table, $currentIdList);
            }
        }

        // Initialize multi record selection actions
        $multiRecordSelectionActions = '';
        if ($this->noControlPanels===false) {
            $multiRecordSelectionActions = '
                <div class="recordlist-heading-row t3js-multi-record-selection-actions hidden">
                    <div class="recordlist-heading-title">
                        <strong>' . htmlspecialchars(
                            $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.selection')
                    ) . '</strong>
                    </div>
                    <div class="recordlist-heading-actions">
                        ' . $this->renderMultiRecordSelectionActions($table, $currentIdList) . '
                    </div>
                </div>
            ';
        }

        $recordListMessages = '';
        $recordlistMessageEntries = [];
        if ($backendUser->workspace > 0 && ExtensionManagementUtility::isLoaded(
                        'workspaces'
                ) && !BackendUtility::isTableWorkspaceEnabled($table)) {
            // In case the table is not editable in workspace inform the user about the missing actions
            if ($backendUser->workspaceAllowsLiveEditingInTable($table)) {
                $recordlistMessageEntries[] = [
                        'message' => $lang->sL(
                                'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.editingLiveRecordsWarning'
                        ),
                        'severity' => ContextualFeedbackSeverity::WARNING,
                ];
            } else {
                $recordlistMessageEntries[] = [
                        'message' => $lang->sL(
                                'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.notEditableInWorkspace'
                        ),
                        'severity' => ContextualFeedbackSeverity::INFO,
                ];
            }
        }

        foreach ($recordlistMessageEntries as $messageEntry) {
            $recordListMessages .= '<div class="alert alert-' . $messageEntry['severity']->getCssClass() . '">';
            $recordListMessages .= $this->iconFactory->getIcon(
                    $messageEntry['severity']->getIconIdentifier(),
                    Icon::SIZE_SMALL
            )->render();
            $recordListMessages .= ' ';
            $recordListMessages .= htmlspecialchars($messageEntry['message'], ENT_QUOTES | ENT_HTML5);
            $recordListMessages .= '</div>';
        }

        $collapseClass = $tableCollapsed && !$this->table ? 'collapse':'collapse show';
        $dataState = $tableCollapsed && !$this->table ? 'collapsed':'expanded';
        return '
            <div class="recordlist" id="t3-table-' . htmlspecialchars(
                        $tableIdentifier
                ) . '" data-multi-record-selection-identifier="t3-table-' . htmlspecialchars($tableIdentifier) . '">
                <form action="' . htmlspecialchars($this->listURL()) . '#t3-table-' . htmlspecialchars(
                        $tableIdentifier
                ) . '" method="post" name="list-table-form-' . htmlspecialchars($tableIdentifier) . '">
                    <input type="hidden" name="cmd_table" value="' . htmlspecialchars($tableIdentifier) . '" />
                    <input type="hidden" name="cmd" />
                    <div class="recordlist-heading ' . ($multiRecordSelectionActions!=='' ? 'multi-record-selection-panel':'') . '">
                        <div class="recordlist-heading-row">
                            <div class="recordlist-heading-title">' . $tableHeader . '</div>
                            <div class="recordlist-heading-actions">' . $tableActions . '</div>
                        </div>
                        ' . $multiRecordSelectionActions . '
                    </div>
                    ' . $recordListMessages . '
                    <div class="' . $collapseClass . '" data-state="' . $dataState . '" id="recordlist-' . htmlspecialchars(
                        $tableIdentifier
                ) . '">
                        <div class="table-fit">
                            <table data-table="' . htmlspecialchars($tableIdentifier) . '" class="table table-striped table-hover">
                                <thead>
                                    ' . $columnsOutput . '
                                </thead>
                                <tbody data-multi-record-selection-row-selection="true">
                                    ' . $rowOutput . '
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>
            </div>
        ';
    }

    /**
     * Rendering the header row for a table
     *
     * @param string $table Table name
     * @param int[] $currentIdList Array of the currently displayed uids of the table
     * @return string Header table row
     * @throws \UnexpectedValueException
     * @internal
     * @see getTable()
     */
    public function renderListHeaderGridelements($table, $currentIdList)
    {
        $tsConfig = BackendUtility::getPagesTSconfig($this->id)['TCEFORM.'][$table . '.'] ?? null;
        $tsConfigOfTable = is_array($tsConfig) ? $tsConfig:null;

        $lang = $this->getLanguageService();
        // Init:
        $theData = [];
        // Traverse the fields:
        foreach ($this->fieldArray as $fCol) {
            // Calculate users permissions to edit records in the table:
            if ($table==='pages') {
                $permsEdit = $this->calcPerms->editPagePermissionIsGranted();
            } else {
                $permsEdit = $this->calcPerms->editContentPermissionIsGranted();
            }

            $permsEdit = $permsEdit && $this->overlayEditLockPermissions($table);
            switch ((string)$fCol) {
                case '_SELECTOR_':
                    if ($table!=='pages' || !$this->showOnlyTranslatedRecords) {
                        // Add checkbox actions for all tables except the special page translations table
                        $theData[$fCol] = $this->renderCheckboxActions();
                    } else {
                        // Remove "_SELECTOR_", which is always the first item, from the field list
                        array_splice($this->fieldArray, 0, 1);
                    }
                    break;
                case 'icon':
                    // In case no checkboxes are rendered (page translations or disabled) add the icon
                    // column, otherwise the selector column is using "colspan=2"
                    if (!in_array('_SELECTOR_', $this->fieldArray, true)
                            || ($table==='pages' && $this->showOnlyTranslatedRecords)
                    ) {
                        $theData[$fCol] = '';
                    }
                    break;
                case '_CONTROL_':
                    $theData[$fCol] = '<i class="hidden">' . htmlspecialchars(
                                    $lang->sL(
                                            'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels._CONTROL_'
                                    )
                            ) . '</i>';
                    // In single table view, add button to edit displayed fields of marked / listed records
                    if ($this->table && $permsEdit && is_array($currentIdList) && $this->isEditable($table)) {
                        $label = htmlspecialchars(
                                $lang->sL(
                                        'LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf:editShownColumns'
                                )
                        );
                        $theData[$fCol] = '<button type="button"'
                                . ' class="btn btn-default t3js-record-edit-multiple"'
                                . ' title="' . $label . '"'
                                . ' aria-label="' . $label . '"'
                                . ' data-return-url="' . htmlspecialchars($this->listURL()) . '"'
                                . ' data-columns-only="' . htmlspecialchars(implode(',', $this->fieldArray)) . '">'
                                . $this->iconFactory->getIcon('actions-document-open', Icon::SIZE_SMALL)->render()
                                . '</button>';
                    }
                    break;
                case '_PATH_':
                    // Path
                    $theData[$fCol] = '<i>' . htmlspecialchars(
                                    $lang->sL(
                                            'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels._PATH_'
                                    )
                            ) . '</i>';
                    break;
                case '_REF_':
                    // References
                    $theData[$fCol] = '<i>' . htmlspecialchars(
                                    $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels._REF_')
                            ) . '</i>';
                    break;
                case '_LOCALIZATION_':
                    // Show language of record
                    $theData[$fCol] = '<i>' . htmlspecialchars(
                                    $lang->sL(
                                            'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels._LOCALIZATION_'
                                    )
                            ) . '</i>';
                    break;
                case '_LOCALIZATION_b':
                    // Show translation options
                    if ($this->showLocalizeColumn[$table] ?? false) {
                        $theData[$fCol] = '<i>' . htmlspecialchars(
                                        $lang->sL(
                                                'LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf:Localize'
                                        )
                                ) . '</i>';
                    }
                    break;
                default:
                    // Regular fields header
                    $theData[$fCol] = '';

                    // Check if $fCol is really a field and get the label and remove the colons at the end
                    $sortLabel = BackendUtility::getItemLabel($table, $fCol);
                    if ($sortLabel!==null) {
                        // Field label
                        $fieldTSConfig = [];
                        if (isset($tsConfigOfTable[$fCol . '.'])
                                && is_array($tsConfigOfTable[$fCol . '.'])
                        ) {
                            $fieldTSConfig = $tsConfigOfTable[$fCol . '.'];
                        }
                        $sortLabel = $lang->translateLabel(
                                $fieldTSConfig['label.'] ?? [],
                                $fieldTSConfig['label'] ?? $sortLabel
                        );
                        $sortLabel = htmlspecialchars(rtrim(trim($sortLabel), ':'));
                    } elseif ($specialLabel = $lang->sL(
                            'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.' . $fCol
                    )) {
                        // Special label exists for this field (Probably a management field, e.g. sorting)
                        $sortLabel = htmlspecialchars($specialLabel);
                    } else {
                        // No TCA field, only output the $fCol variable with square brackets []
                        $sortLabel = htmlspecialchars($fCol);
                        $sortLabel = '<i>[' . rtrim(trim($sortLabel), ':') . ']</i>';
                    }

                    if ($this->table && is_array($currentIdList)) {
                        // If the numeric clipboard pads are selected, show duplicate sorting link:
                        if ($this->noControlPanels===false
                                && $this->isClipboardFunctionalityEnabled($table)
                                && $this->clipObj->current!=='normal'
                        ) {
                            $theData[$fCol] .= '<a class="btn btn-default" href="' . htmlspecialchars(
                                            $this->listURL() . '&duplicateField=' . $fCol
                                    )
                                    . '" title="' . htmlspecialchars(
                                            $lang->sL(
                                                    'LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf:clip_duplicates'
                                            )
                                    ) . '">'
                                    . $this->iconFactory->getIcon(
                                            'actions-document-duplicates-select',
                                            Icon::SIZE_SMALL
                                    )->render() . '</a>';
                        }
                        // If the table can be edited, add link for editing THIS field for all
                        // listed records:
                        if ($this->isEditable(
                                        $table
                                ) && $permsEdit && ($GLOBALS['TCA'][$table]['columns'][$fCol] ?? false)) {
                            $iTitle = sprintf(
                                    $lang->sL(
                                            'LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf:editThisColumn'
                                    ),
                                    $sortLabel
                            );
                            $theData[$fCol] .= '<button type="button"'
                                    . ' class="btn btn-default t3js-record-edit-multiple"'
                                    . ' title="' . htmlspecialchars($iTitle) . '"'
                                    . ' aria-label="' . htmlspecialchars($iTitle) . '"'
                                    . ' data-return-url="' . htmlspecialchars($this->listURL()) . '"'
                                    . ' data-columns-only="' . htmlspecialchars($fCol) . '">'
                                    . $this->iconFactory->getIcon('actions-document-open', Icon::SIZE_SMALL)->render()
                                    . '</button>';
                        }
                        if (strlen($theData[$fCol]) > 0) {
                            $theData[$fCol] = '<div class="btn-group">' . $theData[$fCol] . '</div> ';
                        }
                    }
                    $theData[$fCol] .= $this->addSortLink($sortLabel, $fCol, $table);
            }
        }

        $this->totalColumnCount = 10 + count($theData);
        $headerOutput = '<colgroup>';
        for ($i = -10, $iMax = count($theData); $i < $iMax; $i++) {
            if ($i < -1) {
                $headerOutput .= '<col class="col-icon" width="40" />';
            } else {
                $headerOutput .= '<col width="auto" />';
            }
        }
        $headerOutput .= '</colgroup>';

        $event = $this->eventDispatcher->dispatch(
                new ModifyRecordListHeaderColumnsEvent($theData, $table, $currentIdList, $this)
        );

        // Create and return header table row:
        return $headerOutput . '<thead>' . $this->addElementGridelements(
                        $event->getColumns(),
                        GeneralUtility::implodeAttributes(
                                $event->getHeaderAttributes(),
                                true
                        ),
                        'th',
                        0,
                        $table
                ) . '</thead>';
    }

    /**
     * Creates a page browser for tables with many records
     *
     * @return string Navigation HTML
     */
    protected function renderListNavigation(string $table, int $totalItems, int $itemsPerPage): string
    {
        $currentPage = $this->page;
        $paginationColumns = count($this->fieldArray);
        $totalPages = (int)ceil($totalItems / $itemsPerPage);
        // Show page selector if not all records fit into one page
        if ($totalPages <= 1) {
            return '';
        }
        if ($totalItems > $currentPage * $itemsPerPage) {
            $lastElementNumber = $currentPage * $itemsPerPage;
        } else {
            $lastElementNumber = $totalItems;
        }
        $view = $this->backendViewFactory->create($this->request);
        return $view->assignMultiple([
                                             'currentUrl' => $this->listURL('', $table, 'pointer'),
                                             'currentPage' => $currentPage,
                                             'totalPages' => $totalPages,
                                             'firstElement' => ((($currentPage - 1) * $itemsPerPage) + 1),
                                             'lastElement' => $lastElementNumber,
                                             'colspan' => $paginationColumns,
                                     ])
                ->render('ListNavigation');
    }

    /*********************************
     *
     * Rendering of various elements
     *
     *********************************/

    /**
     * Rendering a single row for the list
     *
     * @param string $table Table name
     * @param mixed[] $row Current record
     * @param int $indent Indent from left.
     * @param array $translations Array of already existing translations for the current record
     * @param bool $translationEnabled Whether the record can be translated
     * @param int $level Level of nesting within grid containers
     * @param int $triggerContainer
     * @param string $expanded
     * @return string Table row for the element
     * @internal
     * @see getTable()
     */
    public function renderListRowGridelements(
            $table,
            array $row,
            int $indent,
            array $translations,
            bool $translationEnabled,
            int $level = 0,
            int $triggerContainer = 0,
            string $expanded = ''
    ): string {
        $titleCol = $GLOBALS['TCA'][$table]['ctrl']['label'] ?? '';
        $languageService = $this->getLanguageService();
        $rowOutput = '';
        $id_orig = $this->id;
        // If in search mode, make sure the preview will show the correct page
        if ((string)$this->searchString!=='') {
            $this->id = $row['pid'];
        }

        $tagAttributes = [
                'class' => [],
                'data-table' => $table,
                'title' => 'id=' . $row['uid'],
        ];

        // Add active class to record of current link
        if (
                isset($this->currentLink['tableNames'])
                && (int)$this->currentLink['uid']===(int)$row['uid']
                && GeneralUtility::inList($this->currentLink['tableNames'], $table)
        ) {
            $tagAttributes['class'][] = 'active';
        }
        // Overriding with versions background color if any:
        if (!empty($row['_CSSCLASS'])) {
            $tagAttributes['class'] = [$row['_CSSCLASS']];
        }

        $tagAttributes['class'][] = 't3js-entity';

        // Preparing and getting the data-array
        $theData = [];
        $deletePlaceholderClass = '';
        foreach ($this->fieldArray as $fCol) {
            if ($fCol===$titleCol) {
                $recTitle = BackendUtility::getRecordTitle($table, $row, false, true);
                $warning = '';
                // If the record is edit-locked	by another user, we will show a little warning sign:
                $lockInfo = BackendUtility::isRecordLocked($table, $row['uid']);
                if ($lockInfo) {
                    $warning = '<span tabindex="0"'
                            . ' title="' . htmlspecialchars($lockInfo['msg']) . '"'
                            . ' aria-label="' . htmlspecialchars($lockInfo['msg']) . '">'
                            . $this->iconFactory->getIcon(
                                    'status-user-backend',
                                    Icon::SIZE_SMALL,
                                    'overlay-edit'
                            )->render()
                            . '</span>';
                }
                if ($this->isRecordDeletePlaceholder($row)) {
                    // Delete placeholder records do not link to formEngine edit and are rendered strike-through
                    $deletePlaceholderClass = ' deletePlaceholder';
                    $theData[$fCol] = $theData['__label'] =
                            $warning
                            . '<span title="' . htmlspecialchars(
                                    $languageService->sL(
                                            'LLL:EXT:backend/Resources/Private/Language/locallang.xlf:row.deletePlaceholder.title'
                                    )
                            ) . '">'
                            . htmlspecialchars($recTitle)
                            . '</span>';
                } else {
                    $theData[$fCol] = $theData['__label'] = $warning . $this->linkWrapItems(
                                    $table,
                                    $row['uid'],
                                    $recTitle,
                                    $row
                            );
                }
            } elseif ($fCol==='pid') {
                $theData[$fCol] = $row[$fCol];
            } elseif ($fCol==='_SELECTOR_') {
                if ($table!=='pages' || !$this->showOnlyTranslatedRecords) {
                    // Add checkbox for all tables except the special page translations table
                    $theData[$fCol] = $this->makeCheckbox($table, $row);
                } else {
                    // Remove "_SELECTOR_", which is always the first item, from the field list
                    array_splice($this->fieldArray, 0, 1);
                }
            } elseif ($fCol==='icon') {
                $icon = $this->iconFactory
                        ->getIconForRecord($table, $row, Icon::SIZE_SMALL)
                        ->setTitle(BackendUtility::getRecordIconAltText($row, $table, false))
                        ->render();
                $theData[$fCol] = ''
                        . ($indent ? '<span class="indent indent-inline-block" style="--indent-level: ' . $indent . '"></span> ':'')
                        . (($this->clickMenuEnabled && !$this->isRecordDeletePlaceholder(
                                        $row
                                )) ? BackendUtility::wrapClickMenuOnIcon($icon, $table, $row['uid']):$icon);
            } elseif ($fCol==='_PATH_') {
                $theData[$fCol] = $this->recPath($row['pid']);
            } elseif ($fCol==='_REF_') {
                $theData[$fCol] = $this->generateReferenceToolTip($table, $row['uid']);
            } elseif ($fCol==='_CONTROL_') {
                $theData[$fCol] = $this->makeControl($table, $row);
            } elseif ($fCol==='_LOCALIZATION_') {
                // Language flag an title
                $theData[$fCol] = $this->languageFlag($table, $row);
                // Localize record
                $localizationPanel = $translationEnabled ? $this->makeLocalizationPanel($table, $row, $translations):'';
                if ($localizationPanel!=='') {
                    $theData['_LOCALIZATION_b'] = '<div class="btn-group">' . $localizationPanel . '</div>';
                    $this->showLocalizeColumn[$table] = true;
                }
            } elseif ($fCol!=='_LOCALIZATION_b') {
                // default for all other columns, except "_LOCALIZATION_b"
                $pageId = $table==='pages' ? $row['uid']:$row['pid'];
                $tmpProc = BackendUtility::getProcessedValueExtra(
                        $table,
                        $fCol,
                        $row[$fCol],
                        100,
                        $row['uid'],
                        true,
                        $pageId,
                        $row
                );
                $theData[$fCol] = $this->linkUrlMail(htmlspecialchars((string)$tmpProc), (string)($row[$fCol] ?? ''));
            }
        }
        // Reset the ID if it was overwritten
        if ((string)$this->searchString!=='') {
            $this->id = $id_orig;
        }
        // Add classes to table cells
        $this->addElement_tdCssClass['_SELECTOR_'] = 'col-checkbox';
        $this->addElement_tdCssClass[$titleCol] = 'col-title col-responsive' . $deletePlaceholderClass;
        $this->addElement_tdCssClass['__label'] = $this->addElement_tdCssClass[$titleCol];
        $this->addElement_tdCssClass['icon'] = 'col-icon';
        $this->addElement_tdCssClass['_CONTROL_'] = 'col-control';
        $this->addElement_tdCssClass['_PATH_'] = 'col-path';
        $this->addElement_tdCssClass['_LOCALIZATION_'] = 'col-localizationa';
        $this->addElement_tdCssClass['_LOCALIZATION_b'] = 'col-localizationb';

        $theData = GeneralUtility::makeInstance(EventDispatcherInterface::class)->dispatch(
                new ModifyRecordListElementDataEvent($table, $row, $level, $theData, $this)
        )->getReturnData();

        // Create element in table cells:
        $theData['uid'] = $row['uid'];
        if (isset($GLOBALS['TCA'][$table]['ctrl']['languageField'])
                && isset($GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'])
        ) {
            $theData['_l10nparent_'] = $row[$GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']];
        }

        $tagAttributes = array_map(
                static function ($attributeValue) {
                    if (is_array($attributeValue)) {
                        return implode(' ', $attributeValue);
                    }
                    return $attributeValue;
                },
                $tagAttributes
        );

        if ($triggerContainer) {
            $theData['_triggerContainer'] = $triggerContainer;
        }
        $rowOutput .= $this->addElementGridelements(
                $theData,
                GeneralUtility::implodeAttributes($tagAttributes, true),
                'td',
                $level,
                $table
        );

        if (!empty($theData['_EXPANDABLE_']) && $level < 8 && $row['l18n_parent']==0 && !empty($theData['_CHILDREN_'])) {
            $expanded = !empty($this->expandedGridelements[$row['uid']]) && (
                    (!empty($row['tx_gridelements_container']) && !empty($this->expandedGridelements[$row['tx_gridelements_container']]) && $expanded)
                    || empty($row['tx_gridelements_container'])
            ) ? ' expanded':'';
            $previousGridColumn = '';
            $originalMoveUp = $this->showMoveUp;
            $originalMoveDown = $this->showMoveDown;
            foreach ($theData['_CHILDREN_'] as $key => $child) {
                if (isset($child['tx_gridelements_columns']) && ($child['tx_gridelements_columns']!==$previousGridColumn)) {
                    $previousGridColumn = $child['tx_gridelements_columns'];
                    $this->currentTable['prev'][$child['uid']] = (int)$row['pid'];
                } else {
                    if (isset($theData['_CHILDREN_'][$key - 2]) && $theData['_CHILDREN_'][$key - 2]['tx_gridelements_columns']===$child['tx_gridelements_columns']) {
                        $this->currentTable['prev'][$child['uid']] = -(int)$theData['_CHILDREN_'][$key - 2]['uid'];
                    } else {
                        $this->currentTable['prev'][$child['uid']] = (int)$row['pid'];
                    }
                }
                if (isset($theData['_CHILDREN_'][$key + 1]) && $theData['_CHILDREN_'][$key + 1]['tx_gridelements_columns']===$child['tx_gridelements_columns']) {
                    $this->currentTable['next'][$child['uid']] = -(int)$theData['_CHILDREN_'][$key + 1]['uid'];
                }
            }
            $previousGridColumn = '';
            foreach ($theData['_CHILDREN_'] as $key => $child) {
                if ((isset($child['tx_gridelements_columns'])) && ($child['tx_gridelements_columns']!==$previousGridColumn)) {
                    $previousGridColumn = $child['tx_gridelements_columns'];
                    $this->showMoveUp = false;
                    $rowOutput .= '<tr class="t3-gridelements-child' . $expanded . '" data-trigger-container="'
                            . ($row['l18n_parent'] ?:$row['uid'])
                            . '" data-grid-container="' . $row['uid'] . '">
                                <td colspan="' . ($level + 2) . '"></td>
                                <td colspan="' . (count($this->fieldArray) - $level - 2 + $this->maxDepth) . '" style="padding:5px;">
                                <br>
                                    <strong>' . $this->getLanguageService()->sL(
                                    'LLL:EXT:gridelements/Resources/Private/Language/locallang_db.xlf:list.containerColumnName'
                            )
                            . ' ' . $theData['_CONTAINER_COLUMNS_']['columns'][$child['tx_gridelements_columns']] . '</strong>
                                </td>
                            </tr>';
                } else {
                    $this->showMoveUp = true;
                }
                $this->showMoveDown = !isset($child['tx_gridelements_columns']) || !isset($theData['_CHILDREN_'][$key + 1])
                        || (int)$child['tx_gridelements_columns']===(int)$theData['_CHILDREN_'][$key + 1]['tx_gridelements_columns'];
                // TODO
                /** @phpstan-ignore-next-line * */
                $this->currentIdList[] = $child['uid'];
                if ($row['CType']==='gridelements_pi1') {
                    /** @phpstan-ignore-next-line * */
                    $this->currentContainerIdList[] = $row['uid'];
                }
                $child['_CSSCLASS'] = 't3-gridelements-child' . $expanded;

                $rowOutput .= $this->renderListRowGridelements(
                        $table,
                        $child,
                        0,
                        [],
                        false,
                        $level + 1,
                        $row['uid'],
                        $expanded
                );
            }
            $this->showMoveUp = $originalMoveUp;
            $this->showMoveDown = $originalMoveDown;
        }

        // Finally, return table row element:
        return $rowOutput;
    }

    /**
     * Returns a table-row with the content from the fields in the input data array.
     * OBS: $this->fieldArray MUST be set! (represents the list of fields to display)
     *
     * @param array $data Is the data array, record with the fields. Notice: These fields are (currently) NOT htmlspecialchar'ed before being wrapped in <td>-tags
     * @param string $rowParams Is insert in the <tr>-tags. Must carry a ' ' as first character
     * @param string $colType Defines the tag being used for the columns. Default is td.
     * @param int $level
     * @param string $table
     * @return string HTML content for the table row
     */
    public function addElementGridelements($data, $rowParams = '', $colType = 'td', $level = 0, $table = ''): string
    {
        if ($colType==='pagination') {
            $colType = 'td';
            $pagination = true;
        } else {
            $colType = ($colType==='th') ? 'th':'td';
            $pagination = false;
        }
        $noWrap = $this->no_noWrap ? '':' nowrap="nowrap"';
        $dataUid = ($colType==='td') ? ((int)$data['uid'] ?? 0):0;
        $l10nParent = $data['_l10nparent_'] ?? 0;
        $out = '
		<!-- Element, begin: -->
		<tr ' . $rowParams . ' data-uid="' . $dataUid . '" data-l10nparent="' . $l10nParent . '" data-multi-record-selection-element="true"' .
                (!empty($data['tx_gridelements_container']) ? ' data-grid-container="' . (int)$data['tx_gridelements_container'] . '"':'') .
                (!empty($data['_triggerContainer']) ? ' data-trigger-container="' . (int)$data['_triggerContainer'] . '"':'') . '>';

        $contentCollapseIcon = '';

        if (count($data) > 1) {
            if (!empty($data['_EXPANDABLE_']) && !$l10nParent) {
                $sortField = $this->request->getQueryParams()['sortField'] ?? '';
                $sortField = $sortField ? $sortField . ':' . (int)$this->request->getQueryParams()['sortRev'] ?? 0:'';
                /**
                 * @hook contentCollapseIcon
                 * @date 2014-02-11
                 * @request Alexander Grein <alexander.grein@in2code.de>
                 */
                if (!empty($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/class.db_list_extra.inc']['actions'])) {
                    foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/class.db_list_extra.inc']['actions'] ?? [] as $className) {
                        $hookObject = GeneralUtility::makeInstance($className);
                        if (is_object($hookObject) && method_exists($hookObject, 'contentCollapseIcon')) {
                            $hookObject->contentCollapseIcon($data, $sortField, $level, $contentCollapseIcon, $this);
                        }
                    }
                }
            }
        }

        // Init rendering.
        $colsp = '';
        $lastKey = '';
        $c = 0;
        $ccount = 0;
        // __label is used as the label key to circumvent problems with uid used as label (see #67756)
        // as it was introduced later on, check if it really exists before using it
        $fields = $this->fieldArray;
        if ($colType==='td' && isset($data['__label'])) {
            // The title label column does always follow the icon column. Since
            // in some cases the first column - "_SELECTOR_" - might not be rendered,
            // we always have to calculate the key by searching for the icon column.
            $titleLabelKey = (int)(array_search('icon', $fields, true)) + 1;
            $fields[$titleLabelKey] = '__label';
        }
        // Traverse field array which contains the data to present:
        foreach ($fields as $vKey) {
            if (isset($data[$vKey])) {
                if ($lastKey) {
                    $collapseCell = '';
                    if ($table==='tt_content' && $lastKey==='_SELECTOR_') {
                        if ($contentCollapseIcon) {
                            $collapseCell = '<' . $colType . ' colspan="' . ((int)$level + 1) . '" nowrap="nowrap" class="col-icon col-content-collapse-icon">' . $contentCollapseIcon . '</' . $colType . '>';
                        } elseif ($colType!=='th') {
                            $collapseCell = '<' . $colType . ' colspan="' . ((int)$level + 1) . '" nowrap="nowrap"></' . $colType . '>';
                        }
                        if ($colType==='th') {
                            $colsp = ' colspan="2"';
                        }
                    }
                    $cssClass = $this->addElement_tdCssClass[$lastKey] ?? '';
                    if ($this->oddColumnsCssClass && $ccount % 2==0) {
                        $cssClass = implode(' ', [$this->addElement_tdCssClass[$lastKey], $this->oddColumnsCssClass]);
                    }
                    $out .= '
						<' . $colType . ' class="' . $cssClass . ' nowrap' . '"' . $colsp . '>' . $data[$lastKey] . '</' . $colType . '>'
                            . $collapseCell;
                }
                $lastKey = $vKey;
                $c = 1;
                $ccount++;
            } else {
                if (!$lastKey) {
                    $lastKey = $vKey;
                }
                $c++;
            }
            if (count($data)==1) {
                $c++;
            }
            if ($pagination) {
                $colsp = ' colspan="' . ($this->totalColumnCount - 1) . '"';
            } elseif ($c > 1) {
                $colsp = ' colspan="2"';
            } elseif ($ccount===3 && $colType==='td') {
                $colsp = ' colspan="' . ($this->maxDepth - (int)$level - 3) . '"';
            } elseif ($ccount===2 && $colType==='th') {
                if ($table==='tt_content') {
                    $colsp = ' colspan="' . ($this->maxDepth - (int)$level - 2) . '"';
                } else {
                    $colsp = ' colspan="' . ($this->maxDepth - (int)$level - 3) . '"';
                }
            } else {
                $colsp = '';
            }
        }
        if ($lastKey) {
            $cssClass = $this->addElement_tdCssClass[$lastKey] ?? '';
            if ($this->oddColumnsCssClass) {
                $cssClass = implode(' ', [$this->addElement_tdCssClass[$lastKey], $this->oddColumnsCssClass]);
            }
            $out .= '
				<' . $colType . $noWrap . ' class="' . $cssClass . '"' . $colsp
                    . ($this->addElement_tdParams[$lastKey] ?? '') . '>' . ($data[$lastKey] ?? '') . '</' . $colType . '>';
        }
        // End row
        $out .= '
		</tr>';
        return $out;
    }

    /**
     * Creates the control panel for a single record in the listing.
     *
     * @param string $table The table
     * @param mixed[] $row The record for which to make the control panel.
     * @return string HTML table with the control panel (unless disabled)
     * @throws \UnexpectedValueException
     */
    public function makeControl($table, $row)
    {
        $backendUser = $this->getBackendUserAuthentication();
        $userTsConfig = $backendUser->getTSConfig();
        $rowUid = $row['uid'];
        if (ExtensionManagementUtility::isLoaded('workspaces') && isset($row['_ORIG_uid'])) {
            $rowUid = $row['_ORIG_uid'];
        }
        $isDeletePlaceHolder = $this->isRecordDeletePlaceholder($row);
        $cells = [
                'primary' => [],
                'secondary' => [],
        ];

        // Hide the move elements for localized records - doesn't make much sense to perform these options for them
        $isL10nOverlay = (int)($row[$GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'] ?? null] ?? 0)!==0;
        $localCalcPerms = $this->getPagePermissionsForRecord($table, $row);
        if ($table==='pages') {
            $permsEdit = ($backendUser->checkLanguageAccess(
                            $row[$GLOBALS['TCA']['pages']['ctrl']['languageField'] ?? null] ?? 0
                    ))
                    && $localCalcPerms->editPagePermissionIsGranted();
        } else {
            $permsEdit = $localCalcPerms->editContentPermissionIsGranted() && $backendUser->recordEditAccessInternals(
                            $table,
                            $row
                    );
        }
        $permsEdit = $this->overlayEditLockPermissions($table, $row, $permsEdit);

        // "Show" link (only pages and tt_content elements)
        $tsConfig = BackendUtility::getPagesTSconfig($this->id)['mod.']['web_list.'] ?? [];
        if ((
                        $table==='pages'
                        && isset($row['doktype'])
                        && !in_array((int)$row['doktype'], $this->getNoViewWithDokTypes($tsConfig), true)
                )
                || (
                        $table==='tt_content'
                        && isset($this->pageRow['doktype'])
                        && !in_array((int)$this->pageRow['doktype'], $this->getNoViewWithDokTypes($tsConfig), true)
                )
        ) {
            if (!$isDeletePlaceHolder
                    && ($attributes = $this->getPreviewUriBuilder($table, $row)->serializeDispatcherAttributes())!==null
            ) {
                $viewAction = '<button'
                        . ' type="button"'
                        . ' class="btn btn-default" ' . $attributes
                        . ' title="' . htmlspecialchars(
                                $this->getLanguageService()->sL(
                                        'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.showPage'
                                )
                        ) . '">';
                if ($table==='pages') {
                    $viewAction .= $this->iconFactory->getIcon('actions-view-page', Icon::SIZE_SMALL)->render();
                } else {
                    $viewAction .= $this->iconFactory->getIcon('actions-view', Icon::SIZE_SMALL)->render();
                }
                $viewAction .= '</button>';
                $this->addActionToCellGroup($cells, $viewAction, 'view');
            } else {
                $this->addActionToCellGroup($cells, $this->spaceIcon, 'view');
            }
        } else {
            $this->addActionToCellGroup($cells, $this->spaceIcon, 'view');
        }

        // "Edit" link: ( Only if permissions to edit the page-record of the content of the parent page ($this->id)
        if ($permsEdit && !$isDeletePlaceHolder && $this->isEditable($table)) {
            $params = [
                    'edit' => [
                            $table => [
                                    $row['uid'] => 'edit',
                            ],
                    ],
            ];
            $iconIdentifier = 'actions-open';
            if ($table==='pages') {
                // Disallow manual adjustment of the language field for pages
                $params['overrideVals']['pages']['sys_language_uid'] = $row[$GLOBALS['TCA']['pages']['ctrl']['languageField'] ?? null] ?? 0;
                $iconIdentifier = 'actions-page-open';
            }
            $params['returnUrl'] = $this->listURL();
            $editLink = $this->uriBuilder->buildUriFromRoute('record_edit', $params);
            $editAction = '<a class="btn btn-default" href="' . $editLink . '"'
                    . ' title="' . htmlspecialchars(
                            $this->getLanguageService()->sL(
                                    'LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf:edit'
                            )
                    ) . '">' . $this->iconFactory->getIcon($iconIdentifier, Icon::SIZE_SMALL)->render() . '</a>';
        } else {
            $editAction = $this->spaceIcon;
        }
        $this->addActionToCellGroup($cells, $editAction, 'edit');

        // "Info"
        if (!$isDeletePlaceHolder) {
            $label = htmlspecialchars(
                    $this->getLanguageService()->sL(
                            'LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf:showInfo'
                    )
            );
            $viewBigAction = '<button type="button" aria-haspopup="dialog"'
                    . ' class="btn btn-default" '
                    . $this->createShowItemTagAttributes($table . ',' . ($row['uid'] ?? 0))
                    . ' title="' . $label . '"'
                    . ' aria-label="' . $label . '">'
                    . $this->iconFactory->getIcon('actions-document-info', Icon::SIZE_SMALL)->render()
                    . '</button>';
            $this->addActionToCellGroup($cells, $viewBigAction, 'viewBig');
        } else {
            $this->addActionToCellGroup($cells, $this->spaceIcon, 'viewBig');
        }

        // "Move" wizard link for pages/tt_content elements:
        if ($permsEdit && ($table==='tt_content' || $table==='pages') && $this->isEditable($table)) {
            if ($isL10nOverlay || $isDeletePlaceHolder) {
                $moveAction = $this->spaceIcon;
            } elseif ($table==='pages') {
                $linkTitleLL = htmlspecialchars(
                        $this->getLanguageService()->sL(
                                'LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf:move_page'
                        )
                );
                $icon = $this->iconFactory->getIcon('actions-page-move', Icon::SIZE_SMALL);
                $url = (string)$this->uriBuilder->buildUriFromRoute('move_page', [
                        'uid' => $row['uid'],
                        'returnUrl' => $this->listURL(),
                ]);
                $moveAction = '<a class="btn btn-default" href="' . htmlspecialchars(
                                $url
                        ) . '" aria-label="' . $linkTitleLL . '">' . $icon->render() . '</a>';
            } else {
                $linkTitleLL = htmlspecialchars(
                        $this->getLanguageService()->sL(
                                'LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf:move_record'
                        )
                );
                $icon = $this->iconFactory->getIcon('actions-document-move', Icon::SIZE_SMALL);
                $url = (string)$this->uriBuilder->buildUriFromRoute('move_element', [
                        'uid' => $row['uid'],
                        'returnUrl' => $this->listURL(),
                ]);
                $moveAction = '<a class="btn btn-default" href="' . htmlspecialchars(
                                $url
                        ) . '" aria-label="' . $linkTitleLL . '">' . $icon->render() . '</a>';
            }
            $this->addActionToCellGroup($cells, $moveAction, 'move');
        }

        // If the table is NOT a read-only table, then show these links:
        if ($this->isEditable($table)) {
            // "Revert" link (history/undo)
            if (\trim(
                    $userTsConfig['options.']['showHistory.'][$table] ?? $userTsConfig['options.']['showHistory'] ?? '1'
            )) {
                if (!$isDeletePlaceHolder) {
                    $moduleUrl = $this->uriBuilder->buildUriFromRoute('record_history', [
                                    'element' => $table . ':' . $row['uid'],
                                    'returnUrl' => $this->listURL(),
                            ]) . '#latest';
                    $historyAction = '<a class="btn btn-default" href="' . htmlspecialchars($moduleUrl) . '" title="'
                            . htmlspecialchars(
                                    $this->getLanguageService()->sL(
                                            'LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf:history'
                                    )
                            ) . '">'
                            . $this->iconFactory->getIcon('actions-document-history-open', Icon::SIZE_SMALL)->render(
                            ) . '</a>';
                    $this->addActionToCellGroup($cells, $historyAction, 'history');
                } else {
                    $this->addActionToCellGroup($cells, $this->spaceIcon, 'history');
                }
            }

            // "Edit Perms" link:
            if ($table==='pages' && $this->moduleProvider->accessGranted('permissions_pages', $backendUser)) {
                if ($isL10nOverlay || $isDeletePlaceHolder) {
                    $permsAction = $this->spaceIcon;
                } else {
                    $params = [
                            'id' => $row['uid'],
                            'action' => 'edit',
                            'returnUrl' => $this->listURL(),
                    ];
                    $href = (string)$this->uriBuilder->buildUriFromRoute('permissions_pages', $params);
                    $permsAction = '<a class="btn btn-default" href="' . htmlspecialchars($href) . '" title="'
                            . htmlspecialchars(
                                    $this->getLanguageService()->sL(
                                            'LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf:permissions'
                                    )
                            ) . '">'
                            . $this->iconFactory->getIcon('actions-lock', Icon::SIZE_SMALL)->render() . '</a>';
                }
                $this->addActionToCellGroup($cells, $permsAction, 'perms');
            }

            // "New record after" link (ONLY if the records in the table are sorted by a "sortby"-row
            // or if default values can depend on previous record):
            if ((($GLOBALS['TCA'][$table]['ctrl']['sortby'] ?? false) || ($GLOBALS['TCA'][$table]['ctrl']['useColumnsForDefaultValues'] ?? false)) && $permsEdit) {
                $neededPermission = $table==='pages' ? Permission::PAGE_NEW:Permission::CONTENT_EDIT;
                if ($this->calcPerms->isGranted($neededPermission)) {
                    if ($isL10nOverlay || $isDeletePlaceHolder) {
                        $this->addActionToCellGroup($cells, $this->spaceIcon, 'new');
                    } elseif ($this->showNewRecLink($table)) {
                        $params = [
                                'edit' => [
                                        $table => [
                                                (0 - (($row['_MOVE_PLH'] ?? 0) ? $row['_MOVE_PLH_uid']:$row['uid'])) => 'new',
                                        ],
                                ],
                                'returnUrl' => $this->listURL(),
                        ];
                        $icon = ($table==='pages' ? $this->iconFactory->getIcon(
                                'actions-page-new',
                                Icon::SIZE_SMALL
                        ):$this->iconFactory->getIcon('actions-plus', Icon::SIZE_SMALL));
                        $titleLabel = $this->getLanguageService()->sL(
                                'LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf:new'
                        );
                        if ($GLOBALS['TCA'][$table]['ctrl']['sortby'] ?? false) {
                            $titleLabel = $this->getLanguageService()->sL(
                                    'LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf:newRecord'
                            );
                            if ($table==='pages') {
                                $titleLabel = $this->getLanguageService()->sL(
                                        'LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf:newPage'
                                );
                            }
                        }
                        $newLink = $this->uriBuilder->buildUriFromRoute('record_edit', $params);
                        $newAction = '<a class="btn btn-default" href="' . $newLink . '" title="' . htmlspecialchars(
                                        $titleLabel
                                ) . '">'
                                . $icon->render() . '</a>';
                        $this->addActionToCellGroup($cells, $newAction, 'new');
                    }
                }
            }

            // "Hide/Unhide" links:
            $hiddenField = $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['disabled'] ?? null;
            if ($hiddenField!==null
                    && !empty($GLOBALS['TCA'][$table]['columns'][$hiddenField])
                    && (empty($GLOBALS['TCA'][$table]['columns'][$hiddenField]['exclude']) || $backendUser->check(
                                    'non_exclude_fields',
                                    $table . ':' . $hiddenField
                            ))
            ) {
                if (!$permsEdit || $isDeletePlaceHolder || $this->isRecordCurrentBackendUser($table, $row)) {
                    $hideAction = $this->spaceIcon;
                } else {
                    $visibleTitle = $this->getLanguageService()->sL(
                            'LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf:hide' . ($table==='pages' ? 'Page':'')
                    );
                    $visibleIcon = 'actions-edit-hide';
                    $visibleValue = '0';
                    $hiddenTitle = $this->getLanguageService()->sL(
                            'LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf:unHide' . ($table==='pages' ? 'Page':'')
                    );
                    $hiddenIcon = 'actions-edit-unhide';
                    $hiddenValue = '1';
                    if ($row[$hiddenField] ?? false) {
                        $titleLabel = $hiddenTitle;
                        $iconIdentifier = $hiddenIcon;
                        $status = 'hidden';
                    } else {
                        $titleLabel = $visibleTitle;
                        $iconIdentifier = $visibleIcon;
                        $status = 'visible';
                    }
                    $attributesString = GeneralUtility::implodeAttributes(
                            [
                                    'class' => 'btn btn-default',
                                    'type' => 'button',
                                    'title' => $titleLabel,
                                    'data-datahandler-action' => 'visibility',
                                    'data-datahandler-table' => $table,
                                    'data-datahandler-uid' => $rowUid,
                                    'data-datahandler-field' => $hiddenField,
                                    'data-datahandler-status' => $status,
                                    'data-datahandler-visible-label' => $visibleTitle,
                                    'data-datahandler-visible-value' => $visibleValue,
                                    'data-datahandler-visible-icon' => $visibleIcon,
                                    'data-datahandler-hidden-label' => $hiddenTitle,
                                    'data-datahandler-hidden-value' => $hiddenValue,
                                    'data-datahandler-hidden-icon' => $hiddenIcon,
                            ],
                            true
                    );
                    $hideAction = '<button ' . $attributesString . '>'
                            . $this->iconFactory->getIcon($iconIdentifier, Icon::SIZE_SMALL)
                            . '</button>';
                }
                $this->addActionToCellGroup($cells, $hideAction, 'hide');
            }

            // "Up/Down" links
            if ($permsEdit && ($GLOBALS['TCA'][$table]['ctrl']['sortby'] ?? false) && !$this->sortField && !$this->searchLevels) {
                if (!$isL10nOverlay && !$isDeletePlaceHolder && isset($this->currentTable['prev'][$row['uid']])) {
                    // Up
                    $params = [];
                    $params['redirect'] = $this->listURL();
                    $params['cmd'][$table][$row['uid']]['move'] = $this->currentTable['prev'][$row['uid']];
                    $url = (string)$this->uriBuilder->buildUriFromRoute('tce_db', $params);
                    $moveUpAction = '<a class="btn btn-default" href="' . htmlspecialchars(
                                    $url
                            ) . '" title="' . htmlspecialchars(
                                    $this->getLanguageService()->sL(
                                            'LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf:moveUp'
                                    )
                            ) . '">'
                            . $this->iconFactory->getIcon('actions-move-up', Icon::SIZE_SMALL)->render() . '</a>';
                } else {
                    $moveUpAction = $this->spaceIcon;
                }
                $this->addActionToCellGroup($cells, $moveUpAction, 'moveUp');

                if (!$isL10nOverlay && !$isDeletePlaceHolder && !empty($this->currentTable['next'][$row['uid']])) {
                    // Down
                    $params = [];
                    $params['redirect'] = $this->listURL();
                    $params['cmd'][$table][$row['uid']]['move'] = $this->currentTable['next'][$row['uid']];
                    $url = (string)$this->uriBuilder->buildUriFromRoute('tce_db', $params);
                    $moveDownAction = '<a class="btn btn-default" href="' . htmlspecialchars(
                                    $url
                            ) . '" title="' . htmlspecialchars(
                                    $this->getLanguageService()->sL(
                                            'LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf:moveDown'
                                    )
                            ) . '">'
                            . $this->iconFactory->getIcon('actions-move-down', Icon::SIZE_SMALL)->render() . '</a>';
                } else {
                    $moveDownAction = $this->spaceIcon;
                }
                $this->addActionToCellGroup($cells, $moveDownAction, 'moveDown');
            }

            // "Delete" link:
            $disableDelete = (bool)\trim(
                    (string)($userTsConfig['options.']['disableDelete.'][$table] ?? $userTsConfig['options.']['disableDelete'] ?? '')
            );
            if ($permsEdit
                    && !$disableDelete
                    && (($table==='pages' && $localCalcPerms->deletePagePermissionIsGranted(
                                    )) || ($table!=='pages' && $this->calcPerms->editContentPermissionIsGranted()))
                    && !$this->isRecordCurrentBackendUser($table, $row)
                    && !$isDeletePlaceHolder
            ) {
                $actionName = 'delete';
                $recordInfo = BackendUtility::getRecordTitle($table, $row);
                if ($this->getBackendUserAuthentication()->shallDisplayDebugInformation()) {
                    $recordInfo .= ' [' . $table . ':' . $row['uid'] . ']';
                }
                $refCountMsg = BackendUtility::referenceCount(
                                $table,
                                $row['uid'],
                                LF . $this->getLanguageService()->sL(
                                        'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.referencesToRecord'
                                ),
                                (string)$this->getReferenceCount($table, $row['uid'])
                        ) . BackendUtility::translationCount(
                                $table,
                                $row['uid'],
                                LF . $this->getLanguageService()->sL(
                                        'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.translationsOfRecord'
                                )
                        );

                $warningText = sprintf(
                                $this->getLanguageService()->sL(
                                        'LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf:' . $actionName . 'Warning'
                                ),
                                trim($recordInfo)
                        ) . $refCountMsg;
                $params = 'cmd[' . $table . '][' . $row['uid'] . '][delete]=1';
                $icon = $this->iconFactory->getIcon('actions-edit-' . $actionName, Icon::SIZE_SMALL)->render();
                $linkTitle = $this->getLanguageService()->sL(
                        'LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf:' . $actionName
                );
                $titleText = $this->getLanguageService()->sL(
                        'LLL:EXT:backend/Resources/Private/Language/locallang_alt_doc.xlf:label.confirm.delete_record.title'
                );
                $l10nParentField = $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'] ?? '';

                $deleteActionAttributes = GeneralUtility::implodeAttributes([
                                                                                    'type' => 'button',
                                                                                    'class' => 'btn btn-default t3js-record-delete',
                                                                                    'title' => $linkTitle,
                                                                                    'aria-label' => $linkTitle,
                                                                                    'aria-haspopup' => 'dialog',
                                                                                    'data-button-ok-text' => $linkTitle,
                                                                                    'data-l10parent' => $l10nParentField ? (string)$row[$l10nParentField]:'',
                                                                                    'data-params' => $params,
                                                                                    'data-message' => $warningText,
                                                                                    'data-title' => $titleText,
                                                                            ], true, true);
                $deleteAction = '<button ' . $deleteActionAttributes . '>' . $icon . '</button>';
            } else {
                $deleteAction = $this->spaceIcon;
            }
            $this->addActionToCellGroup($cells, $deleteAction, 'delete');

            // "Levels" links: Moving pages into new levels...
            if ($permsEdit && $table==='pages' && !$this->searchLevels) {
                // Up (Paste as the page right after the current parent page)
                if ($this->calcPerms->createPagePermissionIsGranted()) {
                    if (!$isDeletePlaceHolder && !$isL10nOverlay) {
                        $params = [];
                        $params['redirect'] = $this->listURL();
                        $params['cmd'][$table][$row['uid']]['move'] = -$this->id;
                        $url = (string)$this->uriBuilder->buildUriFromRoute('tce_db', $params);
                        $label = htmlspecialchars(
                                $this->getLanguageService()->sL(
                                        'LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf:prevLevel'
                                )
                        );
                        $moveLeftAction = '<a class="btn btn-default"'
                                . ' href="' . htmlspecialchars($url) . '"'
                                . ' title="' . $label . '"'
                                . ' aria-label="' . $label . '">'
                                . $this->iconFactory->getIcon('actions-move-left', Icon::SIZE_SMALL)->render()
                                . '</a>';
                        $this->addActionToCellGroup($cells, $moveLeftAction, 'moveLeft');
                    } else {
                        $this->addActionToCellGroup($cells, $this->spaceIcon, 'moveLeft');
                    }
                }
                // Down (Paste as subpage to the page right above)
                if (!$isL10nOverlay && !$isDeletePlaceHolder && !empty($this->currentTable['prevUid'][$row['uid']])) {
                    $localCalcPerms = $this->getPagePermissionsForRecord(
                            'pages',
                            BackendUtility::getRecord('pages', $this->currentTable['prevUid'][$row['uid']]) ?? []
                    );
                    if ($localCalcPerms->createPagePermissionIsGranted()) {
                        $params = [];
                        $params['redirect'] = $this->listURL();
                        $params['cmd'][$table][$row['uid']]['move'] = $this->currentTable['prevUid'][$row['uid']];
                        $url = (string)$this->uriBuilder->buildUriFromRoute('tce_db', $params);
                        $label = htmlspecialchars(
                                $this->getLanguageService()->sL(
                                        'LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf:nextLevel'
                                )
                        );
                        $moveRightAction = '<a class="btn btn-default"'
                                . ' href="' . htmlspecialchars($url) . '"'
                                . ' title="' . $label . '"'
                                . ' aria-label="' . $label . '">'
                                . $this->iconFactory->getIcon('actions-move-right', Icon::SIZE_SMALL)->render(
                                ) . '</a>';
                    } else {
                        $moveRightAction = $this->spaceIcon;
                    }
                } else {
                    $moveRightAction = $this->spaceIcon;
                }
                $this->addActionToCellGroup($cells, $moveRightAction, 'moveRight');
            }
        }

        // Add clipboard related actions
        $this->makeClip($table, $row, $cells);

        $event = $this->eventDispatcher->dispatch(
                new ModifyRecordListRecordActionsEvent($cells, $table, $row, $this)
        );

        $output = '';
        foreach ($event->getActions() as $classification => $actions) {
            if ($classification!=='primary') {
                $cellOutput = '';
                foreach ($actions as $action) {
                    if ($action===$this->spaceIcon) {
                        continue;
                    }
                    // This is a backwards-compat layer for the existing hook items, which will be removed in TYPO3 v12.
                    $action = str_replace('btn btn-default', 'dropdown-item dropdown-item-spaced', $action);
                    $title = [];
                    preg_match('/title="([^"]*)"/', $action, $title);
                    if (empty($title)) {
                        preg_match('/aria-label="([^"]*)"/', $action, $title);
                    }
                    if (!empty($title[1] ?? '')) {
                        $action = str_replace(
                                [
                                        '</a>',
                                        '</button>',
                                ],
                                [
                                        ' ' . $title[1] . '</a>',
                                        ' ' . $title[1] . '</button>',
                                ],
                                $action
                        );
                        // In case we added the title as tag content, we can remove the attribute,
                        // since this is duplicated and would trigger a tooltip with the same content.
                        if (!empty($title[0] ?? '')) {
                            $action = str_replace($title[0], '', $action);
                        }
                    }
                    $cellOutput .= '<li>' . $action . '</li>';
                }

                if ($cellOutput!=='') {
                    $icon = $this->iconFactory->getIcon('actions-menu-alternative', Icon::SIZE_SMALL);
                    $title = $this->getLanguageService()->sL(
                            'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:cm.more'
                    );
                    $output .= ' <div class="btn-group dropdown" title="' . htmlspecialchars($title) . '">' .
                            '<a href="#actions_' . $table . '_' . $row['uid'] . '" class="btn btn-default dropdown-toggle dropdown-toggle-no-chevron" data-bs-toggle="dropdown" data-bs-boundary="window" aria-expanded="false">' . $icon->render(
                            ) . '</a>' .
                            '<ul id="actions_' . $table . '_' . $row['uid'] . '" class="dropdown-menu">' . $cellOutput . '</ul>' .
                            '</div>';
                } else {
                    $output .= ' <div class="btn-group">' . $this->spaceIcon . '</div>';
                }
            } else {
                $output .= ' <div class="btn-group">' . implode('', $actions) . '</div>';
            }
        }

        return $output;
    }

    /**
     * @return object|BackendLayoutView
     */
    protected function getBackendLayoutView()
    {
        return GeneralUtility::makeInstance(BackendLayoutView::class);
    }

    /**
     * @return int[]
     */
    public function getExpandedGridelements(): array
    {
        return $this->expandedGridelements;
    }

    /**
     * @return LayoutSetup
     */
    public function getGridelementsBackendLayouts(): LayoutSetup
    {
        return $this->gridelementsBackendLayouts;
    }
}

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
use PDO;
use function trim;
use TYPO3\CMS\Backend\RecordList\RecordListGetTableHookInterface;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendLayoutView;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\QueryHelper;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Core\Versioning\VersionState;
use TYPO3\CMS\Recordlist\RecordList\RecordListHookInterface;
use UnexpectedValueException;

/**
 * Class for rendering of Web>List module
 */
class DatabaseRecordList10 extends \TYPO3\CMS\Recordlist\RecordList\DatabaseRecordList
{
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

    // *********
    // Internal:
    // *********
    /**
     * @var int[]
     */
    protected array $expandedGridelements = [];

    /**
     * @var int[]
     */
    protected array $currentIdList = [];

    /**
     * @var int[]
     */
    protected array $currentContainerIdList = [];

    /**
     * @var bool
     */
    protected bool $showMoveUp;

    /**
     * @var bool
     */
    protected bool $showMoveDown;

    /**
     * @var string
     */
    protected string $lastMoveDownParams;

    /**
     * @var BackendUserAuthentication
     */
    protected BackendUserAuthentication $backendUser;

    /**
     * @var bool
     */
    protected bool $l10nEnabled;

    /**
     * @var bool
     */
    protected bool $no_noWrap = false;

    /**
     * @var bool
     */
    protected bool $localizationView;

    /**
     * Gridelements backend layouts to provide container column information
     *
     * @var LayoutSetup
     */
    protected LayoutSetup $gridelementsBackendLayouts;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Creates the listing of records from a single table
     *
     * @param string $table Table name
     * @param int $id Page id
     * @param string $rowList List of fields to show in the listing. Pseudo fields will be added including the record header.
     * @return string HTML table with the listing for the record.
     * @throws Exception
     * @throws RouteNotFoundException
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getTable($table, $id, $rowList = ''): string
    {
        $backendLayout = $this->getBackendLayoutView()->getSelectedBackendLayout($id);
        $backendLayoutColumns = [];
        if (is_array($backendLayout['__items'])) {
            foreach ($backendLayout['__items'] as $backendLayoutItem) {
                $backendLayoutColumns[$backendLayoutItem[1]] = htmlspecialchars($backendLayoutItem[0]);
            }
        }
        $rowListArray = GeneralUtility::trimExplode(',', $rowList, true);
        // if no columns have been specified, show description (if configured)
        if (!empty($GLOBALS['TCA'][$table]['ctrl']['descriptionColumn']) && empty($rowListArray)) {
            $rowListArray[] = $GLOBALS['TCA'][$table]['ctrl']['descriptionColumn'];
        }
        $backendUser = $this->getBackendUserAuthentication();
        $lang = $this->getLanguageService();
        // Init
        $addWhere = '';
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $titleCol = $GLOBALS['TCA'][$table]['ctrl']['label'];
        $thumbsCol = $GLOBALS['TCA'][$table]['ctrl']['thumbnail'];
        $this->l10nEnabled = $GLOBALS['TCA'][$table]['ctrl']['languageField']
                && $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']
                && $table !== 'pages_language_overlay';
        $tableCollapsed = (bool)$this->tablesCollapsed[$table];
        // prepare space icon
        $this->spaceIcon = '<span class="btn btn-default disabled">' . $this->iconFactory->getIcon(
            'empty-empty',
            Icon::SIZE_SMALL
        )->render() . '</span>';
        // Cleaning rowlist for duplicates and place the $titleCol as the first column always!
        $this->fieldArray = [];
        // title Column
        // Add title column
        $this->fieldArray[] = $titleCol;
        // Control-Panel
        if (!GeneralUtility::inList($rowList, '_CONTROL_')) {
            $this->fieldArray[] = '_CONTROL_';
        }
        // Clipboard
        if ($this->showClipboard) {
            $this->fieldArray[] = '_CLIPBOARD_';
        }
        // Ref
        if (!$this->dontShowClipControlPanels) {
            $this->fieldArray[] = '_REF_';
        }
        // Path
        if ($this->searchLevels) {
            $this->fieldArray[] = '_PATH_';
        }
        // Localization
        if ($this->l10nEnabled) {
            $this->fieldArray[] = '_LOCALIZATION_';
            // Do not show the "Localize to:" field when only translated records should be shown
            if (!$this->showOnlyTranslatedRecords) {
                $this->fieldArray[] = '_LOCALIZATION_b';
            }
            // Only restrict to the default language if no search request is in place
            // And if only translations should be shown
            if ($this->searchString === '' && !$this->showOnlyTranslatedRecords) {
                $addWhere = (string)$queryBuilder->expr()->orX(
                    $queryBuilder->expr()->lte($GLOBALS['TCA'][$table]['ctrl']['languageField'], 0),
                    $queryBuilder->expr()->eq($GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'], 0)
                );
            }
        }
        // Cleaning up:
        $this->fieldArray = array_unique(array_merge($this->fieldArray, $rowListArray));
        if ($this->noControlPanels) {
            $tempArray = array_flip($this->fieldArray);
            unset($tempArray['_CONTROL_']);
            unset($tempArray['_CLIPBOARD_']);
            $this->fieldArray = array_keys($tempArray);
        }
        // Creating the list of fields to include in the SQL query:
        $selectFields = $this->fieldArray;
        $selectFields[] = 'uid';
        $selectFields[] = 'pid';
        // adding column for thumbnails
        if ($thumbsCol) {
            $selectFields[] = $thumbsCol;
        }
        if ($table === 'pages') {
            $selectFields[] = 'module';
            $selectFields[] = 'extendToSubpages';
            $selectFields[] = 'nav_hide';
            $selectFields[] = 'doktype';
            $selectFields[] = 'shortcut';
            $selectFields[] = 'shortcut_mode';
            $selectFields[] = 'mount_pid';
        }
        if ($table === 'tt_content') {
            $selectFields[] = 'CType';
            $selectFields[] = 'colPos';
            $selectFields[] = 'tx_gridelements_backend_layout';
            $selectFields[] = 'tx_gridelements_container';
            $selectFields[] = 'tx_gridelements_columns';
        }
        if (is_array($GLOBALS['TCA'][$table]['ctrl']['enablecolumns'])) {
            $selectFields = array_merge($selectFields, $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']);
        }
        foreach (['type', 'typeicon_column', 'editlock'] as $field) {
            if ($GLOBALS['TCA'][$table]['ctrl'][$field]) {
                $selectFields[] = $GLOBALS['TCA'][$table]['ctrl'][$field];
            }
        }
        if ($GLOBALS['TCA'][$table]['ctrl']['versioningWS']) {
            $selectFields[] = 't3ver_id';
            $selectFields[] = 't3ver_state';
            $selectFields[] = 't3ver_wsid';
        }
        if ($this->l10nEnabled) {
            $selectFields[] = $GLOBALS['TCA'][$table]['ctrl']['languageField'];
            $selectFields[] = $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'];
        }
        if ($GLOBALS['TCA'][$table]['ctrl']['label_alt']) {
            $selectFields = array_merge(
                $selectFields,
                GeneralUtility::trimExplode(',', $GLOBALS['TCA'][$table]['ctrl']['label_alt'], true)
            );
        }
        // Unique list!
        $selectFields = array_unique($selectFields);
        $fieldListFields = $this->makeFieldList($table, 1);
        if (empty($fieldListFields) && $GLOBALS['TYPO3_CONF_VARS']['BE']['debug']) {
            $message = sprintf(
                $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf:missingTcaColumnsMessage'),
                $table,
                $table
            );
            $messageTitle = $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf:missingTcaColumnsMessageTitle');
            /** @var FlashMessage $flashMessage */
            $flashMessage = GeneralUtility::makeInstance(
                FlashMessage::class,
                $message,
                $messageTitle,
                FlashMessage::WARNING,
                true
            );
            /** @var FlashMessageService $flashMessageService */
            $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
            $defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
            $defaultFlashMessageQueue->enqueue($flashMessage);
        }
        // Making sure that the fields in the field-list ARE in the field-list from TCA!
        $selectFields = array_intersect($selectFields, $fieldListFields);
        // Implode it into a list of fields for the SQL-statement.
        $selFieldList = implode(',', $selectFields);
        $this->selFieldList = $selFieldList;
        if ($table === 'tt_content') {
            $this->gridelementsBackendLayouts = GeneralUtility::makeInstance(LayoutSetup::class)->init($id);
        }
        foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/class.db_list_extra.inc']['getTable'] ?? [] as $className) {
            $hookObject = GeneralUtility::makeInstance($className);
            if (!$hookObject instanceof RecordListGetTableHookInterface) {
                throw new UnexpectedValueException(
                    $className . ' must implement interface ' . RecordListGetTableHookInterface::class,
                    1195114460
                );
            }
            $hookObject->getDBlistQuery($table, $id, $addWhere, $selFieldList, $this);
        }
        if ($table == 'pages' && $this->showOnlyTranslatedRecords) {
            $addWhere .= ' AND ' . $GLOBALS['TCA']['pages']['ctrl']['languageField'] . ' IN(' . implode(',', array_keys($this->languagesAllowedForUser)) . ')';
        }
        $additionalConstraints = empty($addWhere) ? [] : [QueryHelper::stripLogicalOperatorPrefix($addWhere)];
        if ($table === 'tt_content') {
            $additionalConstraints[] = (string)$queryBuilder->expr()->andX(
                $queryBuilder->expr()->neq('colPos', -1)
            );
        }
        $selFieldList = GeneralUtility::trimExplode(',', $selFieldList, true);

        // Create the SQL query for selecting the elements in the listing:
        // do not do paging when outputting as CSV
        if ($this->csvOutput) {
            $this->iLimit = 0;
        }
        if ($this->firstElementNumber > 2 && $this->iLimit > 0) {
            // Get the two previous rows for sorting if displaying page > 1
            $this->firstElementNumber -= 2;
            $this->iLimit += 2;
            // (API function from TYPO3\CMS\Recordlist\RecordList\AbstractDatabaseRecordList)
            $queryBuilder = $this->getQueryBuilder($table, $id, $additionalConstraints);
            $this->firstElementNumber += 2;
            $this->iLimit -= 2;
        } else {
            // (API function from TYPO3\CMS\Recordlist\RecordList\AbstractDatabaseRecordList)
            $queryBuilder = $this->getQueryBuilder($table, $id, $additionalConstraints);
        }

        if ($table === 'tt_content') {
            array_pop($additionalConstraints);
        }

        // Finding the total amount of records on the page
        // (API function from TYPO3\CMS\Recordlist\RecordList\AbstractDatabaseRecordList)
        $this->setTotalItems($table, $id, $additionalConstraints);

        // Init:
        $queryResult = $queryBuilder->execute();
        $dbCount = 0;
        $out = '';
        $tableHeader = '';
        $listOnlyInSingleTableMode = $this->listOnlyInSingleTableMode && !$this->table;
        // If the count query returned any number of records, we perform the real query,
        // selecting records.
        if ($this->totalItems) {
            // Fetch records only if not in single table mode
            if ($listOnlyInSingleTableMode) {
                $dbCount = $this->totalItems;
            } else {
                // Set the showLimit to the number of records when outputting as CSV
                if ($this->csvOutput) {
                    $this->showLimit = $this->totalItems;
                    $this->iLimit = $this->totalItems;
                    $dbCount = $this->totalItems;
                } else {
                    if ($this->firstElementNumber + $this->showLimit <= $this->totalItems) {
                        $dbCount = $this->showLimit + 2;
                    } else {
                        $dbCount = $this->totalItems - $this->firstElementNumber + 2;
                    }
                }
            }
        }
        // If any records was selected, render the list:
        if ($dbCount) {
            $tableIdentifier = $table;
            // Use a custom table title for translated pages
            if ($table == 'pages' && $this->showOnlyTranslatedRecords) {
                // pages records in list module are split into two own sections, one for pages with
                // sys_language_uid = 0 "Page" and an own section for sys_language_uid > 0 "Page Translation".
                // This if sets the different title for the page translation case and a unique table identifier
                // which is used in DOM as id.
                $tableTitle = htmlspecialchars($lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:pageTranslation'));
                $tableIdentifier = 'pages_translated';
            } else {
                $tableTitle = htmlspecialchars($lang->sL($GLOBALS['TCA'][$table]['ctrl']['title']));
                if ($tableTitle === '') {
                    $tableTitle = $table;
                }
            }
            // Header line is drawn
            $theData = [];
            if ($this->disableSingleTableView) {
                $theData[$titleCol] = '<span class="c-table">' . BackendUtility::wrapInHelp($table, '', $tableTitle)
                        . '</span> (<span class="t3js-table-total-items">' . $this->totalItems . '</span>)';
            } else {
                $icon = $this->table
                        ? '<span title="' . htmlspecialchars($lang->getLL('contractView')) . '">' . $this->iconFactory->getIcon(
                            'actions-view-table-collapse',
                            Icon::SIZE_SMALL
                        )->render() . '</span>'
                        : '<span title="' . htmlspecialchars($lang->getLL('expandView')) . '">' . $this->iconFactory->getIcon(
                            'actions-view-table-expand',
                            Icon::SIZE_SMALL
                        )->render() . '</span>';
                $theData[$titleCol] = $this->linkWrapTable(
                    $table,
                    $tableTitle . ' (<span class="t3js-table-total-items">' . $this->totalItems . '</span>) ' . $icon
                );
            }
            if ($listOnlyInSingleTableMode) {
                $tableHeader .= BackendUtility::wrapInHelp($table, '', $theData[$titleCol]);
            } else {
                // Render collapse button if in multi table mode
                $collapseIcon = '';
                if (!$this->table) {
                    $href = htmlspecialchars($this->listURL() . '&collapse[' . $table . ']=' . ($tableCollapsed ? '0' : '1'));
                    $title = $tableCollapsed
                            ? htmlspecialchars($lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.expandTable'))
                            : htmlspecialchars($lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.collapseTable'));
                    $icon = '<span class="collapseIcon">' . $this->iconFactory->getIcon(
                        ($tableCollapsed ? 'actions-view-list-expand' : 'actions-view-list-collapse'),
                        Icon::SIZE_SMALL
                    )->render() . '</span>';
                    $collapseIcon = '<a href="' . $href . '" title="' . $title . '" class="pull-right t3js-toggle-recordlist" data-table="' . htmlspecialchars($tableIdentifier) . '" data-toggle="collapse" data-target="#recordlist-' . htmlspecialchars($tableIdentifier) . '">' . $icon . '</a>';
                }
                $tableHeader .= $theData[$titleCol] . $collapseIcon;
            }
            // Check if gridelements containers are expanded or collapsed
            if ($table === 'tt_content') {
                $this->expandedGridelements = [];
                $backendUser = $this->getBackendUserAuthentication();
                if (is_array($backendUser->uc['moduleData']['list']['gridelementsExpanded'])) {
                    $this->expandedGridelements = $backendUser->uc['moduleData']['list']['gridelementsExpanded'];
                }
                $expandOverride = GeneralUtility::_GP('gridelementsExpand');
                if (is_array($expandOverride)) {
                    foreach ($expandOverride as $expandContainer => $expandValue) {
                        if ($expandValue) {
                            $this->expandedGridelements[$expandContainer] = 1;
                        } else {
                            unset($this->expandedGridelements[$expandContainer]);
                        }
                    }
                    $backendUser->uc['moduleData']['list']['gridelementsExpanded'] = $this->expandedGridelements;
                    // Save modified user uc
                    $backendUser->writeUC($backendUser->uc);
                    $returnUrl = GeneralUtility::sanitizeLocalUrl(GeneralUtility::_GP('returnUrl'));
                    if ($returnUrl !== '') {
                        HttpUtility::redirect($returnUrl);
                    }
                }
            }
            // Render table rows only if in multi table view or if in single table view
            $rowOutput = '';
            if (!$listOnlyInSingleTableMode || $this->table) {
                // Fixing an order table for sortby tables
                $this->currentTable = [];
                $this->currentIdList = [];
                $doSort = $GLOBALS['TCA'][$table]['ctrl']['sortby'] && !$this->sortField;
                $prevUid = 0;
                $prevPrevUid = 0;
                // Get first two rows and initialize prevPrevUid and prevUid if on page > 1
                if ($this->firstElementNumber > 2 && $this->iLimit > 0) {
                    $row = $queryResult->fetch();
                    $prevPrevUid = -((int)$row['uid']);
                    $row = $queryResult->fetch();
                    $prevUid = $row['uid'];
                }
                $accRows = [];
                // Accumulate rows here
                while ($row = $queryResult->fetch()) {
                    if (!$this->isRowListingConditionFulfilled($table, $row)) {
                        continue;
                    }
                    // In offline workspace, look for alternative record:
                    BackendUtility::workspaceOL($table, $row, $backendUser->workspace, true);
                    if (is_array($row)) {
                        $accRows[] = $row;
                        $this->currentIdList[] = $row['uid'];
                        if ($row['CType'] === 'gridelements_pi1') {
                            $this->currentContainerIdList[] = $row['uid'];
                        }
                        if ($doSort) {
                            if ($prevUid) {
                                $this->currentTable['prev'][$row['uid']] = $prevPrevUid;
                                $this->currentTable['next'][$prevUid] = '-' . $row['uid'];
                                $this->currentTable['prevUid'][$row['uid']] = $prevUid;
                            }
                            $prevPrevUid = isset($this->currentTable['prev'][$row['uid']]) ? -$prevUid : $row['pid'];
                            $prevUid = $row['uid'];
                        }
                    }
                }
                $this->totalRowCount = count($accRows);
                // CSV initiated
                if ($this->csvOutput) {
                    $this->initCSV();
                }
                // Render items:
                $this->CBnames = [];
                $this->duplicateStack = [];
                $this->eCounter = $this->firstElementNumber;
                $cc = 0;
                $lastColPos = 0;
                // The header row for the table is now created:
                $out .= '###REPLACE_LIST_HEADER###';
                foreach ($accRows as $key => $row) {
                    // Render item row if counter < limit
                    if ($cc < $this->iLimit) {
                        $cc++;
                        $this->translations = false;
                        if (isset($row['colPos']) && ($row['colPos'] != $lastColPos)) {
                            $lastColPos = $row['colPos'];
                            $this->showMoveUp = false;
                            $rowOutput .= '<tr>
                                    <td colspan="2"></td>
                                    <td colspan="' . (count($this->fieldArray) - 1 + $this->maxDepth) . '" style="padding:5px;">
                                        <br />
                                        <strong>'
                                    . $this->getLanguageService()->sL('LLL:EXT:gridelements/Resources/Private/Language/locallang_db.xlf:list.columnName')
                                    . ' ' . ($backendLayoutColumns[$row['colPos']] ?: (int)$row['colPos']) . '</strong>
                                    </td>
                                </tr>';
                        } else {
                            $this->showMoveUp = true;
                        }
                        $this->showMoveDown = !isset($row['colPos']) || !isset($accRows[$key + 1])
                                || $row['colPos'] == $accRows[$key + 1]['colPos'];
                        $rowOutput .= $this->renderListRow($table, $row, $cc, $titleCol, $thumbsCol);
                        // If no search happened it means that the selected
                        // records are either default or All language and here we will not select translations
                        // which point to the main record:
                        if ($this->l10nEnabled && $this->searchString === '' && !($this->hideTranslations === '*' || GeneralUtility::inList(
                            $this->hideTranslations,
                            $table
                        ))) {
                            // For each available translation, render the record:
                            if (is_array($this->translations)) {
                                foreach ($this->translations as $lRow) {
                                    // $lRow isn't always what we want - if record was moved we've to work with the
                                    // placeholder records otherwise the list is messed up a bit
                                    if ($row['_MOVE_PLH_uid'] && $row['_MOVE_PLH_pid']) {
                                        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                                                ->getQueryBuilderForTable($table);
                                        $queryBuilder->getRestrictions()
                                                ->removeAll()
                                                ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
                                        $predicates = [
                                                $queryBuilder->expr()->eq(
                                                    't3ver_move_id',
                                                    $queryBuilder->createNamedParameter((int)$lRow['uid'], PDO::PARAM_INT)
                                                ),
                                                $queryBuilder->expr()->eq(
                                                    'pid',
                                                    $queryBuilder->createNamedParameter(
                                                        (int)$row['_MOVE_PLH_pid'],
                                                        PDO::PARAM_INT
                                                    )
                                                ),
                                                $queryBuilder->expr()->eq(
                                                    't3ver_wsid',
                                                    $queryBuilder->createNamedParameter(
                                                        (int)$row['t3ver_wsid'],
                                                        PDO::PARAM_INT
                                                    )
                                                ),
                                        ];

                                        $tmpRow = $queryBuilder
                                                ->select(...$selFieldList)
                                                ->from($table)
                                                ->andWhere(...$predicates)
                                                ->execute()
                                                ->fetch();

                                        $lRow = is_array($tmpRow) ? $tmpRow : $lRow;
                                    }
                                    if (!$this->isRowListingConditionFulfilled($table, $lRow)) {
                                        continue;
                                    }
                                    // In offline workspace, look for alternative record:
                                    BackendUtility::workspaceOL($table, $lRow, $backendUser->workspace, true);
                                    if (is_array($lRow) && $backendUser->checkLanguageAccess((int)$lRow[$GLOBALS['TCA'][$table]['ctrl']['languageField']])) {
                                        $this->currentIdList[] = $lRow['uid'];
                                        $rowOutput .= $this->renderListRow(
                                            $table,
                                            $lRow,
                                            $cc,
                                            $titleCol,
                                            $thumbsCol,
                                            18
                                        );
                                    }
                                }
                            }
                        }
                    }
                    // Counter of total rows incremented:
                    $this->eCounter++;
                }
                // Record navigation is added to the beginning and end of the table if in single
                // table mode
                if ($this->table) {
                    $rowOutput = '###REPLACE_LIST_NAVIGATION_TOP###' . $rowOutput . '###REPLACE_LIST_NAVIGATION_BOTTOM###';
                } else {
                    // Show that there are more records than shown
                    if ($this->totalItems > $this->itemsLimitPerTable) {
                        $countOnFirstPage = min($this->totalItems, $this->itemsLimitSingleTable);
                        $hasMore = $this->totalItems > $this->itemsLimitSingleTable;
                        $colspan = $this->showIcon
                                ? count($this->fieldArray) + 1 + $this->maxDepth
                                : count($this->fieldArray);
                        $rowOutput .= '<tr><td colspan="' . $colspan . '">
								<a href="' . htmlspecialchars(($this->listURL() . '&table=' . rawurlencode($tableIdentifier)))
                                . '" class="btn btn-default">'
                                . '<span class="t3-icon fa fa-chevron-down"></span> <i>[1 - '
                                . $countOnFirstPage . ($hasMore ? '+' : '') . ']</i></a>
								</td></tr>';
                    }
                }
            }

            $collapseClass = $tableCollapsed && !$this->table ? 'collapse' : 'collapse in';
            $dataState = $tableCollapsed && !$this->table ? 'collapsed' : 'expanded';

            // The list of records is added after the header:
            $out .= $rowOutput;
            // ... and it is all wrapped in a table:
            $out = '



			<!--
				DB listing of elements:	"' . htmlspecialchars($tableIdentifier) . '"
			-->
				<div class="panel panel-space panel-default recordlist">
					<div class="panel-heading">
					' . $tableHeader . '
					</div>
					<div class="' . $collapseClass . '" data-state="' . $dataState . '" id="recordlist-' . htmlspecialchars($tableIdentifier) . '">
						<div class="table-fit">
							<table data-table="' . htmlspecialchars($tableIdentifier) . '" class="table table-striped table-hover' . ($listOnlyInSingleTableMode ? ' typo3-dblist-overview' : '') . '">
								' . $out . '
							</table>
						</div>
					</div>
				</div>
			';
            // Output csv if...
            // This ends the page with exit.
            if ($this->csvOutput) {
                $this->outputCSV($table);
            }
        }
        // Return content:
        $out = str_replace('###REPLACE_LIST_HEADER###', $this->renderListHeader($table, $this->currentIdList), $out);
        $out = str_replace('###REPLACE_LIST_NAVIGATION_TOP###', $this->renderListNavigation(), $out);
        return str_replace('###REPLACE_LIST_NAVIGATION_BOTTOM###', $this->renderListNavigation('bottom'), $out);
    }

    /**
     * @return object|BackendLayoutView
     */
    protected function getBackendLayoutView()
    {
        return GeneralUtility::makeInstance(BackendLayoutView::class);
    }

    /**
     * Rendering a single row for the list
     *
     * @param string $table Table name
     * @param array $row Current record
     * @param int $cc Counter, counting for each time an element is rendered (used for alternating colors)
     * @param string $titleCol Table field (column) where header value is found
     * @param string $thumbsCol Table field (column) where (possible) thumbnails can be found
     * @param int $indent Indent from left.
     * @param int $level
     * @param int $triggerContainer
     * @param string $expanded
     * @return string Table row for the element
     * @throws RouteNotFoundException
     * @throws \Doctrine\DBAL\DBALException
     * @internal
     * @see getTable()
     */
    public function renderListRow(
        $table,
        $row,
        $cc,
        $titleCol,
        $thumbsCol,
        $indent = 0,
        int $level = 0,
        int $triggerContainer = 0,
        string $expanded = ''
    ): string {
        if (!is_array($row)) {
            return '';
        }
        $rowOutput = '';
        $id_orig = null;
        // If in search mode, make sure the preview will show the correct page
        if ($this->searchString !== '') {
            $id_orig = $this->id;
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
            && (int)$this->currentLink['uid'] === (int)$row['uid']
            && GeneralUtility::inList($this->currentLink['tableNames'], $table)
        ) {
            $tagAttributes['class'][] = 'active';
        }
        // Add special classes for first and last row
        if ($cc == 1 && $indent == 0) {
            $tagAttributes['class'][] = 'firstcol';
        }
        if ($cc == $this->totalRowCount || $cc == $this->iLimit) {
            $tagAttributes['class'][] = 'lastcol';
        }
        // Overriding with versions background color if any:
        if (!empty($row['_CSSCLASS'])) {
            $tagAttributes['class'] = [$row['_CSSCLASS']];
        }

        $tagAttributes['class'][] = 't3js-entity';

        // Incr. counter.
        $this->counter++;
        // The icon with link
        $toolTip = BackendUtility::getRecordToolTip($row, $table);
        $additionalStyle = $indent ? ' style="margin-left: ' . $indent . 'px;"' : '';
        $iconImg = '<span ' . $toolTip . ' ' . $additionalStyle . '>'
                . $this->iconFactory->getIconForRecord($table, $row, Icon::SIZE_SMALL)->render()
                . '</span>';
        $theIcon = $this->clickMenuEnabled ? BackendUtility::wrapClickMenuOnIcon(
            $iconImg,
            $table,
            $row['uid']
        ) : $iconImg;
        // Preparing and getting the data-array
        $theData = [];
        $localizationMarkerClass = '';
        $lC2 = '';
        foreach ($this->fieldArray as $fCol) {
            if ($fCol == $titleCol) {
                $recTitle = BackendUtility::getRecordTitle($table, $row);
                $warning = '';
                // If the record is edit-locked	by another user, we will show a little warning sign:
                $lockInfo = BackendUtility::isRecordLocked($table, $row['uid']);
                if ($lockInfo) {
                    $warning = '<span data-toggle="tooltip" data-placement="right" data-title="' . htmlspecialchars($lockInfo['msg']) . '">'
                            . $this->iconFactory->getIcon('warning-in-use', Icon::SIZE_SMALL)->render() . '</span>';
                }
                $theData[$fCol] = $theData['__label'] = $warning . $this->linkWrapItems(
                    $table,
                    $row['uid'],
                    $recTitle,
                    $row
                );
                // Render thumbnails, if:
                // - a thumbnail column exists
                // - there is content in it
                // - the thumbnail column is visible for the current type
                $type = 0;
                if (isset($GLOBALS['TCA'][$table]['ctrl']['type'])) {
                    $typeColumn = $GLOBALS['TCA'][$table]['ctrl']['type'];
                    $type = $row[$typeColumn];
                }
                // If current type doesn't exist, set it to 0 (or to 1 for historical reasons,
                // if 0 doesn't exist)
                if (!isset($GLOBALS['TCA'][$table]['types'][$type])) {
                    $type = isset($GLOBALS['TCA'][$table]['types'][0]) ? 0 : 1;
                }

                $visibleColumns = $this->getVisibleColumns($GLOBALS['TCA'][$table], (string)$type);

                if ($this->thumbs &&
                        trim((string)$row[$thumbsCol]) &&
                        preg_match('/(^|(.*(;|,)?))' . $thumbsCol . '(((;|,).*)|$)/', $visibleColumns) === 1
                ) {
                    $thumbCode = '<br />' . BackendUtility::thumbCode($row, $table, $thumbsCol);
                    $theData[$fCol] .= $thumbCode;
                    $theData['__label'] .= $thumbCode;
                }
                if (isset($GLOBALS['TCA'][$table]['ctrl']['languageField'])
                        && $row[$GLOBALS['TCA'][$table]['ctrl']['languageField']] != 0
                        && $row[$GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']] != 0
                ) {
                    // It's a translated record with a language parent
                    $localizationMarkerClass = ' localization';
                }
            } elseif ($fCol === 'pid') {
                $theData[$fCol] = $row[$fCol];
            } elseif ($fCol === '_PATH_') {
                $theData[$fCol] = $this->recPath($row['pid']);
            } elseif ($fCol === '_REF_') {
                $theData[$fCol] = $this->generateReferenceToolTip($table, $row['uid']);
            } elseif ($fCol === '_CONTROL_') {
                $theData[$fCol] = $this->makeControl($table, $row);
            } elseif ($fCol === '_CLIPBOARD_') {
                $theData[$fCol] = $this->makeClip($table, $row);
            } elseif ($fCol === '_LOCALIZATION_') {
                [$lC1, $lC2] = $this->makeLocalizationPanel($table, $row);
                $theData[$fCol] = $lC1;
            } elseif ($fCol !== '_LOCALIZATION_b') {
                $tmpProc = BackendUtility::getProcessedValueExtra($table, $fCol, $row[$fCol], 100, $row['uid']);
                $theData[$fCol] = $this->linkUrlMail(htmlspecialchars((string)$tmpProc), $row[$fCol]);
                if ($this->csvOutput) {
                    $row[$fCol] = BackendUtility::getProcessedValueExtra($table, $fCol, $row[$fCol], 0, $row['uid']);
                }
            } elseif ($fCol === '_LOCALIZATION_b') {
                $theData[$fCol] = $lC2;
            } else {
                $theData[$fCol] = htmlspecialchars(BackendUtility::getProcessedValueExtra(
                    $table,
                    $fCol,
                    $row[$fCol],
                    0,
                    $row['uid']
                ));
            }
        }
        // Reset the ID if it was overwritten
        if ($this->searchString !== '') {
            $this->id = $id_orig;
        }
        // Add row to CSV list:
        if ($this->csvOutput) {
            $hooks = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][__CLASS__]['customizeCsvRow'] ?? [];
            if (!empty($hooks)) {
                $hookParameters = [
                        'databaseRow' => &$row,
                        'tableName' => $table,
                        'pageId' => $this->id,
                ];
                foreach ($hooks as $hookFunction) {
                    GeneralUtility::callUserFunction($hookFunction, $hookParameters, $this);
                }
            }
            $this->addToCSV($row);
        }
        // Add classes to table cells
        $this->addElement_tdCssClass[$titleCol] = 'col-title col-responsive' . $localizationMarkerClass;
        $this->addElement_tdCssClass['__label'] = $this->addElement_tdCssClass[$titleCol];
        $this->addElement_tdCssClass['_CONTROL_'] = 'col-control';
        if ($this->moduleData['clipBoard']) {
            $this->addElement_tdCssClass['_CLIPBOARD_'] = 'col-clipboard';
        }
        $this->addElement_tdCssClass['_PATH_'] = 'col-path';
        $this->addElement_tdCssClass['_LOCALIZATION_'] = 'col-localizationa';
        $this->addElement_tdCssClass['_LOCALIZATION_b'] = 'col-localizationb';
        /**
         * @hook checkChildren
         * @date 2014-02-11
         * @request Alexander Grein <alexander.grein@in2code.de>
         */
        foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/class.db_list_extra.inc']['actions'] ?? [] as $className) {
            $hookObject = GeneralUtility::makeInstance($className);
            if (is_object($hookObject) && method_exists($hookObject, 'checkChildren')) {
                $hookObject->checkChildren($table, $row, $level, $theData, $this);
            }
        }
        // Create element in table cells:
        $theData['uid'] = $row['uid'];
        if ($table === 'tt_content') {
            $theData['tx_gridelements_container'] = (int)$row['tx_gridelements_container'];
        }
        if (isset($GLOBALS['TCA'][$table]['ctrl']['languageField'])
                && isset($GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'])
        ) {
            $theData['parent'] = $row[$GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']];
        }
        $tagAttributes = array_map(
            function ($attributeValue) {
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
        $rowOutput .= $this->addElement(1, $theIcon, $theData, GeneralUtility::implodeAttributes($tagAttributes, true), '', '', 'td', $level, $table);

        $translations = $this->translations;

        if ($theData['_EXPANDABLE_'] && $level < 8 && !empty($theData['_CHILDREN_'])) {
            $expanded = $this->expandedGridelements[$row['uid']] && (($this->expandedGridelements[$row['tx_gridelements_container']] && $expanded) || $row['tx_gridelements_container'] === 0) ? ' expanded' : '';
            $previousGridColumn = '';
            $originalMoveUp = $this->showMoveUp;
            $originalMoveDown = $this->showMoveDown;
            foreach ($theData['_CHILDREN_'] as $key => $child) {
                if (isset($child['tx_gridelements_columns']) && ($child['tx_gridelements_columns'] !== $previousGridColumn)) {
                    $previousGridColumn = $child['tx_gridelements_columns'];
                    $this->currentTable['prev'][$child['uid']] = (int)$row['pid'];
                } else {
                    if (isset($theData['_CHILDREN_'][$key - 2]) && $theData['_CHILDREN_'][$key - 2]['tx_gridelements_columns'] === $child['tx_gridelements_columns']) {
                        $this->currentTable['prev'][$child['uid']] = -(int)$theData['_CHILDREN_'][$key - 2]['uid'];
                    } else {
                        $this->currentTable['prev'][$child['uid']] = (int)$row['pid'];
                    }
                }
                if (isset($theData['_CHILDREN_'][$key + 1]) && $theData['_CHILDREN_'][$key + 1]['tx_gridelements_columns'] === $child['tx_gridelements_columns']) {
                    $this->currentTable['next'][$child['uid']] = -(int)$theData['_CHILDREN_'][$key + 1]['uid'];
                }
            }
            $previousGridColumn = '';
            foreach ($theData['_CHILDREN_'] as $key => $child) {
                if (isset($child['tx_gridelements_columns']) && ($child['tx_gridelements_columns'] !== $previousGridColumn)) {
                    $previousGridColumn = $child['tx_gridelements_columns'];
                    $this->showMoveUp = false;
                    $rowOutput .= '<tr class="t3-gridelements-child' . $expanded . '" data-trigger-container="'
                            . $row['uid']
                            . '" data-grid-container="' . $row['uid'] . '">
                                <td colspan="' . ($level + 2) . '"></td>
                                <td colspan="' . (count($this->fieldArray) - $level - 2 + $this->maxDepth) . '" style="padding:5px;">
                                <br>
                                    <strong>' . $this->getLanguageService()->sL('LLL:EXT:gridelements/Resources/Private/Language/locallang_db.xlf:list.containerColumnName')
                            . ' ' . $theData['_CONTAINER_COLUMNS_']['columns'][(int)$child['tx_gridelements_columns']] . '</strong>
                                </td>
                            </tr>';
                } else {
                    $this->showMoveUp = true;
                }
                $this->showMoveDown = !isset($child['tx_gridelements_columns']) || !isset($theData['_CHILDREN_'][$key + 1])
                        || (int)$child['tx_gridelements_columns'] === (int)$theData['_CHILDREN_'][$key + 1]['tx_gridelements_columns'];
                $this->currentIdList[] = $child['uid'];
                if ($row['CType'] === 'gridelements_pi1') {
                    $this->currentContainerIdList[] = $row['uid'];
                }
                $child['_CSSCLASS'] = 't3-gridelements-child' . $expanded;
                $rowOutput .= $this->renderListRow(
                    $table,
                    $child,
                    $cc,
                    $titleCol,
                    $thumbsCol,
                    0,
                    $level + 1,
                    $row['uid'],
                    $expanded
                );
            }
            $this->showMoveUp = $originalMoveUp;
            $this->showMoveDown = $originalMoveDown;
        }

        if ($this->l10nEnabled && $level === 0) {
            // For each available translation, render the record:
            if (is_array($translations)) {
                $expanded = $this->expandedGridelements[$row['uid']] && (($this->expandedGridelements[$row['tx_gridelements_container']] && $expanded) || $row['tx_gridelements_container'] === 0) ? ' expanded' : '';
                foreach ($translations as $lRow) {
                    // $lRow isn't always what we want - if record was moved we've to work with the
                    // placeholder records otherwise the list is messed up a bit
                    if ($row['_MOVE_PLH_uid'] && $row['_MOVE_PLH_pid']) {
                        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                                ->getQueryBuilderForTable($table);
                        $queryBuilder->getRestrictions()
                                ->removeAll()
                                ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
                        $predicates = [
                                $queryBuilder->expr()->eq(
                                    't3ver_move_id',
                                    $queryBuilder->createNamedParameter((int)$lRow['uid'], PDO::PARAM_INT)
                                ),
                                $queryBuilder->expr()->eq(
                                    'pid',
                                    $queryBuilder->createNamedParameter((int)$row['_MOVE_PLH_pid'], PDO::PARAM_INT)
                                ),
                                $queryBuilder->expr()->eq(
                                    't3ver_wsid',
                                    $queryBuilder->createNamedParameter((int)$row['t3ver_wsid'], PDO::PARAM_INT)
                                ),
                        ];

                        $tmpRow = $queryBuilder
                                ->select(...$this->selFieldList)
                                ->from($table)
                                ->andWhere(...$predicates)
                                ->execute()
                                ->fetch();

                        $lRow = is_array($tmpRow) ? $tmpRow : $lRow;
                    }
                    // In offline workspace, look for alternative record:
                    BackendUtility::workspaceOL($table, $lRow, $this->getBackendUserAuthentication()->workspace, true);
                    if (is_array($lRow) && $this->getBackendUserAuthentication()->checkLanguageAccess((int)$lRow[$GLOBALS['TCA'][$table]['ctrl']['languageField']])) {
                        $this->currentIdList[] = $lRow['uid'];
                        if ($row['tx_gridelements_container']) {
                            $lRow['_CSSCLASS'] = 't3-gridelements-child' . $expanded;
                        }
                        $rowOutput .= $this->renderListRow(
                            $table,
                            $lRow,
                            $cc,
                            $titleCol,
                            $thumbsCol,
                            20,
                            $level,
                            (int)$row['tx_gridelements_container'],
                            $expanded
                        );
                    }
                }
            }
        }

        // Finally, return table row element:
        return $rowOutput;
    }

    /*********************************
     *
     * Helper functions
     *
     *********************************/

    /**
     * Creates the control panel for a single record in the listing.
     *
     * @param string $table The table
     * @param array $row The record for which to make the control panel.
     * @return string HTML table with the control panel (unless disabled)
     * @throws RouteNotFoundException
     * @throws UnexpectedValueException
     */
    public function makeControl($table, $row): string
    {
        $userTsConfig = $this->getBackendUserAuthentication()->getTSConfig();
        $rowUid = $row['uid'];
        if (ExtensionManagementUtility::isLoaded('workspaces') && isset($row['_ORIG_uid'])) {
            $rowUid = $row['_ORIG_uid'];
        }
        $cells = [
                'primary' => [],
                'secondary' => [],
        ];
        // Enables to hide the move elements for localized records - doesn't make much sense to perform these options for them
        // For page translations these icons should never be shown
        $isL10nOverlay = $table === 'pages' && $row[$GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']] != 0;
        // If the listed table is 'pages' we have to request the permission settings for each page:
        $localCalcPerms = 0;
        if ($table === 'pages') {
            // If the listed table is 'pages' we have to request the permission settings for each page.
            $localCalcPerms = $this->getBackendUserAuthentication()->calcPerms(BackendUtility::getRecord(
                'pages',
                $row['uid']
            ));
        } else {
            // If the listed table is not 'pages' we have to request the permission settings from the parent page
            $localCalcPerms = $this->getBackendUserAuthentication()->calcPerms(BackendUtility::getRecord(
                'pages',
                $row['pid']
            ));
        }
        $permsEdit = $table === 'pages'
                && $this->getBackendUserAuthentication()->checkLanguageAccess((int)$row[$GLOBALS['TCA']['pages']['ctrl']['languageField']])
                && $localCalcPerms & Permission::PAGE_EDIT
                || $table !== 'pages'
                && $localCalcPerms & Permission::CONTENT_EDIT
                && $this->getBackendUserAuthentication()->recordEditAccessInternals($table, $row);
        $permsEdit = $this->overlayEditLockPermissions($table, $row, $permsEdit);
        // "Show" link (only pages and tt_content elements)
        /** @var UriBuilder $uriBuilder */
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);

        if ($table === 'pages' || $table === 'tt_content') {
            $onClick = $this->getOnClickForRow($table, $row);
            $viewAction = '<a class="btn btn-default" href="#" onclick="'
                    . htmlspecialchars(
                        $onClick
                    ) . '" title="' . htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.showPage')) . '">';
            if ($table === 'pages') {
                $viewAction .= $this->iconFactory->getIcon('actions-view-page', Icon::SIZE_SMALL)->render();
            } else {
                $viewAction .= $this->iconFactory->getIcon('actions-view', Icon::SIZE_SMALL)->render();
            }
            $viewAction .= '</a>';
            $this->addActionToCellGroup($cells, $viewAction, 'view');
        }
        // "Edit" link: ( Only if permissions to edit the page-record of the content of the parent page ($this->id)
        if ($permsEdit && $this->isEditable($table)) {
            $params = '&edit[' . $table . '][' . $row['uid'] . ']=edit';
            $iconIdentifier = 'actions-open';
            if ($table === 'pages') {
                // Disallow manual adjustment of the language field for pages
                $params .= '&overrideVals[pages][sys_language_uid]=' . (int)$row[$GLOBALS['TCA']['pages']['ctrl']['languageField']];
                $iconIdentifier = 'actions-page-open';
            }
            $editAction = '<a class="btn btn-default" href="#" onclick="' . htmlspecialchars(BackendUtility::editOnClick(
                $params,
                '',
                -1
            ))
                    . '" title="' . htmlspecialchars($this->getLanguageService()->getLL('edit')) . '">' . $this->iconFactory->getIcon(
                        $iconIdentifier,
                        Icon::SIZE_SMALL
                    )->render() . '</a>';
        } else {
            $editAction = $this->spaceIcon;
        }
        $this->addActionToCellGroup($cells, $editAction, 'edit');
        // "Info": (All records)
        $onClick = 'top.TYPO3.InfoWindow.showItem(' . GeneralUtility::quoteJSvalue($table) . ', ' . (int)$row['uid'] . '); return false;';
        $viewBigAction = '<a class="btn btn-default" href="#" onclick="' . htmlspecialchars($onClick) . '" title="' . htmlspecialchars($this->getLanguageService()->getLL('showInfo')) . '">'
                . $this->iconFactory->getIcon('actions-document-info', Icon::SIZE_SMALL)->render() . '</a>';
        $this->addActionToCellGroup($cells, $viewBigAction, 'viewBig');
        // "Move" wizard link for pages/tt_content elements:
        if ($permsEdit && ($table === 'tt_content' || $table === 'pages') && $this->isEditable($table)) {
            if ($isL10nOverlay) {
                $moveAction = $this->spaceIcon;
            } else {
                $onClick = 'return jumpExt(' . GeneralUtility::quoteJSvalue($uriBuilder->buildUriFromRoute('move_element') . '&table=' . $table . '&uid=' . $row['uid']) . ');';
                $linkTitleLL = htmlspecialchars($this->getLanguageService()->getLL('move_' . ($table === 'tt_content' ? 'record' : 'page')));
                $icon = ($table === 'pages' ? $this->iconFactory->getIcon(
                    'actions-page-move',
                    Icon::SIZE_SMALL
                ) : $this->iconFactory->getIcon('actions-document-move', Icon::SIZE_SMALL));
                $moveAction = '<a class="btn btn-default" href="#" onclick="' . htmlspecialchars($onClick) . '" title="' . $linkTitleLL . '">' . $icon->render() . '</a>';
            }
            $this->addActionToCellGroup($cells, $moveAction, 'move');
        }
        // If the table is NOT a read-only table, then show these links:
        if ($this->isEditable($table)) {
            // "Revert" link (history/undo)
            if (trim($userTsConfig['options.']['showHistory.'][$table] ?? $userTsConfig['options.']['showHistory'] ?? '1')) {
                $moduleUrl = (string)$uriBuilder->buildUriFromRoute(
                    'record_history',
                    ['element' => $table . ':' . $row['uid']]
                );
                $onClick = 'return jumpExt(' . GeneralUtility::quoteJSvalue($moduleUrl) . ',\'#latest\');';
                $historyAction = '<a class="btn btn-default" href="#" onclick="' . htmlspecialchars($onClick) . '" title="'
                        . htmlspecialchars($this->getLanguageService()->getLL('history')) . '">'
                        . $this->iconFactory->getIcon('actions-document-history-open', Icon::SIZE_SMALL)->render() . '</a>';
                $this->addActionToCellGroup($cells, $historyAction, 'history');
            }
            // "Edit Perms" link:
            if ($table === 'pages' && $this->getBackendUserAuthentication()->check(
                'modules',
                'system_BeuserTxPermission'
            ) && ExtensionManagementUtility::isLoaded('beuser')) {
                if ($isL10nOverlay) {
                    $permsAction = $this->spaceIcon;
                } else {
                    $href = $uriBuilder->buildUriFromRoute('system_BeuserTxPermission') . '&id=' . $row['uid'] . '&tx_beuser_system_beusertxpermission[action]=edit' . $this->makeReturnUrl();
                    $permsAction = '<a class="btn btn-default" href="' . htmlspecialchars($href) . '" title="'
                            . htmlspecialchars($this->getLanguageService()->getLL('permissions')) . '">'
                            . $this->iconFactory->getIcon('actions-lock', Icon::SIZE_SMALL)->render() . '</a>';
                }
                $this->addActionToCellGroup($cells, $permsAction, 'perms');
            }
            // "New record after" link (ONLY if the records in the table are sorted by a "sortby"-row
            // or if default values can depend on previous record):
            if (($GLOBALS['TCA'][$table]['ctrl']['sortby'] || $GLOBALS['TCA'][$table]['ctrl']['useColumnsForDefaultValues']) && $permsEdit) {
                if ($table !== 'pages' && $this->calcPerms & Permission::CONTENT_EDIT || $table === 'pages' && $this->calcPerms & Permission::PAGE_NEW) {
                    if ($table === 'pages' && $isL10nOverlay) {
                        $this->addActionToCellGroup($cells, $this->spaceIcon, 'new');
                    } elseif ($this->showNewRecLink($table)) {
                        $params = '&edit[' . $table . '][' . -($row['_MOVE_PLH'] ? $row['_MOVE_PLH_uid'] : $row['uid']) . ']=new';
                        $icon = ($table === 'pages' ? $this->iconFactory->getIcon(
                            'actions-page-new',
                            Icon::SIZE_SMALL
                        ) : $this->iconFactory->getIcon('actions-add', Icon::SIZE_SMALL));
                        $titleLabel = 'new';
                        if ($GLOBALS['TCA'][$table]['ctrl']['sortby']) {
                            $titleLabel .= ($table === 'pages' ? 'Page' : 'Record');
                        }
                        $newAction = '<a class="btn btn-default" href="#" onclick="' . htmlspecialchars(BackendUtility::editOnClick(
                            $params,
                            '',
                            -1
                        ))
                                . '" title="' . htmlspecialchars($this->getLanguageService()->getLL($titleLabel)) . '">'
                                . $icon->render() . '</a>';
                        $this->addActionToCellGroup($cells, $newAction, 'new');
                    }
                }
            }
            // "Up/Down" links
            if ($permsEdit && $GLOBALS['TCA'][$table]['ctrl']['sortby'] && !$this->sortField && !$this->searchLevels) {
                if (isset($this->currentTable['prev'][$row['uid']]) && $this->showMoveUp === true && !$isL10nOverlay) {
                    // Up
                    $params = '&cmd[' . $table . '][' . $row['uid'] . '][move]=' . $this->currentTable['prev'][$row['uid']];
                    $moveUpAction = '<a class="btn btn-default" href="#" onclick="'
                            . htmlspecialchars('return jumpToUrl(' . BackendUtility::getLinkToDataHandlerAction(
                                $params,
                                -1
                            ) . ');')
                            . '" title="' . htmlspecialchars($this->getLanguageService()->getLL('moveUp')) . '">'
                            . $this->iconFactory->getIcon('actions-move-up', Icon::SIZE_SMALL)->render() . '</a>';
                } else {
                    $moveUpAction = $this->spaceIcon;
                }
                $this->addActionToCellGroup($cells, $moveUpAction, 'moveUp');

                if ($this->currentTable['next'][$row['uid']] && $this->showMoveDown === true && !$isL10nOverlay) {
                    // Down
                    $params = '&cmd[' . $table . '][' . $row['uid'] . '][move]=' . $this->currentTable['next'][$row['uid']];
                    $moveDownAction = '<a class="btn btn-default" href="#" onclick="'
                            . htmlspecialchars('return jumpToUrl(' . BackendUtility::getLinkToDataHandlerAction(
                                $params,
                                -1
                            ) . ');')
                            . '" title="' . htmlspecialchars($this->getLanguageService()->getLL('moveDown')) . '">'
                            . $this->iconFactory->getIcon('actions-move-down', Icon::SIZE_SMALL)->render() . '</a>';
                } else {
                    $moveDownAction = $this->spaceIcon;
                }
                $this->addActionToCellGroup($cells, $moveDownAction, 'moveDown');
            }
            // "Hide/Unhide" links:
            $hiddenField = $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['disabled'];

            if (
                !empty($GLOBALS['TCA'][$table]['columns'][$hiddenField])
                && (empty($GLOBALS['TCA'][$table]['columns'][$hiddenField]['exclude'])
                        || $this->getBackendUserAuthentication()->check('non_exclude_fields', $table . ':' . $hiddenField))
            ) {
                if (!$permsEdit || $this->isRecordCurrentBackendUser($table, $row)) {
                    $hideAction = $this->spaceIcon;
                } else {
                    $hideTitle = htmlspecialchars($this->getLanguageService()->getLL('hide' . ($table === 'pages' ? 'Page' : '')));
                    $unhideTitle = htmlspecialchars($this->getLanguageService()->getLL('unHide' . ($table === 'pages' ? 'Page' : '')));
                    if ($row[$hiddenField]) {
                        $params = 'data[' . $table . '][' . $rowUid . '][' . $hiddenField . ']=0';
                        $hideAction = '<a class="btn btn-default t3js-record-hide" data-state="hidden" href="#"'
                                . ' data-params="' . htmlspecialchars($params) . '"'
                                . ' title="' . $unhideTitle . '"'
                                . ' data-toggle-title="' . $hideTitle . '">'
                                . $this->iconFactory->getIcon('actions-edit-unhide', Icon::SIZE_SMALL)->render() . '</a>';
                    } else {
                        $params = 'data[' . $table . '][' . $rowUid . '][' . $hiddenField . ']=1';
                        $hideAction = '<a class="btn btn-default t3js-record-hide" data-state="visible" href="#"'
                                . ' data-params="' . htmlspecialchars($params) . '"'
                                . ' title="' . $hideTitle . '"'
                                . ' data-toggle-title="' . $unhideTitle . '">'
                                . $this->iconFactory->getIcon('actions-edit-hide', Icon::SIZE_SMALL)->render() . '</a>';
                    }
                }
                $this->addActionToCellGroup($cells, $hideAction, 'hide');
            }
            // "Delete" link:
            $disableDelete = (bool)trim($userTsConfig['options.']['disableDelete.'][$table] ?? $userTsConfig['options.']['disableDelete'] ?? '0');
            if ($permsEdit && !$disableDelete && ($table === 'pages' && $localCalcPerms & Permission::PAGE_DELETE || $table !== 'pages' && $this->calcPerms & Permission::CONTENT_EDIT)) {
                // Check if the record version is in "deleted" state, because that will switch the action to "restore"
                if ($this->getBackendUserAuthentication()->workspace > 0 && isset($row['t3ver_state']) && VersionState::cast($row['t3ver_state'])->equals(VersionState::DELETE_PLACEHOLDER)) {
                    $actionName = 'restore';
                    $refCountMsg = '';
                } else {
                    $actionName = 'delete';
                    $refCountMsg = BackendUtility::referenceCount(
                        $table,
                        $row['uid'],
                        ' ' . $this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.referencesToRecord'),
                        $this->getReferenceCount($table, $row['uid'])
                    ) . BackendUtility::translationCount(
                        $table,
                        $row['uid'],
                        ' ' . $this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.translationsOfRecord')
                    );
                }

                if ($this->isRecordCurrentBackendUser($table, $row)) {
                    $deleteAction = $this->spaceIcon;
                } else {
                    $title = BackendUtility::getRecordTitle($table, $row);
                    $warningText = $this->getLanguageService()->getLL($actionName . 'Warning') . ' "' . $title . '" ' . '[' . $table . ':' . $row['uid'] . ']' . $refCountMsg;

                    $params = 'cmd[' . $table . '][' . $row['uid'] . '][delete]=1';
                    $icon = $this->iconFactory->getIcon('actions-edit-' . $actionName, Icon::SIZE_SMALL)->render();
                    $linkTitle = htmlspecialchars($this->getLanguageService()->getLL($actionName));
                    $l10nParentField = $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'] ?? '';
                    $deleteAction = '<a class="btn btn-default t3js-record-delete" href="#" '
                            . ' data-button-ok-text="' . htmlspecialchars($linkTitle) . '"'
                            . ' data-l10parent="' . ($l10nParentField ? htmlspecialchars((string)$row[$l10nParentField]) : '') . '"'
                            . ' data-params="' . htmlspecialchars($params) . '" data-title="' . htmlspecialchars($title) . '"'
                            . ' data-message="' . htmlspecialchars($warningText) . '" title="' . $linkTitle . '"'
                            . '>' . $icon . '</a>';
                }
            } else {
                $deleteAction = $this->spaceIcon;
            }
            $this->addActionToCellGroup($cells, $deleteAction, 'delete');
            // "Levels" links: Moving pages into new levels...
            if ($permsEdit && $table === 'pages' && !$this->searchLevels) {
                // Up (Paste as the page right after the current parent page)
                if ($this->calcPerms & Permission::PAGE_NEW) {
                    $params = '&cmd[' . $table . '][' . $row['uid'] . '][move]=' . -$this->id;
                    $moveLeftAction = '<a class="btn btn-default" href="#" onclick="'
                            . htmlspecialchars('return jumpToUrl(' . BackendUtility::getLinkToDataHandlerAction(
                                $params,
                                -1
                            ) . ');')
                            . '" title="' . htmlspecialchars($this->getLanguageService()->getLL('prevLevel')) . '">'
                            . $this->iconFactory->getIcon('actions-move-left', Icon::SIZE_SMALL)->render() . '</a>';
                    $this->addActionToCellGroup(
                        $cells,
                        $isL10nOverlay ? $this->spaceIcon : $moveLeftAction,
                        'moveLeft'
                    );
                }
                // Down (Paste as subpage to the page right above)
                if (!$isL10nOverlay && $this->currentTable['prevUid'][$row['uid']]) {
                    $localCalcPerms = $this->getBackendUserAuthentication()->calcPerms(BackendUtility::getRecord(
                        'pages',
                        $this->currentTable['prevUid'][$row['uid']]
                    ));
                    if ($localCalcPerms & Permission::PAGE_NEW) {
                        $params = '&cmd[' . $table . '][' . $row['uid'] . '][move]=' . $this->currentTable['prevUid'][$row['uid']];
                        $moveRightAction = '<a class="btn btn-default" href="#" onclick="'
                                . htmlspecialchars('return jumpToUrl(' . BackendUtility::getLinkToDataHandlerAction(
                                    $params,
                                    -1
                                ) . ');')
                                . '" title="' . htmlspecialchars($this->getLanguageService()->getLL('nextLevel')) . '">'
                                . $this->iconFactory->getIcon('actions-move-right', Icon::SIZE_SMALL)->render() . '</a>';
                    } else {
                        $moveRightAction = $this->spaceIcon;
                    }
                } else {
                    $moveRightAction = $this->spaceIcon;
                }
                $this->addActionToCellGroup($cells, $moveRightAction, 'moveRight');
            }
        }
        /*
         * hook: recStatInfoHooks: Allows to insert HTML before record icons on various places
         */
        $hooks = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['GLOBAL']['recStatInfoHooks'] ?? [];
        if (!empty($hooks)) {
            $stat = '';
            $_params = [$table, $row['uid']];
            foreach ($hooks as $_funcRef) {
                $stat .= GeneralUtility::callUserFunction($_funcRef, $_params, $this);
            }
            $this->addActionToCellGroup($cells, $stat, 'stat');
        }
        /*
         * hook:  makeControl: Allows to change control icons of records in list-module
         * usage: This hook method gets passed the current $cells array as third parameter.
         *        This array contains values for the icons/actions generated for each record in Web>List.
         *        Each array entry is accessible by an index-key.
         *        The order of the icons is depending on the order of those array entries.
         */
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/class.db_list_extra.inc']['actions'] ?? false)) {
            // for compatibility reason, we move all icons to the rootlevel
            // before calling the hooks
            foreach ($cells as $section => $actions) {
                foreach ($actions as $actionKey => $action) {
                    $cells[$actionKey] = $action;
                }
            }
            foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/class.db_list_extra.inc']['actions'] as $className) {
                $hookObject = GeneralUtility::makeInstance($className);
                if (!$hookObject instanceof RecordListHookInterface) {
                    throw new UnexpectedValueException(
                        $className . ' must implement interface ' . RecordListHookInterface::class,
                        1195567840
                    );
                }
                $cells = $hookObject->makeControl($table, $row, $cells, $this);
            }
            // now sort icons again into primary and secondary sections
            // after all hooks are processed
            $hookCells = $cells;
            foreach ($hookCells as $key => $value) {
                if ($key === 'primary' || $key === 'secondary') {
                    continue;
                }
                $this->addActionToCellGroup($cells, $value, $key);
            }
        }
        $output = '<!-- CONTROL PANEL: ' . $table . ':' . $row['uid'] . ' -->';
        foreach ($cells as $classification => $actions) {
            $visibilityClass = ($classification !== 'primary' && !$this->moduleData['bigControlPanel'] ? 'collapsed' : 'expanded');
            if ($visibilityClass === 'collapsed') {
                $cellOutput = implode('', $actions);
                $output .= ' <div class="btn-group">' .
                        '<span id="actions_' . $table . '_' . $row['uid'] . '" class="btn-group collapse collapse-horizontal width">' . $cellOutput . '</span>' .
                        '<a href="#actions_' . $table . '_' . $row['uid'] . '" class="btn btn-default collapsed" data-toggle="collapse" aria-expanded="false"><span class="t3-icon fa fa-ellipsis-h"></span></a>' .
                        '</div>';
            } else {
                $output .= ' <div class="btn-group" role="group">' . implode('', $actions) . '</div>';
            }
        }
        return $output;
    }

    /**
     * Returns a table-row with the content from the fields in the input data array.
     * OBS: $this->fieldArray MUST be set! (represents the list of fields to display)
     *
     * @param int $h Is an integer >=0 and denotes how tall an element is. Set to '0' makes a halv line, -1 = full line, set to 1 makes a 'join' and above makes 'line'
     * @param string $icon Is the <img>+<a> of the record. If not supplied the first 'join'-icon will be a 'line' instead
     * @param array $data Is the dataarray, record with the fields. Notice: These fields are (currently) NOT htmlspecialchar'ed before being wrapped in <td>-tags
     * @param string $rowParams Is insert in the <tr>-tags. Must carry a ' ' as first character
     * @param string $_ OBSOLETE - NOT USED ANYMORE. $lMargin is the leftMargin (int)
     * @param string $_2 OBSOLETE - NOT USED ANYMORE. Is the HTML <img>-tag for an alternative 'gfx/ol/line.gif'-icon (used in the top)
     * @param string $colType Defines the tag being used for the columns. Default is td.
     * @param int $level
     * @param string $table
     *
     * @return string HTML content for the table row
     */
    public function addElement($h, $icon, $data, $rowParams = '', $_ = '', $_2 = '', $colType = 'td', int $level = 0, string $table = ''): string
    {
        if ($colType === 'pagination') {
            $colType = 'td';
            $pagination = true;
        } else {
            $colType = ($colType === 'th') ? 'th' : 'td';
            $pagination = false;
        }
        $noWrap = $this->no_noWrap ? '' : ' nowrap="nowrap"';
        // Start up:
        $parent = isset($data['_l10nparent_']) ? (int)$data['_l10nparent_'] : 0;
        $out = '
		<!-- Element, begin: -->
		<tr ' . $rowParams . ' data-uid="' . (int)$data['uid'] . '" data-l10nparent="' . $parent . '"' .
                ((int)$data['tx_gridelements_container'] > 0 ? ' data-grid-container="' . $data['tx_gridelements_container'] . '"' : '') .
                ((int)$data['_triggerContainer'] > 0 ? ' data-trigger-container="' . $data['_triggerContainer'] . '"' : '') . '>';
        if (count($data) > 1) {
            $colsp = ' colspan="' . ($level + 1) . '"';

            if ($data['_EXPANDABLE_']) {
                $sortField = GeneralUtility::_GP('sortField') ? GeneralUtility::_GP('sortField') . ':' . (int)GeneralUtility::_GP('sortRev') : '';
                $contentCollapseIcon = '';
                /**
                 * @hook contentCollapseIcon
                 * @date 2014-02-11
                 * @request Alexander Grein <alexander.grein@in2code.de>
                 */
                foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/class.db_list_extra.inc']['actions'] ?? [] as $className) {
                    $hookObject = GeneralUtility::makeInstance($className);
                    if (is_object($hookObject) && method_exists($hookObject, 'contentCollapseIcon')) {
                        $hookObject->contentCollapseIcon($data, $sortField, $level, $contentCollapseIcon, $this);
                    }
                }
                $out .= '<' . $colType . $colsp . ' nowrap="nowrap" class="col-icon">' . $contentCollapseIcon . '</' . $colType . '>';
            } else {
                if ($table === 'tt_content' && $colType === 'td') {
                    $out .= '<' . $colType . $colsp . '></' . $colType . '>';
                }
            }
        }
        // Show icon and lines
        if ($this->showIcon) {
            $out .= '
			<' . $colType . ' nowrap="nowrap" class="col-icon">';
            if (!$h) {
                $out .= '&nbsp;';
            } else {
                for ($a = 0; $a < $h; $a++) {
                    if (!$a) {
                        if ($icon) {
                            $out .= $icon;
                        }
                    }
                }
            }
            $out .= '</' . $colType . '>
			';
        }
        // Init rendering.
        $colsp = '';
        $lastKey = '';
        $c = 0;
        $ccount = 0;
        // __label is used as the label key to circumvent problems with uid used as label (see #67756)
        // as it was introduced later on, check if it really exists before using it
        $fields = $this->fieldArray;
        if ($colType === 'td' && array_key_exists('__label', $data)) {
            $fields[0] = '__label';
        }
        // Traverse field array which contains the data to present:
        foreach ($fields as $vKey) {
            if (isset($data[$vKey])) {
                if ($lastKey) {
                    $cssClass = $this->addElement_tdCssClass[$lastKey];
                    if ($this->oddColumnsCssClass && $ccount % 2 == 0) {
                        $cssClass = implode(
                            ' ',
                            [$this->addElement_tdCssClass[$lastKey], $this->oddColumnsCssClass]
                        );
                    }
                    $out .= '
						<' . $colType . $noWrap . ' class="' . $cssClass . '"' . $colsp
                            . $this->addElement_tdParams[$lastKey] . '>' . $data[$lastKey] . '</' . $colType . '>';
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
            if (count($data) == 1) {
                $c++;
            }
            if ($pagination) {
                $colsp = ' colspan="' . ($this->totalColumnCount - 1) . '"';
            } elseif ($c > 1) {
                $colsp = ' colspan="2"';
            } elseif ($ccount === 1 && $colType === 'td') {
                $colsp = ' colspan="' . ($this->maxDepth - $level - 1) . '"';
            } elseif ($ccount === 1 && $colType === 'th') {
                if ($table === 'tt_content') {
                    $colsp = ' colspan="' . ($this->maxDepth - $level) . '"';
                } else {
                    $colsp = ' colspan="' . ($this->maxDepth - $level - 1) . '"';
                }
            } else {
                $colsp = '';
            }
        }
        if ($lastKey) {
            $cssClass = $this->addElement_tdCssClass[$lastKey];
            if ($this->oddColumnsCssClass) {
                $cssClass = implode(' ', [$this->addElement_tdCssClass[$lastKey], $this->oddColumnsCssClass]);
            }
            $out .= '
				<' . $colType . $noWrap . ' class="' . $cssClass . '"' . $colsp
                    . $this->addElement_tdParams[$lastKey] . '>' . $data[$lastKey] . '</' . $colType . '>';
        }
        // End row
        $out .= '
		</tr>';
        // Return row.
        return $out;
    }

    /**
     * Rendering the header row for a table
     *
     * @param string $table Table name
     * @param int[] $currentIdList Array of the currently displayed uids of the table
     * @return string Header table row
     * @throws RouteNotFoundException
     * @throws UnexpectedValueException
     * @internal
     * @see getTable()
     */
    public function renderListHeader($table, $currentIdList): string
    {
        $tsConfig = BackendUtility::getPagesTSconfig($this->id);
        $tsConfigOfTable = is_array($tsConfig['TCEFORM.'][$table . '.']) ? $tsConfig['TCEFORM.'][$table . '.'] : null;
        $lang = $this->getLanguageService();
        // Init:
        $theData = [];
        $icon = '';
        /** @var UriBuilder $uriBuilder */
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        // Traverse the fields:
        foreach ($this->fieldArray as $fCol) {
            // Calculate users permissions to edit records in the table:
            $permsEdit = $this->calcPerms & ($table === 'pages' ? 2 : 16) && $this->overlayEditLockPermissions($table);
            switch ((string)$fCol) {
                case '_PATH_':
                    // Path
                    $theData[$fCol] = '<i>[' . htmlspecialchars($lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels._PATH_')) . ']</i>';
                    break;
                case '_REF_':
                    // References
                    $theData[$fCol] = '<i>[' . htmlspecialchars($lang->sL('LLL:EXT:filelist/Resources/Private/Language/locallang_mod_file_list.xlf:c__REF_')) . ']</i>';
                    break;
                case '_LOCALIZATION_':
                    // Path
                    $theData[$fCol] = '<i>[' . htmlspecialchars($lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels._LOCALIZATION_')) . ']</i>';
                    break;
                case '_LOCALIZATION_b':
                    // Path
                    $theData[$fCol] = htmlspecialchars($lang->getLL('Localize'));
                    break;
                case '_CLIPBOARD_':
                    if (!$this->moduleData['clipBoard']) {
                        break;
                    }
                    // Clipboard:
                    $cells = [];
                    // If there are elements on the clipboard for this table, and the parent page is not locked by editlock
                    // then display the "paste into" icon:
                    $elFromTable = $this->clipObj->elFromTable($table);
                    if (!empty($elFromTable) && $this->overlayEditLockPermissions($table)) {
                        $href = htmlspecialchars($this->clipObj->pasteUrl($table, $this->id));
                        $confirmMessage = $this->clipObj->confirmMsgText('pages', $this->pageRow, 'into', $elFromTable);
                        $cells['pasteAfter'] = '<a class="btn btn-default t3js-modal-trigger"'
                                . ' href="' . $href . '"'
                                . ' title="' . htmlspecialchars($lang->getLL('clip_paste')) . '"'
                                . ' data-title="' . htmlspecialchars($lang->getLL('clip_paste')) . '"'
                                . ' data-content="' . htmlspecialchars($confirmMessage) . '"'
                                . ' data-severity="warning">'
                                . $this->iconFactory->getIcon('actions-document-paste-into', Icon::SIZE_SMALL)->render()
                                . '</a>';
                    }
                    // If the numeric clipboard pads are enabled, display the control icons for that:
                    if ($this->clipObj->current !== 'normal') {
                        // The "select" link:
                        $spriteIcon = $this->iconFactory->getIcon('actions-edit-copy', Icon::SIZE_SMALL)->render();
                        $cells['copyMarked'] = $this->linkClipboardHeaderIcon(
                            $spriteIcon,
                            $table,
                            'setCB',
                            '',
                            $lang->getLL('clip_selectMarked')
                        );
                        // The "edit marked" link:
                        $editUri = $uriBuilder->buildUriFromRoute('record_edit')
                                . '&edit[' . $table . '][{entityIdentifiers:editList}]=edit'
                                . '&returnUrl={T3_THIS_LOCATION}';
                        $cells['edit'] = '<a class="btn btn-default t3js-record-edit-multiple" href="#"'
                                . ' data-uri="' . htmlspecialchars($editUri) . '"'
                                . ' title="' . htmlspecialchars($lang->getLL('clip_editMarked')) . '">'
                                . $this->iconFactory->getIcon('actions-document-open', Icon::SIZE_SMALL)->render() . '</a>';
                        // The "Delete marked" link:
                        $cells['delete'] = $this->linkClipboardHeaderIcon(
                            $this->iconFactory->getIcon('actions-edit-delete', Icon::SIZE_SMALL)->render(),
                            $table,
                            'delete',
                            sprintf(
                                $lang->getLL('clip_deleteMarkedWarning'),
                                $lang->sL($GLOBALS['TCA'][$table]['ctrl']['title'])
                            ),
                            $lang->getLL('clip_deleteMarked')
                        );
                        // The "Select all" link:
                        $cells['markAll'] = '<a class="btn btn-default t3js-toggle-all-checkboxes" data-checkboxes-names="' . htmlspecialchars(implode(',', $this->CBnames)) . '" rel="" href="#" title="'
                                . htmlspecialchars($lang->getLL('clip_markRecords')) . '">'
                                . $this->iconFactory->getIcon(
                                    'actions-document-select',
                                    Icon::SIZE_SMALL
                                )->render() . '</a>';
                    } else {
                        $cells['empty'] = '';
                    }
                    /*
                     * hook:  renderListHeaderActions: Allows to change the clipboard icons of the Web>List table headers
                     * usage: Above each listed table in Web>List a header row is shown.
                     *        This hook allows to modify the icons responsible for the clipboard functions
                     *        (shown above the clipboard checkboxes when a clipboard other than "Normal" is selected),
                     *        or other "Action" functions which perform operations on the listed records.
                     */
                    foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/class.db_list_extra.inc']['actions'] ?? [] as $className) {
                        $hookObject = GeneralUtility::makeInstance($className);
                        if (!$hookObject instanceof RecordListHookInterface) {
                            throw new UnexpectedValueException(
                                $className . ' must implement interface ' . RecordListHookInterface::class,
                                1195567850
                            );
                        }
                        $cells = $hookObject->renderListHeaderActions($table, $currentIdList, $cells, $this);
                    }
                    $theData[$fCol] = '';
                    if (isset($cells['edit']) && isset($cells['delete'])) {
                        $theData[$fCol] .= '<div class="btn-group" role="group">' . $cells['edit'] . $cells['delete'] . '</div>';
                        unset($cells['edit'], $cells['delete']);
                    }
                    $theData[$fCol] .= '<div class="btn-group" role="group">' . implode('', $cells) . '</div>';
                    break;
                case '_CONTROL_':
                    // Control panel:
                    if ($this->isEditable($table)) {
                        // If new records can be created on this page, add links:
                        $permsAdditional = $table === 'pages' ? 8 : 16;
                        if ($table === 'tt_content') {
                            $expandTitle = $lang->sL('LLL:EXT:gridelements/Resources/Private/Language/locallang_db.xlf:list.expandAllElements');
                            $collapseTitle = $lang->sL('LLL:EXT:gridelements/Resources/Private/Language/locallang_db.xlf:list.collapseAllElements');
                            $containerIds = implode(',', array_flip(array_flip($this->currentContainerIdList)));
                            $icon = '<a
                class="btn btn-default t3js-toggle-gridelements-all" href="#t3-gridelements-collapse-all" id="t3-gridelements-collapse-all"
                title="' . $collapseTitle . '" data-container-ids="' . $containerIds . '">' . $this->iconFactory->getIcon(
                                'actions-view-list-collapse',
                                'small'
                            )->render() . '</a><a
                class="btn btn-default t3js-toggle-gridelements-all" href="#t3-gridelements-expand-all" id="t3-gridelements-expand-all"
                title="' . $expandTitle . '" data-container-ids="' . $containerIds . '">' . $this->iconFactory->getIcon(
                                'actions-view-list-expand',
                                'small'
                            )->render() . '</a>';
                        }
                        if ($this->calcPerms & $permsAdditional && $this->showNewRecLink($table)) {
                            $spriteIcon = $table === 'pages'
                                    ? $this->iconFactory->getIcon('actions-page-new', Icon::SIZE_SMALL)
                                    : $this->iconFactory->getIcon('actions-add', Icon::SIZE_SMALL);
                            if ($table === 'tt_content') {
                                // If mod.newContentElementWizard.override is set, use that extension's create new content wizard instead:
                                $newContentElementWizard = BackendUtility::getPagesTSconfig($this->pageinfo['uid'])['mod.']['newContentElementWizard.']['override']
                                        ?? 'new_content_element_wizard';
                                $url = (string)$uriBuilder->buildUriFromRoute(
                                    $newContentElementWizard,
                                    [
                                                'id' => $this->id,
                                                'returnUrl' => GeneralUtility::getIndpEnv('REQUEST_URI'),
                                        ]
                                );
                                $icon = '<a href="' . htmlspecialchars($url) . '"'
                                        . ' data-title="' . htmlspecialchars($lang->getLL('new')) . '"'
                                        . ' class="btn btn-default t3js-toggle-new-content-element-wizard">'
                                        . $spriteIcon->render()
                                        . '</a>';
                            } elseif ($table === 'pages') {
                                $parameters = [
                                        'id' => $this->id,
                                        'pagesOnly' => 1,
                                        'returnUrl' => GeneralUtility::getIndpEnv('REQUEST_URI'),
                                ];
                                $href = (string)$uriBuilder->buildUriFromRoute('db_new', $parameters);
                                $icon = '<a class="btn btn-default" href="' . htmlspecialchars($href) . '" title="' . htmlspecialchars($lang->getLL('new')) . '">'
                                        . $spriteIcon->render() . '</a>';
                            } else {
                                $params = '&edit[' . $table . '][' . $this->id . ']=new';
                                $icon = '<a class="btn btn-default" href="#" onclick="' . htmlspecialchars(BackendUtility::editOnClick(
                                    $params,
                                    '',
                                    -1
                                ))
                                        . '" title="' . htmlspecialchars($lang->getLL('new')) . '">' . $spriteIcon->render() . '</a>';
                            }
                        }
                        // If the table can be edited, add link for editing ALL SHOWN fields for all listed records:
                        if ($permsEdit && $this->table && is_array($currentIdList)) {
                            $entityIdentifiers = 'entityIdentifiers';
                            if ($this->clipNumPane()) {
                                $entityIdentifiers .= ':editList';
                            }
                            $editUri = $uriBuilder->buildUriFromRoute('record_edit')
                                    . '&edit[' . $table . '][{' . $entityIdentifiers . '}]=edit'
                                    . '&columnsOnly=' . implode(',', $this->fieldArray)
                                    . '&returnUrl={T3_THIS_LOCATION}';
                            $icon .= '<a class="btn btn-default t3js-record-edit-multiple" href="#"'
                                    . ' data-uri="' . htmlspecialchars($editUri) . '"'
                                    . ' title="' . htmlspecialchars($lang->getLL('editShownColumns')) . '">'
                                    . $this->iconFactory->getIcon(
                                        'actions-document-open',
                                        Icon::SIZE_SMALL
                                    )->render() . '</a>';
                            $icon = '<div class="btn-group" role="group">' . $icon . '</div>';
                        }
                        // Add an empty entry, so column count fits again after moving this into $icon
                        $theData[$fCol] = '&nbsp;';
                    } else {
                        $icon = $this->spaceIcon;
                    }
                    break;
                default:
                    // Regular fields header:
                    $theData[$fCol] = '';

                    // Check if $fCol is really a field and get the label and remove the colons
                    // at the end
                    $sortLabel = BackendUtility::getItemLabel($table, $fCol);
                    if ($sortLabel !== null) {
                        $sortLabel = rtrim(trim($lang->sL($sortLabel)), ':');

                        // Field label
                        $fieldTSConfig = [];
                        if (isset($tsConfigOfTable[$fCol . '.'])
                                && is_array($tsConfigOfTable[$fCol . '.'])
                        ) {
                            $fieldTSConfig = $tsConfigOfTable[$fCol . '.'];
                        }
                        if (!empty($fieldTSConfig['label'])) {
                            $sortLabel = $lang->sL($fieldTSConfig['label']);
                        }
                        if (!empty($fieldTSConfig['label.'][$lang->lang])) {
                            $sortLabel = $lang->sL($fieldTSConfig['label.'][$lang->lang]);
                        }
                        $sortLabel = htmlspecialchars($sortLabel);
                    } else {
                        // No TCA field, only output the $fCol variable with square brackets []
                        $sortLabel = htmlspecialchars($fCol);
                        $sortLabel = '<i>[' . rtrim(trim($sortLabel), ':') . ']</i>';
                    }

                    if ($this->table && is_array($currentIdList)) {
                        // If the numeric clipboard pads are selected, show duplicate sorting link:
                        if ($this->clipNumPane()) {
                            $theData[$fCol] .= '<a class="btn btn-default" href="' . htmlspecialchars($this->listURL() . '&duplicateField=' . $fCol)
                                    . '" title="' . htmlspecialchars($lang->getLL('clip_duplicates')) . '">'
                                    . $this->iconFactory->getIcon(
                                        'actions-document-duplicates-select',
                                        Icon::SIZE_SMALL
                                    )->render() . '</a>';
                        }
                        // If the table can be edited, add link for editing THIS field for all
                        // listed records:
                        if ($this->isEditable($table) && $permsEdit && $GLOBALS['TCA'][$table]['columns'][$fCol]) {
                            $entityIdentifiers = 'entityIdentifiers';
                            if ($this->clipNumPane()) {
                                $entityIdentifiers .= ':editList';
                            }
                            $editUri = $uriBuilder->buildUriFromRoute('record_edit')
                                    . '&edit[' . $table . '][{' . $entityIdentifiers . '}]=edit'
                                    . '&columnsOnly=' . $fCol
                                    . '&returnUrl={T3_THIS_LOCATION}';
                            $iTitle = sprintf($lang->getLL('editThisColumn'), $sortLabel);
                            $theData[$fCol] .= '<a class="btn btn-default t3js-record-edit-multiple" href="#"'
                                    . ' data-uri="' . htmlspecialchars($editUri) . '"'
                                    . ' title="' . htmlspecialchars($iTitle) . '">'
                                    . $this->iconFactory->getIcon(
                                        'actions-document-open',
                                        Icon::SIZE_SMALL
                                    )->render() . '</a>';
                        }
                        if (strlen($theData[$fCol]) > 0) {
                            $theData[$fCol] = '<div class="btn-group" role="group">' . $theData[$fCol] . '</div> ';
                        }
                    }
                    $theData[$fCol] .= $this->addSortLink($sortLabel, $fCol, $table);
            }
        }
        $this->totalColumnCount = 10 + count($theData);
        $headerOutput = '<colgroup>';
        for ($i = -10; $i < count($theData); $i++) {
            if ($i < -1) {
                $headerOutput .= '<col class="col-icon" width="40" />';
            } else {
                $headerOutput .= '<col width="auto" />';
            }
        }
        $headerOutput .= '</colgroup>';
        /**
         * @hook renderListHeader: Allows to change the contents of columns/cells of the Web>List table headers
         * @usage Above each listed table in Web>List a header row is shown.
         *        Containing the labels of all shown fields and additional icons to create new records for this
         *        table or perform special clipboard tasks like mark and copy all listed records to clipboard, etc.
         */
        foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/class.db_list_extra.inc']['actions'] ?? [] as $className) {
            $hookObject = GeneralUtility::makeInstance($className);
            if (!$hookObject instanceof RecordListHookInterface) {
                throw new UnexpectedValueException(
                    $className . ' must implement interface ' . RecordListHookInterface::class,
                    1195567855
                );
            }
            $theData = $hookObject->renderListHeader($table, $currentIdList, $theData, $this);
        }

        // Create and return header table row:
        return $headerOutput . '<thead>' . $this->addElement(1, $icon, $theData, '', '', '', 'th', 0, $table) . '</thead>';
    }

    /**
     * Creates a page browser for tables with many records
     *
     * @param string $renderPart Distinguish between 'top' and 'bottom' part of the navigation (above or below the records)
     * @return string Navigation HTML
     */
    protected function renderListNavigation($renderPart = 'top'): string
    {
        $totalPages = ceil($this->totalItems / $this->iLimit);
        // Show page selector if not all records fit into one page
        if ($totalPages <= 1) {
            return '';
        }
        $content = '';
        $listURL = $this->listURL('', $this->table, 'firstElementNumber');
        // 1 = first page
        // 0 = first element
        $currentPage = floor($this->firstElementNumber / $this->iLimit) + 1;
        // Compile first, previous, next, last and refresh buttons
        if ($currentPage > 1) {
            $labelFirst = htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_common.xlf:first'));
            $labelPrevious = htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_common.xlf:previous'));
            $first = '<li><a href="' . $listURL . '&pointer=' . $this->getPointerForPage(1) . '" title="' . $labelFirst . '">'
                    . $this->iconFactory->getIcon('actions-view-paging-first', Icon::SIZE_SMALL)->render() . '</a></li>';
            $previous = '<li><a href="' . $listURL . '&pointer=' . $this->getPointerForPage($currentPage - 1) . '" title="' . $labelPrevious . '">'
                    . $this->iconFactory->getIcon('actions-view-paging-previous', Icon::SIZE_SMALL)->render() . '</a></li>';
        } else {
            $first = '<li class="disabled"><span>' . $this->iconFactory->getIcon(
                'actions-view-paging-first',
                Icon::SIZE_SMALL
            )->render() . '</span></li>';
            $previous = '<li class="disabled"><span>' . $this->iconFactory->getIcon(
                'actions-view-paging-previous',
                Icon::SIZE_SMALL
            )->render() . '</span></li>';
        }
        if ($currentPage < $totalPages) {
            $labelNext = htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_common.xlf:next'));
            $labelLast = htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_common.xlf:last'));
            $next = '<li><a href="' . $listURL . '&pointer=' . $this->getPointerForPage($currentPage + 1) . '" title="' . $labelNext . '">'
                    . $this->iconFactory->getIcon('actions-view-paging-next', Icon::SIZE_SMALL)->render() . '</a></li>';
            $last = '<li><a href="' . $listURL . '&pointer=' . $this->getPointerForPage($totalPages) . '" title="' . $labelLast . '">'
                    . $this->iconFactory->getIcon('actions-view-paging-last', Icon::SIZE_SMALL)->render() . '</a></li>';
        } else {
            $next = '<li class="disabled"><span>' . $this->iconFactory->getIcon(
                'actions-view-paging-next',
                Icon::SIZE_SMALL
            )->render() . '</span></li>';
            $last = '<li class="disabled"><span>' . $this->iconFactory->getIcon(
                'actions-view-paging-last',
                Icon::SIZE_SMALL
            )->render() . '</span></li>';
        }
        $reload = '<li><a href="#" onclick="document.dblistForm.action=' . GeneralUtility::quoteJSvalue($listURL
                        . '&pointer=') . '+calculatePointer(document.getElementById(' . GeneralUtility::quoteJSvalue('jumpPage-' . $renderPart)
                . ').value) document.dblistForm.submit(); return true;" title="'
                . htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_common.xlf:reload')) . '">'
                . $this->iconFactory->getIcon('actions-refresh', Icon::SIZE_SMALL)->render() . '</a></li>';
        if ($renderPart === 'top') {
            // Add js to traverse a page select input to a pointer value
            $content = '
<script type="text/javascript">
/*<![CDATA[*/
	function calculatePointer(page) {
		if (page > ' . $totalPages . ') {
			page = ' . $totalPages . ';
		}
		if (page < 1) {
			page = 1;
		}
		return (page - 1) * ' . $this->iLimit . ';
	}
/*]]>*/
</script>
';
        }
        $pageNumberInput = '
			<input type="number" min="1" max="' . $totalPages . '" value="' . $currentPage . '" size="3" class="form-control input-sm paginator-input" id="jumpPage-' . $renderPart . '" name="jumpPage-'
                . $renderPart . '" onkeyup="if (event.keyCode == 13) { document.dblistForm.action=' . htmlspecialchars(GeneralUtility::quoteJSvalue($listURL . '&pointer='))
                . '+calculatePointer(this.value); document.dblistForm.submit(); } return true;" />
			';
        $pageIndicatorText = sprintf(
            $this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf:pageIndicator'),
            $pageNumberInput,
            $totalPages
        );
        $pageIndicator = '<li><span>' . $pageIndicatorText . '</span></li>';
        if ($this->totalItems > $this->firstElementNumber + $this->iLimit) {
            $lastElementNumber = $this->firstElementNumber + $this->iLimit;
        } else {
            $lastElementNumber = $this->totalItems;
        }
        $rangeIndicator = '<li><span>' . sprintf(
            $this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf:rangeIndicator'),
            $this->firstElementNumber + 1,
            $lastElementNumber
        ) . '</span></li>';

        $titleColumn = $this->fieldArray[0];
        $data = [
                $titleColumn => $content . '
				<nav class="pagination-wrap">
					<ul class="pagination pagination-block">
						' . $first . '
						' . $previous . '
						' . $rangeIndicator . '
						' . $pageIndicator . '
						' . $next . '
						' . $last . '
						' . $reload . '
					</ul>
				</nav>
			',
        ];
        return $this->addElement(1, '', $data, '', '', '', 'pagination');
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

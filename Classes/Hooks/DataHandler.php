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
use GridElementsTeam\Gridelements\DataHandler\AfterDatabaseOperations;
use GridElementsTeam\Gridelements\DataHandler\PreProcessFieldArray;
use GridElementsTeam\Gridelements\DataHandler\ProcessCmdmap;
use GridElementsTeam\Gridelements\Helper\ContainerCycleGuard;
use GridElementsTeam\Gridelements\Helper\GridElementsHelper;
use GridElementsTeam\Gridelements\Helper\RestrictionGuard;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Class/Function which offers TCE main hook functions.
 *
 * @author Jo Hasenau <info@cybercraft.de>
 */
class DataHandler implements SingletonInterface
{
    /**
     * Fields always checked for allowed/disallowed restrictions, in addition to
     * maxitems. Any further field a column's 'allowed'/'disallowed' configuration
     * mentions is checked too -- see resolveRestrictedFields() -- so a project-specific
     * field (e.g. a custom select on tt_content) works the same way content_defender's
     * field-agnostic allowed.<field>/disallowed.<field> does, without a config-format
     * change.
     */
    protected const RESTRICTED_FIELDS = ['CType', 'list_type', 'tx_gridelements_backend_layout'];

    public function __construct()
    {
    }

    /**
     * @param array $layout
     * @param int $column
     * @return string[]
     */
    protected function resolveRestrictedFields(array $layout, int $column): array
    {
        return array_unique(array_merge(
            self::RESTRICTED_FIELDS,
            array_keys($layout['allowed'][$column] ?? []),
            array_keys($layout['disallowed'][$column] ?? [])
        ));
    }

    /**
     * Function to set the colPos of an element depending on
     * whether it is a child of a parent container or not
     * will set colPos according to availability of the current grid column of an element
     * 0 = no column at all
     * -1 = grid element column
     * -2 = non used elements column
     * changes are applied to the field array of the parent object by reference
     *
     * @param array $fieldArray : The array of fields and values that have been saved to the datamap
     * @param string $table : The name of the table the data should be saved to
     * @param string $id : The uid of the page we are currently working on
     * @param \TYPO3\CMS\Core\DataHandling\DataHandler $parentObj : The parent object that triggered this hook
     */
    public function processDatamap_preProcessFieldArray(
        array &$fieldArray,
        string $table,
        string $id,
        \TYPO3\CMS\Core\DataHandling\DataHandler $parentObj
    ): void {
        if (($table === 'tt_content' || $table === 'pages') && !$parentObj->isImporting) {
            /** @var PreProcessFieldArray $hook */
            $hook = GeneralUtility::makeInstance(PreProcessFieldArray::class);
            $hook->execute_preProcessFieldArray($fieldArray, $table, $id, $parentObj);
        }
    }

    /**
     * Rejects a datamap save for tt_content that violates the allowed/disallowed/maxitems
     * restriction config of the target column -- either a grid container's own column
     * (tx_gridelements_container > 0) or the target page's own backend-layout colPos.
     * This covers the New Content Element wizard and direct FormEngine edits, which are
     * otherwise only filtered by the (bypassable) backend-form item lists in
     * Classes/Backend/ItemsProcFuncs -- see processCmdmap_beforeStart() for the equivalent
     * check on copy/move/paste commands.
     *
     * @param \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler
     */
    public function processDatamap_beforeStart(\TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler): void
    {
        $datamap = $dataHandler->datamap;
        if (empty($datamap['tt_content']) || $dataHandler->bypassAccessCheckForRecords || $dataHandler->isImporting) {
            return;
        }

        $pendingCounts = [];

        foreach ($datamap['tt_content'] as $id => $incomingFieldArray) {
            $isNew = !MathUtility::canBeInterpretedAsInteger($id);
            $existingRecord = $isNew ? [] : (BackendUtility::getRecord('tt_content', (int)$id) ?: []);
            $record = array_merge($existingRecord, $incomingFieldArray);

            $pid = (int)($record['pid'] ?? 0);
            if ($pid < 0) {
                $previousRecord = BackendUtility::getRecord('tt_content', abs($pid), 'pid');
                $pid = (int)($previousRecord['pid'] ?? 0);
            }
            if ($pid <= 0) {
                continue;
            }

            $container = (int)($record['tx_gridelements_container'] ?? 0);
            $colPos = (int)($record['colPos'] ?? 0);
            $languageUid = (int)($record['sys_language_uid'] ?? 0);

            if ($container > 0) {
                $containerRecord = BackendUtility::getRecord('tt_content', $container, 'tx_gridelements_backend_layout');
                if (empty($containerRecord)) {
                    continue;
                }
                $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class)->init($pid);
                $layout = $layoutSetup->getLayoutSetup($containerRecord['tx_gridelements_backend_layout']);
                $column = (int)($record['tx_gridelements_columns'] ?? 0);
                $scopeKey = 'c' . $container . '_' . $column . '_' . $languageUid;
            } else {
                $layout = GridElementsHelper::getSelectedBackendLayout($pid);
                $column = $colPos;
                $scopeKey = 'p' . $pid . '_' . $colPos . '_' . $languageUid;
            }

            if (empty($layout)) {
                continue;
            }

            $violatedField = null;
            foreach ($this->resolveRestrictedFields($layout, $column) as $field) {
                if (empty($record[$field])) {
                    continue;
                }
                $allowed = $layout['allowed'][$column][$field] ?? [];
                $disallowed = $layout['disallowed'][$column][$field] ?? [];
                if (RestrictionGuard::isValueDisallowed($allowed, $disallowed, (string)$record[$field])) {
                    $violatedField = $field;
                    break;
                }
            }

            if ($violatedField !== null) {
                $this->flashFieldValueNotAllowedError($dataHandler, $id, $violatedField, (string)$record[$violatedField], 'datamap');
                continue;
            }

            $maxItems = isset($layout['maxitems'][$column]) ? (int)$layout['maxitems'][$column] : null;
            if ($maxItems !== null && $maxItems > 0) {
                $excludeUid = $isNew ? 0 : (int)$id;
                $existingCount = RestrictionGuard::countExistingChildren($pid, $container, $colPos, $column, $languageUid, $excludeUid);
                $existingCount += $pendingCounts[$scopeKey] ?? 0;
                if (RestrictionGuard::isMaxItemsExceeded($maxItems, $existingCount)) {
                    $this->flashMaxItemsReachedError($dataHandler, $id, $maxItems, 'datamap');
                    continue;
                }
                $pendingCounts[$scopeKey] = ($pendingCounts[$scopeKey] ?? 0) + 1;
            }
        }
    }

    /**
     * @param string $status
     * @param string $table : The name of the table the data should be saved to
     * @param string $id : The uid of the page we are currently working on
     * @param array $fieldArray : The array of fields and values that have been saved to the datamap
     * @param \TYPO3\CMS\Core\DataHandling\DataHandler $parentObj : The parent object that triggered this hook
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws \Doctrine\DBAL\Exception
     */
    public function processDatamap_afterDatabaseOperations(
        string $status,
        string $table,
        string $id,
        array &$fieldArray,
        \TYPO3\CMS\Core\DataHandling\DataHandler $parentObj
    ): void {
        // create a copy of $id which is passed by reference
        $recordUid = $id;
        if (($table === 'tt_content' || $table === 'pages') && !$parentObj->isImporting) {
            /** @var AfterDatabaseOperations $hook */
            $hook = GeneralUtility::makeInstance(AfterDatabaseOperations::class);
            if (str_contains($recordUid, 'NEW')) {
                $recordUid = $parentObj->substNEWwithIDs[$recordUid];
            }
            $hook->execute_afterDatabaseOperations($fieldArray, $table, (int)$recordUid, $parentObj);
        }
    }

    /**
     * Function to process the drag & drop copy action
     *
     * @param string $command The command to be handled by the command map
     * @param string $table The name of the table we are working on
     * @param int $id The id of the record that is going to be copied
     * @param mixed $value The value that has been sent with the copy command
     * @param bool $commandIsProcessed A switch to tell the parent object, if the record has been copied
     * @param \TYPO3\CMS\Core\DataHandling\DataHandler $parentObj The parent object that triggered this hook
     * @param bool|array $pasteUpdate Values to be updated after the record is pasted
     * @throws \Doctrine\DBAL\Exception
     */
    public function processCmdmap(
        string $command,
        string $table,
        int $id,
        mixed $value,
        bool &$commandIsProcessed,
        \TYPO3\CMS\Core\DataHandling\DataHandler $parentObj,
        bool|array $pasteUpdate
    ): void {
        if (!$parentObj->isImporting) {
            /** @var ProcessCmdmap $hook */
            $hook = GeneralUtility::makeInstance(ProcessCmdmap::class);
            $hook->execute_processCmdmap($command, $table, $id, $value, $commandIsProcessed, $parentObj, $pasteUpdate);
        }
    }

    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function processCmdmap_beforeStart(\TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler): void
    {
        $cmdmap = $dataHandler->cmdmap;
        if (empty($cmdmap['tt_content']) || $dataHandler->bypassAccessCheckForRecords) {
            return;
        }

        foreach ($cmdmap['tt_content'] as $id => $incomingFieldArray) {
            foreach ($incomingFieldArray as $command => $value) {
                if (!in_array($command, ['copy', 'move'], true)) {
                    continue;
                }

                $currentRecord = BackendUtility::getRecord('tt_content', $id);

                if (empty($currentRecord)) {
                    continue;
                }

                if (is_array($value)
                    && !empty($value['action'])
                    && $value['action'] === 'paste'
                    && (
                        isset($value['update']['colPos']) ||
                        isset($value['update']['tx_gridelements_container']) ||
                        isset($value['update']['tx_gridelements_columns'])
                    )
                ) {
                    $pageId = (int)$value['target'];
                    $colPos = (int)($value['update']['colPos'] ?? 0);
                    $gridContainer = (int)($value['update']['tx_gridelements_container'] ?? 0);
                    $gridColumn = (int)($value['update']['tx_gridelements_columns'] ?? 0);
                } else {
                    $pageId = (int)$value;
                    $colPos = (int)$currentRecord['colPos'];
                    $gridContainer = (int)$currentRecord['tx_gridelements_container'];
                    $gridColumn = (int)$currentRecord['tx_gridelements_columns'];
                }
                $containerRecord = BackendUtility::getRecord('tt_content', $gridContainer);

                if ($pageId < 0) {
                    $targetRecord = BackendUtility::getRecordWSOL('tt_content', abs($pageId), 'pid,colPos,tx_gridelements_container,tx_gridelements_columns');
                    if (empty($targetRecord)) {
                        continue;
                    }
                    $pageId = (int)$targetRecord['pid'];
                    $colPos = (int)$targetRecord['colPos'];
                    $gridContainer = (int)$targetRecord['tx_gridelements_container'];
                    $gridColumn = (int)$targetRecord['tx_gridelements_columns'];
                    $containerRecord = BackendUtility::getRecord('tt_content', $gridContainer);
                }

                if ($colPos !== -1 || empty($containerRecord)) {
                    // not becoming a grid-container child -- still check the target page's
                    // own backend-layout column restriction (mirrors the container-child
                    // checks below, for ordinary page-level colPos placement)
                    if ($pageId > 0 && $colPos >= 0) {
                        $pageLayout = GridElementsHelper::getSelectedBackendLayout($pageId);
                        if (!empty($pageLayout)) {
                            $this->rejectIfRestrictedInColumn(
                                $dataHandler,
                                $id,
                                $command,
                                $pageLayout,
                                $colPos,
                                $currentRecord,
                                $pageId,
                                0,
                                $colPos
                            );
                        }
                    }
                    continue;
                }

                if ($currentRecord['CType'] === 'gridelements_pi1') {
                    if (
                        $command === 'move'
                        && ContainerCycleGuard::wouldCreateContainerCycle((int)$currentRecord['uid'], $gridContainer)
                    ) {
                        $this->flashContainerError($dataHandler, $id, 'tx_gridelements_cannot_create_container_cycle');
                        continue;
                    }

                    $forbiddenAncestorUids = ContainerCycleGuard::getContainerAncestorChain($gridContainer);
                    if (ContainerCycleGuard::subtreeReferencesForbiddenContainer((int)$currentRecord['uid'], $forbiddenAncestorUids)) {
                        $this->flashContainerError($dataHandler, $id, 'tx_gridelements_cannot_reference_ancestor_container');
                        continue;
                    }
                } elseif (
                    $currentRecord['CType'] === 'shortcut'
                    && !empty($currentRecord['records'])
                    && ContainerCycleGuard::shortcutReferencesForbiddenContainer(
                        (string)$currentRecord['records'],
                        ContainerCycleGuard::getContainerAncestorChain($gridContainer)
                    )
                ) {
                    // the shortcut itself (not a container) is being moved/copied into a
                    // column whose container is (or descends from) something it already
                    // references -- would create an infinite rendering loop
                    $this->flashContainerError($dataHandler, $id, 'tx_gridelements_cannot_reference_ancestor_container');
                    continue;
                }

                $currentLayoutSetup = GeneralUtility::makeInstance(LayoutSetup::class)->init($currentRecord['pid']);
                $currentLayout = $currentLayoutSetup->getLayoutSetup($currentRecord['tx_gridelements_backend_layout']);

                if ((int)($currentLayout['top_level_layout'] ?? 0) === 1) {
                    $this->flashNotAllowedError($dataHandler, $id, $command);
                    continue;
                }

                $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class)->init($pageId);
                $layout = $layoutSetup->getLayoutSetup($containerRecord['tx_gridelements_backend_layout']);

                $this->rejectIfRestrictedInColumn(
                    $dataHandler,
                    $id,
                    $command,
                    $layout,
                    $gridColumn,
                    $currentRecord,
                    $pageId,
                    $gridContainer,
                    -1
                );
            }
        }
    }

    /**
     * Shared allowed/disallowed/maxitems check for both branches of processCmdmap_beforeStart()
     * (grid-container child column and ordinary page-level colPos column). Rejects the command
     * (unsets it from cmdmap and shows a flash message) on the first violation found.
     *
     * @param \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler
     * @param int|string $id
     * @param string $command
     * @param array $layout
     * @param int $column
     * @param array $record
     * @param int $pageId
     * @param int $container
     * @param int $colPos
     * @return bool true if the command was rejected
     */
    protected function rejectIfRestrictedInColumn(
        \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler,
        int|string $id,
        string $command,
        array $layout,
        int $column,
        array $record,
        int $pageId,
        int $container,
        int $colPos
    ): bool {
        foreach ($this->resolveRestrictedFields($layout, $column) as $field) {
            if (empty($record[$field])) {
                continue;
            }
            $allowed = $layout['allowed'][$column][$field] ?? [];
            $disallowed = $layout['disallowed'][$column][$field] ?? [];
            if (RestrictionGuard::isValueDisallowed($allowed, $disallowed, (string)$record[$field])) {
                $this->flashNotAllowedError($dataHandler, $id, $command);
                return true;
            }
        }

        $maxItems = isset($layout['maxitems'][$column]) ? (int)$layout['maxitems'][$column] : null;
        if ($maxItems !== null && $maxItems > 0) {
            $languageUid = (int)($record['sys_language_uid'] ?? 0);
            $existingCount = RestrictionGuard::countExistingChildren(
                $pageId,
                $container,
                $colPos,
                $column,
                $languageUid,
                (int)($record['uid'] ?? 0)
            );
            if (RestrictionGuard::isMaxItemsExceeded($maxItems, $existingCount)) {
                $this->flashMaxItemsReachedError($dataHandler, $id, $maxItems, 'cmdmap');
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $allowed
     * @param string $CType
     * @param array $disallowed
     * @return bool
     */
    public function isDisallowedContentElement(array $allowed, array $disallowed, string $CType): bool
    {
        return RestrictionGuard::isValueDisallowed($allowed, $disallowed, $CType);
    }

    /**
     * @param \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler
     * @param int|string $id
     * @param string $command
     * @throws Exception
     */
    public function flashNotAllowedError(\TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler, int|string $id, string $command): void
    {
        unset($dataHandler->cmdmap['tt_content'][$id]);

        $message = LocalizationUtility::translate(sprintf('LLL:EXT:gridelements/Resources/Private/Language/locallang_db.xlf:tx_gridelements_cannot_%s_into_container', $command));

        $flashMessage = GeneralUtility::makeInstance(FlashMessage::class, $message, '', ContextualFeedbackSeverity::ERROR, true);
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $defaultFlashMessageQueue->enqueue($flashMessage);
    }

    /**
     * Aborts the current command and shows a flash message for the given language label,
     * used for the container-recursion guards (self/ancestor cycles, shortcut elements
     * referencing one of their own ancestor containers).
     *
     * @param \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler
     * @param int|string $id
     * @param string $labelKey
     * @throws Exception
     */
    public function flashContainerError(\TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler, int|string $id, string $labelKey): void
    {
        unset($dataHandler->cmdmap['tt_content'][$id]);

        $message = LocalizationUtility::translate('LLL:EXT:gridelements/Resources/Private/Language/locallang_db.xlf:' . $labelKey);

        $flashMessage = GeneralUtility::makeInstance(FlashMessage::class, $message, '', ContextualFeedbackSeverity::ERROR, true);
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $defaultFlashMessageQueue->enqueue($flashMessage);
    }

    /**
     * Aborts the current datamap/cmdmap entry and shows a flash message because $field's
     * value is not allowed in the target column.
     *
     * @param \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler
     * @param int|string $id
     * @param string $field
     * @param string $value
     * @param string $map 'datamap' or 'cmdmap'
     */
    public function flashFieldValueNotAllowedError(
        \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler,
        int|string $id,
        string $field,
        string $value,
        string $map
    ): void {
        if ($map === 'datamap') {
            unset($dataHandler->datamap['tt_content'][$id]);
        } else {
            unset($dataHandler->cmdmap['tt_content'][$id]);
        }

        $message = sprintf(
            LocalizationUtility::translate('LLL:EXT:gridelements/Resources/Private/Language/locallang_db.xlf:tx_gridelements_field_value_not_allowed_in_column'),
            $value,
            $field
        );

        $flashMessage = GeneralUtility::makeInstance(FlashMessage::class, $message, '', ContextualFeedbackSeverity::ERROR, true);
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $defaultFlashMessageQueue->enqueue($flashMessage);
    }

    /**
     * Aborts the current datamap/cmdmap entry and shows a flash message because the target
     * column's maxitems restriction has been reached.
     *
     * @param \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler
     * @param int|string $id
     * @param int $maxItems
     * @param string $map 'datamap' or 'cmdmap'
     */
    public function flashMaxItemsReachedError(
        \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler,
        int|string $id,
        int $maxItems,
        string $map
    ): void {
        if ($map === 'datamap') {
            unset($dataHandler->datamap['tt_content'][$id]);
        } else {
            unset($dataHandler->cmdmap['tt_content'][$id]);
        }

        $message = sprintf(
            LocalizationUtility::translate('LLL:EXT:gridelements/Resources/Private/Language/locallang_db.xlf:tx_gridelements_maxitems_reached_in_column'),
            $maxItems
        );

        $flashMessage = GeneralUtility::makeInstance(FlashMessage::class, $message, '', ContextualFeedbackSeverity::ERROR, true);
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $defaultFlashMessageQueue->enqueue($flashMessage);
    }
}

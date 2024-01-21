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
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Class/Function which offers TCE main hook functions.
 *
 * @author Jo Hasenau <info@cybercraft.de>
 */
class DataHandler implements SingletonInterface
{
    public function __construct()
    {
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
    ) {
        if (($table === 'tt_content' || $table === 'pages') && !$parentObj->isImporting) {
            /** @var PreProcessFieldArray $hook */
            $hook = GeneralUtility::makeInstance(PreProcessFieldArray::class);
            $hook->execute_preProcessFieldArray($fieldArray, $table, $id, $parentObj);
        }
    }

    /**
     * @param string $status
     * @param string $table : The name of the table the data should be saved to
     * @param string $id : The uid of the page we are currently working on
     * @param array $fieldArray : The array of fields and values that have been saved to the datamap
     * @param \TYPO3\CMS\Core\DataHandling\DataHandler $parentObj : The parent object that triggered this hook
     */
    public function processDatamap_afterDatabaseOperations(
        string &$status,
        string &$table,
        string &$id,
        array &$fieldArray,
        \TYPO3\CMS\Core\DataHandling\DataHandler $parentObj
    ) {
        // create a copy of $id which is passed by reference
        $recordUid = $id;
        if (($table === 'tt_content' || $table === 'pages') && !$parentObj->isImporting) {
            /** @var AfterDatabaseOperations $hook */
            $hook = GeneralUtility::makeInstance(AfterDatabaseOperations::class);
            if (str_contains($recordUid, 'NEW')) {
                $recordUid = $parentObj->substNEWwithIDs[$recordUid];
            } else {
                if ($table === 'tt_content' && $status === 'update') {
                    $hook->adjustValuesAfterWorkspaceOperations($fieldArray, (int)$recordUid, $parentObj);
                }
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
     * @param array|bool $pasteUpdate Values to be updated after the record is pasted
     */
    public function processCmdmap(
        string $command,
        string $table,
        int $id,
        $value,
        bool &$commandIsProcessed,
        \TYPO3\CMS\Core\DataHandling\DataHandler &$parentObj,
        $pasteUpdate
    ) {
        if (!$parentObj->isImporting) {
            /** @var ProcessCmdmap $hook */
            $hook = GeneralUtility::makeInstance(ProcessCmdmap::class);
            $hook->execute_processCmdmap($command, $table, $id, $value, $commandIsProcessed, $parentObj, $pasteUpdate);
        }
    }

    public function processCmdmap_beforeStart(\TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler)
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
                    $colPos = (int)$value['update']['colPos'];
                    $gridContainer = (int)$value['update']['tx_gridelements_container'];
                    $gridColumn = (int)$value['update']['tx_gridelements_columns'];
                    $containerRecord = BackendUtility::getRecord('tt_content', $gridContainer);
                } else {
                    $pageId = (int)$value;
                    $colPos = (int)$currentRecord['colPos'];
                    $gridContainer = (int)$currentRecord['tx_gridelements_container'];
                    $gridColumn = (int)$currentRecord['tx_gridelements_columns'];
                    $containerRecord = BackendUtility::getRecord('tt_content', $gridContainer);
                }

                if ($pageId < 0) {
                    $targetRecord = BackendUtility::getRecordWSOL('tt_content', abs($pageId), 'pid,colPos,tx_gridelements_container,tx_gridelements_columns');
                    $pageId = (int)$targetRecord['pid'];
                    $colPos = (int)$targetRecord['colPos'];
                    $gridContainer = (int)$targetRecord['tx_gridelements_container'];
                    $gridColumn = (int)$targetRecord['tx_gridelements_columns'];
                    $containerRecord = BackendUtility::getRecord('tt_content', $gridContainer);
                }

                if ($colPos !== -1) {
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

                $allowed = $layout['allowed'][$gridColumn]['CType'] ?? [];
                $disallowed = $layout['disallowed'][$gridColumn]['CType'] ?? [];

                if (empty($allowed) && empty($disallowed)) {
                    continue;
                }

                if (
                    !$this->isDisallowedContentElement($allowed, $disallowed, $currentRecord['CType'])
                ) {
                    continue;
                }

                $this->flashNotAllowedError($dataHandler, $id, $command);
            }
        }
    }

    /**
     * @param array $allowed
     * @param string $CType
     * @param array $disallowed
     * @return bool
     */
    public function isDisallowedContentElement(array $allowed, array $disallowed, string $CType): bool
    {
        return (
            !empty($allowed)
            && !isset($allowed['*'])
            && !isset($allowed[$CType])
        ) || (
            !empty($disallowed)
            && (
                isset($disallowed['*'])
                || isset($disallowed[$CType])
            )
        );
    }

    /**
     * @param \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler
     * @param int|string $id
     * @param string $command
     * @throws \TYPO3\CMS\Core\Exception
     */
    public function flashNotAllowedError(\TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler, int|string $id, string $command): void
    {
        unset($dataHandler->cmdmap['tt_content'][$id]);

        $message = LocalizationUtility::translate(sprintf('LLL:EXT:gridelements/Resources/Private/Language/locallang_db.xml:tx_gridelements_cannot_%s_into_container', $command));

        $flashMessage = GeneralUtility::makeInstance(FlashMessage::class, $message, '', ContextualFeedbackSeverity::ERROR, true);
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $defaultFlashMessageQueue->enqueue($flashMessage);
    }
}

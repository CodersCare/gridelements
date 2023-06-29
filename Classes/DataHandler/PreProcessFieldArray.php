<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\DataHandler;

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

use PDO;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Class/Function which offers TCE main hook functions.
 *
 * @author Jo Hasenau <info@cybercraft.de>
 */
class PreProcessFieldArray extends AbstractDataHandler
{
    /**
     * @var array
     */
    protected array $definitionValues;

    /**
     * @var array
     */
    protected array $overrideValues;

    /**
     * Function to set the colPos of an element depending on
     * whether it is a child of a parent container or not
     * will set colPos according to availability of the current grid column of an element
     * 0 = no column at all
     * -1 = grid element column
     * -2 = non used elements column
     * changes are applied to the field array of the parent object by reference
     *
     * @param array $fieldArray The array of fields and values that have been saved to the datamap
     * @param string $table The name of the table the data should be saved to
     * @param string $id The parent uid of either the page or the container we are currently working on
     * @param DataHandler $parentObj The parent object that triggered this hook
     */
    public function execute_preProcessFieldArray(array &$fieldArray, string $table, string $id, DataHandler $parentObj)
    {
        if ($table === 'tt_content') {
            $action = '';
            $this->init($table, $id, $parentObj);
            if (!$this->getTceMain()->isImporting) {
                $new = false;
                if (!empty($parentObj->cmdmap['tt_content']) && is_array($parentObj->cmdmap['tt_content'])) {
                    $cmdId = (int)key($parentObj->cmdmap['tt_content']);
                    $new = !empty($parentObj->cmdmap['tt_content'][$cmdId]['copy']) ||
                        !empty($parentObj->cmdmap['tt_content'][$cmdId]['move']);
                    if ($new) {
                        $action = key($parentObj->cmdmap['tt_content'][$cmdId]);
                    }
                }
                if (!$new && !empty($parentObj->datamap['tt_content']) && is_array($parentObj->datamap['tt_content'])) {
                    $new = !MathUtility::canBeInterpretedAsInteger(key($parentObj->datamap['tt_content']));
                }
                $this->processFieldArrayForTtContent($fieldArray, $id, $new, $action);
            }
        }
    }

    /**
     * process field array for table tt_content
     *
     * @param array $fieldArray
     * @param string $id
     * @param bool $new
     */
    public function processFieldArrayForTtContent(array &$fieldArray, string $id = '0', bool $new = false, $action = '')
    {
        $pid = (int)GeneralUtility::_GET('DDinsertNew');

        if (abs($pid) > 0) {
            $this->setDefaultFieldValues($fieldArray, $pid);
            $this->getDefaultFlexformValues($fieldArray);
        }

        $this->setFieldEntries($fieldArray, $id, $new, $action);
    }

    /**
     * set default field values for new records
     *
     * @param array $fieldArray
     * @param int $uidPid
     */
    public function setDefaultFieldValues(array &$fieldArray, int $uidPid = 0)
    {
        // Default values:
        $newRow = []; // Used to store default values as found here:

        // Default values as set in userTS:
        $TCAdefaultOverride = (array)($this->getBackendUser()->getTSConfig()['TCAdefaults'] ?? []);
        if (!empty($TCAdefaultOverride['tt_content.']) && is_array($TCAdefaultOverride['tt_content.'])) {
            foreach ($TCAdefaultOverride['tt_content.'] as $field => $value) {
                if (isset($GLOBALS['TCA']['tt_content']['columns'][$field])) {
                    $newRow[$field] = $value;
                }
            }
        }

        if ($uidPid < 0) {
            $record = BackendUtility::getRecord('tt_content', abs($uidPid), 'pid');
            $pageId = $record['pid'];
        } else {
            $pageId = $uidPid;
        }

        $pageTS = BackendUtility::getPagesTSconfig($pageId);

        if (isset($pageTS['TCAdefaults.'])) {
            $TCAPageTSOverride = $pageTS['TCAdefaults.'];
            if (!empty($TCAPageTSOverride['tt_content.']) && is_array($TCAPageTSOverride['tt_content.'])) {
                foreach ($TCAPageTSOverride['tt_content.'] as $field => $value) {
                    if (isset($GLOBALS['TCA']['tt_content']['columns'][$field])) {
                        $newRow[$field] = $value;
                    }
                }
            }
        }

        // Default values as submitted:
        $this->definitionValues = GeneralUtility::_GP('defVals') ?? [];
        $this->overrideValues = GeneralUtility::_GP('overrideVals') ?? [];
        if (empty($this->definitionValues) && !empty($this->overrideValues)) {
            $this->definitionValues = $this->overrideValues;
        }
        if (!empty($this->definitionValues['tt_content']) && is_array($this->definitionValues['tt_content'])) {
            foreach ($this->definitionValues['tt_content'] as $field => $value) {
                if (isset($GLOBALS['TCA']['tt_content']['columns'][$field])) {
                    $newRow[$field] = $value;
                }
            }
        }

        // Fetch default values if a previous record exists
        if ($uidPid < 0 && !empty($record) && !empty($GLOBALS['TCA']['tt_content']['ctrl']['useColumnsForDefaultValues'])) {
            // Gets the list of fields to copy from the previous record.
            $fieldArray = explode(',', $GLOBALS['TCA']['tt_content']['ctrl']['useColumnsForDefaultValues']);
            foreach ($fieldArray as $field) {
                $field = trim($field);
                if ($field === '') {
                    continue;
                }
                if (isset($GLOBALS['TCA']['tt_content']['columns'][$field])) {
                    $newRow[$field] = $record[$field];
                }
            }
        }
        $fieldArray = array_merge($newRow, $fieldArray);
    }

    /**
     * @return BackendUserAuthentication
     */
    public function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * checks for default flexform values for new records and sets them accordingly
     *
     * @param array $fieldArray
     */
    public function getDefaultFlexformValues(array &$fieldArray)
    {
        if (!empty($GLOBALS['TCA']['tt_content']['columns']['pi_flexform']['config']['ds'])) {
            foreach ($GLOBALS['TCA']['tt_content']['columns']['pi_flexform']['config']['ds'] as $key => $dataStructure) {
                $types = GeneralUtility::trimExplode(',', $key);
                if (((!empty($fieldArray['list_type']) && $types[0] === $fieldArray['list_type']) || $types[0] === '*')
                    && ((!empty($fieldArray['CType']) && $types[1] === $fieldArray['CType']) || $types[1] === '*')) {
                    $fieldArray['pi_flexform'] = $this->extractDefaultDataFromDataStructure($dataStructure);
                }
            }
        }
    }

    /**
     * extracts the default data out of a given XML data structure
     *
     * @param string $dataStructure
     *
     * @return string $defaultData
     */
    public function extractDefaultDataFromDataStructure(string $dataStructure): string
    {
        $returnXML = '';
        $sheetArray = [];
        if ($dataStructure) {
            $structureArray = GeneralUtility::xml2array($dataStructure);
            if (!isset($structureArray['sheets']) && isset($structureArray['ROOT'])) {
                $structureArray['sheets']['sDEF']['ROOT'] = $structureArray['ROOT'];
                unset($structureArray['ROOT']);
            }
            if (isset($structureArray['sheets']) && !empty($structureArray['sheets'])) {
                foreach ($structureArray['sheets'] as $sheetName => $sheet) {
                    if (is_array($sheet['ROOT']['el']) && !empty($sheet['ROOT']['el'])) {
                        $elArray = [];
                        foreach ($sheet['ROOT']['el'] as $elName => $elConf) {
                            $config = $elConf['TCEforms']['config'] ?? [];
                            $elArray[$elName]['vDEF'] = $config['default'] ?? [];
                            if (!$elArray[$elName]['vDEF'] && $config['type'] === 'select' && !empty($config['items'])) {
                                $elArray[$elName]['vDEF'] = $config['items'][0][1] ?? '';
                            }
                        }
                        $sheetArray['data'][$sheetName]['lDEF'] = $elArray;
                    }
                }
            }
            if (!empty($sheetArray)) {
                $flexformTools = GeneralUtility::makeInstance(FlexFormTools::class);
                $returnXML = $flexformTools->flexArray2Xml($sheetArray, true);
            }
        }

        return $returnXML;
    }

    /**
     * set initial entries to field array
     *
     * @param array $fieldArray
     * @param string $contentId
     * @param bool $new
     */
    public function setFieldEntries(array &$fieldArray, string $contentId = '0', bool $new = false, $action = '')
    {
        $containerUpdateArray = [];
        if (isset($fieldArray['tx_gridelements_container'])) {
            $originalElement = BackendUtility::getRecord(
                'tt_content',
                (int)$contentId,
                'tx_gridelements_container,sys_language_uid'
            );
            if (
                $new &&
                (int)$fieldArray['tx_gridelements_container'] > 0 &&
                !empty($originalElement) &&
                (int)$fieldArray['tx_gridelements_container'] !== (int)$originalElement['tx_gridelements_container']
            ) {
                $containerUpdateArray[(int)$fieldArray['tx_gridelements_container']] = 1;
            }
            if (!empty($originalElement) && $action === 'move') {
                if (
                    (int)$fieldArray['tx_gridelements_container'] === 0 ||
                    (int)$fieldArray['tx_gridelements_container'] !== (int)$originalElement['tx_gridelements_container']
                ) {
                    $containerUpdateArray[(int)$originalElement['tx_gridelements_container']] = -1;
                }
            }
        }
        if (!empty($containerUpdateArray)) {
            $this->doGridContainerUpdate($containerUpdateArray, 'preprocess:' . $action);
        }
        $this->setFieldEntriesForGridContainers($fieldArray, $action);
    }

    /**
     * set/override entries to gridelements container
     *
     * @param array $fieldArray
     */
    public function setFieldEntriesForGridContainers(array &$fieldArray, $action)
    {
        if (!empty($fieldArray['tx_gridelements_container'])
            && isset($fieldArray['colPos']) && (int)$fieldArray['colPos'] !== -1) {
            $fieldArray['colPos'] = -1;
            $fieldArray['tx_gridelements_columns'] = 0;
            $targetContainer = BackendUtility::getRecord(
                'tt_content',
                (int)$fieldArray['tx_gridelements_container'],
                'sys_language_uid'
            );
            if (isset($targetContainer) && (int)$targetContainer['sys_language_uid'] > -1) {
                $fieldArray['sys_language_uid'] = (int)$targetContainer['sys_language_uid'];
            }
        } else {
            if (isset($fieldArray['tx_gridelements_container']) && (int)$fieldArray['tx_gridelements_container'] === 0
                && isset($fieldArray['colPos']) && (int)$fieldArray['colPos'] === -1) {
                $fieldArray['colPos'] = $this->checkForRootColumn($this->getContentUid());
                $fieldArray['tx_gridelements_columns'] = 0;
                $fieldArray['tx_gridelements_container'] = 0;
            } else {
                if (!isset($fieldArray['sys_language_uid'])
                    && isset($fieldArray['tx_gridelements_container']) && (int)$fieldArray['tx_gridelements_container'] > 0
                    && isset($fieldArray['colPos']) && (int)$fieldArray['colPos'] === -1) {
                    $targetContainer = BackendUtility::getRecord(
                        'tt_content',
                        (int)$fieldArray['tx_gridelements_container'],
                        'sys_language_uid'
                    );
                    if ((int)$targetContainer['sys_language_uid'] > -1) {
                        $fieldArray['sys_language_uid'] = (int)$targetContainer['sys_language_uid'];
                    }
                }
            }
        }
        if (isset($targetContainer) && (int)$targetContainer['sys_language_uid'] === -1) {
            $list = array_flip(GeneralUtility::trimExplode(
                ',',
                $GLOBALS['TCA']['tt_content']['ctrl']['copyAfterDuplFields'],
                true
            ));
            unset($list['sys_language_uid']);
            $GLOBALS['TCA']['tt_content']['ctrl']['copyAfterDuplFields'] = implode(',', array_flip($list));
        }
    }

    /**
     * Function to recursively determine the colPos of the root container
     * so that an element that has been removed from any container
     * will still remain in the same major page column
     *
     * @param int $contentId The uid of the current content element
     *
     * @return int The new column of this content element
     * @throws \Doctrine\DBAL\DBALException
     */
    public function checkForRootColumn(int $contentId): int
    {
        $colPos = 0;
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->getRestrictions()
            ->removeAll();
        $parent = $queryBuilder
            ->select('t1.colPos', 't1.tx_gridelements_container')
            ->from('tt_content', 't1')
            ->join(
                't1',
                'tt_content',
                't2',
                $queryBuilder->expr()->eq('t1.uid', $queryBuilder->quoteIdentifier('t2.tx_gridelements_container'))
            )
            ->where(
                $queryBuilder->expr()->eq(
                    't2.uid',
                    $queryBuilder->createNamedParameter($contentId, PDO::PARAM_INT)
                )
            )
            ->execute()
            ->fetch(PDO::FETCH_BOTH);
        if (!empty($parent)) {
            if ($parent['tx_gridelements_container'] > 0) {
                $colPos = $this->checkForRootColumn($parent['tx_gridelements_container']);
            } else {
                $colPos = (int)$parent['colPos'];
            }
        }

        return $colPos;
    }
}

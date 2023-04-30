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

use GridElementsTeam\Gridelements\Backend\LayoutSetup;
use GridElementsTeam\Gridelements\Helper\Helper;
use PDO;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\EndTimeRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\StartTimeRestriction;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class/Function which offers TCE main hook functions.
 *
 * @author Jo Hasenau <info@cybercraft.de>
 */
abstract class AbstractDataHandler
{
    /**
     * @var Connection
     */
    protected Connection $connection;

    /**
     * @var string
     */
    protected string $table;

    /**
     * @var int
     */
    protected int $pageUid;

    /**
     * @var int
     */
    protected int $contentUid = 0;

    /**
     * @var DataHandler
     */
    protected DataHandler $dataHandler;

    /**
     * @var LayoutSetup
     */
    protected LayoutSetup $layoutSetup;

    /**
     * initializes this class
     *
     * @param string $table : The name of the table the data should be saved to
     * @param string $uidPid : The uid of the record or page we are currently working on
     * @param DataHandler $dataHandler
     */
    public function init(string $table, string $uidPid, DataHandler $dataHandler)
    {
        $this->setTable($table);
        if ($table === 'tt_content' && (int)$uidPid < 0) {
            $this->setContentUid(abs((int)$uidPid));
            $pageUid = Helper::getInstance()->getPidFromUid($this->getContentUid());
            $this->setPageUid($pageUid);
        } else {
            $this->setPageUid((int)$uidPid);
        }
        $this->setTceMain($dataHandler);
        $this->injectLayoutSetup(GeneralUtility::makeInstance(LayoutSetup::class)->init($this->getPageUid()));
    }

    /**
     * getter for contentUid
     *
     * @return int contentUid
     */
    public function getContentUid(): int
    {
        return $this->contentUid;
    }

    /**
     * setter for contentUid
     *
     * @param int $contentUid
     */
    public function setContentUid(int $contentUid)
    {
        $this->contentUid = $contentUid;
    }

    /**
     * setter for dataHandler object
     *
     * @param DataHandler $dataHandler
     */
    public function setTceMain(DataHandler $dataHandler)
    {
        $this->dataHandler = $dataHandler;
    }

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
     * getter for pageUid
     *
     * @return int pageUid
     */
    public function getPageUid(): int
    {
        return $this->pageUid;
    }

    /**
     * setter for pageUid
     *
     * @param int $pageUid
     */
    public function setPageUid(int $pageUid)
    {
        $this->pageUid = $pageUid;
    }

    /**
     * Function to remove any remains of versioned records after finalizing a workspace action
     * via 'Discard' or 'Publish' commands
     * @throws \Doctrine\DBAL\DBALException
     */
    public function cleanupWorkspacesAfterFinalizing()
    {
        $queryBuilder = $this->getQueryBuilder();

        $constraints = [
            $queryBuilder->expr()->andX(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter(-1, PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    't3ver_wsid',
                    $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)
                )
            ),
        ];

        $queryBuilder->delete('tt_content')
            ->where(...$constraints)
            ->execute();
    }

    /**
     * getter for queryBuilder
     *
     * @param string $table
     * @return QueryBuilder $queryBuilder
     */
    public function getQueryBuilder(string $table = 'tt_content'): QueryBuilder
    {
        /**@var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()
            ->removeByType(HiddenRestriction::class)
            ->removeByType(StartTimeRestriction::class)
            ->removeByType(EndTimeRestriction::class);

        return $queryBuilder;
    }

    /**
     * Function to handle record actions for current or former children of translated grid containers
     * as well as translated references
     *
     * @param int $uid
     * @throws \Doctrine\DBAL\DBALException
     */
    public function checkAndUpdateTranslatedElements(int $uid)
    {
        if ($uid <= 0) {
            return;
        }
        $queryBuilder = $this->getQueryBuilder();
        $currentValues = $queryBuilder
            ->select(
                'uid',
                'tx_gridelements_container',
                'tx_gridelements_columns',
                'sys_language_uid',
                'colPos',
                'l18n_parent'
            )
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT))
            )
            ->setMaxResults(1)
            ->execute()
            ->fetch(PDO::FETCH_BOTH);
        if (!empty($currentValues['l18n_parent'])) {
            $originalUid = (int)$currentValues['uid'];
            $queryBuilder = $this->getQueryBuilder();
            $currentValues = $queryBuilder
                ->select(
                    'uid',
                    'tx_gridelements_container',
                    'tx_gridelements_columns',
                    'sys_language_uid',
                    'colPos',
                    'l18n_parent'
                )
                ->from('tt_content')
                ->where(
                    $queryBuilder->expr()->eq(
                        'uid',
                        $queryBuilder->createNamedParameter((int)$currentValues['l18n_parent'], PDO::PARAM_INT)
                    )
                )
                ->setMaxResults(1)
                ->execute()
                ->fetch(PDO::FETCH_BOTH);

            if (is_array($currentValues)) {
                $updateArray = $currentValues;
                unset($updateArray['uid']);
                unset($updateArray['sys_language_uid']);
                unset($updateArray['l18n_parent']);
                $this->getConnection()->update(
                    'tt_content',
                    $updateArray,
                    ['uid' => $originalUid]
                );
            }
        }
        if (empty($currentValues['uid'])) {
            return;
        }
        $queryBuilder = $this->getQueryBuilder();
        $translatedElementQuery = $queryBuilder
            ->select(
                'uid',
                'tx_gridelements_container',
                'tx_gridelements_columns',
                'sys_language_uid',
                'colPos',
                'l18n_parent'
            )
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq(
                    'l18n_parent',
                    $queryBuilder->createNamedParameter((int)$currentValues['uid'], PDO::PARAM_INT)
                )
            )
            ->execute();
        $translatedElements = [];
        while ($translatedElement = $translatedElementQuery->fetch(PDO::FETCH_BOTH)) {
            $translatedElements[$translatedElement['uid']] = $translatedElement;
        }
        if (empty($translatedElements)) {
            return;
        }
        $translatedContainers = [];
        if ($currentValues['tx_gridelements_container'] > 0) {
            $queryBuilder = $this->getQueryBuilder();
            $translatedContainerQuery = $queryBuilder
                ->select('uid', 'sys_language_uid')
                ->from('tt_content')
                ->where(
                    $queryBuilder->expr()->eq(
                        'l18n_parent',
                        $queryBuilder->createNamedParameter(
                            (int)$currentValues['tx_gridelements_container'],
                            PDO::PARAM_INT
                        )
                    ),
                    $queryBuilder->expr()->eq(
                        't3ver_oid',
                        $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)
                    )
                )
                ->execute();
            while ($translatedContainer = $translatedContainerQuery->fetch(PDO::FETCH_BOTH)) {
                $translatedContainers[$translatedContainer['sys_language_uid']] = $translatedContainer;
            }
        }
        $containerUpdateArray = [];
        foreach ($translatedElements as $translatedUid => $translatedElement) {
            $updateArray = [];
            if (isset($translatedContainers[$translatedElement['sys_language_uid']])) {
                $updateArray['tx_gridelements_container'] = (int)$translatedContainers[$translatedElement['sys_language_uid']]['uid'];
                $updateArray['tx_gridelements_columns'] = (int)$currentValues['tx_gridelements_columns'];
            } else {
                if ($translatedElement['tx_gridelements_container'] == $currentValues['tx_gridelements_container']) {
                    $updateArray['tx_gridelements_container'] = (int)$currentValues['tx_gridelements_container'];
                    $updateArray['tx_gridelements_columns'] = (int)$currentValues['tx_gridelements_columns'];
                } else {
                    $updateArray['tx_gridelements_container'] = 0;
                    $updateArray['tx_gridelements_columns'] = 0;
                }
            }
            $updateArray['colPos'] = (int)$currentValues['colPos'];

            $this->getConnection()->update(
                'tt_content',
                $updateArray,
                ['uid' => (int)$translatedUid]
            );

            if ($translatedElement['tx_gridelements_container'] !== $updateArray['tx_gridelements_container']) {
                if (!isset($containerUpdateArray[$translatedElement['tx_gridelements_container']])) {
                    $containerUpdateArray[$translatedElement['tx_gridelements_container']] = -1;
                } else {
                    $containerUpdateArray[$translatedElement['tx_gridelements_container']] -= 1;
                }
                if (!isset($containerUpdateArray[$updateArray['tx_gridelements_container']])) {
                    $containerUpdateArray[$updateArray['tx_gridelements_container']] = 1;
                } else {
                    $containerUpdateArray[$updateArray['tx_gridelements_container']] += 1;
                }
                $this->getTceMain()->updateRefIndex('tt_content', (int)$translatedElement['tx_gridelements_container']);
                $this->getTceMain()->updateRefIndex('tt_content', $updateArray['tx_gridelements_container']);
            }
        }
        if (!empty($containerUpdateArray)) {
            $this->doGridContainerUpdate($containerUpdateArray);
        }
    }

    /**
     * setter for Connection object
     *
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tt_content');
    }

    /**
     * getter for dataHandler
     *
     * @return DataHandler dataHandler
     */
    public function getTceMain(): DataHandler
    {
        return $this->dataHandler;
    }

    /**
     * Function to handle record actions between different grid containers
     *
     * @param array $containerUpdateArray
     * @throws \Doctrine\DBAL\DBALException
     */
    public function doGridContainerUpdate(array $containerUpdateArray = [])
    {
        if (is_array($containerUpdateArray) && !empty($containerUpdateArray)) {
            $queryBuilder = $this->getQueryBuilder();
            $currentContainers = $queryBuilder
                ->select('uid', 'tx_gridelements_children')
                ->from('tt_content')
                ->where(
                    $queryBuilder->expr()->in('uid', implode(',', array_keys($containerUpdateArray)))
                )
                ->execute()
                ->fetchAll();
            if (!empty($currentContainers)) {
                foreach ($currentContainers as $fieldArray) {
                    $fieldArray['tx_gridelements_children'] = (int)$fieldArray['tx_gridelements_children'] + (int)$containerUpdateArray[$fieldArray['uid']];
                    $updateArray = $fieldArray;
                    unset($updateArray['uid']);
                    $this->getConnection()->update(
                        'tt_content',
                        $updateArray,
                        ['uid' => (int)$fieldArray['uid']]
                    );
                    $this->getTceMain()->updateRefIndex('tt_content', (int)$fieldArray['uid']);
                }
            }
        }
    }

    /**
     * getter for table
     *
     * @return string table
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * setter for table
     *
     * @param string $table
     */
    public function setTable(string $table)
    {
        $this->table = $table;
    }
}

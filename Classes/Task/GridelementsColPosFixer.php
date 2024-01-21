<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Task;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

class GridelementsColPosFixer extends AbstractTask
{

    /**
     * Fixes Gridelements child records with broken colPos values
     * after falsely updating the DB during major core upgrades
     * with Gridelements being uninstalled.
     *
     * @return bool TRUE if task run was successful
     */
    public function execute(): bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');
        $queryBuilder
            ->update('tt_content')
            ->set(
                'colPos',
                -1,
                true,
                \PDO::PARAM_INT
            )
            ->where(
                $queryBuilder->expr()->gt(
                    'tx_gridelements_container',
                    0
                )
            )
            ->execute();
        return true;
    }

}
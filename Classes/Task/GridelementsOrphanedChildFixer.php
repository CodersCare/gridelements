<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Task;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

class GridelementsOrphanedChildFixer extends AbstractTask
{

    /**
     * Fixes Gridelements child records which refer to a container
     * which is no longer a gridelement. Can happen if the CType
     * of the gridelements container is changed while children
     * still exist.
     *
     * The reference is removed by setting tx_gridelements_container
     * to 0 and colpos to the value of colpos of the formerly
     * gridelement container CE.
     *
     * Additionally, the existing CEs will be set to not visible
     * (hidden=1) because they will previously invisible and will
     * now suddenly reapper.
     *
     * @return bool TRUE if task run was successful
     */
    public function execute(): bool
    {
        // it is not possible to use UPDATE together with JOIN in QueryBuilder
        // so we use SELECT and UPDATE seperately though it is less efficient
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $queryBuilder = $connectionPool
            ->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $rows = $queryBuilder
            ->select('tt_content.uid AS uid', 't2.colpos AS colpos')
            ->from('tt_content')
            ->join('tt_content', 'tt_content', 't2',
                $queryBuilder->expr()->eq('tt_content.tx_gridelements_container',
                    $queryBuilder->quoteIdentifier('t2.uid')
                )
            )
            ->where(
                $queryBuilder->expr()->gt(
                    'tt_content.tx_gridelements_container',
                    0
                ),
                $queryBuilder->expr()->eq(
                    'tt_content.colpos',
                    -1
                ),
                $queryBuilder->expr()->neq(
                    't2.CType',
                    $queryBuilder->createNamedParameter('gridelements_pi1')
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($rows as $row) {
            $uid = (int)($row['uid']);

            $connection = $connectionPool
                ->getConnectionForTable('tt_content');
            $connection->update(
                'tt_content',
                [
                    'colpos' => (int)($row['colpos'] ?? 0),
                    'tx_gridelements_container' => 0,
                    'hidden' => 1,
                ],
                [
                    'uid' => $uid,
                ],
                [
                    Connection::PARAM_INT,
                    Connection::PARAM_INT,
                    Connection::PARAM_INT,
                    Connection::PARAM_INT,
                ]
            );
        }

        return true;
    }

}
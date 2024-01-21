<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Task;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

class GridelementsNumberOfChildrenFixer extends AbstractTask
{

    /**
     * Fixes Gridelements parent records with broken tx_gridelements_children value
     * due to buggy behaviour of Cut/Copy/Paste or Drag/Drop methods.
     *
     * @return bool TRUE if task run was successful
     */
    public function execute(): bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');

        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $containers = $queryBuilder
            ->select('uid', 'pid', 'tx_gridelements_children')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq(
                    'CType',
                    $queryBuilder->createNamedParameter('gridelements_pi1')
                )
            )
            ->orderBy('pid')
            ->addOrderBy('uid')
            ->execute();

        if (!empty($containers)) {
            while ($container = $containers->fetchAssociative()) {
                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                    ->getQueryBuilderForTable('tt_content');

                $queryBuilder->getRestrictions()
                    ->removeAll()
                    ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

                $children = $queryBuilder
                    ->select('uid', 'pid', 'tx_gridelements_container')
                    ->from('tt_content')
                    ->where(
                        $queryBuilder->expr()->eq(
                            'tx_gridelements_container',
                            $queryBuilder->createNamedParameter($container['uid'])
                        )
                    )
                    ->execute()
                    ->fetchAllAssociative();

                if (empty($children)) {
                    $children = [];
                }

                $queryBuilder
                    ->update('tt_content')
                    ->set(
                        'tx_gridelements_children',
                        count($children),
                        true,
                        \PDO::PARAM_INT
                    )
                    ->where(
                        $queryBuilder->expr()->eq(
                            'uid',
                            $container['uid']
                        )
                    )
                    ->execute();
            }
        }
        return true;
    }

}
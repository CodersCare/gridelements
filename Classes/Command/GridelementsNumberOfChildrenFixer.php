<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Command;

use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class GridelementsNumberOfChildrenFixer extends Command
{
    protected function configure(): void
    {
        $this->setHelp('Fixes Gridelements parent records with broken tx_gridelements_children value');
    }

    /**
     * Fixes Gridelements parent records with broken tx_gridelements_children value
     *  due to buggy behaviour of Cut/Copy/Paste or Drag/Drop methods.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
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
            ->orderBy('pid')->addOrderBy('uid')->executeQuery();

        if (!empty($containers)) {
            while ($container = $containers->fetchAssociative()) {
                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                    ->getQueryBuilderForTable('tt_content');

                $queryBuilder->getRestrictions()
                    ->removeAll()
                    ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

                $children = $queryBuilder
                    ->select('uid', 'pid', 'tx_gridelements_container')
                    ->from('tt_content')->where($queryBuilder->expr()->eq(
                        'tx_gridelements_container',
                        $queryBuilder->createNamedParameter($container['uid'])
                    ))->executeQuery()
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
                        Connection::PARAM_INT
                    )->where($queryBuilder->expr()->eq(
                        'uid',
                        $container['uid']
                    ))->executeStatement();
            }
        }
        return Command::SUCCESS;
    }
}

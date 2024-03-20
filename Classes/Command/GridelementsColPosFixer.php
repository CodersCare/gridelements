<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class GridelementsColPosFixer extends Command
{
    protected function configure(): void
    {
        $this->setHelp('Fixes Gridelements child records with broken colPos values');
    }

    /**
     * Fixes Gridelements child records with broken colPos values
     * after falsely updating the DB during major core upgrades
     * with Gridelements being uninstalled.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');
        $queryBuilder
            ->update('tt_content')
            ->set(
                'colPos',
                -1,
                true,
                Connection::PARAM_INT
            )->where(
                $queryBuilder->expr()->gt(
                    'tx_gridelements_container',
                    0
                )
            )->executeStatement();
        return Command::SUCCESS;
    }
}

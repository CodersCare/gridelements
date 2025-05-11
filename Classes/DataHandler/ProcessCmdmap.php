<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\DataHandler;

/***************************************************************
 *  Copyright notice
 *  (c) 2013 Jo Hasenau <info@cybercraft.de>
 *  (c) 2013 Stefan Froemken <froemken@gmail.com>
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

use Doctrine\DBAL\Exception;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\ServerRequestFactory;

/**
 * Class/Function which offers TCE main hook functions.
 *
 * @author         Jo Hasenau <info@cybercraft.de>
 */
class ProcessCmdmap extends AbstractDataHandler
{
    public function __construct(
        protected ServerRequestInterface|null $request = null
    ) {
        if (!Environment::isCli()) {
            $this->request = $GLOBALS['TYPO3_REQUEST'] ?? ServerRequestFactory::fromGlobals();
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
     * @param DataHandler|null $parentObj The parent object that triggered this hook
     * @param array|bool $pasteUpdate Values to be updated after the record is pasted
     * @throws Exception
     */
    public function execute_processCmdmap(
        string $command,
        string $table,
        int $id,
        mixed $value,
        bool &$commandIsProcessed,
        DataHandler $parentObj = null,
        bool|array $pasteUpdate = false
    ): void {

        if (!($this->request instanceof ServerRequestInterface)) {
            return;
        }

        $this->init($table, (string)$id, $parentObj);

        $reference = (int)($this->request->getQueryParams()['reference'] ?? 0);

        if (($command === 'copy' || $command === 'move') && !$commandIsProcessed && $table === 'tt_content' && !$this->getTceMain()->isImporting) {
            if ($reference === 1) {
                $dataArray = [
                    'pid' => $value,
                    'CType' => 'shortcut',
                    'records' => $id,
                    'header' => 'Reference',
                ];

                // used for overriding container and column with real target values
                if (is_array($pasteUpdate) && !empty($pasteUpdate)) {
                    $dataArray = array_merge($dataArray, $pasteUpdate);
                }

                $clipBoard = ($this->request->getQueryParams()['CB'] ?? null);
                if (!empty($clipBoard)) {
                    $updateArray = $clipBoard['update'];
                    if (!empty($updateArray)) {
                        $dataArray = array_merge($dataArray, $updateArray);
                    }
                }

                $data = [];
                $data['tt_content']['NEW234134'] = $dataArray;

                $this->getTceMain()->start($data, []);
                $this->getTceMain()->process_datamap();

                $parentObj->registerDBList = null;
                $parentObj->remapStack = null;
                $commandIsProcessed = true;

            }
            $containerUpdateArray = [];
            if (!empty($pasteUpdate) && !empty($pasteUpdate['tx_gridelements_container'])) {
                $containerUpdateArray[$pasteUpdate['tx_gridelements_container']] = 1;
                $this->doGridContainerUpdate($containerUpdateArray, 'cmdmap: ' . $command);
            }
        }

        if (($command === 'delete' || $command === 'move') && $table === 'tt_content') {
            $containerUpdateArray = [];
            $queryBuilder = $this->getQueryBuilder();
            $originalElement = $queryBuilder
                ->select('tx_gridelements_container', 'sys_language_uid')
                ->from('tt_content')->where($queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($id, Connection::PARAM_INT)
                ))->executeQuery()
                ->fetchAssociative();

            if (!empty($originalElement['tx_gridelements_container'])) {
                $containerUpdateArray[$originalElement['tx_gridelements_container']] = -1;
                $this->doGridContainerUpdate($containerUpdateArray, 'cmdmap: ' . $command);
            }
        }

        if ($table === 'tt_content') {
            $this->cleanupWorkspacesAfterFinalizing();
        }
    }
}

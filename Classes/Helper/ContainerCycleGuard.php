<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Helper;

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

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Guards against a gridelements container becoming its own (direct or indirect)
 * container, either through tx_gridelements_container itself or through a shortcut
 * element referencing one of its own ancestor containers.
 *
 * @author Jo Hasenau <info@cybercraft.de>
 */
class ContainerCycleGuard
{
    /**
     * Walks upward from $containerUid following tx_gridelements_container until
     * reaching the top level (0), returning the ordered list of container uids from
     * $containerUid up to the root, including $containerUid itself.
     * Stops early if a uid repeats, to stay safe against a pre-existing corrupt chain.
     *
     * @param int $containerUid
     * @return int[]
     * @throws Exception
     */
    public static function getContainerAncestorChain(int $containerUid): array
    {
        $chain = [];
        $currentUid = $containerUid;
        while ($currentUid > 0 && !isset($chain[$currentUid])) {
            $chain[$currentUid] = true;
            $queryBuilder = GridElementsHelper::getQueryBuilder();
            $record = $queryBuilder
                ->select('tx_gridelements_container')
                ->from('tt_content')
                ->where($queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($currentUid, Connection::PARAM_INT)
                ))
                ->setMaxResults(1)
                ->executeQuery()
                ->fetchAssociative();
            $currentUid = (int)($record['tx_gridelements_container'] ?? 0);
        }

        return array_keys($chain);
    }

    /**
     * Determines whether assigning $candidateContainerUid as the container of
     * $recordUid would make $recordUid its own (direct or indirect) container.
     *
     * @param int $recordUid
     * @param int $candidateContainerUid
     * @return bool
     * @throws Exception
     */
    public static function wouldCreateContainerCycle(int $recordUid, int $candidateContainerUid): bool
    {
        if ($recordUid <= 0 || $candidateContainerUid <= 0) {
            return false;
        }

        return in_array($recordUid, self::getContainerAncestorChain($candidateContainerUid), true);
    }

    /**
     * Parses a tt_content shortcut 'records' field value and returns the referenced
     * tt_content uids. Only 'tt_content_' entries are relevant; 'pages_' references
     * are out of scope for this guard.
     *
     * @param string $records
     * @return int[]
     */
    public static function extractShortcutContentReferences(string $records): array
    {
        $referencedUids = [];
        foreach (GeneralUtility::trimExplode(',', $records, true) as $reference) {
            if (!str_starts_with($reference, 'tt_content_')) {
                continue;
            }
            $referencedUids[] = (int)str_replace('tt_content_', '', $reference);
        }

        return $referencedUids;
    }

    /**
     * Determines whether a shortcut's 'records' field references any of the given
     * forbidden uids (typically its own current or future ancestor containers).
     *
     * @param string $records
     * @param int[] $forbiddenUids
     * @return bool
     */
    public static function shortcutReferencesForbiddenContainer(string $records, array $forbiddenUids): bool
    {
        foreach (self::extractShortcutContentReferences($records) as $referencedUid) {
            if (in_array($referencedUid, $forbiddenUids, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recursively scans $containerUid and its full descendant subtree for CType='shortcut'
     * elements whose 'records' field references a tt_content uid that is one of the
     * container's forbidden ancestors -- which would create an infinite rendering loop
     * once $containerUid ends up (directly or indirectly) inside one of those ancestors.
     *
     * This only catches shortcuts that travel along *inside* the moved/copied container's
     * own subtree. A shortcut moved/copied on its own (not as part of a container) is not
     * covered here -- see wouldCreateContainerCycle()'s caller for that case.
     *
     * @param int $containerUid The container subtree to scan (the record being moved/copied)
     * @param int[] $forbiddenAncestorUids Container uids that must not be referenced (the
     *              destination container plus its own ancestor chain)
     * @param int[] $localChain Container uids already visited on the way down from the
     *              original $containerUid (these are safe ancestors for anything further down)
     * @return bool
     * @throws Exception
     */
    public static function subtreeReferencesForbiddenContainer(
        int $containerUid,
        array $forbiddenAncestorUids,
        array $localChain = []
    ): bool {
        if ($containerUid <= 0) {
            return false;
        }

        $localChain[] = $containerUid;
        $forbidden = array_merge($forbiddenAncestorUids, $localChain);

        $queryBuilder = GridElementsHelper::getQueryBuilder();
        $children = $queryBuilder
            ->select('uid', 'CType', 'records')
            ->from('tt_content')
            ->where($queryBuilder->expr()->eq(
                'tx_gridelements_container',
                $queryBuilder->createNamedParameter($containerUid, Connection::PARAM_INT)
            ))
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($children as $child) {
            if ($child['CType'] === 'shortcut'
                && !empty($child['records'])
                && self::shortcutReferencesForbiddenContainer((string)$child['records'], $forbidden)
            ) {
                return true;
            }

            if ($child['CType'] === 'gridelements_pi1'
                && self::subtreeReferencesForbiddenContainer((int)$child['uid'], $forbiddenAncestorUids, $localChain)
            ) {
                return true;
            }
        }

        return false;
    }
}

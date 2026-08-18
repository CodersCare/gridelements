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

use TYPO3\CMS\Core\Database\Connection;

/**
 * Evaluates the allowed/disallowed/maxitems restriction config that LayoutSetup and
 * GridElementsHelper::getSelectedBackendLayout already parse from PageTS/grid layout
 * records, and provides the live child-count query used to enforce maxitems.
 *
 * @author Jo Hasenau <info@cybercraft.de>
 */
class RestrictionGuard
{
    /**
     * $allowed/$disallowed are the already-normalized per-field maps produced by
     * GridElementsHelper::mergeAllowedDisallowedSettings(), i.e. either a flipped
     * array of allowed/disallowed values, or a '*' wildcard key.
     *
     * @param array $allowed
     * @param array $disallowed
     * @param string $value
     * @return bool
     */
    public static function isValueAllowed(array $allowed, array $disallowed, string $value): bool
    {
        return !(
            (
                !empty($allowed)
                && !isset($allowed['*'])
                && !isset($allowed[$value])
            ) || (
                !empty($disallowed)
                && (
                    isset($disallowed['*'])
                    || isset($disallowed[$value])
                )
            )
        );
    }

    /**
     * @param array $allowed
     * @param array $disallowed
     * @param string $value
     * @return bool
     */
    public static function isValueDisallowed(array $allowed, array $disallowed, string $value): bool
    {
        return !self::isValueAllowed($allowed, $disallowed, $value);
    }

    /**
     * @param int|null $maxItems
     * @param int $existingCount
     * @return bool
     */
    public static function isMaxItemsExceeded(?int $maxItems, int $existingCount): bool
    {
        return $maxItems !== null && $maxItems > 0 && $existingCount >= $maxItems;
    }

    /**
     * Counts existing tt_content records in a restriction scope: a grid container's
     * column ($container > 0) or a page's own backend-layout colPos ($container === 0).
     *
     * @param int $pid
     * @param int $container
     * @param int $colPos
     * @param int $column
     * @param int $languageUid
     * @param int $excludeUid Own uid to exclude when counting for an update of an existing record
     * @return int
     */
    public static function countExistingChildren(
        int $pid,
        int $container,
        int $colPos,
        int $column,
        int $languageUid,
        int $excludeUid = 0
    ): int {
        $queryBuilder = GridElementsHelper::getQueryBuilder();
        $queryBuilder->count('uid')->from('tt_content');

        if ($container > 0) {
            $queryBuilder->where(
                $queryBuilder->expr()->eq(
                    'tx_gridelements_container',
                    $queryBuilder->createNamedParameter($container, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'tx_gridelements_columns',
                    $queryBuilder->createNamedParameter($column, Connection::PARAM_INT)
                )
            );
        } else {
            $queryBuilder->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'colPos',
                    $queryBuilder->createNamedParameter($colPos, Connection::PARAM_INT)
                )
            );
        }

        $queryBuilder->andWhere(
            $queryBuilder->expr()->eq(
                'sys_language_uid',
                $queryBuilder->createNamedParameter($languageUid, Connection::PARAM_INT)
            )
        );

        if ($excludeUid > 0) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->neq(
                    'uid',
                    $queryBuilder->createNamedParameter($excludeUid, Connection::PARAM_INT)
                )
            );
        }

        return (int)$queryBuilder->executeQuery()->fetchOne();
    }
}

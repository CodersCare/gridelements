<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Backend;

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

use GridElementsTeam\Gridelements\Helper\SvgIconSource;
use TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Wizard icons of grid layouts, identified by page and layout for the separate icon AJAX request
 */
final class LayoutWizardIcon
{
    private const PREFIX = 'gridelements-layout-';

    public static function getIdentifier(int $pageId, string|int $layoutId): string
    {
        return self::PREFIX . $pageId . '-' . $layoutId;
    }

    /**
     * @return array{0: int, 1: string}|null Page and layout of the identifier
     */
    public static function parseIdentifier(string $identifier): ?array
    {
        if (!preg_match('/^' . self::PREFIX . '(\d+)-(.+)$/', $identifier, $matches)) {
            return null;
        }
        return [(int)$matches[1], $matches[2]];
    }

    /**
     * Registers the icon of an item of LayoutSetup::getLayoutWizardItems(), false without icon source
     */
    public static function register(string $identifier, array $item): bool
    {
        $icon = (string)($item['icon'][0] ?? '');
        if ($icon === '') {
            return false;
        }
        if (str_starts_with($icon, '../typo3conf/ext/')) {
            $icon = str_replace('../typo3conf/ext/', 'EXT:', $icon);
        }
        if (str_starts_with($icon, '../uploads/tx_gridelements/')) {
            $icon = str_replace('../', '', $icon);
        } elseif (!str_starts_with($icon, 'EXT:') && str_contains($icon, '/')) {
            $icon = GeneralUtility::resolveBackPath($item['icon'][0]);
        }
        $iconRegistry = GeneralUtility::makeInstance(IconRegistry::class);
        if (str_ends_with($icon, '.svg')) {
            $iconRegistry->registerIcon($identifier, SvgIconProvider::class, [
                'source' => SvgIconSource::resolve($icon),
            ]);
        } else {
            $iconRegistry->registerIcon($identifier, BitmapIconProvider::class, [
                'source' => $icon,
            ]);
        }
        return true;
    }
}

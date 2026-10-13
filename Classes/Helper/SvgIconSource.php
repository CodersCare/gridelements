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

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Resolves the "source" option for SvgIconProvider registrations of grid layout icons.
 *
 * @author Jo Hasenau <info@cybercraft.de>
 */
class SvgIconSource
{
    /**
     * TYPO3 12 and 13 treat a source with a leading slash as file system path, so a path below the
     * web root renders without inline SVG markup. Such a source is mapped to the existing public file.
     */
    public static function resolve(string $source): string
    {
        if (!str_starts_with($source, '/') || is_file($source) || !GeneralUtility::validPathStr($source)) {
            return $source;
        }
        $publicFile = Environment::getPublicPath() . $source;
        return is_file($publicFile) ? $publicFile : $source;
    }
}

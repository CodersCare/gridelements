<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Backend\ItemsProcFuncs;

/***************************************************************
 *  Copyright notice
 *  (c) 2013 Jo Hasenau <info@cybercraft.de>
 *  (c) 2013 Stefan froemken <froemken@gmail.com>
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

use GridElementsTeam\Gridelements\Helper\Helper;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\QueryGenerator;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Class/Function which offers TCE main hook functions.
 *
 * @author Jo Hasenau <info@cybercraft.de>
 */
abstract class AbstractItemsProcFunc implements SingletonInterface
{
    /**
     * @var LanguageService
     */
    protected LanguageService $languageService;

    /**
     * @var QueryGenerator
     */
    protected QueryGenerator $tree;

    /**
     * initializes this class
     */
    public function init()
    {
        $this->setLanguageService($GLOBALS['LANG']);
    }

    /**
     * Gets the selected backend layout
     *
     * @param int $pageId The uid of the page we are currently working on
     *
     * @return array|null An array containing the data of the selected backend layout as well as a parsed version of the layout configuration
     */
    public function getSelectedBackendLayout(int $pageId): ?array
    {
        return Helper::getInstance()->getSelectedBackendLayout($pageId);
    }

    /**
     * This method is a wrapper for unitTests because of the static method
     *
     * @param int $pageId
     *
     * @return array
     */
    public function getRootline(int $pageId): array
    {
        return BackendUtility::BEgetRootLine($pageId);
    }

    /**
     * getter for languageService
     *
     * @return LanguageService $languageService
     */
    public function getLanguageService(): LanguageService
    {
        return $this->languageService;
    }

    /**
     * setter for languageService object
     *
     * @param LanguageService $languageService
     */
    public function setLanguageService(LanguageService $languageService)
    {
        $this->languageService = $languageService;
    }

    /**
     * Gets the current backend user.
     *
     * @return BackendUserAuthentication
     */
    public function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}

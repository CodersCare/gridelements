<?php

declare(strict_types=1);
use GridElementsTeam\Gridelements\Backend\ItemsProcFuncs\CTypeList;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class CTypeListTest extends UnitTestCase
{
    /**
     * test get database connection
     *
     * @test
     */
    public function testGetLanguageService()
    {
        $itemsProcFunc = GeneralUtility::makeInstance(CTypeList::class);
        $languageService = GeneralUtility::makeInstance(LanguageService::class);
        $itemsProcFunc->setLanguageService($languageService);
        $result = $itemsProcFunc->getLanguageService();
        self::assertEquals($languageService, $result);
    }

    /**
     * test get backend user
     *
     * @test
     */
    public function testGetBackendUser()
    {
        $itemsProcFunc = GeneralUtility::makeInstance(CTypeList::class);
        $backendUserAuthentication = GeneralUtility::makeInstance(BackendUserAuthentication::class);
        $GLOBALS['BE_USER'] = $backendUserAuthentication;
        $result = $itemsProcFunc->getBackendUser();
        self::assertEquals($backendUserAuthentication, $result);
    }

    /**
     * test get selected backend layout
     *
     * @test
     */
    public function testGetSelectedBackendLayout()
    {
    }

    /**
     * test items proc func
     *
     * @test
     */
    public function testItemsProcFunc()
    {
    }

    /**
     * test check for allowed CTypes
     *
     * @test
     */
    public function testCheckForAllowedCTypes()
    {
    }
}

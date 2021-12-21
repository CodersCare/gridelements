<?php

use GridElementsTeam\Gridelements\Hooks\PageRenderer;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Tests\UnitTestCase;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PageRendererTest extends UnitTestCase
{
    /**
     * test get backend user
     *
     * @test
     */
    public function testGetBackendUser()
    {
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $GLOBALS['BE_USER'] = GeneralUtility::makeInstance(BackendUserAuthentication::class);
        $result = $pageRenderer->getBackendUser();
        self::assertEquals($GLOBALS['BE_USER'], $result);
    }

    /**
     * test get language service
     *
     * @test
     */
    public function testGetLanguageService()
    {
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageService::class);
        $result = $pageRenderer->getLanguageService();
        self::assertEquals($GLOBALS['LANG'], $result);
    }

    /**
     * test add JS CSS
     *
     * @test
     */
    public function testAddJsCss()
    {
    }
}

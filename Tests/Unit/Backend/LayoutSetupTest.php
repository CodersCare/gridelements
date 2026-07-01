<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\Backend;

use GridElementsTeam\Gridelements\Backend\LayoutSetup;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class LayoutSetupTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    #[Test]
    public function getLanguageServiceRoundTrip(): void
    {
        $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class);
        $languageService = $this->createStub(LanguageService::class);
        $layoutSetup->setLanguageService($languageService);
        self::assertEquals($languageService, $layoutSetup->getLanguageService());
    }

    #[Test]
    public function getBackendUserReturnsGlobalBeUser(): void
    {
        $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class);
        $backendUserAuthentication = new BackendUserAuthentication();
        $GLOBALS['BE_USER'] = $backendUserAuthentication;
        self::assertEquals($backendUserAuthentication, $layoutSetup->getBackendUser());
    }

    #[Test]
    public function getLayoutSetupReturnsSingleLayoutByKey(): void
    {
        $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class);
        $layoutSetup->setLayoutSetup(['1' => ['title' => 'Test Layout'], '2' => ['title' => 'Other']]);
        self::assertSame(['title' => 'Test Layout'], $layoutSetup->getLayoutSetup('1'));
    }

    #[Test]
    public function getLayoutSetupReturnsAllLayoutsWhenNoKeyGiven(): void
    {
        $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class);
        $all = ['1' => ['title' => 'A'], '2' => ['title' => 'B']];
        $layoutSetup->setLayoutSetup($all);
        self::assertSame($all, $layoutSetup->getLayoutSetup());
    }

    #[Test]
    public function getTypoScriptSetupReturnsDefaultRenderObjWhenNoMatch(): void
    {
        $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class);
        $layoutSetup->setTypoScriptSetup([]);
        $result = $layoutSetup->getTypoScriptSetup('nonexistent');
        self::assertSame('<tt_content', $result['columns.']['default.']['renderObj']);
    }

    #[Test]
    public function flexformPathRoundTrip(): void
    {
        $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class);
        $default = $layoutSetup->getFlexformConfigurationPathAndFileName();
        self::assertStringContainsString('gridelements', $default);
        $layoutSetup->setFlexformConfigurationPathAndFileName('EXT:myext/path/to/file.xml');
        self::assertSame('EXT:myext/path/to/file.xml', $layoutSetup->getFlexformConfigurationPathAndFileName());
    }

    #[Test]
    public function cacheCurrentParentReturnsNullForZeroId(): void
    {
        $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class);
        // gridContainerId=0 → early return without DB call
        self::assertNull($layoutSetup->cacheCurrentParent(0));
    }

    #[Test]
    public function cacheCurrentParentReturnsCachedValueWithoutDbWhenAlreadySeeded(): void
    {
        $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class);
        $GLOBALS['tx_gridelements']['parentElement'][7] = ['uid' => 7, 'tx_gridelements_backend_layout' => 'my_layout'];
        $result = $layoutSetup->cacheCurrentParent(7, true);
        self::assertSame(['uid' => 7, 'tx_gridelements_backend_layout' => 'my_layout'], $result);
    }

    #[Test]
    public function getLayoutColumnsReturnsEmptyForUnknownLayoutId(): void
    {
        $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class);
        $layoutSetup->setLayoutSetup([]);
        self::assertSame([], $layoutSetup->getLayoutColumns('99'));
    }

    #[Test]
    public function getLayoutSelectItemsReturnsEmptyWhenLayoutSetupIsEmpty(): void
    {
        $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class);
        $layoutSetup->setLayoutSetup([]);
        // containerId=0 and pageId=0 skip all DB paths; empty setup → empty result
        self::assertSame([], $layoutSetup->getLayoutSelectItems(0, 0, 0, 0));
    }

    #[Test]
    public function getLayoutColumnsSelectItemsReturnsEmptyWhenNoConfigRows(): void
    {
        $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class);
        $layoutSetup->setLayoutSetup(['1' => ['title' => 'Test']]);
        // layout '1' has no config.rows. → early return
        self::assertSame([], $layoutSetup->getLayoutColumnsSelectItems('1'));
    }

    #[Test]
    public function getLayoutWizardItemsReturnsEmptyWhenLayoutSetupIsEmpty(): void
    {
        $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class);
        $layoutSetup->setLayoutSetup([]);
        self::assertSame([], $layoutSetup->getLayoutWizardItems(0));
    }

    #[Test]
    public function getFlexformConfigurationReturnsInlinePiFlexformDs(): void
    {
        $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class);
        $xml = '<T3DataStructure><ROOT/></T3DataStructure>';
        $layoutSetup->setLayoutSetup(['1' => ['pi_flexform_ds' => $xml]]);
        // pi_flexform_ds without FILE: prefix → returned directly, no file read
        self::assertSame($xml, $layoutSetup->getFlexformConfiguration('1'));
    }

    #[Test]
    public function checkAvailableColumnsExtractsColumnPositionsIntoCsv(): void
    {
        $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class);
        $setup = [
            'config' => [
                'rows.' => [
                    '1.' => [
                        'columns.' => [
                            '1.' => ['colPos' => 3, 'name' => 'Left'],
                            '2.' => ['colPos' => 5, 'name' => 'Right'],
                        ],
                    ],
                ],
            ],
        ];
        $result = $layoutSetup->checkAvailableColumns($setup);
        self::assertStringContainsString(',3', $result['CSV']);
        self::assertStringContainsString(',5', $result['CSV']);
        self::assertArrayHasKey('columns', $result);
    }
}

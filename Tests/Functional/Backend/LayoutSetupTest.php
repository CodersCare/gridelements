<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Functional\Backend;

use GridElementsTeam\Gridelements\Backend\LayoutSetup;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class LayoutSetupTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['gridelementsteam/gridelements'];

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    #[Test]
    public function initLoadsLayoutRecordByAlias(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tx_gridelements_backend_layout.csv');

        $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class)->init(0);

        $allLayouts = $layoutSetup->getLayoutSetup();
        self::assertArrayHasKey('two_columns', $allLayouts);
    }

    #[Test]
    public function initLoadsLayoutRecordWithCorrectTitle(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tx_gridelements_backend_layout.csv');

        $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class)->init(0);

        $layout = $layoutSetup->getLayoutSetup('two_columns');
        self::assertSame('Two Columns', $layout['title']);
    }
}

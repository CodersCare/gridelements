<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Functional\Backend\ItemsProcFuncs;

use GridElementsTeam\Gridelements\Backend\ItemsProcFuncs\CTypeList;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class CTypeListTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['gridelementsteam/gridelements'];

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    #[Test]
    public function itemsProcFuncWithPositivePidDoesNotFilterItemsWithoutBackendLayout(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages.csv');

        $list = GeneralUtility::makeInstance(CTypeList::class);
        $params = [
            'items' => [['Text', 'text'], ['Header', 'header']],
            'row' => [
                'pid' => 1,
                'colPos' => 0,
                'tx_gridelements_container' => 0,
                'tx_gridelements_columns' => 0,
            ],
        ];

        $list->itemsProcFunc($params);

        // No backend layout assigned to page 1 → no items removed
        self::assertCount(2, $params['items']);
    }

    #[Test]
    public function itemsProcFuncWithNegativePidLoadsExistingElementAndDoesNotFilterWithoutLayout(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_simple.csv');

        $list = GeneralUtility::makeInstance(CTypeList::class);
        $params = [
            'items' => [['Text', 'text'], ['Header', 'header']],
            'row' => [
                'pid' => -10,  // insert after element uid=10 → resolves to pid=1
                'colPos' => 0,
            ],
        ];

        $list->itemsProcFunc($params);

        // Element uid=10 has pid=1, no backend layout → items unchanged
        self::assertCount(2, $params['items']);
    }
}

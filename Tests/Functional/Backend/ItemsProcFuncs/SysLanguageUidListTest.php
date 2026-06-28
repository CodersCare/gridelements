<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Functional\Backend\ItemsProcFuncs;

use GridElementsTeam\Gridelements\Backend\ItemsProcFuncs\SysLanguageUidList;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class SysLanguageUidListTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['gridelementsteam/gridelements'];

    #[Test]
    public function checkForAllowedLanguagesKeepsOnlyItemsMatchingContainerLanguage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_german_container.csv');

        $list = GeneralUtility::makeInstance(SysLanguageUidList::class);
        $items = [['English', '0'], ['German', '1'], ['French', '2']];
        $list->checkForAllowedLanguages($items, 10);

        self::assertCount(1, $items);
        self::assertSame('1', reset($items)[1]);
    }

    #[Test]
    public function checkForAllowedLanguagesKeepsAllItemsForAllLanguagesContainer(): void
    {
        // sys_language_uid=-1 means "all languages" → condition (int)$parentContainer['sys_language_uid'] > -1 is false → no filtering
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_all_languages_container.csv');

        $list = GeneralUtility::makeInstance(SysLanguageUidList::class);
        $items = [['English', '0'], ['German', '1']];
        $list->checkForAllowedLanguages($items, 20);

        self::assertCount(2, $items);
    }
}

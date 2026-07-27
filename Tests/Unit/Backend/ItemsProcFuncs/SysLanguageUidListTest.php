<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\Backend\ItemsProcFuncs;

use GridElementsTeam\Gridelements\Backend\ItemsProcFuncs\SysLanguageUidList;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class SysLanguageUidListTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private function makeList(): SysLanguageUidList
    {
        return GeneralUtility::makeInstance(SysLanguageUidList::class);
    }

    #[Test]
    public function itemsProcFuncDoesNotModifyItemsWhenContainerIsZero(): void
    {
        $params = [
            'row' => ['tx_gridelements_container' => 0, 'pid' => 5],
            'items' => [['English', '0'], ['German', '1']],
        ];
        $this->makeList()->itemsProcFunc($params);
        self::assertCount(2, $params['items']);
    }

    #[Test]
    public function checkForAllowedLanguagesDoesNotModifyItemsWhenContainerIdIsZero(): void
    {
        $items = [['English', '0'], ['German', '1']];
        $this->makeList()->checkForAllowedLanguages($items, 0);
        self::assertCount(2, $items);
    }
}

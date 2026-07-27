<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\Backend\ItemsProcFuncs;

use GridElementsTeam\Gridelements\Backend\ItemsProcFuncs\ListTypeList;
use GridElementsTeam\Gridelements\Backend\LayoutSetup;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class ListTypeListTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private function makeList(): ListTypeList
    {
        return GeneralUtility::makeInstance(ListTypeList::class);
    }

    private function seedLayout(int $pageId, array $layout): void
    {
        $GLOBALS['tx_gridelements']['pageBackendLayoutData'][$pageId] = $layout;
    }

    #[Test]
    public function checkForAllowedListTypesLeavesItemsUnchangedWhenLayoutHasNoRestrictions(): void
    {
        $this->seedLayout(20, ['allowed' => [], 'disallowed' => []]);
        $items = [['News', 'news_pi1', null, null], ['Events', 'events_pi1', null, null]];
        $this->makeList()->checkForAllowedListTypes($items, 20, 0, 0, 0);
        self::assertCount(2, $items);
    }

    #[Test]
    public function checkForAllowedListTypesRemovesItemsNotInAllowedListType(): void
    {
        $this->seedLayout(21, [
            'allowed' => [0 => ['list_type' => ['news_pi1' => 0]]],
            'disallowed' => [],
        ]);
        $items = [['News', 'news_pi1', null, null], ['Events', 'events_pi1', null, null]];
        $this->makeList()->checkForAllowedListTypes($items, 21, 0, 0, 0);
        $items = array_values($items);
        self::assertCount(1, $items);
        self::assertSame('news_pi1', $items[0][1]);
    }

    #[Test]
    public function checkForAllowedListTypesKeepsAllItemsWhenAllowedHasWildcard(): void
    {
        $this->seedLayout(22, [
            'allowed' => [0 => ['list_type' => ['*' => 0]]],
            'disallowed' => [],
        ]);
        $items = [['News', 'news_pi1', null, null], ['Events', 'events_pi1', null, null]];
        $this->makeList()->checkForAllowedListTypes($items, 22, 0, 0, 0);
        self::assertCount(2, $items);
    }

    #[Test]
    public function checkForAllowedListTypesRemovesDisallowedListType(): void
    {
        $this->seedLayout(23, [
            'allowed' => [],
            'disallowed' => [0 => ['list_type' => ['events_pi1' => 0]]],
        ]);
        $items = [['News', 'news_pi1', null, null], ['Events', 'events_pi1', null, null]];
        $this->makeList()->checkForAllowedListTypes($items, 23, 0, 0, 0);
        $items = array_values($items);
        self::assertCount(1, $items);
        self::assertSame('news_pi1', $items[0][1]);
    }

    #[Test]
    public function checkForAllowedListTypesRemovesAllItemsWhenDisallowedHasWildcard(): void
    {
        $this->seedLayout(24, [
            'allowed' => [],
            'disallowed' => [0 => ['list_type' => ['*' => 0]]],
        ]);
        $items = [['News', 'news_pi1', null, null], ['Events', 'events_pi1', null, null]];
        $this->makeList()->checkForAllowedListTypes($items, 24, 0, 0, 0);
        self::assertCount(0, $items);
    }

    #[Test]
    public function checkForAllowedListTypesUsesPageColumnAsLayoutKey(): void
    {
        $this->seedLayout(25, [
            'allowed' => [1 => ['list_type' => ['news_pi1' => 0]]],
            'disallowed' => [],
        ]);
        $items = [['News', 'news_pi1', null, null], ['Events', 'events_pi1', null, null]];
        $this->makeList()->checkForAllowedListTypes($items, 25, 1, 0, 0);
        $items = array_values($items);
        self::assertCount(1, $items);
        self::assertSame('news_pi1', $items[0][1]);
    }

    #[Test]
    public function checkForAllowedListTypesUsesGridColumnPathWhenPageColumnIsNegative(): void
    {
        $layoutSetup = $this->createMock(LayoutSetup::class);
        $layoutSetup->method('cacheCurrentParent')
            ->with(99, true)
            ->willReturn(['tx_gridelements_backend_layout' => 'my_layout']);
        $layoutSetup->method('getLayoutSetup')
            ->with('my_layout')
            ->willReturn([
                'allowed' => [3 => ['list_type' => ['news_pi1' => 0]]],
                'disallowed' => [],
            ]);

        $list = $this->makeList();
        $list->injectLayoutSetup($layoutSetup);

        $items = [['News', 'news_pi1', null, null], ['Events', 'events_pi1', null, null]];
        // pageColumn = -1 triggers grid column path; gridColumn = 3
        $list->checkForAllowedListTypes($items, 0, -1, 99, 3);
        $items = array_values($items);
        self::assertCount(1, $items);
        self::assertSame('news_pi1', $items[0][1]);
    }

    #[Test]
    public function checkForAllowedListTypesDoesNothingWhenLayoutIsEmpty(): void
    {
        $layoutSetup = $this->createMock(LayoutSetup::class);
        $layoutSetup->method('cacheCurrentParent')->willReturn([]);
        $layoutSetup->method('getLayoutSetup')->willReturn([]);

        $list = $this->makeList();
        $list->injectLayoutSetup($layoutSetup);

        $items = [['News', 'news_pi1', null, null]];
        $list->checkForAllowedListTypes($items, 0, -1, 0, 0);
        self::assertCount(1, $items);
    }
}

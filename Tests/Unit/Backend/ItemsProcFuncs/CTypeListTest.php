<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\Backend\ItemsProcFuncs;

use GridElementsTeam\Gridelements\Backend\ItemsProcFuncs\CTypeList;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class CTypeListTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private function makeList(): CTypeList
    {
        return GeneralUtility::makeInstance(CTypeList::class);
    }

    private function seedLayout(int $pageId, array $layout): void
    {
        $GLOBALS['tx_gridelements']['pageBackendLayoutData'][$pageId] = $layout;
    }

    // --- checkForAllowedCTypes logic ---

    #[Test]
    public function checkForAllowedCTypesLeavesItemsUnchangedWhenLayoutDefinesNoRestrictions(): void
    {
        // non-empty layout with no allowed/disallowed — nothing filtered
        $this->seedLayout(10, ['__config' => []]);
        $items = [['Text', 'text', null, null], ['Image', 'image', null, null]];
        $this->makeList()->checkForAllowedCTypes($items, 10, 0, 0, 0);
        self::assertCount(2, $items);
    }

    #[Test]
    public function checkForAllowedCTypesRemovesItemsNotInAllowedList(): void
    {
        $this->seedLayout(11, [
            'allowed' => [0 => ['CType' => ['text' => 0]]],
            'disallowed' => [],
        ]);
        $items = [['Text', 'text', null, null], ['Image', 'image', null, null]];
        $this->makeList()->checkForAllowedCTypes($items, 11, 0, 0, 0);
        $items = array_values($items);
        self::assertCount(1, $items);
        self::assertSame('text', $items[0][1]);
    }

    #[Test]
    public function checkForAllowedCTypesKeepsAllItemsWhenAllowedHasWildcard(): void
    {
        $this->seedLayout(12, [
            'allowed' => [0 => ['CType' => ['*' => 0]]],
            'disallowed' => [],
        ]);
        $items = [['Text', 'text', null, null], ['Image', 'image', null, null]];
        $this->makeList()->checkForAllowedCTypes($items, 12, 0, 0, 0);
        self::assertCount(2, $items);
    }

    #[Test]
    public function checkForAllowedCTypesRemovesItemsMatchingDisallowedCType(): void
    {
        $this->seedLayout(13, [
            'allowed' => [],
            'disallowed' => [0 => ['CType' => ['image' => 0]]],
        ]);
        $items = [['Text', 'text', null, null], ['Image', 'image', null, null]];
        $this->makeList()->checkForAllowedCTypes($items, 13, 0, 0, 0);
        $items = array_values($items);
        self::assertCount(1, $items);
        self::assertSame('text', $items[0][1]);
    }

    #[Test]
    public function checkForAllowedCTypesRemovesAllItemsWhenDisallowedHasWildcard(): void
    {
        $this->seedLayout(14, [
            'allowed' => [],
            'disallowed' => [0 => ['CType' => ['*' => 0]]],
        ]);
        $items = [['Text', 'text', null, null], ['Image', 'image', null, null]];
        $this->makeList()->checkForAllowedCTypes($items, 14, 0, 0, 0);
        self::assertCount(0, $items);
    }

    #[Test]
    public function checkForAllowedCTypesUsesPageColumnAsLayoutKey(): void
    {
        // column 1 restricts to 'image' only; column 0 is unrestricted
        $this->seedLayout(15, [
            'allowed' => [1 => ['CType' => ['image' => 0]]],
            'disallowed' => [],
        ]);
        $items = [['Text', 'text', null, null], ['Image', 'image', null, null]];
        $this->makeList()->checkForAllowedCTypes($items, 15, 1, 0, 0);
        $items = array_values($items);
        self::assertCount(1, $items);
        self::assertSame('image', $items[0][1]);
    }
}

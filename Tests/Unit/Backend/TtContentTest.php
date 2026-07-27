<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\Backend;

use GridElementsTeam\Gridelements\Backend\TtContent;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class TtContentTest extends UnitTestCase
{
    private function makeTtContent(): TtContent
    {
        return new TtContent();
    }

    // --- removeItemsFromListOfSelectableContainers pure-logic paths ---

    #[Test]
    public function removeItemsDoesNothingWhenCTypeIsEmpty(): void
    {
        $params = [
            'row' => ['CType' => '', 'uid' => 0],
            'items' => [['/', 0], ['Container A', 5]],
        ];
        $possibleContainers = [];

        $this->makeTtContent()->removeItemsFromListOfSelectableContainers($params, $possibleContainers);

        self::assertCount(2, $params['items']);
        self::assertEmpty($possibleContainers);
    }

    #[Test]
    public function removeItemsDoesNothingForNonGridCType(): void
    {
        $params = [
            'row' => ['CType' => 'text', 'uid' => 0],
            'items' => [['/', 0], ['Container A', 5]],
        ];
        $possibleContainers = [];

        $this->makeTtContent()->removeItemsFromListOfSelectableContainers($params, $possibleContainers);

        self::assertCount(2, $params['items']);
        self::assertEmpty($possibleContainers);
    }

    #[Test]
    public function removeItemsSplitsWhenGridCTypeAndUidIsZero(): void
    {
        // uid=0 skips the recursive DB lookup; the split itself is pure array manipulation
        $params = [
            'row' => ['CType' => 'gridelements_pi1', 'uid' => 0],
            'items' => [['/', 0], ['Container A', 5], ['Container B', 7]],
        ];
        $possibleContainers = [];

        $this->makeTtContent()->removeItemsFromListOfSelectableContainers($params, $possibleContainers);

        // first item stays in params['items'], rest go to possibleContainers keyed by value
        self::assertCount(1, $params['items']);
        self::assertSame(['/', 0], $params['items'][0]);
        self::assertArrayHasKey(5, $possibleContainers);
        self::assertArrayHasKey(7, $possibleContainers);
    }

    // --- lookForChildContainersRecursively early-exit path ---

    #[Test]
    public function lookForChildContainersRecursivelyReturnsEarlyWithEmptyIds(): void
    {
        $possibleContainers = [5 => ['Container A', 5]];

        // empty string → early return without touching DB or possibleContainers
        $this->makeTtContent()->lookForChildContainersRecursively('', $possibleContainers);

        self::assertCount(1, $possibleContainers);
    }
}

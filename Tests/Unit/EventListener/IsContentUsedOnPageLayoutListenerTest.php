<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\EventListener;

use GridElementsTeam\Gridelements\EventListener\IsContentUsedOnPageLayoutListener;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\View\Event\IsContentUsedOnPageLayoutEvent;
use TYPO3\CMS\Backend\View\PageLayoutContext;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class IsContentUsedOnPageLayoutListenerTest extends UnitTestCase
{
    private function makeEvent(array $record, bool $alreadyUsed = false): IsContentUsedOnPageLayoutEvent
    {
        $context = $this->createMock(PageLayoutContext::class);
        return new IsContentUsedOnPageLayoutEvent($record, $alreadyUsed, $context);
    }

    #[Test]
    public function setsUsedTrueWhenEventAlreadyMarkedUsed(): void
    {
        $event = $this->makeEvent(['colPos' => 0, 'tx_gridelements_container' => 0], true);
        (new IsContentUsedOnPageLayoutListener())($event);
        self::assertTrue($event->isRecordUsed());
    }

    #[Test]
    public function setsUsedTrueForGridelementsChildWithNonEmptyContainer(): void
    {
        $event = $this->makeEvent(['colPos' => -1, 'tx_gridelements_container' => 42]);
        (new IsContentUsedOnPageLayoutListener())($event);
        self::assertTrue($event->isRecordUsed());
    }

    #[Test]
    public function setsUsedFalseForNormalContentRecord(): void
    {
        $event = $this->makeEvent(['colPos' => 0, 'tx_gridelements_container' => 0]);
        (new IsContentUsedOnPageLayoutListener())($event);
        self::assertFalse($event->isRecordUsed());
    }

    #[Test]
    public function setsUsedFalseWhenColPosMinus1ButContainerIsEmpty(): void
    {
        $event = $this->makeEvent(['colPos' => -1, 'tx_gridelements_container' => 0]);
        (new IsContentUsedOnPageLayoutListener())($event);
        self::assertFalse($event->isRecordUsed());
    }

    #[Test]
    public function keepsTrueWhenAlreadyUsedRegardlessOfColPos(): void
    {
        // alreadyUsed=true stays true even if colPos is 99 (irrelevant value)
        $event = $this->makeEvent(['colPos' => 99, 'tx_gridelements_container' => 0], true);
        (new IsContentUsedOnPageLayoutListener())($event);
        self::assertTrue($event->isRecordUsed());
    }
}

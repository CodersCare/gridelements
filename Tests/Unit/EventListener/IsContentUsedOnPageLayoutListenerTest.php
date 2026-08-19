<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\EventListener;

use GridElementsTeam\Gridelements\EventListener\IsContentUsedOnPageLayoutListener;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\View\Event\IsContentUsedOnPageLayoutEvent;
use TYPO3\CMS\Backend\View\PageLayoutContext;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Covers IsContentUsedOnPageLayoutListener. IsContentUsedOnPageLayoutEvent doesn't exist on
 * TYPO3 11, and this feature has no legacy hook equivalent there either.
 */
class IsContentUsedOnPageLayoutListenerTest extends UnitTestCase
{
    private function skipUnlessTypo3TwelvePlus(): void
    {
        if ((new Typo3Version())->getMajorVersion() < 12) {
            self::markTestSkipped(
                'IsContentUsedOnPageLayoutEvent does not exist on TYPO3 11 and this feature has no '
                . 'legacy hook equivalent.'
            );
        }
    }

    private function makeEvent(array $record, bool $alreadyUsed = false): IsContentUsedOnPageLayoutEvent
    {
        $context = $this->createMock(PageLayoutContext::class);
        return new IsContentUsedOnPageLayoutEvent($record, $alreadyUsed, $context);
    }

    #[Test]
    public function setsUsedTrueWhenEventAlreadyMarkedUsed(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

        $event = $this->makeEvent(['colPos' => 0, 'tx_gridelements_container' => 0], true);
        (new IsContentUsedOnPageLayoutListener())($event);
        self::assertTrue($event->isRecordUsed());
    }

    #[Test]
    public function setsUsedTrueForGridelementsChildWithNonEmptyContainer(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

        $event = $this->makeEvent(['colPos' => -1, 'tx_gridelements_container' => 42]);
        (new IsContentUsedOnPageLayoutListener())($event);
        self::assertTrue($event->isRecordUsed());
    }

    #[Test]
    public function setsUsedFalseForNormalContentRecord(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

        $event = $this->makeEvent(['colPos' => 0, 'tx_gridelements_container' => 0]);
        (new IsContentUsedOnPageLayoutListener())($event);
        self::assertFalse($event->isRecordUsed());
    }

    #[Test]
    public function setsUsedFalseWhenColPosMinus1ButContainerIsEmpty(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

        $event = $this->makeEvent(['colPos' => -1, 'tx_gridelements_container' => 0]);
        (new IsContentUsedOnPageLayoutListener())($event);
        self::assertFalse($event->isRecordUsed());
    }

    #[Test]
    public function keepsTrueWhenAlreadyUsedRegardlessOfColPos(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

        // alreadyUsed=true stays true even if colPos is 99 (irrelevant value)
        $event = $this->makeEvent(['colPos' => 99, 'tx_gridelements_container' => 0], true);
        (new IsContentUsedOnPageLayoutListener())($event);
        self::assertTrue($event->isRecordUsed());
    }
}

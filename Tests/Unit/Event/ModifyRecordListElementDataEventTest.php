<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\Event;

use GridElementsTeam\Gridelements\Event\ModifyRecordListElementDataEvent;
use GridElementsTeam\Gridelements\Xclass\DatabaseRecordList;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Covers ModifyRecordListElementDataEvent, only ever constructed from the TYPO3 12+-only
 * Xclass\DatabaseRecordList (see Tests/Unit/Xclass/DatabaseRecordListTest.php). No TYPO3-11 counterpart.
 */
class ModifyRecordListElementDataEventTest extends UnitTestCase
{
    private function skipUnlessTypo3TwelvePlus(): void
    {
        if ((new Typo3Version())->getMajorVersion() < 12) {
            self::markTestSkipped(
                'ModifyRecordListElementDataEvent is only constructed from Xclass\DatabaseRecordList, '
                . 'the TYPO3 12+-only Xclass; it has no TYPO3-11 counterpart.'
            );
        }
    }

    private function makeEvent(): ModifyRecordListElementDataEvent
    {
        $parentObject = $this->createMock(DatabaseRecordList::class);
        return new ModifyRecordListElementDataEvent(
            'tt_content',
            ['uid' => 1, 'pid' => 10],
            2,
            ['some' => 'data'],
            $parentObject
        );
    }

    #[Test]
    public function getTableReturnsConstructorValue(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

        self::assertSame('tt_content', $this->makeEvent()->getTable());
    }

    #[Test]
    public function getRowReturnsConstructorValue(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

        self::assertSame(['uid' => 1, 'pid' => 10], $this->makeEvent()->getRow());
    }

    #[Test]
    public function getLevelReturnsConstructorValue(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

        self::assertSame(2, $this->makeEvent()->getLevel());
    }

    #[Test]
    public function getInputDataReturnsConstructorValue(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

        self::assertSame(['some' => 'data'], $this->makeEvent()->getInputData());
    }

    #[Test]
    public function getParentObjectReturnsConstructorValue(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

        $parentObject = $this->createMock(DatabaseRecordList::class);
        $event = new ModifyRecordListElementDataEvent('pages', [], 0, [], $parentObject);
        self::assertSame($parentObject, $event->getParentObject());
    }

    #[Test]
    public function getReturnDataReturnsNullInitially(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

        self::assertNull($this->makeEvent()->getReturnData());
    }

    #[Test]
    public function isPropagationStoppedReturnsFalseInitially(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

        self::assertFalse($this->makeEvent()->isPropagationStopped());
    }

    #[Test]
    public function setReturnDataStopsEventPropagation(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

        $event = $this->makeEvent();
        $event->setReturnData(['result' => 'data']);
        self::assertTrue($event->isPropagationStopped());
    }

    #[Test]
    public function setReturnDataMakesDataAccessibleViaGetter(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

        $event = $this->makeEvent();
        $event->setReturnData(['result' => 'data']);
        self::assertSame(['result' => 'data'], $event->getReturnData());
    }
}

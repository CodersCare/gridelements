<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\Event;

use GridElementsTeam\Gridelements\Event\ModifyRecordListElementDataEvent;
use GridElementsTeam\Gridelements\Xclass\DatabaseRecordList as DatabaseRecordListXclass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class ModifyRecordListElementDataEventTest extends UnitTestCase
{
    private function makeEvent(): ModifyRecordListElementDataEvent
    {
        $parentObject = $this->createMock(DatabaseRecordListXclass::class);
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
        self::assertSame('tt_content', $this->makeEvent()->getTable());
    }

    #[Test]
    public function getRowReturnsConstructorValue(): void
    {
        self::assertSame(['uid' => 1, 'pid' => 10], $this->makeEvent()->getRow());
    }

    #[Test]
    public function getLevelReturnsConstructorValue(): void
    {
        self::assertSame(2, $this->makeEvent()->getLevel());
    }

    #[Test]
    public function getInputDataReturnsConstructorValue(): void
    {
        self::assertSame(['some' => 'data'], $this->makeEvent()->getInputData());
    }

    #[Test]
    public function getParentObjectReturnsConstructorValue(): void
    {
        $parentObject = $this->createMock(DatabaseRecordListXclass::class);
        $event = new ModifyRecordListElementDataEvent('pages', [], 0, [], $parentObject);
        self::assertSame($parentObject, $event->getParentObject());
    }

    #[Test]
    public function getReturnDataReturnsNullInitially(): void
    {
        self::assertNull($this->makeEvent()->getReturnData());
    }

    #[Test]
    public function isPropagationStoppedReturnsFalseInitially(): void
    {
        self::assertFalse($this->makeEvent()->isPropagationStopped());
    }

    #[Test]
    public function setReturnDataStopsEventPropagation(): void
    {
        $event = $this->makeEvent();
        $event->setReturnData(['result' => 'data']);
        self::assertTrue($event->isPropagationStopped());
    }

    #[Test]
    public function setReturnDataMakesDataAccessibleViaGetter(): void
    {
        $event = $this->makeEvent();
        $event->setReturnData(['result' => 'data']);
        self::assertSame(['result' => 'data'], $event->getReturnData());
    }
}

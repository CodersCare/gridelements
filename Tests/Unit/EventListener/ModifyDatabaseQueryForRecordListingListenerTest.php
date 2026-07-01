<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\EventListener;

use GridElementsTeam\Gridelements\EventListener\ModifyDatabaseQueryForRecordListingListener;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\RecordList\DatabaseRecordList as CoreDatabaseRecordList;
use TYPO3\CMS\Backend\View\Event\ModifyDatabaseQueryForRecordListingEvent;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class ModifyDatabaseQueryForRecordListingListenerTest extends UnitTestCase
{
    private function makeEvent(QueryBuilder $queryBuilder, string $table): ModifyDatabaseQueryForRecordListingEvent
    {
        $recordList = $this->createMock(CoreDatabaseRecordList::class);
        return new ModifyDatabaseQueryForRecordListingEvent($queryBuilder, $table, 1, [], 0, 100, $recordList);
    }

    #[Test]
    public function doesNothingWhenNestingInListModuleIsNotConfigured(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::never())->method('andWhere');
        $event = $this->makeEvent($queryBuilder, 'tt_content');

        (new ModifyDatabaseQueryForRecordListingListener([]))($event);
    }

    #[Test]
    public function doesNothingWhenNestingInListModuleIsExplicitlyDisabled(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::never())->method('andWhere');
        $event = $this->makeEvent($queryBuilder, 'tt_content');

        (new ModifyDatabaseQueryForRecordListingListener(['nestingInListModule' => 0]))($event);
    }

    #[Test]
    public function doesNothingForNonTtContentTableWhenNestingEnabled(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::never())->method('andWhere');
        $event = $this->makeEvent($queryBuilder, 'pages');

        (new ModifyDatabaseQueryForRecordListingListener(['nestingInListModule' => true]))($event);
    }

    #[Test]
    public function addsColPosWhereClauseForTtContentWhenNestingEnabled(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::once())->method('andWhere')->with('colPos != -1');
        $event = $this->makeEvent($queryBuilder, 'tt_content');

        (new ModifyDatabaseQueryForRecordListingListener(['nestingInListModule' => 1]))($event);
    }

    #[Test]
    public function doesNotAddWhereClauseForSysFileReferenceTableWhenNestingEnabled(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::never())->method('andWhere');
        $event = $this->makeEvent($queryBuilder, 'sys_file_reference');

        (new ModifyDatabaseQueryForRecordListingListener(['nestingInListModule' => true]))($event);
    }
}

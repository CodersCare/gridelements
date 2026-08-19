<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\EventListener;

use GridElementsTeam\Gridelements\EventListener\ModifyDatabaseQueryForRecordListingListener;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\RecordList\DatabaseRecordList as CoreDatabaseRecordList;
use TYPO3\CMS\Backend\View\Event\ModifyDatabaseQueryForRecordListingEvent;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Covers ModifyDatabaseQueryForRecordListingListener, a PSR-14 listener for a TYPO3 12+-only
 * core event. TYPO3-11 counterpart: Hooks\DatabaseRecordList11::getDBlistQuery(), covered by
 * Tests/Unit/Hooks/DatabaseRecordList11Test.php.
 */
class ModifyDatabaseQueryForRecordListingListenerTest extends UnitTestCase
{
    private function skipUnlessTypo3TwelvePlus(): void
    {
        if ((new Typo3Version())->getMajorVersion() < 12) {
            self::markTestSkipped(
                'ModifyDatabaseQueryForRecordListingEvent does not exist on TYPO3 11; the equivalent '
                . 'feature is implemented by Hooks\DatabaseRecordList11::getDBlistQuery() instead, '
                . 'covered by Tests/Unit/Hooks/DatabaseRecordList11Test.php.'
            );
        }
    }

    private function makeEvent(QueryBuilder $queryBuilder, string $table): ModifyDatabaseQueryForRecordListingEvent
    {
        $recordList = $this->createMock(CoreDatabaseRecordList::class);
        return new ModifyDatabaseQueryForRecordListingEvent($queryBuilder, $table, 1, [], 0, 100, $recordList);
    }

    #[Test]
    public function doesNothingWhenNestingInListModuleIsNotConfigured(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::never())->method('andWhere');
        $event = $this->makeEvent($queryBuilder, 'tt_content');

        (new ModifyDatabaseQueryForRecordListingListener([]))($event);
    }

    #[Test]
    public function doesNothingWhenNestingInListModuleIsExplicitlyDisabled(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::never())->method('andWhere');
        $event = $this->makeEvent($queryBuilder, 'tt_content');

        (new ModifyDatabaseQueryForRecordListingListener(['nestingInListModule' => 0]))($event);
    }

    #[Test]
    public function doesNothingForNonTtContentTableWhenNestingEnabled(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::never())->method('andWhere');
        $event = $this->makeEvent($queryBuilder, 'pages');

        (new ModifyDatabaseQueryForRecordListingListener(['nestingInListModule' => true]))($event);
    }

    #[Test]
    public function addsColPosWhereClauseForTtContentWhenNestingEnabled(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::once())->method('andWhere')->with('colPos != -1');
        $event = $this->makeEvent($queryBuilder, 'tt_content');

        (new ModifyDatabaseQueryForRecordListingListener(['nestingInListModule' => 1]))($event);
    }

    #[Test]
    public function doesNotAddWhereClauseForSysFileReferenceTableWhenNestingEnabled(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::never())->method('andWhere');
        $event = $this->makeEvent($queryBuilder, 'sys_file_reference');

        (new ModifyDatabaseQueryForRecordListingListener(['nestingInListModule' => true]))($event);
    }
}

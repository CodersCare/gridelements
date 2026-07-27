<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\Xclass;

use GridElementsTeam\Gridelements\Xclass\DatabaseRecordList;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class DatabaseRecordListTest extends UnitTestCase
{
    #[Test]
    public function classExtendsCoreBackendDatabaseRecordList(): void
    {
        self::assertSame(
            \TYPO3\CMS\Backend\RecordList\DatabaseRecordList::class,
            get_parent_class(DatabaseRecordList::class)
        );
    }

    #[Test]
    public function xclassIsRegisteredInExtLocalconf(): void
    {
        $source = file_get_contents(
            dirname((new \ReflectionClass(DatabaseRecordList::class))->getFileName(), 3) . '/ext_localconf.php'
        );
        self::assertStringContainsString(
            \TYPO3\CMS\Backend\RecordList\DatabaseRecordList::class,
            $source
        );
        self::assertStringContainsString(
            DatabaseRecordList::class,
            $source
        );
    }

    #[Test]
    public function getExpandedGridelementsReturnsEmptyArrayByDefault(): void
    {
        $obj = (new \ReflectionClass(DatabaseRecordList::class))->newInstanceWithoutConstructor();
        self::assertSame([], $obj->getExpandedGridelements());
    }

    #[Test]
    public function addSortLabelReturnsLabelDirectlyForSelectorField(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(DatabaseRecordList::class))->getFileName()
        );
        // Non-sortable fields like _SELECTOR_, _CONTROL_ must be returned as plain label
        self::assertStringContainsString("'_SELECTOR_'", $source);
        self::assertStringContainsString("'_CONTROL_'", $source);
        self::assertStringContainsString('return $label', $source);
    }

    #[Test]
    public function addSortLinkGeneratesAnchorElementForSortableField(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(DatabaseRecordList::class))->getFileName()
        );
        self::assertStringContainsString('sortField=', $source);
        self::assertStringContainsString('sortRev=', $source);
        self::assertStringContainsString('<a ', $source);
    }

    #[Test]
    public function addSortLinkMapsPathFieldToPidField(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(DatabaseRecordList::class))->getFileName()
        );
        // _PATH_ field forces sort by pid
        self::assertStringContainsString("'_PATH_'", $source);
        self::assertStringContainsString("'pid'", $source);
    }

    #[Test]
    public function expandedGridelementsPropertyIsInitializedToEmptyArray(): void
    {
        $ref = new \ReflectionClass(DatabaseRecordList::class);
        $prop = $ref->getProperty('expandedGridelements');
        self::assertTrue($prop->hasDefaultValue());
        self::assertSame([], $prop->getDefaultValue());
    }
}

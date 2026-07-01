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
    public function expandedGridelementsPropertyIsInitializedToEmptyArray(): void
    {
        $ref = new \ReflectionClass(DatabaseRecordList::class);
        $prop = $ref->getProperty('expandedGridelements');
        self::assertTrue($prop->hasDefaultValue());
        self::assertSame([], $prop->getDefaultValue());
    }
}

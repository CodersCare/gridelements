<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\Xclass;

use GridElementsTeam\Gridelements\Xclass\DatabaseRecordList12;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class DatabaseRecordList12Test extends UnitTestCase
{
    #[Test]
    public function classExtendsCoreBackendDatabaseRecordList(): void
    {
        self::assertSame(
            \TYPO3\CMS\Backend\RecordList\DatabaseRecordList::class,
            get_parent_class(DatabaseRecordList12::class)
        );
    }

    #[Test]
    public function xclassIsRegisteredInExtLocalconf(): void
    {
        $source = file_get_contents(
            dirname((new \ReflectionClass(DatabaseRecordList12::class))->getFileName(), 3) . '/ext_localconf.php'
        );
        self::assertStringContainsString(
            \TYPO3\CMS\Backend\RecordList\DatabaseRecordList::class,
            $source
        );
        self::assertStringContainsString(
            DatabaseRecordList12::class,
            $source
        );
    }

    #[Test]
    public function getExpandedGridelementsReturnsEmptyArrayByDefault(): void
    {
        $obj = (new \ReflectionClass(DatabaseRecordList12::class))->newInstanceWithoutConstructor();
        self::assertSame([], $obj->getExpandedGridelements());
    }

    #[Test]
    public function usesResetQueryPartInsteadOfResetOrderBy(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(DatabaseRecordList12::class))->getFileName()
        );
        // CMS 12 QueryBuilder uses resetQueryPart('orderBy'); ->resetOrderBy() is CMS 13+ only
        self::assertStringContainsString("resetQueryPart('orderBy')", $source);
        self::assertStringNotContainsString('->resetOrderBy()', $source);
    }

    #[Test]
    public function expandedGridelementsPropertyIsInitializedToEmptyArray(): void
    {
        $ref = new \ReflectionClass(DatabaseRecordList12::class);
        $prop = $ref->getProperty('expandedGridelements');
        self::assertTrue($prop->hasDefaultValue());
        self::assertSame([], $prop->getDefaultValue());
    }
}

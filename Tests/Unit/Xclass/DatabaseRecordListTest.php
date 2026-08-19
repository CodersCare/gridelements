<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\Xclass;

use GridElementsTeam\Gridelements\Xclass\DatabaseRecordList;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Covers Xclass\DatabaseRecordList, the TYPO3 12+ Xclass of TYPO3\CMS\Backend\RecordList\DatabaseRecordList
 * (that core class doesn't exist on TYPO3 11 - see Tests/Unit/Xclass/DatabaseRecordList11Test.php).
 */
class DatabaseRecordListTest extends UnitTestCase
{
    private function skipUnlessTypo3TwelvePlus(): void
    {
        if ((new Typo3Version())->getMajorVersion() < 12) {
            self::markTestSkipped(
                'GridElementsTeam\Gridelements\Xclass\DatabaseRecordList extends a core class that only '
                . 'exists on TYPO3 12+; TYPO3 11 uses Xclass\DatabaseRecordList11 instead, covered by '
                . 'Tests/Unit/Xclass/DatabaseRecordList11Test.php.'
            );
        }
    }

    #[Test]
    public function classExtendsCoreBackendDatabaseRecordList(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

        self::assertSame(
            \TYPO3\CMS\Backend\RecordList\DatabaseRecordList::class,
            get_parent_class(DatabaseRecordList::class)
        );
    }

    #[Test]
    public function xclassIsRegisteredInExtLocalconf(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

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
        $this->skipUnlessTypo3TwelvePlus();

        $obj = (new \ReflectionClass(DatabaseRecordList::class))->newInstanceWithoutConstructor();
        self::assertSame([], $obj->getExpandedGridelements());
    }

    #[Test]
    public function expandedGridelementsPropertyIsInitializedToEmptyArray(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

        $ref = new \ReflectionClass(DatabaseRecordList::class);
        $prop = $ref->getProperty('expandedGridelements');
        self::assertTrue($prop->hasDefaultValue());
        self::assertSame([], $prop->getDefaultValue());
    }
}

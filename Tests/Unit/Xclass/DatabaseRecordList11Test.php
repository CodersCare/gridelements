<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\Xclass;

use GridElementsTeam\Gridelements\Xclass\DatabaseRecordList11;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Covers Xclass\DatabaseRecordList11, the TYPO3 11 Xclass of TYPO3\CMS\Recordlist\RecordList\DatabaseRecordList.
 * See Tests/Unit/Xclass/DatabaseRecordListTest.php for the TYPO3 12+ counterpart.
 */
class DatabaseRecordList11Test extends UnitTestCase
{
    private function skipUnlessTypo3Eleven(): void
    {
        if ((new Typo3Version())->getMajorVersion() >= 12) {
            self::markTestSkipped(
                'GridElementsTeam\Gridelements\Xclass\DatabaseRecordList11 extends a core class that only '
                . 'exists on TYPO3 11; TYPO3 12+ uses Xclass\DatabaseRecordList instead, covered by '
                . 'Tests/Unit/Xclass/DatabaseRecordListTest.php.'
            );
        }
    }

    #[Test]
    public function classExtendsCoreRecordlistDatabaseRecordList(): void
    {
        $this->skipUnlessTypo3Eleven();

        self::assertSame(
            \TYPO3\CMS\Recordlist\RecordList\DatabaseRecordList::class,
            get_parent_class(DatabaseRecordList11::class)
        );
    }

    #[Test]
    public function xclassIsRegisteredInExtLocalconf(): void
    {
        $this->skipUnlessTypo3Eleven();

        $source = file_get_contents(
            dirname((new \ReflectionClass(DatabaseRecordList11::class))->getFileName(), 3) . '/ext_localconf.php'
        );
        self::assertStringContainsString(
            \TYPO3\CMS\Recordlist\RecordList\DatabaseRecordList::class,
            $source
        );
        self::assertStringContainsString(
            DatabaseRecordList11::class,
            $source
        );
    }

    #[Test]
    public function getExpandedGridelementsReturnsEmptyArrayByDefault(): void
    {
        $this->skipUnlessTypo3Eleven();

        $obj = (new \ReflectionClass(DatabaseRecordList11::class))->newInstanceWithoutConstructor();
        self::assertSame([], $obj->getExpandedGridelements());
    }

    #[Test]
    public function expandedGridelementsPropertyIsInitializedToEmptyArray(): void
    {
        $this->skipUnlessTypo3Eleven();

        $ref = new \ReflectionClass(DatabaseRecordList11::class);
        $prop = $ref->getProperty('expandedGridelements');
        self::assertTrue($prop->hasDefaultValue());
        self::assertSame([], $prop->getDefaultValue());
    }
}

<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\Plugin;

use GridElementsTeam\Gridelements\Plugin\Gridelements;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class PluginTest extends UnitTestCase
{
    private function makePlugin(): Gridelements
    {
        return (new \ReflectionClass(Gridelements::class))->newInstanceWithoutConstructor();
    }

    #[Test]
    public function extensionKeyIsGridelements(): void
    {
        self::assertSame('gridelements', $this->makePlugin()->extKey);
    }

    #[Test]
    public function prefixIdIsGreidelements(): void
    {
        self::assertSame('Gridelements', $this->makePlugin()->prefixId);
    }

    #[Test]
    public function getUsedColumnsReturnsEmptyArrayForNoColumns(): void
    {
        self::assertSame([], $this->makePlugin()->getUsedColumns([]));
    }

    #[Test]
    public function getUsedColumnsConvertsValuesToKeys(): void
    {
        $result = $this->makePlugin()->getUsedColumns([0, 1, 2]);
        self::assertSame([0 => '', 1 => '', 2 => ''], $result);
    }

    #[Test]
    public function getUsedColumnsPreservesColumnOrder(): void
    {
        $result = $this->makePlugin()->getUsedColumns([3, 1, 0]);
        self::assertSame([3, 1, 0], array_keys($result));
    }

    #[Test]
    public function setParentGridDataPrefixesAllKeys(): void
    {
        $result = $this->makePlugin()->setParentGridData(['uid' => 5, 'pid' => 1]);
        self::assertArrayHasKey('parentgrid_uid', $result);
        self::assertArrayHasKey('parentgrid_pid', $result);
        self::assertSame(5, $result['parentgrid_uid']);
        self::assertSame(1, $result['parentgrid_pid']);
    }

    #[Test]
    public function setParentGridDataReturnsEmptyForEmptyInput(): void
    {
        self::assertSame([], $this->makePlugin()->setParentGridData([]));
    }

    #[Test]
    public function setParentGridDataPreservesValues(): void
    {
        $nested = ['a' => [1, 2, 3]];
        $result = $this->makePlugin()->setParentGridData($nested);
        self::assertSame([1, 2, 3], $result['parentgrid_a']);
    }

    #[Test]
    public function getParentGridDataStripsViewChildrenAndColumns(): void
    {
        $data = [
            'uid' => 10,
            'tx_gridelements_view_children' => [['uid' => 1]],
            'tx_gridelements_view_child_1' => 'rendered',
            'tx_gridelements_view_columns' => [0 => 'col0'],
            'tx_gridelements_view_column_0' => 'col0content',
        ];
        $result = $this->makePlugin()->getParentGridData($data);
        self::assertArrayNotHasKey('parentgrid_tx_gridelements_view_children', $result);
        self::assertArrayNotHasKey('parentgrid_tx_gridelements_view_columns', $result);
        self::assertArrayNotHasKey('parentgrid_tx_gridelements_view_child_1', $result);
        self::assertArrayNotHasKey('parentgrid_tx_gridelements_view_column_0', $result);
    }

    #[Test]
    public function getParentGridDataPrefixesRemainingKeys(): void
    {
        $result = $this->makePlugin()->getParentGridData(['uid' => 99, 'pid' => 2]);
        self::assertSame(99, $result['parentgrid_uid']);
        self::assertSame(2, $result['parentgrid_pid']);
    }
}

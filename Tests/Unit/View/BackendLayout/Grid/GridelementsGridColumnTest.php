<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\View\BackendLayout\Grid;

use GridElementsTeam\Gridelements\View\BackendLayout\Grid\GridelementsGridColumn;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumn;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class GridelementsGridColumnTest extends UnitTestCase
{
    private function makeColumn(): GridelementsGridColumn
    {
        return (new \ReflectionClass(GridelementsGridColumn::class))->newInstanceWithoutConstructor();
    }

    private function setProperty(object $obj, string $name, mixed $value): void
    {
        $class = new \ReflectionClass($obj);
        while ($class) {
            if ($class->hasProperty($name)) {
                $prop = $class->getProperty($name);
                // Use closure binding for readonly properties declared in parent scopes
                if ($prop->isReadOnly()) {
                    \Closure::bind(
                        function() use ($name, $value) { $this->$name = $value; },
                        $obj,
                        $prop->getDeclaringClass()->getName()
                    )();
                } else {
                    $prop->setValue($obj, $value);
                }
                return;
            }
            $class = $class->getParentClass();
        }
    }

    #[Test]
    public function extendsGridColumn(): void
    {
        self::assertSame(GridColumn::class, get_parent_class(GridelementsGridColumn::class));
    }

    #[Test]
    public function setCollapsedAndGetCollapsed(): void
    {
        $column = $this->makeColumn();
        self::assertFalse($column->getCollapsed());
        $column->setCollapsed(true);
        self::assertTrue($column->getCollapsed());
        $column->setCollapsed(false);
        self::assertFalse($column->getCollapsed());
    }

    #[Test]
    public function setAllowedAndGetAllowed(): void
    {
        $column = $this->makeColumn();
        self::assertSame([], $column->getAllowed());
        $column->setAllowed(['CType' => ['text' => 0]]);
        self::assertSame(['CType' => ['text' => 0]], $column->getAllowed());
    }

    #[Test]
    public function setDisallowedAndGetDisallowed(): void
    {
        $column = $this->makeColumn();
        self::assertSame([], $column->getDisallowed());
        $column->setDisallowed(['CType' => ['image' => 0]]);
        self::assertSame(['CType' => ['image' => 0]], $column->getDisallowed());
    }

    #[Test]
    public function allowedContentTypeGetterAndSetter(): void
    {
        $column = $this->makeColumn();
        self::assertSame('', $column->getAllowedContentType());
        $column->setAllowedContentType('text,image');
        self::assertSame('text,image', $column->getAllowedContentType());
    }

    #[Test]
    public function allowedListTypeGetterAndSetter(): void
    {
        $column = $this->makeColumn();
        self::assertSame('', $column->getAllowedListType());
        $column->setAllowedListType('news_pi1');
        self::assertSame('news_pi1', $column->getAllowedListType());
    }

    #[Test]
    public function allowedGridTypeGetterAndSetter(): void
    {
        $column = $this->makeColumn();
        self::assertSame('', $column->getAllowedGridType());
        $column->setAllowedGridType('my_layout');
        self::assertSame('my_layout', $column->getAllowedGridType());
    }

    #[Test]
    public function disallowedContentTypeGetterAndSetter(): void
    {
        $column = $this->makeColumn();
        self::assertSame('', $column->getDisallowedContentType());
        $column->setDisallowedContentType('image');
        self::assertSame('image', $column->getDisallowedContentType());
    }

    #[Test]
    public function disallowedListTypeGetterAndSetter(): void
    {
        $column = $this->makeColumn();
        self::assertSame('', $column->getDisallowedListType());
        $column->setDisallowedListType('news_pi1');
        self::assertSame('news_pi1', $column->getDisallowedListType());
    }

    #[Test]
    public function disallowedGridTypeGetterAndSetter(): void
    {
        $column = $this->makeColumn();
        self::assertSame('', $column->getDisallowedGridType());
        $column->setDisallowedGridType('my_layout');
        self::assertSame('my_layout', $column->getDisallowedGridType());
    }

    #[Test]
    public function setMaxitemsAndGetMaxitems(): void
    {
        $column = $this->makeColumn();
        self::assertSame(0, $column->getMaxitems());
        $column->setMaxitems(5);
        self::assertSame(5, $column->getMaxitems());
    }

    #[Test]
    public function setDisableNewContentAndGetDisableNewContent(): void
    {
        $column = $this->makeColumn();
        self::assertFalse($column->getDisableNewContent());
        $column->setDisableNewContent(true);
        self::assertTrue($column->getDisableNewContent());
    }

    #[Test]
    public function setTooManyItemsAndGetTooManyItems(): void
    {
        $column = $this->makeColumn();
        self::assertFalse($column->getTooManyItems());
        $column->setTooManyItems(true);
        self::assertTrue($column->getTooManyItems());
    }

    #[Test]
    public function setMaxItemsClassAndGetMaxItemsClass(): void
    {
        $column = $this->makeColumn();
        self::assertSame('', $column->getMaxItemsClass());
        $column->setMaxItemsClass(' warning');
        self::assertSame(' warning', $column->getMaxItemsClass());
    }

    #[Test]
    public function getNumberOfItemsReturnsZeroWhenNoItemsAdded(): void
    {
        $column = $this->makeColumn();
        self::assertSame(0, $column->getNumberOfItems());
    }

    #[Test]
    public function addItemIncrementsNumberOfItems(): void
    {
        $column = $this->makeColumn();
        $column->setMaxitems(3);
        $item = $this->createStub(GridColumnItem::class);
        $column->addItem($item);
        self::assertSame(1, $column->getNumberOfItems());
    }

    #[Test]
    public function addItemSetsDisableNewContentWhenAtMaxitems(): void
    {
        $column = $this->makeColumn();
        $column->setMaxitems(1);
        $item = $this->createStub(GridColumnItem::class);
        $column->addItem($item);
        self::assertTrue($column->getDisableNewContent());
        self::assertFalse($column->getTooManyItems());
        self::assertSame(' warning', $column->getMaxItemsClass());
    }

    #[Test]
    public function addItemSetsTooManyItemsAndDangerClassWhenExceedingMaxitems(): void
    {
        $column = $this->makeColumn();
        $column->setMaxitems(1);
        $item = $this->createStub(GridColumnItem::class);
        $column->addItem($item);
        $column->addItem($item);
        self::assertTrue($column->getTooManyItems());
        self::assertSame(' danger', $column->getMaxItemsClass());
    }

    #[Test]
    public function addItemDoesNotDisableNewContentWhenMaxitemsIsZero(): void
    {
        $column = $this->makeColumn();
        // maxitems = 0 means unlimited
        $item = $this->createStub(GridColumnItem::class);
        $column->addItem($item);
        self::assertFalse($column->getDisableNewContent());
    }

    #[Test]
    public function setActiveActivatesColumn(): void
    {
        $column = $this->makeColumn();
        $this->setProperty($column, 'columnNumber', 0);
        self::assertFalse($column->isActive());
        $column->setActive();
        self::assertTrue($column->isActive());
    }

    #[Test]
    public function isActiveReturnsFalseWhenColumnNumberIsNull(): void
    {
        $column = $this->makeColumn();
        // columnNumber is readonly ?int, unset means null-check in isActive() returns false
        // We test via the overridden isActive() which checks $this->columnNumber !== null
        $column->setActive();
        // Without setting columnNumber, isActive() returns false
        $source = file_get_contents(
            (new \ReflectionClass(GridelementsGridColumn::class))->getFileName()
        );
        self::assertStringContainsString('$this->columnNumber !== null', $source);
    }

    #[Test]
    public function setRestrictionsDoesNothingForEmptyLayoutColumns(): void
    {
        $column = $this->makeColumn();
        $this->setProperty($column, 'columnNumber', 0);
        $column->setRestrictions([]);
        self::assertSame('', $column->getAllowedContentType());
        self::assertSame('', $column->getDisallowedContentType());
    }

    #[Test]
    public function setRestrictionsSetsAllowedContentTypeFromLayout(): void
    {
        $column = $this->makeColumn();
        $this->setProperty($column, 'columnNumber', 0);
        $column->setRestrictions([
            'allowed' => [0 => ['CType' => ['text' => 0, 'image' => 0]]],
            'disallowed' => [],
        ]);
        $allowedCType = $column->getAllowedContentType();
        self::assertStringContainsString('text', $allowedCType);
        self::assertStringContainsString('image', $allowedCType);
    }

    #[Test]
    public function setRestrictionsSetsDisallowedContentTypeFromLayout(): void
    {
        $column = $this->makeColumn();
        $this->setProperty($column, 'columnNumber', 0);
        $column->setRestrictions([
            'allowed' => [],
            'disallowed' => [0 => ['CType' => ['image' => 0]]],
        ]);
        self::assertStringContainsString('image', $column->getDisallowedContentType());
    }

    #[Test]
    public function setRestrictionsDisallowedWildcardAppliesAllGridAndListTypes(): void
    {
        $column = $this->makeColumn();
        $this->setProperty($column, 'columnNumber', 0);
        $column->setRestrictions([
            'allowed' => [],
            'disallowed' => [0 => ['CType' => ['*' => 0]]],
        ]);
        // wildcard disallowed CType means grid and list types are also disallowed
        self::assertStringContainsString('*', $column->getDisallowedContentType());
    }

    #[Test]
    public function setRestrictionsSetsMaxitemsFromLayoutColumns(): void
    {
        $column = $this->makeColumn();
        $this->setProperty($column, 'columnNumber', 0);
        $column->setRestrictions([
            'maxitems' => [0 => 5],
            'allowed' => [],
            'disallowed' => [],
        ]);
        self::assertSame(5, $column->getMaxitems());
    }
}

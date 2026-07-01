<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\View\BackendLayout\Grid;

use GridElementsTeam\Gridelements\View\BackendLayout\Grid\GridelementsGridColumnItem;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class GridelementsGridColumnItemTest extends UnitTestCase
{
    #[Test]
    public function extendsGridColumnItem(): void
    {
        self::assertSame(
            GridColumnItem::class,
            get_parent_class(GridelementsGridColumnItem::class)
        );
    }

    #[Test]
    public function parentGridColumnItemHasNoGetRowMethod(): void
    {
        self::assertFalse(method_exists(GridColumnItem::class, 'getRow'));
    }

    #[Test]
    public function gridColumnItemDoesNotExposeGetRowMethod(): void
    {
        self::assertFalse(method_exists(GridelementsGridColumnItem::class, 'getRow'));
    }

    #[Test]
    public function parentRecordAccessUsesDirectArrayOnRecord(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(GridelementsGridColumnItem::class))->getFileName()
        );
        self::assertStringContainsString('$this->record', $source);
    }

    #[Test]
    public function constructorRecordParameterIsTypedAsArray(): void
    {
        $ref = new \ReflectionMethod(GridelementsGridColumnItem::class, '__construct');
        $recordParam = null;
        foreach ($ref->getParameters() as $p) {
            if ($p->getName() === 'record') {
                $recordParam = $p;
                break;
            }
        }
        self::assertNotNull($recordParam, 'Constructor must have a $record parameter');
        $type = $recordParam->getType();
        self::assertInstanceOf(\ReflectionNamedType::class, $type);
        self::assertSame('array', (string)$type);
    }

    #[Test]
    public function wrapperClassNameUsesDirectRecordArrayAccess(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(GridelementsGridColumnItem::class))->getFileName()
        );
        preg_match(
            '/function getWrapperClassName.*?(?=\n    (?:public|protected|private|\/\*\*|\}))/s',
            $source,
            $matches
        );
        if ($matches) {
            self::assertStringContainsString('$this->record[', $matches[0]);
        }
    }
}

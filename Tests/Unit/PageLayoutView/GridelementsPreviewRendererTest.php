<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\PageLayoutView;

use GridElementsTeam\Gridelements\PageLayoutView\GridelementsPreviewRenderer;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Preview\StandardContentPreviewRenderer;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class GridelementsPreviewRendererTest extends UnitTestCase
{
    #[Test]
    public function implementsPreviewRendererInterface(): void
    {
        self::assertContains(
            \TYPO3\CMS\Backend\Preview\PreviewRendererInterface::class,
            class_implements(GridelementsPreviewRenderer::class)
        );
    }

    #[Test]
    public function extendsStandardContentPreviewRenderer(): void
    {
        self::assertSame(
            StandardContentPreviewRenderer::class,
            get_parent_class(GridelementsPreviewRenderer::class)
        );
    }

    #[Test]
    public function renderGridContainerCallsGetRecordForArrayAccess(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(GridelementsPreviewRenderer::class))->getFileName()
        );
        self::assertStringContainsString('$item->getRecord()', $source);
    }

    #[Test]
    public function doesNotUseMethodExistsGetRowGuard(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(GridelementsPreviewRenderer::class))->getFileName()
        );
        self::assertStringNotContainsString("method_exists(\$item, 'getRow')", $source);
    }

    #[Test]
    public function usesStandaloneViewForRendering(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(GridelementsPreviewRenderer::class))->getFileName()
        );
        self::assertStringContainsString('StandaloneView', $source);
    }

    #[Test]
    public function doesNotUseViewFactoryInterface(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(GridelementsPreviewRenderer::class))->getFileName()
        );
        self::assertStringNotContainsString('ViewFactoryInterface', $source);
    }

    #[Test]
    public function doesNotWrapChildRecordsViaRecordFactory(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(GridelementsPreviewRenderer::class))->getFileName()
        );
        self::assertStringNotContainsString('RecordFactory', $source);
    }
}

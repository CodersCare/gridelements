<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\PageLayoutView;

use GridElementsTeam\Gridelements\PageLayoutView\ShortcutPreviewRenderer;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Tests for ShortcutPreviewRenderer in CMS 13.
 *
 */
class ShortcutPreviewRendererTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private function makeRenderer(): ShortcutPreviewRenderer
    {
        $queryBuilder = $this->createStub(QueryBuilder::class);
        return new ShortcutPreviewRenderer($queryBuilder, []);
    }

    #[Test]
    public function getTreeListReturnsEmptyStringForPositiveIdWithNoDescendants(): void
    {
        $pageRepoStub = $this->createStub(PageRepository::class);
        $pageRepoStub->method('getDescendantPageIdsRecursive')->willReturn([]);
        GeneralUtility::addInstance(PageRepository::class, $pageRepoStub);

        $renderer = $this->makeRenderer();
        $result = $renderer->getTreeList(5, 0);
        self::assertSame('', $result);
    }

    #[Test]
    public function getTreeListIncludesCurrentPageIdWhenNegativeId(): void
    {
        $pageRepoStub = $this->createStub(PageRepository::class);
        $pageRepoStub->method('getDescendantPageIdsRecursive')->willReturn([6, 7]);
        GeneralUtility::addInstance(PageRepository::class, $pageRepoStub);

        $renderer = $this->makeRenderer();
        $result = $renderer->getTreeList(-5, 2);
        self::assertSame('5,6,7', $result);
    }

    #[Test]
    public function getTreeListReturnsDescendantsForPositiveId(): void
    {
        $pageRepoStub = $this->createStub(PageRepository::class);
        $pageRepoStub->method('getDescendantPageIdsRecursive')->willReturn([6, 7]);
        GeneralUtility::addInstance(PageRepository::class, $pageRepoStub);

        $renderer = $this->makeRenderer();
        $result = $renderer->getTreeList(5, 2);
        self::assertSame('6,7', $result);
    }

    #[Test]
    public function getTreeListBypassTruePathStillManipulatesWhereHidDel(): void
    {
        // In gridelements13, passing $dontCheckEnableFields = true causes the code to
        // read/write $pageRepository->where_hid_del, which is protected in CMS 13 and
        // cannot be accessed from outside PageRepository without reflection.
        // This test documents that the property manipulation is still present in the source.
        $source = file_get_contents(
            (new \ReflectionClass(ShortcutPreviewRenderer::class))->getFileName()
        );
        self::assertStringContainsString('where_hid_del', $source);
    }

    #[Test]
    public function getTreeListPassesFalseBypassFlagByDefault(): void
    {
        $pageRepoMock = $this->createMock(PageRepository::class);
        $pageRepoMock->expects(self::once())
            ->method('getDescendantPageIdsRecursive')
            ->with(
                self::anything(),
                self::anything(),
                self::anything(),
                self::anything(),
                self::identicalTo(false)
            )
            ->willReturn([]);
        GeneralUtility::addInstance(PageRepository::class, $pageRepoMock);

        $renderer = $this->makeRenderer();
        $renderer->getTreeList(1, 1);
    }

    #[Test]
    public function implementsPreviewRendererInterface(): void
    {
        self::assertContains(
            \TYPO3\CMS\Backend\Preview\PreviewRendererInterface::class,
            class_implements(ShortcutPreviewRenderer::class)
        );
    }

    #[Test]
    public function extendsStandardContentPreviewRenderer(): void
    {
        self::assertSame(
            \TYPO3\CMS\Backend\Preview\StandardContentPreviewRenderer::class,
            get_parent_class(ShortcutPreviewRenderer::class)
        );
    }
}

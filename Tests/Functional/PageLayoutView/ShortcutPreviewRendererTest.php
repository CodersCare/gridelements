<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Functional\PageLayoutView;

use GridElementsTeam\Gridelements\PageLayoutView\ShortcutPreviewRenderer;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Covers ShortcutPreviewRenderer::getTreeList() on the TYPO3 11 code path
 * (getDescendantPageIdsRecursiveTypo3Eleven()), which needs a real DB connection to test.
 * See Tests/Unit/PageLayoutView/ShortcutPreviewRendererTest.php for the TYPO3 12+ path.
 *
 * Fixture page tree (Fixtures/pages_tree.csv):
 *   1 (Root)
 *     2 (Child A)
 *     3 (Child B)
 *       4 (Grandchild of B)
 *     5 (Hidden Child, hidden=1)
 */
class ShortcutPreviewRendererTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['gridelementsteam/gridelements'];

    private ShortcutPreviewRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        if ((new Typo3Version())->getMajorVersion() >= 12) {
            self::markTestSkipped(
                'getDescendantPageIdsRecursiveTypo3Eleven() is only used by getTreeList() on TYPO3 11; '
                . 'see Tests/Unit/PageLayoutView/ShortcutPreviewRendererTest.php for the TYPO3 12+ path.'
            );
        }

        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_tree.csv');

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_content');
        $this->renderer = new ShortcutPreviewRenderer($queryBuilder, []);
    }

    #[Test]
    public function getTreeListReturnsDirectChildrenAtDepthOne(): void
    {
        self::assertSame('2,3', $this->renderer->getTreeList(1, 1));
    }

    #[Test]
    public function getTreeListReturnsDescendantsAtDepthTwo(): void
    {
        self::assertSame('2,3,4', $this->renderer->getTreeList(1, 2));
    }

    #[Test]
    public function getTreeListRespectsBeginOffset(): void
    {
        // begin=1 skips the immediate children, returning only grandchildren
        self::assertSame('4', $this->renderer->getTreeList(1, 2, 1));
    }

    #[Test]
    public function getTreeListExcludesHiddenPagesByDefault(): void
    {
        self::assertSame('2,3', $this->renderer->getTreeList(1, 1, 0, false));
    }

    #[Test]
    public function getTreeListIncludesHiddenPagesWhenEnableFieldsBypassed(): void
    {
        self::assertSame('2,3,5', $this->renderer->getTreeList(1, 1, 0, true));
    }

    #[Test]
    public function getTreeListIncludesCurrentPageIdWhenNegativeId(): void
    {
        self::assertSame('1,2,3', $this->renderer->getTreeList(-1, 1));
    }

    #[Test]
    public function getTreeListReturnsEmptyStringForLeafPage(): void
    {
        self::assertSame('', $this->renderer->getTreeList(4, 1));
    }
}

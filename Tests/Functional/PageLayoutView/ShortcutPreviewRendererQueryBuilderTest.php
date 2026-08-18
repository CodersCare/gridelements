<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Functional\PageLayoutView;

use GridElementsTeam\Gridelements\PageLayoutView\ShortcutPreviewRenderer;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Regression test for #102: collectContentDataFromPages() and collectContentData()
 * both build on the DI-injected, shared tt_content QueryBuilder. Calling one after
 * the other on the same renderer instance must not let query state (ORDER BY, FROM)
 * from the first call leak into the second.
 */
class ShortcutPreviewRendererQueryBuilderTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['gridelementsteam/gridelements'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/be_users.csv');
        $this->setUpBackendUser(1);
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content.csv');
    }

    private function invokeProtected(object $object, string $method, array $args): mixed
    {
        $reflectionMethod = new \ReflectionMethod($object, $method);
        $reflectionMethod->setAccessible(true);
        return $reflectionMethod->invokeArgs($object, $args);
    }

    #[Test]
    public function collectContentDataDoesNotInheritOrderByFromPrecedingPagesQuery(): void
    {
        $renderer = $this->get(ShortcutPreviewRenderer::class);

        $collectedItems = [];
        $this->invokeProtected($renderer, 'collectContentDataFromPages', [
            'pages_2', &$collectedItems, 0, 999, 0,
        ]);
        self::assertCount(1, $collectedItems);
        self::assertSame(10, $collectedItems[0]['uid']);

        // Before the fix, this call reused the now-polluted shared QueryBuilder
        // (stale "ORDER BY inSet" from the call above) and threw a DBAL exception.
        $this->invokeProtected($renderer, 'collectContentData', [
            'tt_content_50', &$collectedItems, 999, 0,
        ]);

        self::assertCount(2, $collectedItems);
        self::assertSame(50, $collectedItems[1]['uid']);
    }

    #[Test]
    public function sharedQueryBuilderInstanceIsNeverMutatedByEitherCollector(): void
    {
        $renderer = $this->get(ShortcutPreviewRenderer::class);

        $collectedItems = [];
        $this->invokeProtected($renderer, 'collectContentDataFromPages', [
            'pages_2', &$collectedItems, 0, 999, 0,
        ]);
        $this->invokeProtected($renderer, 'collectContentData', [
            'tt_content_50', &$collectedItems, 999, 0,
        ]);

        $reflectionProperty = new \ReflectionProperty($renderer, 'ttContentQueryBuilder');
        $reflectionProperty->setAccessible(true);
        $sharedQueryBuilder = $reflectionProperty->getValue($renderer);

        // Neither collector may have called select()/from() on the shared instance
        // itself (only on their clones), so it must still be in its pristine,
        // unbuildable state.
        $this->expectException(\Doctrine\DBAL\Query\QueryException::class);
        $sharedQueryBuilder->getSQL();
    }
}

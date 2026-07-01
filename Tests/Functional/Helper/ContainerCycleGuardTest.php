<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Functional\Helper;

use GridElementsTeam\Gridelements\Helper\ContainerCycleGuard;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class ContainerCycleGuardTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['gridelementsteam/gridelements'];

    protected function setUp(): void
    {
        parent::setUp();
        // ContainerCycleGuard uses GridElementsHelper::getQueryBuilder(), which applies
        // a WorkspaceRestriction based on $GLOBALS['BE_USER']->workspace
        $this->importCSVDataSet(__DIR__ . '/Fixtures/be_users.csv');
        $this->setUpBackendUser(1);
    }

    #[Test]
    public function getContainerAncestorChainWalksUpToRoot(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_container_cycle.csv');

        self::assertSame([3, 2, 1], ContainerCycleGuard::getContainerAncestorChain(3));
    }

    #[Test]
    public function getContainerAncestorChainReturnsOnlySelfWhenTopLevel(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_container_cycle.csv');

        self::assertSame([6], ContainerCycleGuard::getContainerAncestorChain(6));
    }

    #[Test]
    public function wouldCreateContainerCycleDetectsIndirectAncestor(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_container_cycle.csv');

        // container 1 is the (grand-)ancestor of container 3, so assigning
        // container 3 as container 1's own container would create a cycle
        self::assertTrue(ContainerCycleGuard::wouldCreateContainerCycle(1, 3));
    }

    #[Test]
    public function wouldCreateContainerCycleReturnsFalseForUnrelatedContainers(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_container_cycle.csv');

        self::assertFalse(ContainerCycleGuard::wouldCreateContainerCycle(6, 3));
    }

    #[Test]
    public function extractShortcutContentReferencesKeepsOnlyTtContentReferences(): void
    {
        self::assertSame(
            [1, 2, 3],
            ContainerCycleGuard::extractShortcutContentReferences('tt_content_1,tt_content_2,pages_5,tt_content_3')
        );
    }

    #[Test]
    public function shortcutReferencesForbiddenContainerDetectsMatch(): void
    {
        self::assertTrue(
            ContainerCycleGuard::shortcutReferencesForbiddenContainer('tt_content_1', [1, 2, 3])
        );
    }

    #[Test]
    public function shortcutReferencesForbiddenContainerReturnsFalseWhenNoMatch(): void
    {
        self::assertFalse(
            ContainerCycleGuard::shortcutReferencesForbiddenContainer('tt_content_99', [1, 2, 3])
        );
    }

    #[Test]
    public function subtreeReferencesForbiddenContainerFindsDangerousShortcutOneLevelDeep(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_container_cycle.csv');

        self::assertTrue(ContainerCycleGuard::subtreeReferencesForbiddenContainer(3, [1]));
    }

    #[Test]
    public function subtreeReferencesForbiddenContainerFindsDangerousShortcutNestedInsideChildContainer(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_container_cycle.csv');

        // container 6 has a safe direct shortcut child (60) plus a nested
        // container (7) whose own shortcut child (8) references the forbidden uid
        self::assertTrue(ContainerCycleGuard::subtreeReferencesForbiddenContainer(6, [1]));
    }

    #[Test]
    public function subtreeReferencesForbiddenContainerReturnsFalseWhenSubtreeIsSafe(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_container_cycle.csv');

        self::assertFalse(ContainerCycleGuard::subtreeReferencesForbiddenContainer(9, [1]));
    }
}

<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Functional\DataHandler;

use GridElementsTeam\Gridelements\DataHandler\AfterDatabaseOperations;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class GetSubPagesRecursivelyTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['gridelementsteam/gridelements'];

    #[Test]
    public function getSubPagesRecursivelyIncludesChildWithoutBackendLayout(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_hierarchy.csv');

        $hook = new AfterDatabaseOperations();
        $hook->setTable('pages');
        $hook->setPageUid(1);
        $hook->setTceMain($this->createStub(DataHandler::class));

        $subPages = [];
        $hook->getSubPagesRecursively(1, $subPages);

        $uids = array_column($subPages, 'uid');
        self::assertContains(2, $uids);
    }

    #[Test]
    public function getSubPagesRecursivelyExcludesChildWithBackendLayout(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_hierarchy.csv');

        $hook = new AfterDatabaseOperations();
        $hook->setTable('pages');
        $hook->setPageUid(1);
        $hook->setTceMain($this->createStub(DataHandler::class));

        $subPages = [];
        $hook->getSubPagesRecursively(1, $subPages);

        $uids = array_column($subPages, 'uid');
        self::assertNotContains(3, $uids);
    }

    #[Test]
    public function getSubPagesRecursivelyDoesNotRecurseIntoPageWithBackendLayoutNextLevel(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_hierarchy.csv');

        $hook = new AfterDatabaseOperations();
        $hook->setTable('pages');
        $hook->setPageUid(1);
        $hook->setTceMain($this->createStub(DataHandler::class));

        $subPages = [];
        $hook->getSubPagesRecursively(1, $subPages);

        $uids = array_column($subPages, 'uid');
        // uid=4 has backend_layout_next_level set → still included (no backend_layout) but NOT recursed
        self::assertContains(4, $uids);
        // uid=5 is grandchild of uid=2 (which has no backend_layout_next_level) → included
        self::assertContains(5, $uids);
    }
}

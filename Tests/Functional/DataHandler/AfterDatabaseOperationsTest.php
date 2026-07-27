<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Functional\DataHandler;

use GridElementsTeam\Gridelements\Backend\LayoutSetup;
use GridElementsTeam\Gridelements\DataHandler\AfterDatabaseOperations;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class AfterDatabaseOperationsTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['gridelementsteam/gridelements'];

    #[Test]
    public function setUnusedElementsMovesChildrenInUnavailableColumnsToColPosMinus2(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_grid_children.csv');

        // LayoutSetup stub: columns 0 and 1 are available; column 2 is not
        $layoutSetup = $this->createStub(LayoutSetup::class);
        $layoutSetup->method('getLayoutColumns')->willReturn(['CSV' => '0,1']);

        $hook = new AfterDatabaseOperations();
        $hook->setTable('tt_content');
        $hook->setContentUid(1);
        $hook->injectLayoutSetup($layoutSetup);
        $hook->setTceMain($this->createStub(DataHandler::class));

        $hook->setUnusedElements(['tx_gridelements_backend_layout' => 'my_layout']);

        // uid=11 was in column 2 (unavailable) → must be moved to colPos=-2
        $records = $this->getAllRecords('tt_content', true);
        self::assertSame(-2, (int)$records[11]['colPos']);
        self::assertSame(-1, (int)$records[11]['backupColPos']);
    }

    #[Test]
    public function setUnusedElementsKeepsChildrenInAvailableColumnsAtColPosMinus1(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_grid_children.csv');

        $layoutSetup = $this->createStub(LayoutSetup::class);
        $layoutSetup->method('getLayoutColumns')->willReturn(['CSV' => '0,1']);

        $hook = new AfterDatabaseOperations();
        $hook->setTable('tt_content');
        $hook->setContentUid(1);
        $hook->injectLayoutSetup($layoutSetup);
        $hook->setTceMain($this->createStub(DataHandler::class));

        $hook->setUnusedElements(['tx_gridelements_backend_layout' => 'my_layout']);

        // uid=10 was in column 0 (available) → must be at colPos=-1
        $records = $this->getAllRecords('tt_content', true);
        self::assertSame(-1, (int)$records[10]['colPos']);
        self::assertSame(-2, (int)$records[10]['backupColPos']);
    }

    #[Test]
    public function doGridContainerUpdateDecrementsChildCount(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_container_with_children.csv');

        $hook = new AfterDatabaseOperations();
        $hook->setTable('tt_content');
        $hook->injectLayoutSetup($this->createStub(LayoutSetup::class));
        $hook->setTceMain($this->createStub(DataHandler::class));

        $hook->doGridContainerUpdate([1 => -1], 'test');

        $records = $this->getAllRecords('tt_content', true);
        self::assertSame(1, (int)$records[1]['tx_gridelements_children']);
    }

    #[Test]
    public function doGridContainerUpdateIncrementsChildCount(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_container_with_children.csv');

        $hook = new AfterDatabaseOperations();
        $hook->setTable('tt_content');
        $hook->injectLayoutSetup($this->createStub(LayoutSetup::class));
        $hook->setTceMain($this->createStub(DataHandler::class));

        $hook->doGridContainerUpdate([1 => 1], 'test');

        $records = $this->getAllRecords('tt_content', true);
        self::assertSame(3, (int)$records[1]['tx_gridelements_children']);
    }

    #[Test]
    public function doGridContainerUpdateNeverGoesBelowZero(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_container_with_children.csv');

        $hook = new AfterDatabaseOperations();
        $hook->setTable('tt_content');
        $hook->injectLayoutSetup($this->createStub(LayoutSetup::class));
        $hook->setTceMain($this->createStub(DataHandler::class));

        // Container uid=2 has tx_gridelements_children=0; decrement must clamp to 0
        $hook->doGridContainerUpdate([2 => -1], 'test');

        $records = $this->getAllRecords('tt_content', true);
        self::assertSame(0, (int)$records[2]['tx_gridelements_children']);
    }

    #[Test]
    public function saveCleanedUpFieldArrayTriggersSetUnusedElementsWhenBackendLayoutKeyPresent(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_grid_children.csv');

        $layoutSetup = $this->createStub(LayoutSetup::class);
        $layoutSetup->method('getLayoutColumns')->willReturn(['CSV' => '0,1']);

        $hook = new AfterDatabaseOperations();
        $hook->setTable('tt_content');
        $hook->setContentUid(1);
        $hook->injectLayoutSetup($layoutSetup);
        $hook->setTceMain($this->createStub(DataHandler::class));

        $hook->saveCleanedUpFieldArray(['tx_gridelements_backend_layout' => 'my_layout']);

        // uid=11 was in tx_gridelements_columns=2 (unavailable) → moved to colPos=-2
        $records = $this->getAllRecords('tt_content', true);
        self::assertSame(-2, (int)$records[11]['colPos']);
    }

    #[Test]
    public function saveCleanedUpFieldArraySkipsSetUnusedElementsWhenNoLayoutKey(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_grid_children.csv');

        $layoutSetup = $this->createStub(LayoutSetup::class);
        $layoutSetup->method('getLayoutColumns')->willReturn(['CSV' => '0,1']);

        $hook = new AfterDatabaseOperations();
        $hook->setTable('tt_content');
        $hook->setContentUid(1);
        $hook->injectLayoutSetup($layoutSetup);
        $hook->setTceMain($this->createStub(DataHandler::class));

        // No tx_gridelements_backend_layout key → setUnusedElements must NOT be called
        $hook->saveCleanedUpFieldArray(['CType' => 'text', 'header' => 'Test']);

        $records = $this->getAllRecords('tt_content', true);
        // uid=11 was in tx_gridelements_columns=2 but colPos stays at original value 0
        self::assertSame(0, (int)$records[11]['colPos']);
    }
}

<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Functional\DataHandler;

use GridElementsTeam\Gridelements\DataHandler\PreProcessFieldArray;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class PreProcessFieldArrayTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['gridelementsteam/gridelements'];

    #[Test]
    public function setFieldEntriesForGridContainersSetsColPosMinus1WhenContainerSet(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_german_container.csv');

        $preprocessor = new PreProcessFieldArray();
        $fieldArray = [
            'tx_gridelements_container' => 1,
            'colPos' => 0,
        ];

        $preprocessor->setFieldEntriesForGridContainers($fieldArray, '');

        self::assertSame(-1, (int)$fieldArray['colPos']);
        self::assertSame(0, (int)$fieldArray['tx_gridelements_columns']);
    }

    #[Test]
    public function setFieldEntriesForGridContainersCopiesContainerLanguageToChild(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_german_container.csv');

        $preprocessor = new PreProcessFieldArray();
        $fieldArray = [
            'tx_gridelements_container' => 1,
            'colPos' => 0,
        ];

        $preprocessor->setFieldEntriesForGridContainers($fieldArray, '');

        // Container uid=1 has sys_language_uid=1 (German) → child inherits it
        self::assertSame(1, (int)$fieldArray['sys_language_uid']);
    }

    // --- setFieldEntries: child-count updates ---

    #[Test]
    public function setFieldEntriesIncrementsNewContainerChildCountForNewElement(): void
    {
        // uid=10 is currently in container 1; we pretend it's a new element moving to container 2
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_container_with_children.csv');

        $hook = new PreProcessFieldArray();
        $hook->setTceMain($this->createStub(DataHandler::class));
        $fieldArray = ['tx_gridelements_container' => 2, 'colPos' => 0];
        $hook->setFieldEntries($fieldArray, '10', true, '');

        $records = $this->getAllRecords('tt_content', true);
        self::assertSame(1, (int)$records[2]['tx_gridelements_children']);
    }

    #[Test]
    public function setFieldEntriesDecrementsSourceContainerChildCountOnMoveOut(): void
    {
        // uid=10 is in container 1 (children=2); moving it away decrements container 1
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_container_with_children.csv');

        $hook = new PreProcessFieldArray();
        $hook->setTceMain($this->createStub(DataHandler::class));
        $fieldArray = ['tx_gridelements_container' => 0, 'colPos' => 3];
        $hook->setFieldEntries($fieldArray, '10', false, 'move');

        $records = $this->getAllRecords('tt_content', true);
        self::assertSame(1, (int)$records[1]['tx_gridelements_children']);
    }

    // --- checkForRootColumn ---

    #[Test]
    public function checkForRootColumnReturnsColPosOfDirectParentContainer(): void
    {
        // uid=30 is inside container uid=3 which sits at page colPos=3
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_root_column.csv');

        $hook = new PreProcessFieldArray();
        self::assertSame(3, $hook->checkForRootColumn(30));
    }

    #[Test]
    public function checkForRootColumnReturnsZeroWhenElementHasNoParent(): void
    {
        // uid=31 is not inside any container → no parent found → default 0
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_root_column.csv');

        $hook = new PreProcessFieldArray();
        self::assertSame(0, $hook->checkForRootColumn(31));
    }
}

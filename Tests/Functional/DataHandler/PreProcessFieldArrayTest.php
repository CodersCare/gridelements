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

    protected function setUp(): void
    {
        parent::setUp();
        // ContainerCycleGuard uses GridElementsHelper::getQueryBuilder(), which applies
        // a WorkspaceRestriction based on $GLOBALS['BE_USER']->workspace; the recursion-guard
        // flash messages also need a real backend-user session to enqueue into
        $this->importCSVDataSet(__DIR__ . '/Fixtures/be_users.csv');
        $this->setUpBackendUser(1);
    }

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

    // --- setFieldEntries: container-recursion guard ---

    #[Test]
    public function setFieldEntriesRejectsAssigningOwnDescendantAsContainer(): void
    {
        // uid=2's tx_gridelements_container chain is 2 -> 1, so assigning
        // container 2 to uid=1 would make uid=1 its own (indirect) container
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_container_cycle_edit.csv');

        $hook = new PreProcessFieldArray();
        $fieldArray = ['tx_gridelements_container' => 2, 'colPos' => 0, 'tx_gridelements_columns' => 0];
        $hook->setFieldEntries($fieldArray, '1', false, '');

        self::assertArrayNotHasKey('tx_gridelements_container', $fieldArray);
        self::assertArrayNotHasKey('colPos', $fieldArray);
        self::assertArrayNotHasKey('tx_gridelements_columns', $fieldArray);
    }

    #[Test]
    public function setFieldEntriesRejectsShortcutRecordsReferencingAncestorContainer(): void
    {
        // uid=11 sits in container 2, whose ancestor chain is [2, 1];
        // pointing its 'records' at uid=1 would create a rendering loop
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_container_cycle_edit.csv');

        $hook = new PreProcessFieldArray();
        $fieldArray = ['records' => 'tt_content_1'];
        $hook->setFieldEntries($fieldArray, '11', false, '');

        self::assertArrayNotHasKey('records', $fieldArray);
    }

    #[Test]
    public function setFieldEntriesRejectsContainerReassignmentWhenExistingRecordsAlreadyDangerous(): void
    {
        // uid=10 is an existing shortcut whose stored 'records' already reference uid=1;
        // only 'tx_gridelements_container' is part of this save, but the guard must still
        // fall back to the DB-stored CType/records to catch the now-dangerous combination
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_container_cycle_edit.csv');

        $hook = new PreProcessFieldArray();
        $fieldArray = ['tx_gridelements_container' => 2];
        $hook->setFieldEntries($fieldArray, '10', false, '');

        self::assertArrayNotHasKey('tx_gridelements_container', $fieldArray);
    }

    // --- extractDefaultDataFromDataStructure ---

    #[Test]
    public function extractDefaultDataFromDataStructureDoesNotFailOnFieldWithoutTypeKey(): void
    {
        // A TCEforms.config without an explicit 'type' key (e.g. a passthrough field) must not
        // trigger an "Undefined array key" warning - under this environment's error handler
        // (warnings escalated to exceptions) that crashed real-world gridelements container
        // creation outright, since config['type'] was accessed unconditionally.
        $dataStructure = '<T3DataStructure>
            <sheets>
                <sDEF>
                    <ROOT>
                        <type>array</type>
                        <el>
                            <settings.myfield>
                                <TCEforms>
                                    <config>
                                        <default>0</default>
                                    </config>
                                </TCEforms>
                            </settings.myfield>
                        </el>
                    </ROOT>
                </sDEF>
            </sheets>
        </T3DataStructure>';

        $hook = new PreProcessFieldArray();
        self::assertIsString($hook->extractDefaultDataFromDataStructure($dataStructure));
    }
}

<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\DataHandler;

use GridElementsTeam\Gridelements\Backend\LayoutSetup;
use GridElementsTeam\Gridelements\DataHandler\AbstractDataHandler;
use GridElementsTeam\Gridelements\DataHandler\PreProcessFieldArray;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class PreProcessFieldArrayTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    #[Test]
    public function injectLayoutSetupStoresInstance(): void
    {
        $hook = GeneralUtility::makeInstance(PreProcessFieldArray::class);
        $layoutSetup = $this->createStub(LayoutSetup::class);
        $hook->injectLayoutSetup($layoutSetup);

        $prop = new \ReflectionProperty(AbstractDataHandler::class, 'layoutSetup');
        self::assertSame($layoutSetup, $prop->getValue($hook));
    }

    #[Test]
    public function initSetsTablePageUidAndTceMain(): void
    {
        $hook = GeneralUtility::makeInstance(PreProcessFieldArray::class);

        $layoutSetupStub = $this->createStub(LayoutSetup::class);
        $layoutSetupStub->method('init')->willReturn($layoutSetupStub);
        GeneralUtility::addInstance(LayoutSetup::class, $layoutSetupStub);

        $dataHandlerStub = $this->createStub(DataHandler::class);
        $hook->init('tt_content', '55', $dataHandlerStub);

        self::assertSame('tt_content', $hook->getTable());
        self::assertSame(55, $hook->getPageUid());
        self::assertSame($dataHandlerStub, $hook->getTceMain());
    }

    #[Test]
    public function getTableRoundTrip(): void
    {
        $hook = GeneralUtility::makeInstance(PreProcessFieldArray::class);
        $hook->setTable('tt_content');
        self::assertEquals('tt_content', $hook->getTable());
    }

    #[Test]
    public function getPageUidRoundTrip(): void
    {
        $hook = GeneralUtility::makeInstance(PreProcessFieldArray::class);
        $hook->setPageUid(123);
        self::assertEquals(123, $hook->getPageUid());
    }

    #[Test]
    public function getTceMainRoundTrip(): void
    {
        $hook = GeneralUtility::makeInstance(PreProcessFieldArray::class);
        $dataHandler = $this->createStub(DataHandler::class);
        $hook->setTceMain($dataHandler);
        self::assertEquals($dataHandler, $hook->getTceMain());
    }

    #[Test]
    public function executePreProcessFieldArrayDoesNotModifyFieldArrayForNonContentTable(): void
    {
        // table != tt_content → whole body skipped, fieldArray must be unchanged
        $hook = GeneralUtility::makeInstance(PreProcessFieldArray::class);
        $fieldArray = ['CType' => 'text', 'colPos' => 0];
        $hook->execute_preProcessFieldArray($fieldArray, 'pages', '1', $this->createStub(DataHandler::class));
        self::assertSame(['CType' => 'text', 'colPos' => 0], $fieldArray);
    }

    #[Test]
    public function processFieldArrayForTtContentDoesNotModifyFieldArrayWithNullRequest(): void
    {
        // In CLI context the constructor leaves $request as null; the method returns early
        $hook = GeneralUtility::makeInstance(PreProcessFieldArray::class);
        $fieldArray = ['CType' => 'text', 'colPos' => 0];
        $hook->processFieldArrayForTtContent($fieldArray);
        self::assertSame(['CType' => 'text', 'colPos' => 0], $fieldArray);
    }

    #[Test]
    public function setDefaultFieldValuesDoesNotModifyFieldArrayWithNullRequest(): void
    {
        // Same null-request early return guard
        $hook = GeneralUtility::makeInstance(PreProcessFieldArray::class);
        $fieldArray = ['CType' => 'text'];
        $hook->setDefaultFieldValues($fieldArray, 5);
        self::assertSame(['CType' => 'text'], $fieldArray);
    }

    #[Test]
    public function extractDefaultDataFromDataStructureReturnsEmptyStringForEmptyInput(): void
    {
        $hook = GeneralUtility::makeInstance(PreProcessFieldArray::class);
        self::assertSame('', $hook->extractDefaultDataFromDataStructure(''));
    }

    #[Test]
    public function setFieldEntriesLeavesFieldArrayUnchangedWhenNoContainerKey(): void
    {
        // no tx_gridelements_container key → all branches skip, fieldArray must be unchanged
        $hook = GeneralUtility::makeInstance(PreProcessFieldArray::class);
        $fieldArray = ['CType' => 'text', 'colPos' => 0];
        $hook->setFieldEntries($fieldArray, '0', false, '');
        self::assertSame(['CType' => 'text', 'colPos' => 0], $fieldArray);
    }

    #[Test]
    public function setFieldEntriesForGridContainersLeavesColPosUnchangedWhenContainerIsZero(): void
    {
        // container=0, colPos!=−1 → no DB branch entered, colPos must not be overridden to −1
        $hook = GeneralUtility::makeInstance(PreProcessFieldArray::class);
        $fieldArray = ['tx_gridelements_container' => 0, 'colPos' => 3];
        $hook->setFieldEntriesForGridContainers($fieldArray, '');
        self::assertSame(3, $fieldArray['colPos']);
    }

    #[Test]
    public function getBackendUserReturnsGlobalBeUser(): void
    {
        $hook = GeneralUtility::makeInstance(PreProcessFieldArray::class);
        $backendUserAuthentication = GeneralUtility::makeInstance(BackendUserAuthentication::class);
        $GLOBALS['BE_USER'] = $backendUserAuthentication;
        self::assertEquals($backendUserAuthentication, $hook->getBackendUser());
    }

    // --- getDefaultFlexformValues ---

    #[Test]
    public function getDefaultFlexformValuesDoesNothingWhenNoTcaDs(): void
    {
        unset($GLOBALS['TCA']['tt_content']['columns']['pi_flexform']['config']['ds']);
        $hook = GeneralUtility::makeInstance(PreProcessFieldArray::class);
        $fieldArray = ['CType' => 'text'];
        $hook->getDefaultFlexformValues($fieldArray);
        self::assertArrayNotHasKey('pi_flexform', $fieldArray);
    }

    #[Test]
    public function getDefaultFlexformValuesDoesNothingWhenNoCTypeMatch(): void
    {
        $GLOBALS['TCA']['tt_content']['columns']['pi_flexform']['config']['ds'] = [
            'mytype,mylist' => '<T3DataStructure/>',
        ];
        $hook = GeneralUtility::makeInstance(PreProcessFieldArray::class);
        $fieldArray = ['CType' => 'text', 'list_type' => ''];
        $hook->getDefaultFlexformValues($fieldArray);
        self::assertArrayNotHasKey('pi_flexform', $fieldArray);
        unset($GLOBALS['TCA']['tt_content']['columns']['pi_flexform']['config']['ds']);
    }
}

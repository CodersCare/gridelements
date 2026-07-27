<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\DataHandler;

use GridElementsTeam\Gridelements\Backend\LayoutSetup;
use GridElementsTeam\Gridelements\DataHandler\AbstractDataHandler;
use GridElementsTeam\Gridelements\DataHandler\AfterDatabaseOperations;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class AfterDatabaseOperationsTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    #[Test]
    public function injectLayoutSetupStoresInstance(): void
    {
        $hook = GeneralUtility::makeInstance(AfterDatabaseOperations::class);
        $layoutSetup = $this->createStub(LayoutSetup::class);
        $hook->injectLayoutSetup($layoutSetup);

        $prop = new \ReflectionProperty(AbstractDataHandler::class, 'layoutSetup');
        self::assertSame($layoutSetup, $prop->getValue($hook));
    }

    #[Test]
    public function initSetsTablePageUidAndTceMain(): void
    {
        $hook = GeneralUtility::makeInstance(AfterDatabaseOperations::class);

        $layoutSetupStub = $this->createStub(LayoutSetup::class);
        $layoutSetupStub->method('init')->willReturn($layoutSetupStub);
        GeneralUtility::addInstance(LayoutSetup::class, $layoutSetupStub);

        $dataHandlerStub = $this->createStub(DataHandler::class);
        $hook->init('tt_content', '99', $dataHandlerStub);

        self::assertSame('tt_content', $hook->getTable());
        self::assertSame(99, $hook->getPageUid());
        self::assertSame($dataHandlerStub, $hook->getTceMain());
    }

    #[Test]
    public function getTableRoundTrip(): void
    {
        $hook = GeneralUtility::makeInstance(AfterDatabaseOperations::class);
        $hook->setTable('tt_content');
        self::assertEquals('tt_content', $hook->getTable());
    }

    #[Test]
    public function getPageUidRoundTrip(): void
    {
        $hook = GeneralUtility::makeInstance(AfterDatabaseOperations::class);
        $hook->setPageUid(123);
        self::assertEquals(123, $hook->getPageUid());
    }

    #[Test]
    public function getTceMainRoundTrip(): void
    {
        $hook = GeneralUtility::makeInstance(AfterDatabaseOperations::class);
        $dataHandler = $this->createStub(DataHandler::class);
        $hook->setTceMain($dataHandler);
        self::assertEquals($dataHandler, $hook->getTceMain());
    }

    #[Test]
    public function getAvailableColumnsReturnsEmptyStringForEmptyArgs(): void
    {
        $hook = GeneralUtility::makeInstance(AfterDatabaseOperations::class);
        // empty layout and empty table → both branches skip → returns ''
        self::assertSame('', $hook->getAvailableColumns('', '', 0));
    }

}

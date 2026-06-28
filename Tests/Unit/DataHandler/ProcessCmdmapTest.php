<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\DataHandler;

use GridElementsTeam\Gridelements\Backend\LayoutSetup;
use GridElementsTeam\Gridelements\DataHandler\AbstractDataHandler;
use GridElementsTeam\Gridelements\DataHandler\ProcessCmdmap;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class ProcessCmdmapTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private function makeHook(): ProcessCmdmap
    {
        return new ProcessCmdmap(null);
    }

    #[Test]
    public function extendsAbstractDataHandler(): void
    {
        self::assertInstanceOf(AbstractDataHandler::class, $this->makeHook());
    }

    #[Test]
    public function getTableRoundTrip(): void
    {
        $hook = $this->makeHook();
        $hook->setTable('tt_content');
        self::assertSame('tt_content', $hook->getTable());
    }

    #[Test]
    public function getPageUidRoundTrip(): void
    {
        $hook = $this->makeHook();
        $hook->setPageUid(42);
        self::assertSame(42, $hook->getPageUid());
    }

    #[Test]
    public function getTceMainRoundTrip(): void
    {
        $hook = $this->makeHook();
        $stub = $this->createStub(DataHandler::class);
        $hook->setTceMain($stub);
        self::assertSame($stub, $hook->getTceMain());
    }

    #[Test]
    public function injectLayoutSetupStoresInstance(): void
    {
        $hook = $this->makeHook();
        $layoutSetup = $this->createStub(LayoutSetup::class);
        $hook->injectLayoutSetup($layoutSetup);

        $prop = new \ReflectionProperty(AbstractDataHandler::class, 'layoutSetup');
        self::assertSame($layoutSetup, $prop->getValue($hook));
    }

    #[Test]
    public function initSetsTablePageUidAndDataHandler(): void
    {
        $hook = $this->makeHook();

        $layoutSetupStub = $this->createStub(LayoutSetup::class);
        $layoutSetupStub->method('init')->willReturn($layoutSetupStub);
        GeneralUtility::addInstance(LayoutSetup::class, $layoutSetupStub);

        $dataHandlerStub = $this->createStub(DataHandler::class);
        $hook->init('tt_content', '7', $dataHandlerStub);

        self::assertSame('tt_content', $hook->getTable());
        self::assertSame(7, $hook->getPageUid());
        self::assertSame($dataHandlerStub, $hook->getTceMain());
    }

}

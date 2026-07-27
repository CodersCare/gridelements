<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Functional\DataHandler;

use GridElementsTeam\Gridelements\DataHandler\ProcessCmdmap;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class ProcessCmdmapTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['gridelementsteam/gridelements'];

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    private function makeHook(): ProcessCmdmap
    {
        return new ProcessCmdmap(new ServerRequest('/'));
    }

    #[Test]
    public function deleteCommandDecrementsContainerChildCount(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_container_with_children.csv');

        $commandIsProcessed = false;
        $this->makeHook()->execute_processCmdmap(
            'delete',
            'tt_content',
            10,
            0,
            $commandIsProcessed,
            $this->createStub(DataHandler::class)
        );

        $records = $this->getAllRecords('tt_content', true);
        self::assertSame(1, (int)$records[1]['tx_gridelements_children']);
    }

    #[Test]
    public function deleteCommandWithNoContainerLeavesContainerUnchanged(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_container_with_children.csv');

        $commandIsProcessed = false;
        $this->makeHook()->execute_processCmdmap(
            'delete',
            'tt_content',
            11,
            0,
            $commandIsProcessed,
            $this->createStub(DataHandler::class)
        );

        $records = $this->getAllRecords('tt_content', true);
        self::assertSame(2, (int)$records[1]['tx_gridelements_children']);
    }

    #[Test]
    public function moveCommandDecrementsOriginalContainerChildCount(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_container_with_children.csv');

        $commandIsProcessed = false;
        $this->makeHook()->execute_processCmdmap(
            'move',
            'tt_content',
            10,
            0,
            $commandIsProcessed,
            $this->createStub(DataHandler::class)
        );

        $records = $this->getAllRecords('tt_content', true);
        self::assertSame(1, (int)$records[1]['tx_gridelements_children']);
    }

    #[Test]
    public function childCountNeverGoesBelowZero(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content_container_with_children.csv');

        $commandIsProcessed = false;
        $this->makeHook()->execute_processCmdmap(
            'delete',
            'tt_content',
            20,
            0,
            $commandIsProcessed,
            $this->createStub(DataHandler::class)
        );

        $records = $this->getAllRecords('tt_content', true);
        self::assertSame(0, (int)$records[2]['tx_gridelements_children']);
    }
}

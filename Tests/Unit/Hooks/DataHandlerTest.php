<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\Hooks;

use GridElementsTeam\Gridelements\Hooks\DataHandler;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class DataHandlerTest extends UnitTestCase
{
    private DataHandler $hook;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hook = new DataHandler();
    }

    #[Test]
    public function isDisallowedReturnsFalseWhenAllowedIsEmpty(): void
    {
        self::assertFalse($this->hook->isDisallowedContentElement([], [], 'text'));
    }

    #[Test]
    public function isDisallowedReturnsFalseWhenAllowedHasWildcard(): void
    {
        self::assertFalse($this->hook->isDisallowedContentElement(['*' => 0], [], 'text'));
    }

    #[Test]
    public function isDisallowedReturnsFalseWhenCTypeIsExplicitlyAllowed(): void
    {
        self::assertFalse($this->hook->isDisallowedContentElement(['text' => 0, 'image' => 0], [], 'text'));
    }

    #[Test]
    public function isDisallowedReturnsTrueWhenCTypeNotInAllowedList(): void
    {
        self::assertTrue($this->hook->isDisallowedContentElement(['text' => 0, 'image' => 0], [], 'gridelements_pi1'));
    }

    #[Test]
    public function isDisallowedReturnsTrueWhenDisallowedHasWildcard(): void
    {
        self::assertTrue($this->hook->isDisallowedContentElement([], ['*' => 0], 'text'));
    }

    #[Test]
    public function isDisallowedReturnsTrueWhenCTypeIsExplicitlyDisallowed(): void
    {
        self::assertTrue($this->hook->isDisallowedContentElement([], ['gridelements_pi1' => 0], 'gridelements_pi1'));
    }

    #[Test]
    public function isDisallowedReturnsFalseWhenCTypeNotInDisallowedList(): void
    {
        self::assertFalse($this->hook->isDisallowedContentElement([], ['gridelements_pi1' => 0], 'text'));
    }

    #[Test]
    public function isDisallowedAllowedCheckTakesPrecedenceOverEmptyDisallowed(): void
    {
        // allowed is non-empty and has wildcard — disallowed is empty — must pass
        self::assertFalse($this->hook->isDisallowedContentElement(['*' => 0], [], 'anything'));
    }

    #[Test]
    public function isDisallowedDisallowedWildcardBlocksEvenAllowedCType(): void
    {
        // CType is in allowed AND disallowed has wildcard — disallowed wins
        self::assertTrue($this->hook->isDisallowedContentElement(['text' => 0], ['*' => 0], 'text'));
    }

    // --- processDatamap_preProcessFieldArray guard ---

    #[Test]
    public function processDatamapPreProcessFieldArraySkipsForNonContentTable(): void
    {
        $fieldArray = ['some_field' => 'value'];
        $original = $fieldArray;
        $parentObj = $this->createStub(\TYPO3\CMS\Core\DataHandling\DataHandler::class);
        $this->hook->processDatamap_preProcessFieldArray($fieldArray, 'be_users', '1', $parentObj);
        self::assertSame($original, $fieldArray);
    }

    #[Test]
    public function processDatamapPreProcessFieldArraySkipsWhenImporting(): void
    {
        $fieldArray = ['CType' => 'text'];
        $original = $fieldArray;
        $parentObj = $this->createStub(\TYPO3\CMS\Core\DataHandling\DataHandler::class);
        $parentObj->isImporting = true;
        $this->hook->processDatamap_preProcessFieldArray($fieldArray, 'tt_content', '1', $parentObj);
        self::assertSame($original, $fieldArray);
    }

    // --- processDatamap_afterDatabaseOperations guard ---

    #[Test]
    public function processDatamapAfterDatabaseOperationsSkipsForNonContentTable(): void
    {
        $fieldArray = ['CType' => 'text'];
        $original = $fieldArray;
        $status = 'new';
        $table = 'be_users';
        $id = '1';
        $parentObj = $this->createStub(\TYPO3\CMS\Core\DataHandling\DataHandler::class);
        $this->hook->processDatamap_afterDatabaseOperations($status, $table, $id, $fieldArray, $parentObj);
        self::assertSame($original, $fieldArray);
    }

    // --- processCmdmap guard ---

    #[Test]
    public function processCmdmapSkipsExecutionWhenImporting(): void
    {
        $commandIsProcessed = false;
        $parentObj = $this->createStub(\TYPO3\CMS\Core\DataHandling\DataHandler::class);
        $parentObj->isImporting = true;
        $this->hook->processCmdmap('delete', 'tt_content', 1, 0, $commandIsProcessed, $parentObj, false);
        self::assertFalse($commandIsProcessed);
    }

    // --- resolveRestrictedFields ---

    private function callResolveRestrictedFields(array $layout, int $column): array
    {
        $method = new \ReflectionMethod(DataHandler::class, 'resolveRestrictedFields');
        $method->setAccessible(true);

        return $method->invoke($this->hook, $layout, $column);
    }

    #[Test]
    public function resolveRestrictedFieldsIncludesTheThreeBaseFieldsWithNoConfig(): void
    {
        self::assertSame(
            ['CType', 'list_type', 'tx_gridelements_backend_layout'],
            $this->callResolveRestrictedFields([], 300)
        );
    }

    #[Test]
    public function resolveRestrictedFieldsAddsCustomFieldsConfiguredOnTheColumn(): void
    {
        $layout = [
            'allowed' => [300 => ['my_custom_field' => ['foo' => 0]]],
            'disallowed' => [300 => ['another_field' => ['bar' => 0]]],
        ];

        self::assertSame(
            ['CType', 'list_type', 'tx_gridelements_backend_layout', 'my_custom_field', 'another_field'],
            $this->callResolveRestrictedFields($layout, 300)
        );
    }

    #[Test]
    public function resolveRestrictedFieldsIgnoresConfigForOtherColumns(): void
    {
        $layout = [
            'allowed' => [301 => ['my_custom_field' => ['foo' => 0]]],
        ];

        self::assertSame(
            ['CType', 'list_type', 'tx_gridelements_backend_layout'],
            $this->callResolveRestrictedFields($layout, 300)
        );
    }

    // --- processCmdmap_beforeStart early returns ---

    #[Test]
    public function processCmdmapBeforeStartReturnsEarlyForEmptyTtContentCmdmap(): void
    {
        $dataHandler = $this->createStub(\TYPO3\CMS\Core\DataHandling\DataHandler::class);
        $dataHandler->cmdmap = [];
        $dataHandler->bypassAccessCheckForRecords = false;
        // No exception → early return branch hit
        $this->hook->processCmdmap_beforeStart($dataHandler);
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function processCmdmapBeforeStartReturnsEarlyWhenBypassingAccessCheck(): void
    {
        $dataHandler = $this->createStub(\TYPO3\CMS\Core\DataHandling\DataHandler::class);
        $dataHandler->cmdmap = ['tt_content' => [1 => ['copy' => 5]]];
        $dataHandler->bypassAccessCheckForRecords = true;
        // No exception → bypass branch hit
        $this->hook->processCmdmap_beforeStart($dataHandler);
        $this->addToAssertionCount(1);
    }
}

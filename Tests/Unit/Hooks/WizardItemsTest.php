<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\Hooks;

use GridElementsTeam\Gridelements\EventListener\ModifyNewContentElementWizardItemsListener;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class WizardItemsTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private function makeListener(): ModifyNewContentElementWizardItemsListener
    {
        return (new \ReflectionClass(ModifyNewContentElementWizardItemsListener::class))
            ->newInstanceWithoutConstructor();
    }

    // --- removeDisallowedWizardItems ---

    #[Test]
    public function removeDisallowedWizardItemsDoesNothingWhenAllowedAndDisallowedEmpty(): void
    {
        $wizardItems = [
            'grp' => ['header' => 'Content'],
            'text' => ['defaultValues' => ['CType' => 'text']],
        ];
        $this->makeListener()->removeDisallowedWizardItems([], [], $wizardItems);
        self::assertCount(2, $wizardItems);
    }

    #[Test]
    public function removeDisallowedWizardItemsRemovesItemNotInAllowedCType(): void
    {
        $wizardItems = [
            'grp' => ['header' => 'Content'],
            'text' => ['defaultValues' => ['CType' => 'text']],
            'image' => ['defaultValues' => ['CType' => 'image']],
        ];
        $this->makeListener()->removeDisallowedWizardItems(['CType' => ['text' => 0]], [], $wizardItems);
        self::assertArrayHasKey('text', $wizardItems);
        self::assertArrayNotHasKey('image', $wizardItems);
    }

    #[Test]
    public function removeDisallowedWizardItemsRemovesTrailingHeaderWhenGroupIsEmpty(): void
    {
        // Only content item is 'image' — allowed restricts to 'text' only, so image gets removed.
        // The orphaned 'grp' header should then be removed as a trailing header.
        $wizardItems = [
            'grp' => ['header' => 'Content'],
            'image' => ['defaultValues' => ['CType' => 'image']],
        ];
        $this->makeListener()->removeDisallowedWizardItems(['CType' => ['text' => 0]], [], $wizardItems);
        self::assertEmpty($wizardItems);
    }

    #[Test]
    public function removeDisallowedWizardItemsKeepsWildcardAllowedItems(): void
    {
        $wizardItems = [
            'grp' => ['header' => 'Content'],
            'text' => ['defaultValues' => ['CType' => 'text']],
            'image' => ['defaultValues' => ['CType' => 'image']],
        ];
        $this->makeListener()->removeDisallowedWizardItems(['CType' => ['*' => 0]], [], $wizardItems);
        self::assertArrayHasKey('text', $wizardItems);
        self::assertArrayHasKey('image', $wizardItems);
    }

    // --- addGridValuesToWizardItems ---

    #[Test]
    public function addGridValuesToWizardItemsAlwaysSetsColumn(): void
    {
        $wizardItems = ['text' => ['defaultValues' => ['CType' => 'text']]];
        $this->makeListener()->addGridValuesToWizardItems($wizardItems, 0, 3);
        self::assertSame(3, $wizardItems['text']['defaultValues']['tx_gridelements_columns']);
    }

    #[Test]
    public function addGridValuesToWizardItemsDoesNotSetContainerWhenZero(): void
    {
        $wizardItems = ['text' => ['defaultValues' => ['CType' => 'text']]];
        $this->makeListener()->addGridValuesToWizardItems($wizardItems, 0, 0);
        self::assertArrayNotHasKey('tx_gridelements_container', $wizardItems['text']['defaultValues']);
    }

    #[Test]
    public function addGridValuesToWizardItemsSetsContainerWhenNonZero(): void
    {
        $wizardItems = ['text' => ['defaultValues' => ['CType' => 'text']]];
        $this->makeListener()->addGridValuesToWizardItems($wizardItems, 5, 3);
        self::assertSame(5, $wizardItems['text']['defaultValues']['tx_gridelements_container']);
        self::assertSame(3, $wizardItems['text']['defaultValues']['tx_gridelements_columns']);
    }

    #[Test]
    public function addGridValuesToWizardItemsClearsBodytextForTableCType(): void
    {
        $wizardItems = ['table' => ['defaultValues' => ['CType' => 'table', 'bodytext' => 'existing']]];
        $this->makeListener()->addGridValuesToWizardItems($wizardItems, 0, 0);
        self::assertSame('', $wizardItems['table']['defaultValues']['bodytext']);
    }

    // --- addGridItemsToWizard ---

    #[Test]
    public function addGridItemsToWizardDoesNothingForEmptyGridItems(): void
    {
        $wizardItems = ['text' => ['defaultValues' => ['CType' => 'text']]];
        $before = $wizardItems;
        $this->makeListener()->addGridItemsToWizard([], $wizardItems);
        self::assertSame($before, $wizardItems);
    }

}

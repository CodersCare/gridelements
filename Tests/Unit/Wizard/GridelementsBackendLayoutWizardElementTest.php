<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\Wizard;

use GridElementsTeam\Gridelements\Wizard\GridelementsBackendLayoutWizardElement;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Form\Element\BackendLayoutWizardElement;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class GridelementsBackendLayoutWizardElementTest extends UnitTestCase
{
    #[Test]
    public function extendsTypo3BackendLayoutWizardElement(): void
    {
        self::assertTrue(
            is_subclass_of(GridelementsBackendLayoutWizardElement::class, BackendLayoutWizardElement::class)
        );
    }

    #[Test]
    public function usesIconSizeSmallConstantNotEnum(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(GridelementsBackendLayoutWizardElement::class))->getFileName()
        );
        self::assertStringContainsString('Icon::SIZE_SMALL', $source);
        self::assertStringNotContainsString('IconSize::SMALL', $source);
    }

    #[Test]
    public function iconSizeSmallConstantValueIsCorrect(): void
    {
        self::assertSame('small', Icon::SIZE_SMALL);
    }

    #[Test]
    public function defaultRowCountIsZero(): void
    {
        $element = (new \ReflectionClass(GridelementsBackendLayoutWizardElement::class))
            ->newInstanceWithoutConstructor();
        $prop = new \ReflectionProperty(GridelementsBackendLayoutWizardElement::class, 'rowCount');
        self::assertSame(0, $prop->getValue($element));
    }

    #[Test]
    public function defaultColCountIsZero(): void
    {
        $element = (new \ReflectionClass(GridelementsBackendLayoutWizardElement::class))
            ->newInstanceWithoutConstructor();
        $prop = new \ReflectionProperty(GridelementsBackendLayoutWizardElement::class, 'colCount');
        self::assertSame(0, $prop->getValue($element));
    }

    #[Test]
    public function defaultRowsIsEmptyArray(): void
    {
        $element = (new \ReflectionClass(GridelementsBackendLayoutWizardElement::class))
            ->newInstanceWithoutConstructor();
        $prop = new \ReflectionProperty(GridelementsBackendLayoutWizardElement::class, 'rows');
        self::assertSame([], $prop->getValue($element));
    }
}

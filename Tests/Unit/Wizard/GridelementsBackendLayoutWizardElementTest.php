<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\Wizard;

use GridElementsTeam\Gridelements\Wizard\GridelementsBackendLayoutWizardElement;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Form\Element\BackendLayoutWizardElement;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Covers GridelementsBackendLayoutWizardElement, the TYPO3 12+ Xclass of BackendLayoutWizardElement.
 * TYPO3 11 uses GridelementsBackendLayoutWizardElement11 instead, covered by
 * Tests/Unit/Wizard/GridelementsBackendLayoutWizardElement11Test.php.
 */
class GridelementsBackendLayoutWizardElementTest extends UnitTestCase
{
    private function skipUnlessTypo3TwelvePlus(): void
    {
        if ((new Typo3Version())->getMajorVersion() < 12) {
            self::markTestSkipped(
                'GridelementsBackendLayoutWizardElement is only Xclassed in on TYPO3 12+; TYPO3 11 uses '
                . 'GridelementsBackendLayoutWizardElement11 instead, covered by '
                . 'Tests/Unit/Wizard/GridelementsBackendLayoutWizardElement11Test.php.'
            );
        }
    }

    #[Test]
    public function extendsTypo3BackendLayoutWizardElement(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

        self::assertTrue(
            is_subclass_of(GridelementsBackendLayoutWizardElement::class, BackendLayoutWizardElement::class)
        );
    }

    #[Test]
    public function usesIconSizeSmallConstantNotEnum(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

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
        $this->skipUnlessTypo3TwelvePlus();

        $element = (new \ReflectionClass(GridelementsBackendLayoutWizardElement::class))
            ->newInstanceWithoutConstructor();
        $prop = new \ReflectionProperty(GridelementsBackendLayoutWizardElement::class, 'rowCount');
        self::assertSame(0, $prop->getValue($element));
    }

    #[Test]
    public function defaultColCountIsZero(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

        $element = (new \ReflectionClass(GridelementsBackendLayoutWizardElement::class))
            ->newInstanceWithoutConstructor();
        $prop = new \ReflectionProperty(GridelementsBackendLayoutWizardElement::class, 'colCount');
        self::assertSame(0, $prop->getValue($element));
    }

    #[Test]
    public function defaultRowsIsEmptyArray(): void
    {
        $this->skipUnlessTypo3TwelvePlus();

        $element = (new \ReflectionClass(GridelementsBackendLayoutWizardElement::class))
            ->newInstanceWithoutConstructor();
        $prop = new \ReflectionProperty(GridelementsBackendLayoutWizardElement::class, 'rows');
        self::assertSame([], $prop->getValue($element));
    }
}

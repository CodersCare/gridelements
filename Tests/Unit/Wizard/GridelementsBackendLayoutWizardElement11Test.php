<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\Wizard;

use GridElementsTeam\Gridelements\Wizard\GridelementsBackendLayoutWizardElement11;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Form\Element\BackendLayoutWizardElement;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Covers GridelementsBackendLayoutWizardElement11, the TYPO3 11 Xclass of BackendLayoutWizardElement.
 * See Tests/Unit/Wizard/GridelementsBackendLayoutWizardElementTest.php for the TYPO3 12+ counterpart.
 * No counterpart to usesIconSizeSmallConstantNotEnum here: this class never references Icon::SIZE_SMALL.
 */
class GridelementsBackendLayoutWizardElement11Test extends UnitTestCase
{
    private function skipUnlessTypo3Eleven(): void
    {
        if ((new Typo3Version())->getMajorVersion() >= 12) {
            self::markTestSkipped(
                'GridelementsBackendLayoutWizardElement11 is only Xclassed in on TYPO3 11; TYPO3 12+ uses '
                . 'GridelementsBackendLayoutWizardElement instead, covered by '
                . 'Tests/Unit/Wizard/GridelementsBackendLayoutWizardElementTest.php.'
            );
        }
    }

    #[Test]
    public function extendsTypo3BackendLayoutWizardElement(): void
    {
        $this->skipUnlessTypo3Eleven();

        self::assertTrue(
            is_subclass_of(GridelementsBackendLayoutWizardElement11::class, BackendLayoutWizardElement::class)
        );
    }

    #[Test]
    public function defaultRowCountIsZero(): void
    {
        $this->skipUnlessTypo3Eleven();

        $element = (new \ReflectionClass(GridelementsBackendLayoutWizardElement11::class))
            ->newInstanceWithoutConstructor();
        $prop = new \ReflectionProperty(GridelementsBackendLayoutWizardElement11::class, 'rowCount');
        self::assertSame(0, $prop->getValue($element));
    }

    #[Test]
    public function defaultColCountIsZero(): void
    {
        $this->skipUnlessTypo3Eleven();

        $element = (new \ReflectionClass(GridelementsBackendLayoutWizardElement11::class))
            ->newInstanceWithoutConstructor();
        $prop = new \ReflectionProperty(GridelementsBackendLayoutWizardElement11::class, 'colCount');
        self::assertSame(0, $prop->getValue($element));
    }

    #[Test]
    public function defaultRowsIsEmptyArray(): void
    {
        $this->skipUnlessTypo3Eleven();

        $element = (new \ReflectionClass(GridelementsBackendLayoutWizardElement11::class))
            ->newInstanceWithoutConstructor();
        $prop = new \ReflectionProperty(GridelementsBackendLayoutWizardElement11::class, 'rows');
        self::assertSame([], $prop->getValue($element));
    }
}

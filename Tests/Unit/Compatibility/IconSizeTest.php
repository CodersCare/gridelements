<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\Compatibility;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class IconSizeTest extends UnitTestCase
{
    #[Test]
    public function iconSizeSmallConstantHasExpectedStringValue(): void
    {
        self::assertSame('small', Icon::SIZE_SMALL);
    }

    #[Test]
    public function gridelementUsesIconSizeSmallConstantNotEnum(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/Classes/Backend/LocalizationController.php'
        );
        self::assertStringContainsString('Icon::SIZE_SMALL', $source);
        self::assertStringNotContainsString('IconSize::SMALL', $source);
    }

    #[Test]
    public function wizardElementUsesIconSizeSmallConstantNotEnum(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/Classes/Wizard/GridelementsBackendLayoutWizardElement.php'
        );
        self::assertStringContainsString('Icon::SIZE_SMALL', $source);
        self::assertStringNotContainsString('IconSize::SMALL', $source);
    }
}

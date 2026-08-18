<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\Helper;

use GridElementsTeam\Gridelements\Helper\RestrictionGuard;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class RestrictionGuardTest extends UnitTestCase
{
    #[Test]
    public function isValueAllowedReturnsTrueWhenAllowedAndDisallowedAreEmpty(): void
    {
        self::assertTrue(RestrictionGuard::isValueAllowed([], [], 'text'));
    }

    #[Test]
    public function isValueAllowedReturnsTrueWhenAllowedHasWildcard(): void
    {
        self::assertTrue(RestrictionGuard::isValueAllowed(['*' => 0], [], 'text'));
    }

    #[Test]
    public function isValueAllowedReturnsTrueWhenValueIsExplicitlyAllowed(): void
    {
        self::assertTrue(RestrictionGuard::isValueAllowed(['text' => 0, 'image' => 0], [], 'text'));
    }

    #[Test]
    public function isValueAllowedReturnsFalseWhenValueNotInAllowedList(): void
    {
        self::assertFalse(RestrictionGuard::isValueAllowed(['text' => 0, 'image' => 0], [], 'gridelements_pi1'));
    }

    #[Test]
    public function isValueAllowedReturnsFalseWhenDisallowedHasWildcard(): void
    {
        self::assertFalse(RestrictionGuard::isValueAllowed([], ['*' => 0], 'text'));
    }

    #[Test]
    public function isValueAllowedReturnsFalseWhenValueIsExplicitlyDisallowed(): void
    {
        self::assertFalse(RestrictionGuard::isValueAllowed([], ['gridelements_pi1' => 0], 'gridelements_pi1'));
    }

    #[Test]
    public function isValueAllowedReturnsTrueWhenValueNotInDisallowedList(): void
    {
        self::assertTrue(RestrictionGuard::isValueAllowed([], ['gridelements_pi1' => 0], 'text'));
    }

    #[Test]
    public function isValueAllowedDisallowedWildcardBlocksEvenAllowedValue(): void
    {
        self::assertFalse(RestrictionGuard::isValueAllowed(['text' => 0], ['*' => 0], 'text'));
    }

    #[Test]
    public function isValueDisallowedIsTheInverseOfIsValueAllowed(): void
    {
        self::assertTrue(RestrictionGuard::isValueDisallowed(['text' => 0], [], 'image'));
        self::assertFalse(RestrictionGuard::isValueDisallowed(['text' => 0], [], 'text'));
    }

    #[Test]
    public function isMaxItemsExceededReturnsFalseWhenMaxItemsIsNull(): void
    {
        self::assertFalse(RestrictionGuard::isMaxItemsExceeded(null, 100));
    }

    #[Test]
    public function isMaxItemsExceededReturnsFalseWhenMaxItemsIsZeroOrNegative(): void
    {
        self::assertFalse(RestrictionGuard::isMaxItemsExceeded(0, 100));
        self::assertFalse(RestrictionGuard::isMaxItemsExceeded(-1, 100));
    }

    #[Test]
    public function isMaxItemsExceededReturnsFalseWhenBelowLimit(): void
    {
        self::assertFalse(RestrictionGuard::isMaxItemsExceeded(2, 1));
    }

    #[Test]
    public function isMaxItemsExceededReturnsTrueAtLimit(): void
    {
        self::assertTrue(RestrictionGuard::isMaxItemsExceeded(2, 2));
    }

    #[Test]
    public function isMaxItemsExceededReturnsTrueAboveLimit(): void
    {
        self::assertTrue(RestrictionGuard::isMaxItemsExceeded(2, 3));
    }
}

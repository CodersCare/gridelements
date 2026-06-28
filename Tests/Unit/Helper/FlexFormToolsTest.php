<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\Helper;

use GridElementsTeam\Gridelements\Helper\FlexFormTools;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class FlexFormToolsTest extends UnitTestCase
{
    private FlexFormTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new FlexFormTools();
    }

    #[Test]
    public function getFlexFormValueReturnsEmptyStringForEmptyArray(): void
    {
        self::assertSame('', $this->tools->getFlexFormValue([], 'someField'));
    }

    #[Test]
    public function getFlexFormValueReturnsEmptyStringForMissingFieldRatherThanNull(): void
    {
        // getFlexFormValueFromSheetArray returns null for a missing key; getFlexFormValue must
        // coalesce to '' to satisfy its array|string return type declaration.
        $data = ['data' => ['sDEF' => ['lDEF' => ['otherField' => ['vDEF' => 'x']]]]];
        self::assertSame('', $this->tools->getFlexFormValue($data, 'missingField'));
    }

    #[Test]
    public function getFlexFormValueReturnsSimpleFieldValue(): void
    {
        $data = ['data' => ['sDEF' => ['lDEF' => ['myField' => ['vDEF' => 'hello']]]]];
        self::assertSame('hello', $this->tools->getFlexFormValue($data, 'myField'));
    }

    #[Test]
    public function getFlexFormValueReturnsNestedFieldValue(): void
    {
        $data = ['data' => ['sDEF' => ['lDEF' => ['parent' => ['child' => ['vDEF' => 'deep']]]]]];
        self::assertSame('deep', $this->tools->getFlexFormValue($data, 'parent/child'));
    }

    #[Test]
    public function getFlexFormValueUsesCustomSheet(): void
    {
        $data = ['data' => ['sCustom' => ['lDEF' => ['field' => ['vDEF' => 'custom']]]]];
        self::assertSame('custom', $this->tools->getFlexFormValue($data, 'field', 'sCustom'));
    }

    #[Test]
    public function getFlexFormValueUsesCustomLanguageKey(): void
    {
        $data = ['data' => ['sDEF' => ['lDE' => ['field' => ['vDE' => 'german']]]]];
        self::assertSame('german', $this->tools->getFlexFormValue($data, 'field', 'sDEF', 'lDE', 'vDE'));
    }

    #[Test]
    public function getFlexFormValueFromSheetArrayReturnsValueByStringKey(): void
    {
        $sheetArray = ['myField' => ['vDEF' => 'val']];
        $result = $this->tools->getFlexFormValueFromSheetArray($sheetArray, ['myField'], 'vDEF');
        self::assertSame('val', $result);
    }

    #[Test]
    public function getFlexFormValueFromSheetArrayTraversesNestedStringKeys(): void
    {
        $sheetArray = ['parent' => ['child' => ['vDEF' => 'nested']]];
        $result = $this->tools->getFlexFormValueFromSheetArray($sheetArray, ['parent', 'child'], 'vDEF');
        self::assertSame('nested', $result);
    }

    #[Test]
    public function getFlexFormValueFromSheetArrayTraversesIntegerKeyByPosition(): void
    {
        $sheetArray = ['first' => 'a', 'second' => 'b'];
        $result = $this->tools->getFlexFormValueFromSheetArray($sheetArray, ['1'], 'vDEF');
        self::assertSame('b', $result);
    }

    #[Test]
    public function getFlexformSectionsRecursivelyExtractsValuesWithDefaultKey(): void
    {
        $dataArr = [
            'field1' => ['vDEF' => 'value1'],
            'field2' => ['vDEF' => 'value2'],
        ];
        $result = $this->tools->getFlexformSectionsRecursively($dataArr);
        self::assertSame(['field1' => 'value1', 'field2' => 'value2'], $result);
    }

    #[Test]
    public function getFlexformSectionsRecursivelyUsesCustomValueKey(): void
    {
        $dataArr = [
            'field1' => ['vDE' => 'german'],
        ];
        $result = $this->tools->getFlexformSectionsRecursively($dataArr, 'vDE');
        self::assertSame(['field1' => 'german'], $result);
    }

    #[Test]
    public function getFlexformSectionsRecursivelyRecursesIntoElKey(): void
    {
        $dataArr = [
            'section' => [
                'el' => [
                    'subField' => ['vDEF' => 'sub'],
                ],
            ],
        ];
        $result = $this->tools->getFlexformSectionsRecursively($dataArr);
        self::assertArrayHasKey('section', $result);
    }
}

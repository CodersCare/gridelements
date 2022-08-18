<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Helper;

/***************************************************************
 *  Copyright notice
 *  (c) 2011 Jo Hasenau <info@cybercraft.de>
 *  All rights reserved
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * FlexForm tools for the 'gridelements' extension.
 * @author Jo Hasenau <info@cybercraft.de>
 */
class FlexFormTools
{
    /**
     * Return value from somewhere inside a FlexForm structure
     *
     * @param array $T3FlexForm_array FlexForm data
     * @param string $fieldName Field name to extract. Can be given like "test/el/2/test/el/field_templateObject" where each part will dig a level deeper in the FlexForm data.
     * @param string $sheet Sheet pointer, eg. "sDEF"
     * @param string $language Language pointer, eg. "lDEF"
     * @param string $value Value pointer, eg. "vDEF"
     * @return string The content.
     */
    public function getFlexFormValue(
        array $T3FlexForm_array,
        string $fieldName,
        string $sheet = 'sDEF',
        string $language = 'lDEF',
        string $value = 'vDEF'
    ) {
        $sheetArray = is_array($T3FlexForm_array) ? $T3FlexForm_array['data'][$sheet][$language] : '';
        if (is_array($sheetArray)) {
            return $this->getFlexFormValueFromSheetArray($sheetArray, explode('/', $fieldName), $value);
        }
        return '';
    }

    /**
     * Returns part of $sheetArray pointed to by the keys in $fieldNameArray
     *
     * @param array $sheetArray Multidimensional array, typically FlexForm contents
     * @param array $fieldNameArr Array where each value points to a key in the FlexForms content - the input array will have the value returned pointed to by these keys. All integer keys will not take their integer counterparts, but rather traverse the current position in the array an return element number X (whether this is right behavior is not settled yet...)
     * @param string $value Value for outermost key, typ. "vDEF" depending on language.
     * @return mixed The value, typ. string.
     * @see pi_getFlexFormValue()
     */
    public function getFlexFormValueFromSheetArray(array $sheetArray, array $fieldNameArr, string $value)
    {
        $tempArr = $sheetArray;
        foreach ($fieldNameArr as $k => $v) {
            $checkedValue = MathUtility::canBeInterpretedAsInteger($v);
            if ($checkedValue) {
                if (is_array($tempArr)) {
                    $c = 0;
                    foreach ($tempArr as $values) {
                        if ($c == $v) {
                            $tempArr = $values;
                            break;
                        }
                        $c++;
                    }
                }
            } else {
                $tempArr = $tempArr[$v];
            }
        }
        if (is_array($tempArr)) {
            if (isset($tempArr['el']) && is_array($tempArr['el'])) {
                $out = $this->getFlexformSectionsRecursively($tempArr['el'], $value);
            } else {
                $out = $tempArr[$value];
            }
        } else {
            $out = $tempArr;
        }

        return $out;
    }

    /**
     * @param array $dataArr
     * @param string $valueKey
     * @return array
     */
    public function getFlexformSectionsRecursively(array $dataArr, string $valueKey = 'vDEF'): array
    {
        $out = [];
        foreach ($dataArr as $k => $el) {
            if (is_array($el) && isset($el['el']) && is_array($el['el'])) {
                $out[$k] = $this->getFlexformSectionsRecursively($el['el']);
            } elseif (is_array($el) && isset($el['data']) && isset($el['data']['el']) && is_array($el['data']['el'])) {
                $out[] = $this->getFlexformSectionsRecursively($el['data']['el']);
            } elseif (isset($el[$valueKey])) {
                $out[$k] = $el[$valueKey];
            }
        }

        return $out;
    }
}

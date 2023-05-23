<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Wizard;

/***************************************************************
 *  Copyright notice
 *  (c) 2013 Jo Hasenau <info@cybercraft.de>
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

use GridElementsTeam\Gridelements\Backend\LayoutSetup;
use TYPO3\CMS\Backend\Form\Element\BackendLayoutWizardElement;
use TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Script Class for grid wizard
 */
class GridelementsBackendLayoutWizardElement extends BackendLayoutWizardElement
{
    /**
     * @var array
     */
    protected $rows = [];

    /**
     * @var int
     */
    protected $colCount = 0;

    /**
     * @var int
     */
    protected $rowCount = 0;

    /**
     * @return array
     */
    public function render(): array
    {
        $lang = $this->getLanguageService();
        $resultArray = $this->initializeResultArray();
        $this->init();

        // readOnly is not supported as columns config but might be set by SingleFieldContainer in case
        // "l10n_display" is set to "defaultAsReadonly". To prevent misbehaviour for fields, which falsely
        // set this, we also check for "defaultAsReadonly" being set and whether the record is an overlay.
        $readOnly = ($parameterArray['fieldConf']['config']['readOnly'] ?? false)
            && ($tca['ctrl']['transOrigPointerField'] ?? false)
            && ($row[$tca['ctrl']['transOrigPointerField']][0] ?? $row[$tca['ctrl']['transOrigPointerField']] ?? false)
            && GeneralUtility::inList($parameterArray['fieldConf']['l10n_display'] ?? '', 'defaultAsReadonly');

        $fieldInformationResult = $this->renderFieldInformation();
        $fieldInformationHtml = $fieldInformationResult['html'];
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldInformationResult, false);

        $fieldWizardResult = $this->renderFieldWizard();
        $fieldWizardHtml = $fieldWizardResult['html'];
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldWizardResult, false);

        $json = json_encode($this->rows, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        $html = [];
        $html[] = '<div class="formengine-field-item t3js-formengine-field-item">';
        $html[] = $fieldInformationHtml;
        $html[] = '<div class="form-control-wrap">';
        $html[] = '<div class="form-wizards-wrap">';
        $html[] = '<div class="form-wizards-element">';
        $html[] = '<input';
        $html[] = ' type="hidden"';
        $html[] = ' name="' . htmlspecialchars($this->data['parameterArray']['itemFormElName']) . '"';
        $html[] = ' value="' . htmlspecialchars($this->data['parameterArray']['itemFormElValue']) . '"';
        $html[] = '/>';
        $html[] = '<table class="grideditor table table-bordered">';
        $html[] = '<tr>';
        $html[] = '<td class="editor_cell">';
        $html[] = '<div';
        $html[] = ' id="editor"';
        $html[] = ' class="t3js-grideditor"';
        $html[] = ' data-data="' . htmlspecialchars($json) . '"';
        $html[] = ' data-rowcount="' . $this->rowCount . '"';
        $html[] = ' data-colcount="' . $this->colCount . '"';
        $html[] = ' data-field="' . htmlspecialchars($this->data['parameterArray']['itemFormElName']) . '"';
        $html[] = '>';
        $html[] = '</div>';
        $html[] = '</td>';
        $html[] = '<td>';
        $html[] = '<div class="btn-group-vertical">';
        $html[] = '<a class="btn btn-default btn-sm t3js-grideditor-addcolumn" href="#"';
        $html[] = ' title="' . htmlspecialchars($lang->getLL('grid_addColumn')) . '">';
        $html[] = '<i class="fa fa-fw fa-arrow-right"></i>';
        $html[] = '</a>';
        $html[] = '<a class="btn btn-default btn-sm t3js-grideditor-removecolumn" href="#"';
        $html[] = ' title="' . htmlspecialchars($lang->getLL('grid_removeColumn')) . '">';
        $html[] = '<i class="fa fa-fw fa-arrow-left"></i>';
        $html[] = '</a>';
        $html[] = '</div>';
        $html[] = '</td>';
        $html[] = '</tr>';
        $html[] = '<tr>';
        $html[] = '<td colspan="2" align="center">';
        $html[] = '<div class="btn-group">';
        $html[] = '<a class="btn btn-default btn-sm t3js-grideditor-addrow" href="#"';
        $html[] = ' title="' . htmlspecialchars($lang->getLL('grid_addRow')) . '">';
        $html[] = '<i class="fa fa-fw fa-arrow-down"></i>';
        $html[] = '</a>';
        $html[] = '<a class="btn btn-default btn-sm t3js-grideditor-removerow" href="#"';
        $html[] = ' title="' . htmlspecialchars($lang->getLL('grid_removeRow')) . '">';
        $html[] = '<i class="fa fa-fw fa-arrow-up"></i>';
        $html[] = '</a>';
        $html[] = '</div>';
        $html[] = '</td>';
        $html[] = '</tr>';
        $html[] = '<tr>';
        $html[] = '<td colspan="2">';
        $html[] = '<a href="#" class="btn btn-default btn-sm t3js-grideditor-preview-button">' . htmlspecialchars($lang->getLL('buttons.pageTsConfig')) . '</a>';
        $html[] = '<pre class="t3js-grideditor-preview-config grideditor-preview"><code></code></pre>';
        $html[] = '</td>';
        $html[] = '</tr>';
        $html[] = '</table>';
        $html[] = '</div>';
        $html[] = '<div class="form-wizards-items-bottom">';
        $html[] = $fieldWizardHtml;
        $html[] = '</div>';
        $html[] = '</div>';
        $html[] = '</div>';
        $html[] = '</div>';

        $contentTypes = [];
        if (is_array($GLOBALS['TCA']['tt_content']['columns']['CType']['config']['items'])) {
            foreach ($GLOBALS['TCA']['tt_content']['columns']['CType']['config']['items'] as $item) {
                $contentType = [];
                if (!empty($item[1])) {
                    $contentType['key'] = $item[1];
                    if (substr((string)$contentType['key'], 0, 2) !== '--') {
                        $contentType['label'] = $lang->sL($item[0]);
                        if (!empty($item[2])) {
                            if (strpos($item[2], 'EXT:') === 0) {
                                $contentType['icon'] = GeneralUtility::getFileAbsFileName($item[2]);
                            } elseif (strpos($item[2], '/typo3') === 0) {
                                $contentType['icon'] = '../../../' . $item[2];
                            } else {
                                $contentType['icon'] = '../../../' . '../typo3/sysext/core/Resources/Public/Icons/T3Icons/content/' . $item[2];
                            }
                        }
                        // Check if file ending exists, therefore compare pos of last slash to pos of last dot
                        if (!empty($contentType['icon']) && strrpos($contentType['icon'], '/') > strrpos($contentType['icon'], '.')) {
                            $contentType['icon'] .= '.svg';
                        }
                        $contentTypes[] = $contentType;
                    }
                }
            }
        }
        $listTypes = [];
        if (is_array($GLOBALS['TCA']['tt_content']['columns']['list_type']['config']['items'])) {
            foreach ($GLOBALS['TCA']['tt_content']['columns']['list_type']['config']['items'] as $item) {
                $listType = [];
                if (!empty($item[1])) {
                    $listType['key'] = $item[1];
                    if (substr((string)$listType['key'], 0, 2) !== '--') {
                        $listType['label'] = $lang->sL($item[0]);
                        if (strpos($item[2], 'EXT:') === 0) {
                            $listType['icon'] = GeneralUtility::getFileAbsFileName($item[2]);
                        } elseif (strpos($item[2], '/typo3') === 0) {
                            $listType['icon'] = '../../../' . $item[2];
                        } else {
                            $listType['icon'] = '../../../' . '../typo3/sysext/core/Resources/Public/Icons/T3Icons/content/' . $item[2];
                        }
                        // Check if file ending exists, therefore compare pos of last slash to pos of last dot
                        if (!empty($listType['icon']) && strrpos($listType['icon'], '/') > strrpos(
                            $listType['icon'],
                            '.'
                        )) {
                            $listType['icon'] .= '.svg';
                        }
                        $listTypes[] = $listType;
                    }
                }
            }
        }
        $gridTypes = [];
        $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class)->init($this->data['parentPageRow']['pid'] ?? 0)->getLayoutSetup();
        if (is_array($layoutSetup)) {
            foreach ($layoutSetup as $key => $item) {
                $gridType = [];
                if (!empty($key)) {
                    $gridType['key'] = $key;
                    if (substr((string)$gridType['key'], 0, 2) !== '--') {
                        $gridType['label'] = $lang->sL($item['title']);
                        if (!empty($item['icon']) && is_array($item['icon']) && !empty($item['icon'][0])) {
                            if (strpos($item['icon'][0], 'EXT:') === 0) {
                                $gridType['icon'] = GeneralUtility::getFileAbsFileName($item['icon'][0]);
                            } elseif (strpos($item['icon'][0], '/typo3') === 0) {
                                $gridType['icon'] = '../../../' . $item['icon'][0];
                            } else {
                                $gridType['icon'] = '../../../' . '../typo3/sysext/core/Resources/Public/Icons/T3Icons/content/' . $item['icon'][0];
                            }
                            // Check if file ending exists, therefore compare pos of last slash to pos of last dot
                            if (!empty($gridType['icon']) && strrpos($gridType['icon'], '/') > strrpos(
                                $gridType['icon'],
                                '.'
                            )) {
                                $gridType['icon'] .= '.svg';
                            }
                        }
                        $gridTypes[] = $gridType;
                    }
                }
            }
        }
        $html[] = '<script type="text/javascript">/*<![CDATA[*/ ' .
            ($contentTypes ? ' TYPO3.settings.availableCTypes = ' . json_encode($contentTypes) . '; ' : '') .
            ($listTypes ? ' TYPO3.settings.availableListTypes = ' . json_encode($listTypes) . '; ' : '') .
            ($gridTypes ? ' TYPO3.settings.availableGridTypes = ' . json_encode($gridTypes) . '; ' : '') .
            '/*]]>*/</script>';

        $html = implode(LF, $html);
        $resultArray['html'] = $html;
        $resultArray['additionalInlineLanguageLabelFiles'][] = 'EXT:gridelements/Resources/Private/Language/locallang_wizard.xlf';
        $resultArray['additionalInlineLanguageLabelFiles'][] = 'EXT:core/Resources/Private/Language/locallang_wizards.xlf';
        $resultArray['additionalInlineLanguageLabelFiles'][] = 'EXT:backend/Resources/Private/Language/locallang.xlf';
        $resultArray['requireJsModules'][] = 'TYPO3/CMS/Gridelements/GridEditor';

        return $resultArray;
    }

    /**
     * Initialize wizard
     */
    protected function init()
    {
        if (empty($this->data['databaseRow']['config'])) {
            $rows = [[['colspan' => 1, 'rowspan' => 1, 'spanned' => 0, 'name' => '0x0']]];
            $colCount = 1;
            $rowCount = 1;
        } else {
            /** @var LayoutSetup $layoutSetup */
            $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class)->init(0);
            if ($this->data['tableName'] === 'tx-gridelements_backend_layout') {
                $layoutId = $this->data['databaseRow']['alias'] ?? (int)$this->data['databaseRow']['uid'];
                $layout = $layoutSetup->getLayoutSetup($layoutId);
            } else {
                $parser = GeneralUtility::makeInstance(TypoScriptParser::class);
                $parser->parse($this->data['databaseRow']['config']);
                $layout = ['config' => $parser->setup['backend_layout.']];
                if (!empty($layout['config']['rows.'])) {
                    $columns = $layoutSetup->checkAvailableColumns($layout);
                    if (!empty($columns['allowed']) || !empty($columns['disallowed']) || !empty($columns['maxitems'])) {
                        $layout['columns'] = $columns;
                        unset($layout['columns']['allowed']);
                        $layout['allowed'] = $columns['allowed'] ?? [];
                        $layout['disallowed'] = $columns['disallowed'] ?? [];
                        $layout['maxitems'] = $columns['maxitems'] ?? [];
                    }
                }
            }
            $rows = [];
            $colCount = $layout['config']['colCount'] ?? 0;
            $rowCount = $layout['config']['rowCount'] ?? 0;
            $dataRows = $layout['config']['rows.'] ?? [];
            $spannedMatrix = [];
            for ($i = 1; $i <= $rowCount; $i++) {
                $cells = [];
                $row = array_shift($dataRows);
                $columns = $row['columns.'];
                for ($j = 1; $j <= $colCount; $j++) {
                    $cellData = [];
                    if (empty($spannedMatrix[$i][$j])) {
                        if (!empty($columns) && is_array($columns)) {
                            $column = array_shift($columns);
                            if (isset($column['colspan'])) {
                                $cellData['colspan'] = (int)$column['colspan'];
                                $columnColSpan = (int)$column['colspan'];
                                if (isset($column['rowspan'])) {
                                    $columnRowSpan = (int)$column['rowspan'];
                                    for ($spanRow = 0; $spanRow < $columnRowSpan; $spanRow++) {
                                        for ($spanColumn = 0; $spanColumn < $columnColSpan; $spanColumn++) {
                                            if (!isset($spannedMatrix[$i + $spanRow])) {
                                                $spannedMatrix[$i + $spanRow] = [];
                                            }
                                            $spannedMatrix[$i + $spanRow][$j + $spanColumn] = 1;
                                        }
                                    }
                                } else {
                                    for ($spanColumn = 0; $spanColumn < $columnColSpan; $spanColumn++) {
                                        if (!isset($spannedMatrix[$i])) {
                                            $spannedMatrix[$i] = [];
                                        }
                                        $spannedMatrix[$i][$j + $spanColumn] = 1;
                                    }
                                }
                            } else {
                                $cellData['colspan'] = 1;
                                if (isset($column['rowspan'])) {
                                    $columnRowSpan = (int)$column['rowspan'];
                                    for ($spanRow = 0; $spanRow < $columnRowSpan; $spanRow++) {
                                        if (!isset($spannedMatrix[$i + $spanRow])) {
                                            $spannedMatrix[$i + $spanRow] = [];
                                        }
                                        $spannedMatrix[$i + $spanRow][$j] = 1;
                                    }
                                }
                            }
                            if (isset($column['rowspan'])) {
                                $cellData['rowspan'] = (int)$column['rowspan'];
                            } else {
                                $cellData['rowspan'] = 1;
                            }
                            if (isset($column['name'])) {
                                $cellData['name'] = $column['name'];
                            }
                            if (isset($column['colPos'])) {
                                $colPos = (int)$column['colPos'];
                                $cellData['column'] = $colPos;
                                $cellData['allowed'] = [];
                                if (isset($layout['allowed'][$colPos])) {
                                    foreach ($layout['allowed'][$colPos] as $key => $valueArray) {
                                        $cellData['allowed'][$key] = implode(',', array_keys($valueArray));
                                    }
                                }
                                $cellData['disallowed'] = [];
                                if (isset($layout['disallowed'][$colPos])) {
                                    foreach ($layout['disallowed'][$colPos] as $key => $valueArray) {
                                        $cellData['disallowed'][$key] = implode(',', array_keys($valueArray));
                                    }
                                }
                                if (isset($layout['maxitems'][$colPos])) {
                                    $cellData['maxitems'] = (int)$layout['maxitems'][$colPos];
                                }
                            }
                        }
                    } else {
                        $cellData = ['colspan' => 1, 'rowspan' => 1, 'spanned' => 1];
                    }
                    $cells[] = $cellData;
                }
                $rows[] = $cells;
                if (!empty($spannedMatrix[$i]) && is_array($spannedMatrix[$i])) {
                    ksort($spannedMatrix[$i]);
                }
            }
        }
        $this->rows = $rows;
        $this->colCount = (int)$colCount;
        $this->rowCount = (int)$rowCount;
    }
}

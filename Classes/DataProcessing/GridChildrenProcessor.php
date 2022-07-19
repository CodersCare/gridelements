<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\DataProcessing;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use GridElementsTeam\Gridelements\Backend\LayoutSetup;
use GridElementsTeam\Gridelements\Helper\FlexFormTools;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentDataProcessor;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * Fetch records from the database, using the default .select syntax from TypoScript.
 */
class GridChildrenProcessor implements DataProcessorInterface
{
    /**
     * @var LayoutSetup
     */
    protected $layoutSetup;

    /**
     * @var ContentDataProcessor
     */
    protected $contentDataProcessor;

    /**
     * @var FlexFormTools
     */
    protected $flexFormTools;

    /**
     * @var array
     */
    protected array $contentObjectConfiguration;

    /**
     * @var array
     */
    protected array $processorConfiguration;

    /**
     * @var array
     */
    protected array $containerProcessorConfiguration;

    /**
     * @var array
     */
    protected array $processedData;

    /**
     * @var array
     */
    protected array $processedRecordVariables = [];

    /**
     * @var array
     */
    protected array $options = [];

    /**
     * @var array
     */
    protected array $registeredOptions = [
        'sortingDirection' => 'asc',
        'sortingDirection.' => [],
        'sortingField' => 'sorting',
        'sortingField.' => [],
        'recursive' => 0,
        'recursive.' => [],
        'resolveFlexFormData' => 1,
        'resolveFlexFormData.' => [],
        'resolveChildFlexFormData' => 1,
        'resolveChildFlexFormData.' => [],
        'resolveBackendLayout' => 1,
        'resolveBackendLayout.' => [],
        'respectColumns' => 1,
        'respectColumns.' => [],
        'respectRows' => 1,
        'respectRows.' => [],
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->flexFormTools = GeneralUtility::makeInstance(FlexFormTools::class);
        $this->layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class);
        $this->contentDataProcessor = GeneralUtility::makeInstance(ContentDataProcessor::class);
    }

    /**
     * Fetches records from the database as an array
     *
     * @param ContentObjectRenderer $cObj The data of the content element or page
     * @param array $contentObjectConfiguration The configuration of Content Object
     * @param array $processorConfiguration The configuration of this processor
     * @param array $processedData Key/value store of processed data (e.g. to be passed to a Fluid View)
     *
     * @return array the processed data as key/value store
     */
    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData
    ): array {
        if (
            empty($processedData['data']['CType']) ||
            $processedData['data']['CType'] !== 'gridelements_pi1' ||
            empty($processorConfiguration)
        ) {
            return $processedData;
        }

        $this->containerProcessorConfiguration = $processorConfiguration[$processedData['data']['tx_gridelements_backend_layout'] . '.'] ?? [];
        if (empty($this->containerProcessorConfiguration)) {
            $this->containerProcessorConfiguration = $processorConfiguration['default.'] ?? [];
            if (empty($this->containerProcessorConfiguration)) {
                return $processedData;
            }
        }

        $this->contentObjectConfiguration = $contentObjectConfiguration;
        $this->processorConfiguration = $processorConfiguration;
        unset($processorConfiguration);
        $this->processedData = $processedData;
        unset($processedData);

        $targetVariableName = $cObj->stdWrapValue('as', $this->containerProcessorConfiguration, 'children');
        $options = $this->containerProcessorConfiguration['options.'] ?? [];
        foreach ($options as $key => &$option) {
            $option = $cObj->stdWrapValue($key, $options, $option);
        }
        if (isset($options['resolveFlexFormData']) && !isset($options['resolveChildFlexFormData'])) {
            if ((int)$options['resolveFlexFormData'] === 0) {
                $options['resolveChildFlexFormData'] = 0;
            }
        }
        $this->options = array_merge(
            $this->registeredOptions,
            array_intersect_key($options, $this->registeredOptions)
        );
        unset($options);

        $this->checkOptions($this->processedData['data']);
        if (isset($this->processorConfiguration['recursive'])) {
            $this->options['recursive'] = $this->processorConfiguration['recursive'];
        }

        $queryConfiguration = [
            'pidInList' => (int)$cObj->data['pid'],
            'languageField' => 0,
            'orderBy' => (
                !empty($this->options['sortingField']) ? htmlspecialchars($this->options['sortingField']) : 'sorting'
            ) . ' ' . (
                strtolower($this->options['sortingDirection']) === 'desc' ? 'DESC' : 'ASC'
            ),
            'where' => 'tx_gridelements_container = ' . (int)$cObj->data['uid'],
        ];
        $records = $cObj->getRecords('tt_content', $queryConfiguration);
        foreach ($records as $record) {
            $this->processChildRecord($record);
        }

        if (
            !empty($this->options['respectColumns']) ||
            !empty($this->options['respectRows'])
        ) {
            $this->processedData[$targetVariableName] = $this->sortRecordsIntoMatrix();
        } else {
            $this->processedData[$targetVariableName] = $this->processedRecordVariables;
        }
        $this->processedRecordVariables = [];

        foreach ($this->options as $key => $value) {
            unset($this->options[$key . '.']);
        }
        $this->processedData['options'] = $this->options;
        unset($this->options);

        return $this->processedData;
    }

    /**
     * @param array $record
     * @param bool $isChild
     */
    protected function checkOptions(array &$record, bool $isChild = false)
    {
        if (
            (
                !empty($this->options['resolveBackendLayout']) ||
                !empty($this->options['respectColumns']) ||
                !empty($this->options['respectRows'])
            ) && !$this->layoutSetup->getRealPid()
        ) {
            $this->layoutSetup->init((int)$record['pid'], $this->contentObjectConfiguration);
        }

        if (
            (
                !$isChild && !empty($this->options['resolveFlexFormData'])
                || $isChild && !empty($this->options['resolveChildFlexFormData'])
            ) && !empty($record['pi_flexform'])
        ) {
            $this->initPluginFlexForm($record);
            $this->getPluginFlexFormData($record);
        }
        if (!empty($this->options['resolveBackendLayout'])) {
            $backendLayout = $record['tx_gridelements_backend_layout'] ?? '';
            if (!empty($this->layoutSetup->getLayoutSetup($backendLayout))) {
                $record['tx_gridelements_backend_layout_resolved'] = $this->layoutSetup->getLayoutSetup($backendLayout);
            } elseif (!empty($this->layoutSetup->getLayoutSetup('default'))) {
                $record['tx_gridelements_backend_layout_resolved'] = $this->layoutSetup->getLayoutSetup('default');
            }
        }
    }

    /**
     * Converts $this->cObj->data['pi_flexform'] from XML string to flexForm array.
     * @param array $record
     * @param string $field Field name to convert
     */
    public function initPluginFlexForm(array &$record, string $field = 'pi_flexform')
    {
        // Converting flexform data into array:
        if (!empty($record)) {
            if (!empty($record[$field]) && !is_array($record[$field])) {
                $record[$field . '_content'] = GeneralUtility::makeInstance(FlexFormService::class)->convertFlexFormContentToArray($record[$field]);
                if (!is_array($record[$field . '_content'])) {
                    $record[$field . '_content'] = [];
                }
                $record[$field] = GeneralUtility::xml2array($record[$field]);
                if (!is_array($record[$field])) {
                    $record[$field] = [];
                }
            }
        }
    }

    /**
     * fetches values from the grid flexform and assigns them to virtual fields in the data array
     * @param array $record
     */
    public function getPluginFlexFormData(array &$record)
    {
        if (!empty($record)) {
            $pluginFlexForm = $record['pi_flexform'] ?? [];

            if (is_array($pluginFlexForm) && !empty($pluginFlexForm['data']) && is_array($pluginFlexForm['data'])) {
                foreach ($pluginFlexForm['data'] as $sheetName => $sheet) {
                    if (is_array($sheet)) {
                        foreach ($sheet as $value) {
                            if (is_array($value)) {
                                foreach ($value as $key => $val) {
                                    $record['flexform_' . $key] = $this->flexFormTools->getFlexFormValue(
                                        $pluginFlexForm,
                                        $key,
                                        $sheetName
                                    );
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Processes child records recursively to get other children into the same array
     *
     * @param array $record
     */
    protected function processChildRecord(array $record)
    {
        $id = (int)$record['uid'];
        $this->checkOptions($record, true);
        /* @var ContentObjectRenderer $recordContentObjectRenderer */
        $recordContentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $recordContentObjectRenderer->start($record, 'tt_content');
        $this->processedRecordVariables[$id] = ['data' => $record];
        if (
            !empty($this->options['recursive']) &&
            $record['CType'] === 'gridelements_pi1' &&
            !empty($record['tx_gridelements_backend_layout'])
        ) {
            $childProcessorConfiguration = $this->containerProcessorConfiguration;
            if (!isset($childProcessorConfiguration['dataProcessing.'])) {
                $childProcessorConfiguration['dataProcessing.'] = [];
            }
            $childProcessorConfiguration['dataProcessing.']['0.'] = $this->processorConfiguration;
            $childProcessorConfiguration['dataProcessing.']['0.']['recursive'] = (int)$this->options['recursive'] - 1;
            $childProcessorConfiguration['dataProcessing.']['0'] = 'GridElementsTeam\Gridelements\DataProcessing\GridChildrenProcessor';
            $this->processedRecordVariables[$id] = $this->contentDataProcessor->process(
                $recordContentObjectRenderer,
                $childProcessorConfiguration,
                $this->processedRecordVariables[$id]
            );
        } else {
            $this->processedRecordVariables[$id] = $this->contentDataProcessor->process(
                $recordContentObjectRenderer,
                $this->containerProcessorConfiguration,
                $this->processedRecordVariables[$id]
            );
        }
    }

    /**
     * @return array
     */
    protected function sortRecordsIntoMatrix(): array
    {
        $processedColumns = [];
        foreach ($this->processedRecordVariables as $key => $processedRecord) {
            if (isset($processedRecord['data']['tx_gridelements_columns']) && !isset($processedColumns[$processedRecord['data']['tx_gridelements_columns']])) {
                $processedColumns[$processedRecord['data']['tx_gridelements_columns']] = [];
            }
            $processedColumns[$processedRecord['data']['tx_gridelements_columns']][$key] = $processedRecord;
        }
        if (!empty($this->options['respectRows'])) {
            $this->options['respectColumns'] = 1;
            $processedRows = [];
            if (!empty($this->processedData['data']['tx_gridelements_backend_layout_resolved'])) {
                if (!empty($this->processedData['data']['tx_gridelements_backend_layout_resolved']['config']['rows.'])) {
                    foreach ($this->processedData['data']['tx_gridelements_backend_layout_resolved']['config']['rows.'] as $rowNumber => $row) {
                        if (!empty($row['columns.'])) {
                            foreach ($row['columns.'] as $column) {
                                $key = substr($rowNumber, 0, -1);
                                if (!isset($processedRows[$key])) {
                                    $processedRows[$key] = [];
                                }
                                $processedRows[$key][$column['colPos']] = $processedColumns[$column['colPos']] ?? [];
                            }
                        }
                    }
                }
            }
            return $processedRows;
        }
        return $processedColumns;
    }
}

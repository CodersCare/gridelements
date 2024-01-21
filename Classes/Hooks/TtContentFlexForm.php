<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Hooks;

/***************************************************************
 *  Copyright notice
 *  (c) 2014 Jo Hasenau <info@cybercraft.de>, Dirk Hoffmann <hoffmann@vmd-jena.de>, Stephan Schuler <stephan.schuler@netlogix.de>
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
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;

/**
 * Manipulate and find flex forms for gridelements tt_content plugin
 *
 * @author Jo Hasenau <info@cybercraft.de>
 * @author Dirk Hoffmann <hoffmann@vmd-jena.de>
 * @author Stephan Schuler <stephan.schuler@netlogix.de>
 */
class TtContentFlexForm
{
    /**
     * Method to find flex form configuration of a tt_content gridelements
     * content element.
     *
     * @param array $tca
     * @param string $tableName
     * @param string $fieldName
     * @param array $row
     * @return array
     */
    public function getDataStructureIdentifierPreProcess(array $tca, string $tableName, string $fieldName, array $row): array
    {
        if ($tableName === 'tt_content' && $fieldName === 'pi_flexform' && $row['CType'] === 'gridelements_pi1') {
            if (!empty($row['tx_gridelements_backend_layout']) && !empty($row['uid']) && !empty($row['pid'])) {
                if (VersionNumberUtility::convertVersionNumberToInteger(TYPO3_version) < 11000000) {
                    BackendUtility::fixVersioningPid($tableName, $row);
                }
                $pageUid = $row['pid'];
                $layoutId = $row['tx_gridelements_backend_layout'];
                /** @var LayoutSetup $layoutSetupInstance */
                $layoutSetupInstance = GeneralUtility::makeInstance(LayoutSetup::class)->init($pageUid);
                $layoutSetup = $layoutSetupInstance->getLayoutSetup($layoutId);
                if (!empty($layoutSetup['pi_flexform_ds_file'])) {
                    if (MathUtility::canBeInterpretedAsInteger($layoutSetup['pi_flexform_ds_file'])) {
                        // Our data structure is in a record. Re-use core internal syntax to resolve that.
                        // Get path of referenced file
                        $fileRepository = GeneralUtility::makeInstance(FileRepository::class);
                        if (MathUtility::canBeInterpretedAsInteger($layoutId)) {
                            $fileReferences = $fileRepository->findByRelation('tx_gridelements_backend_layout', 'pi_flexform_ds_file', $layoutId);
                        }
                        if (!empty($fileReferences[0])) {
                            $file = $fileReferences[0]->getOriginalFile();
                            $storageBasePath = rtrim($file->getStorage()->getConfiguration()['basePath'], '/');
                            $filePath = $storageBasePath . $file->getProperties()['identifier'];
                            $identifier = [
                                'type' => 'record',
                                'tableName' => 'tx_gridelements_backend_layout',
                                'uid' => $layoutId,
                                'fieldName' => 'pi_flexform_ds_file',
                                'flexformDS' => 'FILE:' . $filePath,
                            ];
                        } else {
                            // This could be an additional core patch that allows referencing a DS file directly.
                            // If so, the second hook below would be obsolete.
                            $identifier = [
                                'type' => 'gridelements-dummy',
                            ];
                        }
                    } else {
                        // Our data structure makes use of a written file path. Re-use core internal syntax to resolve that
                        $identifier = [
                            'type' => 'record',
                            'tableName' => 'tx_gridelements_backend_layout',
                            'uid' => $layoutId,
                            'fieldName' => 'pi_flexform_ds_file',
                            'flexformDS' => 'FILE:' . $layoutSetup['pi_flexform_ds_file'],
                        ];
                    }
                } elseif (!empty($layoutSetup['pi_flexform_ds'])) {
                    $identifier = [
                        'type' => 'record',
                        'tableName' => 'tx_gridelements_backend_layout',
                        'uid' => $layoutId,
                        'fieldName' => 'pi_flexform_ds',
                        'flexformDS' => $layoutSetup['pi_flexform_ds'],
                    ];
                } else {
                    // This could be an additional core patch that allows referencing a DS file directly.
                    // If so, the second hook below would be obsolete.
                    $identifier = [
                        'type' => 'gridelements-dummy',
                    ];
                }
            } else {
                $identifier = [
                    'type' => 'gridelements-dummy',
                ];
            }
        } else {
            // Not my business
            $identifier = [];
        }
        return $identifier;
    }

    /**
     * Deliver a dummy flex form if identifier tells us to do so.
     *
     * @param array $identifier
     * @return string
     */
    public function parseDataStructureByIdentifierPreProcess(array $identifier): string
    {
        if (!empty($identifier['type']) && $identifier['type'] === 'gridelements-dummy') {
            return 'FILE:EXT:gridelements/Configuration/FlexForms/default_flexform_configuration.xml';
        }
        if (!empty($identifier['flexformDS'])) {
            return $identifier['flexformDS'];
        }
        return '';
    }
}

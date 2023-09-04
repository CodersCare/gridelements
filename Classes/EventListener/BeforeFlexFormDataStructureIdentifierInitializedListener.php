<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\EventListener;

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
use TYPO3\CMS\Core\Configuration\Event\BeforeFlexFormDataStructureIdentifierInitializedEvent;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Manipulate and find flex forms for gridelements tt_content plugin
 *
 * @author Jo Hasenau <info@cybercraft.de>
 * @author Dirk Hoffmann <hoffmann@vmd-jena.de>
 * @author Stephan Schuler <stephan.schuler@netlogix.de>
 */
final class BeforeFlexFormDataStructureIdentifierInitializedListener
{
    /**
     * @param BeforeFlexFormDataStructureIdentifierInitializedEvent $event
     */
    public function __invoke(BeforeFlexFormDataStructureIdentifierInitializedEvent $event)
    {
        $row = $event->getRow();
        $tableName = $event->getTableName();
        $fieldName = $event->getFieldName();

        if ($tableName === 'tt_content' && $fieldName === 'pi_flexform' && $row['CType'] === 'gridelements_pi1') {
            if (!empty($row['tx_gridelements_backend_layout'])) {
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

            $event->setIdentifier($identifier);
        }
    }
}

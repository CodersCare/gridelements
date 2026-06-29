<?php

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$l10n = 'LLL:EXT:gridelements/Resources/Private/Language/locallang_db.xlf';

$versionInformation = GeneralUtility::makeInstance(Typo3Version::class);

if ($versionInformation->getMajorVersion() < 12) {
    unset(
        $GLOBALS['TCA']['tx_gridelements_backend_layout']['columns']['l10n_parent']['config']['items'],
        $GLOBALS['TCA']['tx_gridelements_backend_layout']['columns']['hidden']['config']['items'],
    );

    $GLOBALS['TCA']['tx_gridelements_backend_layout'] = array_replace_recursive(
        $GLOBALS['TCA']['tx_gridelements_backend_layout'],
        [
            'ctrl' => [
                'cruser_id' => 'cruser_id'
            ],
            'columns' => [
                'hidden' => [
                    'config' => [
                        'items' => [
                            '1' => [
                                '0' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:hidden.I.0',
                            ],
                        ],
                    ],
                ],
                'title' => [
                    'config' => [
                        'eval' => 'required',
                    ],
                ],
                'horizontal' => [
                    'config' => [
                        'items' => [
                            '1' => [
                                '0' => $l10n . ':tx_gridelements_backend_layout.horizontal.I.0',
                            ],
                        ],
                    ],
                ],
                'icon' => [
                    'config' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::getFileFieldTCAConfig(
                        'icon',
                        [
                            'maxitems' => 1,
                            'appearance' => [
                                'createNewRelationLinkTitle' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:images.addFileReference',
                            ],
                        ],
                        $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']
                    ),
                ],
                'frame' => [
                    'config' => [
                        'items' => [
                            [
                                $l10n . ':tx_gridelements_backend_layout.frame.I.0',
                                '0',
                            ],
                            [
                                $l10n . ':tx_gridelements_backend_layout.frame.I.-1',
                                '-1',
                            ],
                            [
                                $l10n . ':tx_gridelements_backend_layout.frame.I.1',
                                '1',
                            ],
                            [
                                $l10n . ':tx_gridelements_backend_layout.frame.I.2',
                                '2',
                            ],
                            [
                                $l10n . ':tx_gridelements_backend_layout.frame.I.3',
                                '3',
                            ],
                        ],
                    ],
                ],
                'top_level_layout' => [
                    'config' => [
                        'type' => 'check',
                        'items' => [
                            '1' => [
                                '0' => 'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.enabled',
                            ],
                        ],
                    ],
                ],
                'pi_flexform_ds_file' => [
                    'config' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::getFileFieldTCAConfig(
                        'pi_flexform_ds_file',
                        [
                            'maxitems' => 1,
                            'appearance' => [
                                'createNewRelationLinkTitle' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:media.addFileReference',
                            ],
                        ],
                        'xml'
                    ),
                ],
            ],
        ]
    );
}

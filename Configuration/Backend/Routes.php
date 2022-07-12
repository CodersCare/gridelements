<?php

declare(strict_types=1);

use GridElementsTeam\Gridelements\Wizard\GridelementsBackendLayoutWizardElement;
use GridElementsTeam\Gridelements\Wizard\GridelementsBackendLayoutWizardElement10;

/**
 * Definitions for routes provided by EXT:gridelements
 * Contains all "regular" routes for entry points
 * Please note that this setup is preliminary until all core use-cases are set up here.
 * Especially some more properties regarding modules will be added until TYPO3 CMS 7 LTS, and might change.
 * Currently the "access" property is only used so no token creation + validation is made,
 * but will be extended further.
 */
if (\TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger(TYPO3_version) >= 11000000) {
    return [
        // Login screen of the TYPO3 Backend
        /** Wizards */
        // Register backend_layout wizard
        'wizard_gridelements_backend_layout' => [
            'path' => '/wizard',
            'target' => GridelementsBackendLayoutWizardElement::class . '::mainAction',
        ],
    ];
}
return [
    // Login screen of the TYPO3 Backend
    /** Wizards */
    // Register backend_layout wizard
    'wizard_gridelements_backend_layout' => [
        'path' => '/wizard',
        'target' => GridelementsBackendLayoutWizardElement10::class . '::mainAction',
    ],
];

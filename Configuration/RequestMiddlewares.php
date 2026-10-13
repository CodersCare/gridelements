<?php

use TYPO3\CMS\Core\Information\Typo3Version;

if ((new Typo3Version())->getMajorVersion() < 12) {
    return [];
}

return [
    'backend' => [
        'gridelementsteam/gridelements/layout-wizard-icons' => [
            'target' => \GridElementsTeam\Gridelements\Middleware\RegisterLayoutWizardIcon::class,
            'after' => [
                'typo3/cms-backend/authentication',
            ],
        ],
    ],
];

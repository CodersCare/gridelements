<?php

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

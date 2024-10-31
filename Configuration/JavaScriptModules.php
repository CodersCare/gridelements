<?php

return [
    'dependencies' => ['core', 'backend'],
    'imports' => [
        '@gridelementsteam/gridelements/' => 'EXT:gridelements/Resources/Public/JavaScript/',
        '@typo3/backend/layout-module/drag-drop.js' => 'EXT:gridelements/Resources/Public/JavaScript/drag-drop.js',
        '@typo3/backend/layout-module/paste.js' => 'EXT:gridelements/Resources/Public/JavaScript/paste.js',
        '@typo3/backend/gridelements/grid-editor.js' => 'EXT:gridelements/Resources/Public/JavaScript/grid-editor.js'
    ],
];

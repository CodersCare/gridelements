<?php

$dragDropFile = (new \TYPO3\CMS\Core\Information\Typo3Version())->getMajorVersion() >= 13
    ? 'EXT:gridelements/Resources/Public/JavaScript/drag-drop.js'
    : 'EXT:gridelements/Resources/Public/JavaScript/drag-drop-cms12.js';

return [
    'dependencies' => ['core', 'backend'],
    'imports' => [
        '@gridelementsteam/gridelements/' => 'EXT:gridelements/Resources/Public/JavaScript/',
        '@typo3/backend/layout-module/drag-drop.js' => $dragDropFile,
        '@typo3/backend/layout-module/paste.js' => 'EXT:gridelements/Resources/Public/JavaScript/paste.js',
        '@typo3/backend/gridelements/grid-editor.js' => 'EXT:gridelements/Resources/Public/JavaScript/grid-editor.js'
    ],
];

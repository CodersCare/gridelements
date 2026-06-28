<?php

use TYPO3\CMS\Core\Information\Typo3Version;

defined('TYPO3') || die();

if ((new(Typo3Version::class))->getMajorVersion() >= 13) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1488914437] = [
            'nodeName' => 'belayoutwizard',
            'priority' => 50,
            'class' => \GridElementsTeam\Gridelements\Wizard\GridelementsBackendLayoutWizardElement::class,
    ];
} else {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1488914437] = [
            'nodeName' => 'belayoutwizard',
            'priority' => 50,
            'class' => \GridElementsTeam\Gridelements\Wizard\GridelementsBackendLayoutWizardElement12::class,
    ];
}

if (!empty($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['gridelements']['nestingInListModule'])) {
    $xclassName = (new \TYPO3\CMS\Core\Information\Typo3Version())->getMajorVersion() >= 13
        ? \GridElementsTeam\Gridelements\Xclass\DatabaseRecordList::class
        : \GridElementsTeam\Gridelements\Xclass\DatabaseRecordList12::class;
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Backend\RecordList\DatabaseRecordList::class] = ['className' => $xclassName];
}
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript(
    'gridelements',
    'setup',
    '@import \'EXT:gridelements/Configuration/TypoScript/backend.typoscript\''
);

// Add colPos fixer task
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\GridElementsTeam\Gridelements\Task\GridelementsColPosFixer::class] = [
    'extension'        => 'gridelements',
    'title'            => 'LLL:EXT:gridelements/Resources/Private/Language/locallang.xlf:gridelementsColPosFixer.name',
    'description'      => 'LLL:EXT:gridelements/Resources/Private/Language/locallang.xlf:gridelementsColPosFixer.description'
];

// Add number of children fixer task
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\GridElementsTeam\Gridelements\Task\GridelementsNumberOfChildrenFixer::class] = [
    'extension'        => 'gridelements',
    'title'            => 'LLL:EXT:gridelements/Resources/Private/Language/locallang.xlf:gridelementsNumberOfChildrenFixer.name',
    'description'      => 'LLL:EXT:gridelements/Resources/Private/Language/locallang.xlf:gridelementsNumberOfChildrenFixer.description'
];

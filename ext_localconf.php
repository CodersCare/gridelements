<?php

if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

if (\TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger(TYPO3_version) >= 11000000) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1488914437] = [
        'nodeName' => 'belayoutwizard',
        'priority' => 50,
        'class'    => \GridElementsTeam\Gridelements\Wizard\GridelementsBackendLayoutWizardElement::class,
    ];
} else {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1488914437] = [
        'nodeName' => 'belayoutwizard',
        'priority' => 50,
        'class'    => \GridElementsTeam\Gridelements\Wizard\GridelementsBackendLayoutWizardElement10::class,
    ];
}
// XCLASS
if (!empty($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['gridelements']['nestingInListModule'])) {
    if (\TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger(TYPO3_version) >= 11000000) {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Recordlist\RecordList\DatabaseRecordList::class] = ['className' => \GridElementsTeam\Gridelements\Xclass\DatabaseRecordList::class];
    } else {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Recordlist\RecordList\DatabaseRecordList::class] = ['className' => \GridElementsTeam\Gridelements\Xclass\DatabaseRecordList10::class];
    }
}

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addUserTSConfig('
	options.saveDocNew.tx_gridelements_backend_layout=1
');

if (empty($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['gridelements']['fluidBasedPageModule']) && \TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger(TYPO3_version) < 11000000) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['fluidBasedPageModule'] = false;
}

if (true === \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\Features::class)->isFeatureEnabled('fluidBasedPageModule')) {
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript(
        'gridelements',
        'setup',
        '@import \'EXT:gridelements/Configuration/TypoScript/backend.typoscript\''
    );
}

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


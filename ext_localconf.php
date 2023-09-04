<?php

if (!defined('TYPO3')) {
    die('Access denied.');
}

// XCLASS
if (!empty($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['gridelements']['nestingInListModule'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Backend\RecordList\DatabaseRecordList::class] = ['className' => \GridElementsTeam\Gridelements\Xclass\DatabaseRecordList::class];
}

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addUserTSConfig('
	options.saveDocNew.tx_gridelements_backend_layout=1
');

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript(
    'gridelements',
    'setup',
    '@import \'EXT:gridelements/Configuration/TypoScript/backend.typoscript\''
);

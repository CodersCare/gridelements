<?php

if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

if (TYPO3_MODE === 'BE') {
    include_once(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('gridelements') . 'Classes/Backend/TtContent.php');

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_pagerenderer.php']['render-preProcess'][] = 'GridElementsTeam\\Gridelements\\Hooks\\PageRenderer->addJSCSS';

    $GLOBALS['TBE_STYLES']['skins']['gridelements']['name'] = 'gridelements';
    $GLOBALS['TBE_STYLES']['skins']['gridelements']['stylesheetDirectories']['gridelements_structure'] = 'EXT:gridelements/Resources/Public/Backend/Css/Skin/';

    if ($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['gridelements']['additionalStylesheet'] && \TYPO3\CMS\Core\Utility\GeneralUtility::validPathStr($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['gridelements']['additionalStylesheet'])) {
        $GLOBALS['TBE_STYLES']['skins']['gridelements']['stylesheetDirectories']['gridelements_additional'] = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['gridelements']['additionalStylesheet'];
    }
}

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_gridelements_backend_layout');

$GLOBALS['TYPO3_CONF_VARS']['BE']['ContextMenu']['ItemProviders'][1487270751] = \GridElementsTeam\Gridelements\ContextMenu\ItemProvider::class;

// Hooks
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/class.tx_cms_layout.php']['tt_content_drawItem'][] = \GridElementsTeam\Gridelements\Hooks\DrawItem::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/class.tx_cms_layout.php']['record_is_used'][] = \GridElementsTeam\Gridelements\Hooks\PageLayoutView::class . '->contentIsUsed';

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms']['db_new_content_el']['wizardItemsHook'][] = \GridElementsTeam\Gridelements\Hooks\WizardItems::class;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = \GridElementsTeam\Gridelements\Hooks\DataHandler::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = \GridElementsTeam\Gridelements\Hooks\DataHandler::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['moveRecordClass'][] = \GridElementsTeam\Gridelements\Hooks\DataHandler::class;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tx_templavoila_api']['apiIsRunningTCEmain'] = true;

$GLOBALS['TYPO3_USER_SETTINGS']['columns']['dragAndDropHideNewElementWizardInfoOverlay'] = [
    'type' => 'check',
    'label' => 'LLL:EXT:gridelements/Resources/Private/Language/locallang_db.xml:dragAndDropHideNewElementWizardInfoOverlay',
];

$GLOBALS['TYPO3_USER_SETTINGS']['columns']['hideColumnHeaders'] = [
    'type' => 'check',
    'label' => 'LLL:EXT:gridelements/Resources/Private/Language/locallang_db.xml:hideColumnHeaders',
];

$GLOBALS['TYPO3_USER_SETTINGS']['columns']['hideContentPreview'] = [
    'type' => 'check',
    'label' => 'LLL:EXT:gridelements/Resources/Private/Language/locallang_db.xml:hideContentPreview',
];

$GLOBALS['TYPO3_USER_SETTINGS']['columns']['showGridInformation'] = [
    'type' => 'check',
    'label' => 'LLL:EXT:gridelements/Resources/Private/Language/locallang_db.xml:showGridInformation',
];

$GLOBALS['TYPO3_USER_SETTINGS']['columns']['disableDragInWizard'] = [
    'type' => 'check',
    'label' => 'LLL:EXT:gridelements/Resources/Private/Language/locallang.xlf:disableDragInWizard',
];

$GLOBALS['TYPO3_USER_SETTINGS']['columns']['disableCopyFromPageButton'] = [
    'type' => 'check',
    'label' => 'LLL:EXT:gridelements/Resources/Private/Language/locallang.xlf:disableCopyFromPageButton',
];

if (isset($GLOBALS['TYPO3_USER_SETTINGS']['showitem'])) {
    $GLOBALS['TYPO3_USER_SETTINGS']['showitem'] .= ',
    --div--;LLL:EXT:gridelements/Resources/Private/Language/locallang_db.xml:gridElements,
        dragAndDropHideNewElementWizardInfoOverlay,
        hideColumnHeaders,
        hideContentPreview,
        showGridInformation,
        disableDragInWizard,
        disableCopyFromPageButton
        ';
}

// Hooks
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][\TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools::class]['flexParsing']['gridelements'] = \GridElementsTeam\Gridelements\Hooks\TtContentFlexForm::class;

if ($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['gridelements']['nestingInListModule']) {
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/class.db_list_extra.inc']['actions'][] = \GridElementsTeam\Gridelements\Hooks\DatabaseRecordList::class;
}

$iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);
$iconRegistry->registerIcon('gridelements-default', \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class, [
    'source' => 'EXT:gridelements/Resources/Public/Icons/gridelements.svg',
]);

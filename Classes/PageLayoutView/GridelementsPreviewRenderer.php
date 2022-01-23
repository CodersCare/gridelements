<?php

namespace GridElementsTeam\Gridelements\PageLayoutView;

use GridElementsTeam\Gridelements\Helper\Helper;
use TYPO3\CMS\Backend\Preview\PreviewRendererInterface;
use TYPO3\CMS\Backend\Preview\StandardContentPreviewRenderer;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem;
use TYPO3\CMS\Backend\View\PageLayoutView;
use TYPO3\CMS\Backend\View\PageLayoutViewDrawItemHookInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Database\QueryGenerator;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class GridelementsPreviewRenderer extends StandardContentPreviewRenderer implements PreviewRendererInterface
{
    /**
     * @var array
     */
    protected $extentensionConfiguration;

    /**
     * @var Helper
     */
    protected $helper;

    /**
     * @var IconFactory
     */
    protected $iconFactory;

    /**
     * @var LanguageService
     */
    protected $languageService;

    /**
     * Stores whether a certain language has translations in it
     *
     * @var array
     */
    protected $languageHasTranslationsCache = [];

    /**
     * @var QueryGenerator
     */
    protected $tree;

    /**
     * @var bool
     */
    protected $showHidden;

    /**
     * @var string
     */
    protected $backPath = '';

    public function __construct()
    {
        $this->extentensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('gridelements');
        $this->setLanguageService($GLOBALS['LANG']);
        $this->helper = Helper::getInstance();
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $this->cleanupCollapsedStatesInUC();
    }

    /**
     * setter for LanguageService object
     *
     * @param LanguageService $languageService
     */
    public function setLanguageService(LanguageService $languageService)
    {
        $this->languageService = $languageService;
    }

    /**
     * Processes the collapsed states of Gridelements columns and removes columns with 0 values
     */
    public function cleanupCollapsedStatesInUC()
    {
        $backendUser = $this->getBackendUser();
        if (is_array($backendUser->uc['moduleData']['page']['gridelementsCollapsedColumns'])) {
            $collapsedGridelementColumns = $backendUser->uc['moduleData']['page']['gridelementsCollapsedColumns'];
            foreach ($collapsedGridelementColumns as $item => $collapsed) {
                if (empty($collapsed)) {
                    unset($collapsedGridelementColumns[$item]);
                }
            }
            $backendUser->uc['moduleData']['page']['gridelementsCollapsedColumns'] = $collapsedGridelementColumns;
            $backendUser->writeUC($backendUser->uc);
        }
    }

    /**
     * Dedicated method for rendering preview header HTML for
     * the page module only. Receives the the GridColumnItem
     * that contains the record for which a preview header
     * should be rendered and returned.
     *
     * @param GridColumnItem $item
     * @return string
     */
    public function renderPageModulePreviewHeader(GridColumnItem $item): string
    {
        return parent::renderPageModulePreviewHeader($item);
    }

    /**
     * Dedicated method for rendering preview body HTML for
     * the page module only. Receives the the GridColumnItem
     * that contains the record for which a preview should be
     * rendered and returned.
     *
     * @param GridColumnItem $item
     * @return string
     */
    public function renderPageModulePreviewContent(GridColumnItem $item): string
    {
        $out = '';
        $record = $item->getRecord();

        $contentTypeLabels = $item->getContext()->getContentTypeLabels();
        $languageService = $this->getLanguageService();

        $drawItem = true;
        $hookPreviewContent = '';
        // Hook: Render an own preview of a record
        if (!empty($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/class.tx_cms_layout.php']['tt_content_drawItem'])) {
            $pageLayoutView = PageLayoutView::createFromPageLayoutContext($item->getContext());
            foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/class.tx_cms_layout.php']['tt_content_drawItem'] ?? [] as $className) {
                $hookObject = GeneralUtility::makeInstance($className);
                if (!$hookObject instanceof PageLayoutViewDrawItemHookInterface) {
                    throw new \UnexpectedValueException(
                        $className . ' must implement interface ' . PageLayoutViewDrawItemHookInterface::class,
                        1582574553
                    );
                }
                $hookObject->preProcess($pageLayoutView, $drawItem, $previewHeader, $hookPreviewContent, $record);
            }
            $item->setRecord($record);
        }

        if (!$drawItem) {
            return $hookPreviewContent;
        }
        // Check if a Fluid-based preview template was defined for this CType
        // and render it via Fluid. Possible option:
        // mod.web_layout.tt_content.preview.media = EXT:site_mysite/Resources/Private/Templates/Preview/Media.html
        $infoArr = [];
        $this->getProcessedValue($item, 'header_position,header_layout,header_link', $infoArr);
        $tsConfig = BackendUtility::getPagesTSconfig($record['pid'])['mod.']['web_layout.']['tt_content.']['preview.'] ?? [];
        if (!empty($tsConfig[$record['CType']]) || !empty($tsConfig[$record['CType'] . '.'])) {
            $fluidPreview = $this->renderContentElementPreviewFromFluidTemplate($record);
            if ($fluidPreview !== null) {
                return $fluidPreview;
            }
        }

        // Draw preview of the item depending on its CType
        switch ($record['CType']) {
            case 'gridelements_pi1':
                $out .= 'Gridelement';
                break;
            case 'shortcut':
                if (!empty($record['records'])) {
                    $out .= $this->renderShortCutContent($item, $record);
                }
                break;
            default:
                $out = '';
        }

        return $out;
    }

    protected function renderShortcutContent($item, $record)
    {
        $shortcutContent = '';
        $shortcutItems = explode(',', $record['records']);
        $collectedItems = [];
        foreach ($shortcutItems as $shortcutItem) {
            $shortcutItem = trim($shortcutItem);
            if (strpos($shortcutItem, 'pages_') !== false) {
                $this->collectContentDataFromPages(
                    $shortcutItem,
                    $collectedItems,
                    $record['recursive'],
                    $record['uid'],
                    $record['sys_language_uid']
                );
            } else {
                if (strpos($shortcutItem, '_') === false || strpos($shortcutItem, 'tt_content_') !== false) {
                    $this->collectContentData(
                        $shortcutItem,
                        $collectedItems,
                        $record['uid'],
                        $record['sys_language_uid']
                    );
                }
            }
        }
        if (!empty($collectedItems)) {
            foreach ($collectedItems as $item) {
                if ($item) {
                    $className = $item['tx_gridelements_reference_container'] ? 'reference container_reference' : 'reference';
                    $shortcutContent .= '<div class="' . $className . '">';
                    $shortcutContent .= 'Shortcut content';
                    // NOTE: this is the end tag for <div class="t3-page-ce-body">
                    // because of bad (historic) conception, starting tag has to be placed inside tt_content_drawHeader()
                    $shortcutContent .= '<div class="reference-overlay"></div></div></div>';
                }
            }
        }
        return $shortcutContent;
    }

    /**
     * Collects tt_content data from a single page or a page tree starting at a given page
     *
     * @param string $shortcutItem : The single page to be used as the tree root
     * @param array $collectedItems : The collected item data rows ordered by parent position, column position and sorting
     * @param int $recursive : The number of levels for the recursion
     * @param int $parentUid : uid of the referencing tt_content record
     * @param int $language : sys_language_uid of the referencing tt_content record
     */
    protected function collectContentDataFromPages(
        $shortcutItem,
        &$collectedItems,
        $recursive = 0,
        $parentUid = 0,
        $language = 0
    ) {
        $itemList = str_replace('pages_', '', $shortcutItem);
        if ($recursive) {
            if (!$this->tree instanceof QueryGenerator) {
                $this->tree = GeneralUtility::makeInstance(QueryGenerator::class);
            }
            $itemList = $this->tree->getTreeList($itemList, (int)$recursive, 0, 1);
        }
        $itemList = GeneralUtility::intExplode(',', $itemList);

        $queryBuilder = $this->getQueryBuilder();

        $items = $queryBuilder
            ->select('*')
            ->addSelectLiteral($queryBuilder->expr()->inSet(
                'pid',
                $queryBuilder->createNamedParameter($itemList, Connection::PARAM_INT_ARRAY)
            ) . ' AS inSet')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->neq(
                    'uid',
                    $queryBuilder->createNamedParameter((int)$parentUid, \PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->in(
                    'pid',
                    $queryBuilder->createNamedParameter($itemList, Connection::PARAM_INT_ARRAY)
                ),
                $queryBuilder->expr()->gte('colPos', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                $queryBuilder->expr()->in(
                    'sys_language_uid',
                    $queryBuilder->createNamedParameter([0, -1], Connection::PARAM_INT_ARRAY)
                )
            )
            ->orderBy('inSet')
            ->addOrderBy('colPos')
            ->addOrderBy('sorting')
            ->execute()
            ->fetchAll();

        foreach ($items as $item) {
            if (!empty($this->extentensionConfiguration['overlayShortcutTranslation']) && $language > 0) {
                $translatedItem = BackendUtility::getRecordLocalization('tt_content', $item['uid'], $language);
                if (!empty($translatedItem)) {
                    $item = array_shift($translatedItem);
                }
            }
            if ($this->helper->getBackendUser()->workspace > 0) {
                unset($item['inSet']);
                BackendUtility::workspaceOL('tt_content', $item, $this->helper->getBackendUser()->workspace);
            }
            $item['tx_gridelements_reference_container'] = $item['pid'];
            $collectedItems[] = $item;
        }
    }

    /**
     * Collects tt_content data from a single tt_content element
     *
     * @param string $shortcutItem : The tt_content element to fetch the data from
     * @param array $collectedItems : The collected item data row
     * @param int $parentUid : uid of the referencing tt_content record
     * @param int $language : sys_language_uid of the referencing tt_content record
     */
    protected function collectContentData($shortcutItem, &$collectedItems, $parentUid, $language)
    {
        $shortcutItem = str_replace('tt_content_', '', $shortcutItem);
        if ((int)$shortcutItem !== (int)$parentUid) {
            $queryBuilder = $this->getQueryBuilder();
            if ($this->showHidden) {
                $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);
            }
            $item = $queryBuilder
                ->select('*')
                ->from('tt_content')
                ->where(
                    $queryBuilder->expr()->eq(
                        'uid',
                        $queryBuilder->createNamedParameter((int)$shortcutItem, \PDO::PARAM_INT)
                    )
                )
                ->setMaxResults(1)
                ->execute()
                ->fetch();

            if (!empty($this->extentensionConfiguration['overlayShortcutTranslation']) && $language > 0) {
                $translatedItem = BackendUtility::getRecordLocalization('tt_content', $item['uid'], $language);
                if (!empty($translatedItem)) {
                    $item = array_shift($translatedItem);
                }
            }

            if ($this->helper->getBackendUser()->workspace > 0) {
                BackendUtility::workspaceOL(
                    'tt_content',
                    $item,
                    $this->helper->getBackendUser()->workspace
                );
            }
            $collectedItems[] = $item;
        }
    }

    /**
     * Render a footer for the record to display in page module below
     * the body of the item's preview.
     *
     * @param GridColumnItem $item
     * @return string
     */
    public function renderPageModulePreviewFooter(GridColumnItem $item): string
    {
        return parent::renderPageModulePreviewFooter($item);
    }

    /**
     * Dedicated method for wrapping a preview header and body
     * HTML. Receives $item, an instance of GridColumnItem holding
     * among other things the record, which can be used to determine
     * appropriate wrapping.
     *
     * @param string $previewHeader
     * @param string $previewContent
     * @param GridColumnItem $item
     * @return string
     */
    public function wrapPageModulePreview(string $previewHeader, string $previewContent, GridColumnItem $item): string
    {
        return parent::wrapPageModulePreview($previewHeader, $previewContent, $item);
    }

    /**
     * getter for queryBuilder
     *
     * @return QueryBuilder
     */
    public function getQueryBuilder()
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');
        return $queryBuilder;
    }
}

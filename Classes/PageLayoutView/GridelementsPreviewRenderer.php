<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\PageLayoutView;

use GridElementsTeam\Gridelements\Backend\LayoutSetup;
use GridElementsTeam\Gridelements\Helper\Helper;
use GridElementsTeam\Gridelements\View\BackendLayout\Grid\GridelementsGridColumn;
use GridElementsTeam\Gridelements\View\BackendLayout\Grid\GridelementsGridColumnItem;
use TYPO3\CMS\Backend\Preview\PreviewRendererInterface;
use TYPO3\CMS\Backend\Preview\StandardContentPreviewRenderer;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\Grid;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridRow;
use TYPO3\CMS\Backend\View\PageLayoutView;
use TYPO3\CMS\Backend\View\PageLayoutViewDrawItemHookInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\QueryGenerator;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\BackendConfigurationManager;
use TYPO3\CMS\Fluid\View\StandaloneView;
use UnexpectedValueException;

class GridelementsPreviewRenderer extends StandardContentPreviewRenderer implements PreviewRendererInterface
{
    /**
     * @var array
     */
    protected $extentensionConfiguration;

    /**
     * @var Helper
     */
    protected Helper $helper;

    /**
     * @var IconFactory
     */
    protected $iconFactory;

    /**
     * @var LanguageService
     */
    protected LanguageService $languageService;

    /**
     * Stores whether a certain language has translations in it
     *
     * @var array
     */
    protected array $languageHasTranslationsCache = [];

    /**
     * @var QueryGenerator
     */
    protected QueryGenerator $tree;

    /**
     * @var bool
     */
    protected bool $showHidden;

    /**
     * @var string
     */
    protected string $backPath = '';

    /**
     * @throws \TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException
     * @throws \TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException
     */
    public function __construct()
    {
        $this->extentensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('gridelements');
        $this->helper = Helper::getInstance();
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $this->cleanupCollapsedStatesInUC();
    }

    /**
     * Processes the collapsed states of Gridelements columns and removes columns with 0 values
     */
    public function cleanupCollapsedStatesInUC()
    {
        $backendUser = $this->getBackendUser();
        if (!empty($backendUser->uc['moduleData']['page']['gridelementsCollapsedColumns'])
            && is_array($backendUser->uc['moduleData']['page']['gridelementsCollapsedColumns'])) {
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
     * Dedicated method for rendering preview body HTML for
     * the page module only. Receives the GridColumnItem
     * that contains the record for which a preview should be
     * rendered and returned.
     *
     * @param GridColumnItem $item
     * @return string
     */
    public function renderPageModulePreviewContent(GridColumnItem $item): string
    {
        $drawItem = true;
        $hookPreviewContent = '';
        $record = $item->getRecord();
        // Hook: Render an own preview of a record
        if (!empty($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/class.tx_cms_layout.php']['tt_content_drawItem'])) {
            $pageLayoutView = PageLayoutView::createFromPageLayoutContext($item->getContext());
            foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/class.tx_cms_layout.php']['tt_content_drawItem'] ?? [] as $className) {
                $hookObject = GeneralUtility::makeInstance($className);
                if (!$hookObject instanceof PageLayoutViewDrawItemHookInterface) {
                    throw new UnexpectedValueException(
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

        return $this->renderGridContainer($item);
    }

    /**
     * @param GridColumnItem $item
     * @return string
     */
    protected function renderGridContainer(GridColumnItem $item): string
    {
        $context = $item->getContext();
        $record = $item->getRecord();
        $grid = GeneralUtility::makeInstance(Grid::class, $context);
        $helper = GeneralUtility::makeInstance(Helper::class);
        $gridContainerId = $record['uid'];
        $pageId = $record['pid'];
        if ($pageId < 0) {
            $originalRecord = BackendUtility::getRecord('tt_content', $record['t3ver_oid']);
        } else {
            $originalRecord = $record;
        }
        $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class)->init($originalRecord['pid']);
        $gridElement = $layoutSetup->cacheCurrentParent($gridContainerId, true);
        $layoutId = $gridElement['tx_gridelements_backend_layout'];
        $layout = $layoutSetup->getLayoutSetup($layoutId);
        $layoutColumns = $layoutSetup->getLayoutColumns($layoutId);
        $activeColumns = [];
        if (!empty($layoutColumns['CSV'])) {
            $activeColumns = array_flip(GeneralUtility::intExplode(',', $layoutColumns['CSV']));
        }

        if (isset($layout['config']['rows.'])) {
            $children = $helper->getChildren('tt_content', $gridContainerId, $pageId, 'sorting', 0, '*');
            $childColumns = [];
            foreach ($children as $childRecord) {
                if (isset($childRecord['tx_gridelements_columns'])) {
                    $childColumns[$childRecord['tx_gridelements_columns']][] = $childRecord;
                }
            }
            foreach ($layout['config']['rows.'] as $row) {
                $gridRow = GeneralUtility::makeInstance(GridRow::class, $context);
                if (isset($row['columns.'])) {
                    foreach ($row['columns.'] as $column) {
                        $gridColumn = GeneralUtility::makeInstance(GridelementsGridColumn::class, $context, $column, $gridContainerId);
                        $gridColumn->setRestrictions($layoutColumns);
                        if (isset($column['colPos']) && isset($activeColumns[$column['colPos']])) {
                            $gridColumn->setActive();
                        }
                        $gridRow->addColumn($gridColumn);
                        if (isset($column['colPos']) && isset($childColumns[$column['colPos']])) {
                            $gridColumn->setCollapsed(!empty($this->helper->getBackendUser()->uc['moduleData']['page']['gridelementsCollapsedColumns'][$gridContainerId . '_' . $column['colPos']]));
                            foreach ($childColumns[$column['colPos']] as $child) {
                                $gridColumnItem = GeneralUtility::makeInstance(GridelementsGridColumnItem::class, $context, $gridColumn, $child, $layoutColumns);
                                $gridColumn->addItem($gridColumnItem);
                            }
                        }
                    }
                }
                $grid->addRow($gridRow);
            }
        }

        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $configurationManager = GeneralUtility::makeInstance(BackendConfigurationManager::class);
        $configuration = $configurationManager->getConfiguration('gridelements');
        $view->setTemplate($configuration['backendContainer']['view']['defaultTemplate'] ?? '');
        $view->setLayoutRootPaths($configuration['backendContainer']['view']['layoutRootPaths'] ?? '');
        $view->setTemplateRootPaths($configuration['backendContainer']['view']['templateRootPaths'] ?? '');
        $view->setPartialRootPaths($configuration['backendContainer']['view']['partialRootPaths'] ?? '');
        $view->assign('hideRestrictedColumns', !empty(BackendUtility::getPagesTSconfig($context->getPageId())['mod.']['web_layout.']['hideRestrictedCols']));
        $view->assign('newContentTitle', $this->getLanguageService()->getLL('newContentElement'));
        $view->assign('newContentTitleShort', $this->getLanguageService()->getLL('content'));
        $view->assign('allowEditContent', $this->getBackendUser()->check('tables_modify', 'tt_content'));
        $view->assign('gridElementsBackendLayout', $layout);
        $view->assign('gridElementsContainer', $grid);
        $rendered = $view->render();

        return $rendered;
    }
}

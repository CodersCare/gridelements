<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Functional\Helper;

use GridElementsTeam\Gridelements\EventListener\ModifyNewContentElementWizardItemsListener;
use GridElementsTeam\Gridelements\Helper\SvgIconSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Icon sources given as path below the web root (TSconfig icons, FAL icons with the backend site path prefix)
 * must produce inline SVG markup for <typo3-backend-icon> in the TYPO3 12 wizard.
 */
class SvgIconSourceTest extends FunctionalTestCase
{
    private const WEBROOT_SVG = '/typo3temp/assets/gridelements-icon-test/icon_benefits-country.svg';
    private const WEBROOT_PNG = '/typo3temp/assets/gridelements-icon-test/icon.png';
    // A public FAL storage outside fileadmin (basePath home/global/)
    private const GLOBAL_STORAGE_SVG = '/home/global/design/gridelements/icon_benefits-country.svg';
    private const FILEADMIN_SVG = '/fileadmin/gridelements-icon-test/icon_benefits-country.svg';
    private const SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><rect width="16" height="16"/></svg>';

    protected array $testExtensionsToLoad = ['gridelementsteam/gridelements'];

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
        foreach ([self::WEBROOT_SVG, self::GLOBAL_STORAGE_SVG, self::FILEADMIN_SVG] as $file) {
            GeneralUtility::mkdir_deep(dirname(Environment::getPublicPath() . $file));
            file_put_contents(Environment::getPublicPath() . $file, self::SVG);
        }
        file_put_contents(Environment::getPublicPath() . self::WEBROOT_PNG, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));
    }

    protected function tearDown(): void
    {
        GeneralUtility::rmdir(Environment::getPublicPath() . '/typo3temp/assets/gridelements-icon-test', true);
        GeneralUtility::rmdir(Environment::getPublicPath() . '/home', true);
        GeneralUtility::rmdir(Environment::getPublicPath() . '/fileadmin/gridelements-icon-test', true);
        parent::tearDown();
    }

    #[Test]
    public function webrootPathIsMappedToThePublicFile(): void
    {
        self::assertSame(Environment::getPublicPath() . self::WEBROOT_SVG, SvgIconSource::resolve(self::WEBROOT_SVG));
    }

    public static function unchangedSourcesDataProvider(): array
    {
        return [
            'extension resource' => ['EXT:gridelements/Resources/Public/Icons/gridelements.svg'],
            'FAL identifier' => ['FAL:1:/gridelements-icon-test/icon_benefits-country.svg'],
            'relative public path' => ['typo3temp/assets/gridelements-icon-test/icon_benefits-country.svg'],
            'https URL' => ['https://example.org/icon.svg'],
            'protocol relative URL' => ['//example.org/icon.svg'],
            'missing webroot file' => ['/typo3temp/assets/gridelements-icon-test/missing.svg'],
            'back path' => ['/../typo3temp/assets/gridelements-icon-test/icon_benefits-country.svg'],
        ];
    }

    #[Test]
    #[DataProvider('unchangedSourcesDataProvider')]
    public function otherSourcesAreKeptUnchanged(string $source): void
    {
        self::assertSame($source, SvgIconSource::resolve($source));
    }

    #[Test]
    public function existingAbsoluteFilePathIsKeptUnchanged(): void
    {
        $source = Environment::getPublicPath() . self::WEBROOT_SVG;

        self::assertSame($source, SvgIconSource::resolve($source));
    }

    public static function falStorageSourcesDataProvider(): array
    {
        return [
            'public storage outside fileadmin' => [self::GLOBAL_STORAGE_SVG],
            'fileadmin storage' => [self::FILEADMIN_SVG],
        ];
    }

    #[Test]
    #[DataProvider('falStorageSourcesDataProvider')]
    public function publicUrlOfFalStorageFileIsKeptOrMappedToThePublicFile(string $webrootPath): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/svg_icon_fal_storages.csv');
        $publicUrl = ltrim($webrootPath, '/');

        self::assertSame($publicUrl, SvgIconSource::resolve($publicUrl));
        self::assertSame(Environment::getPublicPath() . $webrootPath, SvgIconSource::resolve($webrootPath));
    }

    #[Test]
    public function wizardIconsFromWebrootPathsRenderSvgMarkup(): void
    {
        $this->skipBelowTypo3Twelve();
        // FAL icons get the site path prefix in backend requests
        $wizardItems = $this->addGridItemsToWizard([
            'webroot_layout' => [self::WEBROOT_SVG],
            'global_layout' => [self::GLOBAL_STORAGE_SVG],
            'fileadmin_layout' => [self::FILEADMIN_SVG],
        ]);

        foreach (['webroot_layout', 'global_layout', 'fileadmin_layout'] as $layoutId) {
            self::assertSame('gridelements-layout-0-' . $layoutId, $wizardItems['gridelements_' . $layoutId]['iconIdentifier']);
            $this->assertSvgIconRenders('gridelements-layout-0-' . $layoutId);
        }
    }

    #[Test]
    public function wizardIconsFromOtherSourcesAreRegisteredUnchanged(): void
    {
        $this->skipBelowTypo3Twelve();
        $wizardItems = $this->addGridItemsToWizard([
            'ext_layout' => ['EXT:gridelements/Resources/Public/Icons/gridelements.svg'],
            'relative_layout' => [ltrim(self::FILEADMIN_SVG, '/')],
            'bitmap_layout' => [self::WEBROOT_PNG],
        ]);

        $iconRegistry = GeneralUtility::makeInstance(IconRegistry::class);
        foreach (['ext_layout', 'relative_layout'] as $layoutId) {
            $configuration = $iconRegistry->getIconConfigurationByIdentifier($wizardItems['gridelements_' . $layoutId]['iconIdentifier']);
            self::assertSame(SvgIconProvider::class, $configuration['provider'], $layoutId);
            $this->assertSvgIconRenders($wizardItems['gridelements_' . $layoutId]['iconIdentifier']);
        }
        self::assertSame('EXT:gridelements/Resources/Public/Icons/gridelements.svg', $iconRegistry->getIconConfigurationByIdentifier('gridelements-layout-0-ext_layout')['options']['source']);
        $bitmapConfiguration = $iconRegistry->getIconConfigurationByIdentifier($wizardItems['gridelements_bitmap_layout']['iconIdentifier']);
        self::assertSame(BitmapIconProvider::class, $bitmapConfiguration['provider']);
        self::assertSame(self::WEBROOT_PNG, $bitmapConfiguration['options']['source']);
    }

    private function skipBelowTypo3Twelve(): void
    {
        if ((new Typo3Version())->getMajorVersion() < 12) {
            self::markTestSkipped('The TYPO3 11 wizard renders the image markup, which supports these sources.');
        }
    }

    /**
     * @param array<string, string[]> $icons Icon sources by layout
     * @return array Wizard items
     */
    private function addGridItemsToWizard(array $icons): array
    {
        $gridItems = [];
        foreach ($icons as $layoutId => $icon) {
            $gridItems[$layoutId] = ['uid' => $layoutId, 'title' => $layoutId, 'tll' => '', 'tt_content_defValues' => [], 'icon' => $icon];
        }
        $wizardItems = [];
        (new \ReflectionClass(ModifyNewContentElementWizardItemsListener::class))
            ->newInstanceWithoutConstructor()
            ->addGridItemsToWizard($gridItems, $wizardItems);
        return $wizardItems;
    }

    private function assertSvgIconRenders(string $identifier): void
    {
        $iconConfiguration = GeneralUtility::makeInstance(IconRegistry::class)->getIconConfigurationByIdentifier($identifier);
        self::assertSame(SvgIconProvider::class, $iconConfiguration['provider']);
        $icon = GeneralUtility::makeInstance(IconFactory::class)->getIcon($identifier, 'small');
        self::assertSame($identifier, $icon->getIdentifier());
        // The inline variant is what <typo3-backend-icon> requests from the icon AJAX route.
        self::assertStringContainsString('<svg', $icon->getMarkup('inline'), $identifier . ' has no inline SVG markup');
        self::assertMatchesRegularExpression('#<img src="[^"]*(icon_benefits-country|gridelements)\.svg"#', $icon->getMarkup(), $identifier . ' has no image markup');
    }
}

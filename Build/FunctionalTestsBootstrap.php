<?php

declare(strict_types=1);

(static function (): void {
    // Build/FunctionalTestsBootstrap.php is three levels below the workspace root:
    // packages/gridelements/Build/ → packages/gridelements/ → packages/ → workspace root
    // Always override: composer autoload-include.php sets TYPO3_PATH_ROOT to the extension
    // subdirectory before this bootstrap runs, so we must unconditionally correct it.
    $webRoot = dirname(__DIR__, 3) . '/public';
    putenv('TYPO3_PATH_ROOT=' . $webRoot);
    putenv('TYPO3_PATH_WEB=' . $webRoot);

    $testbase = new \TYPO3\TestingFramework\Core\Testbase();
    $testbase->defineOriginalRootPath();
    $testbase->createDirectory(ORIGINAL_ROOT . 'typo3temp/var/tests');
    $testbase->createDirectory(ORIGINAL_ROOT . 'typo3temp/var/transient');
})();

<?php
set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', true);

require_once 'vendor/autoload.php';
require_once 'converter/Converter.php';

// --------------

$filelist = glob('resources/sources/*.zip');
foreach ($filelist as $filename) {
    $basename = basename($filename, '.zip');

    echo 'Converting: ' . $basename . @str_repeat(' ', 32 - strlen($basename));
    if (file_exists('resources/outputs/' . $basename . '.zip')) {
        echo  '[ SKIP ]' . PHP_EOL;
        continue;
    }

    $converter = new Converter\Converter($basename);

    // Proxy for Google
    //$converter->setProxy('194.87.236.181', 3128, 'proxy', 'password');

    // Unzip source
    $converter->unzipSource();

    // Copy template
    $converter->restoreTemplate();

    // Rename images
    $converter->copyRenamedImages();

    // Convert 440px images
    $converter->convertScreenshots(440);

    // Convert 1280px images
    $converter->convertScreenshots(1280);

    // Convert icons
    $converter->convertIcons(['16x16', '48x48', '128x128'], '256x256');

    // Translate descriptions
    $converter->translateTexts();

    // Archive files
    $converter->archiveOutput();

    // Cleanup
    $converter->cleanup();

    echo '[  OK  ]' . PHP_EOL;
}

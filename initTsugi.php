<?php
require_once('config.php');
require 'vendor/autoload.php';
require_once $CFG->codetestBasePath. '/util/RestClient.php';
require_once $CFG->codetestBasePath. '/util/JSONManager.php';
include_once('util/ValidatorService.php');

use Tsugi\Core\LTIX;
use Tsugi\Core\Launch;

use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;

// Load launch data with Tsugi
$LAUNCH = LTIX::requireData();

// Initialize function that will get the help template for each page
$help = function() {
    $phpFile = basename($_SERVER['PHP_SELF']);
    if (file_exists("views/help/$phpFile.twig")) {
        $filename = "$phpFile.twig";
    } else {
        $filename = 'no-help.php.twig';
    }
    return $filename;
};

// Add views path to loader
$loader = new \Twig\Loader\FilesystemLoader($CFG->twig['viewsPath']);

$twig = new \Twig\Environment($loader, [
    'debug' => false, // $CFG->twig['debug'],
    'cache' => $CFG->twig['cachePath'],
]);

$jsonData = JSONManager::getJsonData(getenv("CODETEST_REST_FILE_PATH") ? sys_get_temp_dir().getenv("CODETEST_REST_FILE_PATH") : $CFG->codetestBasePath. '/rest-data.json');


if(!empty($jsonData['authorkit']['token'])){
    $restClientAuthorkit = new RestClient($CFG->apiConfigs['authorkit']['baseUrl'], $jsonData['authorkit']['token']['accessToken']);
}else{
    $restClientAuthorkit = new RestClient($CFG->apiConfigs['authorkit']['baseUrl'], null);
    $restClientAuthorkit->loginAuthor(
        $CFG->apiConfigs['authorkit']['user'],
        $CFG->apiConfigs['authorkit']['pass']
    );
}
$restClientAuthorkit->checkAuthorkitIsOnline();
$GLOBALS['REST_CLIENT_AUTHOR'] = $restClientAuthorkit;


if(!empty($jsonData['spring-repo']['token'])){
    $restClientRepo = new RestClient($CFG->apiConfigs['spring-repo']['baseUrl'], $jsonData['spring-repo']['token']['accessToken']);
}else{
    $restClientRepo = new RestClient($CFG->apiConfigs['spring-repo']['baseUrl'], null);
    $restClientRepo->loginRepo(
        $CFG->apiConfigs['spring-repo']['user'],
        $CFG->apiConfigs['spring-repo']['pass']
    );
}
$restClientRepo->checkRepoIsOnline();
$GLOBALS['REST_CLIENT_REPO'] = $restClientRepo;


// Get locale from TSUGI extracted from the launch-data
$lang = substr($TSUGI_LOCALE, 0, 2);

// Initialize translations
$translator = new Translator($lang);
$translator->setFallbackLocales(["en"]);
$translator->addLoader('php', new PhpFileLoader());


// look for all the files recursively inside the locale folder
$filesIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
        realpath(__DIR__.'/locale')
    )
);

// Add the files to the translation
foreach ($filesIterator as $file) {
    if ($file->isDir() || !str_ends_with($file->getFileName(), 'php')){
        continue;
    }
    $localeValueInPath = basename(dirname($file->getPathname(), 1));
    $translator->addResource('php', $file->getPathname(), $localeValueInPath);
}

$twig->addExtension(new TranslationExtension( $translator));

function logg($message){
    $timeFormat = new DateTime('now', new DateTimeZone("Europe/Madrid"));
    $timeFormat = $timeFormat->format('d/m/Y H:i:s');
    error_log("CT -> [$timeFormat] $message");
}

$validatorService = new ValidatorService;


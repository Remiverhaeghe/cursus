<?php

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Hors debug, Symfony ne regarde plus si la configuration, les routes ou les templates ont changé :
// on vide le cache de test dès qu'un fichier du projet est plus récent que le dernier passage.
$vProjectDir = dirname(__DIR__);
$vCacheDir = $vProjectDir.'/var/cache/test';
$vMarker = $vCacheDir.'/.sources-mtime';
$vLatest = 0;
foreach (['config', 'src', 'templates', 'migrations', 'assets'] as $vFolder) {
    $vFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($vProjectDir.'/'.$vFolder, FilesystemIterator::SKIP_DOTS));
    foreach ($vFiles as $vFile) {
        $vLatest = max($vLatest, $vFile->getMTime());
    }
}
foreach (glob($vProjectDir.'/.env*') ?: [] as $vEnvFile) {
    $vLatest = max($vLatest, (int) filemtime($vEnvFile));
}
if (!is_file($vMarker) || (int) file_get_contents($vMarker) < $vLatest) {
    (new Filesystem())->remove($vCacheDir);
    (new Filesystem())->dumpFile($vMarker, (string) $vLatest);
}

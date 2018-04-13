<?php
    require 'vendor/autoload.php';
    require_once __DIR__ . '/src/InstaScraper.php';

use InstaScraper\Insta;
use InstaScraper\Exception\InstagramEncodedException;

// Instantiate Instagram library
$Instagram = new Insta();

try {
    $medias = $Instagram->getMedias('instausername');

    var_dump($medias);
} catch (\Exception $e) {
    print_r($e->getMessage());
}

?>

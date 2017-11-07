<?php
    require 'vendor/autoload.php';
    require_once __DIR__ . '/src/InstagramScraper.php';

    use InstaScraper\Insta;

    $Instagram = new Insta();

    print_r($Instagram->getMedias('getheartbeatapp'));
?>

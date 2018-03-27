<?php
    require 'vendor/autoload.php';
    require_once __DIR__ . '/src/InstaScraper.php';

    use InstaScraper\Insta;

    $Instagram = new Insta();

    //print_r($Instagram->getMedias('_mattGrubb'));
    print_r($Instagram->getMedias('_mattGrubb', 14));
?>

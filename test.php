<?php
    require 'vendor/autoload.php';
    require_once __DIR__ . '/src/InstaScraper.php';

use InstaScraper\Insta;
use InstaScraper\Exception\InstagramEncodedException;

// Instantiate Instagram library
$Instagram = new Insta();

// $Instagram->setProxy([
//     'port' => '',
//     'address' => '',
//     'auth' => [
//         'user' => '',
//         'pass' => ''
//     ],
// ]);

try {
    $medias = $Instagram->getAccount('_mattGrubb');

    var_dump($medias);
} catch (\Exception $e) {
    print_r($e->getMessage());
}

?>

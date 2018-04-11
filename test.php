<?php
    require 'vendor/autoload.php';
    require_once __DIR__ . '/src/InstaScraper.php';

    use InstaScraper\Insta;

    // Instantiate Instagram library
    $Instagram = Insta::withCredentials('<REDACTED>', '<REDACTED>');

    $Instagram->login();

    try {
        $media = $Instagram->getMediaWithTag('<REDACTED>', '<REDACTED>');

        if (isset($media->error)) {
            echo $media->error_reason . PHP_EOL;
            die();
        }

        print_r($media);
    } catch (\Exception $e) {
        echo $e->getMessage() . PHP_EOL;
    }
?>

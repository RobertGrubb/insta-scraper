<?php
    require 'vendor/autoload.php';
    require_once __DIR__ . '/src/InstaScraper.php';

    use InstaScraper\Insta;

    // Instantiate Instagram library
    $Instagram = Insta::withCredentials('<REDACTED>', '<REDACTED>');

    $Instagram->login();

    try {
        $account = $Instagram->getAccount('<REDACTED>');

        if (isset($account->error)) {
            echo $account->error_reason . PHP_EOL;
            die();
        }

        var_dump($account);
    } catch (\Exception $e) {
        echo $e->getMessage() . PHP_EOL;
    }
?>

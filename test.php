<?php
    require 'vendor/autoload.php';
    require_once __DIR__ . '/src/InstaScraper.php';

    use InstaScraper\Insta;

    // Instantiate Instagram library
    $Instagram = new Insta();

    try {
        $account = $Instagram->getAccount('getheartbeatapp');

        if (isset($account->error)) {
            echo $account->error_reason . PHP_EOL;
            die();
        }

        var_dump($account);
    } catch (\Exception $e) {
        echo $e->getMessage() . PHP_EOL;
    }
?>

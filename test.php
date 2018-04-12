<?php
    require 'vendor/autoload.php';
    require_once __DIR__ . '/src/InstaScraper.php';

use InstaScraper\Insta;
use InstaScraper\Exception\InstagramEncodedException;

// Instantiate Instagram library
$Instagram = Insta::withCredentials('<REDACTED>', '<REDACTED>');

$continue = true;

try {
    $Instagram->login();
} catch (InstagramEncodedException $e) {
    $continue = false;

    $details = $e->getDecodedMessage();

    print_r($details);
}

if ($continue) {
    try {
        $account = $Instagram->getMedias('<REDACTED>');

        if (isset($account->error)) {
            echo $account->error_reason . PHP_EOL;
            die();
        }

        var_dump($account);
    } catch (\Exception $e) {
        print_r($e->getMessage());
    }
}

?>

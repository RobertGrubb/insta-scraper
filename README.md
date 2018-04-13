## information

`Credits to postaddictme/instagram-php-scraper for base`

## Installation

`composer.phar require robert-grubb/insta-scraper`

or

`composer require robert-grubb/insta-scraper`

## Instantiation

```
use InstaScraper\Insta;

$Instagram = new Insta();
```

## Documentation

For main package documentation: https://github.com/postaddictme/instagram-php-scraper

## Fork Information

This fork includes the following:

- getMediaWithTag()
    - This method will find the first media with a specific tag.

- Fixes getMedias issue (Because instagram removed the /media page)
    - Now pulls from their timeline data via a graphql request, then grabs the data for each array item. (Increases load times, but not by much)

- Better exceptions
    - For better understand of what is actually happening when an error occurs, InstagramEncodedException is thrown. You can catch this exception and use `InstagramEncodedException->getDecodedMessage()` that will return the following:

```
[
    [error] => true,
    [error_code] => 404,
    [error_reason] => 'User does not exist'
]
```

## Getting a media item with specific hashtag:

```
use InstaScraper\Insta;
$Instagram = new Insta();
$post = $Instagram->getMediaWithTag('test', '#awesome');
var_dump($post); // Returns InstaScraper\Model\Media Object
```

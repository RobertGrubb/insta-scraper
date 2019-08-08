<?php

// Define Namespace
namespace InstaScraper;

// Uses
use InstaScraper\Exception\InstagramAuthException;
use InstaScraper\Exception\InstagramException;
use InstaScraper\Exception\InstagramNotFoundException;
use InstaScraper\Exception\InstagramEncodedException;
use InstaScraper\Model\Account;
use InstaScraper\Model\Comment;
use InstaScraper\Model\Location;
use InstaScraper\Model\Media;
use InstaScraper\Model\Tag;
use phpFastCache\CacheManager;
use Unirest\Request;

class Insta
{
    // Set default class variables
    const MAX_COMMENTS_PER_REQUEST = 300;
    private static $instanceCache;
    public $sessionUsername;
    public $sessionPassword;
    public $userSession;

    // Class Constructor
    public function __construct() {}

    public static function withCredentials($username, $password, $sessionFolder = null)
    {
        if (is_null($sessionFolder)) {
            $sessionFolder = __DIR__ . DIRECTORY_SEPARATOR . 'sessions' . DIRECTORY_SEPARATOR;
        }
        if (is_string($sessionFolder)) {
            CacheManager::setDefaultConfig([
                'path' => $sessionFolder
            ]);
            self::$instanceCache = CacheManager::getInstance('files');
        } else {
            self::$instanceCache = $sessionFolder;
        }
        $instance = new self();
        $instance->sessionUsername = $username;
        $instance->sessionPassword = $password;
        return $instance;
    }

    public function getMediasFromPage($username, $count = 20)
    {
        $medias = [];
        $index = 0;
        $response = Request::get(Endpoints::getAccountPageMediasLink($username), $this->generateHeaders(false));

        if ($response->code === 403) {
            throw new InstagramEncodedException([
                'error_code' => 403,
                'error_reason' => 'Rate Limiting'
            ]);
        }

        if ($response->code != 200) {
            throw new InstagramEncodedException([
                'error_code' => $response->code,
                'error_reason' => 'Unknown'
            ]);
        }

        preg_match('/window._sharedData\s\=\s(.*?)\;<\/script>/', $response->raw_body, $data);
		$userArray = json_decode($data[1], true, 512, JSON_BIGINT_AS_STRING);

        if (!isset($userArray['entry_data']['ProfilePage'][0]['graphql']['user'])) {
            throw new InstagramEncodedException([
                'error_code' => 404,
                'error_reason' => 'Account with this username does not exist'
            ]);
        }

        $owner = Account::fromAccountPage($userArray['entry_data']['ProfilePage'][0]['graphql']['user']);

        $nodes = $userArray['entry_data']['ProfilePage'][0]['graphql']['user']['edge_owner_to_timeline_media']['edges'];

        if (!isset($nodes) || empty($nodes)) {
            return [];
        }

        // Iterate through each and add it to the medias array.
        foreach($nodes as $post) {
            $post = (array)$post;
            $post = $post['node'];
            $videoData = null;

            if ($post['is_video'] == true) {
                $postData = $this->getMediaByCode($post['shortcode']);
                $videoData = [
                    'video_url' => $postData->videoStandardResolutionUrl,
                    'video_view_count' => $postData->videoViews
                ];
            }

            $medias[] = Media::fromAccountPage($post, $owner, $videoData);
        }

        return $medias;
    }

    /**
     * Grab instagram account information for a specific
     * instagram account.
     * @param string username
     */
    public function getAccount($username)
    {
        $response = Request::get(Endpoints::getAccountPageLink($username), $this->generateHeaders(false));

        // Handle 404 response
        if ($response->code === 404) {
            throw new InstagramEncodedException([
                'error_code' => 404,
                'error_reason' => 'Account does not exist'
            ]);
        }

        if ($response->code === 403) {
            throw new InstagramEncodedException([
                'error_code' => 403,
                'error_reason' => 'Rate Limiting'
            ]);
        }

        // Handle any other response that is not 200
        if ($response->code !== 200) {
            throw new InstagramEncodedException([
                'error_code' => $response->code,
                'error_reason' => 'Unknown'
            ]);
        }

        preg_match('/window._sharedData\s\=\s(.*?)\;<\/script>/', $response->raw_body, $data);
		$userArray = json_decode($data[1], true, 512, JSON_BIGINT_AS_STRING);

        // Decode the data
        //$userArray = json_decode($response->raw_body, true);

        // If user is not set, throw exception
        if (!isset($userArray['entry_data']['ProfilePage'][0]['graphql']['user'])) {
            throw new InstagramEncodedException([
                'error_code' => 404,
                'error_reason' => 'Account with this username does not exist'
            ]);
        }

        // Return model
        return Account::fromAccountPage($userArray['entry_data']['ProfilePage'][0]['graphql']['user']);
    }

    /**
     * Get media items for a specific instagram account
     * @param  string  $username
     * @param  integer $post_num
     *
     * NEW: This now pulls from the account's timeline data
     * via a graphql call.
     */
    public function getMedias($username, $post_num = 20)
    {
        // Set medias to an empty array
        $medias = [];

        $medias = $this->getMediasFromPage($username);

        return $medias;
    }

    /**
     * Grabs the timeline media data for an instagram account.
     * The difference between this and getMedias is that this
     * will return a minimal amount of data for their Media
     * items.
     *
     * @param  string  $username
     * @param  integer $post_num
     */
    public function getTimlineMediaData($username, $post_num = 20) {
        $medias = [];

        $medias = $this->getMediasFromPage($username);

        return $medias;
    }

    /**
     * This method will look for 1 post that has a specific
     * hashtag in the caption.
     *
     * @param  string  $username
     * @param  string  $tag
     * @param  integer $post_num
     */
    public function getMediaWithTag($username, $tag) {
        $resPost = false;

        $medias = $this->getMediasFromPage($username);

        // If object has items
        if (count($medias) >= 1) {

            // Iterate through each
            foreach($medias as $post) {

                if (!isset($post->caption)) {
                    continue;
                }

                if (!$post->caption) {
                    continue;
                }

                // If the caption contains the tag, set the variable
                if (strpos(strtolower($post->caption), strtolower($tag)) !== false) {
                    $resPost = $post;
                }
            }
        }

        return $resPost;
    }

    public static function searchAccountsByUsername($username)
    {
        // TODO: Add tests and auth
        $response = Request::get(Endpoints::getGeneralSearchJsonLink($username), $this->generateHeaders(false));
        if ($response->code === 404) {
            return [
                'error' => true,
                'error_code' => $response->code,
                'error_reason' => 'Account with given username does not exist.'
            ];
        }
        if ($response->code !== 200) {
            return [
                'error' => true,
                'error_code' => $response->code,
                'error_reason' => 'Unknown'
            ];
        }

        $jsonResponse = json_decode($response->raw_body, true);
        if (!isset($jsonResponse['status']) || $jsonResponse['status'] != 'ok') {
            return [
                'error' => true,
                'error_code' => $response->code,
                'error_reason' => 'Unknown'
            ];
        }
        if (!isset($jsonResponse['users']) || count($jsonResponse['users']) == 0) {
            return [];
        }

        $accounts = [];
        foreach ($jsonResponse['users'] as $jsonAccount) {
            $accounts[] = Account::fromSearchPage($jsonAccount['user']);
        }
        return $accounts;
    }

    public static function searchTagsByTagName($tag)
    {
        // TODO: Add tests and auth
        $response = Request::get(Endpoints::getGeneralSearchJsonLink($tag), $this->generateHeaders(false));
        if ($response->code === 404) {
            return [
                'error' => true,
                'error_code' => $response->code,
                'error_reason' => 'Account with given username does not exist.'
            ];
        }
        if ($response->code !== 200) {
            return [
                'error' => true,
                'error_code' => $response->code,
                'error_reason' => 'Unknown'
            ];
        }

        $jsonResponse = json_decode($response->raw_body, true);
        if (!isset($jsonResponse['status']) || $jsonResponse['status'] != 'ok') {
            throw new InstagramException('Response code is not equal 200. Something went wrong. Please report issue.');
        }

        if (!isset($jsonResponse['hashtags']) || count($jsonResponse['hashtags']) == 0) {
            return [];
        }
        $hashtags = [];
        foreach ($jsonResponse['hashtags'] as $jsonHashtag) {
            $hashtags[] = Tag::fromSearchPage($jsonHashtag['hashtag']);
        }
        return $hashtags;
    }

    public function getMediaById($mediaId)
    {
        $mediaLink = Media::getLinkFromId($mediaId);
        return self::getMediaByUrl($mediaLink);
    }

    public function getMediaByUrl($mediaUrl)
    {
        if (filter_var($mediaUrl, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('Malformed media url');
        }
        $response = Request::get(rtrim($mediaUrl, '/') . '/?__a=1', $this->generateHeaders($this->userSession));
        if ($response->code === 404) {
            return [
                'error' => true,
                'error_code' => $response->code,
                'error_reason' => 'Media with given code does not exist or account is private.'
            ];
        }
        if ($response->code !== 200) {
            return [
                'error' => true,
                'error_code' => $response->code,
                'error_reason' => 'Unknown'
            ];
        }
        $mediaArray = json_decode($response->raw_body, true);
        if (!isset($mediaArray['graphql']['shortcode_media'])) {
            return [
                'error' => true,
                'error_code' => $response->code,
                'error_reason' => 'Media with this code does not exist'
            ];
        }
        return Media::fromMediaPage($mediaArray['graphql']['shortcode_media']);
    }

    private function generateHeaders($session)
    {
        $headers = [];
        if ($session) {
            $cookies = '';
            foreach ($session as $key => $value) {
                $cookies .= "$key=$value; ";
            }
            $cookies .= 'ig_pr=2.5; ';
            $cookies .= 'ig_vh=961; ';
            $headers = ['cookie' => $cookies, 'referer' => Endpoints::BASE_URL . '/', 'x-csrftoken' => $session['csrftoken']];
        }

        $headers['user-agent'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/65.0.3325.181 Safari/537.36';
        $headers['x-instagram-gis'] = '4b9db4899ba710aa305d316bc9fd4677';
        $headers['x-requested-with'] = 'XMLHttpRequest';
        return $headers;
    }

    public function getPaginateMedias($username, $maxId = '')
    {
        $hasNextPage = true;
        $medias = [];

        $toReturn = [
            'medias' => $medias,
            'maxId' => $maxId,
            'hasNextPage' => $hasNextPage
        ];

        $response = Request::get(Endpoints::getAccountMediasJsonLink($username, $maxId), $this->generateHeaders($this->userSession));

        if ($response->code !== 200) {
            return [
                'error' => true,
                'error_code' => $response->code,
                'error_reason' => 'Unknown'
            ];
        }

        $arr = json_decode($response->raw_body, true);

        if (!is_array($arr)) {
            return [
                'error' => true,
                'error_code' => $response->code,
                'error_reason' => 'Unknown'
            ];
        }

        if (count($arr['items']) === 0) {
            return $toReturn;
        }

        foreach ($arr['items'] as $mediaArray) {
            $medias[] = Media::fromApi($mediaArray);
        }

        $maxId = $arr['items'][count($arr['items']) - 1]['id'];
        $hasNextPage = $arr['more_available'];

        $toReturn = [
            'medias' => $medias,
            'maxId' => $maxId,
            'hasNextPage' => $hasNextPage
        ];

        return $toReturn;
    }

    public function getMediaCommentsById($mediaId, $count = 10, $maxId = null)
    {
        $code = Media::getCodeFromId($mediaId);
        return self::getMediaCommentsByCode($code, $count, $maxId);
    }

    public function getMediaCommentsByCode($code, $count = 10, $maxId = null)
    {
        $remain = $count;
        $comments = [];
        $index = 0;
        $hasPrevious = true;
        while ($hasPrevious && $index < $count) {
            if ($remain > self::MAX_COMMENTS_PER_REQUEST) {
                $numberOfCommentsToRetreive = self::MAX_COMMENTS_PER_REQUEST;
                $remain -= self::MAX_COMMENTS_PER_REQUEST;
                $index += self::MAX_COMMENTS_PER_REQUEST;
            } else {
                $numberOfCommentsToRetreive = $remain;
                $index += $remain;
                $remain = 0;
            }
            if (!isset($maxId)) {
                $maxId = '';

            }
            $commentsUrl = Endpoints::getCommentsBeforeCommentIdByCode($code, $numberOfCommentsToRetreive, $maxId);
            $response = Request::get($commentsUrl, $this->generateHeaders($this->userSession));
            if ($response->code !== 200) {
                return [
                    'error' => true,
                    'error_code' => $response->code,
                    'error_reason' => 'Unknown'
                ];
            }
            $cookies = self::parseCookies($response->headers['Set-Cookie']);
            $this->userSession['csrftoken'] = $cookies['csrftoken'];
            $jsonResponse = json_decode($response->raw_body, true);
            $nodes = $jsonResponse['data']['shortcode_media']['edge_media_to_comment']['edges'];
            foreach ($nodes as $commentArray) {
                $comments[] = Comment::fromApi($commentArray['node']);
            }
            $hasPrevious = $jsonResponse['data']['shortcode_media']['edge_media_to_comment']['page_info']['has_next_page'];
            $numberOfComments = $jsonResponse['data']['shortcode_media']['edge_media_to_comment']['count'];
            if ($count > $numberOfComments) {
                $count = $numberOfComments;
            }
            if (sizeof($nodes) == 0) {
                return $comments;
            }
            $maxId = $nodes[sizeof($nodes) - 1]['node']['id'];
        }
        return $comments;
    }

    private static function parseCookies($rawCookies)
    {
        if (!is_array($rawCookies)) {
            $rawCookies = [$rawCookies];
        }

        $cookies = [];
        foreach ($rawCookies as $c) {
            $c = explode(';', $c)[0];
            $parts = explode('=', $c);
            if (sizeof($parts) >= 2 && !is_null($parts[1])) {
                $cookies[$parts[0]] = $parts[1];
            }
        }
        return $cookies;
    }

    public function getAccountById($id)
    {
        // Use the follow page to get the account. The follow url will redirect to the home page for the user,
        // which has the username embedded in the url.

        if (!is_numeric($id)) {
            throw new \InvalidArgumentException('User id must be integer or integer wrapped in string');
        }

        $url = Endpoints::getFollowUrl($id);
        $response = Request::get($url, $this->generateHeaders($this->userSession));

        if ($response->code !== 200) {
            return [
                'error' => true,
                'error_code' => $response->code,
                'error_reason' => 'Unknown'
            ];
        }

        $cookies = self::parseCookies($response->headers['Set-Cookie']);
        $this->userSession['csrftoken'] = $cookies['csrftoken'];

        // Get the username from the response url.
        $responseUrl = $response->headers['Location'];
        $urlParts = explode('/', rtrim($responseUrl, '/'));
        $username = end($urlParts);

        return self::getAccount($username);
    }

    // TODO: use new

    public function getMediaByCode($mediaCode)
    {
        return $this->getMediaByUrl(Endpoints::getMediaPageLink($mediaCode));
    }

    public function getMediasByTag($tag, $count = 12, $maxId = '')
    {
        $index = 0;
        $medias = [];
        $mediaIds = [];
        $hasNextPage = true;
        while ($index < $count && $hasNextPage) {
            $response = Request::get(Endpoints::getMediasJsonByTagLink($tag, $maxId), $this->generateHeaders($this->userSession));
            if ($response->code !== 200) {
                return [
                    'error' => true,
                    'error_code' => $response->code,
                    'error_reason' => 'Unknown'
                ];
            }
            $cookies = self::parseCookies($response->headers['Set-Cookie']);
            $this->userSession['csrftoken'] = $cookies['csrftoken'];
            $arr = json_decode($response->raw_body, true);
            if (!is_array($arr)) {
                return [
                    'error' => true,
                    'error_code' => 'DECODING',
                    'error_reason' => 'Response decoding failed. Returned data corrupted or this library outdated.'
                ];
            }
            if (count($arr['tag']['media']['count']) === 0) {
                return [];
            }
            $nodes = $arr['tag']['media']['nodes'];
            foreach ($nodes as $mediaArray) {
                if ($index === $count) {
                    return $medias;
                }
                $media = Media::fromTagPage($mediaArray);
                if (in_array($media->id, $mediaIds)) {
                    return $medias;
                }
                $mediaIds[] = $media->id;
                $medias[] = $media;
                $index++;
            }
            if (count($nodes) == 0) {
                return $medias;
            }
            $maxId = $arr['tag']['media']['page_info']['end_cursor'];
            $hasNextPage = $arr['tag']['media']['page_info']['has_next_page'];
        }
        return $medias;
    }

    public function getPaginateMediasByTag($tag, $maxId = '')
    {
        $hasNextPage = true;
        $medias = [];

        $toReturn = [
            'medias' => $medias,
            'maxId' => $maxId,
            'hasNextPage' => $hasNextPage
        ];

        $response = Request::get(Endpoints::getMediasJsonByTagLink($tag, $maxId), $this->generateHeaders($this->userSession));

        if ($response->code !== 200) {
            return [
                'error' => true,
                'error_code' => $response->code,
                'error_reason' => 'Unknown'
            ];
        }

        $cookies = self::parseCookies($response->headers['Set-Cookie']);
        $this->userSession['csrftoken'] = $cookies['csrftoken'];

        $arr = json_decode($response->raw_body, true);

        if (!is_array($arr)) {
            return [
                'error' => true,
                'error_code' => $response->code,
                'error_reason' => 'Response decoding failed. Returned data corrupted or this library outdated.'
            ];
        }

        if (count($arr['tag']['media']['count']) === 0) {
            return $toReturn;
        }

        $nodes = $arr['tag']['media']['nodes'];

        if (count($nodes) == 0) {
            return $toReturn;
        }

        foreach ($nodes as $mediaArray) {
            $medias[] = Media::fromTagPage($mediaArray);
        }

        $maxId = $arr['tag']['media']['page_info']['end_cursor'];
        $hasNextPage = $arr['tag']['media']['page_info']['has_next_page'];
        $count = $arr['tag']['media']['count'];

        $toReturn = [
            'medias' => $medias,
            'count' => $count,
            'maxId' => $maxId,
            'hasNextPage' => $hasNextPage,
        ];

        return $toReturn;
    }

    public function getTopMediasByTagName($tagName)
    {
        $response = Request::get(Endpoints::getMediasJsonByTagLink($tagName, ''), $this->generateHeaders($this->userSession));
        if ($response->code === 404) {
            return [
                'error' => true,
                'error_code' => $response->code,
                'error_reason' => 'Account with given username does not exist.'
            ];
        }
        if ($response->code !== 200) {
            return [
                'error' => true,
                'error_code' => $response->code,
                'error_reason' => 'Unknown'
            ];
        }
        $cookies = self::parseCookies($response->headers['Set-Cookie']);
        $this->userSession['csrftoken'] = $cookies['csrftoken'];
        $jsonResponse = json_decode($response->raw_body, true);
        $medias = [];
        foreach ($jsonResponse['tag']['top_posts']['nodes'] as $mediaArray) {
            $medias[] = Media::fromTagPage($mediaArray);
        }
        return $medias;
    }

    public function getLocationTopMediasById($facebookLocationId)
    {
        $response = Request::get(Endpoints::getMediasJsonByLocationIdLink($facebookLocationId), $this->generateHeaders($this->userSession));
        if ($response->code === 404) {
            return [
                'error' => true,
                'error_code' => $response->code,
                'error_reason' => 'Location with this id doesn\'t exist'
            ];
        }
        if ($response->code !== 200) {
            return [
                'error' => true,
                'error_code' => $response->code,
                'error_reason' => 'Unknown'
            ];
        }
        $cookies = self::parseCookies($response->headers['Set-Cookie']);
        $this->userSession['csrftoken'] = $cookies['csrftoken'];
        $jsonResponse = json_decode($response->raw_body, true);
        $nodes = $jsonResponse['location']['top_posts']['nodes'];
        $medias = [];
        foreach ($nodes as $mediaArray) {
            $medias[] = Media::fromTagPage($mediaArray);
        }
        return $medias;
    }

    public function getLocationMediasById($facebookLocationId, $quantity = 12, $offset = '')
    {
        $index = 0;
        $medias = [];
        $hasNext = true;
        while ($index < $quantity && $hasNext) {
            $response = Request::get(Endpoints::getMediasJsonByLocationIdLink($facebookLocationId, $offset), $this->generateHeaders($this->userSession));
            if ($response->code !== 200) {
                return [
                    'error' => true,
                    'error_code' => $response->code,
                    'error_reason' => 'Unknown'
                ];
            }
            $cookies = self::parseCookies($response->headers['Set-Cookie']);
            $this->userSession['csrftoken'] = $cookies['csrftoken'];
            $arr = json_decode($response->raw_body, true);
            $nodes = $arr['location']['media']['nodes'];
            foreach ($nodes as $mediaArray) {
                if ($index === $quantity) {
                    return $medias;
                }
                $medias[] = Media::fromTagPage($mediaArray);
                $index++;
            }
            if (count($nodes) == 0) {
                return $medias;
            }
            $hasNext = $arr['location']['media']['page_info']['has_next_page'];
            $offset = $arr['location']['media']['page_info']['end_cursor'];
        }
        return $medias;
    }

    public function getLocationById($facebookLocationId)
    {
        $response = Request::get(Endpoints::getMediasJsonByLocationIdLink($facebookLocationId), $this->generateHeaders($this->userSession));
        if ($response->code === 404) {
            return [
                'error' => true,
                'error_code' => $response->code,
                'error_reason' => 'Location with this id doesn\'t exist'
            ];
        }
        if ($response->code !== 200) {
            return [
                'error' => true,
                'error_code' => $response->code,
                'error_reason' => 'Unknown'
            ];
        }
        $cookies = self::parseCookies($response->headers['Set-Cookie']);
        $this->userSession['csrftoken'] = $cookies['csrftoken'];
        $jsonResponse = json_decode($response->raw_body, true);
        return Location::makeLocation($jsonResponse['location']);
    }

    public function login($force = false)
    {
        if ($this->sessionUsername == null || $this->sessionPassword == null) {
            throw new InstagramAuthException("User credentials not provided");
        }

        $cachedString = self::$instanceCache->getItem($this->sessionUsername);
        $session = $cachedString->get();
        if ($force || !$this->isLoggedIn($session)) {
            $response = Request::get(Endpoints::BASE_URL);
            if ($response->code !== 200) {
                throw new InstagramException('Response code is ' . $response->code . '. Body: ' . $response->body . ' Something went wrong. Please report issue.');
            }
            $cookies = self::parseCookies($response->headers['Set-Cookie']);
            $mid = $cookies['mid'];
            $csrfToken = $cookies['csrftoken'];
            $headers = ['cookie' => "csrftoken=$csrfToken; mid=$mid;", 'referer' => Endpoints::BASE_URL . '/', 'x-csrftoken' => $csrfToken];
            $response = Request::post(Endpoints::LOGIN_URL, $headers, ['username' => $this->sessionUsername, 'password' => $this->sessionPassword]);


            if ($response->code === 403) {
                throw new InstagramEncodedException([
                    'error_code' => 403,
                    'error_reason' => 'Rate Limiting'
                ]);
            }

            if ($response->code !== 200) {
                if ((is_string($response->code) || is_numeric($response->code)) && is_string($response->body)) {
                    throw new InstagramAuthException('Response code is ' . $response->code . '. Body: ' . $response->body . ' Something went wrong. Please report issue.');
                } else {
                    throw new InstagramAuthException('Something went wrong. Please report issue.');
                }
            }

            $cookies = self::parseCookies($response->headers['Set-Cookie']);
            $cookies['mid'] = $mid;
            $cachedString->set($cookies);
            self::$instanceCache->save($cachedString);
            $this->userSession = $cookies;
        } else {
            $this->userSession = $session;
        }
    }

    public function isLoggedIn($session)
    {
        if (is_null($session) || !isset($session['sessionid'])) {
            return false;
        }
        $sessionId = $session['sessionid'];
        $csrfToken = $session['csrftoken'];
        $headers = ['cookie' => "csrftoken=$csrfToken; sessionid=$sessionId;", 'referer' => Endpoints::BASE_URL . '/', 'x-csrftoken' => $csrfToken];
        $response = Request::get(Endpoints::BASE_URL, $headers);
        if ($response->code !== 200) {
            return false;
        }
        $cookies = self::parseCookies($response->headers['Set-Cookie']);
        if (!isset($cookies['ds_user_id'])) {
            return false;
        }
        return true;
    }

    public function saveSession()
    {
        $cachedString = self::$instanceCache->getItem($this->sessionUsername);
        $cachedString->set($this->userSession);
    }
}

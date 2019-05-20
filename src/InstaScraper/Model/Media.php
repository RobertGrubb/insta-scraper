<?php

namespace InstaScraper\Model;

use InstaScraper\Endpoints;

class Media
{
    const TYPE_IMAGE = 'image';
    const TYPE_VIDEO = 'video';
    const TYPE_SIDECAR = 'sidecar';
	const TYPE_CAROUSEL = 'carousel';


    public $id;
    public $shortcode;
    public $code;

    public $createdTime;
    public $type;
    public $link;
    public $imageLowResolutionUrl;
    public $imageThumbnailUrl;
    public $imageStandardResolutionUrl;
    public $imageHighResolutionUrl;
	public $carouselMedia;
    public $caption;
    public $captionIsEdited;
    public $isAd;
    public $videoLowResolutionUrl;
    public $videoStandardResolutionUrl;
    public $videoLowBandwidthUrl;
    public $videoViews;
    public $owner;
    public $ownerId;
    public $likesCount;
    public $locationId;
    public $locationName;
    public $commentsCount;

    function __construct()
    {
    }

    public static function fromApi($mediaArray)
    {
        $instance = new self();
        $instance->id = $mediaArray['id'];
        $instance->type = $mediaArray['type'];
        $instance->createdTime = $mediaArray['created_time'];
        $instance->shortcode = $mediaArray['code'];
        $instance->code = $mediaArray['code'];
        $instance->link = $mediaArray['link'];
        $instance->commentsCount = 0;

        if (isset($mediaArray['comments'])) {
            if (isset($mediaArray['comments']['count'])) {
                $instance->commentsCount = $mediaArray['comments']['count'];
            }
        }

        if (isset($mediaArray['edge_media_to_parent_comment'])) {
            if (isset($mediaArray['edge_media_to_parent_comment']['count'])) {
                $instance->commentsCount = $mediaArray['edge_media_to_parent_comment']['count'];
            }
        }

        $instance->likesCount = $mediaArray['likes']['count'];
        $images = self::getImageUrls($mediaArray['images']['standard_resolution']['url']);
        $instance->imageLowResolutionUrl = $images['low'];
        $instance->imageThumbnailUrl = $images['thumbnail'];
        $instance->imageStandardResolutionUrl = $images['standard'];
        $instance->imageHighResolutionUrl = $images['high'];
		if(isset($mediaArray["carousel_media"])){
			$instance->carouselMedia = array();
			foreach($mediaArray["carousel_media"] as $carouselArray){
				$carouselMedia = new CarouselMedia();
				$carouselMedia->type = $carouselArray['type'];

				if (isset($carouselArray['images'])) {
					$carouselImages = self::getImageUrls($carouselArray['images']['standard_resolution']['url']);
					$carouselMedia->imageLowResolutionUrl = $carouselImages['low'];
					$carouselMedia->imageThumbnailUrl = $carouselImages['thumbnail'];
					$carouselMedia->imageStandardResolutionUrl = $carouselImages['standard'];
					$carouselMedia->imageHighResolutionUrl = $carouselImages['high'];
				}

				if ($carouselMedia->type === 'video') {
					if (isset($mediaArray['video_views'])) {
						$carouselMedia->videoViews = $carouselArray['video_views'];
					}
					if (isset($carouselArray['videos'])) {
						$carouselMedia->videoLowResolutionUrl = $carouselArray['videos']['low_resolution']['url'];
						$carouselMedia->videoStandardResolutionUrl = $carouselArray['videos']['standard_resolution']['url'];
						$carouselMedia->videoLowBandwidthUrl = $carouselArray['videos']['low_bandwidth']['url'];
					}
				}
				array_push($instance->carouselMedia, $carouselMedia);
			}
		}

        if (isset($mediaArray['caption'])) {
            $instance->caption = $mediaArray['caption']['text'];
        }
        if ($instance->type === 'video') {
            if (isset($mediaArray['video_views'])) {
                $instance->videoViews = $mediaArray['video_views'];
            }
            if (isset($mediaArray['videos'])) {
                $instance->videoLowResolutionUrl = $mediaArray['videos']['low_resolution']['url'];
                $instance->videoStandardResolutionUrl = $mediaArray['videos']['standard_resolution']['url'];
                $instance->videoLowBandwidthUrl = $mediaArray['videos']['low_bandwidth']['url'];
            }
        }
        if (isset($mediaArray['location']['id'])) {
            $instance->locationId = $mediaArray['location']['id'];
        }
        if (isset($mediaArray['location']['name'])) {
            $instance->locationName = $mediaArray['location']['name'];
        }
        $instance->owner = Account::fromMediaPage($mediaArray['user']);
        $instance->ownerId = $instance->owner->id;
        return $instance;
    }

    private static function getImageUrls($imageUrl)
    {
        $parts = explode('/', parse_url($imageUrl)['path']);
        $imageName = $parts[sizeof($parts) - 1];
        $urls = [
            'thumbnail' => Endpoints::INSTAGRAM_CDN_URL . 't/s150x150/' . $imageName,
            'low' => Endpoints::INSTAGRAM_CDN_URL . 't/s320x320/' . $imageName,
            'standard' => Endpoints::INSTAGRAM_CDN_URL . 't/s640x640/' . $imageName,
            'high' => Endpoints::INSTAGRAM_CDN_URL . 't/' . $imageName
        ];
        return $urls;
    }

    private static function getImageUrlsFromDisplayResources($displayResources) {
        $urls = [];

        foreach ($displayResources as $image) {
            if ($image['config_width'] == 640) {
                $urls['thumbnail'] = $image['src'];
            }

            if ($image['config_width'] == 640) {
                $urls['low'] = $image['src'];
            }

            if ($image['config_width'] == 750) {
                $urls['standard'] = $image['src'];
            }

            if ($image['config_width'] == 1080) {
                $urls['high'] = $image['src'];
            }
        }

        return $urls;
    }

    public static function fromAccountPage($mediaArray, $owner, $videoData)
    {
        $instance = new self();
        $instance->id = $mediaArray['id'];
        $instance->type = $mediaArray['is_video'] ? 'video' : 'image';
        if (!is_null($videoData)) {
            $instance->videoStandardResolutionUrl = $videoData['video_url'];
            $instance->videoViews = $videoData['video_view_count'];
        }
        if (isset($mediaArray['caption_is_edited'])) {
            $instance->captionIsEdited = $mediaArray['caption_is_edited'];
        }
        if (isset($mediaArray['is_ad'])) {
            $instance->isAd = $mediaArray['is_ad'];
        }
        $instance->shortcode = $mediaArray['shortcode'];
        $instance->code = $mediaArray['shortcode'];
        $instance->link = Endpoints::getMediaPageLink($instance->shortcode);
        $instance->owner = $owner;
        $instance->imageStandardResolutionUrl = isset($mediaArray['thumbnail_resources'][4]) ? $mediaArray['thumbnail_resources'][4]['src'] : null;
        $instance->imageLowResolutionUrl = isset($mediaArray['thumbnail_resources'][3]) ? $mediaArray['thumbnail_resources'][3]['src'] : null;
        $instance->imageHighResolutionUrl = $mediaArray['display_url'];
        $instance->imageThumbnailUrl = $mediaArray['thumbnail_src'];
        $instance->createdTime = $mediaArray['taken_at_timestamp'];

        $instance->commentsCount = 0;

        if (isset($mediaArray['edge_media_to_comment'])) {
            if (isset($mediaArray['edge_media_to_comment']['count'])) {
                $instance->commentsCount = $mediaArray['edge_media_to_comment']['count'];
            }
        }

        if (isset($mediaArray['edge_media_to_parent_comment'])) {
            if (isset($mediaArray['edge_media_to_parent_comment']['count'])) {
                $instance->commentsCount = $mediaArray['edge_media_to_parent_comment']['count'];
            }
        }


        $instance->likesCount = $mediaArray['edge_media_preview_like']['count'];
        $instance->ownerId = $mediaArray['owner']['id'];
        if (isset($mediaArray['edge_media_to_caption']['edges'][0]['node']['text'])) {
            $instance->caption = $mediaArray['edge_media_to_caption']['edges'][0]['node']['text'];
        }
        if (isset($mediaArray['location']['id'])) {
            $instance->locationId = $mediaArray['location']['id'];
        }
        if (isset($mediaArray['location']['name'])) {
            $instance->locationName = $mediaArray['location']['name'];
        }
        return $instance;
    }

    public static function fromMediaPage($mediaArray)
    {
        $instance = new self();
        $instance->id = $mediaArray['id'];
        $instance->type = 'image';
        if ($mediaArray['is_video']) {
            $instance->type = 'video';
            $instance->videoStandardResolutionUrl = $mediaArray['video_url'];
            $instance->videoViews = $mediaArray['video_view_count'];
        }
		if(isset($mediaArray["carousel_media"])){
			$instance->type = 'carousel';
			$instance->carouselMedia = array();
			foreach($mediaArray["carousel_media"] as $carouselArray){
				$carouselMedia = new CarouselMedia();
				$carouselMedia->type = $carouselArray['type'];

				if (isset($carouselArray['images'])) {
					$carouselImages = self::getImageUrls($carouselArray['images']['standard_resolution']['url']);
					$carouselMedia->imageLowResolutionUrl = $carouselImages['low'];
					$carouselMedia->imageThumbnailUrl = $carouselImages['thumbnail'];
					$carouselMedia->imageStandardResolutionUrl = $carouselImages['standard'];
					$carouselMedia->imageHighResolutionUrl = $carouselImages['high'];
				}

				if ($carouselMedia->type === 'video') {
					if (isset($mediaArray['video_views'])) {
						$carouselMedia->videoViews = $carouselArray['video_views'];
					}
					if (isset($carouselArray['videos'])) {
						$carouselMedia->videoLowResolutionUrl = $carouselArray['videos']['low_resolution']['url'];
						$carouselMedia->videoStandardResolutionUrl = $carouselArray['videos']['standard_resolution']['url'];
						$carouselMedia->videoLowBandwidthUrl = $carouselArray['videos']['low_bandwidth']['url'];
					}
				}
				array_push($instance->carouselMedia, $carouselMedia);
			}
		}
        if (isset($mediaArray['caption_is_edited'])) {
            $instance->captionIsEdited = $mediaArray['caption_is_edited'];
        }
        if (isset($mediaArray['is_ad'])) {
            $instance->isAd = $mediaArray['is_ad'];
        }
        $instance->createdTime = $mediaArray['taken_at_timestamp'];
        $instance->shortcode = $mediaArray['shortcode'];
        $instance->code = $mediaArray['shortcode'];
        $instance->link = Endpoints::getMediaPageLink($instance->shortcode);

        $instance->commentsCount = 0;

        if (isset($mediaArray['edge_media_to_comment'])) {
            if (isset($mediaArray['edge_media_to_comment']['count'])) {
                $instance->commentsCount = $mediaArray['edge_media_to_comment']['count'];
            }
        }

        if (isset($mediaArray['edge_media_to_parent_comment'])) {
            if (isset($mediaArray['edge_media_to_parent_comment']['count'])) {
                $instance->commentsCount = $mediaArray['edge_media_to_parent_comment']['count'];
            }
        }

        $instance->likesCount = $mediaArray['edge_media_preview_like']['count'];
        // $images = self::getImageUrls($mediaArray['display_url']);
        $images = self::getImageUrlsFromDisplayResources($mediaArray['display_resources']);
        $instance->imageStandardResolutionUrl = $images['standard'];
        $instance->imageLowResolutionUrl = $images['low'];
        $instance->imageHighResolutionUrl = $images['high'];
        $instance->imageThumbnailUrl = $images['thumbnail'];
        if (isset($mediaArray['edge_media_to_caption']['edges'][0]['node']['text'])) {
            $instance->caption = $mediaArray['edge_media_to_caption']['edges'][0]['node']['text'];
        }
        if (isset($mediaArray['location']['id'])) {
            $instance->locationId = $mediaArray['location']['id'];
        }
        if (isset($mediaArray['location']['name'])) {
            $instance->locationName = $mediaArray['location']['name'];
        }
        $instance->owner = Account::fromMediaPage($mediaArray['owner']);
        return $instance;
    }

    public static function fromTagPage($mediaArray)
    {
        $instance = new self();
        $instance->shortcode = $mediaArray['code'];
        $instance->code = $mediaArray['code'];
        $instance->link = Endpoints::getMediaPageLink($instance->shortcode);

        $instance->commentsCount = 0;

        if (isset($mediaArray['comments'])) {
            if (isset($mediaArray['comments']['count'])) {
                $instance->commentsCount = $mediaArray['comments']['count'];
            }
        }

        if (isset($mediaArray['edge_media_to_parent_comment'])) {
            if (isset($mediaArray['edge_media_to_parent_comment']['count'])) {
                $instance->commentsCount = $mediaArray['edge_media_to_parent_comment']['count'];
            }
        }

        $instance->likesCount = $mediaArray['likes']['count'];
        $instance->ownerId = $mediaArray['owner']['id'];
        if (isset($mediaArray['caption'])) {
            $instance->caption = $mediaArray['caption'];
        }
        $instance->createdTime = $mediaArray['date'];
        $images = self::getImageUrls($mediaArray['display_src']);
        $instance->imageStandardResolutionUrl = $images['standard'];
        $instance->imageLowResolutionUrl = $images['low'];
        $instance->imageHighResolutionUrl = $images['high'];
        $instance->imageThumbnailUrl = $images['thumbnail'];
        $instance->type = 'image';
        if ($mediaArray['is_video']) {
            $instance->type = 'video';
            $instance->videoViews = $mediaArray['video_views'];
        }
		if(isset($mediaArray["carousel_media"])){
			$instance->type = 'carousel';
			$instance->carouselMedia = array();
			foreach($mediaArray["carousel_media"] as $carouselArray){
				$carouselMedia = new CarouselMedia();
				$carouselMedia->type = $carouselArray['type'];

				if (isset($carouselArray['images'])) {
					$carouselImages = self::getImageUrls($carouselArray['images']['standard_resolution']['url']);
					$carouselMedia->imageLowResolutionUrl = $carouselImages['low'];
					$carouselMedia->imageThumbnailUrl = $carouselImages['thumbnail'];
					$carouselMedia->imageStandardResolutionUrl = $carouselImages['standard'];
					$carouselMedia->imageHighResolutionUrl = $carouselImages['high'];
				}

				if ($carouselMedia->type === 'video') {
					if (isset($mediaArray['video_views'])) {
						$carouselMedia->videoViews = $carouselArray['video_views'];
					}
					if (isset($carouselArray['videos'])) {
						$carouselMedia->videoLowResolutionUrl = $carouselArray['videos']['low_resolution']['url'];
						$carouselMedia->videoStandardResolutionUrl = $carouselArray['videos']['standard_resolution']['url'];
						$carouselMedia->videoLowBandwidthUrl = $carouselArray['videos']['low_bandwidth']['url'];
					}
				}
				array_push($instance->carouselMedia, $carouselMedia);
			}
		}
        $instance->id = $mediaArray['id'];
        return $instance;
    }

    public static function getIdFromCode($code)
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
        $id = 0;
        for ($i = 0; $i < strlen($code); $i++) {
            $c = $code[$i];
            $id = $id * 64 + strpos($alphabet, $c);
        }
        return $id;
    }

    public static function getLinkFromId($id)
    {
        $code = Media::getCodeFromId($id);
        return Endpoints::getMediaPageLink($code);
    }

    public static function getCodeFromId($id)
    {
        $parts = explode('_', $id);
        $id = $parts[0];
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
        $code = '';
        while ($id > 0) {
            $remainder = $id % 64;
            $id = ($id - $remainder) / 64;
            $code = $alphabet{$remainder} . $code;
        };
        return $code;
    }
}

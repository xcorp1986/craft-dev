<?php

namespace biglotteryfund\utils;

use Imgix\UrlBuilder;
use League\Uri\Parser;

class Images
{
    private static function _getImgixConfig()
    {
        $imgixDomain = getenv('CUSTOM_IMGIX_DOMAIN');
        $imgixSignKey = getenv('CUSTOM_IMGIX_SIGN_KEY');

        if ($imgixDomain && $imgixSignKey) {
            return [
                'domain' => $imgixDomain,
                'signKey' => $imgixSignKey,
            ];
        }
    }

    public static function imgixUrl($originalUrl, $options = [])
    {
        $imgixConfig = self::_getImgixConfig();

        if ($imgixConfig) {
            $parser = new Parser();
            $parsedUri = $parser($originalUrl);

            $builder = new UrlBuilder($imgixConfig['domain']);
            $builder->setSignKey($imgixConfig['signKey']);
            $builder->setUseHttps(true);
            $builder->setIncludeLibraryParam(false);

            $defaults = array('auto' => "compress,format", 'crop' => 'entropy', 'fit' => 'crop');
            $params = array_replace_recursive($defaults, $options);
            return $builder->createURL($parsedUri['path'], $params);
        } else {
            return $originalUrl;
        }
    }

    public static function extractImage($imageField)
    {
        $image = $imageField ? $imageField->one() : null;
        return $image ?? null;
    }

    public static function extractImageUrl($imageField)
    {
        $image = $imageField ? $imageField->one() : null;
        return $image ? $image->url : null;
    }

    public static function extractHeroImage($imageField)
    {
        $hero = self::extractImage($imageField);
        return $hero ? self::buildHeroImage($hero) : null;
    }

    public static function buildHeroImage($heroEntry)
    {
        $imageSmall = self::imgixUrl(
            $heroEntry->imageSmall->one()->url,
            ['w' => '644', 'fit' => 'fill']
        );

        $imageMedium = self::imgixUrl(
            $heroEntry->imageMedium->one()->url,
            ['w' => '1280', 'fit' => 'fill']
        );

        $imageLarge = self::imgixUrl(
            $heroEntry->imageLarge->one()->url,
            ['w' => '1373', 'fit' => 'fill']
        );

        return [
            'title' => $heroEntry->title,
            'caption' => $heroEntry->caption,
            'default' => $imageMedium,
            'small' => $imageSmall,
            'medium' => $imageMedium,
            'large' => $imageLarge,
            'grantId' => $heroEntry->heroGrantId ?? null,
        ];
    }

    public static function buildHero($heroField)
    {
        if ($heroField && $heroField->one()) {
            $hero = $heroField->one();
            return [
                'image' => $hero->image ? Images::extractHeroImage($hero->image) : null,
                'credit' => $hero->credit ?? null,
            ];
        } else {
            return null;
        }
    }

    public static function extractHeroImageField($heroField)
    {
        if ($heroField) {
            $hero = $heroField->one() ?? null;
            return $hero ? $hero->image->one() : null;
        } else {
            return null;
        }
    }
}

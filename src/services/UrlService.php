<?php

namespace mostlyserious\craftimgixpicture\services;

use craft\elements\Asset;
use mostlyserious\craftimgixpicture\Plugin;
use yii\base\Component;

class UrlService extends Component
{
    /**
     * Generates the URL for a given asset at it's imgix source.
     *
     * @param Asset $asset The asset for which to generate the source URL.
     * @return string The generated source URL.
     */
    public function sourceUrl(Asset $asset): string
    {
        $url = $asset->getUrl();
        $parsedUrl = parse_url($url);
        $path = isset($parsedUrl['path']) ? ltrim($parsedUrl['path'], '/') : '';
        $query = isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '';
        $src = trim(Plugin::getInstance()->settings->getImgixUrl(), '/') . '/' . $path . $query;

        return $src;
    }
}

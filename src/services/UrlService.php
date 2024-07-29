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
        $src = trim(Plugin::getInstance()->settings->getImgixUrl(), '/') . '/' . $asset->path;

        return $src;
    }
}

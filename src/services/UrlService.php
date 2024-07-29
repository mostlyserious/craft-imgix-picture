<?php

namespace mostlyserious\craftimgixpicture\services;

use yii\base\Component;
use craft\elements\Asset;
use mostlyserious\craftimgixpicture\Plugin;

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

<?php

namespace mostlyserious\craftimgixpicture\services;

use craft\helpers\App;
use yii\base\Component;
use craft\elements\Asset;

class UrlService extends Component
{

    public function sourceUrl(Asset $asset): string
    {
        /* TODO: replace with settings... */
        return str_replace(App::env('AWS_CLOUDFRONT_URL'), App::env('IMGIX_URL'), $asset->url);
    }
}

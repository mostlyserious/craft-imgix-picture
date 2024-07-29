<?php

namespace mostlyserious\craftimgixpicture\models;

use Craft;
use craft\base\Model;
use craft\helpers\App;

/**
 * imgix Picture settings
 */
class Settings extends Model
{
    public $imgixUrl = '';
    public $imgixApiKey = '';
    public $altTextHandle = 'alt';
    public $defaultParameters = [
        'auto' => 'format,compress',
        'q' => 35,
        'fit' => 'max'
    ];
    public $useNativeTransforms = false;
    public $fallBackImageSrc = '';

    public function defineRules(): array
    {
        return [
            [['useNativeTransforms'], 'boolean'],
            [['imgixUrl', 'imgixApiKey', 'fallBackImageSrc', 'altTextHandle'], 'string'],
        ];
    }

    public function getImgixUrl(): string
    {
        return strval(App::parseEnv($this->imgixUrl));
    }

    public function getImgixApiKey(): string
    {
        return strval(App::parseEnv($this->imgixApiKey));
    }

    public function getFallBackImageSrc(): string
    {
        return strval(App::parseEnv($this->fallBackImageSrc));
    }
}

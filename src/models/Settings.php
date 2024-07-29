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
    public $defaultParameters = []; /* experimental */
    public $useNativeTransforms = false; /* experimental */
    public $fallBackImageSrc = ''; /* experimental */

    public function defineRules(): array
    {
        return [
            /* [['imgixUrl', 'imgixApiKey'], 'required'], */
            [['useNativeTransforms'], 'boolean'],
            [['imgixUrl', 'imgixApiKey', 'fallBackImageSrc'], 'string'],
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

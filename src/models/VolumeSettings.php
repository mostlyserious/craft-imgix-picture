<?php

namespace mostlyserious\craftimgixpicture\models;

use craft\base\Model;
use craft\helpers\App;

/**
 * imgix Picture Volume Settings
 */
class VolumeSettings extends Model
{
    /**
     * @var string The imgix source URL for this volume
     */
    public $imgixSourceUrl = '';

    /**
     * @var bool Whether to use native transforms for this volume
     */
    public $useNativeTransforms = false;

    /**
     * @var string The handle for the alt text field for this volume
     */
    public $altTextHandle = '';

    /**
     * @var array The default parameters for imgix transformations for this volume
     */
    public $defaultParameters = [];

    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        return [
            [['useNativeTransforms'], 'boolean'],
            [['imgixSourceUrl', 'altTextHandle'], 'string'],
        ];
    }

    /**
     * Get the imgix source URL
     *
     * @return string The imgix source URL
     */
    public function getImgixSourceUrl(): string
    {
        return strval(App::parseEnv($this->imgixSourceUrl));
    }
}

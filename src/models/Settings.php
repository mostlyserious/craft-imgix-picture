<?php

namespace mostlyserious\craftimgixpicture\models;

use craft\base\Model;
use craft\helpers\App;

/**
 * imgix Picture settings
 */
class Settings extends Model
{
    public const DEFAULT_ALT_TEXT_HANDLE = 'alt';

    /**
     * @var string The imgix API key
     */
    public $imgixApiKey = '';

    /**
     * @var array Volume-specific settings keyed by Volume handle
     * Example format:
     * [
     *     'uploads' => [
     *         'imgixSourceUrl' => 'https://uploads.imgix.net',
     *     ],
     * ]
     */
    public $volumes = [];

    /**
     * @var array Cached volume settings models
     */
    private $_volumeSettings = [];

    /**
     * @var array The default parameters for imgix transformations
     */
    public $defaultParameters = [
        'auto' => 'format,compress',
        'q' => 35,
        'fit' => 'max',
    ];

    /**
     * @var string The fallback image source
     */
    public $fallBackImageSrc = '';


    /** Legacy Config Settings */

    /**
     * @var string The imgix URL (legacy setting)
     */
    public $imgixUrl = '';

    /**
     * @var string The handle for the alt text field
     */
    public $altTextHandle = '';

    /**
     * @var bool Whether to use native transforms
     */
    public $useNativeTransforms = false;

    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        return [
            [['imgixApiKey', 'fallBackImageSrc'], 'string'],
            /** Legacy - these should now be set on a per-volume basis */
            [['useNativeTransforms'], 'boolean'],
            [['imgixUrl', 'altTextHandle'], 'string'],
        ];
    }

    /**
     * Get the imgix API key
     *
     * @return string The imgix API key
     */
    public function getImgixApiKey(): string
    {
        return strval(App::parseEnv($this->imgixApiKey));
    }

    /**
     * Get the VolumeSettings Model for a specific Asset Volume
     *
     * @param string $volumeHandle The volume handle
     * @return VolumeSettings|null The volume settings model
     */
    public function getVolumeSettings(string $volumeHandle): ?VolumeSettings
    {
        if (!isset($this->_volumeSettings[$volumeHandle])) {
            if (!isset($this->volumes[$volumeHandle])) {
                return null;
            }

            $settings = $this->volumes[$volumeHandle];

            // Handle legacy 'imgixUrl' property
            if (isset($settings['imgixUrl']) && !isset($settings['imgixSourceUrl'])) {
                $settings['imgixSourceUrl'] = $settings['imgixUrl'];
                unset($settings['imgixUrl']);
            }

            $volumeSettings = new VolumeSettings($settings);
            $this->_volumeSettings[$volumeHandle] = $volumeSettings;
        }

        return $this->_volumeSettings[$volumeHandle];
    }

    /**
     * Get the imgix URL for a specific volume
     *
     * @param string|null $volumeHandle The volume handle
     * @return string The imgix URL
     */
    public function getVolumeSourceUrl(?string $volumeHandle = null): string
    {
        if (
            $volumeHandle !== null &&
            $volumeSettings = $this->getVolumeSettings($volumeHandle)
        ) {
            return $volumeSettings->getImgixSourceUrl();
        }

        /** Fall back to legacy settings */
        return strval(App::parseEnv($this->imgixUrl));
    }

    /**
     * Check if native transforms should be used for a specific volume
     *
     * @param string|null $volumeHandle The volume handle
     * @return bool Whether to use native transforms
     */
    public function getVolumeUsesNative(?string $volumeHandle = null): bool
    {
        if (
            $volumeHandle !== null &&
            $volumeSettings = $this->getVolumeSettings($volumeHandle)
        ) {
            return ($volumeSettings->imgixSourceUrl !== '') ? $volumeSettings->useNativeTransforms : true;
        }

        /** Fall back to legacy settings */
        return ($this->imgixUrl !== '') ? $this->useNativeTransforms : true;
    }

    /**
     * Get the alt text handle for a specific volume
     *
     * @param string|null $volumeHandle The volume handle
     * @return string The alt text handle
     */
    public function getVolumeAltTextHandle(?string $volumeHandle = null): string
    {
        if (
            $volumeHandle !== null &&
            $volumeSettings = $this->getVolumeSettings($volumeHandle)
        ) {
            return $volumeSettings->altTextHandle !== '' ? $volumeSettings->altTextHandle : self::DEFAULT_ALT_TEXT_HANDLE;
        }

        /** Fall back to legacy settings */
        return $this->altTextHandle !== '' ? $this->altTextHandle : self::DEFAULT_ALT_TEXT_HANDLE;
    }

    /**
     * Get the fallback image source
     *
     * @return string The fallback image source
     */
    public function getFallBackImageSrc(): string
    {
        return strval(App::parseEnv($this->fallBackImageSrc));
    }
}

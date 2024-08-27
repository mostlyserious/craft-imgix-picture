<?php

namespace mostlyserious\craftimgixpicture;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\elements\Asset;
use craft\events\ElementEvent;
use craft\events\ReplaceAssetEvent;
use craft\services\Assets;
use craft\services\Elements;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use mostlyserious\craftimgixpicture\models\Settings;
use mostlyserious\craftimgixpicture\services\UrlService;
use mostlyserious\craftimgixpicture\twigextensions\ImgixTwigExtension;
use yii\base\Event;

/**
 * imgix Picture plugin
 *
 * @property Settings $settings
 * @property UrlService $urlService
 * @method static Plugin getInstance()
 * @method Settings getSettings()
 * @author Mostly Serious
 * @copyright Mostly Serious
 * @license https://craftcms.github.io/license/ Craft License
 */
class Plugin extends BasePlugin
{
    public $schemaVersion = '1.0.0';
    public $hasCpSettings = false;

    public string $imgixUrl = '';
    public string $imgixApiKey = '';

    public static function config(): array
    {
        return [
            'components' => [
                'urlService' => [
                    'class' => UrlService::class,
                ],
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->imgixUrl = $this->settings->getImgixUrl();
        $this->imgixApiKey = $this->settings->getImgixApiKey();

        /** Add the picture() Twig function */
        Craft::$app->view->registerTwigExtension(ImgixTwigExtension::instance());

        /** Purge IMGIX for certain Asset events */
        if ($this->imgixApiKey !== '') {
            Event::on(
                Elements::class,
                Elements::EVENT_BEFORE_SAVE_ELEMENT,
                function (ElementEvent $event) {
                    $element = $event->element;
                    $isNewElement = $event->isNew;
                    $isAsset = $element instanceof Asset;
                    if ($isAsset && !$isNewElement) {
                        $this->purgeCache($element);
                    }
                }
            );
            Event::on(
                Asset::class,
                Asset::EVENT_AFTER_DELETE,
                function (Event $event): void {
                    /** @var Asset $asset */
                    $asset = $event->sender;

                    $this->purgeCache($asset);
                }
            );
            Event::on(
                Assets::class,
                Assets::EVENT_BEFORE_REPLACE_ASSET,
                function (ReplaceAssetEvent $event) {
                    $this->purgeCache($event->asset);
                }
            );
        }
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    /**
     * Purges the cache for a given asset.
     *
     * @param Asset $asset The asset to purge the cache for.
     * @throws ClientException If the cache purge request fails.
     * @return void
     */
    protected function purgeCache(Asset $asset): void
    {
        if ($asset->supportsImageEditor) {
            $purgeUrl = $this->urlService->sourceUrl($asset);
            $guzzleClient = new Client();

            try {
                $guzzleClient->post('https://api.imgix.com/api/v1/purge', [
                    'headers' => [
                        'Authorization' => sprintf('Bearer %s', $this->imgixApiKey),
                        'Content-Type' => 'application/vnd.api+json',
                    ],
                    'json' => [
                        'data' => [
                            'attributes' => [
                                'url' => $purgeUrl,
                            ],
                            'type' => 'purges',
                        ],
                    ],
                ]);
            } catch (ClientException $e) {
                $response = $e->getResponse();
                $message = [
                    'Failed to purge IMGIX cache.',
                    'Error Code: ' . $e->getResponse()->getStatusCode(),
                    'Reason: ' . $response->getReasonPhrase(),
                ];
                $message = implode(' ', $message);

                Craft::error($message, __METHOD__);
            }
        }
    }
}

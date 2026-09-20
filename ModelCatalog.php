<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Venice;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Dynamic catalog fetching the model list from the Venice API (`GET /models?type=all`).
 *
 * Venice groups models by `type` (`text`, `embedding`, `image`, `inpaint`, `upscale`, `tts`,
 * `asr`, `music`, `video`). A text model additionally reports feature flags under
 * `model_spec.capabilities`, and a video model the concrete operation under
 * `model_spec.constraints.model_type`. A type or operation this bridge has no capability for
 * leaves the model listed without that capability, so the catalog keeps reflecting the API
 * instead of a hand-maintained list.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    private bool $modelsAreLoaded = false;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
        $this->models = [];
    }

    public function getModel(string $modelName): Model
    {
        $this->preloadRemoteModels();

        return parent::getModel($modelName);
    }

    public function getModels(): array
    {
        $this->preloadRemoteModels();

        return parent::getModels();
    }

    /**
     * Resolves the model id behind a Venice "trait" alias such as `default`,
     * `default_reasoning`, `default_code`, `default_vision`, `most_intelligent`,
     * `most_uncensored`, `function_calling_default` or `fastest`.
     *
     * Returns null when the trait is not exposed by the API for the requested type.
     */
    public function resolveTrait(string $trait, string $type = 'text'): ?string
    {
        $payload = $this->httpClient->request('GET', 'models/traits', [
            'query' => ['type' => $type],
        ])->toArray();

        $traits = \is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        if (!\is_string($traits[$trait] ?? null)) {
            return null;
        }

        return $traits[$trait];
    }

    private function preloadRemoteModels(): void
    {
        if ($this->modelsAreLoaded) {
            return;
        }

        $response = $this->httpClient->request('GET', 'models', [
            'query' => ['type' => 'all'],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException(\sprintf('Cannot connect to the Venice API: "%s".', $e->getMessage()), previous: $e);
        }

        if (200 !== $statusCode) {
            throw new RuntimeException(\sprintf('Cannot retrieve models from the Venice API (Status code: %d).', $statusCode));
        }

        $payload = $response->toArray();

        if (!\is_array($payload['data'] ?? null)) {
            throw new RuntimeException('The Venice API returned a malformed model list.');
        }

        $catalog = [];

        foreach ($payload['data'] as $model) {
            if (!\is_array($model) || !\is_string($model['id'] ?? null)) {
                continue;
            }

            $catalog[$model['id']] = [
                'class' => Venice::class,
                'capabilities' => self::capabilitiesForModel($model),
            ];
        }

        ksort($catalog);

        $this->models = $catalog;
        $this->modelsAreLoaded = true;
    }

    /**
     * @param array<int|string, mixed> $model
     *
     * @return list<Capability>
     */
    private static function capabilitiesForModel(array $model): array
    {
        $modelSpec = \is_array($model['model_spec'] ?? null) ? $model['model_spec'] : [];
        $constraints = \is_array($modelSpec['constraints'] ?? null) ? $modelSpec['constraints'] : [];

        return match ($model['type'] ?? null) {
            'asr' => [
                Capability::SPEECH_TO_TEXT,
                Capability::INPUT_AUDIO,
                Capability::OUTPUT_TEXT,
            ],
            'embedding' => [
                Capability::EMBEDDINGS,
                Capability::INPUT_TEXT,
            ],
            'image' => [
                Capability::TEXT_TO_IMAGE,
                Capability::INPUT_TEXT,
                Capability::OUTPUT_IMAGE,
            ],
            // `inpaint` covers editing a given image, `upscale` enlarging it; both are served by
            // the `image/edit` and `image/upscale` endpoints and selected through the `mode` option.
            'inpaint', 'upscale' => [
                Capability::IMAGE_TO_IMAGE,
                Capability::INPUT_IMAGE,
                Capability::INPUT_TEXT,
                Capability::OUTPUT_IMAGE,
            ],
            'music' => [
                Capability::MUSIC,
                Capability::INPUT_TEXT,
                Capability::OUTPUT_AUDIO,
            ],
            'text' => self::textCapabilities(
                \is_array($modelSpec['capabilities'] ?? null) ? $modelSpec['capabilities'] : [],
            ),
            'tts' => [
                Capability::TEXT_TO_SPEECH,
                Capability::INPUT_TEXT,
                Capability::OUTPUT_AUDIO,
            ],
            'video' => self::videoCapabilities(
                \is_string($constraints['model_type'] ?? null) ? $constraints['model_type'] : null,
            ),
            default => [],
        };
    }

    /**
     * @return list<Capability>
     */
    private static function videoCapabilities(?string $modelType): array
    {
        return match ($modelType) {
            'text-to-video' => [
                Capability::TEXT_TO_VIDEO,
                Capability::INPUT_TEXT,
            ],
            'image-to-video' => [
                Capability::IMAGE_TO_VIDEO,
                Capability::INPUT_IMAGE,
            ],
            'video' => [
                Capability::VIDEO_TO_VIDEO,
                Capability::INPUT_VIDEO,
            ],
            default => [],
        };
    }

    /**
     * @param array<string|int, mixed> $capabilities
     *
     * @return list<Capability>
     */
    private static function textCapabilities(array $capabilities): array
    {
        $resolved = [
            Capability::INPUT_TEXT,
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
        ];

        if (true === ($capabilities['supportsFunctionCalling'] ?? false)) {
            $resolved[] = Capability::TOOL_CALLING;
        }

        if (true === ($capabilities['supportsReasoning'] ?? false)) {
            $resolved[] = Capability::THINKING;
        }

        if (true === ($capabilities['supportsVision'] ?? false)) {
            $resolved[] = Capability::INPUT_IMAGE;
        }

        if (true === ($capabilities['supportsAudioInput'] ?? false)) {
            $resolved[] = Capability::INPUT_AUDIO;
        }

        if (true === ($capabilities['supportsVideoInput'] ?? false)) {
            $resolved[] = Capability::INPUT_VIDEO;
        }

        return $resolved;
    }
}

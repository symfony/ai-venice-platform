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
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class VenicePayload
{
    /**
     * @param array<int|string, mixed>|string $payload
     */
    public function __construct(
        private readonly array|string $payload,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function asVideoGenerationPayload(Model $model, array $options): array
    {
        if (\is_string($this->payload)) {
            throw new InvalidArgumentException('Payload must be an array for video generation.');
        }

        $prompt = $this->payload['text'] ?? $this->payload['prompt'] ?? $options['prompt'] ?? throw new InvalidArgumentException('A valid input or a prompt is required for video generation.');

        return match (true) {
            $model->supports(Capability::TEXT_TO_VIDEO) => [
                ...$options,
                'prompt' => $prompt,
            ],
            $model->supports(Capability::IMAGE_TO_VIDEO) => [
                ...$options,
                'prompt' => $prompt,
                'image_url' => $this->asImage(),
            ],
            $model->supports(Capability::VIDEO_TO_VIDEO) => [
                ...$options,
                'prompt' => $prompt,
                'video_url' => $this->payload['video_url'] ?? throw new InvalidArgumentException('The video must be a valid URL or a data URL (ex: "data:").'),
            ],
            default => throw new InvalidArgumentException('Unsupported video generation.'),
        };
    }

    /**
     * @return non-empty-list<mixed>
     */
    public function asCompletionPayload(): array
    {
        if (\is_string($this->payload)) {
            throw new InvalidArgumentException('Payload must be an array for completion.');
        }

        if (!\array_key_exists('messages', $this->payload)) {
            throw new InvalidArgumentException('Payload must contain "messages" key for completion.');
        }

        $messages = $this->payload['messages'];

        if (!\is_array($messages) || [] === $messages) {
            throw new InvalidArgumentException('Messages must be a non-empty array.');
        }

        return array_values($messages);
    }

    public function asImageGeneration(): string
    {
        if (\is_string($this->payload)) {
            if ('' === $this->payload) {
                throw new InvalidArgumentException('The prompt cannot be empty.');
            }

            return $this->payload;
        }

        if (!\array_key_exists('prompt', $this->payload)) {
            throw new InvalidArgumentException('The "prompt" key is missing.');
        }

        $prompt = $this->payload['prompt'];

        if (!\is_string($prompt) || '' === $prompt) {
            throw new InvalidArgumentException('The "prompt" key must be a non-empty string.');
        }

        return $prompt;
    }

    public function asTextToSpeechPayload(): string
    {
        if (\is_string($this->payload)) {
            if ('' === $this->payload) {
                throw new InvalidArgumentException('The text cannot be empty.');
            }

            return $this->payload;
        }

        if (!\array_key_exists('text', $this->payload)) {
            throw new InvalidArgumentException('The "text" key is missing.');
        }

        $text = $this->payload['text'];

        if (!\is_string($text) || '' === $text) {
            throw new InvalidArgumentException('The "text" key must be a non-empty string.');
        }

        return $text;
    }

    public function asSpeechToTextPayload(): string
    {
        if (!\is_array($this->payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array when using file-based transcription endpoint, given "%s".', \gettype($this->payload)));
        }

        if (!\is_array($this->payload['input_audio'] ?? null)) {
            throw new InvalidArgumentException('Payload must contain an "input_audio" array key for transcription.');
        }

        if (!\is_string($this->payload['input_audio']['path'] ?? null)) {
            throw new InvalidArgumentException('Payload "input_audio" must contain a "path" string key for transcription.');
        }

        return $this->payload['input_audio']['path'];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{image: string, prompt?: string}
     */
    public function asImageEditPayload(array $options = [], bool $requirePrompt = false): array
    {
        if (!\is_array($this->payload)) {
            throw new InvalidArgumentException('Payload must be an array for image edition.');
        }

        $result = ['image' => $this->asImage()];

        if ($requirePrompt) {
            $prompt = $this->payload['prompt'] ?? $options['prompt'] ?? null;

            if (!\is_string($prompt) || '' === $prompt) {
                throw new InvalidArgumentException('Payload must contain a non-empty "prompt" string for image edition.');
            }

            $result['prompt'] = $prompt;
        }

        return $result;
    }

    /**
     * `image/upscale` rejects a data URL and only accepts the bare base64 payload, while
     * `image/edit` accepts a data URL, a bare base64 payload and an HTTP URL alike.
     *
     * @return array{image: string}
     */
    public function asUpscalePayload(): array
    {
        $image = $this->asImage();

        if (1 === preg_match('#^data:[^,;]*;base64,#', $image)) {
            $image = substr($image, strpos($image, ',') + 1);
        }

        return ['image' => $image];
    }

    public function asEmbeddingsPayload(): string
    {
        if (\is_string($this->payload)) {
            if ('' === $this->payload) {
                throw new InvalidArgumentException('The text cannot be empty.');
            }

            return $this->payload;
        }

        if (!\array_key_exists('text', $this->payload)) {
            throw new InvalidArgumentException('The "text" key is missing.');
        }

        $text = $this->payload['text'];

        if (!\is_string($text) || '' === $text) {
            throw new InvalidArgumentException('The "text" key must be a non-empty string.');
        }

        return $text;
    }

    /**
     * The contract normalizes an image to `['type' => 'image_url', 'image_url' => ['url' => '...']]`,
     * while a hand-written payload may pass the image directly as `image` or `image_url`.
     */
    private function asImage(): string
    {
        if (!\is_array($this->payload)) {
            throw new InvalidArgumentException('Payload must be an array to read an image from it.');
        }

        $image = $this->payload['image'] ?? $this->payload['image_url'] ?? null;

        if (\is_array($image)) {
            $image = $image['url'] ?? null;
        }

        if (!\is_string($image) || '' === $image) {
            throw new InvalidArgumentException('Payload must contain a non-empty "image" string (base64, data URL or HTTP URL).');
        }

        return $image;
    }
}

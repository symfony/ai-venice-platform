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

use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class TokenUsageExtractor implements TokenUsageExtractorInterface
{
    public function extract(RawResultInterface $rawResult, array $options = []): ?TokenUsageInterface
    {
        if ($options['stream'] ?? false) {
            return null;
        }

        /** @var ResponseInterface $response */
        $response = $rawResult->getObject();

        $rawUrl = $response->getInfo('url');

        if (!\is_string($rawUrl)) {
            return null;
        }

        // Only the completions and embeddings endpoints report usage; every other one answers with
        // media, so the body must not be read as JSON.
        $isCompletion = str_contains($rawUrl, 'completions');

        if (!$isCompletion && !str_contains($rawUrl, 'embeddings')) {
            return null;
        }

        $content = $rawResult->getData();
        $usage = \is_array($content['usage'] ?? null) ? $content['usage'] : [];

        $promptTokensDetails = \is_array($usage['prompt_tokens_details'] ?? null) ? $usage['prompt_tokens_details'] : [];
        $completionTokensDetails = \is_array($usage['completion_tokens_details'] ?? null) ? $usage['completion_tokens_details'] : [];

        return match (true) {
            $isCompletion => new TokenUsage(
                promptTokens: isset($usage['prompt_tokens']) && \is_int($usage['prompt_tokens']) ? $usage['prompt_tokens'] : null,
                completionTokens: isset($usage['completion_tokens']) && \is_int($usage['completion_tokens']) ? $usage['completion_tokens'] : null,
                thinkingTokens: isset($completionTokensDetails['reasoning_tokens']) && \is_int($completionTokensDetails['reasoning_tokens']) ? $completionTokensDetails['reasoning_tokens'] : null,
                cachedTokens: isset($promptTokensDetails['cached_tokens']) && \is_int($promptTokensDetails['cached_tokens']) ? $promptTokensDetails['cached_tokens'] : null,
                totalTokens: isset($usage['total_tokens']) && \is_int($usage['total_tokens']) ? $usage['total_tokens'] : null,
            ),
            default => new TokenUsage(
                promptTokens: isset($usage['prompt_tokens']) && \is_int($usage['prompt_tokens']) ? $usage['prompt_tokens'] : null,
                totalTokens: isset($usage['total_tokens']) && \is_int($usage['total_tokens']) ? $usage['total_tokens'] : null,
            ),
        };
    }
}

<?php
/**
 * Hyvä Themes - https://hyva.io
 * Copyright © Hyvä Themes. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Hyva\AiOpenAi\Model\Provider;

use Hyva\Ai\Api\ProviderConfigInterface;
use Hyva\Ai\Api\ProviderInterface;
use Hyva\AiOpenAi\Model\Client;
use Magento\Framework\Exception\LocalizedException;

class OpenAiProvider implements ProviderInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly ProviderConfigInterface $providerConfig
    ) {
    }

    public function process(array $data, array $options = []): array
    {
        $allMessages = $data['messages'] ?? [];
        if (empty($allMessages)) {
            throw new LocalizedException(__('Messages are required for OpenAI processing.'));
        }

        $model = $options['model'] ?? $this->providerConfig->getDefaultModel();
        $temperature = $options['temperature'] ?? $this->providerConfig->getDefaultTemperature();
        $maxTokens = $options['max_tokens'] ?? $this->providerConfig->getDefaultMaxTokens();

        $responses = [];

        foreach ($allMessages as $batch) {
            $messages = $batch['messages'] ?? [];

            if (empty($messages)) {
                $responses[] = '';
                continue;
            }

            try {
                $response = $this->client->chatCompletion($messages, $model, $temperature, $maxTokens);
            } catch (\Exception $e) {
                $responses[] = $e->getMessage();
                continue;
            }

            $responses[] = $this->client->extractContent($response);
        }

        return ['responses' => $responses];
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    public function getName(): string
    {
        return $this->providerConfig->getProviderName();
    }
}

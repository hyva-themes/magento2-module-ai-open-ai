<?php
/**
 * Hyvä Themes - https://hyva.io
 * Copyright © Hyvä Themes 2020-present. All rights reserved.
 * This product is licensed per Magento install
 * See https://hyva.io/license
 */
declare(strict_types=1);

namespace Hyva\AiOpenai\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Encryption\EncryptorInterface;
use Psr\Log\LoggerInterface;

class Client
{
    private const OPENAI_API_URL = 'https://api.openai.com/v1/chat/completions';
    private const DEFAULT_MODEL = 'gpt-3.5-turbo';
    private const CONFIG_PATH_API_KEY = 'hyva_ai/openai/api_key';

    public function __construct(
        private ScopeConfigInterface $scopeConfig,
        private Curl $curl,
        private Json $json,
        private EncryptorInterface $encryptor,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Make a chat completion request to OpenAI API
     */
    public function chatCompletion(
        array $messages,
        string $model = self::DEFAULT_MODEL,
        float $temperature = 0.7,
        int $maxTokens = 4000
    ): array {
        $apiKey = $this->getApiKey();
        if (!$apiKey) {
            throw new LocalizedException(__('OpenAI API key is not configured.'));
        }

        $this->curl->addHeader('Authorization', 'Bearer ' . $apiKey);
        $this->curl->addHeader('Content-Type', 'application/json');

        $requestData = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens
        ];

        $this->curl->post(self::OPENAI_API_URL, $this->json->serialize($requestData));

        $responseBody = $this->curl->getBody();
        $httpStatus = $this->curl->getStatus();

        if ($httpStatus !== 200) {
            throw new LocalizedException(__('OpenAI API request failed with status %1: %2', $httpStatus, $responseBody));
        }

        $response = $this->json->unserialize($responseBody);

        if (!isset($response['choices'][0]['message']['content'])) {
            throw new LocalizedException(__('Invalid response from OpenAI API'));
        }

        return $response;
    }

    /**
     * Get the content from the first choice in a chat completion response
     */
    public function extractContent(array $response): string
    {
        return $response['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Check if OpenAI API key is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->getApiKey());
    }

    /**
     * Get OpenAI API key from configuration
     */
    private function getApiKey(): ?string
    {
        $encryptedKey = $this->scopeConfig->getValue(self::CONFIG_PATH_API_KEY);
        if (!$encryptedKey) {
            return null;
        }

        return $this->encryptor->decrypt($encryptedKey);
    }
}

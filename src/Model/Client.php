<?php
/**
 * Hyvä Themes - https://hyva.io
 * Copyright © Hyvä Themes. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Hyva\AiOpenAi\Model;

use Hyva\Ai\Api\ProviderConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Encryption\EncryptorInterface;

class Client
{
    private const DEFAULT_API_URL = 'https://api.openai.com/v1/chat/completions';
    private const CONFIG_PATH_API_KEY = 'hyva_ai/openai/api_key';

    public function __construct(
        private ScopeConfigInterface $scopeConfig,
        private Curl $curl,
        private Json $json,
        private EncryptorInterface $encryptor,
        private ProviderConfigInterface $providerConfig,
        private string $apiUrl = self::DEFAULT_API_URL
    ) {
    }

    /**
     * Make a chat completion request to OpenAI API
     */
    public function chatCompletion(
        array $messages,
        ?string $model = null,
        ?float $temperature = null,
        ?int $maxTokens = null
    ): array {
        $model = $model ?? $this->providerConfig->getDefaultModel();
        $temperature = $temperature ?? $this->providerConfig->getDefaultTemperature();
        $maxTokens = $maxTokens ?? $this->providerConfig->getDefaultMaxTokens();
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

        $this->curl->post($this->apiUrl, $this->json->serialize($requestData));

        $responseBody = $this->curl->getBody();
        $httpStatus = $this->curl->getStatus();

        if ($httpStatus !== 200) {
            $errorMessage = $this->parseErrorMessage($responseBody, $httpStatus);
            throw new LocalizedException(__('OpenAI API request failed: %1', $errorMessage));
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

    /**
     * Parse error message from API response
     */
    private function parseErrorMessage(string $responseBody, int $httpStatus): string
    {
        try {
            $errorData = $this->json->unserialize($responseBody);

            // OpenAI error format: {"error": {"message": "...", "type": "...", "code": "..."}}
            if (isset($errorData['error']['message'])) {
                return $errorData['error']['message'];
            }

            if (isset($errorData['error']) && is_string($errorData['error'])) {
                return $errorData['error'];
            }
        } catch (\Exception $e) {
            // If we can't parse the error, fall through to default message
        }

        return match ($httpStatus) {
            400 => 'Bad request. Please check your input parameters.',
            401 => 'Authentication failed. Please check your API key.',
            403 => 'Access forbidden. Please check your API key permissions.',
            429 => 'Too many requests. Please try again later.',
            500 => 'OpenAI service error. Please try again later.',
            503 => 'Service temporarily unavailable. Please try again later.',
            default => "HTTP {$httpStatus}: Unable to process request",
        };
    }
}

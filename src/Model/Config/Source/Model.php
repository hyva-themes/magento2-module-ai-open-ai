<?php
/**
 * Hyvä Themes - https://hyva.io
 * Copyright © Hyvä Themes 2020-present. All rights reserved.
 * This product is licensed per Magento install
 * See https://hyva.io/license
 */
declare(strict_types=1);

namespace Hyva\AiOpenai\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Model implements OptionSourceInterface
{
    /**
     * Return array of options as value-label pairs
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'gpt-4o-mini', 'label' => 'GPT-4o Mini'],
            ['value' => 'gpt-4o', 'label' => 'GPT-4o'],
            ['value' => 'gpt-4-turbo', 'label' => 'GPT-4 Turbo'],
            ['value' => 'gpt-4', 'label' => 'GPT-4'],
            ['value' => 'gpt-3.5-turbo', 'label' => 'GPT-3.5 Turbo'],
        ];
    }
}

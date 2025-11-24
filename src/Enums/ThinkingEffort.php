<?php

namespace Shawnveltman\LaravelOpenai\Enums;

enum ThinkingEffort: string
{
    case NONE = 'none';
    case MINIMAL = 'minimal';
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';

    /**
     * Convert thinking effort to Claude thinking tokens
     */
    public function toClaudeTokens(): int
    {
        return match ($this) {
            self::NONE => 0,
            self::MINIMAL => 2000,
            self::LOW => 6000,
            self::MEDIUM => 16000,
            self::HIGH => 25000,
        };
    }

    /**
     * Convert thinking effort to OpenAI reasoning effort string
     */
    public function toOpenAIEffort(): string
    {
        return $this->value;
    }
}

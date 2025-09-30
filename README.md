# Laravel OpenAI - Multi-Provider AI API Wrapper

[![Latest Version on Packagist](https://img.shields.io/packagist/v/shawnveltman/laravel-openai.svg?style=flat-square)](https://packagist.org/packages/shawnveltman/laravel-openai)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/shawnveltman/laravel-openai/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/shawnveltman/laravel-openai/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/shawnveltman/laravel-openai/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/shawnveltman/laravel-openai/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/shawnveltman/laravel-openai.svg?style=flat-square)](https://packagist.org/packages/shawnveltman/laravel-openai)

A unified Laravel package for interacting with multiple AI provider APIs (OpenAI, Claude/Anthropic, Gemini, and Mistral). Uses Laravel's HTTP facade for seamless testing and mocking, with built-in cost tracking and support for advanced features like extended thinking and reasoning.

## Features

- 🤖 **Multi-Provider Support**: OpenAI, Claude/Anthropic, Gemini, and Mistral
- 🧪 **Easy Testing**: Built on Laravel's HTTP facade for simple mocking
- 💰 **Cost Tracking**: Automatic logging of API usage and costs
- 🧠 **Extended Thinking**: Support for Claude's extended thinking and OpenAI's reasoning effort
- 🎯 **Unified Interface**: Consistent API across all providers
- 🖼️ **Image Support**: Send images to vision-capable models
- 🔄 **Retry Logic**: Automatic handling of rate limits and token limits

## Installation

Install via Composer:

```bash
composer require shawnveltman/laravel-openai
```

Publish and run migrations for cost tracking:

```bash
php artisan vendor:publish --tag="laravel-openai-migrations"
php artisan migrate
```

Publish the config file:

```bash
php artisan vendor:publish --tag="laravel-openai-config"
```

Add your API keys to `.env`:

```env
OPEN_API_KEY=your-openai-key
ANTHROPIC_API_KEY=your-anthropic-key
GEMINI_API_KEY=your-gemini-key
MISTRAL_API_KEY=your-mistral-key
```

## Usage

### Basic Example

```php
use Shawnveltman\LaravelOpenai\ProviderResponseTestClass;

$ai = new ProviderResponseTestClass();

// Works with any supported model
$response = $ai->get_response_from_provider(
    prompt: 'Explain quantum computing in simple terms',
    model: 'gpt-5-nano', // or 'claude-sonnet-4', 'gemini-pro', etc.
);
```

### Using Thinking Effort

Control the reasoning depth with the `ThinkingEffort` enum:

```php
use Shawnveltman\LaravelOpenai\Enums\ThinkingEffort;

// Minimal thinking for simple tasks (fast, cheaper)
$response = $ai->get_response_from_provider(
    prompt: 'Format this data as JSON',
    model: 'gpt-5-nano',
    thinking_effort: ThinkingEffort::MINIMAL
);

// High thinking for complex reasoning (slower, more thorough)
$response = $ai->get_response_from_provider(
    prompt: 'Solve this complex mathematical proof',
    model: 'claude-sonnet-4',
    thinking_effort: ThinkingEffort::HIGH
);
```

**Thinking Effort Levels:**
- `MINIMAL`: Fast responses, minimal reasoning (0 tokens for Claude, "minimal" for OpenAI)
- `LOW`: Basic reasoning (6,000 tokens / "low")
- `MEDIUM`: Standard reasoning - default (16,000 tokens / "medium")
- `HIGH`: Deep reasoning (25,000 tokens / "high")

### JSON Mode

Force responses in JSON format:

```php
$response = $ai->get_response_from_provider(
    prompt: 'List the top 5 programming languages with descriptions',
    model: 'gpt-5-nano',
    json_mode: true
);
```

### Working with Images

```php
$response = $ai->get_response_from_provider(
    prompt: 'What is in this image?',
    model: 'gpt-4o',
    image_urls: ['https://example.com/image.jpg']
);
```

### System Prompts and Messages

```php
$response = $ai->get_response_from_provider(
    prompt: 'Write a haiku',
    model: 'claude-sonnet-4',
    system_prompt: 'You are a professional poet',
    messages: [
        ['role' => 'user', 'content' => 'Previous message'],
        ['role' => 'assistant', 'content' => 'Previous response'],
    ]
);
```

### Advanced Configuration

```php
$response = $ai->get_response_from_provider(
    prompt: 'Your prompt here',
    model: 'claude-sonnet-4',
    user_id: 123,
    description: 'Marketing copy generation',
    job_uuid: 'unique-job-id',
    temperature: 0.7,
    max_tokens: 4096,
    thinking_effort: ThinkingEffort::MEDIUM
);
```

## Supported Models

### OpenAI
- GPT-5 series: `gpt-5`, `gpt-5-mini`, `gpt-5-nano`
- GPT-4 series: `gpt-4o`, `gpt-4o-mini`, `gpt-4-turbo`
- O-series: `o1`, `o1-mini`, `o3`, `o3-mini`, `o4-mini`

### Anthropic Claude
- `claude-sonnet-4-20250514` (default)
- `claude-opus-4`
- Other Claude models

### Google Gemini
- `gemini-pro`
- `gemini-1.5-pro`

### Mistral
- `mistral-large`
- `mistral-medium`
- `codestral`

## Cost Tracking

The package automatically logs all API calls to the `cost_logs` table:

```php
use Shawnveltman\LaravelOpenai\Models\CostLog;

// Get costs for a user
$costs = CostLog::where('user_id', 123)->get();

// Get costs by model
$costs = CostLog::where('model', 'gpt-5-nano')->sum('input_tokens');
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Shawn Veltman](https://github.com/shawnveltman)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

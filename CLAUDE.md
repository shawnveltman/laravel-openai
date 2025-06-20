# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Laravel OpenAI is a Laravel package that provides a unified wrapper for multiple AI provider APIs (OpenAI, Claude/Anthropic, Gemini, and Mistral) using Laravel's HTTP facade. The package is designed to make testing easy by leveraging Laravel's built-in HTTP mocking capabilities.

## Essential Development Commands

```bash
# Run tests
composer test

# Run tests with coverage
composer test-coverage

# Format code (Laravel Pint)
composer format

# Run static analysis (PHPStan)
composer analyse

# Run all quality checks before committing
composer format && composer analyse && composer test
```

## Architecture

The package uses a trait-based architecture where each AI provider has its own trait:

- `src/OpenAiTrait.php` - OpenAI API integration
- `src/ClaudeTrait.php` - Anthropic Claude API integration  
- `src/GeminiTrait.php` - Google Gemini API integration
- `src/MistralTrait.php` - Mistral AI API integration
- `src/AssistantOpenAiTrait.php` - OpenAI Assistants API support
- `src/ProviderResponseTrait.php` - Common response handling

Each trait is consumed by a corresponding test class (e.g., `TestClass.php`, `ClaudeTestClass.php`) that can be instantiated for testing.

Key models:
- `src/Models/CostLog.php` - Tracks API usage costs with database persistence
- `src/Models/User.php` - User model for authentication

Custom exceptions in `src/Exceptions/` handle provider-specific errors like rate limits.

## Testing Approach

The package uses Pest PHP for testing. All tests are in the `tests/` directory. The test suite includes:
- Unit tests for each provider trait
- Architecture tests to ensure code quality
- HTTP request mocking is enforced (see `tests/Pest.php`)

To run a single test file:
```bash
vendor/bin/pest tests/OpenAiTraitTest.php
```

## Key Design Decisions

1. **HTTP Facade Usage**: All API calls use Laravel's HTTP facade instead of direct Guzzle clients. This enables easy mocking in tests and consistent error handling.

2. **Trait-Based Architecture**: Each provider is implemented as a trait that can be used in any class. This provides flexibility for users to compose their own classes.

3. **Cost Tracking**: Built-in cost logging with database migrations allows tracking API usage costs across all providers.

4. **Unified Response Format**: All providers return responses in a consistent format through `ProviderResponseTrait`.

## Configuration

The package configuration is published to `config/ai_providers.php` and expects these environment variables:
- `OPEN_API_KEY`
- `ANTHROPIC_API_KEY`
- `GEMINI_API_KEY`
- `MISTRAL_API_KEY`

## Database Migrations

The package includes migrations for cost tracking that must be published and run:
```bash
php artisan vendor:publish --tag="laravel-openai-migrations"
php artisan migrate
```

## Development Workflow

1. Make changes to the relevant trait or model
2. Add or update tests in the corresponding test file
3. Run `composer format` to fix code style
4. Run `composer analyse` to check for static analysis issues
5. Run `composer test` to ensure all tests pass
6. Create a pull request

The CI pipeline will automatically run tests on Ubuntu/Windows with PHP 8.1/8.2 and check code style.
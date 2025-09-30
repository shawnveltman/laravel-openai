<?php

namespace Shawnveltman\LaravelOpenai;

use Illuminate\Support\Str;

trait ProviderResponseTrait
{
    use ClaudeTrait;
    use GeminiTrait;
    use MistralTrait;
    use OpenAiTrait;

    public function get_response_from_provider(
        string $prompt,
        string $model,
        ?int $user_id = null,
        ?string $assistant_starter_text = '',
        string $description = '',
        string $job_uuid = '',
        bool $json_mode = true,
        ?float $temperature = 0.7,
        ?string $system_prompt = null,
        array $messages = [],
        array $image_urls = [],
        ?int $thinking_tokens = 16000,
        int $max_tokens = 64000,
    ): mixed {
        if (Str::contains($model, ['gpt', 'o1', 'o3', 'o4', 'chatgpt', 'codex-mini', 'computer-use', 'gpt-image', 'davinci', 'babbage'])) {
            return $this->get_response_from_prompt_and_context(
                prompt: $prompt,
                model: $model,
                json_mode: $json_mode,
                user_id: $user_id,
                temperature: $temperature,
                image_urls: $image_urls,
            );
        }

        if (Str::contains($model, ['mistral', 'codestral', 'mixtral'])) {
            return $this->get_mistral_completion(
                prompt: $prompt,
                user_id: $user_id,
                model: $model,
            );
        }

        if (Str::contains($model, 'gemini')) {
            return $this->get_gemini_response(
                prompt: $prompt,
                model: $model,
                temperature: $temperature,
                user_id: $user_id,
                description: $description,
                job_uuid: $job_uuid,
                json_mode: $json_mode,
                messages: $messages,
            );
        }

        if (Str::contains($model, 'claude')) {
            return $this->get_claude_response(
                prompt: $prompt,
                model: $model,
                max_tokens: $max_tokens,
                temperature: $temperature,
                assistant_starter_text: $assistant_starter_text,
                user_id: $user_id,
                messages: $messages,
                description: $description,
                job_uuid: $job_uuid,
                json_mode: $json_mode,
                system_prompt: $system_prompt,
                image_urls: $image_urls,
                thinking_tokens: $thinking_tokens,
            );
        }

        return null;
    }
}

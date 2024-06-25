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
    ): mixed {
        if (str_contains($model, 'gpt')) {
            return $this->get_response_from_prompt_and_context(
                prompt: $prompt,
                model: $model,
                json_mode: $json_mode,
                user_id: $user_id,
                temperature: $temperature,
            );
        }

        if (Str::contains($model, ['mistral','codestral','mixtral'])) {
            return $this->get_mistral_completion(
                prompt: $prompt,
                user_id: $user_id,
                model: $model,
            );
        }

        if (str_contains($model, 'gemini')) {
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

        return $this->get_claude_response(
            prompt: $prompt,
            model: $model,
            temperature: $temperature,
            assistant_starter_text: $assistant_starter_text,
            user_id: $user_id,
            messages: $messages,
            description: $description,
            job_uuid: $job_uuid,
            json_mode: $json_mode,
            system_prompt: $system_prompt,
        );
    }
}

<?php

namespace Shawnveltman\LaravelOpenai;

trait ProviderResponseTrait
{
    use ClaudeTrait;
    use OpenAiTrait;
    use MistralTrait;
    use GeminiTrait;

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
        if (str_contains($model, 'gpt'))
        {
            return $this->get_response_from_prompt_and_context(
                prompt: $prompt,
                model: $model,
                json_mode: $json_mode,
                user_id: $user_id,
                temperature: $temperature,
            );
        }

        if (str_contains($model, 'mistral'))
        {
            return $this->get_mistral_completion(
                prompt: $prompt,
                user_id: $user_id,
                model: $model,
            );
        }

        if (str_contains($model, 'gemini'))
        {
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

<?php

namespace Shawnveltman\LaravelOpenai;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Shawnveltman\LaravelOpenai\Exceptions\ClaudeRateLimitException;
use Shawnveltman\LaravelOpenai\Models\CostLog;

trait ClaudeTrait
{
    public string $logged_formatted_prompt = '';

    public array $logged_response = [];

    /**
     * @throws ClaudeRateLimitException
     */
    public function get_claude_response(
        string $prompt,
        string $model = 'claude-3-opus-20240229',
        int $max_tokens = 4096,
        int $temperature = 1,
        float $top_p = 0.7,
        int $top_k = 5,
        int $iteration = 1,
        int $timeout_seconds = 600,
        ?string $assistant_starter_text = null,
        ?int $user_id = null,
        array $messages = [],
        ?string $description = '',
        ?string $job_uuid = null,
        bool $json_mode = false,
        int $max_token_retry_attempts = 2,
        ?string $system_prompt = null,
    ): mixed {
        if (count($messages) < 1) {
            $messages = [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ];
        } else {
            $messages[] = [
                'role' => 'user',
                'content' => $prompt,
            ];
        }

        if ($json_mode && ! $assistant_starter_text) {
            $assistant_starter_text = '{';
        }

        if ($assistant_starter_text) {
            $messages[] = [
                'role' => 'assistant',
                'content' => $assistant_starter_text,
            ];
        }

        $retry_count = 0;
        while ($retry_count < $max_token_retry_attempts) {
            $parameters = [
                'metadata' => [
                    'user_id' => (string) ($user_id),
                ],
                'max_tokens' => $max_tokens,
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
                'top_p' => $top_p,
                'top_k' => $top_k,
            ];

            if ($system_prompt) {
                $parameters['system'] = $system_prompt;
            }

            $response_object = Http::timeout($timeout_seconds)
                ->withHeaders([
                    'accept' => 'application/json',
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                    'x-api-key' => config('ai_providers.anthropic_key'),
                ])
                ->post('https://api.anthropic.com/v1/messages', $parameters);
            $response = $response_object->json();

            if (! $response_object->ok() && collect([429, 529])->contains($response_object->status())) {
                throw new ClaudeRateLimitException;
            }

            $this->logged_response = $response;

            $this->logged_formatted_prompt = $prompt;
            $this->claude_attempt_log_prompt(raw_response: $response, model: $model, description: $description, job_uuid: $job_uuid, user_id: $user_id);

            $collected_response = collect($response['content']);
            $last_response = $collected_response->last();
            $return_response = $last_response['text'];

            if ($response['stop_reason'] !== 'max_tokens') {
                return $assistant_starter_text.$return_response;
            }

            // Concatenate the assistant's response with the previous assistant message
            $messages[1]['content'] .= $return_response;
            $assistant_starter_text = $messages[1]['content'];

            $retry_count++;
        }

        return $assistant_starter_text.$return_response;
    }

    public function claude_log_prompt(mixed $raw_response, string $model, ?string $description = null, ?string $job_uuid = null, ?int $user_id = null): void
    {
        CostLog::create([
            'user_id' => $user_id,
            'prompt_identifier' => $raw_response['id'] ?? null,
            'model' => $model,
            'service' => 'Anthropic',
            'input_tokens' => $raw_response['usage']['input_tokens'] ?? 0,
            'output_tokens' => $raw_response['usage']['output_tokens'] ?? 0,
            'description' => $description,
            'job_uuid' => $job_uuid,
        ]);
    }

    private function claude_attempt_log_prompt($raw_response, string $model, ?string $description = null, ?string $job_uuid = null, ?int $user_id = null): void
    {
        try {
            $this->claude_log_prompt(raw_response: $raw_response, model: $model, description: $description, job_uuid: $job_uuid, user_id: $user_id);
        } catch (Exception $e) {
            Log::error('CostLog creation failed: '.$e->getMessage());
        }
    }
}

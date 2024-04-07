<?php

namespace Shawnveltman\LaravelOpenai;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Shawnveltman\LaravelOpenai\Models\CostLog;

trait ClaudeTrait
{
    public string $logged_formatted_prompt = '';

    public array $logged_response = [];

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
        ?int    $user_id = null,
        array   $messages = [],
        ?string $description = '',
        ?string $job_uuid = null,
    ): mixed
    {
        $formatted_prompt = $prompt;
        if ($iteration <= 1) {
            $messages = [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ];
        }

        if ($assistant_starter_text) {
            $messages[] = [
                'role' => 'assistant',
                'content' => $assistant_starter_text,
            ];
        }

        $response = Http::timeout($timeout_seconds)
            ->withHeaders([
                'accept' => 'application/json',
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
                'x-api-key' => config('ai_providers.anthropic_key'),
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'metadata'    => [
                    'user_id' => (string)($user_id),
                ],
                'max_tokens'  => $max_tokens,
                'model'       => $model,
                'messages'    => $messages,
                'temperature' => $temperature,
                'top_p'       => $top_p,
                'top_k'       => $top_k,
            ])->json();

        $this->logged_response = $response;

        $this->logged_formatted_prompt = $formatted_prompt;
        $this->claude_attempt_log_prompt($user_id, $response, $model, $description, $job_uuid);

        $collected_response = collect($response['content']);
        $last_response      = $collected_response->last();
        $return_response    = $last_response['text'];

        return $assistant_starter_text.$return_response;
    }

    public function claude_log_prompt(?int $user_id, mixed $raw_response, string $model, ?string $description = null, ?string $job_uuid = null): void
    {
        if ($user_id) {
            CostLog::create([
                'user_id' => $user_id,
                'prompt_identifier' => $raw_response['id'] ?? null,
                'model'             => $model,
                'service'           => 'Anthropic',
                'input_tokens'      => $raw_response['usage']['input_tokens'] ?? 0,
                'output_tokens'     => $raw_response['usage']['output_tokens'] ?? 0,
                'description'       => $description,
            ]);
        }
    }

    private function claude_attempt_log_prompt(?int $user_id, $raw_response, string $model, ?string $description = null, string $job_uuid = null): void
    {
        try
        {
            $this->claude_log_prompt($user_id, $raw_response, $model, $description, $job_uuid);
        } catch (Exception $e)
        {
            Log::error('CostLog creation failed: ' . $e->getMessage());
        }
    }
}

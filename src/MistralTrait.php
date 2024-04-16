<?php

namespace Shawnveltman\LaravelOpenai;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Shawnveltman\LaravelOpenai\Models\CostLog;

trait MistralTrait
{
    public function get_mistral_completion(
        string $prompt,
        ?int $user_id = null,
        string $context = 'You are a helpful assistant',
        string $model = 'mistral-small-latest')
    {
        $messages = $this->generate_chat_array_from_input_prompt($prompt);
        $final_messages = collect([
            ['role' => 'system', 'content' => $context],
        ])
            ->concat($messages)
            ->toArray();

        $instructions_array = [
            'model' => $model,
            'messages' => $final_messages,
        ];

        $response = Http::acceptJson()
            ->withToken(config('ai_providers.mistral_key'))
            ->post('https://api.mistral.ai/v1/chat/completions', $instructions_array);

        $response_json = $response->json();
        if ($response->ok()) {
            $this->attempt_log_prompt($user_id, $response_json, $model);

            return $response_json['choices'][0]['message']['content'];
        }

        return [];
    }

    public function generate_chat_array_from_input_prompt(string $message): array
    {
        return [
            [
                'role' => 'user',
                'content' => $message,
            ],
        ];
    }

    public function log_prompt(?int $user_id, mixed $raw_response, string $model): void
    {
        if ($user_id) {
            CostLog::create([
                'user_id' => $user_id,
                'prompt_identifier' => $raw_response['id'],
                'model' => $model,
                'service' => 'Mistral',
                'input_tokens' => $raw_response['usage']['prompt_tokens'] ?? 0,
                'output_tokens' => $raw_response['usage']['completion_tokens'] ?? 0,
            ]);
        }
    }

    private function attempt_log_prompt(?int $user_id, $raw_response, string $model): void
    {
        try {
            $this->log_prompt($user_id, $raw_response, $model);
        } catch (Exception $e) {
            Log::error('CostLog creation failed: '.$e->getMessage());
        }
    }
}

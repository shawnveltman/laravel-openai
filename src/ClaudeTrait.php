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
        string $model = 'claude-sonnet-4-20250514',
        int $max_tokens = 4096,
        ?float $temperature = 0.7,
        ?float $top_p = null,
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
        array $image_urls = [],
        ?int $thinking_tokens = 16000,
    ): mixed {

        if ($temperature && $top_p) {
            $top_p = null;
        }

        if ($top_p) {
            $temperature = null;
        }

        if (count($messages) < 1) {
            $content = [];

            // Add images if they exist
            foreach ($image_urls as $url) {
                try {
                    $imageData = base64_encode(file_get_contents($url));
                    $extension = pathinfo($url, PATHINFO_EXTENSION);
                    $mimeType = match (strtolower($extension)) {
                        'jpg', 'jpeg' => 'image/jpeg',
                        'png' => 'image/png',
                        'gif' => 'image/gif',
                        'webp' => 'image/webp',
                        default => 'image/jpeg',
                    };

                    $content[] = [
                        'type' => 'image',
                        'source' => [
                            'type' => 'base64',
                            'media_type' => $mimeType,
                            'data' => $imageData,
                        ],
                    ];
                } catch (Exception $e) {
                    Log::error('Failed to process image: '.$e->getMessage());
                }
            }

            // Add the text prompt
            $content[] = [
                'type' => 'text',
                'text' => $prompt,
            ];

            $messages = [
                [
                    'role' => 'user',
                    'content' => $content,
                ],
            ];
        } else {
            $messages[] = [
                'role' => 'user',
                'content' => $prompt,
            ];
        }

        // Handle JSON mode differently based on whether extended thinking is enabled
        $use_extended_thinking = $thinking_tokens && $thinking_tokens >= 1024;

        if ($json_mode && ! $assistant_starter_text) {
            if ($use_extended_thinking) {
                // When using extended thinking, we can't prefill assistant content
                // so we add JSON instruction to system prompt instead
                $json_instruction = "\n\nIMPORTANT: You must respond ONLY with valid JSON starting with '{'. Do not include any text before or after the JSON object.";
                $system_prompt = ($system_prompt ?? '').$json_instruction;
            } else {
                // Traditional approach: prefill with '{'
                $assistant_starter_text = '{';
            }
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
                'top_k' => $top_k,
            ];

            if ($temperature) {
                $parameters['temperature'] = $temperature;
            }

            if ($top_p) {
                $parameters['top_p'] = $top_p;
            }

            if ($system_prompt) {
                $parameters['system'] = $system_prompt;
            }

            // Extended thinking cannot be used with assistant_starter_text
            // as assistant messages must start with a thinking block when enabled
            if ($use_extended_thinking && ! $assistant_starter_text) {
                $parameters['thinking'] = [
                    'type' => 'enabled',
                    'budget_tokens' => $thinking_tokens,
                ];
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

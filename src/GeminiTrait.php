<?php

namespace Shawnveltman\LaravelOpenai;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Shawnveltman\LaravelOpenai\Models\CostLog;

trait GeminiTrait
{
    public string $logged_formatted_prompt = '';

    public array $logged_response = [];

    public function get_gemini_response(
        string  $prompt,
        string  $model = 'gemini-1.5-flash-latest',
        int     $max_tokens = 4096,
        float   $temperature = 1,
        int     $timeout_seconds = 600,
        ?int    $user_id = null,
        ?string $description = '',
        ?string $job_uuid = null,
        bool    $json_mode = false,
        array   $messages = [],
    ): mixed
    {
        $formatted_prompt = $prompt;
        $api_key          = config('ai_providers.gemini_key');
        $url              = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";
        $generate_config  = [
            'temperature'     => $temperature,
            'maxOutputTokens' => $max_tokens,
        ];

        if (count($messages) < 1)
        {
            $messages = [
                [
                    'parts' => [
                        [
                            'text' => $prompt,
                        ],
                    ],
                    'role'  => 'user',
                ],
            ];
        } else
        {
            $messages[] = [
                'parts' => [
                    [
                        'text' => $prompt,
                    ],
                ],
                'role'  => 'user',
            ];
        }

        if ($json_mode)
        {
            $generate_config['response_mime_type'] = 'application/json';
        }

        $body = [
            'contents'         => $messages,
            'generationConfig' => $generate_config,
        ];

        $response_object = Http::timeout($timeout_seconds)
            ->withBody(json_encode($body), 'application/json')
            ->post($url);

        $response_json = $response_object->json();

        if (!$response_object->ok() && collect([429, 529])->contains($response_object->status()))
        {
            throw new Exception('Rate limit exceeded');
        }

        $this->logged_response         = $response_json;
        $this->logged_formatted_prompt = $formatted_prompt;
        $this->gemini_log_prompt(raw_response: $response_json, model: $model, description: $description, job_uuid: $job_uuid, user_id: $user_id);

        $collected_response = collect($response_json['candidates']);
        $last_response      = $collected_response->last();

        return $last_response['content']['parts'][0]['text'];
    }

    public function gemini_log_prompt(mixed $raw_response, string $model, ?string $description = null, ?string $job_uuid = null, ?int $user_id = null): void
    {
        CostLog::create([
            'user_id'           => $user_id,
            'prompt_identifier' => null,
            'model'             => $model,
            'service'           => 'Google Gemini',
            'input_tokens'      => $raw_response['usageMetadata']['promptTokenCount'] ?? 0,
            'output_tokens'     => $raw_response['usageMetadata']['candidatesTokenCount'] ?? 0,
            'description'       => $description,
            'job_uuid'          => $job_uuid,
        ]);
    }

    private function gemini_attempt_log_prompt($raw_response, string $model, ?string $description = null, ?string $job_uuid = null, ?int $user_id = null): void
    {
        try
        {
            $this->gemini_log_prompt(raw_response: $raw_response, model: $model, description: $description, job_uuid: $job_uuid, user_id: $user_id);
        } catch (Exception $e)
        {
            Log::error('CostLog creation failed: ' . $e->getMessage());
        }
    }
}

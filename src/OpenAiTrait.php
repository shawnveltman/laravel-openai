<?php

namespace Shawnveltman\LaravelOpenai;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Rajentrivedi\TokenizerX\TokenizerX;
use Shawnveltman\LaravelOpenai\Exceptions\GeneralOpenAiException;
use Shawnveltman\LaravelOpenai\Exceptions\OpenAi500ErrorException;
use Shawnveltman\LaravelOpenai\Exceptions\OpenAiRateLimitExceededException;
use Shawnveltman\LaravelOpenai\Models\CostLog;

trait OpenAiTrait
{
    public function get_openai_chat_completion(
        array $messages,
        float $temperature = 0.7,
        int $max_tokens = 840,
        float $top_p = 1,
        float $frequency_penalty = 0,
        float $presence_penalty = 0,
        string $model = 'gpt-3.5-turbo',
        string $role_context = 'You are a helpful assistant.',
        int $timeout_in_seconds = 600,
        ?array $function_definition = null,
        bool $json_mode = false,
        ?string $user_identifier = null,
    ) {
        $final_messages = collect([
            ['role' => 'system', 'content' => $role_context],
        ])
            ->concat($messages)
            ->toArray();

        $instructions_array = [
            'model' => $model,
            'messages' => $final_messages,
            'temperature' => $temperature,
            'max_tokens' => $max_tokens,
            'top_p' => $top_p,
            'frequency_penalty' => $frequency_penalty,
            'presence_penalty' => $presence_penalty,
        ];

        if ($json_mode) {
            $instructions_array['response_format'] = ['type' => 'json_object'];
        }

        if ($function_definition) {
            $instructions_array['functions'] = $function_definition;
        }

        if ($user_identifier) {
            $instructions_array['user'] = $user_identifier;
        }

        $response = Http::withToken(config('ai_providers.open_ai_key'))
            ->timeout($timeout_in_seconds)
            ->post(
                url: 'https://api.openai.com/v1/chat/completions',
                data: $instructions_array
            );

        if ($response->ok()) {
            return $response->json();
        }

        if ($response->status() === 429) {
            throw new OpenAiRateLimitExceededException('OpenAI API rate limit exceeded.');
        }

        if ($response->status() === 500) {
            throw new OpenAi500ErrorException('OpenAI API returned a 500 error.');
        }

        throw new GeneralOpenAiException('OpenAI API returned an error that was neither 429 nor 500.');
    }

    public function get_openai_moderation($prompt)
    {
        return Http::withToken(config('ai_providers.open_ai_key'))
            ->post(
                url: 'https://api.openai.com/v1/moderations',
                data: [
                    'input' => $prompt,
                ]
            )->json();
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

    public function get_response_from_prompt_and_context(
        string $prompt,
        string $context = 'You are a helpful assistant',
        string $model = 'gpt-3.5-turbo',
        ?array $function_definition = null,
        bool $json_mode = false,
        ?int $user_id = null,
        ?float $temperature = 0.7,
    ): ?string {
        $messages = $this->generate_chat_array_from_input_prompt($prompt);
        $approximateinput_tokens = TokenizerX::count($prompt);

        $gpt4_models = collect(['gpt-4-1106-preview', 'gpt-4-turbo-preview', 'gpt-4-0125-preview']);
        if ($gpt4_models->contains($model)) {
            $approximate_output_max_tokens = 4096;
        } else {
            $approximate_output_max_tokens = min(4096, $this->get_max_output_tokens($model) - $approximateinput_tokens);
        }

        if ($approximate_output_max_tokens < 0) {
            return null;
        }

        $user_identifier = null;
        if ($user_id) {
            $user_identifier = 'user-'.$user_id;
        }

        $raw_response = $this->get_openai_chat_completion(
            messages: $messages,
            temperature: $temperature,
            max_tokens: $approximate_output_max_tokens,
            model: $model,
            role_context: $context,
            timeout_in_seconds: 1800,
            function_definition: $function_definition,
            json_mode: $json_mode,
            user_identifier: $user_identifier,
        );

        $summary_response_text = $raw_response['choices'][0]['message']['content'] ?? null;

        if (! $summary_response_text) {
            return null;
        }

        $this->attempt_log_prompt($user_id, $raw_response, $model);

        return $summary_response_text;
    }

    public function get_max_output_tokens(string $model = 'gpt-3.5-turbo'): int
    {
        if (Str::contains($model, 'gpt-4')) {
            return 8000;
        }

        if (Str::contains($model, ['gpt-3.5-turbo-16k','gpt-3.5-turbo'])) {
            return 16000;
        }

        return 3900;
    }

    public function get_whisper_transcription(
        ?string $filepath = null,
        ?string $filename = 'my_file.wav',
        int $timeout_seconds = 6000,
        string $model = 'whisper-1',
    ): mixed {
        $form = Http::asMultipart()
            ->timeout($timeout_seconds)
            ->withToken(config('ai_providers.open_ai_key'))
            ->attach(
                'file',                           // name of the input field
                Storage::get($filepath),          // file content
                $filename                         // file name
            );

        $transcription_response = $form
            ->post('https://api.openai.com/v1/audio/transcriptions', ['model' => $model])
            ->json();

        return $transcription_response['text'] ?? '';
    }

    public function validate_and_fix_json($json_string): ?string
    {
        // Try to decode the JSON string as is
        $decoded = json_decode($json_string);

        if (json_last_error() === JSON_ERROR_NONE) {
            // JSON is valid, no need to fix
            return $json_string;
        }

        // Fix extra commas at the end of arrays
        $fixed_json_string = preg_replace('/,(\s*})/', '}', $json_string);
        $fixed_json_string = preg_replace('/,(\s*])/', ']', $fixed_json_string);

        // Try to decode the fixed JSON string
        $decoded = json_decode($fixed_json_string);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $fixed_json_string;
        }

        return null;
    }

    public function get_corrected_json_from_response(?string $response): ?array
    {
        if (! $response) {
            return null;
        }

        $new_stripped_response = $this->validate_and_fix_json($response);
        if ($new_stripped_response === $response) {
            return json_decode($response, true);
        }

        if ($new_stripped_response !== null) {
            return json_decode($this->clean_json_string($new_stripped_response), true);
        }

        $stripped_response = $this->clean_json_string($response);

        return json_decode($stripped_response, true);
    }

    public function clean_json_string($str): ?string
    {
        // Find the starting position of a JSON object or array, allowing spaces or newlines
        if (preg_match('/\s*\[\s*{/', $str, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][1];
        } elseif (preg_match('/\s*{/', $str, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][1];
        } else {
            // No JSON object or array found
            return null;
        }

        // Extract and return the JSON string starting from the found position
        return trim(substr($str, $pos));
    }

    public function get_ada_embedding(string $contents)
    {
        $input_values = json_encode([
            'input' => $contents,
            'model' => 'text-embedding-ada-002',
        ]);

        $embeddings = Http::withBody($input_values)
            ->withToken(config('ai_providers.open_ai_key'))
            ->post('https://api.openai.com/v1/embeddings')
            ->json();

        return $embeddings['data'][0]['embedding'];
    }

    public function log_prompt(?int $user_id, mixed $raw_response, string $model): void
    {
        if ($user_id) {
            CostLog::create([
                'user_id' => $user_id,
                'prompt_identifier' => $raw_response['id'],
                'model' => $model,
                'service' => 'OpenAI',
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

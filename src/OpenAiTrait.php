<?php

namespace Shawnveltman\LaravelOpenai;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Shawnveltman\LaravelOpenai\Enums\ThinkingEffort;
use Shawnveltman\LaravelOpenai\Exceptions\GeneralOpenAiException;
use Shawnveltman\LaravelOpenai\Exceptions\OpenAi500ErrorException;
use Shawnveltman\LaravelOpenai\Exceptions\OpenAiRateLimitExceededException;
use Shawnveltman\LaravelOpenai\Models\CostLog;

trait OpenAiTrait
{
    public ?string $latest_stop_reason = null;

    public function get_openai_chat_completion(
        array $messages,
        float $temperature = 0.7,
        int $max_tokens = 840,
        float $top_p = 1,
        float $frequency_penalty = 0,
        float $presence_penalty = 0,
        string $model = 'gpt-5-nano',
        string $role_context = 'You are a helpful assistant.',
        int $timeout_in_seconds = 600,
        ?array $function_definition = null,
        bool $json_mode = false,
        ?string $user_identifier = null,
        array $image_urls = [],
        ?ThinkingEffort $thinking_effort = null,
    ) {
        $is_o1_model = Str::startsWith($model, 'o1') ||
                       Str::startsWith($model, 'o3') ||
                       Str::startsWith($model, 'o4');

        $is_gpt5_model = Str::startsWith($model, 'gpt-5');

        // GPT-5 and o-series models require temperature = 1
        if ($is_gpt5_model || $is_o1_model) {
            $temperature = 1;
        }

        $final_messages = collect([]);
        if (! $is_o1_model) {
            $final_messages->push(['role' => 'system', 'content' => $role_context]);
        }

        // Process messages to include images if they exist
        foreach ($messages as $message) {
            if ($message['role'] === 'user' && count($image_urls) > 0) {
                $content = [];

                // Add the text content if it exists
                if (is_string($message['content'])) {
                    $content[] = [
                        'type' => 'text',
                        'text' => $message['content'],
                    ];
                }

                // Add images
                foreach ($image_urls as $url) {
                    try {
                        if (Str::startsWith($url, 'data:image')) {
                            // If it's already a base64 data URL, use it directly
                            $imageUrl = $url;
                        } else {
                            // Download and encode the image
                            $imageData = base64_encode(file_get_contents($url));
                            $extension = pathinfo($url, PATHINFO_EXTENSION);
                            $mimeType = match (strtolower($extension)) {
                                'jpg', 'jpeg' => 'image/jpeg',
                                'png' => 'image/png',
                                'gif' => 'image/gif',
                                'webp' => 'image/webp',
                                default => 'image/jpeg',
                            };
                            $imageUrl = "data:{$mimeType};base64,{$imageData}";
                        }

                        $content[] = [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => $imageUrl,
                                'detail' => 'auto',
                            ],
                        ];
                    } catch (Exception $e) {
                        Log::error('Failed to process image: '.$e->getMessage());
                    }
                }

                $message['content'] = $content;
            }
            $final_messages->push($message);
        }

        $instructions_array = [
            'model' => $model,
            'messages' => $final_messages->toArray(),
        ];

        if (! $is_o1_model) {
            $instructions_array['top_p'] = $top_p;
            $instructions_array['frequency_penalty'] = $frequency_penalty;
            $instructions_array['presence_penalty'] = $presence_penalty;
        }

        // Temperature is required for both o-series and GPT-5 models (must be 1)
        // For other models, only include if specified
        if ($is_o1_model || $is_gpt5_model) {
            $instructions_array['temperature'] = 1;
        } elseif ($temperature !== null) {
            $instructions_array['temperature'] = $temperature;
        }

        if ($json_mode && ! $is_o1_model) {
            $instructions_array['response_format'] = ['type' => 'json_object'];
        }

        if ($function_definition) {
            $instructions_array['functions'] = $function_definition;
        }

        if ($user_identifier) {
            $instructions_array['user'] = $user_identifier;
        }

        // Add reasoning_effort for GPT-5 and o-series models
        if ($thinking_effort && (Str::startsWith($model, 'gpt-5') || $is_o1_model)) {
            $instructions_array['reasoning_effort'] = $thinking_effort->toOpenAIEffort();
        }

        $response = Http::withToken(config('ai_providers.open_ai_key'))
            ->timeout($timeout_in_seconds)
            ->post(
                url: 'https://api.openai.com/v1/chat/completions',
                data: $instructions_array
            );

        if ($response->ok()) {
            $response_json = $response->json();
            if ($response['choices'][0]['finish_reason'] === 'length') {
                $this->latest_stop_reason = 'length';

                return $response_json;
            }

            return $response->json();
        }

        if ($response->status() === 429) {
            throw new OpenAiRateLimitExceededException('OpenAI API rate limit exceeded.');
        }

        if ($response->status() === 500) {
            throw new OpenAi500ErrorException('OpenAI API returned a 500 error.');
        }

        $response_json = $response->json();
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
        string $model = 'gpt-5-nano',
        ?array $function_definition = null,
        bool $json_mode = false,
        ?int $user_id = null,
        ?float $temperature = 0.7,
        int $retry_count = 0,
        array $messages = [],
        array $image_urls = [],
        ?ThinkingEffort $thinking_effort = null,
    ): ?string {

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

        $approximate_output_max_tokens = $this->get_max_output_tokens($model);

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
            image_urls: $image_urls,
            thinking_effort: $thinking_effort,
        );
        $this->attempt_log_prompt($user_id, $raw_response, $model);

        $summary_response_text = $raw_response['choices'][0]['message']['content'] ?? null;
        if (! $summary_response_text) {
            return null;
        }

        if ($this->latest_stop_reason === 'length') {
            $this->latest_stop_reason = null;
            $full_response = $summary_response_text;
            for ($i = 0; $i < $retry_count; $i++) {
                $continued_prompt = <<<EOD
"Please carefully analyze the PROMPT and INITIAL ANSWER below.

The INITIAL ANSWER is cut off due to the maximum token limit being reached.  Please provide a continuation of the answer that is relevant to the PROMPT, starting EXACTLY where the INITIAL ANSWER left off.

ONLY provide the continuation of the answer, do not provide the PROMPT or any other information.

PROMPT
$prompt

INITIAL ANSWER:
$full_response

EOD;

                $messages = $this->generate_chat_array_from_input_prompt($continued_prompt);
                $continued_response = $this->get_openai_chat_completion(
                    messages: $messages,
                    temperature: 0,
                    max_tokens: $approximate_output_max_tokens,
                    model: $model,
                    role_context: $context,
                    timeout_in_seconds: 1800,
                    function_definition: $function_definition,
                    json_mode: false,
                    user_identifier: $user_identifier,
                    image_urls: $image_urls,
                );

                $second_response_text = $continued_response['choices'][0]['message']['content'] ?? null;
                if ($second_response_text) {
                    $full_response .= $second_response_text;
                }

                if (! $this->latest_stop_reason) {
                    break;
                }
            }

            return $full_response;
        }

        return $summary_response_text;
    }

    public function get_max_output_tokens(string $model = 'gpt-5-nano'): int
    {
        switch ($model) {
            // O-series models
            case 'gpt-5':
            case 'gpt-5-mini':
            case 'gpt-5-nano':
                return 128000;
            case 'o1':
            case 'o1-2024-12-17':
                return 100000;
            case 'o1-pro':
            case 'o1-pro-2025-03-19':
                return 100000;
            case 'o3-pro':
            case 'o3-pro-2025-06-10':
                return 100000;
            case 'o3':
            case 'o3-2025-04-16':
                return 100000;
            case 'o4-mini':
            case 'o4-mini-2025-04-16':
                return 65536;
            case 'o3-mini':
            case 'o3-mini-2025-01-31':
                return 65536;
            case 'o1-mini':
            case 'o1-mini-2024-09-12':
                return 65536;
            case 'o1-preview':
                return 32768;

                // GPT-4.5 models
            case 'gpt-4.5-preview':
            case 'gpt-4.5-preview-2025-02-27':
                return 32768;

                // GPT-4.1 models
            case 'gpt-4.1':
            case 'gpt-4.1-2025-04-14':
                return 16384;
            case 'gpt-4.1-mini':
            case 'gpt-4.1-mini-2025-04-14':
                return 16384;
            case 'gpt-4.1-nano':
            case 'gpt-4.1-nano-2025-04-14':
                return 16384;

                // GPT-4o models
            case 'gpt-4o':
            case 'gpt-4o-2024-08-06':
            case 'gpt-4o-audio-preview':
            case 'gpt-4o-audio-preview-2024-12-17':
            case 'gpt-4o-realtime-preview':
            case 'gpt-4o-realtime-preview-2024-12-17':
                return 16384;

                // GPT-4o-mini models
            case 'gpt-4o-mini':
            case 'gpt-4o-mini-2024-07-18':
            case 'gpt-4o-mini-audio-preview':
            case 'gpt-4o-mini-audio-preview-2024-12-17':
            case 'gpt-4o-mini-realtime-preview':
            case 'gpt-4o-mini-realtime-preview-2024-12-17':
                return 16384;

                // Search models
            case 'gpt-4o-mini-search-preview':
            case 'gpt-4o-mini-search-preview-2025-03-11':
            case 'gpt-4o-search-preview':
            case 'gpt-4o-search-preview-2025-03-11':
                return 16384;

                // Other specialized models
            case 'codex-mini-latest':
                return 8192;
            case 'computer-use-preview':
            case 'computer-use-preview-2025-03-11':
                return 16384;
            case 'gpt-image-1':
                return 4096;

                // ChatGPT models
            case 'chatgpt-4o-latest':
                return 16384;

                // GPT-4 turbo models
            case 'gpt-4-turbo':
            case 'gpt-4-turbo-2024-04-09':
                return 4096;

                // GPT-4 models
            case 'gpt-4':
            case 'gpt-4-0613':
            case 'gpt-4-0314':
                return 8192;
            case 'gpt-4-32k':
                return 32768;

                // GPT-3.5 models
            case 'gpt-3.5-turbo':
            case 'gpt-3.5-turbo-0125':
                return 16384;
            case 'gpt-3.5-turbo-instruct':
                return 4096;
            case 'gpt-3.5-turbo-16k-0613':
                return 16384;

                // Embedding models (not for chat completion but included for completeness)
            case 'text-embedding-3-small':
            case 'text-embedding-3-large':
            case 'text-embedding-ada-002':
                return 8191; // Max input tokens for embeddings

                // Legacy models
            case 'davinci-002':
            case 'babbage-002':
                return 4096;

            default:
                return 4096; // Conservative default for unknown models
        }
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

    public function get_corrected_json_from_response(?string $response, ?int $user_id = null, string $json_first_key_name = 'response'): ?array
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

        $array = json_decode($stripped_response, true);
        if (! $array || count($array) < 1) {
            $array = $this->get_fallback_response_from_open_ai(
                response_text: $response,
                user_id: $user_id,
                json_first_key_name: $json_first_key_name
            );
        }

        return $array;
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
        return $this->get_embedding(contents: $contents, model: 'text-embedding-ada-002');
    }

    public function get_embedding(string $contents, string $model = 'text-embedding-3-large')
    {
        $input_values = json_encode([
            'input' => $contents,
            'model' => $model,
        ]);

        $embeddings = Http::withBody($input_values)
            ->withToken(config('ai_providers.open_ai_key'))
            ->post('https://api.openai.com/v1/embeddings')
            ->json();

        return $embeddings['data'][0]['embedding'];
    }

    public function log_prompt(?int $user_id, mixed $raw_response, string $model): void
    {
        CostLog::create([
            'user_id' => $user_id,
            'prompt_identifier' => $raw_response['id'],
            'model' => $model,
            'service' => 'OpenAI',
            'input_tokens' => $raw_response['usage']['prompt_tokens'] ?? 0,
            'output_tokens' => $raw_response['usage']['completion_tokens'] ?? 0,
        ]);
    }

    private function attempt_log_prompt(?int $user_id, $raw_response, string $model): void
    {
        try {
            $this->log_prompt($user_id, $raw_response, $model);
        } catch (Exception $e) {
            Log::error('CostLog creation failed: '.$e->getMessage());
        }
    }

    public function get_fallback_response_from_open_ai(string $response_text, ?int $user_id = null, string $json_first_key_name = ''): array
    {
        $prompt = <<<EOD
Please carefully analyze the malformed JSON string below, and identify what the issues are that are causing it to be malformed.
Please also return a corrected JSON string that is valid.  Make only the minimum required to make the JSON valid.
If it appears the malformed JSON is attempting to use multiple newlines, please ensure the newlines are modified to be escaped newlines, like \n\n.
MALFORMED STRING:
{$response_text}

Your output should be ONLY the corrected JSON.

EOD;
        $response = $this->get_response_from_prompt_and_context(
            prompt: $prompt,
            model: 'gpt-5-nano',
            json_mode: true,
            user_id: $user_id,
            thinking_effort: ThinkingEffort::MINIMAL
        );

        // Strip markdown JSON code blocks if present
        $response = $this->strip_markdown_json($response);

        return json_decode($response, true) ?? [];
    }

    private function strip_markdown_json(?string $response): ?string
    {
        if (! $response) {
            return $response;
        }

        // Remove markdown JSON code block markers
        $response = preg_replace('/^```json\s*/i', '', $response);
        $response = preg_replace('/^```\s*/m', '', $response);
        $response = preg_replace('/\s*```\s*$/s', '', $response);

        return trim($response);
    }
}

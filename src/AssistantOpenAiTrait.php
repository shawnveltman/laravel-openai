<?php

namespace Shawnveltman\LaravelOpenai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Shawnveltman\LaravelOpenai\Exceptions\GeneralOpenAiException;
use Shawnveltman\LaravelOpenai\Exceptions\OpenAi500ErrorException;
use Shawnveltman\LaravelOpenai\Exceptions\OpenAiRateLimitExceededException;

trait AssistantOpenAiTrait
{
    protected string $api_url = 'https://api.openai.com/v1';

    // Create Assistant Function
    public function create_assistant(string $name, string $instructions = '', string $description = '', array $parameters = [], string $model = 'gpt-3.5-turbo'): array
    {
        $api_key = config('ai_providers.open_ai_key');

        $url = $this->api_url.'/assistants';

        $response = Http::withToken($api_key)
            ->withHeaders([
                'OpenAI-Beta' => 'assistants=v2',
            ])
            ->post($url, [
                'instructions' => $instructions,
                'description' => $description,
                'name' => $name,
                'tools' => $parameters,
                'model' => $model,
            ]);

        return $this->handle_response($response, 'Failed to create assistant');
    }

    // Update Assistant Function
    public function update_assistant(string $assistant_id, array $update_values = []): array
    {
        $api_key = config('ai_providers.open_ai_key');

        $url = $this->api_url.'/assistants/'.$assistant_id;

        $response = Http::withToken($api_key)
            ->withHeaders([
                'OpenAI-Beta' => 'assistants=v2',
            ])
            ->post($url, $update_values);

        return $this->handle_response($response, 'Failed to update assistant');
    }

    // Delete Assistant Function
    public function delete_assistant(string $assistant_id): array
    {
        $api_key = config('ai_providers.open_ai_key');
        $url = $this->api_url.'/assistants/'.$assistant_id;

        $response = Http::withToken($api_key)
            ->withHeaders([
                'OpenAI-Beta' => 'assistants=v2',
            ])
            ->delete($url);

        return $this->handle_response($response, 'Failed to delete assistant');
    }

    public function create_run_and_thread(string $assistant_id, string $prompt = '')
    {
        $api_key = config('ai_providers.open_ai_key');
        $url = $this->api_url.'/threads/runs';

        $response = Http::withToken($api_key)
            ->withHeaders([
                'OpenAI-Beta' => 'assistants=v2',
            ])
            ->post($url, [
                'assistant_id' => $assistant_id,
                'thread' => [
                    'messages' => [[
                        'role' => 'user',
                        'content' => $prompt,
                    ]],
                ],
            ]);

        return $this->handle_response($response, 'Failed to create run and thread');
    }

    public function get_run_from_run_id_and_thread_id($run_id, $thread_id)
    {
        $api_key = config('ai_providers.open_ai_key');
        $url = $this->api_url."/threads/{$thread_id}/runs/{$run_id}";

        $response = Http::withToken($api_key)
            ->withHeaders([
                'OpenAI-Beta' => 'assistants=v2',
            ])
            ->get($url);

        return $this->handle_response($response, 'Failed to retrieve run');
    }

    public function upload_files(string $vector_store_id, array $files): array
    {
        $file_ids = [];

        foreach ($files as $file) {
            $response = $this->upload_file($file);
            $file_ids[] = $response['id'] ?? '';
        }

        return $this->create_vector_files_for_files_id($vector_store_id, $file_ids);
    }

    public function upload_file(string $file_path)
    {
        $api_key = config('ai_providers.open_ai_key');

        $file = Storage::disk('base_path')->get($file_path);
        $file_name = basename($file_path);

        $response = Http::withToken($api_key)
            ->attach('file', $file, $file_name)
            ->post($this->api_url.'/files', [
                'purpose' => 'assistants',
                'file' => $file,
            ]);

        return $this->handle_response($response, 'Failed to upload file');
    }

    public function create_vector_files_for_files_id(string $vector_store_id, array $file_ids): array
    {
        $api_key = config('ai_providers.open_ai_key');

        $response = Http::withToken($api_key)
            ->withHeaders([
                'OpenAI-Beta' => 'assistants=v2',
            ])
            ->post($this->api_url.'/vector_stores/'.$vector_store_id.'/file_batches', [
                'file_ids' => $file_ids,
            ]);

        return $this->handle_response($response, 'Failed to associate files with vector store');
    }

    // Create Vector Store Function
    public function create_vector_store(string $name): array
    {
        $api_key = config('ai_providers.open_ai_key');

        $url = $this->api_url.'/vector_stores';

        $response = Http::withToken($api_key)
            ->withHeaders([
                'OpenAI-Beta' => 'assistants=v2',
            ])
            ->post($url, [
                'name' => $name,
            ]);

        return $this->handle_response($response, 'Failed to create vector store');
    }

    // Retrieve Vector Store Function
    public function retrieve_vector_store(string $vector_store_id): array
    {
        $api_key = config('ai_providers.open_ai_key');

        $url = $this->api_url.'/vector_stores/'.$vector_store_id;

        $response = Http::withToken($api_key)
            ->withHeaders([
                'OpenAI-Beta' => 'assistants=v2',
            ])
            ->get($url);

        return $this->handle_response($response, 'Failed to create vector store');
    }

    // Update Vector Store Function
    public function update_vector_store(string $vector_store_id, array $update_values = []): array
    {
        $api_key = config('ai_providers.open_ai_key');

        $url = $this->api_url.'/vector_stores/'.$vector_store_id;

        $response = Http::withToken($api_key)
            ->withHeaders([
                'OpenAI-Beta' => 'assistants=v2',
            ])
            ->post($url, $update_values);

        return $this->handle_response($response, 'Failed to update vector store');
    }

    // Delete Vector Store Function
    public function delete_vector_store(string $vector_store_id): array
    {
        $api_key = config('ai_providers.open_ai_key');

        $url = $this->api_url.'/vector_stores/'.$vector_store_id;

        $response = Http::withToken($api_key)
            ->withHeaders([
                'OpenAI-Beta' => 'assistants=v2',
            ])
            ->delete($url);

        return $this->handle_response($response, 'Failed to delete vector store');
    }

    // Link Assistant to Vector Store Function
    public function link_assistant_to_vector_stores(string $assistant_id, array $vector_store_ids): array
    {
        return $this->update_assistant($assistant_id, [
            'tool_resources' => [
                'file_search' => [
                    'vector_store_ids' => $vector_store_ids,
                ],
            ],
        ]);
    }

    public function retrieve_messages_from_a_thread(string $thread_id)
    {
        $api_key = config('ai_providers.open_ai_key');

        $url = $this->api_url.'/threads/'.$thread_id.'/messages';

        $response = Http::withToken($api_key)
            ->withHeaders([
                'OpenAI-Beta' => 'assistants=v2',
            ])
            ->get($url);

        return $this->handle_response($response, 'Failed to retrieve messages');
    }

    // Handle the response and throw appropriate exceptions
    protected function handle_response($response, $default_error_message)
    {
        if ($response->successful()) {
            return $response->json();
        }

        if ($response->status() === 429) {
            throw new OpenAiRateLimitExceededException('OpenAI API rate limit exceeded.');
        }

        if ($response->status() === 500) {
            throw new OpenAi500ErrorException('OpenAI API returned a 500 error.');
        }

        $response_json = $response->json();

        throw new GeneralOpenAiException($default_error_message.': '.($response_json['error']['message'] ?? 'Unknown error'));
    }
}

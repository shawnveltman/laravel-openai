<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Shawnveltman\LaravelOpenai\AssistantOpenAiTrait;
use Shawnveltman\LaravelOpenai\Exceptions\GeneralOpenAiException;
use Shawnveltman\LaravelOpenai\Exceptions\OpenAi500ErrorException;
use Shawnveltman\LaravelOpenai\Exceptions\OpenAiRateLimitExceededException;

beforeEach(function (): void
{
    $this->traitObject = new class()
    {
        use AssistantOpenAiTrait;
    };
});

// Upload Files Tests
it('uploads files successfully', function (): void
{
    Http::fake([
        'api.openai.com/v1/files'                                      => Http::response(['id' => 'file_id'], 200),
        'api.openai.com/v1/vector_stores/vector_store_id/file_batches' => Http::response(['success' => true], 200),
    ]);

    Storage::fake('base_path')->put('file_path', 'file content');

    $response = $this->traitObject->upload_files('vector_store_id', ['file_path']);

    expect($response)->toHaveKey('success', true);
});

it('throws GeneralOpenAiException on file upload failure', function (): void
{
    Http::fake([
        'api.openai.com/v1/files' => Http::response(['error' => ['message' => 'Error message']], 400),
    ]);

    Storage::fake('base_path')->put('file_path', 'file content');

    $this->traitObject->upload_files('vector_store_id', ['file_path']);
})->throws(GeneralOpenAiException::class, 'Failed to upload file: Error message');

it('throws OpenAiRateLimitExceededException on rate limit exceeded during file upload', function (): void
{
    Http::fake([
        'api.openai.com/v1/files' => Http::response([], 429),
    ]);

    Storage::fake('base_path')->put('file_path', 'file content');

    $this->traitObject->upload_files('vector_store_id', ['file_path']);
})->throws(OpenAiRateLimitExceededException::class, 'OpenAI API rate limit exceeded.');

it('throws OpenAi500ErrorException on server error during file upload', function (): void
{
    Http::fake([
        'api.openai.com/v1/files' => Http::response([], 500),
    ]);

    Storage::fake('base_path')->put('file_path', 'file content');

    $this->traitObject->upload_files('vector_store_id', ['file_path']);
})->throws(OpenAi500ErrorException::class, 'OpenAI API returned a 500 error.');

// Create Vector Store Tests
it('creates a vector store successfully', function (): void
{
    Http::fake([
        'api.openai.com/v1/vector_stores' => Http::response(['id' => 'vector_store_id'], 200),
    ]);

    $response = $this->traitObject->create_vector_store('Test Vector Store');

    expect($response)->toHaveKey('id', 'vector_store_id');
});

it('throws GeneralOpenAiException on create vector store failure', function (): void
{
    Http::fake([
        'api.openai.com/v1/vector_stores' => Http::response(['error' => ['message' => 'Error message']], 400),
    ]);

    $this->traitObject->create_vector_store('Test Vector Store');
})->throws(GeneralOpenAiException::class, 'Failed to create vector store: Error message');

it('throws OpenAiRateLimitExceededException on rate limit exceeded during create vector store', function (): void
{
    Http::fake([
        'api.openai.com/v1/vector_stores' => Http::response([], 429),
    ]);

    $this->traitObject->create_vector_store('Test Vector Store');
})->throws(OpenAiRateLimitExceededException::class, 'OpenAI API rate limit exceeded.');

it('throws OpenAi500ErrorException on server error during create vector store', function (): void
{
    Http::fake([
        'api.openai.com/v1/vector_stores' => Http::response([], 500),
    ]);

    $this->traitObject->create_vector_store('Test Vector Store');
})->throws(OpenAi500ErrorException::class, 'OpenAI API returned a 500 error.');

// Retrieve Vector Store Tests
it('retrieves a vector store successfully', function (): void
{
    Http::fake([
        'api.openai.com/v1/vector_stores/vector_store_id' => Http::response(['id' => 'vector_store_id'], 200),
    ]);

    $response = $this->traitObject->retrieve_vector_store('vector_store_id');

    expect($response)->toHaveKey('id', 'vector_store_id');
});

it('throws GeneralOpenAiException on retrieve vector store failure', function (): void
{
    Http::fake([
        'api.openai.com/v1/vector_stores/vector_store_id' => Http::response(['error' => ['message' => 'Error message']], 400),
    ]);

    $this->traitObject->retrieve_vector_store('vector_store_id');
})->throws(GeneralOpenAiException::class, 'Failed to create vector store: Error message');

it('throws OpenAiRateLimitExceededException on rate limit exceeded during retrieve vector store', function (): void
{
    Http::fake([
        'api.openai.com/v1/vector_stores/vector_store_id' => Http::response([], 429),
    ]);

    $this->traitObject->retrieve_vector_store('vector_store_id');
})->throws(OpenAiRateLimitExceededException::class, 'OpenAI API rate limit exceeded.');

it('throws OpenAi500ErrorException on server error during retrieve vector store', function (): void
{
    Http::fake([
        'api.openai.com/v1/vector_stores/vector_store_id' => Http::response([], 500),
    ]);

    $this->traitObject->retrieve_vector_store('vector_store_id');
})->throws(OpenAi500ErrorException::class, 'OpenAI API returned a 500 error.');

// Update Vector Store Tests
it('updates a vector store successfully', function (): void
{
    Http::fake([
        'api.openai.com/v1/vector_stores/vector_store_id' => Http::response(['id' => 'vector_store_id'], 200),
    ]);

    $response = $this->traitObject->update_vector_store('vector_store_id', ['name' => 'Updated Vector Store']);

    expect($response)->toHaveKey('id', 'vector_store_id');
});

it('throws GeneralOpenAiException on update vector store failure', function (): void
{
    Http::fake([
        'api.openai.com/v1/vector_stores/vector_store_id' => Http::response(['error' => ['message' => 'Error message']], 400),
    ]);

    $this->traitObject->update_vector_store('vector_store_id', ['name' => 'Updated Vector Store']);
})->throws(GeneralOpenAiException::class, 'Failed to update vector store: Error message');

it('throws OpenAiRateLimitExceededException on rate limit exceeded during update vector store', function (): void
{
    Http::fake([
        'api.openai.com/v1/vector_stores/vector_store_id' => Http::response([], 429),
    ]);

    $this->traitObject->update_vector_store('vector_store_id', ['name' => 'Updated Vector Store']);
})->throws(OpenAiRateLimitExceededException::class, 'OpenAI API rate limit exceeded.');

it('throws OpenAi500ErrorException on server error during update vector store', function (): void
{
    Http::fake([
        'api.openai.com/v1/vector_stores/vector_store_id' => Http::response([], 500),
    ]);

    $this->traitObject->update_vector_store('vector_store_id', ['name' => 'Updated Vector Store']);
})->throws(OpenAi500ErrorException::class, 'OpenAI API returned a 500 error.');

// Delete Vector Store Tests
it('deletes a vector store successfully', function (): void
{
    Http::fake([
        'api.openai.com/v1/vector_stores/vector_store_id' => Http::response(['deleted' => true], 200),
    ]);

    $response = $this->traitObject->delete_vector_store('vector_store_id');

    expect($response)->toHaveKey('deleted', true);
});

it('throws GeneralOpenAiException on delete vector store failure', function (): void
{
    Http::fake([
        'api.openai.com/v1/vector_stores/vector_store_id' => Http::response(['error' => ['message' => 'Error message']], 400),
    ]);

    $this->traitObject->delete_vector_store('vector_store_id');
})->throws(GeneralOpenAiException::class, 'Failed to delete vector store: Error message');

it('throws OpenAiRateLimitExceededException on rate limit exceeded during delete vector store', function (): void
{
    Http::fake([
        'api.openai.com/v1/vector_stores/vector_store_id' => Http::response([], 429),
    ]);

    $this->traitObject->delete_vector_store('vector_store_id');
})->throws(OpenAiRateLimitExceededException::class, 'OpenAI API rate limit exceeded.');

it('throws OpenAi500ErrorException on server error during delete vector store', function (): void
{
    Http::fake([
        'api.openai.com/v1/vector_stores/vector_store_id' => Http::response([], 500),
    ]);

    $this->traitObject->delete_vector_store('vector_store_id');
})->throws(OpenAi500ErrorException::class, 'OpenAI API returned a 500 error.');

// Link Assistant to Vector Stores Tests
it('links assistant to vector stores successfully', function (): void
{
    Http::fake([
        'api.openai.com/v1/assistants/assistant_id' => Http::response(['id' => 'assistant_id'], 200),
    ]);

    $response = $this->traitObject->link_assistant_to_vector_stores('assistant_id', ['vector_store_id']);

    expect($response)->toHaveKey('id', 'assistant_id');
});

it('throws GeneralOpenAiException on link assistant to vector stores failure', function (): void
{
    Http::fake([
        'api.openai.com/v1/assistants/assistant_id' => Http::response(['error' => ['message' => 'Error message']], 400),
    ]);

    $this->traitObject->link_assistant_to_vector_stores('assistant_id', ['vector_store_id']);
})->throws(GeneralOpenAiException::class, 'Failed to update assistant: Error message');

it('throws OpenAiRateLimitExceededException on rate limit exceeded during link assistant to vector stores', function (): void
{
    Http::fake([
        'api.openai.com/v1/assistants/assistant_id' => Http::response([], 429),
    ]);

    $this->traitObject->link_assistant_to_vector_stores('assistant_id', ['vector_store_id']);
})->throws(OpenAiRateLimitExceededException::class, 'OpenAI API rate limit exceeded.');

it('throws OpenAi500ErrorException on server error during link assistant to vector stores', function (): void
{
    Http::fake([
        'api.openai.com/v1/assistants/assistant_id' => Http::response([], 500),
    ]);

    $this->traitObject->link_assistant_to_vector_stores('assistant_id', ['vector_store_id']);
})->throws(OpenAi500ErrorException::class, 'OpenAI API returned a 500 error.');

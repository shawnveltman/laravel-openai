<?php

use Illuminate\Support\Facades\Http;
use Shawnveltman\LaravelOpenai\AssistantOpenAiTrait;
use Shawnveltman\LaravelOpenai\Exceptions\GeneralOpenAiException;
use Shawnveltman\LaravelOpenai\Exceptions\OpenAi500ErrorException;
use Shawnveltman\LaravelOpenai\Exceptions\OpenAiRateLimitExceededException;

beforeEach(function (): void {
    $this->traitObject = new class()
    {
        use AssistantOpenAiTrait;
    };
});

// Create Assistant Tests
it('creates an assistant successfully', function (): void {
    Http::fake([
        'api.openai.com/v1/assistants' => Http::response(['id' => 'assistant_id'], 200),
    ]);

    $response = $this->traitObject->create_assistant('Test Assistant');

    expect($response)->toHaveKey('id', 'assistant_id');
});

it('throws GeneralOpenAiException on create failure', function (): void {
    Http::fake([
        'api.openai.com/v1/assistants' => Http::response(['error' => ['message' => 'Error message']], 400),
    ]);

    $this->traitObject->create_assistant('Test Assistant');
})->throws(GeneralOpenAiException::class, 'Failed to create assistant: Error message');

it('throws OpenAiRateLimitExceededException on rate limit exceeded', function (): void {
    Http::fake([
        'api.openai.com/v1/assistants' => Http::response([], 429),
    ]);

    $this->traitObject->create_assistant('Test Assistant');
})->throws(OpenAiRateLimitExceededException::class, 'OpenAI API rate limit exceeded.');

it('throws OpenAi500ErrorException on server error', function (): void {
    Http::fake([
        'api.openai.com/v1/assistants' => Http::response([], 500),
    ]);

    $this->traitObject->create_assistant('Test Assistant');
})->throws(OpenAi500ErrorException::class, 'OpenAI API returned a 500 error.');

// Update Assistant Tests
it('updates an assistant successfully', function (): void {
    Http::fake([
        'api.openai.com/v1/assistants/assistant_id' => Http::response(['id' => 'assistant_id'], 200),
    ]);

    $response = $this->traitObject->update_assistant('assistant_id', ['name' => 'Updated Assistant']);

    expect($response)->toHaveKey('id', 'assistant_id');
});

it('throws GeneralOpenAiException on update failure', function (): void {
    Http::fake([
        'api.openai.com/v1/assistants/assistant_id' => Http::response(['error' => ['message' => 'Error message']], 400),
    ]);

    $this->traitObject->update_assistant('assistant_id', ['name' => 'Updated Assistant']);
})->throws(GeneralOpenAiException::class, 'Failed to update assistant: Error message');

it('throws OpenAiRateLimitExceededException on rate limit exceeded during update', function (): void {
    Http::fake([
        'api.openai.com/v1/assistants/assistant_id' => Http::response([], 429),
    ]);

    $this->traitObject->update_assistant('assistant_id', ['name' => 'Updated Assistant']);
})->throws(OpenAiRateLimitExceededException::class, 'OpenAI API rate limit exceeded.');

it('throws OpenAi500ErrorException on server error during update', function (): void {
    Http::fake([
        'api.openai.com/v1/assistants/assistant_id' => Http::response([], 500),
    ]);

    $this->traitObject->update_assistant('assistant_id', ['name' => 'Updated Assistant']);
})->throws(OpenAi500ErrorException::class, 'OpenAI API returned a 500 error.');

// Delete Assistant Tests
it('deletes an assistant successfully', function (): void {
    Http::fake([
        'api.openai.com/v1/assistants/assistant_id' => Http::response(['deleted' => true], 200),
    ]);

    $response = $this->traitObject->delete_assistant('assistant_id');

    expect($response)->toHaveKey('deleted', true);
});

it('throws GeneralOpenAiException on delete failure', function (): void {
    Http::fake([
        'api.openai.com/v1/assistants/assistant_id' => Http::response(['error' => ['message' => 'Error message']], 400),
    ]);

    $this->traitObject->delete_assistant('assistant_id');
})->throws(GeneralOpenAiException::class, 'Failed to delete assistant: Error message');

it('throws OpenAiRateLimitExceededException on rate limit exceeded during delete', function (): void {
    Http::fake([
        'api.openai.com/v1/assistants/assistant_id' => Http::response([], 429),
    ]);

    $this->traitObject->delete_assistant('assistant_id');
})->throws(OpenAiRateLimitExceededException::class, 'OpenAI API rate limit exceeded.');

it('throws OpenAi500ErrorException on server error during delete', function (): void {
    Http::fake([
        'api.openai.com/v1/assistants/assistant_id' => Http::response([], 500),
    ]);

    $this->traitObject->delete_assistant('assistant_id');
})->throws(OpenAi500ErrorException::class, 'OpenAI API returned a 500 error.');

// Create Run and Thread Tests
it('creates a run and thread successfully', function (): void {
    Http::fake([
        'api.openai.com/v1/threads/runs' => Http::response(['id' => 'run_id'], 200),
    ]);

    $response = $this->traitObject->create_run_and_thread('assistant_id', 'Test content');

    expect($response)->toHaveKey('id', 'run_id');
});

it('throws GeneralOpenAiException on create run and thread failure', function (): void {
    Http::fake([
        'api.openai.com/v1/threads/runs' => Http::response(['error' => ['message' => 'Error message']], 400),
    ]);

    $this->traitObject->create_run_and_thread('assistant_id', 'Test content');
})->throws(GeneralOpenAiException::class, 'Failed to create run and thread: Error message');

it('throws OpenAiRateLimitExceededException on rate limit exceeded during create run and thread', function (): void {
    Http::fake([
        'api.openai.com/v1/threads/runs' => Http::response([], 429),
    ]);

    $this->traitObject->create_run_and_thread('assistant_id', 'Test content');
})->throws(OpenAiRateLimitExceededException::class, 'OpenAI API rate limit exceeded.');

it('throws OpenAi500ErrorException on server error during create run and thread', function (): void {
    Http::fake([
        'api.openai.com/v1/threads/runs' => Http::response([], 500),
    ]);

    $this->traitObject->create_run_and_thread('assistant_id', 'Test content');
})->throws(OpenAi500ErrorException::class, 'OpenAI API returned a 500 error.');

// Get Run from Run ID and Thread ID Tests
it('retrieves a run successfully', function (): void {
    Http::fake([
        'api.openai.com/v1/threads/thread_id/runs/run_id' => Http::response(['id' => 'run_id'], 200),
    ]);

    $response = $this->traitObject->get_run_from_run_id_and_thread_id('run_id', 'thread_id');

    expect($response)->toHaveKey('id', 'run_id');
});

it('throws GeneralOpenAiException on get run failure', function (): void {
    Http::fake([
        'api.openai.com/v1/threads/thread_id/runs/run_id' => Http::response(['error' => ['message' => 'Error message']], 400),
    ]);

    $this->traitObject->get_run_from_run_id_and_thread_id('run_id', 'thread_id');
})->throws(GeneralOpenAiException::class, 'Failed to retrieve run: Error message');

it('throws OpenAiRateLimitExceededException on rate limit exceeded during get run', function (): void {
    Http::fake([
        'api.openai.com/v1/threads/thread_id/runs/run_id' => Http::response([], 429),
    ]);

    $this->traitObject->get_run_from_run_id_and_thread_id('run_id', 'thread_id');
})->throws(OpenAiRateLimitExceededException::class, 'OpenAI API rate limit exceeded.');

it('throws OpenAi500ErrorException on server error during get run', function (): void {
    Http::fake([
        'api.openai.com/v1/threads/thread_id/runs/run_id' => Http::response([], 500),
    ]);

    $this->traitObject->get_run_from_run_id_and_thread_id('run_id', 'thread_id');
})->throws(OpenAi500ErrorException::class, 'OpenAI API returned a 500 error.');

// Retrieve Messages from a Thread Tests
it('retrieves messages from a thread successfully', function (): void {
    Http::fake([
        'api.openai.com/v1/threads/thread_id/messages' => Http::response(['messages' => [['id' => 'message_id']]], 200),
    ]);

    $response = $this->traitObject->retrieve_messages_from_a_thread('thread_id');

    expect($response)->toHaveKey('messages')
        ->and($response['messages'][0])->toHaveKey('id', 'message_id');
});

it('throws GeneralOpenAiException on retrieve messages failure', function (): void {
    Http::fake([
        'api.openai.com/v1/threads/thread_id/messages' => Http::response(['error' => ['message' => 'Error message']], 400),
    ]);

    $this->traitObject->retrieve_messages_from_a_thread('thread_id');
})->throws(GeneralOpenAiException::class, 'Failed to retrieve messages: Error message');

it('throws OpenAiRateLimitExceededException on rate limit exceeded during retrieve messages', function (): void {
    Http::fake([
        'api.openai.com/v1/threads/thread_id/messages' => Http::response([], 429),
    ]);

    $this->traitObject->retrieve_messages_from_a_thread('thread_id');
})->throws(OpenAiRateLimitExceededException::class, 'OpenAI API rate limit exceeded.');

it('throws OpenAi500ErrorException on server error during retrieve messages', function (): void {
    Http::fake([
        'api.openai.com/v1/threads/thread_id/messages' => Http::response([], 500),
    ]);

    $this->traitObject->retrieve_messages_from_a_thread('thread_id');
})->throws(OpenAi500ErrorException::class, 'OpenAI API returned a 500 error.');

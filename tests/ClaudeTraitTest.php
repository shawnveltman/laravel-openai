<?php

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Shawnveltman\LaravelOpenai\ClaudeTestClass;
use Shawnveltman\LaravelOpenai\Exceptions\ClaudeRateLimitException;
use Shawnveltman\LaravelOpenai\Models\CostLog;

uses(WithFaker::class);

beforeEach(function () {
    // Initialize the class that uses ClaudeTrait if necessary.
    $this->testClass = new ClaudeTestClass;
});

// Test for get_claude_response
test('get_claude_response returns the expected response', function () {
    $prompt = 'Hello, Claude!';
    $fakeApiResponse = [
        'stop_reason' => 'end_turn',
        'content' => [
            [
                'text' => 'This is a fake response from Claude.',
            ],
        ],
    ];

    Http::fake([
        'https://api.anthropic.com/v1/messages' => Http::response($fakeApiResponse, 200),
    ]);

    $response = $this->testClass->get_claude_response($prompt);
    expect($response)->toBe('This is a fake response from Claude.');
});

// Test for ClaudeRateLimitException
test('get_claude_response throws ClaudeRateLimitException on 429 error', function () {
    $prompt = 'Hello, Claude!';
    Http::fake([
        'https://api.anthropic.com/v1/messages' => Http::response('Rate limit exceeded', 429),
    ]);

    $this->expectException(ClaudeRateLimitException::class);
    $this->testClass->get_claude_response($prompt);
});

// Test for ClaudeRateLimitException on 529 error
test('get_claude_response throws ClaudeRateLimitException on 529 error', function () {
    $prompt = 'Hello, Claude!';
    Http::fake([
        'https://api.anthropic.com/v1/messages' => Http::response('Rate limit exceeded', 529),
    ]);

    $this->expectException(ClaudeRateLimitException::class);
    $this->testClass->get_claude_response($prompt);
});

// Test that logging works correctly
test('it logs the response correctly', function () {
    $prompt = 'Log this interaction.';
    $fakeApiResponse = [
        'id' => 'example-id',
        'stop_reason' => 'end_turn',
        'content' => [
            [
                'text' => 'Fake response for logging.',
            ],
        ],
        'usage' => [
            'input_tokens' => 100,
            'output_tokens' => 150,
        ],
    ];

    Http::fake([
        'https://api.anthropic.com/v1/messages' => Http::response($fakeApiResponse, 200),
    ]);

    $response = $this->testClass->get_claude_response($prompt, user_id: 1);

    // Verify that the CostLog is written to the database with the correct details
    $this->assertDatabaseHas('cost_logs', [
        'user_id' => 1,
        'prompt_identifier' => 'example-id',
        'model' => 'claude-sonnet-4-20250514',
        'service' => 'Anthropic',
        'input_tokens' => 100,
        'output_tokens' => 150,
    ]);
});

// Test that logging handles exceptions correctly
it('logs an error when log_prompt fails', function () {
    // Fake the model events for CostLog so we can listen for model events without hitting the database
    $this->withoutExceptionHandling();
    Event::fake([CostLog::class]);

    // Mock the response from the Claude API call that get_claude_response will make
    Http::fake([
        'https://api.anthropic.com/v1/messages' => Http::response([
            'content' => [
                ['text' => 'Response from Claude'],
            ],
            'id' => 'fake_id',
            'stop_reason' => 'end_turn',
            'usage' => [
                'input_tokens' => 10,
                'output_tokens' => 5,
            ],
        ], 200),
    ]);

    // Setup a listener to hear the model created event and throw an exception when it's heard
    Event::listen('eloquent.created: '.CostLog::class, function () {
        throw new Exception('Database write failed');
    });

    // Since we're listening for the event, no actual database interaction occurs, and we can expect an error log
    Log::shouldReceive('error')
        ->once()
        ->withArgs(function ($arg) {
            return Str::contains($arg, 'Database write failed');
        });

    // Call the method under test and assert that the response matches our expectations
    $response = $this->testClass->get_claude_response('Test prompt', user_id: 1);
    expect($response)->toBe('Response from Claude');

    // After the test, remove the event listener to avoid affecting subsequent tests
    Event::forget('eloquent.created: '.CostLog::class);
});

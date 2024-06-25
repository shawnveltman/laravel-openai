<?php

use Illuminate\Support\Facades\Http;
use Shawnveltman\LaravelOpenai\ProviderResponseTestClass;

beforeEach(function () {
    $this->testClass = new ProviderResponseTestClass();
});

it('gets a response from GPT model', function () {
    $prompt = 'What is AI?';
    $model = 'gpt-3';

    // Configure the expected response and the fake Http response
    $fakeApiResponse = [
        'id' => 'example-id',
        'choices' => [
            [
                'message' => [
                    'content' => 'Artificial Intelligence (AI) is...',
                ],
                'finish_reason' => 'stop',
            ],
        ],
        'usage' => [
            'prompt_tokens' => 100,
            'completion_tokens' => 150,
        ],
    ];

    Http::fake([
        'api.openai.com/v1/chat/completions' => Http::response($fakeApiResponse, 200),
    ]);

    $response = $this->testClass->get_response_from_provider(prompt: $prompt, model: $model, user_id: 1);

    expect($response)->toEqual('Artificial Intelligence (AI) is...');
});

it('gets a response from Mistral model', function () {
    $prompt = 'Explain Mistral';
    $model = 'mistral-7B';

    Http::fake([
        'https://api.mistral.ai/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'Mistral is a cutting-edge language model...']]],
        ], 200),
    ]);

    $response = $this->testClass->get_response_from_provider($prompt, $model);

    expect($response)->toEqual('Mistral is a cutting-edge language model...');
});

it('gets a response from Mistral mixtral model', function () {
    $prompt = 'Explain Mistral';
    $model = 'open-mixtral-8x7b';

    Http::fake([
        'https://api.mistral.ai/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'Mistral is a cutting-edge language model...']]],
        ], 200),
    ]);

    $response = $this->testClass->get_response_from_provider($prompt, $model);

    expect($response)->toEqual('Mistral is a cutting-edge language model...');
});

it('gets a response from Mistral codestral model', function () {
    $prompt = 'Explain Mistral';
    $model = 'codestral-latest';

    Http::fake([
        'https://api.mistral.ai/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'Mistral is a cutting-edge language model...']]],
        ], 200),
    ]);

    $response = $this->testClass->get_response_from_provider($prompt, $model);

    expect($response)->toEqual('Mistral is a cutting-edge language model...');
});

it('gets a response from Gemini model', function () {
    $model = 'gemini-1.5-flash-latest';
    $prompt = 'Hello, World!';
    $messages = [
        [
            'parts' => [
                [
                    'text' => 'Hello, World Test!',
                ],
            ],
            'role' => 'user',
        ],
    ];

    // Configure the expected response from the Gemini API
    $fakeApiResponse = [
        'candidates' => [
            [
                'content' => [
                    'parts' => [
                        [
                            'text' => 'This is a fake response from Gemini.',
                        ],
                    ],
                ],
            ],
        ],
        'usageMetadata' => [
            'promptTokenCount' => 10,
            'candidatesTokenCount' => 5,
        ],
    ];

    // Fake the response to return the expected JSON above when the matching URL is called
    Http::fake([
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent*' => function ($request) use ($fakeApiResponse) {
            // Assert that the request contains the correctly formatted messages
            expect($request->data())->toMatchArray([
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => 'Hello, World Test!',
                            ],
                        ],
                        'role' => 'user',
                    ],
                    [
                        'parts' => [
                            [
                                'text' => 'Hello, World!',
                            ],
                        ],
                        'role' => 'user',
                    ],
                ],
                'generationConfig' => [
                    'temperature' => .7,
                    'maxOutputTokens' => 4096,
                    'response_mime_type' => 'application/json',
                ],
            ]);

            return Http::response($fakeApiResponse, 200);
        },
    ]);

    // Call the method we're testing
    $response = $this->testClass->get_response_from_provider($prompt, model: $model, messages: $messages);

    // Assert the response matches our expectation
    expect($response)->toEqual('This is a fake response from Gemini.');
});
it('gets a response from Claude model', function () {
    $prompt = 'Tell me about Claude';
    $model = 'claude-v1';
    $assistant_starter_text = 'Starting with Claude...';
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

    $response = $this->testClass->get_response_from_provider(
        $prompt,
        $model,
        assistant_starter_text: $assistant_starter_text
    );

    expect($response)->toEqual('Starting with Claude...This is a fake response from Claude.');
});

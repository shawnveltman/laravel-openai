<?php

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Shawnveltman\LaravelOpenai\GeminiTestClass;
use Shawnveltman\LaravelOpenai\TestClass;

uses(WithFaker::class);

beforeEach(function () {
    // Initialize the class that uses OpenAiTrait if necessary.
    $this->testClass = new GeminiTestClass;
});

/** @test */
test('it sends a correctly formatted request with messages', function () {
    $testClass = new TestClass;

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
                    'temperature' => 0.7,
                    'maxOutputTokens' => 4096,
                ],
            ]);

            return Http::response($fakeApiResponse, 200);
        },
    ]);

    // Call the method we're testing
    $response = $this->testClass->get_gemini_response($prompt, messages: $messages);

    // Assert the response matches our expectation
    expect($response)->toEqual('This is a fake response from Gemini.');
});

/** @test */
test('it sends a correctly formatted request without messages', function () {
    $testClass = new TestClass;

    $prompt = 'Hello, World!';

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
            // Assert that the request contains only the system message if no messages are provided
            expect($request->data())->toMatchArray([
                'contents' => [
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
                    'temperature' => 0.7,
                    'maxOutputTokens' => 4096,
                ],
            ]);

            return Http::response($fakeApiResponse, 200);
        },
    ]);

    // Call the method we're testing
    $response = $this->testClass->get_gemini_response($prompt);

    // Assert the response matches our expectation
    expect($response)->toEqual('This is a fake response from Gemini.');
});

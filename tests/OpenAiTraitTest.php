<?php

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Shawnveltman\LaravelOpenai\Exceptions\OpenAi500ErrorException;
use Shawnveltman\LaravelOpenai\Exceptions\OpenAiRateLimitExceededException;
use Shawnveltman\LaravelOpenai\Models\CostLog;
use Shawnveltman\LaravelOpenai\TestClass;

uses(WithFaker::class);

beforeEach(function () {
    // Initialize the class that uses OpenAiTrait if necessary.
    $this->testClass = new TestClass;
});

test('get_openai_chat_completion returns the expected response', function () {
    $testClass = new TestClass;

    // configure the expected response from the OpenAI API
    $fakeApiResponse = [
        'choices' => [
            [
                'message' => [
                    'content' => 'This is a fake response from OpenAI.',
                ],
                'finish_reason' => 'stop',
            ],
        ],
    ];

    // fake the response to return the expected JSON above when the matching URL is called
    Http::fake([
        'api.openai.com/v1/chat/completions' => function ($request) use ($fakeApiResponse) {
            // This will log the body of the request to see if it matches what you expect
            ray('Fake OpenAI request body:', $request->data());

            return Http::response($fakeApiResponse, 200);
        },
    ]);

    // Call the method we're testing
    $response = $testClass->get_openai_chat_completion(messages: [['role' => 'user', 'content' => 'Hello, World!']]);
    ray($response);
    // Assert the response matches our expectation
    expect($response)->toEqual($fakeApiResponse);

    // You might want to assert the structure of the response or particular values.
    expect($response)->toHaveKey('choices.0.message.content', 'This is a fake response from OpenAI.');
});

test('it handles and logs the response correctly', function () {
    $testClass = new TestClass;

    // Configure the expected response and the fake Http response
    $fakeApiResponse = [
        'id' => 'example-id',
        'choices' => [
            [
                'message' => [
                    'content' => 'Fake response for logging.',
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

    // Use Laravel's `assertDatabaseHas` to ensure the logging behaves correctly.
    // For this, you have to use a database that is configured for testing, like an in-memory SQLite database.
    $response = $testClass->get_response_from_prompt_and_context(prompt: 'Log this interaction.', user_id: 1);

    // Verify that the `CostLog` is written to the database with the correct details
    $this->assertDatabaseHas('cost_logs', [
        'user_id' => 1,
        'prompt_identifier' => 'example-id',
        'model' => 'gpt-5-nano',
        'service' => 'OpenAI',
        'input_tokens' => 100,
        'output_tokens' => 150,
    ]);
});

it('can test for model existence', function () {
    $model = CostLog::factory()->create();
    $this->assertModelExists($model);
});

// Test for get_openai_moderation
test('get_openai_moderation returns the expected moderation response', function () {
    $prompt = 'Some content to be moderated.';

    Http::fake([
        'api.openai.com/v1/moderations' => Http::response(['moderation' => 'content is clean'], 200),
    ]);

    $response = $this->testClass->get_openai_moderation($prompt);

    expect($response)->toHaveKey('moderation', 'content is clean');
});

// Test for generate_chat_array_from_input_prompt
test('generate_chat_array_from_input_prompt returns a correct chat array', function () {
    $inputPrompt = 'Hello, OpenAI!';
    $expectedOutput = [
        [
            'role' => 'user',
            'content' => $inputPrompt,
        ],
    ];

    $result = $this->testClass->generate_chat_array_from_input_prompt($inputPrompt);

    expect($result)->toBe($expectedOutput);
});

// Test for get_max_output_tokens
test('get_max_output_tokens returns the correct token count based on model', function () {
    $gpt3Model = 'gpt-3.5-turbo';
    $gpt4Model = 'gpt-4-1106-preview';

    $gpt3Result = $this->testClass->get_max_output_tokens($gpt3Model);
    $gpt4Result = $this->testClass->get_max_output_tokens($gpt4Model);

    expect($gpt3Result)->toEqual(16384);
    expect($gpt4Result)->toEqual(4096);
});

// Test for get_whisper_transcription
test('get_whisper_transcription handles transcription correctly', function () {
    // Mock the file path
    $mockFilePath = 'path/to/mock/file.wav';
    $mockFilename = 'mock_file.wav';

    // Assuming the file exists at the path specified.
    Storage::fake('local');
    $fakeFile = Storage::put($mockFilePath, 'Fake audio data');

    $fakeApiResponse = ['text' => 'This is a transcription'];

    Http::fake([
        'api.openai.com/v1/audio/transcriptions' => Http::response($fakeApiResponse, 200),
    ]);

    $response = $this->testClass->get_whisper_transcription($mockFilePath, $mockFilename);

    expect($response)->toBe($fakeApiResponse['text']);
});

// Test that validate_and_fix_json handles valid JSON
test('validate_and_fix_json handles valid JSON string', function () {
    $validJson = '{"key":"value"}';

    $result = $this->testClass->validate_and_fix_json($validJson);

    expect($result)->toBe($validJson);
});

// Test that validate_and_fix_json fixes invalid JSON
test('validate_and_fix_json fixes invalid JSON and returns the corrected string', function () {
    $invalidJson = '{"key":"value",}'; // Extra comma makes it invalid

    $result = $this->testClass->validate_and_fix_json($invalidJson);

    expect($result)->toBe('{"key":"value"}');
});

// Test get_corrected_json_from_response
test('get_corrected_json_from_response returns null when response is null', function () {
    $response = null;

    $result = $this->testClass->get_corrected_json_from_response($response);

    expect($result)->toBeNull();
});

test('get_corrected_json_from_response returns array when response is valid JSON', function () {
    $response = '{"key":"value"}';

    $result = $this->testClass->get_corrected_json_from_response($response);

    expect($result)->toBe(['key' => 'value']);
});

// Test clean_json_string
test('clean_json_string finds and extracts JSON from string', function () {
    $dirtyString = "Non-JSON content {'key':'value'} JSON end";

    $cleanedString = $this->testClass->clean_json_string($dirtyString);

    expect($cleanedString)->toBe("{'key':'value'} JSON end");
});

// Test get_ada_embedding
test('get_ada_embedding returns embedding for content', function () {
    $contents = 'This is some text to get embeddings for.';

    $fakeApiResponse = [
        'data' => [
            [
                'embedding' => [1, 2, 3, 4, 5],
            ],
        ],
    ];

    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response($fakeApiResponse, 200),
    ]);

    $response = $this->testClass->get_ada_embedding($contents);

    expect($response)->toBe($fakeApiResponse['data'][0]['embedding']);
});

// Test for clean_json_string with a string containing no JSON object
test('clean_json_string returns null when string contains no JSON object or array', function () {
    $nonJsonString = 'This is just a plain text, no JSON here!';

    $result = $this->testClass->clean_json_string($nonJsonString);

    expect($result)->toBeNull();
});

// Test for clean_json_string with a string containing a malformed JSON
test('clean_json_string returns the JSON portion of the string even if malformed', function () {
    $dirtyStringWithMalformedJson = "Non-JSON content {'key':}} JSON end";

    $result = $this->testClass->clean_json_string($dirtyStringWithMalformedJson);

    expect($result)->toBe("{'key':}} JSON end");
});

// Test for get_corrected_json_from_response returning an array after correcting JSON
test('get_corrected_json_from_response returns array after correcting malformed JSON', function () {
    $invalidJson = '{"key": "value",}'; // Malformed JSON with trailing comma
    $expectedFixedJson = '{"key": "value"}';  // The fixed version without trailing comma

    $result = $this->testClass->get_corrected_json_from_response($invalidJson);

    // The result should be a properly decoded array equivalent to the fixed JSON
    expect($result)->toBe(['key' => 'value']);
});

// Test to fake an exception in the log_prompt method
it('ensures attempt_log_prompt logs an error when log_prompt fails', function () {
    // Fake the model events for CostLog so we can listen for model events without hitting the database
    $this->withoutExceptionHandling();
    Event::fake([CostLog::class]);

    // Mock the response from the OpenAI API call that get_response_from_prompt_and_context will make
    Http::fake([
        'api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => 'Response from OpenAI'], 'finish_reason' => 'stop'],
            ],
            'id' => 'fake_id',
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 5,
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
    $response = $this->testClass->get_response_from_prompt_and_context('Test prompt', user_id: 1);
    expect($response)->toBe('Response from OpenAI');

    // After the test, remove the event listener to avoid affecting subsequent tests
    Event::forget('eloquent.created: '.CostLog::class);
});

test('get_openai_chat_completion throws OpenAiRateLimitExceededException on 429 error', function () {
    Http::fake([
        'api.openai.com/v1/chat/completions' => Http::response('Rate limit exceeded', 429),
    ]);

    $this->expectException(OpenAiRateLimitExceededException::class);
    $this->expectExceptionMessage('OpenAI API rate limit exceeded.');

    $this->testClass->get_openai_chat_completion(messages: [['role' => 'user', 'content' => 'Hello, World!']]);
});

// Test for handling 500 Internal Server Error
test('get_openai_chat_completion throws OpenAi500ErrorException on 500 error', function () {
    Http::fake([
        'api.openai.com/v1/chat/completions' => Http::response('Internal Server Error', 500),
    ]);

    $this->expectException(OpenAi500ErrorException::class);
    $this->expectExceptionMessage('OpenAI API returned a 500 error.');

    $this->testClass->get_openai_chat_completion(messages: [['role' => 'user', 'content' => 'Hello, World!']]);
});

/** @test */
test('it sends a correctly formatted request with messages', function () {
    $testClass = new TestClass;

    $prompt = 'Hello, World!';
    $messages = [
        ['role' => 'user', 'content' => 'Hello, World Test!'],
    ];

    // Configure the expected response from the OpenAI API
    $fakeApiResponse = [
        'choices' => [
            [
                'message' => [
                    'content' => 'This is a fake response from OpenAI.',
                ],
                'finish_reason' => 'stop',
            ],
        ],
    ];

    // Fake the response to return the expected JSON above when the matching URL is called
    Http::fake([
        'api.openai.com/v1/chat/completions' => function ($request) use ($fakeApiResponse) {
            // Assert that the request contains the correctly formatted messages
            expect($request->data())->toMatchArray([
                'model' => 'gpt-5-nano',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful assistant'],
                    ['role' => 'user', 'content' => 'Hello, World Test!'],
                    ['role' => 'user', 'content' => 'Hello, World!'],
                ],
                'temperature' => 1, // GPT-5 models require temperature = 1
                'top_p' => 1,
                'frequency_penalty' => 0,
                'presence_penalty' => 0,
            ]);

            return Http::response($fakeApiResponse, 200);
        },
    ]);

    // Call the method we're testing
    $response = $testClass->get_response_from_prompt_and_context($prompt, messages: $messages);

    // Assert the response matches our expectation
    expect($response)->toEqual('This is a fake response from OpenAI.');
});

/** @test */
test('it sends a correctly formatted request without messages', function () {
    $testClass = new TestClass;

    $prompt = 'Hello, World!';

    // Configure the expected response from the OpenAI API
    $fakeApiResponse = [
        'choices' => [
            [
                'message' => [
                    'content' => 'This is a fake response from OpenAI.',
                ],
                'finish_reason' => 'stop',
            ],
        ],
    ];

    // Fake the response to return the expected JSON above when the matching URL is called
    Http::fake([
        'api.openai.com/v1/chat/completions' => function ($request) use ($fakeApiResponse) {
            // Assert that the request contains only the system message if no messages are provided
            expect($request->data())->toMatchArray([
                'model' => 'gpt-5-nano',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful assistant'],
                    ['role' => 'user', 'content' => 'Hello, World!'],
                ],
                'temperature' => 1, // GPT-5 models require temperature = 1
                'top_p' => 1,
                'frequency_penalty' => 0,
                'presence_penalty' => 0,
            ]);

            return Http::response($fakeApiResponse, 200);
        },
    ]);

    // Call the method we're testing
    $response = $testClass->get_response_from_prompt_and_context($prompt);

    // Assert the response matches our expectation
    expect($response)->toEqual('This is a fake response from OpenAI.');
});

test('get_openai_chat_completion handles O1 model correctly', function () {
    $testClass = new TestClass;

    $messages = [
        ['role' => 'user', 'content' => 'Hello, O1!'],
    ];

    $fakeApiResponse = [
        'choices' => [
            [
                'message' => [
                    'content' => 'This is a fake response from O1.',
                ],
                'finish_reason' => 'stop',
            ],
        ],
    ];

    Http::fake([
        'api.openai.com/v1/chat/completions' => function ($request) use ($fakeApiResponse) {
            $data = $request->data();

            // Check that system message is not included
            expect($data['messages'])->not->toContain(function ($message) {
                return $message['role'] === 'system';
            });

            // Check that top_p, frequency_penalty, and presence_penalty are not included
            expect($data)->not->toHaveKeys(['top_p', 'frequency_penalty', 'presence_penalty']);

            // Check that temperature IS included and set to 1 for o-series models
            expect($data)->toHaveKey('temperature', 1);

            return Http::response($fakeApiResponse, 200);
        },
    ]);

    $response = $testClass->get_openai_chat_completion(
        messages: $messages,
        model: 'o1-model'
    );

    expect($response)->toEqual($fakeApiResponse);
});

test('get_openai_chat_completion handles non-O1 model correctly', function () {
    $testClass = new TestClass;

    $messages = [
        ['role' => 'user', 'content' => 'Hello, GPT!'],
    ];

    $fakeApiResponse = [
        'choices' => [
            [
                'message' => [
                    'content' => 'This is a fake response from GPT.',
                ],
                'finish_reason' => 'stop',
            ],
        ],
    ];

    Http::fake([
        'api.openai.com/v1/chat/completions' => function ($request) use ($fakeApiResponse) {
            $data = $request->data();

            // Check that system message is included
            expect($data['messages'])->toBeArray()
                ->and($data['messages'])->toHaveCount(2)
                ->and($data['messages'][0]['role'])->toBe('system')
                ->and($data['messages'][1]['role'])->toBe('user')
                ->and($data['messages'][1]['content'])->toBe('Hello, GPT!')
                ->and($data)->toHaveKeys(['temperature', 'top_p', 'frequency_penalty', 'presence_penalty']);

            // Check that temperature, top_p, frequency_penalty, and presence_penalty are included

            return Http::response($fakeApiResponse, 200);
        },
    ]);

    $response = $testClass->get_openai_chat_completion(
        messages: $messages,
        model: 'gpt-3.5-turbo'
    );

    expect($response)->toEqual($fakeApiResponse);
});

test('get_openai_chat_completion handles json_mode correctly for O1 and non-O1 models', function () {
    $testClass = new TestClass;

    $messages = [
        ['role' => 'user', 'content' => 'Hello!'],
    ];

    $fakeApiResponse = [
        'choices' => [
            [
                'message' => [
                    'content' => '{"response": "This is a fake JSON response."}',
                ],
                'finish_reason' => 'stop',
            ],
        ],
    ];

    Http::fake([
        'api.openai.com/v1/chat/completions' => function ($request) use ($fakeApiResponse) {
            $data = $request->data();

            if (Str::startsWith($data['model'], 'o1')) {
                expect($data)->not->toHaveKey('response_format');
            } else {
                expect($data)->toHaveKey('response_format.type', 'json_object');
            }

            return Http::response($fakeApiResponse, 200);
        },
    ]);

    // Test with O1 model
    $responseO1 = $testClass->get_openai_chat_completion(
        messages: $messages,
        model: 'o1-model',
        json_mode: true
    );

    // Test with non-O1 model
    $responseNonO1 = $testClass->get_openai_chat_completion(
        messages: $messages,
        model: 'gpt-3.5-turbo',
        json_mode: true
    );

    expect($responseO1)->toEqual($fakeApiResponse)
        ->and($responseNonO1)->toEqual($fakeApiResponse);
});

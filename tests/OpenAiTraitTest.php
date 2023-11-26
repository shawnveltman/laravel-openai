<?php

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Shawnveltman\LaravelOpenai\TestClass;
use Shawnveltman\LaravelOpenai\Models\CostLog;
use Mockery;
use Mockery\MockInterface;
use Illuminate\Support\Facades\Log;


uses(WithFaker::class);

beforeEach(function () {
    // Initialize the class that uses OpenAiTrait if necessary.
    $this->testClass = new TestClass();
});

test('get_openai_chat_completion returns the expected response', function () {
    $testClass = new TestClass();

    // configure the expected response from the OpenAI API
    $fakeApiResponse = [
        'choices' => [
            [
                'message' => [
                    'content' => 'This is a fake response from OpenAI.',
                ],
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
    $testClass = new TestClass();

    // Configure the expected response and the fake Http response
    $fakeApiResponse = [
        'id' => 'example-id',
        'choices' => [
            [
                'message' => [
                    'content' => 'Fake response for logging.',
                ],
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
        'model' => 'gpt-3.5-turbo',
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
    $prompt = "Some content to be moderated.";

    Http::fake([
        'api.openai.com/v1/moderations' => Http::response(['moderation' => 'content is clean'], 200),
    ]);

    $response = $this->testClass->get_openai_moderation($prompt);

    expect($response)->toHaveKey('moderation', 'content is clean');
});

// Test for generate_chat_array_from_input_prompt
test('generate_chat_array_from_input_prompt returns a correct chat array', function () {
    $inputPrompt = "Hello, OpenAI!";
    $expectedOutput = [
        [
            'role'    => 'user',
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

    expect($gpt3Result)->toEqual(3900);
    expect($gpt4Result)->toEqual(8000);
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
    $contents = "This is some text to get embeddings for.";

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
    $nonJsonString = "This is just a plain text, no JSON here!";

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
    $expectedFixedJson = '{"key": "value"}'; // The fixed version without trailing comma

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
                ['message' => ['content' => 'Response from OpenAI']],
            ],
            'id' => 'fake_id',
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 5,
            ],
        ], 200),
    ]);

    // Setup a listener to hear the model created event and throw an exception when it's heard
    Event::listen('eloquent.created: ' . CostLog::class, function () {
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
    Event::forget('eloquent.created: ' . CostLog::class);
});



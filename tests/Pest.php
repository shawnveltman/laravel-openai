<?php

use Shawnveltman\LaravelOpenai\Tests\TestCase;
use Illuminate\Support\Facades\Http;


uses(TestCase::class)->in(__DIR__);

beforeEach(function () {
    // This will prevent any stray HTTP requests during testing
    Http::preventStrayRequests();
});

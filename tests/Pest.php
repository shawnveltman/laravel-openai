<?php

use Illuminate\Support\Facades\Http;
use Shawnveltman\LaravelOpenai\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

beforeEach(function () {
    // This will prevent any stray HTTP requests during testing
    Http::preventStrayRequests();
});

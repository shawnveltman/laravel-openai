<?php

namespace Shawnveltman\LaravelOpenai\Commands;

use Illuminate\Console\Command;

class LaravelOpenaiCommand extends Command
{
    public $signature = 'laravel-openai';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}

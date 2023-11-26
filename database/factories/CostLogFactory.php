<?php

namespace Shawnveltman\LaravelOpenai\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Shawnveltman\LaravelOpenai\Models\CostLog;

class CostLogFactory extends Factory
{
    protected $model = CostLog::class;

    public function definition()
    {
        return [
            'user_id' => random_int(1, 1000),
            'input_tokens' => random_int(1, 200000),
            'output_tokens' => random_int(1, 4000),
            'service' => 'openai',
            'model' => 'gpt-4',
        ];
    }
}

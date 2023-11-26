<?php

namespace Shawnveltman\LaravelOpenai\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CostLog extends Model
{
    use HasFactory;
    public $guarded = [];

    public function user()
    {
        return $this->belongsTo(config('mypackage.user_model'));
    }

}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('cost_logs', 'description'))
        {
            Schema::table('cost_logs', function (Blueprint $table) {
                $table->string('description')->nullable();
            });
        }

        if (!Schema::hasColumn('cost_logs', 'job_uuid'))
        {
            Schema::table('cost_logs', function (Blueprint $table) {
                $table->string('job_uuid')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('cost_logs', function (Blueprint $table) {
            //
        });
    }
};

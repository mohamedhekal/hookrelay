<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hookrelay_endpoints', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('url');
            $table->string('secret');
            $table->json('events')->nullable(); // null = all events
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->json('headers')->nullable();
            $table->timestamps();
        });

        Schema::create('hookrelay_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('endpoint_id')->constrained('hookrelay_endpoints')->cascadeOnDelete();
            $table->string('event_type');
            $table->json('payload');
            $table->string('idempotency_key')->nullable();
            $table->string('status', 32)->default('pending'); // pending|processing|delivered|failed|dead_lettered
            $table->unsignedSmallInteger('attempts_count')->default(0);
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('dead_lettered_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['endpoint_id', 'idempotency_key']);
            $table->index(['status', 'next_attempt_at']);
            $table->index(['event_type', 'created_at']);
        });

        Schema::create('hookrelay_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('hookrelay_deliveries')->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['delivery_id', 'attempt_number']);
        });

        Schema::create('hookrelay_inbounds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('source');
            $table->string('event_type')->nullable();
            $table->json('payload')->nullable();
            $table->json('headers')->nullable();
            $table->boolean('signature_valid')->default(false);
            $table->string('idempotency_key')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['source', 'idempotency_key']);
            $table->index(['source', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hookrelay_inbounds');
        Schema::dropIfExists('hookrelay_attempts');
        Schema::dropIfExists('hookrelay_deliveries');
        Schema::dropIfExists('hookrelay_endpoints');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->longText('state');
            // Bumped on every save; a save carrying an older version is rejected instead of overwriting newer data.
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('last_opened_at')->nullable();
            $table->timestamps();
        });

        Schema::create('campaign_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('ulid', 26)->index();
            $table->string('type', 20);
            $table->string('slot', 4)->nullable();
            $table->string('who', 80)->nullable();
            $table->string('hero_id', 40)->nullable();
            $table->text('text');
            $table->string('source', 10)->nullable();
            $table->string('coalesce', 40)->nullable();
            $table->timestamp('happened_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_events');
        Schema::dropIfExists('campaigns');
    }
};

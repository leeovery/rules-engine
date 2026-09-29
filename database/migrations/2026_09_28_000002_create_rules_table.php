<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rule_set_version_id')->constrained('rule_set_versions')->restrictOnDelete();
            $table->uuid('uuid')->index();
            $table->string('key');
            $table->unsignedInteger('position');
            $table->json('condition');
            $table->string('condition_hash', 64)->index();
            $table->json('value');
            $table->json('metadata')->nullable();

            $table->unique(['rule_set_version_id', 'uuid']);
            $table->unique(['rule_set_version_id', 'key']);
            $table->unique(['rule_set_version_id', 'position']);
            $table->unique(['rule_set_version_id', 'condition_hash']);
        });
    }
};

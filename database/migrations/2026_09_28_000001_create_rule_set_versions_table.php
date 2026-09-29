<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rule_set_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rule_set_id')->constrained('rule_sets')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->date('effective_from')->nullable();
            $table->timestamp('published_at', 6);
            $table->string('published_by')->nullable();
            $table->text('note')->nullable();

            $table->unique(['rule_set_id', 'version']);
        });
    }
};

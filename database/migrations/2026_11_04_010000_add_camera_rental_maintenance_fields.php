<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cameras', function (Blueprint $table) {
            $table->string('brand');
            $table->string('model');
            $table->string('category');
            $table->decimal('daily_rate', 10, 2);
            $table->string('availability')->default('available');
            $table->text('description');
            $table->json('specs')->nullable();
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('camera_id')->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('pending');
            $table->decimal('total', 10, 2);
            $table->index(['camera_id', 'status', 'start_date', 'end_date']);
        });

        Schema::table('maintenances', function (Blueprint $table) {
            $table->foreignId('camera_id')->constrained()->cascadeOnDelete();
            $table->string('issue');
            $table->date('scheduled_date');
            $table->string('status')->default('scheduled');
            $table->string('assigned_to')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('camera_id');
            $table->dropColumn(['issue', 'scheduled_date', 'status', 'assigned_to']);
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->dropIndex(['camera_id', 'status', 'start_date', 'end_date']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('camera_id');
            $table->dropColumn(['start_date', 'end_date', 'status', 'total']);
        });

        Schema::table('cameras', function (Blueprint $table) {
            $table->dropColumn([
                'brand',
                'model',
                'category',
                'daily_rate',
                'availability',
                'description',
                'specs',
            ]);
        });
    }
};

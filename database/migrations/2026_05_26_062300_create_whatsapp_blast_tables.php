<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->unique();
            $table->timestamps();
        });

        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_customer_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['customer_group_id', 'customer_id']);
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('customer_group_id')->constrained()->cascadeOnDelete();
            $table->string('sender_mode'); // fixed | random_rotate
            $table->foreignId('sender_id')->nullable()->constrained()->nullOnDelete();
            $table->string('message_type'); // text | button | media
            $table->text('message')->nullable();
            $table->string('footer')->nullable();
            $table->string('button_image_url')->nullable();
            $table->string('media_path')->nullable();
            $table->string('media_type')->nullable(); // image | video | audio | document
            $table->text('caption')->nullable();
            $table->json('buttons')->nullable();
            $table->string('delay_type'); // per_message | per_batch
            $table->unsignedInteger('delay_seconds')->default(10);
            $table->unsignedInteger('batch_size')->default(1);
            $table->boolean('is_scheduled')->default(false);
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamps();
        });

        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('phone');
            $table->string('sender_phone')->nullable();
            $table->string('status')->default('pending');
            $table->json('api_response')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('queue_index')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('customer_customer_group');
        Schema::dropIfExists('customer_groups');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('app_settings');
    }
};

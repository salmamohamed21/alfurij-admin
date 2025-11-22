<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        ///////////////////////////////////////
        // USERS
        ///////////////////////////////////////
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->unique()->nullable();
            $table->string('name', 150);
            $table->string('email', 255)->unique();
            $table->string('role', 30)->default('user'); // user, admin
            $table->string('avatar')->nullable();
            $table->json('saved_cards')->nullable();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_seen')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        ///////////////////////////////////////
        // LISTINGS
        ///////////////////////////////////////
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users');
            $table->string('ad_type', 20)->default('ad'); // ad or auction
            $table->boolean('buy_now')->default(false);
            $table->string('title', 255);
            $table->string('category', 100);
            $table->string('section', 50)->default('general');
            $table->string('city', 100);
            $table->text('description');
            $table->decimal('price', 15, 2);
            $table->string('status', 30)->default('draft'); // draft, published, sold
            $table->string('condition', 30);
            $table->string('model', 100);
            $table->string('serial_number', 100);
            $table->string('cabin_type', 50)->nullable();
            $table->string('vehicle_type', 50)->nullable();
            $table->string('engine_capacity', 50)->nullable();
            $table->string('transmission', 50)->nullable();
            $table->string('fuel_type', 50)->nullable();
            $table->string('lights_type', 100)->nullable();
            $table->string('color', 50)->nullable();
            $table->decimal('length', 10, 2)->nullable();
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('height', 10, 2)->nullable();
            $table->decimal('kilometers', 10, 2)->nullable();
            $table->integer('registration_year')->nullable();
            $table->string('gearbox_brand', 100)->nullable();
            $table->string('gearbox_type', 100)->nullable();
            $table->json('location')->nullable();
            $table->json('media')->nullable();
            $table->json('documents')->nullable();
            $table->json('files')->nullable();
            $table->json('other')->nullable();
            $table->string('approval_status', 30)->default('pending'); // pending, approved, rejected
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        ///////////////////////////////////////
        // AUCTIONS
        ///////////////////////////////////////
        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->unique()->constrained('listings');
            $table->string('type', 20)->default('scheduled'); // scheduled or live
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->decimal('starting_price', 15, 2)->nullable();
            $table->decimal('current_price', 15, 2)->nullable();
            $table->decimal('reserve_price', 15, 2)->nullable();
            $table->decimal('min_increment', 15, 2)->default(50);
            $table->decimal('join_fee', 15, 2)->default(0);
            $table->string('status', 30)->default('upcoming'); // upcoming, opening, live, finished
            $table->foreignId('winner_id')->nullable()->constrained('users');
            $table->integer('participants_count')->default(0);
            $table->timestamps();
        });

        ///////////////////////////////////////
        // AUCTION STREAMS
        ///////////////////////////////////////
        Schema::create('auction_streams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->onDelete('cascade');
            $table->enum('platform', [
                'youtube', 'facebook'
            ]);
            $table->string('stream_url');          // رابط المشاهدة
            $table->enum('status', ['scheduled', 'live', 'finished'])
                  ->default('scheduled');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['auction_id', 'status']);
            $table->unique(['auction_id', 'status'], 'one_active_stream_per_auction');
        });

        ///////////////////////////////////////
        // WALLETS
        ///////////////////////////////////////
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users');
            $table->decimal('balance', 15, 2)->default(0);
            $table->string('currency', 10)->default('COIN');
            $table->timestamps();
        });

        ///////////////////////////////////////
        // PAYMENTS
        ///////////////////////////////////////
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 10)->default('SAR');
            $table->string('status', 30)->default('pending'); // pending, succeeded, failed
            $table->string('provider', 50)->nullable();
            $table->string('provider_payment_id', 255)->nullable();
            // Card data fields for linking to payment APIs
            $table->string('cardholder_name')->nullable();
            $table->integer('expiry_month')->nullable();
            $table->integer('expiry_year')->nullable();
            $table->boolean('save_card')->default(false);
            $table->string('card_token', 255)->nullable(); // Token from payment gateway
            $table->string('card_last_four', 4)->nullable(); // Last 4 digits for display
            $table->string('card_brand', 50)->nullable(); // e.g., Visa, Mastercard
            $table->timestamps();
        });

        ///////////////////////////////////////
        // TRANSACTIONS
        ///////////////////////////////////////
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('related_user_id')->nullable()->constrained('users');
            $table->foreignId('wallet_id')->constrained('wallets');
            $table->foreignId('payment_id')->nullable()->constrained('payments');
            $table->foreignId('auction_id')->nullable()->constrained('auctions');
            $table->string('type', 30); // topup, bid, refund, fee, purchase
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2)->default(0);
            $table->decimal('balance_after', 15, 2)->default(0);
            $table->string('status', 30)->default('pending'); // pending, success, failed
            $table->text('description')->nullable();
            $table->timestamps();
        });

        ///////////////////////////////////////
        // NOTIFICATIONS (Laravel Standard)
        ///////////////////////////////////////
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        ///////////////////////////////////////
        // CACHE
        ///////////////////////////////////////
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        ///////////////////////////////////////
        // PERSONAL ACCESS TOKENS
        ///////////////////////////////////////
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        ///////////////////////////////////////
        // SESSIONS
        ///////////////////////////////////////
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        ///////////////////////////////////////
        // JOBS
        ///////////////////////////////////////
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        ///////////////////////////////////////
        // FAVORITES
        ///////////////////////////////////////
        Schema::create('favorites', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('listing_id')->constrained('listings');
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['user_id', 'listing_id']);
        });

        ///////////////////////////////////////
        // AUCTION PARTICIPANTS
        ///////////////////////////////////////
        Schema::create('auction_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained('auctions');
            $table->foreignId('user_id')->constrained('users');
            $table->decimal('join_fee', 15, 2)->default(0);
            $table->integer('total_bids')->default(0);
            $table->decimal('total_spent', 15, 2)->default(0);
            $table->boolean('is_winner')->default(false);
            $table->timestamp('joined_at')->useCurrent();

            $table->unique(['auction_id', 'user_id']);
        });

        ///////////////////////////////////////
        // BIDS
        ///////////////////////////////////////
        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained('auctions');
            $table->foreignId('bidder_id')->constrained('users');
            $table->decimal('amount', 15, 2);
            $table->boolean('is_auto_bid')->default(false);
            $table->enum('status', ['accepted', 'refunded', 'cancelled'])->default('accepted');
            $table->timestamps();

            $table->index(['auction_id']);
            $table->index(['bidder_id']);
            $table->index(['auction_id', 'amount', 'created_at'], 'bids_auction_amount_created_at_index');
        });

        ///////////////////////////////////////
        // MODEL TRUCKS
        ///////////////////////////////////////
        Schema::create('model_trucks', function (Blueprint $table) {
            $table->id();
            $table->string('truck_name');
            $table->string('model_name');
            $table->string('image_path')->nullable();
            $table->timestamps();
        });

        ///////////////////////////////////////
        // BANNERS
        ///////////////////////////////////////
        DB::statement("CREATE TABLE IF NOT EXISTS banners (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(191) NOT NULL,
            image_path VARCHAR(191) NOT NULL,
            `order` INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");

        ///////////////////////////////////////
        // COMPLAINTS
        ///////////////////////////////////////
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('type')->nullable(); // complaint, suggestion, inquiry
            $table->string('category')->nullable(); // payment, abuse, technical, etc.
            $table->string('title', 255);
            $table->text('description');
            $table->string('status')->default('open'); // open, in_progress, resolved, closed
            $table->string('priority')->default('normal'); // low, normal, high, urgent
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('banners');
        Schema::dropIfExists('model_trucks');
        Schema::dropIfExists('bids');
        Schema::dropIfExists('auction_participants');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('auction_streams');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('auctions');
        Schema::dropIfExists('listings');
        Schema::dropIfExists('users');
    }
};

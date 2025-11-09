<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_seen')->nullable();
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
            $table->string('section', 50);
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
            $table->json('location')->nullable();
            $table->json('media')->nullable();
            $table->json('documents')->nullable();
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
            $table->timestamp('live_stream_time')->nullable();
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
        // NOTIFICATIONS
        ///////////////////////////////////////
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('title', 150)->nullable();
            $table->string('type', 50);
            $table->json('payload')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
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
            $table->timestamps();

            $table->index(['auction_id']);
            $table->index(['bidder_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bids');
        Schema::dropIfExists('auction_participants');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('auctions');
        Schema::dropIfExists('listings');
        Schema::dropIfExists('users');
    }
};

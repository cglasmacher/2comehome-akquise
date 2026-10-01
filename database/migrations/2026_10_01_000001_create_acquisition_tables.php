<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('agent');
            $t->boolean('active')->default(true);
        });
        Schema::create('search_profiles', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->boolean('active')->default(true);
            $t->json('postal_patterns')->nullable();
            $t->string('center')->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->decimal('radius_km', 8, 2)->nullable();
            $t->string('area_mode')->default('any');
            $t->json('sources');
            $t->json('property_types');
            $t->json('markets');
            $t->decimal('min_price', 14, 2)->nullable();
            $t->decimal('max_price', 14, 2)->nullable();
            $t->decimal('min_area', 10, 2)->nullable();
            $t->decimal('max_area', 10, 2)->nullable();
            $t->timestamps();
        });
        Schema::create('contacts', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->nullable()->index();
            $t->string('phone')->nullable()->index();
            $t->string('provider_type')->default('unclear');
            $t->text('classification_reason')->nullable();
            $t->boolean('contact_blocked')->default(false);
            $t->text('block_reason')->nullable();
            $t->unsignedBigInteger('onoffice_id')->nullable();
            $t->timestamps();
        });
        Schema::create('properties', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('property_type');
            $t->string('market')->default('sale');
            $t->string('postal_code', 5)->nullable()->index();
            $t->string('city')->nullable();
            $t->string('street')->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->boolean('location_approximate')->default(true);
            $t->decimal('price', 14, 2)->nullable();
            $t->decimal('area', 10, 2)->nullable();
            $t->decimal('rooms', 5, 1)->nullable();
            $t->text('description')->nullable();
            $t->unsignedBigInteger('onoffice_id')->nullable();
            $t->timestamps();
        });
        Schema::create('listings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained();
            $t->foreignId('contact_id')->constrained();
            $t->string('source');
            $t->string('external_id');
            $t->text('url')->nullable();
            $t->text('contact_url')->nullable();
            $t->string('availability')->default('online');
            $t->timestamp('first_seen_at');
            $t->timestamp('last_seen_at');
            $t->decimal('price', 14, 2)->nullable();
            $t->json('price_history')->nullable();
            $t->timestamps();
            $t->unique(['source', 'external_id']);
        });
        Schema::create('leads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->unique()->constrained();
            $t->foreignId('contact_id')->constrained();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status')->default('new');
            $t->boolean('acquisition_active')->default(true);
            $t->timestamp('follow_up_at')->nullable()->index();
            $t->text('contact_permission')->nullable();
            $t->string('transfer_state')->default('pending');
            $t->boolean('owner_linked')->default(false);
            $t->timestamp('transferred_at')->nullable();
            $t->text('transfer_error')->nullable();
            $t->timestamps();
        });
        Schema::create('activities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type');
            $t->text('note');
            $t->timestamp('occurred_at');
            $t->unsignedBigInteger('onoffice_id')->nullable();
            $t->timestamps();
        });
        Schema::create('import_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('search_profile_id')->nullable()->constrained()->nullOnDelete();
            $t->string('source');
            $t->string('status');
            $t->unsignedInteger('created_count')->default(0);
            $t->unsignedInteger('updated_count')->default(0);
            $t->unsignedInteger('skipped_count')->default(0);
            $t->text('message')->nullable();
            $t->timestamp('started_at');
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['import_runs', 'activities', 'leads', 'listings', 'properties', 'contacts', 'search_profiles'] as $table) {
            Schema::dropIfExists($table);
        } Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'active']));
    }
};

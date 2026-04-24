<?php

use App\Models\System;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('systems_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(System::class)->constrained();
            $table->unsignedBigInteger('market_id');
            $table->string('type')->default('station');
            $table->string('name');
            $table->json('body')->nullable();
            $table->bigInteger('distance_to_arrival');
            $table->string('allegiance')->nullable();
            $table->string('government')->nullable();
            $table->string('economy')->nullable();
            $table->string('second_economy')->nullable();
            $table->boolean('has_market')->default(false);
            $table->boolean('has_shipyard')->default(false);
            $table->boolean('has_outfitting')->default(false);
            $table->text('other_services')->nullable();
            $table->string('controlling_faction')->nullable();
            $table->timestamp('information_last_updated')->nullable();
            $table->timestamp('market_last_updated')->nullable();
            $table->timestamp('shipyard_last_updated')->nullable();
            $table->timestamp('outfitting_last_updated')->nullable();
            $table->string('slug')->nullable();
            $table->softDeletes();
            $table->unique(['name', 'type', 'distance_to_arrival']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('systems_stations');
    }
};

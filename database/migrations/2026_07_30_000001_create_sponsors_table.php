<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsors', function (Blueprint $table): void {
            $table->id();
            $table->char('source_hash', 40)->unique();
            $table->string('company_name');
            $table->string('slug')->unique();
            $table->string('town')->nullable()->index();
            $table->string('county')->nullable()->index();
            $table->string('postcode', 32)->nullable()->index();
            $table->string('licence_number')->nullable()->index();
            $table->string('organisation_type')->nullable()->index();
            $table->json('routes')->nullable();
            $table->string('rating', 64)->nullable()->index();
            $table->string('status', 64)->nullable()->index();
            $table->timestamp('imported_at')->nullable()->index();
            $table->timestamps();
            $table->index(['status', 'rating', 'town']);
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE sponsors ADD FULLTEXT sponsors_fulltext (company_name, town, county, postcode, licence_number, organisation_type)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsors');
    }
};

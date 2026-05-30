<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('route_details');
        Schema::dropIfExists('route_declarations');
    }

    public function down(): void
    {
        //
    }
};

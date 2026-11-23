<?php

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
        Schema::table('accompanying_visitors', function (Blueprint $table) {
            $table->string('marriage_certificate_path')->nullable()->after('id_photo_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accompanying_visitors', function (Blueprint $table) {
            $table->dropColumn('marriage_certificate_path');
        });
    }
};

<?php

use App\Models\User_declaration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('declaration_receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('secure_token');
            $table->string('receipt_number')->unique();
            $table->foreignIdFor(User_declaration::class,'user_declaration_id')->index()->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('declaration_receipts');
    }
};

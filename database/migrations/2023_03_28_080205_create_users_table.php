<?php

use App\Models\Marital_status;
use App\Models\Sex;
use App\Models\Staff;
use App\Models\Ward;
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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('secure_token');
            $table->foreignIdFor(Staff::class,'staff_id')->index()->constrained()->onDelete('cascade');
            $table->string('file_number')->nullable()->unique();
            $table->string('profile_picture')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('nida')->unique()->nullable();
            $table->string('phone_number')->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('otp')->nullable();
            $table->string('nationality')->nullable();
            $table->string('po_box')->nullable();
            $table->string('village')->nullable();
            $table->foreignIdFor(Ward::class,'ward_id')->nullable()->index()->constrained()->onDelete('cascade');
            $table->foreignIdFor(Marital_status::class,'marital_status_id')->nullable()->index()->constrained()->onDelete('cascade');
            $table->foreignIdFor(Sex::class,'sex_id')->nullable()->index()->constrained()->onDelete('cascade');
            $table->string('date_of_birth')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_password_changed')->default(false);
            $table->string('password');
            $table->rememberToken();
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
        Schema::dropIfExists('users');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUploadFilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('upload_files', function (Blueprint $table) {
            $table->increments('id');
            $table->string('org_type')->default('');
            $table->string('org_name')->default('');
            $table->integer('org_size')->default(0);
            $table->string('file_name')->default('');
            $table->string('html_name')->default('');
            $table->string('conv_name')->default('');
            $table->string('to_lang')->default('');
            $table->integer('job_id')->default(0);
            $table->integer('file_id')->default(0);
            $table->integer('status')->default(0);
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
        Schema::dropIfExists('upload_files');
    }
}

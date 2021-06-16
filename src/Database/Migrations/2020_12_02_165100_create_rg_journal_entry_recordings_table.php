<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRgJournalEntryRecordingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('tenant')->create('rg_journal_entry_recordings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestamps();

            //>> default columns
            $table->softDeletes();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            //<< default columns

            //>> table columns
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('journal_entry_id');
            $table->unsignedBigInteger('financial_account_code');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->string('contact_name', 50)->nullable();
            $table->string('contact_address', 50)->nullable();
            $table->string('description', 250)->nullable();
            $table->string('currency', 3);
            $table->unsignedDecimal('debit', 20,5);
            $table->unsignedDecimal('credit', 20, 5);

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('tenant')->dropIfExists('rg_journal_entry_recordings');
    }
}

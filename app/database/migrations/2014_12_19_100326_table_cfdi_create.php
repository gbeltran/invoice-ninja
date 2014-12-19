<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class TableCfdiCreate extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('cfdi', function($table)
			{
				$table->increments('id');
				$table->string('xml')->nullable();
				$table->string('pdf')->nullable();
				$table->integer('invoice_id')->nullable();
				$table->string('cancel')->nullable();
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
		Schema::drop('cfdi');
	}

}

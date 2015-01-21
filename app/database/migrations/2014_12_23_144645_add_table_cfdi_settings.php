<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTableCfdiSettings extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('cfdi_settings', function($table)
			{
				$table->increments('id');
				$table->string('apisecret')->nullable();
				$table->string('apipublic')->nullable();
				$table->string('posturl')->nullable();
				$table->string('cancelurl')->nullable();
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
		Schema::drop('cfdi_settings');
	}

}

<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddClientsApisatColumns extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('clients', function($table)
		{
			$table->string('rfc')->nullable();
			$table->string('suburb')->nullable();
		});	
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('clients', function($table)
		{			
			$table->dropColumn('rfc');
			$table->dropColumn('suburb');
		});
	}

}

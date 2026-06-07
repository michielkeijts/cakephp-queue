<?php

use Phinx\Migration\BaseMigration;

class AddFailedColumns extends BaseMigration {

	/**
	 * @return void
	 */
	public function change() {
		$this->table('queued_jobs')
			->addColumn('failed', 'datetime', [
				'after' => 'fetched',
				'null' => true,
				'default' => null
			])
			->save();
	}

}

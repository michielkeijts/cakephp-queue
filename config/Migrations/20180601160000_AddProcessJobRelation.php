<?php

use Phinx\Migration\AbstractMigration;

class AddProcessJobRelation extends AbstractMigration {

	/**
	 * Change Method.
	 *
	 * Write your reversible migrations using this method.
	 *
	 * More information on writing migrations is available here:
	 * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
	 *
	 * @return void
	 */
	public function change() {
		$table = $this->table('queued_jobs');

		$table->addColumn('queue_process_id', 'integer', [
			'length' => 11,
			'null' => true,
			'default' => null,
			'after' => 'priority'
		]);
		
		$table->update();
	}

}

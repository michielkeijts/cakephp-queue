<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class MakeNotbeforeNotNull extends AbstractMigration
{
    /**
     * Up Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-up-method
     * @return void
     */
    public function change()
    {
		if ($this->isMigratingUp()) {
			$this->execute("UPDATE queued_jobs SET notbefore = created WHERE notbefore IS NULL");
		}

		$table = $this->table('queued_jobs');

		$table->changeColumn('notbefore', 'datetime', [
			'default' => 'CURRENT_TIMESTAMP',
			'null' => FALSE,
		])->update();
    }
}



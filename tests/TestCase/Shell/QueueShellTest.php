<?php

namespace Queue\Test\TestCase\Shell;

use Cake\Console\ConsoleIo;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Queue\Shell\QueueShell;
use Tools\TestSuite\ConsoleOutput;
use Tools\TestSuite\ToolsTestTrait;

class QueueShellTest extends TestCase {

	use ToolsTestTrait;

	/**
	 * @var \Queue\Shell\QueueShell|\PHPUnit_Framework_MockObject_MockObject
	 */
	public $QueueShell;

	/**
	 * @var \Tools\TestSuite\ConsoleOutput
	 */
	public $out;

	/**
	 * @var \Tools\TestSuite\ConsoleOutput
	 */
	public $err;

	/**
	 * Fixtures to load
	 *
	 * @var array
	 */
	public $fixtures = [
		'plugin.Queue.QueuedJobs',
		'plugin.Queue.QueueProcesses',
	];

	/**
	 * Setup Defaults
	 *
	 * @return void
	 */
	public function setUp() {
		parent::setUp();

		$this->out = new ConsoleOutput();
		$this->err = new ConsoleOutput();
		$io = new ConsoleIo($this->out, $this->err);

		$this->QueueShell = $this->getMockBuilder(QueueShell::class)
			->setMethods(['in', 'err', '_stop'])
			->setConstructorArgs([$io])
			->getMock();

		$this->QueueShell->initialize();

		Configure::write('Queue', [
			'sleeptime' => 2,
			'gcprob' => 10,
			'defaultworkertimeout' => 3,
			'defaultworkerretries' => 1,
			'workermaxruntime' => 5,
			'cleanuptimeout' => 10,
			'exitwhennothingtodo' => false,
			'pidfilepath' => false, // TMP . 'queue' . DS,
			'log' => false,
		]);
	}

	/**
	 * @return void
	 */
	public function testObject() {
		$this->assertTrue(is_object($this->QueueShell));
		$this->assertInstanceOf(QueueShell::class, $this->QueueShell);
	}

	/**
	 * @return void
	 */
	public function testStats() {
		$this->_needsConnection();

		$this->QueueShell->stats();
		$this->assertContains('Total unfinished jobs: 0', $this->out->output());
	}

	/**
	 * @return void
	 */
	public function testSettings() {
		$this->QueueShell->settings();
		$this->assertContains('* cleanuptimeout: 10', $this->out->output());
	}

	/**
	 * @return void
	 */
	public function testAddInexistent() {
		$this->QueueShell->args[] = 'FooBar';
		$this->QueueShell->add();
		$this->assertContains('Error: Task not found: FooBar', $this->out->output());
	}

	/**
	 * @return void
	 */
	public function testAdd() {
		$this->QueueShell->args[] = 'Example';
		$this->QueueShell->add();

		$this->assertContains('OK, job created, now run the worker', $this->out->output(), print_r($this->out->output, true));
	}

	/**
	 * @return void
	 */
	public function testHardReset() {
		$this->QueueShell->hardReset();

		$this->assertContains('OK', $this->out->output(), print_r($this->out->output, true));
	}

	/**
	 * @return void
	 */
	public function testHardResetIntegration() {
		/** @var \Queue\Model\Table\QueuedJobsTable $queuedJobsTable */
		$queuedJobsTable = TableRegistry::get('Queue.QueuedJobs');
		$queuedJobsTable->createJob('Example');

		$queuedJobs = $queuedJobsTable->find()->count();
		$this->assertSame(1, $queuedJobs);

		$this->QueueShell->runCommand(['hard_reset']);

		$this->assertContains('OK', $this->out->output(), print_r($this->out->output, true));

		$queuedJobs = $queuedJobsTable->find()->count();
		$this->assertSame(0, $queuedJobs);
	}

	/**
	 * @return void
	 */
	public function testReset() {
		$this->QueueShell->reset();

		$this->assertContains('0 jobs reset.', $this->out->output(), print_r($this->out->output, true));
	}

	/**
	 * @return void
	 */
	public function testRerun() {
		$this->QueueShell->rerun('Foo');

		$this->assertContains('0 jobs reset for re-run.', $this->out->output(), print_r($this->out->output, true));
	}

	/**
	 * @return void
	 */
	public function testEnd() {
		$this->QueueShell->end();

		$this->assertContains('No processes found', $this->out->output(), print_r($this->out->output, true));
	}

	/**
	 * @return void
	 */
	public function testKill() {
		$this->QueueShell->kill();

		$this->assertContains('No processes found', $this->out->output(), print_r($this->out->output, true));
	}

	/**
	 * @return void
	 */
	public function testRetry() {
		$file = TMP . 'task_retry.txt';
		if (file_exists($file)) {
			unlink($file);
		}

		$this->_needsConnection();

		$this->QueueShell->args[] = 'RetryExample';
		$this->QueueShell->add();

		$expected = 'This is a very simple example of a QueueTask and how retries work';
		$this->assertContains($expected, $this->out->output());

		$this->QueueShell->runworker();

		$this->assertContains('Job did not finish, requeued after try 1.', $this->out->output());
	}

	/**
	 * @return void
	 */
	public function testTimeNeeded() {
		$this->QueueShell = $this->getMockBuilder(QueueShell::class)->setMethods(['_time'])->getMock();

		$first = time();
		$second = $first - HOUR + MINUTE;
		$this->QueueShell->expects($this->at(0))->method('_time')->will($this->returnValue($first));
		$this->QueueShell->expects($this->at(1))->method('_time')->will($this->returnValue($second));
		$this->QueueShell->expects($this->exactly(2))->method('_time')->withAnyParameters();

		$result = $this->invokeMethod($this->QueueShell, '_timeNeeded');
		$this->assertSame('3540s', $result);
	}

	/**
	 * @return void
	 */
	public function testMemoryUsage() {
		$result = $this->invokeMethod($this->QueueShell, '_memoryUsage');
		$this->assertRegExp('/^\d+MB/', $result, 'Should be e.g. `17MB` or `17MB/1GB` etc.');
	}

	/**
	 * @return void
	 */
	public function testStringToArray() {
		$string = 'Foo,Bar,';
		$result = $this->invokeMethod($this->QueueShell, '_stringToArray', [$string]);

		$expected = [
			'Foo',
			'Bar',
		];
		$this->assertSame($expected, $result);
	}

	/**
	 * Helper method for skipping tests that need a real connection.
	 *
	 * @return void
	 */
	protected function _needsConnection() {
		$config = ConnectionManager::getConfig('test');
		$this->skipIf(strpos($config['driver'], 'Mysql') === false, 'Only Mysql is working yet for this.');
	}

}

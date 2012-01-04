<?php

/**
 * @group nergal
 * @group nergal.modules
 * @group nergal.modules.beanstalk
 */
class KohanaBeanstalkTest extends PHPUnit_Framework_TestCase {

	static protected $test_instance;

	public function setUp()
	{
		self::$test_instance = new TestMockQueue;
	}

	public function tearDown()
	{
		$mock = $this->getMock('Beanstalk', array('disconnect'));
		$mock->expects($this->any())
			->method('disconnect');
		
		self::$test_instance->setBeans($mock);
		self::$test_instance = NULL;
	}

	/**
	 * Tests the cache static instance method
	 */
	public function testInstance()
	{
		// Try and load a Queue instance
		$this->assertInstanceOf('Kohana_Queue', self::$test_instance);
		
		// TODO: Test singleton
	}

	public function testGet()
	{
		$tube = 'testTube';
		$value = 'testtest';
		$job_id = 123;

		// Empty tube
		$mock = $this->getMock('Beanstalk', array('ignore', 'watch', 'reserve'));
		$mock->expects($this->once())
			->method('ignore')
			->with('default');
		$mock->expects($this->once())
			->method('reserve')
			->will($this->returnValue(NULL));
		
		self::$test_instance->setBeans($mock);
		$this->assertEquals(0, self::$test_instance->proceed($tube, function($item) { return $item; } ));
		
		// Default tube
		$mock = $this->getMock('Beanstalk', array('ignore', 'watch', 'reserve'));
		$mock->expects($this->never())
			->method('ignore');
		
		self::$test_instance->setBeans($mock);
		self::$test_instance->proceed('default', NULL);
		
		// Callback validation
		$mock = $this->getMock('Beanstalk', array('ignore', 'watch', 'reserve', 'touch', 'delete'));
		$mock->expects($this->exactly(2))
			->method('reserve')
			->will($this->onConsecutiveCalls(
				array(
					'id' => $job_id,
					'body' => serialize($value),
				),
				NULL
			));
		self::$test_instance->setBeans($mock);
		$this->assertEquals(0, self::$test_instance->proceed($tube, NULL));

		// Success calling
		$mock = $this->getMock('Beanstalk', array('ignore', 'watch', 'reserve', 'touch', 'delete'));
		$mock->expects($this->any())
			->method('reserve')
			->will($this->onConsecutiveCalls(
				array(
					'id' => $job_id,
					'body' => serialize($value),
				),
				NULL
			));
		$mock->expects($this->once())
			->method('touch')
			->with($this->equalTo($job_id));
		$mock->expects($this->once())
			->method('delete')
			->with($this->equalTo($job_id));

		self::$test_instance->setBeans($mock);
		$self = $this;
		$this->assertEquals(1, self::$test_instance->proceed($tube, function($id, $item) use ($self, $job_id, $value) {
			$self->assertEquals($id, $job_id);
			$self->assertEquals($item, $value);
			return TRUE;
		}));

		// Bury event
		$mock = $this->getMock('Beanstalk', array('ignore', 'watch', 'reserve', 'bury'));
		$mock->expects($this->any())
			->method('reserve')
			->will($this->onConsecutiveCalls(
				array(
					'id' => $job_id,
					'body' => serialize($value),
				),
				NULL
			));
		$mock->expects($this->once())
			->method('bury')
			->with($this->equalTo($job_id));

		self::$test_instance->setBeans($mock);
		$this->assertEquals(0, self::$test_instance->proceed($tube, function($id, $item) {
			return FALSE;
		}));

	}
	
	public function testQueue()
	{
		$mock = $this->getMock('Beanstalk');
		self::$test_instance->setBeans($mock);
		
		$this->assertSame($mock, self::$test_instance->queue());

	}

	public function testSet()
	{
		$value = 'foobar';
		$value2 = 'snafu';
		$return = 'testtest';

		// Set a new property
		$mock = $this->getMock('Beanstalk', array('useTube', 'put'));
		$mock->expects($this->once())
			->method('useTube')
			->with($this->equalTo($value));
			
		$mock->expects($this->once())
			->method('put')
			->with(
				2,
				4,
				8,
				$this->equalTo(serialize($value2))
			)
			->will($this->returnValue($return));
		self::$test_instance->setBeans($mock);
		
		$_return = self::$test_instance->add($value, $value2, 2 /* priority */, 4 /* delay */, 8 /* ttr */);
		$this->assertEquals($_return, $return);
		
		$mock = $this->getMock('Beanstalk', array('useTube', 'put'));
		$mock->expects($this->once())
			->method('put')
			->will($this->returnValue(NULL));
		self::$test_instance->setBeans($mock);
		
		try {
			self::$test_instance->add($value, $value2);
		} catch (Kohana_Exception $e) {
			return;
		}
		
		$this->fail('An expected exception has not been raised.');
		
	}
}

class TestMockQueue extends Kohana_Queue {
	public function __construct() {}

	public function setBeans($mock) {
		$this->_beans = $mock;
	}
}
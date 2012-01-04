<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Обработка очередей
 *
 * @author nergal
 * @package beanstalk
 */
abstract class Kohana_Queue
{
    /**
     * Задержка при выбрке
     * @const
     */
    const DEFAULT_DELAY = 0;       // без задержки

    /**
     * Приоритет сообщения
     * @const
     */
    const DEFAULT_PRIORITY = 1024; // 0 - самый срочный, макс. значение - 4294967295

    /**
     * Время на выполнение задачи, Time To Run
     * @const
     */
    const DEFAULT_TTR = 60;        // 1 минута

    /**
     * Время ожидания новой задачи, если очередь пуста
     * @const
     */
    const DEFAULT_TIMEOUT = 1;     // 1 секунда

    /**
     * Пулл инстанций
     *
     * @static
     * @access protected
     * @var array of Kohana_Queue
     */
    protected static $_instance = array();

    /**
     * Конфигурация по-умолчанию
     *
     * @static
     * @access protected
     * @var $_default_config array
     */
    protected static $_default_config = array();

    /**
     * Инстанцирование объекта очереди
     *
     * @param string $name
     * @param array $config
     * @throws Kohana_Exception
     * @return Kohana_Queue
     */
    public static function instance($name = 'default', $config = array())
    {
        if ( ! isset(self::$_instance[$name])) {
            if ( ! class_exists('Beanstalk')) {
                if ($path = Kohana::find_file('classes', 'Beanstalk')) {
                    require_once $path;
                } else {
                    throw new Kohana_Exception('Class Beanstalk not found');
                }
            }

            if (empty(self::$_default_config)) {
                self::$_default_config = Kohana::$config->load('beanstalk')->as_array();
            }

            $config = array_merge(self::$_default_config, $config);

            self::$_instance[$name] = new Queue($config);
        }

        return self::$_instance[$name];
    }

    /**
     * Создание очереди
     *
     * @constructor
     * @access protected
     * @return void
     */
    protected function __construct($config)
    {
        $this->_beans = new Beanstalk($config);
    }

    /**
     * Установка задачи в очередь
     *
     * @param string $queue
     * @param mixed $params
     * @param integer $priority
     * @param integer $delay
     * @param integer $ttr
     * @throws Kohana_Exception
     * @return Ambigous <number, boolean>
     */
    public function add($queue, $params, $priority = self::DEFAULT_PRIORITY, $delay = self::DEFAULT_DELAY, $ttr = self::DEFAULT_TTR)
    {
        $this->_beans->useTube($queue);
        $params = serialize($params);

        if ($job_id = $this->_beans->put($priority, $delay, $ttr, $params)) {
            return $job_id;
        }

        throw new Kohana_Exception('Cant set new job in '.$queue);
    }

    /**
     * Возврат объекта очереди
     *
     * @return Beanstalk
     */
    public function queue()
    {
        return $this->_beans;
    }

    /**
     * Обработка очереди
     *
     * @param string $queue
     * @param callaback $callback
     * @return integer
     */
    public function proceed($queue, $callback)
    {
        if ($queue !== 'default') {
            $this->_beans->ignore('default');
        }

        $this->_beans->watch($queue);
        $count = 0;

        while ($job = $this->_beans->reserve(self::DEFAULT_TIMEOUT)) {
            if (is_array($job) AND isset($job['id'])) {
                $job_id = intVal($job['id']);
                $job_body = unserialize($job['body']);

                if (is_callable($callback)) {
                    try {
                        if (call_user_func_array($callback, array($job_id, $job_body))) {
                            $count++;

                            $this->_beans->touch($job_id);
                            $this->_beans->delete($job_id, self::DEFAULT_PRIORITY, self::DEFAULT_DELAY);
                        } else {
                            throw new Exception('Cant call callback');
                        }
                    } catch (Exception $e) {
                        Kohana::$log->add(Log::WARNING, 'Unable to execute job width id='.$job_id.'. Buried with message "'.$e->getMessage().'"');
                        $this->_beans->bury($job_id, self::DEFAULT_PRIORITY);
                    }
                }
            }
        }

        return $count;
    }

    /**
     * Деструктор класса
     *
     * @destructor
     */
    public function __destruct()
    {
        $this->_beans->disconnect();
    }
}

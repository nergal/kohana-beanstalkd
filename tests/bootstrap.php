<?php

if ( ! defined('SYSPATH')) {
    error_reporting(E_ALL | E_STRICT);

    define('SYSPATH', dirname(__FILE__));

    require_once 'PHPUnit/Framework/TestCase.php';
    require_once __DIR__ . '/../classes/Beanstalk.php';
    require_once __DIR__ . '/../classes/kohana/queue.php';

    // Mock objects

    class Kohana {
        public static $log;
    }

    class Kohana_Exception extends Exception { }

    class Log {
        const WARNING = 1;
        public function add($str) { }
    }

    Kohana::$log = new Log;
}
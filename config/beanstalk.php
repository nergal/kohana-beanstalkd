<?php defined('SYSPATH') or die('No direct access allowed.');

return array(
	/**
         * Whether to make the connection persistent or
         * not, defaults to `true` as the FAQ recommends
         * persistent connections.
         */
	'persistent'   => TRUE,
	
	/**
	 * The beanstalk server hostname or IP address to
         * connect to, defaults to `127.0.0.1`.
         */
	'host'         => '127.0.0.1',
	
	/**
	 * The port of the server to connect to, defaults
         * to `11300`.
         */
	'port'         => 11300,
	
	/**
	 * Timeout in seconds when establishing the
         * connection, defaults to `1`.
         */
	'timeout'      => 1,
);

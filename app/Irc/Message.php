<?php

/**
 * Aki-chan (version 1.0-dev released on $WCDATE$, http://aki.aurielle.cz)
 *
 * Copyright (c) 2013 Václav Vrbka (aurielle@aurielle.cz)
 *
 * For the full copyright and license information, please view
 * the file license.md that was distributed with this source code.
 */

namespace Aki\Irc;

use Aki, Nette, React;



class Message extends Nette\Object
{
	/** @var Aki\Irc\Connection */
	protected $connection;

	/** @var array */
	private $buffer;

	/** Events */
	public $onDataReceived = array();
	public $onDataSent = array();


	public function __construct(Connection $connection)
	{
		$this->connection = $connection;
		$_this = $this;

		// Handle incoming data with our buffer
		$connection->on('data', function($data, $conn) use($_this) {
			$_this->buffer($data, callback($_this, 'onDataReceived'), array($conn));
		});
	}


	/**
	 * Sends command to the server.
	 * @todo use PHP_EOL?
	 * @param  string $data
	 * @return Message Provides a fluent interface.
	 */
	public function send($data)
	{
		// Get rid of any \r\n at end of the line
		$data = rtrim($data);

		if ($this->connection->write($data . "\r\n")) {
			$this->onDataSent($data, $this->connection);
		}

		return $this;
	}


	/**
	 * Serves lines data to callback function one by one
	 * @internal
	 * @param  string $rawData
	 * @param  callable $callback
	 * @param  array  $params
	 * @return void
	 */
	public function buffer($rawData, $callback, $params = array())
	{
		if (!$callback instanceof Nette\Callback) {
			$callback = Nette\Callback::create($callback);
		}

		// Determine hash of the callback to distinguish different calls
		$cb = $callback->getNative();
		if ($cb instanceof \Closure) {
			// spl_object_hash($callback) results in same hashes for different callbacks
			$hash = spl_object_hash($cb);
		} elseif (is_string($cb) && $cb[0] === "\0") {
			// lambda functions
			$hash = md5($cb);
		} else {
			is_callable($cb, TRUE, $textual);
			$hash = md5($textual);
		}

		// Split raw data into pieces of each line and clean them from control characters
		// \xFF is intentionally added here to detect line endings (anyone knows of better solution?)
		// ltrim gets rid of possible control characters at start (\r, \n)
		$rawData = str_replace(array("\r\n", "\r", "\n"), array("\n", "\n", "\xFF\n"), ltrim($rawData));
		$data = explode("\n", $rawData);

		// If something's in buffer, use it and clean the buffer
		if (isset($this->buffer[$hash])) {
			$first = $this->buffer[$hash] . array_shift($data);
			$this->buffer[$hash] = NULL;
			$data = array_merge(array($first), $data);
		}

		// Detect last possibly incomplete line
		// Very last line could be an empty string
		$last = array_pop($data);
		if (empty($last)) {
			$last = array_pop($data);
		}

		// If the last line does not end with our control character,
		// put it into the buffer and stop it from processing.
		// Else merge the array back and proceed normally.
		if (!Nette\Utils\Strings::endsWith($last, "\xFF")) {
			$this->buffer[$hash] = $last;

		} else {
			$data = array_merge($data, array($last));
		}

		// Now call the callback
		foreach ($data as $d) {
			call_user_func_array($callback, array_merge(array(rtrim($d, "\xFF")), (array) $params));
		}
	}
}
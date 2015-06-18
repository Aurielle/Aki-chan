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
			$_this->buffer($data, callback($_this, 'processEvent'));
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
			$this->onDataSent($data);
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


	/**
	 * Converts server responses to events
	 * @internal
	 * @param  string $rawData
	 * @return Aki\Irc\Event\IEvent
	 */
	public function processEvent($rawData)
	{
		$buffer = $rawData;
		$prefix = '';

		// If the event has a prefix, extract it
		if (substr($buffer, 0, 1) === ':') {
			$parts = explode(' ', $buffer, 3);
			$prefix = substr(array_shift($parts), 1);
			$buffer = implode(' ', $parts);
		}

		// Parse the command and arguments
		list($cmd, $args) = array_pad(explode(' ', $buffer, 2), 2, null);

		// Parse the server name or hostmask
		if (strpos($prefix, '@') === FALSE) {
			$hostmask = new Hostmask(NULL, NULL, $prefix);
		} else {
			$hostmask = Hostmask::fromString($prefix);
		}

		// Parse the event arguments depending on the event type
		$cmd = strtolower($cmd);
		switch ($cmd) {
			case 'names':
			case 'nick':
			case 'quit':
			case 'ping':
			case 'pong':
			case 'error':
			case 'part':
				$args = array_filter(array(ltrim($args, ':')));
				break;

			case 'privmsg':
			case 'notice':
				$args = $this->parseArguments($args, 2);
				list($source, $ctcp) = $args;
				if (substr($ctcp, 0, 1) === "\x01" && substr($ctcp, -1) === "\x01") {
					$ctcp = substr($ctcp, 1, -1);
					$reply = $cmd === 'notice';
					list($cmd, $args) = array_pad(explode(' ', $ctcp, 2), 2, array());
					$cmd = strtolower($cmd);
					switch ($cmd) {
						case 'version':
						case 'time':
						case 'finger':
						case 'ping':
						case 'source':
							if ($reply) {
								$args = array($args);
							}
							break;

						case 'action':
							$args = array($source, $args);
							break;
					}
				}
				// This fixes the issue that seems to occur, but why does it?
				if (!is_array($args)) {
					$args = array($args);
				}
				break;

			case 'topic':
			case 'invite':
			case 'join':
				$args = $this->parseArguments($args, 2);
				break;

			case 'kick':
			case 'mode':
				$args = $this->parseArguments($args, 3);
				break;

			default:
				$args = ltrim(substr($args, strpos($args, ' ') + 1), ':');
				break;
		}

		if (ctype_digit($cmd)) {
			$event = new Event\Response($cmd, $args);

		} else {
			try {
				$event = new Event\Request($cmd, $args, isset($reply));
				$event->setHostmask($hostmask);
			} catch (Nette\MemberAccessException $e) {
				// invalid message, we don't know how to handle it
				// log and throw it away
				Nette\Diagnostics\Debugger::log($e, Nette\Diagnostics\Debugger::ERROR);
				return;
			}
		}

		$event->setRawData($rawData);
		$this->onDataReceived($event);

		return $event;
	}


	protected function parseArguments($args, $count = -1)
	{
		return preg_split('/ :?/S', ltrim($args, ':'), $count);
	}
}
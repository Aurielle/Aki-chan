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
use React\EventLoop\LoopInterface;
use Aki\Stream\Stdout;



/**
 * Brain of Aki. Controls connection to the server and responds accordingly.
 */
class Bot extends Nette\Object
{
	/** @var React\EventLoop\LoopInterface */
	protected $eventLoop;

	/** @var React\Stream\Stream */
	protected $connection;

	/** @var Aki\Irc\Message */
	protected $message;

	/** @var Aki\Stream\Stdout */
	protected $stdout;

	/** @var Aki\Irc\Session */
	protected $session;

	/** @var Aki\Irc\Logger */
	protected $logger;


	/** Events */
	public $onStartup = array();
	public $onDisconnect = array();



	public function __construct(LoopInterface $eventLoop, Connection $connection, Message $message, Stdout $stdout, Session $session, Logger $logger)
	{
		$this->eventLoop = $eventLoop;
		$this->connection = $connection;
		$this->message = $message;
		$this->stdout = $stdout;
		$this->session = $session;
		$this->logger = $logger;

		$_this = $this;

		// Graceful shutdown
		$connection->on('close', function($conn) use($_this, $logger) {
			$_this->onDisconnect($_this, $conn);
			$logger->logMessage($logger::WARNING, 'Connection closed, exiting.');
			exit;
		});
	}


	/**
	 * Runs the event loop and connects to the server.
	 * @return void
	 */
	public function run()
	{
		$this->onStartup($this);
		$this->eventLoop->run();
	}


	/**
	 * Sends command to the server.
	 * @param  string $data
	 * @return Bot Provides a fluent interface.
	 */
	public function send($data)
	{
		return $this->message->send($data);
	}
}
<?php

/**
 * Aki-chan (version 1.0-dev released on $WCDATE$, http://aki.aurielle.cz)
 *
 * Copyright (c) 2013 Václav Vrbka (aurielle@aurielle.cz)
 *
 * For the full copyright and license information, please view
 * the file license.md that was distributed with this source code.
 */

namespace Aki\Irc\Bridge;

use Aki, Nette, React;
use Aki\Irc;
use Aki\Irc\ServerCodes;
use Kdyby\Events;



/**
 * Handles connecting to the server.
 */
class Connection extends Nette\Object implements Events\Subscriber
{
	/** @var bool */
	protected $connecting = FALSE;

	/** @var Aki\Irc\Message */
	protected $message;

	/** @var Aki\Irc\Session */
	protected $session;

	/** @var Aki\Irc\Network */
	protected $network;

	/** @var Aki\Irc\Logger */
	protected $logger;


	/** Events */
	public $onConnectingBegin = array();
	public $onConnectingEnd = array();
	public $onEndOfMotd = array();


	public function __construct(Irc\Message $message, Irc\Session $session, Irc\Network $network, Irc\Logger $logger)
	{
		$this->message = $message;
		$this->session = $session;
		$this->network = $network;
		$this->logger = $logger;
	}


	/**
	 * Sends identification to the server.
	 * @param  string $timer
	 * @param  React\EventLoop\LoopInterface $loop
	 * @return void
	 */
	public function connect($timer, React\EventLoop\LoopInterface $loop)
	{
		if (!$this->isConnecting()) {
			return;
		}

		if ($this->network->password) {
			$this->message->send(sprintf('PASS %s', $this->network->password));
		}

		$this->session->setNick($nick = $this->network->nick);
		$this->message->send(sprintf('USER %s aurielle.cz %s :%s', $this->network->ident, $nick, $this->network->user));
		$this->message->send(sprintf('NICK %s', $nick));
		$this->logger->logMessage(Irc\ILogger::INFO, 'Logging in as %s...', $nick);
	}


	/**
	 * Handles server responses during connection phase.
	 * @param  string $data
	 * @param  Aki\Irc\Connection $conn
	 * @return void
	 */
	public function onDataReceived($data, Irc\Connection $connection)
	{
		if (!$this->isConnecting()) {
			return;
		}

		// All responses should have more than one word (not checked with RFC)
		$tmp = explode(' ', $data);

		// Numeric responses (@see Aki\Irc\ServerCodes for some response codes)
		if (!is_numeric($tmp[1])) {
			return;
		}

		switch ((int) $tmp[1]) {
			// Server welcome message
			case ServerCodes::WELCOME:
				$this->logger->logMessage(Irc\ILogger::INFO, 'Received server welcome message');
				break;

			// Bot's nick is already on the server, use a different one
			case ServerCodes::NICK_USED:
				$this->alternativeNick();
				break;

			// Save our unique ID (what is it for?)
			case ServerCodes::UNIQUE_ID:
				$this->session->setUniqueId($tmp[3]);
				$this->logger->logMessage(Irc\ILogger::INFO, 'Unique ID (%s) saved', $tmp[3]);
				break;

			// MOTD ends, begin identifying phase
			case ServerCodes::MOTD_END:
			case ServerCodes::NO_MOTD:
			case ServerCodes::SPAM:
				$this->logger->logMessage(Irc\ILogger::INFO, 'Received end of MOTD');
				$this->onEndOfMotd();
				break;

			// Unhandled response
			default:
				break;
		}
	}


	/**
	 * Issues alternative nick to the bot.
	 * @return void
	 */
	protected function alternativeNick()
	{
		static $nicks;

		// Fill up the variable if called for the first time
		if ($nicks === NULL) {
			$nicks = $this->network->alternativeNicks;
		}

		// Alternative nicks are exhausted, quit
		// onDisconnect will get called automatically (end of stream)
		if ($nicks === array()) {
			$this->message->send('QUIT :No alternative nicks available.');
			$this->logger->logMessage(Irc\ILogger::ERROR, 'No more alternative nicks available');
			return;
		}

		// Select another new nick and send it to the server
		$newNick = array_shift($nicks);
		$this->session->setNick($newNick);
		$this->message->send(sprintf('NICK %s', $newNick));
		$this->logger->logMessage(Irc\ILogger::NOTICE, 'Using alternative nick %s', $newNick);
	}


	public function isConnecting()
	{
		return $this->connecting;
	}


	public function setConnecting($connecting)
	{
		$this->connecting = (bool) $connecting;
		if ($this->connecting) {
			$this->onConnectingBegin($this);

		} else {
			$this->onConnectingEnd($this);
		}

		return $this;
	}


	public function getSubscribedEvents()
	{
		return array('Aki\Irc\Message::onDataReceived');
	}
}
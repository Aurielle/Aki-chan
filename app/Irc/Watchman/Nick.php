<?php

/**
 * Aki-chan (version 1.0-dev released on $WCDATE$, http://aki.aurielle.cz)
 *
 * Copyright (c) 2013 Václav Vrbka (aurielle@aurielle.cz)
 *
 * For the full copyright and license information, please view
 * the file license.md that was distributed with this source code.
 */

namespace Aki\Irc\Watchman;

use Aki, Nette, React;
use Aki\Irc;
use Aki\Irc\ServerCodes;
use Kdyby\Events;



/**
 * Watches for changes of bot's nick.
 */
class Nick extends Nette\Object implements Events\Subscriber
{
	/** @var Aki\Irc\Session */
	protected $session;

	/** @var Aki\Irc\Logger */
	protected $logger;


	public function __construct(Irc\Session $session, Irc\Logger $logger)
	{
		$this->session = $session;
		$this->logger = $logger;
	}

	/**
	 * Watches for changes of bot's nick.
	 * @param  Irc\Event\IEvent $data
	 * @return void
	 */
	public function onDataReceived($data)
	{
		// Only watch for specific numeric codes
		if (!$data instanceof Irc\Event\Response || !in_array($data->code, Irc\Event\Response::$nickSetters)) {
			return;
		}

		$tmp = explode(' ', $data->rawData);

		// Store current server to our variable
		$server = ltrim($tmp[0], ':');
		if ($this->session->server !== $server) {
			$this->session->setServer($server);
			$this->logger->logMessage(Irc\ILogger::INFO, 'Updating server to %s', $server);
		}

		// Store current nick to our variable
		$nick = $tmp[2];
		if ($this->session->nick !== $nick) {
			$this->session->setNick($nick);
			$this->logger->logMessage(Irc\ILogger::INFO, 'Updating nick to %s', $nick);
		}
	}

	public function getSubscribedEvents()
	{
		return array('Aki\Irc\Message::onDataReceived');
	}
}
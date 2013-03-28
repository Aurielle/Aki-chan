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
 * Watches for channel joins/parts/kicks/bans.
 */
class Channels extends Nette\Object implements Events\Subscriber
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
	 * Watches for channel joins/parts/kicks/bans
	 * @param  string $data
	 * @param  Aki\Irc\Connection $connection
	 * @return void
	 */
	public function onDataReceived($data, Irc\Connection $connection)
	{
		$tmp = explode(' ', $data, 5);
		if ($tmp[1] === 'JOIN') {
			$channel = ltrim($tmp[2], ':');
			$this->session->channelJoined($channel);
			$this->logger->logMessage(Irc\ILogger::INFO, 'Joined channel %s', $channel);

		} elseif ($tmp[1] === 'KICK' && $tmp[3] === $this->session->nick) {
			$channel = ltrim($tmp[2], ':');
			$this->session->channelKicked($channel);
			$this->logger->logMessage(Irc\ILogger::WARNING, 'Kicked from channel %s by %s (reason: %s)', $channel, substr($tmp[0], 1, strpos($tmp[0], '!') - 1), ltrim($tmp[4], ':'));
		}
	}

	public function getSubscribedEvents()
	{
		return array('Aki\Irc\Message::onDataReceived');
	}
}
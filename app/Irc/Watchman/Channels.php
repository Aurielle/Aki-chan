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
	 * @param  Irc\Event\IEvent $data
	 * @return void
	 */
	public function onDataReceived($data)
	{
		if ($data->type === Irc\Event\Request::TYPE_JOIN && $data->nick === $this->session->nick) {
			$this->session->channelJoined($data->channel);
			$this->logger->logMessage(Irc\ILogger::INFO, 'Joined channel %s', $data->channel);

		} elseif ($data->type === Irc\Event\Request::TYPE_KICK && $data->user === $this->session->nick) {
			$this->session->channelKicked($data->channel);
			$this->logger->logMessage(Irc\ILogger::WARNING, 'Kicked from channel %s by %s (reason: %s)', $data->channel, $data->getNick(), $data->comment);
		}
	}

	public function getSubscribedEvents()
	{
		return array('Aki\Irc\Message::onDataReceived');
	}
}
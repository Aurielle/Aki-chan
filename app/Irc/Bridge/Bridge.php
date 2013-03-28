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
use Kdyby\Events;



/**
 * Manages correct connection to the IRC server.
 */
class Bridge extends Nette\Object implements Events\Subscriber
{
	/** @var React\EventLoop\LoopInterface */
	protected $eventLoop;

	/** @var Aki\Irc\Connection */
	protected $connection;

	/** @var Aki\Irc\Network */
	protected $network;

	/** @var Aki\Irc\Logger */
	protected $logger;

	/** @var Aki\Irc\Bridge\Connection */
	protected $bridgeConnection;

	/** @var Aki\Irc\Bridge\Identification */
	protected $bridgeIdentification;

	/** @var Aki\Irc\Bridge\Channels */
	protected $bridgeChannels;


	public function __construct(React\EventLoop\LoopInterface $eventLoop, Irc\Connection $connection, Irc\Network $network, Irc\Logger $logger,
		Connection $bridgeConnection, Identification $bridgeIdentification, Channels $bridgeChannels)
	{
		$this->eventLoop = $eventLoop;
		$this->connection = $connection;
		$this->network = $network;
		$this->logger = $logger;

		$this->bridgeConnection = $bridgeConnection;
		$this->bridgeIdentification = $bridgeIdentification;
		$this->bridgeChannels = $bridgeChannels;
	}


	public function onStartup(Irc\Bot $bot)
	{
		$this->bridgeConnection->setConnecting(TRUE);
		$this->eventLoop->addTimer(0.3, callback($this->bridgeConnection, 'connect'));

		$logger = $this->logger;
		$network = $this->network;
		$this->connection->once('data', function() use ($logger, $network) {
			$logger->logMessage($logger::INFO, 'Connected to %s on port %d', $network->server, $network->port);
		});
	}


	public function onEndOfMotd()
	{
		$this->eventLoop->addTimer(0.8, callback($this->bridgeIdentification, 'identify'));
		$this->eventLoop->addPeriodicTimer(2, callback($this->bridgeChannels, 'handleJoinChannels'));
	}


	public function joinChannel($channel)
	{
		return $this->bridgeChannels->joinChannel('#' . ltrim($channel, '#'));
	}


	public function getSubscribedEvents()
	{
		return array('Aki\Irc\Bot::onStartup', 'Aki\Irc\Bridge\Connection::onEndOfMotd');
	}
}
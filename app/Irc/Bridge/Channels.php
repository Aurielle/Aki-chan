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
 * Handles channel joining.
 */
class Channels extends Nette\Object implements Events\Subscriber
{
	/** @var Aki\Irc\Message */
	protected $message;

	/** @var Aki\Irc\Bridge\Connection */
	protected $bridgeConnection;

	/** @var Aki\Irc\Session */
	protected $session;

	/** @var Aki\Irc\Network */
	protected $network;


	public function __construct(Irc\Message $message, Connection $bridgeConnection, Irc\Session $session, Irc\Network $network)
	{
		$this->message = $message;
		$this->bridgeConnection = $bridgeConnection;
		$this->session = $session;
		$this->network = $network;
	}


	public function handleJoinChannels($timer, React\EventLoop\LoopInterface $loop)
	{
		if (!$this->bridgeConnection->isConnecting()) {
			$loop->cancelTimer($timer);
			return;
		}

		// waiting for identification
		if ($this->network->nickPassword && $this->network->setup->nickserv && !$this->session->isIdentified()) {
			return;
		}

		// no password provided || identification done, proceed with joining
		$loop->cancelTimer($timer);
		$channels = $this->network->channels;

		foreach ($channels as $channel) {
			$this->joinChannel('#' . ltrim($channel, '#'));
		}

		// Stop connecting phase
		$this->bridgeConnection->setConnecting(FALSE);
	}


	public function joinChannel($channel)
	{
		$this->message->send('JOIN ' . $channel);
	}



	/**
	 * Handles replies to various messages from ChanServ.
	 * @param  string $data
	 * @param  Aki\Irc\Connection $connection
	 * @return void
	 */
	public function onDataReceived($data, Irc\Connection $connection)
	{
		$tmp = explode(' ', $data);
		if (!($tmp[1] === 'NOTICE' && Nette\Utils\Strings::startsWith(ltrim($tmp[0], ':'), $this->network->setup->chanserv))) {
			return;
		}

		$msg = Irc\Utils::stripFormatting(ltrim($tmp[3], ':'));
		// @todo
	}


	public function getSubscribedEvents()
	{
		return array('Aki\Irc\Message::onDataReceived');
	}
}
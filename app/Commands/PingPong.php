<?php

/**
 * Aki-chan (version 1.0-dev released on $WCDATE$, http://aki.aurielle.cz)
 *
 * Copyright (c) 2013 Václav Vrbka (aurielle@aurielle.cz)
 *
 * For the full copyright and license information, please view
 * the file license.md that was distributed with this source code.
 */

namespace Aki\Commands;

use Aki, Nette, React;
use Kdyby\Events;



/**
 * Responds to PING events sent by server,
 * keeping connection alive.
 */
class PingPong extends Nette\Object implements Events\Subscriber
{
	/** @var Aki\Irc\Message */
	protected $message;



	public function __construct(Aki\Irc\Message $message)
	{
		$this->message = $message;
	}


	/**
	 * Responds to PING event.
	 * @param  Aki\Irc\Event\IEvent $data
	 * @return void
	 */
	public function onDataReceived($data)
	{
		if (!($data->type === Aki\Irc\Event\Request::TYPE_PING && !$data->isCtcp())) {
			return;
		}

		$this->message->send('PONG ' . $data->server);
	}


	public function getSubscribedEvents()
	{
		return array('Aki\Irc\Message::onDataReceived');
	}
}
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
 * Logs all data communication to the server console.
 */
class CommunicationLogger extends Nette\Object implements Events\Subscriber
{
	/** @var Aki\Irc\Bot */
	protected $bot;

	/** @var Aki\Stream\Stdout */
	protected $stdout;



	public function __construct(Aki\Irc\Bot $bot, Aki\Stream\Stdout $stdout)
	{
		$this->bot = $bot;
		$this->stdout = $stdout;
	}


	public function onDataReceived($data, $connection)
	{
		$data = Aki\Irc\Utils::stripFormatting($data);
		fwrite($this->stdout->socket, "< $data\n");
	}

	public function onDataSent($data, $connection)
	{
		$data = Aki\Irc\Utils::stripFormatting($data);
		fwrite($this->stdout->socket, "> $data\n");
	}

	public function getSubscribedEvents()
	{
		return array('Aki\Irc\Message::onDataReceived', 'Aki\Irc\Message::onDataSent');
	}
}
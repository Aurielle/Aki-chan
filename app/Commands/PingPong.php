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



/**
 * Responds to PING events sent by server,
 * keeping connection alive.
 */
class PingPong extends Nette\Object
{
	/** @var Aki\Irc\Bot */
	protected $bot;



	public function __construct(Aki\Irc\Bot $bot)
	{
		$this->bot = $bot;
		$this->bot->onDataReceived[] = callback($this, 'respondToPing');
	}


	/**
	 * Responds to PING event.
	 * @param  string $data
	 * @param  Aki\Irc\Connection $connection
	 * @return void
	 */
	public function respondToPing($data, $connection)
	{
		$data = Aki\Irc\Utils::stripFormatting($data);

		// @see Aki\Irc\Bot::handleConnect() for data format explanation
		$tmp = explode(' ', $data);
		if ($tmp[0] === 'PING') {
			$this->bot->send("PONG {$tmp[1]}");
		}
	}
}
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
 * Will automatically rejoin channels when kicked.
 */
class AutoRejoin extends Nette\Object
{
	/** @var Aki\Irc\Bot */
	protected $bot;

	/** @var Aki\Stream\Stdout */
	protected $stdout;

	/** @var array */
	protected $channels = array();



	public function __construct(Aki\Irc\Bot $bot, Aki\Stream\Stdout $stdout)
	{
		$this->bot = $bot;
		$this->stdout = $stdout;

		$this->bot->onDataReceived[] = callback($this, 'onDataReceived');
	}


	public function setChannels(array $channels = array())
	{
		$this->channels = array_map(function($val) {
			return '#' . ltrim($val, '#');
		}, $channels);
	}


	public function onDataReceived($data, $connection)
	{
		if ($matches = Nette\Utils\Strings::match($data, '#^\:([^!]+)\![^ ]+ KICK ([^ ]+) ' . preg_quote($this->bot->getNick(), '#') . '#')) {
			if (empty($this->channels) || in_array($matches[2], $this->channels)) {
				$this->bot->joinChannel($matches[2]);
			}
		}
	}
}
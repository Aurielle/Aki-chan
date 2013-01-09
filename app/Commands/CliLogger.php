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




class CliLogger extends Nette\Object
{
	/** @var Aki\Irc\Bot */
	protected $bot;

	/** @var Aki\Stream\Stdin */
	protected $stdin;

	/** @var Aki\Stream\Stdout */
	protected $stdout;



	public function __construct(Aki\Irc\Bot $bot, Aki\Stream\Stdin $stdin, Aki\Stream\Stdout $stdout)
	{
		$this->bot = $bot;
		$this->stdin = $stdin;
		$this->stdout = $stdout;

		$this->bot->onDataReceived[] = callback($this, 'onDataReceived');
		$this->bot->onDataSent[] = callback($this, 'onDataSent');
	}


	public function onDataReceived($data)
	{
		fwrite($this->stdout->socket, "< $data\n");
	}

	public function onDataSent($data)
	{
		fwrite($this->stdout->socket, "> $data");
	}
}
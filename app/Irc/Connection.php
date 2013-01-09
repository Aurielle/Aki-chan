<?php

/**
 * Aki-chan (version 1.0-dev released on $WCDATE$, http://aki.aurielle.cz)
 *
 * Copyright (c) 2013 Václav Vrbka (aurielle@aurielle.cz)
 *
 * For the full copyright and license information, please view
 * the file license.md that was distributed with this source code.
 */

namespace Aki\Irc;

use Aki, Nette, React;




class Connection extends React\Socket\Connection
{
	public function __construct(Aki\Stream\IrcSocket $socket, React\EventLoop\LoopInterface $loop)
	{
		parent::__construct($socket->socket, $loop);
	}
}
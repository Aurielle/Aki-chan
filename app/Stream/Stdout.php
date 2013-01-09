<?php

/**
 * Aki-chan (version 1.0-dev released on $WCDATE$, http://aki.aurielle.cz)
 *
 * Copyright (c) 2013 Václav Vrbka (aurielle@aurielle.cz)
 *
 * For the full copyright and license information, please view
 * the file license.md that was distributed with this source code.
 */

namespace Aki\Stream;

use Aki, Nette, React;




class Stdout extends Nette\Object
{
	/** @var resource */
	protected $socket;

	public function __construct()
	{
		$this->socket = STDOUT;
		stream_set_blocking($this->socket, FALSE);
	}


	public function getSocket()
	{
		return $this->socket;
	}
}
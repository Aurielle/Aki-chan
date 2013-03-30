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




class IrcSocket extends Nette\Object
{
	/** @var resource */
	protected $socket;

	/** @var Aki\Irc\Network */
	protected $network;


	public function __construct(Aki\Irc\Network $network)
	{
		$remote = "{$network->protocol}://{$network->server}:{$network->port}";
		$errno = $errstr = NULL;

		$this->socket = $this->connect($remote, $errno, $errstr, $network->context);
		if (!$this->socket) {
			throw new Aki\ConnectionException('Unable to connect: socket error ' . $errstr . " ($errno)");
		}

		stream_set_blocking($this->socket, 0);
	}


	/**
	 * Creates connection
	 * @param  string $remote
	 * @param  int $errno
	 * @param  string $errstr
	 * @param  array  $context
	 * @return resource
	 */
	protected function connect($remote, &$errno, &$errstr, array $context = array())
	{
		return stream_socket_client(
			$remote,
			$errno,
			$errstr,
			ini_get('default_socket_timeout'),
			STREAM_CLIENT_CONNECT,
			stream_context_create($context)
		);
	}


	/**
	 * @return resource
	 */
	public function getSocket()
	{
		return $this->socket;
	}
}
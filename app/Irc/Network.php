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

use Aki, Nette;




class Network extends Nette\Object
{
	/** @var string */
	protected $server;

	/** @var int */
	protected $port;

	/** @var string */
	protected $nick;

	/** @var string */
	protected $password;

	/** @var array */
	protected $alternativeNicks;

	/** @var string */
	protected $ident;

	/** @var string */
	protected $user;

	/** @var array */
	protected $channels;



	public function __construct(array $options)
	{
		foreach ($options as $key => $value) {
			$this->$key = $value;
		}
	}


	public function getServer()
	{
		return $this->server;
	}

	public function getPort()
	{
		return $this->port;
	}

	public function getNick()
	{
		return $this->nick;
	}

	public function getPassword()
	{
		return $this->password;
	}

	public function getAlternativeNicks()
	{
		return $this->alternativeNicks;
	}

	public function getIdent()
	{
		return $this->ident;
	}

	public function getUser()
	{
		return $this->user;
	}

	public function getChannels()
	{
		return $this->channels;
	}
}
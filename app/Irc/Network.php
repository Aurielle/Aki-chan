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
	protected $protocol;

	/** @var string */
	protected $password;

	/** @var array */
	protected $context;


	/** @var string */
	protected $nick;

	/** @var string */
	protected $nickPassword;

	/** @var array */
	protected $alternativeNicks;

	/** @var string */
	protected $ident;

	/** @var string */
	protected $user;

	/** @var array */
	protected $channels;


	/** @var array */
	protected $setup;



	public function __construct(array $network, array $setup)
	{
		foreach ($network as $key => $value) {
			$this->$key = $value;
		}

		$this->setup = (object) $setup;
	}


	public function getServer()
	{
		return $this->server;
	}

	public function getPort()
	{
		return $this->port;
	}

	public function getProtocol()
	{
		return $this->protocol;
	}

	public function getPassword()
	{
		return $this->password;
	}

	public function getContext()
	{
		return $this->context;
	}

	public function getNick()
	{
		return $this->nick;
	}

	public function getNickPassword()
	{
		return $this->nickPassword;
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

	public function getSetup()
	{
		return $this->setup;
	}
}
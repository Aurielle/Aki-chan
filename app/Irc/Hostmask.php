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



/**
 * Data structure of a hostmask.
 *  Heavily inspired by Phergie_Hostmask
 */
class Hostmask extends Nette\Object
{
	/** @var string */
	protected $host;

	/** @var string */
	protected $nick;

	/** @var string */
	protected $username;

	/** Regex used to match hostmasks */
	const REGEX = '/^([^!@]+)!(?:[ni]=)?([^@]+)@([^ ]+)/';


	public function __construct($nick, $username, $host)
	{
		$this->nick = $nick;
		$this->username = $username;
		$this->host = $host;
	}


	/**
	 * Returns whether a given string appears to be a valid hostmask.
	 * @param  string $hostmask
	 * @return boolean
	 */
	public static function isValid($hostmask)
	{
		return (bool) Nette\Utils\Strings::match($hostmask, static::REGEX);
	}


	/**
	 * Parses a string containing the entire hostmask into a new instance of
	 * this class.
	 * @param  string $hostmask
	 * @return Aki\Irc\Hostmask
	 * @throws Aki\HostmaskException
	 */
	public static function fromString($hostmask)
	{
		if ($matches = Nette\Utils\Strings::match($hostmask, static::REGEX)) {
			list(, $nick, $username, $host) = $matches;
			return new static($nick, $username, $host);
		}

		throw new Aki\HostmaskException("Hostmask '$hostmask' is not valid.");
	}


	/**
	 * @return string
	 */
	public function getHost()
	{
		return $this->host;
	}


	/**
	 * @param string
	 * @return Aki\Irc\Hostmask Provides a fluent interface
	 */
	public function setHost($host)
	{
		$this->host = $host;
		return $this;
	}


	/**
	 * @return string
	 */
	public function getNick()
	{
		return $this->nick;
	}


	/**
	 * @param string
	 * @return Aki\Irc\Hostmask Provides a fluent interface
	 */
	public function setNick($nick)
	{
		$this->nick = $nick;
		return $this;
	}


	/**
	 * @return string
	 */
	public function getUsername()
	{
		return $this->username;
	}


	/**
	 * @param string
	 * @return Aki\Irc\Hostmask Provides a fluent interface
	 */
	public function setUsername($username)
	{
		$this->username = $username;
		return $this;
	}


	/**
	 * Returns the hostmask for the originating server or user.
	 * @return string
	 */
	public function __toString()
	{
		return $this->nick . '!' . $this->username . '@' . $this->host;
	}


	/**
	 * Returns whether a given hostmask matches a given pattern.
	 *
	 * @param string $pattern  Pattern using conventions of a ban mask where
	 *        represents a wildcard
	 * @param string $hostmask Optional hostmask to match against, if not
	 *        the current hostmask instance
	 *
	 * @return bool TRUE if the hostmask matches the pattern, FALSE otherwise
	 * @link http://irchelp.org/irchelp/rfc/chapter4.html#c4_2_3 Examples
	 */
	public function matches($pattern, $hostmask = null)
	{
		if (!$hostmask) {
			$hostmask = (string) $this;
		}

		$pattern = str_replace('*', '.*', $pattern);
		return (bool) Nette\Utils\Strings::match($hostmask, '#^' . preg_quote($pattern, '#') . '$#');
	}
}
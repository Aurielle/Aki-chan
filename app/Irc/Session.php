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



/**
 * Holds information about current session.
 */
class Session extends Nette\Object
{
	/** Key for modes array */
	const MODES_BOT = 'bot';

	/** @var bool */
	protected $identified = FALSE;


	/** @var string */
	protected $uniqueId;

	/** @var string */
	protected $nick;

	/** @var string */
	protected $server;

	/** @var array */
	protected $joinedChannels = array();

	/** @var array */
	protected $modes = array();


	/** Events */
	public $onNickChanged = array();
	public $onServerChanged = array();
	public $onIdentified = array();
	public $onChannelJoined = array();
	public $onKickedFromChannel = array();
	public $onUsermodeChange = array();
	public $onChannelBotModeChange = array();



	public function __construct()
	{
		$this->modes[static::MODES_BOT] = array();
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
	 * @return Session Provides a fluent interface
	 */
	public function setNick($nick)
	{
		$this->onNickChanged($nick, $this->nick);
		$this->nick = $nick;
		return $this;
	}


	/**
	 * @return string
	 */
	public function getServer()
	{
		return $this->server;
	}


	/**
	 * @param string
	 * @return Session Provides a fluent interface
	 */
	public function setServer($server)
	{
		$this->onServerChanged($server, $this->server);
		$this->server = $server;
		return $this;
	}


	/**
	 * @return string
	 */
	public function getUniqueId()
	{
		return $this->uniqueId;
	}


	/**
	 * @param string
	 * @return Session Provides a fluent interface
	 */
	public function setUniqueId($uniqueId)
	{
		$this->uniqueId = $uniqueId;
		return $this;
	}


	/**
	 * @return bool
	 */
	public function isIdentified()
	{
		return $this->identified;
	}


	/**
	 * @param bool
	 * @return Session Provides a fluent interface
	 */
	public function setIdentified($identified)
	{
		$this->identified = (bool) $identified;
		if ($this->identified) {
			$this->onIdentified();
		}

		return $this;
	}


	/**
	 * Returns list of channels the bot is currently on.
	 * @return array
	 */
	public function getChannels()
	{
		return array_keys($this->joinedChannels);
	}


	/**
	 * Returns bot's modes on server or particular channel joined on.
	 * @param NULL|bool|string $key
	 * @return array
	 */
	public function getModes($key = NULL)
	{
		if ($key === TRUE) {
			return $this->modes;
		}

		if (is_string($key) && array_key_exists($key, $this->modes)) {
			return $this->modes[$key];

		} elseif (is_string($key)) {
			throw new Nette\InvalidArgumentException("Bot is not on channel $key.");

		} else {
			return $this->modes[static::MODES_BOT];
		}
	}



	public function channelJoined($channel)
	{
		$this->onChannelJoined($channel);
		$this->joinedChannels[$channel] = TRUE;
		$this->modes[$channel] = array();
	}

	protected function channelLeft($channel)
	{
		unset($this->joinedChannels[$channel]);
		unset($this->modes[$channel]);
	}

	public function channelKicked($channel)
	{
		$this->onKickedFromChannel($channel);
		return $this->channelLeft($channel);
	}

	public function usermodeChange($modes)
	{
		$target = static::MODES_BOT;
		$_this = $this;

		if (Nette\Utils\Strings::startsWith($modes, '+')) {
			$added = str_split(ltrim($modes, '+'));
			$this->modes[$target] = array_merge($this->modes[$target], $added);
			array_walk($added, function($value) use($_this) {
				$_this->onUsermodeChange($value);
			});

		} else {
			$toUnset = str_split(ltrim($modes, '-'));
			foreach ($toUnset as $mode) {
				$key = array_search($mode, $this->modes[static::MODES_BOT]);
				unset($this->modes[static::MODES_BOT][$key]);
			}

			array_walk($toUnset, function($value) use($_this) {
				$_this->onUsermodeChange($value, TRUE);
			});
		}
	}

	public function channelmodeChange($modes, $add, $users, $channel)
	{
		foreach (explode(' ', $users) as $key => $user) {
			if ($user !== $this->getNick()) {
				continue;
			}

			if ($add) {
				$this->modes[$channel] = array_merge($this->modes[$channel], array($modes[$key]));
				$this->onChannelBotModeChange($channel, $modes[$key]);

			} else {
				$mode = $modes[$key];
				$key2 = array_search($mode, $this->modes[$channel]);
				unset($this->modes[$channel][$key2]);
				$this->onChannelBotModeChange($channel, $mode, TRUE);
			}
		}

		$this->modes[$channel] = array_unique($this->modes[$channel]);
	}
}
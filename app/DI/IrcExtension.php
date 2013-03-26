<?php

/**
 * Aki-chan (version 1.0-dev released on $WCDATE$, http://aki.aurielle.cz)
 *
 * Copyright (c) 2013 Václav Vrbka (aurielle@aurielle.cz)
 *
 * For the full copyright and license information, please view
 * the file license.md that was distributed with this source code.
 */

namespace Aki\DI;

use Aki, Nette;



class IrcExtension extends Nette\Config\CompilerExtension
{
	/** @var array */
	public $defaults = array(
		'network' => array(
			'server' => NULL,
			'port' => 6667,
			'nick' => 'AkichanBot',
			'password' => NULL,
			'alternativeNicks' => array(),
			'ident' => NULL,
			'user' => NULL,
			'channels' => array(),
		),

		'setup' => array(
			'nickserv' => 'NickServ',
			'chanserv' => 'ChanServ',
			'ghostDelay' => 60,
		),
	);

	public $alternativeNicks = array(
		'%s`',
		'%s_',
		'%s|',
	);

	public $disallowedInIdent = array("\x00", "\r", "\n", ' ', '@');



	/**
	 * Loads network configuration
	 * @throws Nette\InvalidStateException
	 */
	public function loadConfiguration()
	{
		$container = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);
		$network = $config['network'];

		// Server
		if (empty($network['server'])) {
			throw new Nette\InvalidStateException("Network has no server specified.");
		}

		$network['server'] = (string) $network['server'];

		// Port
		$network['port'] = (int) $network['port'];

		// Nick
		$network['nick'] = (string) $network['nick'];

		// Password
		$network['password'] = empty($network['password']) ? NULL : (string) $network['password'];

		// Alternative nicks
		if (empty($network['alternativeNicks'])) {
			$network['alternativeNicks'] = $this->alternativeNicks;
		}

		$network['alternativeNicks'] = array_unique((array) $network['alternativeNicks']);
		foreach ($network['alternativeNicks'] as &$value) {
			$value = sprintf($value, $network['nick']);
		};

		// Ident
		if (empty($network['ident'])) {
			$network['ident'] = Aki\Aki::NAME;

		} else {
			foreach ($this->disallowedInIdent as $disallowed) {
				if (strpos($network['ident'], $disallowed) !== FALSE) {
					throw new Nette\NotSupportedException('Ident contains illegal characters (disallowed: \x00, \r, \n, " " (space), @).');
				}
			}
		}

		$network['ident'] = (string) $network['ident'];

		// User
		if (empty($network['user'])) {
			$network['user'] = Aki\Aki::NAME . ' ' . Aki\Aki::VERSION;
		}

		$network['user'] = (string) $network['user'];

		// Autojoin channels
		$aliases = array('join', 'autojoin');
		foreach ($aliases as $alias) {
			if (array_key_exists($alias, $network)) {
				$network['channels'] = $network[$alias];
				unset($network[$alias]);
			}
		}

		$network['channels'] = (array) $network['channels'];
		foreach ($network['channels'] as &$channel) {
			$channel = '#' . ltrim($channel, '#');
		}


		// Setup options
		$setup = $config['setup'];



		$container->addDefinition($this->prefix('network'))
			->setClass('Aki\Irc\Network', array($network, $setup));
	}
}
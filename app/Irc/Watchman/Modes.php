<?php

/**
 * Aki-chan (version 1.0-dev released on $WCDATE$, http://aki.aurielle.cz)
 *
 * Copyright (c) 2013 Václav Vrbka (aurielle@aurielle.cz)
 *
 * For the full copyright and license information, please view
 * the file license.md that was distributed with this source code.
 */

namespace Aki\Irc\Watchman;

use Aki, Nette, React;
use Aki\Irc;
use Aki\Irc\ServerCodes;
use Kdyby\Events;



/**
 * Watches for mode changes.
 */
class Modes extends Nette\Object implements Events\Subscriber
{
	/** @var Aki\Irc\Session */
	protected $session;

	/** @var Aki\Irc\Logger */
	protected $logger;


	public function __construct(Irc\Session $session, Irc\Logger $logger)
	{
		$this->session = $session;
		$this->logger = $logger;
	}


	/**
	 * Watches for mode changes
	 * @param  string $data
	 * @param  Aki\Irc\Connection $connection
	 * @return void
	 */
	public function onDataReceived($data, Irc\Connection $connection)
	{
		$tmp = explode(' ', $data);
		if ($tmp[1] !== 'MODE') {
			return;
		}

		// User mode change
		if ($tmp[2] === $this->session->nick) {
			$modes = ltrim($tmp[3], ':');
			$this->session->usermodeChange($modes);
			$this->logger->logMessage(Irc\ILogger::DEBUG, 'Mode change [%2$s] for %1$s', $tmp[2], $modes);

		// Channel mode change
		} else {
			$matches = Nette\Utils\Strings::match($data, '~\:(?P<nick>[^!]+)\!(?P<hostname>[^ ]+) MODE (?P<channel>#[^ ]+) (?P<mode>(?P<mode1>\+|\-)(?P<mode2>[^ ]+)) ?(?P<users>.*)~i');

			if ($matches['users']) {
				$this->logger->logMessage(Irc\ILogger::DEBUG, 'Mode change for %2$s [%3$s: %4$s] by %1$s', $matches['nick'], $matches['channel'], $matches['mode'], $matches['users']);

				// Indexes in modes correspond to order in list of users affected
				if (strpos($matches['users'], $this->session->nick) !== FALSE) {
					$modes = str_split($matches['mode2']);
					$this->session->channelmodeChange($modes, $matches['mode1'] === '+', $matches['users'], $matches['channel']);
				}

			} else {
				$this->logger->logMessage(Irc\ILogger::DEBUG, 'Mode change for %2$s [%3$s] by %1$s', $matches['nick'], $matches['channel'], $matches['mode']);
			}
		}
	}

	public function getSubscribedEvents()
	{
		return array('Aki\Irc\Message::onDataReceived');
	}
}
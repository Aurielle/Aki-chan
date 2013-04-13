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
use Aki\Irc\ILogger;
use Kdyby\Events;



/**
 * Will automatically rejoin channels when kicked.
 */
class AutoRejoin extends Nette\Object implements Events\Subscriber
{
	/** @var Aki\Irc\Bridge\Channels */
	protected $bridgeChannels;

	/** @var Aki\Irc\Logger */
	protected $logger;


	/** @var array */
	protected $channels = array();



	public function __construct(Aki\Irc\Bridge\Channels $bridgeChannels, Aki\Irc\Logger $logger)
	{
		$this->bridgeChannels = $bridgeChannels;
		$this->logger = $logger;
	}


	public function setChannels(array $channels = array())
	{
		$this->channels = array_map(function($channel) {
			if (!Aki\Irc\Utils::isChannelName($channel)) {
				$channel = '#' . $channel;
			}

			return $channel;
		}, $channels);
	}


	public function onKickedFromChannel($channel)
	{
		if (empty($this->channels) || in_array($channel, $this->channels)) {
			$this->logger->logMessage(ILogger::NOTICE, 'Automatic rejoin enabled for %s, rejoining', $channel);
			$this->bridgeChannels->joinChannel($channel);
		}
	}


	public function getSubscribedEvents()
	{
		return array('Aki\Irc\Session::onKickedFromChannel');
	}
}
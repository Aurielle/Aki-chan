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
class AutoVoice extends Nette\Object implements Events\Subscriber
{
	/** @var Aki\Irc\Message */
	protected $message;

	/** @var Aki\Irc\Session */
	protected $session;

	/** @var Aki\Irc\Logger */
	protected $logger;


	/** @var array */
	protected $channels = array();



	public function __construct(Aki\Irc\Message $message, Aki\Irc\Session $session, Aki\Irc\Logger $logger)
	{
		$this->message = $message;
		$this->session = $session;
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


	public function onUserJoinedChannel($nick, $channel)
	{
		if (empty($this->channels) || in_array($channel, $this->channels)) {
			// check for voicing capabilities
			$modes = $this->session->getModes($channel);
			if (in_array('h', $modes) || in_array('o', $modes)) {
				$this->logger->logMessage(ILogger::DEBUG, 'Setting auto voice for %s in %s', $nick, $channel);
				$this->message->send(sprintf('MODE %s +v %s', $channel, $nick));
			}
		}
	}


	public function getSubscribedEvents()
	{
		return array('Aki\Irc\Session::onUserJoinedChannel');
	}
}
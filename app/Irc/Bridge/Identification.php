<?php

/**
 * Aki-chan (version 1.0-dev released on $WCDATE$, http://aki.aurielle.cz)
 *
 * Copyright (c) 2013 Václav Vrbka (aurielle@aurielle.cz)
 *
 * For the full copyright and license information, please view
 * the file license.md that was distributed with this source code.
 */

namespace Aki\Irc\Bridge;

use Aki, Nette, React;
use Aki\Irc;
use Aki\Irc\ServerCodes;
use Kdyby\Events;



/**
 * Handles identification with the server.
 */
class Identification extends Nette\Object implements Events\Subscriber
{
	/** @var Aki\Irc\Message */
	protected $message;

	/** @var React\EventLoop\LoopInterface */
	protected $eventLoop;

	/** @var Aki\Irc\Session */
	protected $session;

	/** @var Aki\Irc\Network */
	protected $network;

	/** @var Aki\Irc\Logger */
	protected $logger;


	/** @var int */
	protected $sentGhost;

	/** Events */
	public $onGhosted = array();


	public function __construct(Irc\Message $message, React\EventLoop\LoopInterface $eventLoop, Irc\Session $session, Irc\Network $network, Irc\Logger $logger)
	{
		$this->message = $message;
		$this->eventLoop = $eventLoop;
		$this->session = $session;
		$this->network = $network;
		$this->logger = $logger;
	}


	/**
	 * Identifies with NickServ, if password is set (alternatively ghosts existing session).
	 * @return void
	 */
	public function identify()
	{
		if ($this->network->nickPassword && $this->network->setup->nickserv) {
			if ($this->session->nick === $this->network->nick) {
				$this->message->send(sprintf('PRIVMSG %s :IDENTIFY %s', $this->network->setup->nickserv, $this->network->nickPassword));
				$this->logger->logMessage(Irc\ILogger::INFO, 'Identifying with NickServ...');

			} else {
				$this->ghost();
			}
		}
	}


	/**
	 * Ghosts a bot's nick that is no longer connected.
	 * @return void
	 */
	public function ghost()
	{
		if ($this->network->nickPassword && $this->network->setup->nickserv && $this->session->nick !== $this->network->nick) {
			if ($this->sentGhost && time() < ($this->sentGhost + $this->network->setup->ghostDelay)) {
				$this->logger->logMessage(Irc\ILogger::WARNING, 'Ghost attempted too early, allowed only once every %d seconds.', $this->network->setup->ghostDelay);
			}

			$this->send(sprintf('PRIVMSG %s :GHOST %s %s', $this->network->setup->nickserv, $this->network->nick, $this->network->nickPassword));
			$this->logger->logMessage(Irc\ILogger::NOTICE, '~ Sending Ghost (current nick: %s; ghosting: %s)', $this->session->nick, $this->network->nick);
			$this->sentGhost = time();
		}
	}


	/**
	 * Reclaim ghosted nick and identify for it.
	 * @return void
	 */
	public function afterGhost()
	{
		$this->send(sprintf('NICK %s', $this->network->nick));
		$this->session->setNick($this->network->nick);
		$this->eventLoop->addTimer(1, callback($this, 'identify'));
	}



	/**
	 * Handles replies to various messages from NickServ.
	 * @param  Irc\Event\IEvent $data
	 * @return void
	 */
	public function onDataReceived($data)
	{
		if (!($data->type === Irc\Event\Request::TYPE_NOTICE && $data->nickname === $this->network->setup->nickserv)) {
			return;
		}

		$msg = Irc\Utils::stripFormatting($data->text);

		// Incorrect password for registered nick
		if (stripos($msg, 'incorrect') !== FALSE || stripos($msg, 'denied') !== FALSE) {
			$this->logger->logMessage(Irc\ILogger::WARNING, 'Incorrect password passed for NickServ (nick: %s)', $this->session->nick);
		}

		// Password specified, but nick not registered
		if (stripos($msg, 'is not registered') !== FALSE || stripos($msg, "isn't registered") !== FALSE) {
			$this->logger->logMessage(Irc\ILogger::NOTICE, 'Current nick (%s) is not registered', $this->session->nick);
		}

		// Registered nick, identified by NickServ
		if (stripos($msg, 'now recognized') !== FALSE ||
			stripos($msg, 'already identified') !== FALSE ||
			stripos($msg, 'already logged in') !== FALSE ||
			stripos($msg, 'password accepted') !== FALSE ||
			stripos($msg, 'now identified') !== FALSE) {

			$this->session->setIdentified(TRUE);
			$this->logger->logMessage(Irc\ILogger::INFO, 'Password accepted, Aki is recognized');
		}

		// Ghost with your nick was killed message
		if (stripos($msg, 'killed') !== FALSE && (strpos($msg, $this->network->nick) !== FALSE || stripos($msg, 'ghost') !== FALSE)) {
			$this->onGhosted();
			$this->logger->logMessage(Irc\ILogger::NOTICE, 'Ghost of nick %s has been killed', $this->network->nick);
			$this->session->setIdentified(FALSE);
			$this->sentGhost = NULL;
			$this->eventLoop->addTimer(1, callback($this, 'afterGhost'));
		}

		// This nick is currently not used message (after ghosting)
		// Not all Rizon servers seem to send this message. What about others?
		// Keeping both conditions to ensure afterGhost() gets called
		if (stripos($msg, 'currently') !== FALSE && (stripos($msg, 'is not') !== FALSE || stripos($msg, "isn't") !== FALSE)) {
			// Cancel any existing timers for afterGhost so it won't be performed twice
			$this->eventLoop->cancelTimer(callback($this, 'afterGhost'));
			$this->eventLoop->addTimer(1, callback($this, 'afterGhost'));
		}
	}


	public function getSubscribedEvents()
	{
		return array('Aki\Irc\Message::onDataReceived');
	}
}
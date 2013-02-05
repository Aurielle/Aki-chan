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
 * Brain of Aki. Controls connection to the server and responds accordingly.
 */
class Bot extends Nette\Object
{
	/** @var Aki\Irc\Network */
	protected $network;

	/** @var React\EventLoop\LoopInterface */
	protected $eventLoop;

	/** @var React\Stream\Stream */
	protected $connection;

	/** @var React\Stream\Stream */
	protected $stdin;

	/** @var React\Stream\Stream */
	protected $stdout;


	/** Events */
	public $onDataReceived = array();
	public $onDataSent = array();
	public $onDisconnect = array();

	/** @var bool */
	protected $connecting = FALSE;

	/** @var bool */
	protected $identified = FALSE;


	/** @var string */
	protected $uniqueId;

	/** @var string */
	protected $nick;

	/** @var string */
	protected $server;


	/** @var int */
	protected $sentGhost;

	/** @var mixed */
	private $buffer;



	public function __construct(Network $network, React\EventLoop\LoopInterface $eventLoop, Connection $connection, Aki\Stream\Stdin $stdin, Aki\Stream\Stdout $stdout)
	{
		$this->network = $network;
		$this->eventLoop = $eventLoop;
		$this->connection = $connection;
		$this->stdin = $stdin;
		$this->stdout = $stdout;

		$_this = $this;
		/*$eventLoop->addReadStream($stdin->socket, function($stream) use($_this) {
			$_this->send(stream_get_line($stream, 512));
		});*/

		// Console information about successful connection
		$connection->once('data', function() use ($_this, $network) {
			$_this->status(sprintf('~ Connected to %s on port %d', $network->server, $network->port));
		});

		// Handle incoming data with our buffer
		$connection->on('data', function($data, $conn) use($_this) {
			$_this->buffer($data, callback($_this, 'onDataReceived'), array($conn));
		});

		// Graceful shutdown
		$connection->on('close', function($conn) use($_this, $eventLoop, $stdin) {
			//$eventLoop->removeReadStream($stdin->socket);
			$_this->onDisconnect($_this, $conn);
			$_this->status('! Connection closed, Aki is aborting.');
			exit;
		});

		// Identifying with the server and other stuff
		$eventLoop->addTimer(0.3, callback($this, 'connect'));
	}


	/**
	 * Runs the event loop and connects to the server.
	 * @return void
	 */
	public function run()
	{
		// Internal events
		$this->onDataReceived[] = callback($this, 'handleConnect');
		$this->onDataReceived[] = callback($this, 'watchNick');

		$this->connecting = TRUE;
		$this->eventLoop->run();
	}


	/**
	 * Sends command to the server.
	 * @todo use PHP_EOL?
	 * @param  string $data
	 * @return Bot Provides a fluent interface.
	 */
	public function send($data)
	{
		if ($this->connection->write($data . "\r\n")) {
			$this->onDataSent($data, $this->connection);
		}

		return $this;
	}


	/**
	 * Prints information to the console.
	 * @todo Implement some normal logger with severity.
	 * @todo use PHP_EOL?
	 * @param  string $text
	 * @return Bot Provides a fluent interface.
	 */
	public function status($text)
	{
		fwrite($this->stdout->socket, $text . "\n");
		return $this;
	}


	/**
	 * Sends identification to the server.
	 * @param  string $timer
	 * @param  React\EventLoop\LoopInterface $loop
	 * @return void
	 */
	public function connect($timer, React\EventLoop\LoopInterface $loop)
	{
		if (!$this->isConnecting()) {
			return;
		}

		$this->nick = $this->network->nick;
		$this->send(sprintf('USER %s aurielle.cz %s :%s', $this->network->ident, $this->nick, $this->network->user));
		$this->send(sprintf('NICK %s', $this->nick));
		$this->status(sprintf('~ Logging in as %s...', $this->nick));
	}


	/**
	 * Serves lines data to callback function one by one
	 * Intended to be called only once!!
	 * @param  string $rawData
	 * @param  callable $callback
	 * @param  array  $params
	 * @return void
	 */
	public function buffer($rawData, $callback, $params = array())
	{
		if (!is_callable($callback)) {
			throw new Nette\InvalidArgumentException('Buffer callback is not callable.');
		}

		// Split raw data into pieces of each line and clean them from control characters
		// \xFF is intentionally added here to detect line endings (anyone knows of better solution?)
		// ltrim gets rid of possible control characters at start (\r, \n)
		$rawData = str_replace(array("\r\n", "\r", "\n"), array("\n", "\n", "\xFF\n"), ltrim($rawData));
		$data = explode("\n", $rawData);

		// If something's in buffer, use it and clean the buffer
		if ($this->buffer) {
			$first = $this->buffer . array_shift($data);
			$this->buffer = NULL;
			$data = array_merge(array($first), $data);
		}

		// Detect last possibly incomplete line
		// Very last line could be an empty string
		$last = array_pop($data);
		if (empty($last)) {
			$last = array_pop($data);
		}

		// If the last line does not end with our control character,
		// put it into the buffer and stop it from processing.
		// Else merge the array back and proceed normally.
		if (!Nette\Utils\Strings::endsWith($last, "\xFF")) {
			$this->buffer = $last;

		} else {
			$data = array_merge($data, array($last));
		}

		// Now call the callback
		foreach ($data as $d) {
			call_user_func_array($callback, array_merge(array(rtrim($d, "\xFF")), (array) $params));
		}
	}



	/**
	 * Handles server responses during connection phase.
	 * @param  string $data
	 * @param  Aki\Irc\Connection $conn
	 * @return void
	 */
	public function handleConnect($data, Connection $conn)
	{
		if (!$this->isConnecting()) {
			return;
		}

		// Not sure here, all responses should have more than one word
		// Again, not checked with RFC
		$tmp = explode(' ', $data); $_this = $this;

		// Numeric responses (@see Aki\Irc\ServerCodes for some of response codes)
		if (is_numeric($tmp[1])) {
			switch ((int) $tmp[1]) {
				// Server welcome message
				case ServerCodes::WELCOME:
					$this->status('~ Received server welcome message');
					break;

				// Bot's nick is already on the server, use a different one
				case ServerCodes::NICK_USED:
					$this->alternativeNick();
					break;

				// Save our unique ID (what is it for?)
				case ServerCodes::UNIQUE_ID:
					$this->uniqueId = $tmp[3];
					$this->status('~ Unique ID saved');
					break;

				// MOTD ends, begin identifying phase
				case ServerCodes::MOTD_END:
				case ServerCodes::NO_MOTD:
				case ServerCodes::SPAM:
					$this->status('~ Received end of MOTD');
					$this->eventLoop->addTimer(0.8, callback($this, 'identify'));
					$this->eventLoop->addTimer(5, function() use($_this) {
						$_this->send('JOIN #fairytail');
					});
					//$this->connecting = FALSE; // CHANGE!
					break;

				// Unhandled response
				default:
					break;
			}

		// NickServ or ChanServ replies, handle them within their respective functions
		} elseif ($tmp[1] === 'NOTICE') {
			if (Nette\Utils\Strings::startsWith(ltrim($tmp[0], ':'), $this->network->setup->nickserv)) {
				$this->nickservNotice($data, $conn);

			} elseif (Nette\Utils\Strings::startsWith(ltrim($tmp[0], ':'), $this->network->setup->chanserv)) {
				$this->chanservNotice($data, $conn);
			}

		// User or channel mode change
		} elseif ($tmp[1] === 'MODE') {
			$this->status(sprintf('~ Setting mode %s for %s', ltrim($tmp[3], ':'), $tmp[2]));
		}
	}


	/**
	 * Issues alternative nick to the bot.
	 * @return void
	 */
	protected function alternativeNick()
	{
		static $nicks;

		// Fill up the variable if called for the first time
		if ($nicks === NULL) {
			$nicks = $this->network->alternativeNicks;
		}

		// Alternative nicks are exhausted, quit
		// onDisconnect will get called automatically (end of stream)
		if($nicks === array()) {
			$this->send('QUIT :No alternative nicks available.');
			$this->status('! No more alternative nicks available');
			return;
		}

		// Select another new nick and send it to the server
		$newNick = array_shift($nicks);
		$this->nick = $newNick;
		$this->send(sprintf('NICK %s', $newNick));
		$this->status(sprintf('~ Using alternative nick %s', $newNick));
	}


	/**
	 * Identifies with NickServ, if password is set (alternatively ghosts existing session).
	 * @return void
	 */
	public function identify()
	{
		if ($this->network->password && $this->network->setup->nickserv) {
			if ($this->nick === $this->network->nick) {
				$this->send(sprintf('PRIVMSG %s :IDENTIFY %s', $this->network->setup->nickserv, $this->network->password));
				$this->status('~ Identifying with NickServ...');

			} else {
				$this->ghost();
			}
		}
	}


	/**
	 * Ghosts a bot's nick that is no longer connected.
	 * @return [type] [description]
	 */
	public function ghost()
	{
		if ($this->network->password && $this->network->setup->nickserv && $this->nick !== $this->network->nick) {
			if ($this->sentGhost && time() < ($this->sentGhost + $this->network->setup->ghostDelay)) {
				$this->status(sprintf('! Ghost attempted too early, allowed only once every %d seconds.', $this->network->setup->ghostDelay));
			}

			$this->send(sprintf('PRIVMSG %s :GHOST %s %s', $this->network->setup->nickserv, $this->network->nick, $this->network->password));
			$this->status(sprintf('~ Sending Ghost (current nick: %s; ghosting: %s)', $this->nick, $this->network->nick));
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
		$this->nick = $this->network->nick;
		$this->eventLoop->addTimer(1, callback($this, 'identify'));
	}


	/**
	 * Watches for changes of bot's nick.
	 * @param  string $data
	 * @param  Aki\Irc\Connection $connection
	 * @return void
	 */
	public function watchNick($data, Connection $connection)
	{
		// @see handleConect() for notice on this
		$tmp = explode(' ', $data, 4);

		// Only watch for specific numeric codes
		if (!in_array($tmp[1], ServerCodes::$nickSetters)) {
			return;
		}

		// Store current server to our variable
		$server = ltrim($tmp[0], ':');
		if ($this->server !== $server) {
			$this->server = $server;
			$this->status(sprintf('~ Updating server to %s', $server));
		}

		// Store current nick to our variable
		$nick = $tmp[2];
		if ($this->nick !== $nick) {
			$this->nick = $nick;
			$this->status(sprintf('~ Updating nick to %s', $nick));
		}
	}


	/**
	 * Handles replies to various messages from NickServ.
	 * @param  string $data
	 * @param  Aki\Irc\Connection $connection
	 * @return void
	 */
	protected function nickservNotice($data, Connection $connection)
	{
		// again, @see handleConnect() for notice on this
		$tmp = explode(' ', $data, 4);
		$msg = Utils::stripFormatting(ltrim($tmp[3], ':'));

		// Incorrect password for registered nick
		if (stripos($msg, 'incorrect') !== FALSE || stripos($msg, 'denied') !== FALSE) {
			$this->status('! Incorrect password passed for NickServ');
		}

		// Password specified, but nick not registered
		if (stripos($msg, 'is not registered') !== FALSE || stripos($msg, "isn't registered") !== FALSE) {
			$this->status(sprintf('~ Current nick (%s) is not registered', $this->nick));
		}

		// Registered nick, identified by NickServ
		if (stripos($msg, 'now recognized') !== FALSE ||
			stripos($msg, 'already identified') !== FALSE ||
			stripos($msg, 'password accepted') !== FALSE ||
			stripos($msg, 'now identified') !== FALSE) {

			$this->identified = TRUE;
			$this->status('~ Password accepted, Aki is recognized');
		}

		// Ghost with your nick was killed message
		if (stripos($msg, 'killed') !== FALSE && (strpos($msg, $this->network->nick) !== FALSE || stripos($msg, 'ghost') !== FALSE)) {
			$this->status(sprintf('~ Ghost of nick %s has been killed', $this->network->nick));
			$this->identified = FALSE;
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

	protected function chanservNotice($data, $connection)
	{

	}




	/**
	 * Is bot in connecting phase?
	 * @return bool
	 */
	public function isConnecting()
	{
		return $this->connecting;
	}


	/**
	 * Is bot identified with NickServ?
	 * @return bool
	 */
	public function isIdentified()
	{
		return $this->identified;
	}


	/**
	 * Returns current nick
	 * @return string
	 */
	public function getNick()
	{
		return $this->nick;
	}


	/**
	 * Returns current server
	 * @return string
	 */
	public function getServer()
	{
		return $this->server;
	}


	/**
	 * Returns bot's unique ID
	 * @return string
	 */
	public function getUniqueId()
	{
		return $this->uniqueId;
	}
}
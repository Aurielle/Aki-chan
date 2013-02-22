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
	/** Key for modes array */
	const MODES_BOT = 'bot';

	/** @var Aki\Irc\Network */
	protected $network;

	/** @var React\EventLoop\LoopInterface */
	protected $eventLoop;

	/** @var React\Stream\Stream */
	protected $connection;

	/** @var Aki\Stream\Stdin */
	protected $stdin;

	/** @var Aki\Stream\Stdout */
	protected $stdout;

	/** @var Aki\Irc\Logger */
	protected $logger;


	/** Events */
	public $onDataReceived = array();
	public $onDataSent = array();
	public $onDisconnect = array();

	public $onUserModeChange = array();
	public $onChannelBotModeChange = array();

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

	/** @var array */
	protected $joinedChannels = array();

	/** @var array */
	protected $modes = array();


	/** @var int */
	protected $sentGhost;

	/** @var array */
	private $buffer;



	public function __construct(Network $network, React\EventLoop\LoopInterface $eventLoop, Connection $connection, Aki\Stream\Stdin $stdin, Aki\Stream\Stdout $stdout, Aki\Irc\Logger $logger)
	{
		$this->network = $network;
		$this->eventLoop = $eventLoop;
		$this->connection = $connection;
		$this->stdin = $stdin;
		$this->stdout = $stdout;
		$this->logger = $logger;

		$_this = $this;
		/*$eventLoop->addReadStream($stdin->socket, function($stream) use($_this) {
			$_this->send(stream_get_line($stream, 512));
		});*/

		// Console information about successful connection
		$connection->once('data', function() use ($logger, $network) {
			$logger->logMessage(ILogger::INFO, 'Connected to %s on port %d', $network->server, $network->port);
		});

		// Handle incoming data with our buffer
		$connection->on('data', function($data, $conn) use($_this) {
			$_this->buffer($data, callback($_this, 'onDataReceived'), array($conn));
		});

		// Graceful shutdown
		$connection->on('close', function($conn) use($_this, $eventLoop, $stdin, $logger) {
			//$eventLoop->removeReadStream($stdin->socket);
			$_this->onDisconnect($_this, $conn);
			$logger->logMessage($logger::WARNING, 'Connection closed, aborting.');
			exit;
		});

		// Internal events
		$this->onDataReceived[] = callback($this, 'handleConnect');
		$this->onDataReceived[] = callback($this, 'watchNick');
		$this->onDataReceived[] = callback($this, 'watchModes');
		$this->onDataReceived[] = callback($this, 'watchChannels');

		// Identifying with the server and other stuff
		$eventLoop->addTimer(0.3, callback($this, 'connect'));
	}


	/**
	 * Runs the event loop and connects to the server.
	 * @return void
	 */
	public function run()
	{
		// Initialize modes array
		$this->modes[static::MODES_BOT] = array();

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
		$this->logger->logMessage(ILogger::INFO, 'Logging in as %s...', $this->nick);
	}


	/**
	 * Serves lines data to callback function one by one
	 * @param  string $rawData
	 * @param  callable $callback
	 * @param  array  $params
	 * @return void
	 */
	public function buffer($rawData, $callback, $params = array())
	{
		if (!$callback instanceof Nette\Callback) {
			$callback = Nette\Callback::create($callback);
		}

		// Determine hash of the callback to distinguish different calls
		$cb = $callback->getNative();
		if ($cb instanceof \Closure) {
			// spl_object_hash($callback) results in same hashes for different callbacks
			$hash = spl_object_hash($cb);
		} elseif (is_string($cb) && $cb[0] === "\0") {
			// lambda functions
			$hash = md5($cb);
		} else {
			is_callable($cb, TRUE, $textual);
			$hash = md5($textual);
		}

		// Split raw data into pieces of each line and clean them from control characters
		// \xFF is intentionally added here to detect line endings (anyone knows of better solution?)
		// ltrim gets rid of possible control characters at start (\r, \n)
		$rawData = str_replace(array("\r\n", "\r", "\n"), array("\n", "\n", "\xFF\n"), ltrim($rawData));
		$data = explode("\n", $rawData);

		// If something's in buffer, use it and clean the buffer
		if (isset($this->buffer[$hash])) {
			$first = $this->buffer[$hash] . array_shift($data);
			$this->buffer[$hash] = NULL;
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
			$this->buffer[$hash] = $last;

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
		$tmp = explode(' ', $data);

		// Numeric responses (@see Aki\Irc\ServerCodes for some response codes)
		if (is_numeric($tmp[1])) {
			switch ((int) $tmp[1]) {
				// Server welcome message
				case ServerCodes::WELCOME:
					$this->logger->logMessage(ILogger::INFO, 'Received server welcome message');
					break;

				// Bot's nick is already on the server, use a different one
				case ServerCodes::NICK_USED:
					$this->alternativeNick();
					break;

				// Save our unique ID (what is it for?)
				case ServerCodes::UNIQUE_ID:
					dump($tmp);
					$this->uniqueId = $tmp[3];
					$this->logger->logMessage(ILogger::INFO, 'Unique ID (%s) saved', $tmp[3]);
					break;

				// MOTD ends, begin identifying phase
				case ServerCodes::MOTD_END:
				case ServerCodes::NO_MOTD:
				case ServerCodes::SPAM:
					$this->logger->logMessage(ILogger::INFO, 'Received end of MOTD');
					$this->eventLoop->addTimer(0.8, callback($this, 'identify'));
					$this->eventLoop->addPeriodicTimer(2, callback($this, 'handleJoinChannels'));
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
		if ($nicks === array()) {
			$this->send('QUIT :No alternative nicks available.');
			$this->logger->logMessage(ILogger::ERROR, 'No more alternative nicks available');
			return;
		}

		// Select another new nick and send it to the server
		$newNick = array_shift($nicks);
		$this->nick = $newNick;
		$this->send(sprintf('NICK %s', $newNick));
		$this->logger->logMessage(ILogger::NOTICE, 'Using alternative nick %s', $newNick);
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
				$this->logger->logMessage(ILogger::INFO, 'Identifying with NickServ...');

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
		if ($this->network->password && $this->network->setup->nickserv && $this->nick !== $this->network->nick) {
			if ($this->sentGhost && time() < ($this->sentGhost + $this->network->setup->ghostDelay)) {
				$this->logger->logMessage(ILogger::WARNING, 'Ghost attempted too early, allowed only once every %d seconds.', $this->network->setup->ghostDelay);
			}

			$this->send(sprintf('PRIVMSG %s :GHOST %s %s', $this->network->setup->nickserv, $this->network->nick, $this->network->password));
			$this->logger->logMessage(ILogger::NOTICE, '~ Sending Ghost (current nick: %s; ghosting: %s)', $this->nick, $this->network->nick);
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
			$this->logger->logMessage(ILogger::INFO, 'Updating server to %s', $server);
		}

		// Store current nick to our variable
		$nick = $tmp[2];
		if ($this->nick !== $nick) {
			$this->nick = $nick;
			$this->logger->logMessage(ILogger::INFO, 'Updating nick to %s', $nick);
		}
	}


	/**
	 * Watches for mode changes
	 * @todo unset array on channel leave/kick
	 *
	 * @param  string $data
	 * @param  Aki\Irc\Connection $connection
	 * @return void
	 */
	public function watchModes($data, Connection $connection)
	{
		$tmp = explode(' ', $data);
		if ($tmp[1] !== 'MODE') {
			return;
		}

		// User mode change
		if ($tmp[2] === $this->nick) {
			$_this = $this;
			$modes = ltrim($tmp[3], ':');
			$this->logger->logMessage(ILogger::DEBUG, 'Mode change [%2$s] for %1$s', $tmp[2], $modes);

			if (Nette\Utils\Strings::startsWith($modes, '+')) {
				$added = str_split(ltrim($modes, '+'));
				$this->modes[static::MODES_BOT] = array_merge($this->modes[static::MODES_BOT], $added);
				array_walk($added, function($value) use($_this) {
					$_this->onUserModeChange($value);
				});

			} else {
				$toUnset = str_split(ltrim($modes, '-'));
				foreach ($toUnset as $mode) {
					$key = array_search($mode, $this->modes[static::MODES_BOT]);
					unset($this->modes[static::MODES_BOT][$key]);
				}

				array_walk($toUnset, function($value) use($_this) {
					$_this->onUserModeChange($value, TRUE);
				});
			}

		// Channel mode change
		} else {
			$matches = Nette\Utils\Strings::match($data, '~\:(?P<nick>[^!]+)\!(?P<hostname>[^ ]+) MODE (?P<channel>#[^ ]+) (?P<mode>(?P<mode1>\+|\-)(?P<mode2>[^ ]+)) ?(?P<users>.*)~i');

			if ($matches['users']) {
				$this->logger->logMessage(ILogger::DEBUG, 'Mode change for %2$s [%3$s: %4$s] by %1$s', $matches['nick'], $matches['channel'], $matches['mode'], $matches['users']);

				// Indexes in modes correspond to order in list of users affected
				if (strpos($matches['users'], $this->nick) !== FALSE) {
					$modes = str_split($matches['mode2']);
					foreach (explode(' ', $matches['users']) as $key => $user) {
						if ($user !== $this->nick) {
							continue;
						}

						if ($matches['mode1'] === '+') {
							$this->modes[$matches['channel']] = array_merge($this->modes[$matches['channel']], array($modes[$key]));
							$this->onChannelBotModeChange($matches['channel'], $modes[$key]);

						} else {
							$mode = $modes[$key];
							$key2 = array_search($mode, $this->modes[$matches['channel']]);
							unset($this->modes[$matches['channel']][$key2]);
							$this->onChannelBotModeChange($matches['channel'], $mode, TRUE);
						}
					}

					$this->modes[$matches['channel']] = array_unique($this->modes[$matches['channel']]);
				}

			} else {
				$this->logger->logMessage(ILogger::DEBUG, 'Mode change for %2$s [%3$s] by %1$s', $matches['nick'], $matches['channel'], $matches['mode']);
			}
		}
	}



	/**
	 * Watches for channel joins/parts/kicks/bans
	 * @param  string $data
	 * @param  Aki\Irc\Connection $connection
	 * @return void
	 */
	public function watchChannels($data, Connection $connection)
	{
		$tmp = explode(' ', $data, 5);
		if ($tmp[1] === 'JOIN') {
			$channel = ltrim($tmp[2], ':');
			$this->joinedChannels[$channel] = TRUE;
			$this->modes[$channel] = array();

			$this->logger->logMessage(ILogger::INFO, 'Joined channel %s', $channel);

		} elseif ($tmp[1] === 'KICK' && $tmp[3] === $this->nick) {
			$channel = ltrim($tmp[2], ':');
			unset($this->joinedChannels[$channel]);
			unset($this->modes[$channel]);

			$this->logger->logMessage(ILogger::WARNING, 'Kicked from channel %s by %s (reason: %s)', $channel, substr($tmp[0], 1, strpos($tmp[0], '!') - 1), ltrim($tmp[4], ':'));
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
			$this->logger->logMessage(ILogger::WARNING, 'Incorrect password passed for NickServ (nick: %s)', $this->nick);
		}

		// Password specified, but nick not registered
		if (stripos($msg, 'is not registered') !== FALSE || stripos($msg, "isn't registered") !== FALSE) {
			$this->logger->logMessage(ILogger::NOTICE, 'Current nick (%s) is not registered', $this->nick);
		}

		// Registered nick, identified by NickServ
		if (stripos($msg, 'now recognized') !== FALSE ||
			stripos($msg, 'already identified') !== FALSE ||
			stripos($msg, 'already logged in') !== FALSE ||
			stripos($msg, 'password accepted') !== FALSE ||
			stripos($msg, 'now identified') !== FALSE) {

			$this->identified = TRUE;
			$this->logger->logMessage(ILogger::INFO, 'Password accepted, Aki is recognized');
		}

		// Ghost with your nick was killed message
		if (stripos($msg, 'killed') !== FALSE && (strpos($msg, $this->network->nick) !== FALSE || stripos($msg, 'ghost') !== FALSE)) {
			$this->logger->logMessage(ILogger::NOTICE, '~ Ghost of nick %s has been killed', $this->network->nick);
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



	public function handleJoinChannels($timer, React\EventLoop\LoopInterface $loop)
	{
		if (!$this->isConnecting()) {
			$loop->cancelTimer($timer);
			return;
		}

		// waiting for identification
		if ($this->network->password && $this->network->setup->nickserv && !$this->isIdentified()) {
			return;
		}

		// no password provided || identification done, proceed with joining
		$loop->cancelTimer($timer);
		$channels = $this->network->channels;

		foreach ($channels as $channel) {
			$this->joinChannel('#' . ltrim($channel, '#'));
		}

		// Stop connecting phase
		$this->connecting = FALSE;
	}


	public function joinChannel($channel)
	{
		$this->send('JOIN ' . $channel);
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


	/**
	 * Returns bot's modes on server or particular channel joined on.
	 * @param NULL|bool|string $key
	 * @param bool $all
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
}
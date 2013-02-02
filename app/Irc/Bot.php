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


	/** @var string */
	protected $uniqueId;

	/** @var string */
	protected $nick;

	/** @var string */
	protected $server;


	/** @var int */
	protected $sentGhost;

	/** @var bool */
	protected $identified = FALSE;



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

		$connection->once('data', function() use ($_this, $network) {
			$_this->status(sprintf('~ Connected to %s on port %d', $network->server, $network->port));
		});

		$connection->on('data', function($data, $conn) use($_this) {
			$_this->buffer($data, callback($_this, 'onDataReceived'), array($conn));
		});

		$connection->on('close', function($conn) use($_this, $eventLoop, $stdin) {
			//$eventLoop->removeReadStream($stdin->socket);
			$_this->onDisconnect($_this, $conn);
			$_this->status('! Connection closed, Aki is aborting.');
			exit;
		});

		$eventLoop->addTimer(0.3, callback($this, 'connect'));
	}


	public function run()
	{
		$this->onDataReceived[] = callback($this, 'watchNick');
		$this->onDataReceived[] = callback($this, 'pingPong');

		$this->connecting = TRUE;
		$this->eventLoop->run();
	}


	public function send($data)
	{
		if ($this->connection->write($data . "\r\n")) {
			$this->onDataSent($data, $this->connection);
		}
	}


	public function status($text)
	{
		fwrite($this->stdout->socket, $text . "\n");
		return $this;
	}


	public function connect($timer, $loop)
	{
		if (!$this->isConnecting()) {
			return;
		}

		$this->nick = $this->network->nick;
		$this->send(sprintf('USER %s aurielle.cz %s :%s', $this->network->ident, $this->nick, $this->network->user));
		$this->send(sprintf('NICK %s', $this->nick));
		$this->status(sprintf('~ Logging in as %s...', $this->nick));

		$connection = $this->connection;
		$_this = $this;

		$this->connection->on('data', callback($this, 'connectHelper'));
		$loop->addPeriodicTimer(0.5, function($timer2) use($loop, $_this, $connection) {
			if (!$_this->isConnecting()) {
				$connection->removeListener('data', callback($_this, 'connectHelper'));
				$loop->cancelTimer($timer2);
			}
		});
	}


	public function buffer($rawData, $callback, $params = array())
	{
		if (!is_callable($callback)) {
			throw new Nette\InvalidArgumentException('Buffer callback is not callable.');
		}

		// Initialize buffer
		static $buffer;

		// Split raw data into pieces of each line and clean them from control characters
		// \xFF is intentionally added here to detect line endings (anyone knows of better solution?)
		$rawData = str_replace(array("\r\n", "\r", "\n"), array("\n", "\n", "\xFF\n"), $rawData);
		$data = explode("\n", $rawData);

		// If something's in buffer, use it and clean the buffer
		if ($buffer) {
			$first = $buffer . array_shift($data);
			$data = array($first) + $data;
			$buffer = NULL;
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
			$buffer = $last;

		} else {
			$data = $data + array($last);
		}

		// Now call the callback
		foreach ($data as $d) {
			call_user_func_array($callback, array_merge(array(rtrim($d, "\xFF")), (array) $params));
		}
	}


	public function connectHelper($data, $conn)
	{
		$this->buffer($data, callback($this, 'handleConnect'), array($conn));
	}




	public function handleConnect($data, $conn)
	{
		$tmp = explode(' ', $data);
		if (is_numeric($tmp[1])) {
			switch ((int) $tmp[1]) {
				case ServerCodes::WELCOME:
					$this->status('~ Received server welcome message');
					break;

				case ServerCodes::NICK_USED:
					$this->alternativeNick();
					$this->ghost();
					break;

				case ServerCodes::UNIQUE_ID:
					$this->uniqueId = $tmp[3];
					$this->status('~ Unique ID saved');
					break;

				case ServerCodes::MOTD_END:
				case ServerCodes::NO_MOTD:
				case ServerCodes::SPAM:
					$this->status('~ Received end of MOTD');
					$this->eventLoop->addTimer(0.8, callback($this, 'identify'));
					//$this->connecting = FALSE; // CHANGE!
					break;

				default:
					break;
			}

		} elseif ($tmp[1] === 'NOTICE') {
			if (Nette\Utils\Strings::startsWith(ltrim($tmp[0], ':'), $this->network->setup->nickserv)) {
				$this->nickservNotice($data, $conn);

			} elseif (Nette\Utils\Strings::startsWith(ltrim($tmp[0], ':'), $this->network->setup->chanserv)) {
				$this->chanservNotice($data, $conn);
			}

		} elseif ($tmp[1] === 'MODE') {
			$this->status(sprintf('~ Setting mode %s for %s', ltrim($tmp[3], ':'), $tmp[2]));
			if (ltrim($tmp[3], ':') === '+r' && !$this->identified) {
				$this->identified = TRUE;
				$this->status('~ Password accepted, Aki is recognized');
			}
		}
	}

	protected function alternativeNick()
	{
		static $nicks;
		if ($nicks === NULL) {
			$nicks = $this->network->alternativeNicks;
		}

		// Alternative nicks exhausted
		if($nicks === array()) {
			$this->send('QUIT :No alternative nicks available.');
			$this->status('! No more alternative nicks available');
			return;
		}

		$newNick = array_shift($nicks);
		$this->nick = $newNick;
		$this->send(sprintf('NICK %s', $newNick));
		$this->status(sprintf('~ Using alternative nick %s', $newNick));
	}

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

	public function afterGhost()
	{
		$this->send(sprintf('NICK %s', $this->network->nick));
		$this->nick = $this->network->nick;
		$this->eventLoop->addTimer(0.8, callback($this, 'identify'));
	}

	public function watchNick($data, $connection)
	{
		$tmp = explode(' ', $data, 4);
		if (!in_array($tmp[1], ServerCodes::$nickSetters)) {
			return;
		}

		$server = ltrim($tmp[0], ':');
		if ($this->server !== $server) {
			$this->server = $server;
			$this->status(sprintf('~ Updating server to %s', $server));
		}

		$nick = $tmp[2];
		if ($this->nick !== $nick) {
			$this->nick = $nick;
			$this->status(sprintf('~ Updating nick to %s', $nick));
		}
	}

	public function pingPong($data, $connection)
	{
		$tmp = explode(' ', $data, 2);
		if ($tmp[0] === 'PING') {
			$this->send("PONG {$tmp[1]}");
		}
	}

	protected function nickservNotice($data, $connection)
	{
		$tmp = explode(' ', $data, 4);
		$msg = Utils::stripFormatting(ltrim($tmp[3], ':'));

		if (stripos($msg, 'incorrect') !== FALSE || stripos($msg, 'denied') !== FALSE) {
			$this->status('! Incorrect password passed for NickServ');
		}

		if (stripos($msg, 'is not registered') !== FALSE || stripos($msg, "isn't registered") !== FALSE) {
			$this->status(sprintf('~ Current nick (%s) is not registered', $this->nick));
		}

		if (stripos($msg, 'now recognized') !== FALSE ||
			stripos($msg, 'already identified') !== FALSE ||
			stripos($msg, 'password accepted') !== FALSE ||
			stripos($msg, 'now identified') !== FALSE) {
			$this->identified = TRUE;
			$this->status('~ Password accepted, Aki is recognized');
		}

		if (stripos($msg, 'killed') !== FALSE && (strpos($msg, $this->network->nick) !== FALSE || stripos($msg, 'ghost') !== FALSE)) {
			$this->status(sprintf('~ Ghost of nick %s has been killed', $this->network->nick));
			$this->identified = FALSE;
			$this->sentGhost = NULL;
		}

		if (stripos($msg, 'currently') !== FALSE && (stripos($msg, 'is not') !== FALSE || stripos($msg, "isn't") !== FALSE)) {
			$this->eventLoop->addTimer(1, callback($this, 'afterGhost'));
		}
	}

	protected function chanservNotice($data, $connection)
	{

	}


	public function isConnecting()
	{
		return $this->connecting;
	}
}
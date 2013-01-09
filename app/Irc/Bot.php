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


	public $onDataReceived = array();
	public $onDataSent = array();



	public function __construct(Network $network, React\EventLoop\LoopInterface $eventLoop, Connection $connection, Aki\Stream\Stdin $stdin, Aki\Stream\Stdout $stdout)
	{
		$this->network = $network;
		$this->eventLoop = $eventLoop;
		$this->connection = $connection;
		$this->stdin = $stdin;
		$this->stdout = $stdout;

		/*$this->eventLoop->addReadStream($stdin->socket, function($stream) {
			$data = fread($stream, 512);
			echo 'r: ' . $data;
		});*/

		$_this = $this;
		$this->connection->on('data', function($data) use($_this) {
			$data = explode("\n", trim($data));
			foreach ($data as $d) {
				$_this->onDataReceived(trim($d));
			}
		});
	}


	public function run()
	{
		$this->eventLoop->run();
	}
}
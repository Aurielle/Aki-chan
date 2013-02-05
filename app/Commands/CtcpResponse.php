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



/**
 * Responds to CTCP commands such as VERSION.
 */
class CtcpResponse extends Nette\Object
{
	/** @var Aki\Irc\Bot */
	protected $bot;

	public $fingerResponses = array(
		'%s, you baka!!!',
		'You perv!',
		"Aren't you perverted, %s?",
		'Fine. Prepare your anus.',
		'Schlick, schlick, schlick!',
		'Faster, fasteeeer!',
		'Kyaaaaaaaa~',
	);



	public function __construct(Aki\Irc\Bot $bot)
	{
		$this->bot = $bot;
		$this->bot->onDataReceived[] = callback($this, 'onDataReceived');
	}


	public function onDataReceived($data, $connection)
	{
		if ($matches = Nette\Utils\Strings::match($data, '#\:([^!]+)\![^ ]+ PRIVMSG ' . preg_quote($this->bot->getNick(), '#') . " :\x01(VERSION|PING|TIME|SOURCE|FINGER) ?(.+)?\x01#")) {
			$this->{strtolower($matches[2])}($matches);
		}
	}



	protected function version($matches)
	{
		$this->bot->send("NOTICE $matches[1] :\x01VERSION " . Aki\Aki::NAME . " " . Aki\Aki::VERSION . "\x01");
	}

	protected function ping($matches)
	{
		$this->bot->send("NOTICE $matches[1] :\x01PING " . time() . "\x01");
	}

	protected function time($matches)
	{
		$this->bot->send("NOTICE $matches[1] :\x01TIME " . date('r') . "\x01");
	}

	protected function source($matches)
	{
		$this->bot->send("NOTICE $matches[1] :\x01SOURCE https://github.com/Aurielle/Aki-chan\x01");
	}

	protected function finger($matches)
	{
		$random = sprintf($this->fingerResponses[array_rand($this->fingerResponses)], $matches[1]);
		$this->bot->send("NOTICE $matches[1] :\x01FINGER $random\x01");
	}
}
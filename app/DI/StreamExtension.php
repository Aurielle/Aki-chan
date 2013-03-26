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



class StreamExtension extends Nette\Config\CompilerExtension
{
	/**
	 * Loads network configuration
	 * @throws Nette\InvalidStateException
	 */
	public function loadConfiguration()
	{
		$container = $this->getContainerBuilder();

		$container->addDefinition($this->prefix('irc'))
			->setClass('Aki\Stream\IrcSocket');

		$container->addDefinition($this->prefix('stdin'))
			->setClass('Aki\Stream\Stdin');

		$container->addDefinition($this->prefix('stdout'))
			->setClass('Aki\Stream\Stdout');
	}
}
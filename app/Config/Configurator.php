<?php

/**
 * Aki-chan (version 1.0-dev released on $WCDATE$, http://aki.aurielle.cz)
 *
 * Copyright (c) 2013 Václav Vrbka (aurielle@aurielle.cz)
 *
 * For the full copyright and license information, please view
 * the file license.md that was distributed with this source code.
 */

namespace Aki\Config;

use Aki, Nette;



class Configurator extends Nette\Config\Configurator
{
	/**
	 * @return array
	 */
	protected function getDefaultParameters()
	{
		$trace = debug_backtrace(FALSE);
		$debugMode = static::detectDebugMode();
		return array(
			'rootDir' => isset($trace[1]['file']) ? dirname($trace[1]['file']) : NULL,
			'appDir' => realpath(__DIR__ . '/..'),
			'wwwDir' => isset($_SERVER['SCRIPT_FILENAME']) ? dirname($_SERVER['SCRIPT_FILENAME']) : NULL,
			'debugMode' => $debugMode,
			'productionMode' => !$debugMode,
			'environment' => $debugMode ? 'development' : 'production',
			'consoleMode' => PHP_SAPI === 'cli',
			'container' => array(
				'class' => 'SystemContainer',
				'parent' => 'Nette\DI\Container',
			)
		);
	}
}
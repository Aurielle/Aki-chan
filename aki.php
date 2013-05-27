<?php

/**
 * Aki-chan (version 1.0-dev released on $WCDATE$, http://aki.aurielle.cz)
 *
 * Copyright (c) 2013 Václav Vrbka (aurielle@aurielle.cz)
 *
 * For the full copyright and license information, please view
 * the file license.md that was distributed with this source code.
 */

// Vendor libs
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/Config/Configurator.php';

// CLI only
if (PHP_SAPI !== 'cli') {
	die('<h1>Please run Aki from the command line.</h1>');
}

// Causes shutdown handler to be called normally when the script is exitted via ctrl+c
// works only on Linux and PHP compiled with --enable-pcntl
declare(ticks=1);

function sigint() {
	exit;
}

if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGINT, 'sigint');
	pcntl_signal(SIGTERM, 'sigint');
}


// Configuration
$configurator = new Aki\Config\Configurator();

// Error visualization & logging
$configurator->setDebugMode(array('AURIELLE'));
$configurator->enableDebugger(__DIR__ . '/log/error');

// Autoloader and cache
$configurator->setTempDirectory(__DIR__ . '/temp');
$configurator->createRobotLoader()
	->addDirectory(__DIR__ . '/app')
	->register();

// Network settings
// Redundant? This should be always enabled in CLI
if (!isset($argc)) {
	throw new Nette\NotSupportedException("PHP setting 'register_argc_argv' must be enabled in order to run Aki.");

} elseif ($argc === 1) {
	throw new Nette\InvalidStateException("Please specify network (neon file) to connect to.");

} else {
	array_shift($argv);	// Name of this script, or '-' if included from another (will fail in case above)

	// Let's look for that config file, shall we?
	$network = reset($argv);         	// only first argument
	$network = trim($network, '"\'');	// get rid of quotes
	$network = !Nette\Utils\Strings::endsWith($network, '.neon') ? $network . '.neon' : $network;

	if (!is_file(__DIR__ . '/config/' . $network)) {
		throw new Nette\FileNotFoundException("Config file '$network' doesn't exist.");
	}
}

// Configuration
$configurator->addConfig(__DIR__ . '/config/config.neon', $configurator::NONE);
$configurator->addConfig(__DIR__ . '/config/' . $network, $configurator::NONE);
if ($configurator->isDebugMode()) {
	$configurator->addConfig(__DIR__ . '/config/config.dev.neon', $configurator::NONE);
}

$configurator->onCompile[] = function($configurator, $compiler) {
	$compiler->addExtension('irc', new Aki\DI\IrcExtension);
	$compiler->addExtension('stream', new Aki\DI\StreamExtension);
	$compiler->addExtension('curl', new Kdyby\Curl\DI\CurlExtension);
	$compiler->addExtension('events', new Kdyby\Events\DI\EventsExtension);
};
$container = $configurator->createContainer();
$container->irc->bot->run();
<?php

/**
 * Aki-chan (version 1.0-dev released on $WCDATE$, http://aki.aurielle.cz)
 *
 * Copyright (c) 2013 Václav Vrbka (aurielle@aurielle.cz)
 *
 * For the full copyright and license information, please view
 * the file license.md that was distributed with this source code.
 */

namespace Aki\Irc\Event;

use Aki, Nette, React;



interface IEvent
{
	/**
	 * Returns the event type.
	 * @return string
	 */
	function getType();


	/**
	 * Sets the event type.
	 * @param string
	 */
	function setType($type);


	/**
	 * Returns raw data sent by the server.
	 * @return string
	 */
	function getRawData();


	/**
	 * Sets raw data sent by the server.
	 * @param string
	 */
	function setRawData($rawData);
}
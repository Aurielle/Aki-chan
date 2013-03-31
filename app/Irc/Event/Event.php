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



/**
 * Represents an event sent by the server.
 */
abstract class Event extends Nette\Object implements IEvent
{
	/** @var string */
	protected $type;

	/** @var string */
	protected $rawData;


	/**
	 * Returns the event type.
	 * @return string
	 */
	public function getType()
	{
		return $this->type;
	}


	/**
	 * Sets the event type.
	 * @param string
	 * @return Aki\Irc\Event\Event Provides a fluent interface
	 */
	public function setType($type)
	{
		$this->type = (string) $type;
		return $this;
	}


	/**
	 * Returns raw data sent by the server.
	 * @return string
	 */
	function getRawData()
	{
		return $this->rawData;
	}


	/**
	 * Sets raw data sent by the server.
	 * @param string
	 * @return Aki\Irc\Event\Event Provides a fluent interface
	 */
	function setRawData($rawData)
	{
		$this->rawData = (string) $rawData;
		return $this;
	}
}
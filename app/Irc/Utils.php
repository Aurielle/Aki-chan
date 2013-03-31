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

use Aki, Nette;



class Utils extends Nette\Object
{
	/**
	 * Static class - cannot be instantiated.
	 */
	public function __construct()
	{
		throw new Nette\StaticClassException;
	}


	/**
	 * Strip colours from given string.
	 */
	public static function stripColours($s)
	{
		return Nette\Utils\Strings::replace($s, '~\x03(?:\d{1,2},\d{1,2}|\d{1,2}|,\d{1,2}|)~', '');
	}


	/**
	 * Strip bold tags from given string.
	 */
	public static function stripBold($s)
	{
		return str_replace("\x02", '', $s);
	}


	/**
	 * Strip reverse tags from given string.
	 */
	public static function stripReverse($s)
	{
		return str_replace("\x16", '', $s);
	}


	/**
	 * Strip underline tags from given string.
	 */
	public static function stripUnderline($s)
	{
		return str_replace(array("\x1f", "\x1F"), array('', ''), $s);
	}


	/**
	 * Strip all formatting from given string.
	 */
	public static function stripFormatting($s)
	{
		$s = self::stripColours($s);
		$s = self::stripBold($s);
		$s = self::stripReverse($s);
		$s = self::stripUnderline($s);
		return str_replace(array("\x0f", "\x0F"), array('', ''), $s);
	}


	/**
	 * Determines whether a given string is a valid IRC channel name.
	 * @param string $string String to analyze
	 * @return bool
	 */
	public static function isChannelName($string)
	{
		// Per the 2000 RFCs 2811 and 2812, channels may begin with &, #, +, or !
		return (strspn($string, '#&+!', 0, 1) >= 1);
	}
}
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


	public static function stripColours($s)
	{
		return Nette\Utils\Strings::replace($s, '~\x03(?:\d{1,2},\d{1,2}|\d{1,2}|,\d{1,2}|)~', '');
	}

	public static function stripBold($s)
	{
		return str_replace("\x02", '', $s);
	}

	public static function stripReverse($s)
	{
		return str_replace("\x16", '', $s);
	}

	public static function stripUnderline($s)
	{
		return str_replace(array("\x1f", "\x1F"), array('', ''), $s);
	}

	public static function stripFormatting($s)
	{
		$s = self::stripColours($s);
		$s = self::stripBold($s);
		$s = self::stripReverse($s);
		$s = self::stripUnderline($s);
		return str_replace(array("\x0f", "\x0F"), array('', ''), $s);
	}
}
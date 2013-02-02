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



class ServerCodes extends Nette\Object
{
	const WELCOME = 001,
		YOUR_HOST = 002,
		CREATED = 003,
		MY_INFO = 004,
		BOUNCE = 005,
		UNIQUE_ID = 042,

		STATSCON = 250,
		LUSERCLIENT = 251,
		LUSEROP = 252,
		LUSERCHANNELS = 254,
		LUSERME = 255,
		LOCALUSERS = 265,
		GLOBALUSERS = 266,

		TOPIC = 332,
		TOPICWHOTIME = 333,
		NAMEREPLY = 353,
		ENDOFNAMES = 366,
		MOTD = 372,
		MOTD_START = 375,
		MOTD_END = 376,
		SPAM = 377,

		NO_MOTD = 422,
		NICK_USED = 433;

	/** @var array */
	public static $nickSetters = array(
		self::WELCOME, self::YOUR_HOST, self::CREATED, self::MY_INFO, self::STATSCON, self::LUSERCLIENT, self::LUSEROP,
		self::LUSERCHANNELS, self::LUSERME, self::LOCALUSERS, self::GLOBALUSERS, self::MOTD, self:: MOTD_START,
		self::MOTD_END, self::TOPICWHOTIME, self::NAMEREPLY, self::TOPIC, self::ENDOFNAMES, self::BOUNCE,
	);


	/**
	 * Static class - cannot be instantiated.
	 */
	public function __construct()
	{
		throw new Nette\StaticClassException;
	}
}
<?php

/**
 * Aki-chan (version 1.0-dev released on $WCDATE$, http://aki.aurielle.cz)
 *
 * Copyright (c) 2013 Václav Vrbka (aurielle@aurielle.cz)
 *
 * For the full copyright and license information, please view
 * the file license.md that was distributed with this source code.
 */

namespace Aki\Twitter;

use Aki, Nette;



class Twitter extends \TijsVerkoyen\Twitter\Twitter
{
	public function __construct($consumerKey, $consumerSecret)
	{
		if (empty($consumerKey) || empty($consumerSecret)) {
			throw new Nette\InvalidArgumentException('Please fill in twitter application details in config.neon.');
		}

		parent::__construct($consumerKey, $consumerSecret);
	}
}
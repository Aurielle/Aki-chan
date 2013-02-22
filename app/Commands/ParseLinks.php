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
use Kdyby\Curl;



/**
 * Displays contents of link when posted into a channel.
 */
class ParseLinks extends Nette\Object
{
	/** @var Aki\Irc\Bot */
	protected $bot;

	/** @var Kdyby\Curl\CurlSender */
	protected $curlSender;

	/** @var int */
	protected $limit = 1;



	public function __construct(Aki\Irc\Bot $bot, Curl\CurlSender $curlSender)
	{
		$this->bot = $bot;
		$this->curlSender = $curlSender;

		$this->bot->onDataReceived[] = callback($this, 'onDataReceived');
	}


	public function onDataReceived($data, $connection)
	{
		if (!($matches = Nette\Utils\Strings::match($data, '~^\:([^!]+)\![^ ]+ PRIVMSG (\#[^ ]+) \:(.*https?\:\/\/.*)~i'))) {
			return;
		}

		if ($matches[1] === $this->bot->getNick()) {
			return;	// RECURSION!
		}


		$i = 0;
		foreach ($this->matchLinks($matches[3]) as $link) {
			if ($i >= $this->limit) {
				break;
			}

			$i++;
			if (Nette\Utils\Strings::endsWith($link[1], 'youtube.com') || Nette\Utils\Strings::endsWith($link[1], 'youtu.be')) {
				$response = $this->youtube($link[0]);

			} elseif (Nette\Utils\Strings::endsWith($link[1], 'twitter.com')) {
				$response = $this->twitter($link[0]);

			} else {
				$response = $this->regularHtml($link[0]);
			}

			if ($response === FALSE) {
				$i--;
				continue;
			}

			$this->bot->send(sprintf('PRIVMSG %s :%s', $matches[2], $response));
		}
	}


	public function getLimit()
	{
		return $this->limit;
	}


	public function setLimit($limit)
	{
		$this->limit = (int) $limit;
		return $this;
	}


	private function matchLinks($data)
	{
		// Comes from Nette\Utils\Validators::isUrl()
		$alpha = "a-z\x80-\xFF";
		$domain = "[0-9$alpha](?:[-0-9$alpha]{0,61}[0-9$alpha])?";
		$topDomain = "[$alpha][-0-9$alpha]{0,17}[$alpha]";
		return Nette\Utils\Strings::matchAll($data, "(https?://((?:$domain\\.)*$topDomain|\\d{1,3}\.\\d{1,3}\.\\d{1,3}\.\\d{1,3})(:\\d{1,5})?(/\\S*)?)i");
	}


	private function youtube($link)
	{
		// Simplified version: (?<=v=)[a-zA-Z0-9-]+(?=&)|(?<=v\/)[^&\n]+|(?<=v=)[^&\n]+|(?<=youtu.be/)[^&\n]+
		// Doesn't catch urls like http://www.youtube.com/user/Scobleizer#p/u/1/1p3vcRhsYGo
		//
		// This more complex version comes from http://stackoverflow.com/questions/5830387/how-to-find-all-youtube-video-ids-in-a-string-using-a-regex/5831191#5831191
		$regex = '~
			# Match non-linked youtube URL in the wild. (Rev:20111012)
			https?://         # Required scheme. Either http or https.
			(?:[0-9A-Z-]+\.)? # Optional subdomain.
			(?:               # Group host alternatives.
			  youtu\.be/      # Either youtu.be,
			| youtube\.com    # or youtube.com followed by
			  \S*             # Allow anything up to VIDEO_ID,
			  [^\w\-\s]       # but char before ID is non-ID char.
			)                 # End host alternatives.
			([\w\-]{11})      # $1: VIDEO_ID is exactly 11 chars.
			(?=[^\w\-]|$)     # Assert next char is non-ID or EOS.
			(?!               # Assert URL is not pre-linked.
			  [?=&+%\w]*      # Allow URL (query) remainder.
			  (?:             # Group pre-linked alternatives.
			    [\'"][^<>]*>  # Either inside a start tag,
			  | </a>          # or inside <a> element text contents.
			  )               # End recognized pre-linked alts.
			)                 # End negative lookahead assertion.
			[?=&+%\w-]*        # Consume any URL (query) remainder.
			~ix';
		$matches = Nette\Utils\Strings::match($link, $regex);
		$vid = $matches[1];

		// @todo: use curl
		$url = "http://gdata.youtube.com/feeds/api/videos/$vid?v=2&alt=json";
		$ch = new Curl\Request($url);
		try {
			$res = $this->curlSender->send($ch);

		} catch (\Exception $e) {
			Nette\Diagnostics\Debugger::log($e, Nette\Diagnostics\Debugger::ERROR);
			// @todo: fire custom logger

			return FALSE;
		}

		$json = json_decode($res->getResponse());
		return sprintf('[YouTube] %s • http://youtu.be/%s', $json->entry->title->{'$t'}, $vid);
	}


	private function twitter($link)
	{
		$regex = '~https?://(?:www\.)?twitter\.com.*/status(?:es)?/([0-9]+)~';
		$matches = Nette\Utils\Strings::match($link, $regex);
		$tweetid = $matches[1];

		$url = "http://api.twitter.com/1/statuses/show.json?id=$tweetid";
		$ch = new Curl\Request($url);
		try {
			$res = $this->curlSender->send($ch);

		} catch (\Exception $e) {
			Nette\Diagnostics\Debugger::log($e, Nette\Diagnostics\Debugger::ERROR);
			// @todo: fire custom logger

			return FALSE;
		}

		$json = json_decode($res->getResponse());
		$text = str_replace(array("\r\n", "\r", "\n"), array("\n", "\n", ' '), $json->text);
		$text = trim(htmlspecialchars_decode($text, ENT_QUOTES));
		return sprintf('<%s> %s', $json->user->screen_name, $text);
	}


	private function regularHtml($link)
	{
		$ch = new Curl\Request($link);
		$ch->setCertificationVerify(FALSE);	// in case of https links

		try {
			$res = $this->curlSender->send($ch);

		} catch (\Exception $e) {
			Nette\Diagnostics\Debugger::log($e, Nette\Diagnostics\Debugger::ERROR);
			// @todo: fire custom logger
			// @todo: do not log in case of common errors (404)

			return FALSE;
		}

		// Non-html page
		if (!$res instanceof Curl\HtmlResponse) {
			return FALSE;
		}

		$title = Nette\Utils\Strings::match($res->getResponse(), '#\<title[^>]*\>(.+)\<\/title\>#i');
		return sprintf('[Web] %s', html_entity_decode($title[1], ENT_QUOTES));	// title can contain any entity
	}
}
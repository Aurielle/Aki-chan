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
use Kdyby\Events;



/**
 * Displays contents of link when posted into a channel.
 */
class ParseLinks extends Nette\Object implements Events\Subscriber
{
	/** @var Aki\Irc\Message */
	protected $message;

	/** @var Kdyby\Curl\CurlSender */
	protected $curlSender;

	/** @var Aki\Irc\Logger */
	protected $logger;

	/** @var Aki\Twitter\Twitter */
	protected $twitter;

	/** @var int */
	protected $limit = 1;



	public function __construct(Aki\Irc\Message $message, Curl\CurlSender $curlSender, Aki\Irc\Logger $logger, Aki\Twitter\Twitter $twitter)
	{
		$this->message = $message;
		$this->curlSender = $curlSender;
		$this->twitter = $twitter;
	}


	public function onDataReceived($data)
	{
		if (!($matches = Nette\Utils\Strings::match($data->rawData, '~^\:([^!]+)\![^ ]+ PRIVMSG (\#[^ ]+) \:(.*https?\:\/\/.*)~i'))) {
			return;
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

			} elseif (Nette\Utils\Strings::endsWith($link[1], 'facebook.com')) {
				$response = $this->facebook($link[0]);

			} else {
				$response = $this->regularHtml($link[0]);
			}

			if ($response === FALSE) {
				$i--;
				continue;
			}

			$this->message->send(sprintf('PRIVMSG %s :%s', $matches[2], $response));
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


	public function getSubscribedEvents()
	{
		return array('Aki\Irc\Message::onDataReceived');
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

		try {
			$json = $this->twitter->statusesShow($tweetid);

		} catch (\Exception $e) {
			Nette\Diagnostics\Debugger::log($e, Nette\Diagnostics\Debugger::ERROR);
			// @todo: fire custom logger

			return FALSE;
		}

		$text = isset($json['retweeted_status']) ? $json['retweeted_status']['text'] : $json['text'];
		$user = isset($json['retweeted_status']) ? $json['retweeted_status']['user']['screen_name'] : $json['user']['screen_name'];

		$text = str_replace(array("\r\n", "\r", "\n"), array("\n", "\n", ' '), $text);
		$text = trim(htmlspecialchars_decode($text, ENT_QUOTES));
		return sprintf('<%s> %s', $user, $text);
	}


	private function facebook($link)
	{
		// don't parse FB links
		return FALSE;
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

		$title = Nette\Utils\Strings::match($res->getResponse(), '#\\<title[^>]*\\>(.*?)\\<\\/title\\>#is');
		if (!$title || !$title[1]) {	// in case regexp does not match, need tests for this || title is empty
		    return FALSE;
		}

		return sprintf('[Web] %s', html_entity_decode(trim($title[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));	// title can contain any entity
	}
}
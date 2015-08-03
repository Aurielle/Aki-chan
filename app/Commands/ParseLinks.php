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

	/** @var string */
	protected $youtubeApiKey;

	/** @var array */
	protected $mediaExtensions = array(
		'jpg', 'jpeg', 'gif', 'png', 'mp4', 'gifv', 'webm', 'flv', 'mkv', 'avi', 'ogg', 'm4a', 'mka', 'm4v',
		'mp3', 'aac', 'ass', 'srt', 'mks', 'm2ts', 'ts', 'wmv', 'wma', 'pdf', 'doc', 'docx', 'xls', 'xlsx',
		'ppt', 'pptx', 'flac', 'waw', 'torrent', 'bmp', 'psd', 'ai', 'cr2', 'rar', 'zip', 'exe', 'dll', '7z', 'iso',
		'tar', 'gz', 'jar', 'dng', 'tif', 'tiff', 'mov', 'ape', 'dta', 'truehd',
	);


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
			$domain = substr($link[2], 0, strpos($link[2], '/'));
			if (Nette\Utils\Strings::endsWith($domain, 'youtube.com') || Nette\Utils\Strings::endsWith($domain, 'youtu.be')) {
				$response = $this->youtube($link[1]);

			} elseif (Nette\Utils\Strings::endsWith($domain, 'twitter.com')) {
				$response = $this->twitter($link[1]);

			} elseif (Nette\Utils\Strings::endsWith($domain, 'facebook.com')) {
				$response = $this->facebook($link[1]);

			} elseif ($this->containsMedia($link[1])) {
				$response = $this->media($link[1], $matches[1]);

			} else {
				$response = $this->regularHtml($link[1]);
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
		// From: http://daringfireball.net/2010/07/improved_regex_for_matching_urls
		$regex = '~
			(?xi)
			\b
			(                       # Capture 1: entire matched URL
			  (?:
			    https?://               # http or https protocol
			    |                       #   or
			    www\d{0,3}[.]           # "www.", "www1.", "www2." … "www999."
			    |                           #   or
			    [a-z0-9.\-]+[.][a-z]{2,4}/  # looks like domain name followed by a slash
			  )
			  (                       # One or more: (?: removed)
			    [^\s()<>]+                  # Run of non-space, non-()<>
			    |                           #   or
			    \(([^\s()<>]+|(\([^\s()<>]+\)))*\)  # balanced parens, up to 2 levels
			  )+
			  (?:                       # End with:
			    \(([^\s()<>]+|(\([^\s()<>]+\)))*\)  # balanced parens, up to 2 levels
			    |                               #   or
			    [^\s`!()\[\]{};:\'".,<>?«»“”‘’]        # not a space or one of these punct chars
			  )
			)~ix';
		return Nette\Utils\Strings::matchAll($data, $regex);
	}


	private function youtube($link)
	{
		if (!$this->youtubeApiKey) {
			return FALSE;
		}

		// Simplified version: (?<=v=)[a-zA-Z0-9-]+(?=&)|(?<=v\/)[^&\n]+|(?<=v=)[^&\n]+|(?<=youtu.be/)[^&\n]+
		// Doesn't catch urls like http://www.youtube.com/user/Scobleizer#p/u/1/1p3vcRhsYGo
		//
		// This more complex version comes from http://stackoverflow.com/questions/5830387/how-to-find-all-youtube-video-ids-in-a-string-using-a-regex/5831191#5831191
		$regex = '~
        # Match non-linked youtube URL in the wild. (Rev:20130823)
        https?://         # Required scheme. Either http or https.
        (?:[0-9A-Z-]+\.)? # Optional subdomain.
        (?:               # Group host alternatives.
          youtu\.be/      # Either youtu.be,
        | youtube         # or youtube.com or
          (?:-nocookie)?  # youtube-nocookie.com
          \.com           # followed by
          \S*             # Allow anything up to VIDEO_ID,
          [^\w\s-]       # but char before ID is non-ID char.
        )                 # End host alternatives.
        ([\w-]{11})      # $1: VIDEO_ID is exactly 11 chars.
        (?=[^\w-]|$)     # Assert next char is non-ID or EOS.
        (?!               # Assert URL is not pre-linked.
          [?=&+%\w.-]*    # Allow URL (query) remainder.
          (?:             # Group pre-linked alternatives.
            [\'"][^<>]*>  # Either inside a start tag,
          | </a>          # or inside <a> element text contents.
          )               # End recognized pre-linked alts.
        )                 # End negative lookahead assertion.
        [?=&+%\w.-]*        # Consume any URL (query) remainder.
        ~ix';
		$matches = Nette\Utils\Strings::match($link, $regex);
		$vid = $matches[1];

		$url = "https://www.googleapis.com/youtube/v3/videos?part=snippet&id=$vid&key=" . $this->youtubeApiKey;
		$ch = new Curl\Request($url);
		$ch->setCertificationVerify(FALSE);
		try {
			$res = $this->curlSender->send($ch);

		} catch (\Exception $e) {
			Nette\Diagnostics\Debugger::log($e, Nette\Diagnostics\Debugger::ERROR);
			// @todo: fire custom logger

			return FALSE;
		}

		$json = json_decode($res->getResponse());
		return sprintf('[YouTube] %s • http://youtu.be/%s', $json->items[0]->snippet->title, $vid);
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
		$flags = ENT_QUOTES;
		if (PHP_VERSION_ID >= 50400) {
			$flags = $flags | ENT_HTML5;
		}

		if (!$title || !$title[1]) {	// in case regexp does not match, need tests for this || title is empty
		    return FALSE;
		}

		$t = trim($title[1]);
		$t = str_replace(array("\r\n", "\r", "\n"), array("\n", "\n", ' '), $t);
		return sprintf('[Web] %s', html_entity_decode($t, $flags, 'UTF-8'));	// title can contain any entity
	}


	private function containsMedia($link)
	{
		foreach ($this->mediaExtensions as $ext) {
			if (Nette\Utils\Strings::endsWith($link, ".$ext")) {
				return TRUE;
			}
		}

		return FALSE;
	}

	private function media($link, $sender)
	{
		if (Nette\Utils\Strings::endsWith($link, '.mkv')) {
			return sprintf("%s: Please don't rape me with such big files! t ( - _ - t )", $sender);
		}

		return FALSE;
	}

	/**
	 * @param string $youtubeApiKey
	 * @return ParseLinks Provides a fluent interface.
	 */
	public function setYoutubeApiKey($youtubeApiKey)
	{
		$this->youtubeApiKey = $youtubeApiKey;
		return $this;
	}
}
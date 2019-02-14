<?php
/**
 * @package    AcyMailingConnector
 *
 * @author     HR-IT-Solutions GmbH Florian Häusler <info@hr-it-solutions.com>
 * @license    http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 only
 * @copyright  Copyright (C) 2019 - 2019 HR-IT-Solutions GmbH
 **/

defined('_JEXEC') or die;

/**
 * Class AcyMailingConnector
 *
 * @since 3.9
 */
class AcyMailingConnector {

	var $pkey = '';

	private $subject;
	private $body;
	private $altbody;

	// Check params
	private $checked_UserID;
	private $checked_ListID;

	// Acy email params
	private $mailid;
	private $type            = "notification";
	private $email_language  = "";
	private $email_visible   = "0";
	private $email_alias     = "notification";
	private $email_summary   = "";

	// Queue priority
	private $priority = 3;

	// Ignore valide subscription acymailing and lists
	public $ignoreSubscription = false;

	public function __construct() {	}

	/**
	 * checkListSubscription
	 *
	 * @param $userID int the joomla user id.
	 * @param $listID int the acymailing list id.
	 *
	 * @since 3.9
	 *
	 * @return bool on success
	 */
	public function checkListSubscription($userID, $listID)
	{
		if($this->ignoreSubscription){
			return true;
		}

		$db = JFactory::getDbo();

		$query = $db->getQuery(true);

		$query->select($db->qn('SUB.subid'))
			->from('#__acymailing_subscriber AS SUB')
			->join('LEFT', '#__acymailing_listsub AS L ON (L.subid = SUB.subid)')
			->where($db->qn('SUB.userid') . ' = ' . $db->q($userID))
			->where($db->qn('L.listid') . ' = ' . $db->q($listID))
			->where($db->qn('L.status') . ' = 1');

		$db->setQuery($query);

		$result = $db->loadResult();

		if($result)
		{
			$this->checked_UserID = $userID;
			$this->checked_ListID = $listID;
		}

		return $result;
	}

	/**
	 * getJUserList_AcyListSub
	 *
	 * Get Joomla User Object List from AcyListSubscriptions
	 * e.g. for prozessing addMail() with params like {id}, {name}, etc.
	 *
	 * @param $listID int the acymailing list id.
	 *
	 * @since 3.9
	 *
	 * @return mixed a minimal User ObjectList of valid subscriptions included in this AcyMailing list <br>
	 *               • the list includes #__user cols {id}, {name}, {username}, {email} <br>
	 *               • the list insludes #__acymailing_subscriber cols {subid}
	 *
	 */
	public function getJUserList_AcyListSub($listID){

		$db = JFactory::getDbo();

		$query = $db->getQuery(true);

		$query->select($db->qn(array('SUB.subid','JU.id','JU.name','JU.username','JU.email')))
			->from('#__acymailing_listsub AS L')
			->join('LEFT', '#__acymailing_subscriber AS SUB ON (SUB.subid = L.subid)')
			->join('LEFT', '#__users AS JU ON (JU.id = SUB.userid)')
			->where($db->qn('JU.block') . ' = 0')
			->where($db->qn('L.listid') . ' = ' . (int) $listID)
			->where($db->qn('L.status') . ' = 1');

		$db->setQuery($query);

		return $db->loadObjectList();
	}

	/**
	 * loadTemplate
	 *
	 * @param $id
	 *
	 * @since 3.9
	 * @return boolean
	 */
	public function setTemplate($id)
	{
		$db = JFactory::getDbo();

		$query = $db->getQuery(true);

		$query->select($db->qn(array('subject','body')))
			->from('#__acymailing_template')
			->where($db->qn('tempid') . ' = ' . $db->q( (int) $id));

		$db->setQuery($query);

		$result = $db->loadObject();

		if($result)
		{
			$this->subject  = $result->subject;
			$this->body     = $result->body;

			return true;
		}

		return false;
	}

	/**
	 * setPriority
	 *
	 * @param $priority int The priority for mailing queue (1-4 suggested) 1 is hight, 4 is low, default = 3
	 *
	 * @since 3.9
	 */
	public function setPriority($priority)
	{
		$this->priority = (int) $priority;
	}

	/**
	 * addMail
	 *
	 * @param int   $userID            The joomla user id.
	 * @param int   $listID            The acymailing list id.
	 * @param array $search            The value being searched for, otherwise known as the needle.
	 * @param array $replace           The replacement value that replaces found searchvalues.
	 * @param int   $templateID        The email template, optional if not already set by setTemplate($id).
	 *
	 * @since 3.9
	 *
	 * @return bool on success
	 * @throws
	 */
	public function addMail($userID, $listID, $search, $replace, $templateID = false)
	{

		// check ListSubscription (if not checked)
		if ($this->checked_UserID !== $userID &&
			$this->checked_ListID != $listID)
		{
			if(!$this->checkListSubscription($userID, $listID) && !$this->ignoreSubscription)
			{
				JFactory::getApplication()->enqueueMessage('Benutzer hat kein gültiges Listen Abo','warning');

				$this->checked_UserID = false;

				return false;
			}

			$this->checked_UserID = $userID;
			$this->checked_ListID = $listID;
		}

		// set Template (if not set)
		if (empty($this->subject) || empty($this->body))
		{
			if ($templateID && !$this->setTemplate($templateID))
			{
				JFactory::getApplication()->enqueueMessage('E-Mail Template konnte nicht geladen werden!','error');

				return false;
			}
		}

		// get subscriber id
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('subid')
			->from($db->qn('#__acymailing_subscriber'))
			->where($db->qn('userid') . ' = ' . $db->q($userID));

		if(!$this->ignoreSubscription)
		{
			// check verification
			$query->where($db->qn('confirmed') . ' = 1')
				->where($db->qn('enabled') . ' = 1')
				->where($db->qn('accept') . " = 1");
		}

		$db->setQuery($query);

		$subid = $db->loadResult();

		if(!$subid){
			JFactory::getApplication()->enqueueMessage('Benutzer hat kein gültiges Abo','warning');

			return false;
		}

		// fill tempalte variables
		$this->body  = str_replace($search, $replace, $this->body);

		$sendDate = time();

		$query = $db->getQuery(true);
		$query->insert($db->qn('#__acymailing_mail'))
			->columns(array('subject', 'body', 'altbody','senddate', 'type', 'language', 'visible', 'userid', 'alias', 'summary'))
			->values(implode(',', array(
				$db->q($this->subject),
				$db->q($this->body),
				$db->q(strip_tags($this->body)),
				$db->q($sendDate),
				$db->q($this->type),
				$db->q($this->email_language),
				$db->q($this->email_visible),
				$db->q($userID),
				$db->q($this->email_alias),
				$db->q($this->email_summary))));
		$db->setQuery($query);
		$db->execute();
		$this->mailid = $db->insertid();

		// final user object
		$user = new stdClass();
		$user->subid = $subid;

		// AcyMailing Statisitken für Benachrichtigungen abklären
		// $this->acymailing_replaceusertags($this, $user);

		// udate Body
		$query = $db->getQuery(true);
		$query->update($db->qn('#__acymailing_mail'))
			->set($db->qn('body') . '=' . $db->q($this->body))
			->where($db->qn('mailid') . ' = ' . $this->mailid);
		$db->setQuery($query);
		$db->execute();

		// add to MailQue
		$query = $db->getQuery(true);
		$query->insert($db->qn('#__acymailing_queue'))
			->columns(array('senddate', 'subid', 'mailid', 'priority'))
			->values(implode(',', array(
				$db->q($sendDate),
				$db->q($user->subid),
				$db->q($this->mailid),
				$db->q($this->priority))));
		$db->setQuery($query);

		if($db->execute()){
			return true;
		}

		JFactory::getApplication()->enqueueMessage('AcyMailing','error');

		return false;

	}

	/*
	 * Adapted from:
	 * administrator\components\com_acymailing\extensions\plg_acymailing_urltracker\urltracker.php
	 * Last Update 2019-02-07
	 *
	 * @since 3.9
	 *
	 * @package	AcyMailing for Joomla!
	 * @version	5.2.0
	 * @author	acyba.com
	 * @copyright	(C) 2009-2016 ACYBA S.A.R.L. All rights reserved.
	 * @license	GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
	 *
	 * @param object $email
	 * @param object $user
	 */
	private function acymailing_replaceusertags(&$email, &$user){

		$urls = array();

		// $db = JFactory::getDbo();
		// $query = $db->getQuery(true);
		// $query->select($db->qn('value'))
		// 	->from($db->qn('#__acymailing_config'))
		// 	->where($db->qn('namekey') . ' = ' . $db->q('trackingsystemexternalwebsite'));
		// $db->setQuery($query);
		// $trackingSystemExternalWebsite = $db->loadResult();
		$trackingSystemExternalWebsite = '1';

		preg_match_all('#href[ ]*=[ ]*"(?!mailto:|\#|ymsgr:|callto:|file:|ftp:|webcal:|skype:|tel:)([^"]+)"#Ui', $email->body.$email->altbody, $results);
		preg_match_all('#\( ([^)]+) \)#Ui', $email->body.$email->altbody, $results2);
		if(!empty($results2)){
			foreach($results2[1] as $i => $oneLink){
				if(strpos($oneLink, 'http') === 0 || strpos($oneLink, 'www') === 0){
					$results[0][] = $results2[0][$i];
					$results[1][] = $results2[1][$i];
				}
			}
		}

		if(empty($results)) return;

		foreach($results[1] as $i => $url){
			if(isset($urls[$results[0][$i]]) || strpos($url, 'task=out')) continue;

			$simplifiedUrl = str_replace(array('https://', 'http://'), '', $url);
			$simplifiedWebsite = str_replace(array('https://', 'http://'), '', ACYMAILING_LIVE);
			$internalUrl = (strpos($simplifiedUrl, rtrim($simplifiedWebsite, '/')) !== false) ? true : false;

			$isFile = false;
			if($internalUrl){
				$path = str_replace('/', DS, str_replace($simplifiedWebsite, '', $simplifiedUrl));
				if(!empty($path) && $path != 'index.php' && $path != 'index2.php' && @file_exists(ACYMAILING_ROOT.DS.$path)) $isFile = true;
			}

			$subfolder = false;
			if($internalUrl){
				$urlWithoutBase = str_replace($simplifiedWebsite, '', $simplifiedUrl);
				if(strpos($urlWithoutBase, '/') || strpos($urlWithoutBase, '?')){
					$folderName = substr($urlWithoutBase, 0, strpos($urlWithoutBase, '/') == false ? strpos($urlWithoutBase, '?') : strpos($urlWithoutBase, '/'));
					if(strpos($folderName, '.') === false) $subfolder = @is_dir(ACYMAILING_ROOT.$folderName);
				}
			}

			// $db = JFactory::getDbo();
			// $query = $db->getQuery(true);
			// $query->select($db->qn('value'))
			//	->from($db->qn('#__acymailing_config'))
			//	->where($db->qn('namekey') . ' = ' . $db->q('trackingsystem'));
			// $db->setQuery($query);
			// $trackingSystem = $db->loadResult();
			$trackingSystem = 'acymailing';

			if(strpos($url, 'utm_source') === false && !$isFile && strpos($trackingSystem, 'google') !== false){
				if((!$internalUrl || $subfolder) && $trackingSystemExternalWebsite != 1) continue;
				$args = array();
				$args[] = 'utm_source=newsletter_'.@$email->mailid;
				$args[] = 'utm_medium=email';
				$args[] = 'utm_campaign='.@$email->alias;
				$anchor = '';
				if(strpos($url, '#') !== false){
					$anchor = substr($url, strpos($url, '#'));
					$url = substr($url, 0, strpos($url, '#'));
				}

				if(strpos($url, '?')){
					$mytracker = $url.'&'.implode('&', $args);
				}else{
					$mytracker = $url.'?'.implode('&', $args);
				}
				$mytracker .= $anchor;
				$urls[$results[0][$i]] = str_replace($results[1][$i], $mytracker, $results[0][$i]);
				$url = $mytracker;
			}

			if(strpos($trackingSystem, 'acymailing') !== false){
				if(!$internalUrl || $isFile || strpos($url, '#') !== false || $subfolder){
					if($trackingSystemExternalWebsite != 1) continue;
					if(preg_match('#subid|passw|modify|\{|%7B#i', $url)) continue;
					$mytracker = $this->getUrl($url, $email->mailid, $user->subid);
				}else{
					if(preg_match('#=out&|/out/#i', $url)) continue;
					$extraParam = 'acm='.$user->subid.'_'.$email->mailid;
					if(strpos($url, '#')){
						$before = substr($url, 0, strpos($url, '#'));
						$after = substr($url, strpos($url, '#'));
					}else{
						$before = $url;
						$after = '';
					}
					$mytracker = $before.(strpos($before, '?') ? '&' : '?').$extraParam.$after;
				}

				if(empty($mytracker)) continue;
				$urls[$results[0][$i]] = str_replace($results[1][$i], $mytracker, $results[0][$i]);
			}
		}

		$email->body = str_replace(array_keys($urls), $urls, $email->body);
		$email->altbody = str_replace(array_keys($urls), $urls, $email->altbody);
	}

	/*
	 * Adapted from:
	 * administrator\components\com_acymailing\helpers\helper.php
	 * Last Update 2019-02-07
	 *
	 * @since 3.9
	 *
	 * @package	AcyMailing for Joomla!
	 * @version	5.2.0
	 * @author	acyba.com
	 * @copyright	(C) 2009-2016 ACYBA S.A.R.L. All rights reserved.
	 * @license	GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
	 */
	private function acymailing_frontendLink($link, $popup = false){

		define('ACYMAILING_LIVE', rtrim(str_replace('https:', 'http:', JURI::root()), '/').'/');

		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select($db->qn('value'))
			->from($db->qn('#__acymailing_config'))
			->where($db->qn('namekey') . ' = ' . $db->q('ssl_links'));
		$db->setQuery($query);
		$ssl_links = $db->loadResult();

		if(!$ssl_links){
			define('ACYMAILING_LIVE', rtrim(str_replace('https:', 'http:', JURI::root()), '/').'/');
		}else{
			define('ACYMAILING_LIVE', rtrim(str_replace('http:', 'https:', JURI::root()), '/').'/');
		}

		 $query = $db->getQuery(true);
		 $query->select($db->qn('value'))
			->from($db->qn('#__acymailing_config'))
			->where($db->qn('namekey') . ' = ' . $db->q('use_sef'));
		$db->setQuery($query);
		$use_sef = $db->loadResult();

		$app = JFactory::getApplication();
		if(!$app->isAdmin() && $use_sef){
			$link = ltrim(JRoute::_($link, false), '/');
		}

		static $mainurl = '';
		static $otherarguments = false;
		if(empty($mainurl)){
			$urls = parse_url(ACYMAILING_LIVE);
			if(isset($urls['path']) AND strlen($urls['path']) > 0){
				$mainurl = substr(ACYMAILING_LIVE, 0, strrpos(ACYMAILING_LIVE, $urls['path'])).'/';
				$otherarguments = trim(str_replace($mainurl, '', ACYMAILING_LIVE), '/');
				if(strlen($otherarguments) > 0) $otherarguments .= '/';
			}else{
				$mainurl = ACYMAILING_LIVE;
			}
		}

		if($otherarguments AND strpos($link, $otherarguments) === false){
			$link = $otherarguments.$link;
		}

		return $mainurl.$link;
	}

	/*
	 * Adapted from:
	 * administrator\components\com_acymailing\classes\url.php
	 * Last Update 2019-02-07
	 *
	 * @since 3.9
	 *
	 * @package	AcyMailing for Joomla!
	 * @version	5.2.0
	 * @author	acyba.com
	 * @copyright	(C) 2009-2016 ACYBA S.A.R.L. All rights reserved.
	 * @license	GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
	 */
	private function getUrl($url,$mailid,$subid){
		static $allurls;

		$url = str_replace('&amp;','&',$url);
		if(empty($allurls[$url])){
			$currentURL = $this->getAdd($url);
			$allurls[$url] = $currentURL;
		}else{
			$currentURL = $allurls[$url];
		}

		// $db = JFactory::getDbo();
		// $query = $db->getQuery(true);
		// $query->select($db->qn('value'))
		//	->from($db->qn('#__acymailing_config'))
		//	->where($db->qn('namekey') . ' = ' . $db->q('itemid'));
		// $db->setQuery($query);
		// $trackingSystem = $db->loadResult();
		$itemId = '0';
		$item = empty($itemId) ? '' : '&Itemid='.$itemId;

		if(empty($currentURL->urlid)) return;
		return str_replace('&amp;','&',$this->acymailing_frontendLink('index.php?subid='.$subid.'&option=com_acymailing&ctrl=url&urlid='.$currentURL->urlid.'&mailid='.$mailid.$item));

	}

	/*
	 * Adapted from:
	 * administrator\components\com_acymailing\classes\url.php
	 * Last Update 2019-02-07
	 *
	 * @since 3.9
	 *
	 * @package	AcyMailing for Joomla!
	 * @version	5.2.0
	 * @author	acyba.com
	 * @copyright	(C) 2009-2016 ACYBA S.A.R.L. All rights reserved.
	 * @license	GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
	 */
	private function get($urlid,$default = null){
		$db = JFactory::getDbo();
		$column = is_numeric($urlid) ? 'urlid' : 'url';
		$query = 'SELECT * FROM #__acymailing_url WHERE '.$column.' = '.$db->Quote($urlid).' LIMIT 1';
		$db->setQuery($query);
		return $db->loadObject();
	}

	/*
	 * Adapted from:
	 * administrator\components\com_acymailing\classes\url.php
	 * Last Update 2019-02-07
	 *
	 * @since 3.9
	 *
	 * @package	AcyMailing for Joomla!
	 * @version	5.2.0
	 * @author	acyba.com
	 * @copyright	(C) 2009-2016 ACYBA S.A.R.L. All rights reserved.
	 * @license	GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
	 */
	private function getAdd($url){
		$currentURL = $this->get($url);
		if(empty($currentURL->urlid)){
			$currentURL = new stdClass();
			$currentURL->url = $url;
			$currentURL->name = $url;
			$currentURL->urlid = $this->save($currentURL);
		}

		return $currentURL;
	}

	/*
	 * Adapted from:
	 * administrator\components\com_acymailing\classes\url.php
	 * Last Update 2019-02-07
	 *
	 * @since 3.9
	 *
	 * @package	AcyMailing for Joomla!
	 * @version	5.2.0
	 * @author	acyba.com
	 * @copyright	(C) 2009-2016 ACYBA S.A.R.L. All rights reserved.
	 * @license	GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
	 */
	private function save($element){
		$db = JFactory::getDbo();
		$pkey = $this->pkey;
		if(empty($element->$pkey)){
			$status = $db->insertObject('#__acymailing_url', $element);
		}else{
			if(count((array)$element) > 1){
				$status = $db->updateObject('#__acymailing_url', $element, $pkey);
			}else{
				$status = true;
			}
		}
		if(!$status){
			$this->errors[] = substr(strip_tags($db->getErrorMsg()), 0, 200).'...';
		}

		if($status) return empty($element->$pkey) ? $db->insertid() : $element->$pkey;
		return false;
	}

}

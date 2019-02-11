
[![DDSTATE](https://img.shields.io/badge/status-ALPHA-red.svg?style=flat)](https://img.shields.io/badge/status-ALPHA-red.svg?style=flat)

# DD_J_acyMailing_Connector
A class, build for Joomla to connect you extension to acyMailing. It allows to check subscriptions, let you push your notifications into acymailing and huge more..

![alt text](https://raw.githubusercontent.com/hr-it-solutions/DD_J_acyMailingConnector/master/demo.jpg)

[![GPL Licence](https://badges.frapsoft.com/os/gpl/gpl.png?v=102)](https://opensource.org/licenses/GPL-2.0/)

### Features
- checkListSubscription()
- setTemplate()
- addMail()

...

**Note:** It is even in ALPHA state

### Hack for multiple subscribers within one mailid
- To run multiple subscribers within one mailid, it is necessary to make a change to the AcyMailing Queu table?
ALTER TABLE  `lszqy_acymailing_queue` DROP PRIMARY KEY , ADD PRIMARY KEY (  `mailid` )

### Get it into acyMailing Core - Information about further developement
We did informed AcyMailing with very positive feedback about these solution to get it into acyMailing Core.
And we will now wait and hope for acyMailing implementation till we spend more time on that Connector.

### Using example


```php
$AcyMailingConnector = new AcyMailingConnector();
$AcyMailingConnector->ignoreSubscription = true;
// The E-Mail template you created in acyMailing, including placeholder like {title}, {name}, etc. 
// which you want to dynamically replace with the notification in the addMail() methode (see below)
$AcyMailingConnector->setTemplate(20);

for($i=0; $i<count($myNotications); $i++) {

	if(!$AcyMailingConnector->checkListSubscription([$i]['id'], 6)){

		$this->setError($myNotications[$i]['email'] . ' Not to list subscribed');

		continue;
	}

	$search = array('{title}', '{name}', '{lastname}', '{messagebody}', '{senddate}', '{link}');
	$replace = array(
		$myNotications[$i]['title'],
		$myNotications[$i]['name'],
		$myNotications[$i]['lastname'],
		$myNotications[$i]['messagebody'],
		date('d.m.Y', strtotime($myNotications[$i]['senddate'])),
		$backlink
	);
	
	if(!$AcyMailingConnector->addMail($user_data[$i]['id'], 6, $search, $replace)){
		$errors[] = $user_data[$i]['email'];
	}

}

if (!empty($errors)) {
	$this->setError('Email could not be sent to the following addresses: ' . implode(', ', $errors));
	return false;
}
```

# System requirements
AcyMailing 5.10.4 or newer                                                                  <br>
PHP 5.6.13 or newer

# DD_ Namespace
DD_ stands for **D**idl**d**u a.k.a | HR-IT-Solutions GmbH (Brand recognition)              <br>
It is a namespace prefix, provided to avoid element name conflicts.

<br>
Author: HR-IT-Solutions GmbH Florian Häusler https://www.hr-it-solution.com                 <br>
Copyright: (C) 2019 - 2019 HR-IT-Solutions GmbH >> Adapted from Joomla! plg_search_content  <br>
http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 only

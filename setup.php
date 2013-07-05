<?php

/**
 * autorespond/setup.php
 * 
 * The plugin initialization page for the SquirrelMail "autorespond" plugin,
 * which allows a user to modify an email forward file and vacation files
 * over FTP, using their IMAP credentials for authentication.
 * 
 * @copyright Copyright (c) 2002-2007 O'Shaughnessy Evans <shaug-spamrule @ wumpus.org>
 * @version $Id: setup.php,v 1.15 2007/10/23 00:29:32 shaug Exp $
 * @license http://opensource.org/licenses/artistic-license-2.0.php
 * @package plugins
 * @subpackage autorespond
 */


/**
 * squirrelmail_plugin_init_autorespond()
 * 
 * Initialize the plugin.
 */
function squirrelmail_plugin_init_autorespond()
{
    global $squirrelmail_plugin_hooks;

    $squirrelmail_plugin_hooks['optpage_register_block']['autorespond']
     = 'autorespond_options';
    $squirrelmail_plugin_hooks['menuline']['autorespond']
     = 'autorespond_menuline';
}


/**
 * autorespond_options()
 * 
 * Set up the Options page block.
 */
function autorespond_options()
{
    global $optpage_blocks;

    bindtextdomain('autorespond', SM_PATH. 'plugins/autorespond/locale');
    textdomain('autorespond');

    $optpage_blocks[] = array(
        'name' => _("Auto Response:  Reply or Forward"),
        'url'  => '../plugins/autorespond/options.php',
        'desc' => _("Automatically reply to incoming mail or send it all to ".
	            "another address."),
        'js'   => FALSE
    );

    bindtextdomain('squirrelmail', SM_PATH. 'locale');
    textdomain('squirrelmail');
}


/**
 * autorespond_menuline()
 * 
 * Add a link to the main frame's menu line.
 */
function autorespond_menuline()
{
    bindtextdomain('autorespond', SM_PATH. 'plugins/autorespond/locale');
    textdomain('autorespond');

    displayInternalLink('plugins/autorespond/options.php',
                        _("Auto Response"),
                        '');
    echo '&nbsp;&nbsp;';

    bindtextdomain('squirrelmail', SM_PATH. 'locale');
    textdomain('squirrelmail');
}


/**
 * autorespond_info()
 * 
 * @returns array with various bits of information about the plugin,
 * as documented at http://www.squirrelmail.org/docs/devel/devel-4.html.
 * 
 * Each element in the array is an info parameter.  Elements may be of any type.
 */
function autorespond_info()
{
    return array(
	'english_name' => 'Autorespond',
	'version' => '0.5.2rc4',
	'required_sm_version' => '1.4',
	'authors' => array(
	    'O\'Shaughnessy Evans' => array('email' => 'shaug-sqml@wumpus.org'),
	),
	'summary' => 'Uses FTP to maintain vacation and forward files in '.
	             'the same page.',
	'details' => 'Autorespond is Yet Another vacation and forwarding '.
	             'plugin.  It lets you modify your vacation message and '.
		     'forwarding address and toggle either one on or off, '.
		     'and it will also let you toggle whether to keep a local '.
		     'copy.  It\'s FTP-based, so your system will need to '.
		     'provide FTP access to accounts via the IMAP login names.',
	'requires_configuration' => 1,
	'requires_source_patch' => 0,
	'required_plugins' => array('compatibility' => '2.0'),
	'required_php_version' => '4.1.0',
	//'required_php_modules' => array('ftp'),
	//'required_pear_packages' => array('ssh2'),
	'other_requirements' => 'FTP, FTPS, or SSH server; ftp or ssh2 module',
    );
}


/**
 * autorespond_version()
 * 
 * @returns string identifying the plugin's version number.
 */
function autorespond_version()
{
    $info = autorespond_info();
    return $info['version'];
}

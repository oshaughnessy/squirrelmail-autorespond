<?php

/**
 * autorespond/config.php
 * 
 * User configuration page for the SquirrelMail "autorespond" plugin,
 * which allows a user to modify an email forward file and vacation files
 * over FTP, using their IMAP credentials for authentication.
 * 
 * @copyright Copyright (c) 2002-2007 O'Shaughnessy Evans <shaug-sqml @ wumpus.org>
 * @version $Id: config.php.ex,v 1.17 2007/10/22 22:31:15 shaug Exp $
 * @license http://opensource.org/licenses/artistic-license-2.0.php
 * @package plugins
 * @subpackage autorespond
 */


// make this global so that the vlogin plugin can override options
global $AUTORESPOND_OPTS;
sqsession_register($AUTORESPOND_OPTS, 'AUTORESPOND_OPTS');

/*
 * Customize the plugin here.  Follow the instructions in the comments
 * near each variable below to configure the plugin for your environment.
 * By default, the plugin will FTP to localhost, modify .forward files
 * in a user's home FTP dir, enable maildrop when filtering is requested,
 * use /usr/bin/vacation for an autoresponder, and modify .vacation.msg
 * in the user's home FTP dir.
 */

// Define the host that stores the user's forward file:
//  Each user with an IMAP account must have the same login and password here.
if (empty($AUTORESPOND_OPTS['ftphost']))
    $AUTORESPOND_OPTS['ftphost'] = 'localhost';

// Choose the FTP method:  the default is "ftp" if not defined here.
//  Options include:
//      "ftp" (plain-text FTP)
//      "ftp/tls" (FTP over TLS on port 21)
//      "ftps" (FTP over SSL on port 990)
//      "ssh" (SSH via scp and sftp on port 22)
if (empty($AUTORESPOND_OPTS['ftp_method']))
    $AUTORESPOND_OPTS['ftp_method'] = 'ftp';

// If you need to use a nonstandard port, define it here.
//  Otherwise the plugin will use a normal default as described above:
//if (empty($AUTORESPOND_OPTS['ftp_port']))
//    $AUTORESPOND_OPTS['ftp_port'] = 21;

// What is the name of the user's filter file?
//  for sendmail or postfix, it would be .forward
//  for qmail, .qmail
$AUTORESPOND_OPTS['forward_file'] = '.forward';


// Filter file commands:
//
// ... How is it directed to keep a local copy of all incoming messages?
$AUTORESPOND_OPTS['keep_string'] = '\\'. $GLOBALS['username'];
// Should the plugin enable the 'keep a copy' option by default?
$AUTORESPOND_OPTS['keep_by_default'] = TRUE;
//
// ... How is it directed to send incoming mail through a filter
//     (if there is no filter available, be sure to unset this)
$AUTORESPOND_OPTS['filter_string']  = '"|/usr/local/bin/maildrop"';
// ... Define a PCRE to match the filter_string
$AUTORESPOND_OPTS['filter_pattern'] = '/\|.*maildrop\"?/';
// ... Define a descriptive string for the filter
$AUTORESPOND_OPTS['filter_descr']   = _("the Spam Rules");
//
// ... How is it directed to create an automatic reply to new mail?
//     (The 1st %s is replaced with the results from vacation_alias,
//     defined below.)
$AUTORESPOND_OPTS['vacation_string'] = '"|/usr/bin/vacation %s '.
                                       $GLOBALS['username'] . '"';
// ... Define a format string for passing an alias address to the vacation app.
//     (The vacation_string and vacation_alias should work together to produce
//     a pipe to your vacation app.  It'll look something like this in use:
//     "|/usr/bin/vacation -a 'your_alias1' -a 'your_alias2' your_login")
$AUTORESPOND_OPTS['vacation_alias']  = ' -a %s';
// ... Define a regexp to match the vacation_string and any aliases:
//     (NOTE that this is a PCRE now instead of an EREG)
$AUTORESPOND_OPTS['vacation_pattern'] = '/\|.*vacation/';
// ... If the vacation pattern matches a scan of the forward_file, we need to
//     be able to pull the individual aliases from it with a repeated PCRE:
$AUTORESPOND_OPTS['vacation_aliases_pat'] = '/-a \"?(\S+)\"?/';
// ... Does the locale vacation program allow From headers?
$AUTORESPOND_OPTS['vacation_from'] = TRUE;


// Configure the vacation autoresponder:
//
// ... Where is the vacation message kept?
$AUTORESPOND_OPTS['vacation_file']    = '.vacation.msg';
// ... Where is the vacation's reply cache kept?
$AUTORESPOND_OPTS['vacation_cache']    = '.vacation.db';
// ... What should the default subject be?
$AUTORESPOND_OPTS['default_subject'] = '';
// ... What should the default message be?
$AUTORESPOND_OPTS['default_message'] = _("Hello.  I'm away from my mailbox right now, but I will read
your message when I return.  Thank you for your patience.

PS:  This automatic response is only sent after your first
message.  It won't be sent again in the near future, even if
you send more email before I return.

Best regards.

    -- "). $GLOBALS['username']. "\n";


/*
 * END OF BASIC CUSTOMIZATION
 * 
 * In previous versions of this plugin, it was recommended that if you want
 * to customize the messages and descriptions here, you do so by changing
 * the variables below.  The plugin has been internationalized now, so the
 * messages below represent search strings for i18n message translations.
 * If you want to customize you messages and support multiple languages,
 * you should not modify anything below, but modify your language files in
 * the plugin's locale subdir instead.  After making changes to your locale
 * files, use locale/Makefile to rebuild the binary .po files from the
 * text-based .mo source.
 * 
 * You can still disable various features by undefining their descriptions
 * here.
 */


$AUTORESPOND_OPTS['default_header'] = '<p>'.
  _("Here you can define various ways to automatically handle all your ".
    "Incoming email.  <b>Please note:</b>  If you choose <i>Forward</i> or ".
    "<i>Reply</i>, you will not keep copies of mail in your mailbox unless ".
    "you also select <i>Keep a copy here</i>.");

$AUTORESPOND_OPTS['default_footer'] = '';

$AUTORESPOND_OPTS['new_header'] = '<div align=left><p>"'.
  _("Your new forwarding rules have been saved."). '</p></div>';

$AUTORESPOND_OPTS['new_footer'] = '';

$AUTORESPOND_OPTS['vacation_header'] = '<p align=center><u>'.
  _("Vacation Message"). "</u></p>\n".
  "<p align=left>\n".
  _("Here you can change the message that is automatically sent to people ".
    "who send email to you.  The word &quot;\$SUBJECT&quot; in the ".
    "<em>Subject</em> field will be replaced with the subject of the current ".
    "message when the response is generated.  For example, if you've saved a ".
    "vacation message with the <em>Subject</em> &quot;Re: \$SUBJECT&quot;, ".
    "and you receive a message with the <em>Subject</em> &quot;I Am The ".
    "Walrus&quot;, then the automatic response sent back will say ".
    "&quot;Re: I Am The Walrus&quot;.").
  "</p>\n";
$AUTORESPOND_OPTS['vacation_footer'] = $GLOBALS['javascript_on']
 ? '<p>[ <a href="javascript:window.close()" '.
   'onClick="window.opener.location.reload()">'. _("Close"). '</a> ]</p>'
 : '';


// What messages should be shown in the options form to describe the fields?
// Undefine any of these to prevent users from setting them.

// forwarding option:
$AUTORESPOND_OPTS['forward_desc'] = _("Send all your Incoming email to ".
 "another address:");

// vacation option:
$AUTORESPOND_OPTS['vacation_desc'] = _("Return a prewritten response, ".
 "sometimes called a &quot;vacation message&quot;, to all your senders:");

// "keep a copy when vacationing or forwarding" option:
$AUTORESPOND_OPTS['keep_desc'] = _("Enable this to keep a copy of any mail ".
 "you receive.  If you've set up"). ' <a href="../spamrule/options.php">'.
 _("Spam filters"). '</a>'. _(", select &quot;filtered&quot; to send your ".
 "mail through those, otherwise select &quot;unfiltered&quot; to store mail ".
 "without filtering.");

// send all mail to the Trash option:
$AUTORESPOND_OPTS['trash_desc'] = _("This is convenient if you don't check ".
 "this mailbox often or know you'll be gone for a long time and don't want ".
 "to go over your allotted disk space.");

// empty the reply cache option:
$AUTORESPOND_OPTS['empty_cache_desc'] = _("The vacation program normally ".
 "only sends a reply to each sender once a week.  If you are changing your ".
 "message and want to ensure that previous senders get the new copy, check ".
 "this option.");

// vacation aliases option:
$AUTORESPOND_OPTS['aliases_desc'] = _("If you have any aliases forwarded to ".
 "this account, list them here so that the vacation program will be able to ".
 "reply to them correctly.  Separate multiple addresses with commas or ".
 "spaces.");

// unimplemented:
/*
 * // aliases file:  if defined, aliases for the vacation program will be
 * // read automatically from here and the user won't be presented with
 * // the aliases_desc input mentioned above.
 * //$AUTORESPOND_OPTS['aliases_file'] = '.mailfilters/recipients+';
 * 
 * //$AUTORESPOND_OPTS['aliases_func'] = get_aliases();
 * function get_aliases() {
 *     global $data_dir;
 * 
 *     sqgetGlobalVar('username', $username, SQ_SESSION);
 *     return getPref($data_dir, $username, 'email_address');
 * }
 */

?>

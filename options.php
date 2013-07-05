<?php

/**
 * autorespond/options.php
 * 
 * Options page for the SquirrelMail "autorespond" plugin,
 * which allows a user to modify an email forward file and vacation files
 * over FTP, using their IMAP credentials for authentication.
 * 
 * @copyright Copyright (c) 2002-2007 O'Shaughnessy Evans <shaug-spamrule @ wumpus.org>
 * @version $Id: options.php,v 1.13 2007/10/17 18:33:14 shaug Exp $
 * @license http://opensource.org/licenses/artistic-license-2.0.php
 * @package plugins
 * @subpackage autorespond
 */


// load init scripts for SquirrelMail 1.5 or 1.4
if (file_exists('../../include/init.php'))  {
    require('../../include/init.php');
}
else if (file_exists('../../include/validate.php'))  {
    if (!defined('SM_PATH')) {
        define('SM_PATH', '../../');
    }
    include_once(SM_PATH . 'include/validate.php');
} 
else if (file_exists('../../src/validate.php'))  {
    chdir('..');
    if (!defined('SM_PATH')) {
        define('SM_PATH', '../');
    }
    include_once(SM_PATH . 'src/validate.php');
}


global $AUTORESPOND_OPTS, $action, $color;
sqgetGlobalVar('AUTORESPOND_OPTS', $AUTORESPOND_OPTS);
sqgetGlobalVar('action', $action, SQ_FORM);


if ($action != 'editvacation') {
    displayPageHeader($color, '');

    // we're internationalized, so bind gettext functions to our domain
    bindtextdomain('autorespond', SM_PATH. 'plugins/autorespond/locale');
    textdomain('autorespond');
}
else {
    bindtextdomain('autorespond', SM_PATH. 'plugins/autorespond/locale');
    textdomain('autorespond');

    displayHtmlHeader(_("Auto Responder:  Edit Vacation"));
}

// load plugin configs and functions
load_config('autorespond', array('config.php', 'config_local.php'));
include_once(SM_PATH . 'plugins/autorespond/lib.php');

// iirc, this was here to allow a popup vacation editor but hasn't been
// completely implemented yet
if ($action === 'editvacation') {
    if ($AUTORESPOND_OPTS['vacation_header']) {
        ar_print_header(_($AUTORESPOND_OPTS['vacation_header']), 2);
    }
    ar_edit_vacation();
    if ($AUTORESPOND_OPTS['vacation_footer']) {
        ar_print_footer(_($AUTORESPOND_OPTS['vacation_footer']), 2);
    }
}
// display a header, optionally install a new vacation and forward if
// we're coming from a form submission, then display the form again with
// the new defaults, and finally display the page footer
else {
    if ($AUTORESPOND_OPTS['default_header']) {
        ar_print_header(_($AUTORESPOND_OPTS['default_header']), 3);
    }
    if ($action == _("Finish"))  {
        ar_install_autoresponse();
    }
    ar_change_autoresponse();
    if ($AUTORESPOND_OPTS['default_footer']) {
        ar_print_footer(_($AUTORESPOND_OPTS['default_footer']), 3);
    }
}

?>

</body>
</html>

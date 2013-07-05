<?php

/*
 * autorespond/config_local.php
 * 
 * If you want to override any settings in config.php, put them here.
 * This file will not be overwritten during upgrades.
 * 
 * Examples are provided below, but you can override anything that's
 * in config.php.
 */

// define your ftp or ssh server if it's not "localhost"
//$AUTORESPOND_OPTS['ftphost'] = 'ftp.'. $GLOBALS['domain'];

// define your upload/download method:  ftp, ftps, ftp/tls, or ssh
//$AUTORESPOND_OPTS['ftp_method'] = 'ftp/tls';
//$AUTORESPOND_OPTS['ftp_method'] = 'ftps';
//$AUTORESPOND_OPTS['ftp_method'] = 'ssh';

// other options you might want to change
//$AUTORESPOND_OPTS['forward_file'] = '.forward';

//$AUTORESPOND_OPTS['keep_by_default'] = TRUE;

//$AUTORESPOND_OPTS['filter_string']  = '"|/usr/local/bin/maildrop"';
//$AUTORESPOND_OPTS['filter_pattern'] = '/\|.*maildrop\"?/';
//$AUTORESPOND_OPTS['filter_descr']   = _("the Spam Rules");

//$AUTORESPOND_OPTS['vacation_from'] = TRUE;

?>

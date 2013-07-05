<?php

/**
 * autorespond/lib.php
 * 
 * The primary function library for the SquirrelMail "autorespond" plugin,
 * which allows a user to modify an email forward file and vacation files
 * over FTP, using their IMAP credentials for authentication.
 * 
 * @copyright Copyright (c) 2002-2007 O'Shaughnessy Evans <shaug-spamrule @ wumpus.org>
 * @version $Id: lib.php,v 1.27 2007/10/22 22:31:44 shaug Exp $
 * @license http://opensource.org/licenses/artistic-license-2.0.php
 * @package plugins
 * @subpackage autorespond
 */

/*
 * Table of Contents:
 * 
 * ar_print_header($mesg, $columns)
 * ar_print_footer($mesg, $columns)
 * ar_download($path, $max_bytes)
 * ar_ftp_get($path, $max_bytes, $method, $port)
 * ar_ssh_get($path, $max_bytes, $method, $port)
 * ar_upload($path, $data)
 * ar_ftp_put($path, $data, $method, $port)
 * ar_ssh_put($path, $data, $method, $port)
 * ar_syslog($message)
 * ar_change_autoresponse()
 * ar_install_autoresponse()
 * ar_read_vacation()
 * ar_edit_vacation()
 */


/**
 * ar_print_header($mesg, $columns)
 * 
 * Print out the beginning of a table, with $mesg in the first row.
 * 
 * @param string $mesg  text to print out
 * @param int $columns  how many columns will be in the table
 * 
 * Returns:
 *   nothing
 */
function ar_print_header($mesg, $columns)
{
    global $color;

    $title = _("Options") . ' - '. _("Automatic Forward and Reply");

    echo <<<EOtable_top
<br>
<table bgcolor="{$color[0]}" border=0 width=95%
       cellspacing=0 cellpadding=2 align=center>
  <tr bgcolor="{$color[0]}">
    <th>{$title}</b></th>
  </tr>
  <tr><td>
    <table bgcolor="{$color[4]}" border=0 width=100% cellspacing=0 cellpadding=5 align=center valign=top>
      <tr align=center bgcolor="{$color[4]}">
        <td colspan=$columns>
EOtable_top;

    if (isset($mesg)) {
        echo "<p>{$mesg}</p>\n";
    }

    echo "</td>\n</tr>\n\n";
}


/**
 * ar_print_footer($mesg, $columns)
 * 
 * Print out the end of a table, with $mesg in the last row.
 * 
 * @param string $mesg  text to print out
 * @param int $columns  how many columns the table has
 * 
 * Returns:
 *   nothing
 */
function ar_print_footer($mesg, $columns)
{
    global $color;

    if (isset($mesg)) {
        echo <<<EOmesg

  <tr align=center bgcolor="{$color[4]}">
    <td colspan=$columns>
      <p>{$mesg}</p>
    </td>
  </tr>
EOmesg;
    }

    echo <<<EOfooter

    </table>
  </td></tr>
</table>

EOfooter;
}


/**
 * ar_ftp_get($path, $max_bytes [, $method [, $port]])
 * 
 * Downloads the given file name from the user's account on
 * $AUTORESPOND_OPTS['ftphost'] and returns the contents as an array.
 * 
 * @param string $path  path to the file to be downloaded via FTP
 * 
 * @returns array (boolean status, string info)
 *   2-element array:
 *   On success -- the file was downloaded successfully or it didn't exist --
 *   the 1st element will be true and the 2nd will contain a string with the
 *   contents of the requested file.
 *   On failure, the 1st element will be false and the 2nd will contain
 *   an error message describing the problem.
 */
function ar_ftp_get($path, $max_bytes = 10240, $method = 'ftp', $port = 21)
{
    global $AUTORESPOND_OPTS;
    sqgetGlobalVar('AUTORESPOND_OPTS', $AUTORESPOND_OPTS, SQ_SESSION);
    sqgetGlobalVar('autorespond_uplink', $uplink_id, SQ_SESSION);

    $stat = null;

    $host = $AUTORESPOND_OPTS['ftphost'];
    if (!$host) {
        return array(false, _("Sorry, but this plugin is not completely set ".
                              "up.  Please contact your System Administrator ".
                              "about configuring the Autorespond plugin."));
    }

    if (empty($path)) {
        return array(false, _("no file was given to upload"));
    }

    // connect via plain-text or SSL FTP
    if (!$uplink_id || (ftp_systype($uplink_id) === false)) {
        global $key, $onetimepad, $username;
        sqgetGlobalVar('key', $key, SQ_COOKIE);
        sqgetGlobalVar('onetimepad', $onetimepad, SQ_SESSION);
        sqgetGlobalVar('username', $username, SQ_SESSION);

        switch ($method)  {
            case 'ftp':
                $uplink_id = ftp_connect($host, $port);
                break;
            case 'ftp/tls':
            case 'ftps':
                $uplink_id = ftp_ssl_connect($host, $port);
                break;
            default:
                return array(false, _("FTP method is not recognized").
                                    " ($method)");
        }

        if (!$uplink_id) {
            return array(false, _("cannot connect to"). " $host".
                                ($php_errormsg ? ": $php_errormsg" : ''));
        }

        // decrypt the user's password so we can pass it to the ftp site
        $password = OneTimePadDecrypt($key, $onetimepad);

        $stat = ftp_login($uplink_id, $username, $password);
        if (!$stat) {
            $php_errormsg = "($uplink_id)";
            return array(false, _("cannot log in to"). " $host".
                                ($php_errormsg ? ": $php_errormsg" : ''));
        }

        sqsession_register($uplink_id, 'autorespond_uplink');
    }


    // need to make sure our remote file's parent dir exists just so that
    // we can return a suitable error message when it doesn't
    if (! @ftp_chdir($uplink_id, dirname($path))) {
        return array(false, _("cannot change directory").
                            ($php_errormsg ? ": $php_errormsg" : ''));
    }

    // if our remote file exists, then we download it into a temp file
    // and store the results into $data, one line per array element
    $file = basename($path);
    if (@ftp_size($uplink_id, $file) !== -1) {
        // create a local temp file to store the rules
        $temp = tmpfile();
        $stat = @ftp_fget($uplink_id, $temp, $file, FTP_ASCII);
        if ($stat === false) {
            //sqsession_unregister('autorespond_uplink');
            return array(false, _("cannot read"). " $file: $php_errormsg");
        }
        //@ftp_quit($uplink_id);

        // put each line of the temp file into an array
        rewind($temp);
        $data = array();
        while (fstat($temp) && !feof($temp)) {
            $data[] = trim(fgets($temp, 1024));
        }
        fclose($temp);
    }

    return array(true, $data);
}


/**
 * ar_ssh_get($path, $max_bytes [, $method [, $port]])
 * 
 * Downloads the given file name from the user's account on
 * $AUTORESPOND_OPTS['ftphost'], via scp or sftp, and returns the contents
 * as an array.
 * 
 * @param string $path  path to the file to be downloaded via SSH
 * 
 * @returns array (boolean status, string info)
 *   2-element array:
 *   On success -- the file was downloaded successfully or it didn't exist --
 *   the 1st element will be true and the 2nd will contain a string with the
 *   contents of the requested file.
 *   On failure, the 1st element will be false and the 2nd will contain
 *   an error message describing the problem.
 */
function ar_ssh_get($path, $max_bytes = 10240, $method = 'scp', $port = 22)
{
    global $AUTORESPOND_OPTS;
    sqgetGlobalVar('AUTORESPOND_OPTS', $AUTORESPOND_OPTS, SQ_SESSION);
    sqgetGlobalVar('autorespond_uplink', $uplink_id, SQ_SESSION);

    $return_data = array();
    $sftp = null;
    $stat = null;
    $stream = false;
    $stream_data = '';
    $url = '';

    $host = $AUTORESPOND_OPTS['ftphost'];
    if (!$host) {
        return array(false, _("Sorry, but this plugin is not completely set ".
                              "up.  Please contact your System Administrator ".
                              "about configuring the Autorespond plugin."));
    }

    if (empty($path)) {
        return array(false, _("no file was given to upload"));
    }


    if (!$uplink_id || !($sftp = ssh2_sftp($uplink_id))) {
        global $key, $onetimepad, $username;
        sqgetGlobalVar('key', $key, SQ_COOKIE);
        sqgetGlobalVar('onetimepad', $onetimepad, SQ_SESSION);
        sqgetGlobalVar('username', $username, SQ_SESSION);

        print "new ssh session for $path<br>\n";

        // start our ssh session by initializing the connection
        $uplink_id = ssh2_connect($host, $port);
        if ($uplink_id === false) {
            return array(false, _("cannot connect to").
                                " $host:$port".
                                ($php_errormsg ? ": $php_errormsg" : ''));
        }

        // decrypt the user's password so we can pass it to the ftp site
        $password = OneTimePadDecrypt($key, $onetimepad);

        // now try to authenticate
        @$stat = ssh2_auth_password($uplink_id, $username, $password);
        if ($stat === false) {
            return array(false, _("cannot log in to"). " $host".
                                ($php_errormsg ? ": $php_errormsg" : ''));
        }

        // we need the sftp subsystem to stat the file, whichjlets us
        // know whether the file even exists
        $sftp = ssh2_sftp($uplink_id);
        if (!$sftp) {
            return array(false, _("cannot connect to sftp subsystem on ").
                                " $host:$port".
                                ($php_errormsg ? ": $php_errormsg" : ''));
        }

        sqsession_register($uplink_id, 'autorespond_uplink');
    }

    // if the remote file doesn't exist, we just return true
    // and an empty data array
    if (ssh2_sftp_stat($sftp, $path) === false) {
        return array(true, array());
    }

    // create a local temp file to store the rules
    $temp = tempnam(sys_get_temp_dir(), "autorespond.");
    //print "$path: temp is $temp<br>\n";
    @$stat = ssh2_scp_recv($uplink_id, $path, $temp);
    if ($stat === false) {
        unlink($temp);
        return array(false, _("cannot scp"). ' '. basename($path).
                            ' '. _("into"). ' '. $temp.
                            ($php_errormsg ? ": $php_errormsg" : ''));
    }

    // download the requested file using file_get_contents, which
    // should be the most efficient, but doesn't exist until PHP 4.3.0
    //$url = "ssh2.sftp://$sftp/".urlencode($path);
    //$stream_data = file_get_contents($url, null, null, 0, $max_bytes);
    /*
    if ($stream_data === false) {
        $php_errormsg = "($url)";
        return array(false, _("cannot download"). ' '. basename($path).
                            ($php_errormsg ? ": $php_errormsg" : ''));
    }
    */

    // put each line of the temp file into an array
    $stream = @fopen($temp, 'r');
    if ($stream === false) {
        unlink($temp);
        return array(false, _("cannot open"). ' '. basename($temp).
                            ($php_errormsg ? ": $php_errormsg" : ''));
    }
    rewind($stream);
    while (fstat($stream) && !feof($stream)) {
        $return_data[] = trim(fgets($stream, 1024));
    }
    unlink($temp);
    fclose($stream);
    return array(true, $return_data);

    /*
    // read the file contents using stream functions
    $stream = @fopen($url, 'r');
    if ($stream === false) {
        return array(false, _("cannot download"). ' '. basename($path).
                            ($php_errormsg ? ": $php_errormsg" : ''));
    }

    // read the results of the command
    stream_set_blocking($stream, true);
    if ($timeout > 0) {
        stream_set_timeout($stream, $timeout);
    }

    $stream_data = stream_get_contents($stream, $max_bytes);
    $stream_info = stream_get_meta_data($stream);
    if ($stream_info['timed_out']) {
        return array(false, _("timeout downloading"). ' '. basename($path).
                            ($php_errormsg ? ": $php_errormsg" : ''));
    }
    fclose($stream);
    */

    /*
    $file = basename($path);
    if (! @ftp_size($ftp, $file)) {
        return array(false, _("cannot read"). " $file: $php_errormsg");
    }
    */

    // run through the returned data line by line and store it into
    // the return_data array.
    /*
    $raw = strtok($stream_data, "\n");
    while ($raw !== false) {
        //print "downloaded line: <pre>$raw</pre><br>\n";
        //$raw = trim($raw);      // remove any leading or trailing whitespace
        $return_data[] = $raw;  // store the line
        $raw = strtok("\n");    // step forward to the next line
    }
    return array(true, $return_data);
    */

    // split the return data into individual lines and pass it back as an array
    // note that while strtok is better at working with larger data
    // and explode is probably much more efficient, both strip out empty
    // lines.  we have to use something that will keep the data intact.
    //return array(true, preg_split('/\n/', $stream_data));
}


/**
 * ar_download($path, $max_bytes)
 * 
 * Wrapper around ar_ftp_get() and ar_ssh_get() that calls the appropriate
 * backend based on $AUTORESPOND_OPTS['ftp_method'].
 */
function ar_download($path, $max_bytes)
{
    global $AUTORESPOND_OPTS;
    sqgetGlobalVar('AUTORESPOND_OPTS', $AUTORESPOND_OPTS, SQ_SESSION);
    $method = 'ftp';        // default connection method (ftp, ftps, or sftp)
    $port = null;           // default port (depends on method)

    // override our default ftp_method with the value from $AUTORESPOND_OPTS
    // if the user defined it.
    if (array_key_exists('ftp_method', $AUTORESPOND_OPTS) &&
        $AUTORESPOND_OPTS['ftp_method'] != null) {
        $method = strtolower($AUTORESPOND_OPTS['ftp_method']);
    }

    if (array_key_exists('ftp_port', $AUTORESPOND_OPTS) &&
        $AUTORESPOND_OPTS['ftp_port'] != null) {
        $port = $AUTORESPOND_OPTS['ftp_port'];
    }

    // call the right backend method and return whatever it returns
    switch ($method)  {
        case 'ftp':
            return ar_ftp_get($path, $max_bytes, 'ftp', $port ? $port : 21);
            break;
        case 'ftp/tls':
            return ar_ftp_get($path, $max_bytes, 'ftps', $port ? $port : 21);
            break;
        case 'ftps':
            return ar_ftp_get($path, $max_bytes, 'ftps', $port ? $port : 990);
            break;
        case 'ssh':
            return ar_ssh_get($path, $max_bytes, 'ssh', $port ? $port : 22);
            break;
        case 'scp':
            return ar_ssh_get($path, $max_bytes, 'scp', $port ? $port : 22);
            break;
        case 'sftp':
            return ar_ssh_get($path, $max_bytes, 'sftp', $port ? $port : 22);
            break;
        default:
            return array(false, _("FTP method is not recognized").
                                " ($method)");
    }
}


/**
 * ar_ftp_put($path, $data [, $method [, $port]])
 * 
 * FTP method for ar_upload().
 * 
 * @param string $path  the file to be modified
 * @param string $data  the data to be written to $path; if it's an empty string, $path will be removed
 * @param string $method  ftp connection method ("ftp" or "ftps"; default "ftp")
 * @param int    $port    ftp connection port (default 21)
 * 
 * @returns array (boolean $status, string $info)
 *   a 2-part array, with the first part indicating success (true) or failure
 *    (false) and the second part containing an error message in the case of
 *    failure
 */
function ar_ftp_put($path, $data, $method = 'ftp', $port = 21)
{
    global $AUTORESPOND_OPTS;
    sqgetGlobalVar('AUTORESPOND_OPTS', $AUTORESPOND_OPTS, SQ_SESSION);
    sqgetGlobalVar('autorespond_uplink', $uplink_id, SQ_SESSION);

    $stat = false;

    if (!$path) {
        return array(false, _("no file was given to upload"));
    }

    $host = $AUTORESPOND_OPTS['ftphost'];
    if (!$host) {
        return array(false, _("Sorry, but this plugin is not completely set ".
                              "up.  Please contact your System Administrator ".
                              "about configuring the Autorespond plugin."));
    }

    // connect to our ftp server
    if (!$uplink_id || (ftp_systype($uplink_id) === false)) {
        global $key, $onetimepad, $username;
        sqgetGlobalVar('key', $key, SQ_COOKIE);
        sqgetGlobalVar('onetimepad', $onetimepad, SQ_SESSION);
        sqgetGlobalVar('username', $username, SQ_SESSION);

        switch ($method)  {
            case 'ftp':
                $uplink_id = ftp_connect($host, $port);
                break;
            case 'ftp/tls':
            case 'ftps':
                $uplink_id = ftp_ssl_connect($host, $port);
                break;
            default:
                return array(false, _("FTP method is not recognized").
                                    " ($method)");
        }

        if (!$uplink_id) {
            return array(false, _("cannot connect to"). " $method://$host".
                                ($php_errormsg ? ": $php_errormsg" : ''));
        }

        // decrypt the user's password so we can pass it to the ftp site
        // (borrowed from the vacation plugin; thanks!)
        $password = OneTimePadDecrypt($key, $onetimepad);

        $stat = ftp_login($uplink_id, $username, $password);
        if (!$stat) {
            return array(false, _("cannot log in to"). " $method://$host".
                                ($php_errormsg ? ": $php_errormsg" : ''));
        }

        sqsession_register($uplink_id, 'autorespond_uplink');
    }

    // if there is data to upload, write it to a temporary file and upload
    // its contents
    if ($data) {
        // create a file w/the new rules
        $temp = tmpfile();
        fwrite($temp, $data);
        rewind($temp);

        // search the path to the file and create each parent dir if it
        // doesn't exist
        $dir = dirname($path);
        $dirs = preg_split('/[\\/\\\]+/', $dir, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($dirs as $d) {
            if ($d == '.') {
                continue;
            }
            if ($d == '/') {
                continue;
            }
            if (! @ftp_chdir($uplink_id, $d)) {
                if (!(@ftp_mkdir($uplink_id, $d)
                     && @ftp_site($uplink_id, "chmod 0700 $d")
                     && @ftp_chdir($uplink_id, $d))) {
                    @ftp_close($uplink_id);
                    return array(false, _("error creating"). " $dir".
                                 ($php_errormsg ? ": $php_errormsg" : ''));
                }
            }
        }

        $cwd = ftp_pwd($uplink_id);
        /*
        if ($cwd != "/" and $cwd != $dir and $cwd != "/$dir") {
            return array(false, "error creating $dir (could only get to ".
                            "$cwd): $php_errormsg");
        }
        */

        $file = basename($path);
        $stat = ftp_fput($uplink_id, $file, $temp, FTP_ASCII);
        if (!$stat) {
            @ftp_close($uplink_id);
            return array(false, _("error changing"). " $cwd/$file".
                                ($php_errormsg ? ": $php_errormsg" : ''));
        }
        @ftp_site($uplink_id, "chmod 0600 $file");
        fclose($temp);
    }
    // otherwise delete the remote file (we don't want to leave an empty file)
    else {
        $stat = @ftp_delete($uplink_id, $path);
        if (!$stat) {
            @ftp_close($uplink_id);
            return array(false, _("error removing"). " $path".
                                ($php_errormsg ? ": $php_errormsg" : ''));
        }
    }

    //@ftp_quit($uplink_id);

    return array(true, '');
}


/**
 * ar_ssh_put($path, $data [, $method [, $port]])
 * 
 * SSH method for ar_upload(), using scp.
 * 
 * @param string $path  the file to be modified
 * @param string $data  the data to be written to $path; if it's an empty string, $path will be removed
 * @param string $method  ssh connection method ("scp" or "sftp"; default "scp")
 * @param int    $port    ssh connection port (default 22)
 * 
 * @returns array (boolean $status, string $info)
 *   a 2-part array, with the first part indicating success (true) or failure
 *    (false) and the second part containing an error message in the case of
 *    failure
 */
function ar_ssh_put($path, $data, $method = 'scp', $port = 22)
{
    global $AUTORESPOND_OPTS;
    sqgetGlobalVar('AUTORESPOND_OPTS', $AUTORESPOND_OPTS, SQ_SESSION);
    sqgetGlobalVar('autorespond_uplink', $uplink_id, SQ_SESSION);

    $stat = false;

    if (!$path) {
        return array(false, _("no file was given to upload"));
    }

    $host = $AUTORESPOND_OPTS['ftphost'];
    if (!$host) {
        return array(false, _("Sorry, but this plugin is not completely set ".
                              "up.  Please contact your System Administrator ".
                              "about configuring the Autorespond plugin."));
    }

    if (!$uplink_id || !($sftp = ssh2_sftp($uplink_id))) {
        global $key, $onetimepad, $username;
        sqgetGlobalVar('key', $key, SQ_COOKIE);
        sqgetGlobalVar('onetimepad', $onetimepad, SQ_SESSION);
        sqgetGlobalVar('username', $username, SQ_SESSION);

        $uplink_id = ssh2_connect($host, $port);
        if ($uplink_id === false) {
            return array(false, _("cannot connect to").
                                " $method://$host:$port".
                                ($php_errormsg ? ": $php_errormsg" : ''));
        }

        // decrypt the user's password so we can log into the ssh server
        $password = OneTimePadDecrypt($key, $onetimepad);

        @$stat = ssh2_auth_password($uplink_id, $username, $password);
        if ($stat === false) {
            return array(false, _("cannot log in to"). " $host".
                                ($php_errormsg ? ": $php_errormsg" : ''));
        }

        // we need the sftp subsystem to stat the file, whichjlets us
        // know whether the file even exists
        $sftp = ssh2_sftp($uplink_id);
        if (!$sftp) {
            return array(false, _("cannot connect to sftp subsystem on ").
                                " $host:$port".
                                ($php_errormsg ? ": $php_errormsg" : ''));
        }

        sqsession_register($uplink_id, 'autorespond_uplink');
    }

    // connect to our "ftp" server
    switch ($method)  {
        case 'ssh':
        case 'scp':
        case 'sftp':
            break;
        default:
            return array(false, _("FTP method is not recognized").
                                " ($method)");
    }

    // if there is data to upload, write it to a temporary file and upload
    // its contents
    if ($data) {
        // make sure that the parent dirs for our path exist.
        // this can only be done through sftp
        $dir = dirname($path);
        if ($dir !== '.' && $dir !== '/' && $dir !== '\\') {
            if (!$stat = @ssh2_sftp_stat($sftp, $d)) {
                if (!@ssh2_sftp_mkdir($sftp, $d, 0700, true)) {
                    return array(false, _("error creating"). " $dir".
                                 ($php_errormsg ? ": $php_errormsg" : ''));
                }
            }
        }

        switch ($method) {
            case 'ssh':
            case 'scp':
                // create a file w/the new rules
                $temp = tempnam(sys_get_temp_dir(), "autorespond.");
                $fh = fopen($temp, 'w');
                fwrite($fh, $data);
                fclose($fh);

                // upload the temp file
                $stat = ssh2_scp_send($uplink_id, $temp, $path, 0600);
                unlink($temp);
                break;
            case 'sftp':
                // open the file as an sftp URL and upload it directly
                $url = "ssh2.sftp//$sftp/". urlencode($path);
                $stream = fopen($url, 'w');
                if (!$stream) {
                    return array(false, _("cannot connect to").
                                 " $method://$host:$port".
                                 ($php_errormsg ? ": $php_errormsg" : ''));
                }
                fwrite($stream, $data);
                fclose($stream);
                break;
        }
        if (!$stat) {
            return array(false, _("error changing"). " $path".
                                ($php_errormsg ? ": $php_errormsg" : ''));
        }
    }
    // otherwise delete the remote file (we don't want to leave an empty file)
    else {
        $sftp = ssh2_sftp($uplink_id);
        if ($sftp && ($stat = @ssh2_sftp_stat($sftp, $path))) {
            if (!@ssh2_sftp_unlink($sftp, $path)) {
                return array(false, _("error removing"). " $path".
                             ($php_errormsg ? ": $php_errormsg" : ''));
            }
        }
    }

    return array(true, '');
}


/**
 * ar_upload($path, $data)
 * 
 * Uploads the text in $data to $path in the user's account on 
 * $AUTORESPOND_OPTS['ftphost'].  Calls an appropriate backend function
 * according to the value of $AUTORESPOND_OPTS['ftp_method'].
 * 
 * @param string $path  the file to be modified
 * @param string $data  the data to be written to $path; if it's an empty string, $path will be removed
 * 
 * @returns array (boolean $status, string $info)
 *   a 2-part array, with the first part indicating success (true) or failure
 *   (false) and the second part containing an error message in the case of
 *   failure
 */
function ar_upload($path, $data)
{
    global $AUTORESPOND_OPTS;
    sqgetGlobalVar('AUTORESPOND_OPTS', $AUTORESPOND_OPTS, SQ_SESSION);
    $method = 'ftp';        // default connection method (ftp, ftps, or sftp)
    $port = null;           // default port (depends on method)

    // override our default ftp_method with the value from $AUTORESPOND_OPTS
    // if the user defined it.
    if (array_key_exists('ftp_method', $AUTORESPOND_OPTS) &&
        $AUTORESPOND_OPTS['ftp_method'] != null) {
        $method = strtolower($AUTORESPOND_OPTS['ftp_method']);
    }

    if (array_key_exists('ftp_port', $AUTORESPOND_OPTS) &&
        $AUTORESPOND_OPTS['ftp_port'] != null) {
        $port = $AUTORESPOND_OPTS['ftp_port'];
    }

    // call the right backend method and return whatever it returns
    switch ($method)  {
        case 'ftp':
            return ar_ftp_put($path, $data, 'ftp', $port ? $port : 21);
            break;
        case 'ftp/tls':
            return ar_ftp_put($path, $data, 'ftps', $port ? $port : 21);
            break;
        case 'ftps':
            return ar_ftp_put($path, $data, 'ftps', $port ? $port : 990);
            break;
        case 'ssh':
            return ar_ssh_put($path, $data, 'ssh', $port ? $port : 22);
            break;
        case 'scp':
            return ar_ssh_put($path, $data, 'scp', $port ? $port : 22);
            break;
        case 'sftp':
            return ar_ssh_put($path, $data, 'sftp', $port ? $port : 22);
            break;
        default:
            return array(false, _("FTP method is not recognized").
                                " ($method)");
    }
}


/**
 * ar_syslog($message)
 * 
 * Write a message to the syslog.
 * 
 * @param string $message  text to send
 * 
 * Returns:
 *   nothing
 */
function ar_syslog($message)
{
    define_syslog_variables();
    openlog('forward', LOG_NDELAY, LOG_DAEMON);
    syslog(LOG_ERR, $message);
    closelog();
}


/**
 * ar_change_autoresponse()
 * 
 * Prints out a form allowing the user to change various options for
 * processing new mail (typically the things you might want to do in a
 * .forward file).  These include:
 *   - forwarding to another address
 *   - enabling and modifying a vacation message
 *   - emptying your vacation message's reply cache
 *   - keeping mail locally, either filtered or unfiltered
 * 
 * The user's forward_file and vacation_file are loaded before the form is
 * printed out.  Both are processed to set defaults for the form.  An effort
 * is made to make as few assumptions as possible about the forward file so
 * that external editing of it won't confuse this plugin, but any changes
 * outside the scope of the features that this plugin can make will most
 * likely still be lost.
 * 
 * Parameters:
 *   none
 * 
 * Returns:
 *   nothing
 */
function ar_change_autoresponse()
{
    global $AUTORESPOND_OPTS, $color, $data_dir, $trash_folder;
    sqgetGlobalVar('AUTORESPOND_OPTS', $AUTORESPOND_OPTS, SQ_SESSION);
    sqgetGlobalVar('trash_folder', $trash_folder, SQ_FORM);
    sqgetGlobalVar('username', $username, SQ_SESSION);

    $addrs = array();
    $aliases = '';
    $check_fwd = '';
    $check_keep = '';
    $check_trash = '';
    $check_vacation = '';
    $keep_nofilt = '';
    $keep_filt = '';

    // download and process the installed forward file
    list($stat, $forward) = ar_download($AUTORESPOND_OPTS['forward_file']);
    if ($stat === false) {
        print _("There was a problem connecting to your FTP server:").
              '&nbsp;&nbsp;'. ($forward ? "\"$forward\".&nbsp;&nbsp;" : '').
              _("Please contact your support department."). "<br>\n";
    }
    else if (! empty($forward)) {
        foreach ($forward as $line) {
            $line = trim($line);
            // skip if the line is empty
            if (!$line) {
                continue;
            }

            // if it looks like a disabled forward that we created
            // earlier, remember it as the default forward address
            $matches = array();
            if (@ereg('^# forward: *([^ ].*)', $line, $matches)) {
                array_push($addrs, $matches[1]);
                continue;
            }
            // skip other comments
            else if (@ereg('^#', $line)) {
                continue;
            }

            // split it by commas since sendmail allows those
            // then run through each component
            // (blindly hoping there's nothing like this:  abc, "this, that")
            $splitline = preg_split('/\s*,\s*/', $line);
            foreach ($splitline as $f) {
                // if the forward entry is a path,
                // either it's being put into the Trash
                // or we will presume that means a local copy is being kept
                $matches = array();
                // does the line look like a path?
                if (@preg_match('/^[\.\/\\\\]\S+/', $f, $matches)) {
                    // now see if the end matches our trash_folder
                    if (strpos(strrev($matches[0]), strrev($trash_folder))
                        === 0) {
                        $check_trash = 'checked';
                    }
                    else {
                        $keep_nofilt = $check_keep = 'checked';
                        $keep_filt = '';
                    }
                }
                // if it matches our vacation regex, turn on
                // the reply section's checked flag for the form,
                // then look for aliases in the vacation command
                else if ((isset($AUTORESPOND_OPTS['vacation_pattern']) &&
                 @preg_match($AUTORESPOND_OPTS['vacation_pattern'], $f)) ||
                 (isset($AUTORESPOND_OPTS['vacation_pcre']) &&
                 @preg_match($AUTORESPOND_OPTS['vacation_pcre'], $f))) {
                    $vac_aliases = array();
                    $check_vacation = 'checked';
                    if (@preg_match_all($AUTORESPOND_OPTS['vacation_aliases_pat'],
                                        $f, $vac_aliases)) {
                        $aliases = implode(', ', $vac_aliases[1]);
                    }
                }
                // if it matches our filter command pattern, turn on the
                // keep checkbox and the filter option
                else if (@preg_match($AUTORESPOND_OPTS['filter_pattern'], $f)) {
                    $keep_filt = $check_keep = 'checked';
                    $keep_nofilt = '';
                }
                // ignore other pipes
                else if (@ereg('^\"?\|', $f)) {
                    continue;
                }
                // finally, if the line matches anything else, we presume
                // it's an email address for forwarding
                else {
                    $check_fwd = 'checked';
                    array_push($addrs, $f);
                }
            }
        }
    }

    if (empty($addrs)) {
        $def_fwd = getPref($data_dir, $username, 'autorespond_forward');
    }
    else {
        $def_fwd = implode(', ', $addrs);
    }

    // If a forwarding address wasn't defined in the .forward and
    // no "keep a copy" option was defined there either, we may
    // want to enable 'keep a copy' to simplify life for vacation
    // users that don't realize they won't have any new mail without
    // enabling this.  See 'keep_by_default' in options.php to change
    // the default.
    if (!$check_fwd && !$keep_nofilt && !$keep_filt && 
        $AUTORESPOND_OPTS['keep_by_default'] === true) {
        $keep_nofilt = $check_keep = 'checked';
        $keep_filt = '';
    }

    // If no vacation aliases were pulled from the forward_file, we should
    // load a default set from the squirrelmail preferences
    if (!$aliases) {
        global $data_dir;
        sqgetGlobalVar('username', $username, SQ_SESSION);
        $aliases = getPref($data_dir, $username, 'autorespond_aliases');
    }

    list($subject, $message) = ar_read_vacation();
    if (!$subject) {
        $subject = $AUTORESPOND_OPTS['default_subject'];
    }
    if (!$message) {
        $message = $AUTORESPOND_OPTS['default_message'];
    }

    echo <<<EOform_top
<tr bgcolor="{$color[12]}">
  <td colspan=3><form action="options.php" method=GET>
EOform_top;

    print '<b>'. _("You can choose one or more of these options:"). "</br>\n";

    echo <<<EOform_top
  </td>
</tr>

EOform_top;

    // show the text input for the forward address
    $desc = $AUTORESPOND_OPTS['forward_desc'];
    if ($desc) {
        $to_label = _("To:");
        $check_label = _("Forward?");
        echo <<<EOforward
    <tr align=left valign=top bgcolor="{$color[4]}">
      <td><input type=checkbox name=forward $check_fwd></input></td>
      <td><b>$check_label</b></td>
      <td>$desc<br><br>
        <i>$to_label</i> &nbsp;&nbsp;
        <input type=text name=addr size=40 value="$def_fwd"></input>
      </td>
    </tr>

EOforward;
    }

    // show the vacation subject and message inputs
    $desc = $AUTORESPOND_OPTS['vacation_desc'];
    if ($desc) {
        $subj_label = _("Subject:");
        $mesg_label = _("Message:");
        $check_label = _("Reply?");
        echo <<<EOvacation
    <tr align=left valign=top bgcolor="$color[12]">
      <td><input type=checkbox name=vacation $check_vacation></input></td>
      <td><b>$check_label</b></td>
      <td>$desc<br><br>
        <i>$subj_label</i> &nbsp;&nbsp;
        <input type=text name=subject size=60 value="$subject"></input><br>
        <br><i>$mesg_label</i><br>
        <textarea name=message rows=10 cols=65>$message</textarea>
        <br><br>
EOvacation;

        // show the aliases checkbox and input box
        $desc = $AUTORESPOND_OPTS['aliases_desc'];
        if ($desc) {
            $opt_title = _("Aliases:");
            echo <<<EOaliases
        <i>$opt_title</i> &nbsp;&nbsp;
        <input type=text name=aliases size=60 value="$aliases"></input>
        <br><small><i>$desc</i></small>
        <br><br>
EOaliases;
        }

        // show the reset-cache checkbox
        $desc = $AUTORESPOND_OPTS['empty_cache_desc'];
        if ($desc) {
            $opt_title = _("Empty reply cache?");
            echo <<<EOcache
        <input type=checkbox name=reset_cache></input>
        <i>$opt_title</i><br>
        <small><i>$desc</i></small>
EOcache;
        }


        echo <<<EOvacation
        <br>
      </td>
    </tr>
EOvacation;
    }

    // show the "keep a local copy" checkboxes:
    // In $AUTORESPOND_OPTS, we check whether keep_desc is set.  This will
    // enable the whole form section that lets a user enable storing their
    // mail locallly, either via the keep_string method (typically "\username"
    // in a .forward file) or the filter_string method (typically a pipe to
    // procmail or maildrop).  If filter_string is defined, we show radio
    // boxes allowing the user to choose whether to filter or not; if it's not
    // defined, we don't show the radio boxes and assume the keep_string is
    // defined to allow the user to enable local retention w/o filtering.
    $desc = $AUTORESPOND_OPTS['keep_desc'];
    if ($desc) {
        $unfilt_label = _("unfiltered");
        $filt_label = _("filtered");
        $check_label = _("Keep a copy here?");
        $keep_text = isset($AUTORESPOND_OPTS['filter_string'])
         ? '<input type=radio name=keeptype value=unfiltered '.$keep_nofilt.'>'.
           "<i>$unfilt_label</i>&nbsp;&nbsp;\n".
           '<input type=radio name=keeptype value=filtered '. $keep_filt. '>'.
           "<i>$filt_label</i>\n"
         : "&nbsp;\n";
        echo <<<EOkeep
    <tr align="left" valign="top" bgcolor="{$color[4]}">
      <td><input type="checkbox" name="keep" $check_keep></td>
      <td><b>$check_label</b></td>
      <td>$desc<br><br>
        $keep_text
      </td>
    </tr>

EOkeep;
    }

    // show the "send it to the Trash" option
    $desc = $AUTORESPOND_OPTS['trash_desc'];
    if ($desc) {
        $check_label = _("Sent it to your Trash?");
        echo <<<EOkeep
<tr align="left" valign="top" bgcolor="{$color[12]}">
  <td><input type="checkbox" name="trash" $check_trash></td>
  <td><b>$check_label</b></td>
  <td>$desc
  </td>
</tr>

EOkeep;
    }

    // close out the form
    $finish_label = _("Finish");
    $reset_label = _("Reset");
    echo <<<EOend
    <tr align="left">
      <td align="left" colspan="3"> 
        <br>
        <input type="submit" name="action" value="$finish_label"> &nbsp;
        <input type="reset" value="$reset_label"> &nbsp;
        </form>
      </td>
    </tr>
EOend;
}


/**
 * ar_install_autoresponse()
 * 
 * Reads the data submitted by the autoresponse options form and
 * installs it all in the user's forward and vacation files via FTP,
 * printing status messages along the way:
 *   - possibly adds a forwarding address
 *   - possibly installs a vacation message, with or without aliases
 *   - possibly empties out the existing vacation reply cache
 *   - possibly keeps mail stored locally, piped through a filter
 *     or just sent directly to the inbox
 *   - possibly sends all mail to the trash
 * 
 * Parameters:
 *   none
 * 
 * Returns:
 *   nothing
 */
function ar_install_autoresponse()
{
    global $AUTORESPOND_OPTS, $color, $addr, $aliases, $data_dir, $forward,
           $keep, $reset_cache, $trash, $trash_folder, $vacation;
    sqgetGlobalVar('AUTORESPOND_OPTS', $AUTORESPOND_OPTS, SQ_SESSION);
    sqgetGlobalVar('addr', $addr, SQ_FORM);
    sqgetGlobalVar('aliases', $aliases, SQ_FORM);
    sqgetGlobalVar('forward', $forward, SQ_FORM);
    sqgetGlobalVar('keep', $keep, SQ_FORM);
    sqgetGlobalVar('reset_cache', $reset_cache, SQ_FORM);
    sqgetGlobalVar('trash', $trash, SQ_FORM);
    sqgetGlobalVar('trash_folder', $trash_folder);
    sqgetGlobalVar('username', $username, SQ_SESSION);
    sqgetGlobalVar('vacation', $vacation, SQ_FORM);
    $data = '';
    $alias_opts = '';

    if ($addr) {
        if ($forward) {
            $addr = trim($addr);
            $data = "$addr\n";
            $mesg = _("New mail will be sent to"). ' '.
                    '<a href="mailto:'. htmlspecialchars($addr).
                    '"><i>'. htmlspecialchars($addr). '</i></a>.';
        }

        setPref($data_dir, $username, 'autorespond_forward', $addr);
    }
    else {
        removePref($data_dir, $username, 'autorespond_forward');
    }
    if (!$forward) {
        $mesg = _("New mail will be kept here in your Inbox.");
    }

    // build up a list of vacation command options for each alias provided
    // by the form, then create the vacation command string from this
    // and the predefined command string
    //
    // note that we want to process the aliases whether or not a vacation
    // is being saved so that we can remember the old aliases when the
    // vacation message is being disabled.  it will save the user from
    // having to put them in every time they reactivate their vacation reply.
    if ($aliases) {
        $aliases_pref = array();
        $alias_list = preg_split('/[\s,]+/', $aliases);

        if (sizeof($alias_list) > 0) {
            foreach ($alias_list as $alias_addr) {
                if (preg_match('/^\S+$/', $alias_addr)) {
                    $mesg .= "<br>\n... ". htmlspecialchars($alias_addr). ' '.
                             _("is recognized as an alias.");
                    $alias_opts .=
                               sprintf($AUTORESPOND_OPTS['vacation_alias'],
                               $alias_addr);
                    array_push($aliases_pref, $alias_addr);
                }
            }
        }

        if (empty($aliases_pref)) {
            removePref($data_dir, $username, 'autorespond_aliases');
        }
        else {
            setPref($data_dir, $username, 'autorespond_aliases',
                    implode(', ', $aliases_pref));
        }
    }
    else {
        removePref($data_dir, $username, 'autorespond_aliases');
    }

    // if the vacation feature was enabled, recreate the vacation_file
    // and upload it, then append the vacation_string to the forward data
    if ($vacation) {
        sqgetGlobalVar('message', $message, SQ_FORM);
        sqgetGlobalVar('subject', $subject, SQ_FORM);
        $vacdata = '';

        // build up the new vacation message file in $vacdata:
        // from and subject headers, then a newline and the message body.

        // include a From header in the vacation message derived from
        // the address the user has defined in squirrelmail's prefs.
        // this can be disabled by changing the vacation_from config var
        // to false.  this is necessary for platforms where vacation
        // doesn't support a From header (e.g. Solaris 8 & before)
        if ($AUTORESPOND_OPTS['vacation_from'] !== false) {
            $name = getPref($data_dir, $username, 'full_name');
            $email = getPref($data_dir, $username, 'email_address');
            if (!empty($name) && !empty($email)) {
                $vacdata .= "From: $name <$email>\n";
            }
        }

        $vacdata .= "Subject: ". $subject. "\n\n". $message;

        // convert <return><newline> to <newline> in the vacation file
        // because the form may pass it to us with the extra returns
        $vacdata = str_replace("\r\n", "\n", $vacdata);

        // ensure the new vacation file ends with a newline
        if (! @ereg("\n$", $vacdata)) {
            $vacdata .= "\n";
        }

        list($stat, $ftpmesg) = ar_upload($AUTORESPOND_OPTS['vacation_file'],
         $vacdata);
        if ($stat === false) {
            print _("There was a problem uploading your vacation file:").
                  '&nbsp;&nbsp;'. ($ftpmesg ? "\"$ftpmesg\".&nbsp;&nbsp;" : '').
                  _("Please contact your support department.").
                  "<br>\n";
        }
        $data .= sprintf($AUTORESPOND_OPTS['vacation_string'], $alias_opts).
                 "\n";

        $mesg .= "<br>\n". _("Senders will get an automatic reply.");
    }
    else {
        $mesg .= "<br>\n". _("No automatic reply will be sent.");
    }

    // empty out the user's vacation reply cache if requsted
    if ($reset_cache) {
        $mesg .= "<br>\n". _("Your reply cache has been reset.");
        // use FTP to remove the vacation_cache file from the user's account
        ar_upload($AUTORESPOND_OPTS['vacation_cache'], '');
    }

    // if the user requested to keep local mail, build up a filter_string
    // or keep_string and add it to the forward file data
    if ($keep) {
        sqgetGlobalVar('keeptype', $keeptype, SQ_FORM);
        if ($keeptype === 'filtered') {
            $data = $AUTORESPOND_OPTS['filter_string']. "\n" . $data;
            if ($addr) {
                $mesg .= "<br>\n". _("Local copies will also be kept ".
                         "(and will be filtered through"). ' '.
                         $AUTORESPOND_OPTS['filter_descr']. ").";
            }
            else {
                $mesg .= "<br>\n..." . _("filtered through"). ' '.
                         $AUTORESPOND_OPTS['filter_descr']. '.';
            }
        }
        else {
            $data = $AUTORESPOND_OPTS['keep_string']. "\n" . $data;
            if ($addr) {
                $mesg .= "<br>\n". _("Local copies will also be kept.");
            }
        }
    }

    // add a directive to send all mail to the trash folder if requested
    if ($trash) {
        $trash = @ereg('^[\.\/\\]', $trash_folder) ? $trash_folder
                 : "./$trash_folder";
        $mesg .= "<br>\n". _("Mail will be kept in your Trash mailbox.");
        $data .= $trash. "\n";
    }

    // upload the new forward file with all the commands that we just
    // put together
    list($stat, $ftpmesg) = ar_upload($AUTORESPOND_OPTS['forward_file'], $data);
    if ($stat === false) {
        print _("There was a problem uploading your forward file:").
              '&nbsp;&nbsp;'. ($ftpmesg ? "\"$ftpmesg\".&nbsp;&nbsp;" : '').
              _("Please contact your support department."). "<br>\n";
    }

    // tell the user what features were just activated
    echo <<<EOmesg

    <tr align=left valign=top bgcolor="$color[12]">
      <td>&nbsp;</td>
      <td><b>
EOmesg;
    print _("New settings saved:"). "</b></td>\n";
    echo <<<EOmesg
      <td><font color=green>$mesg</font></td>
    </tr>
    <tr align=left>
      <td colspan=3>&nbsp;</td>
    </tr>
EOmesg;
}


/**
 * ar_read_vacation()
 * 
 * Load the user's vacation message via FTP.
 * 
 * @returns array:  (string subject, string message body (individual lines joined together by newlines))
 */
function ar_read_vacation()
{
    global $AUTORESPOND_OPTS;
    sqgetGlobalVar('AUTORESPOND_OPTS', $AUTORESPOND_OPTS, SQ_SESSION);
    $subject = '';
    $message = '';

    // download and process the installed vacation message
    list($stat, $vacation) = ar_download($AUTORESPOND_OPTS['vacation_file']);
    if ($stat === false) {
        print _("There was a problem connecting to your FTP server:").
              '&nbsp;&nbsp;'. ($vacation ? "\"$vacation\".&nbsp;&nbsp;" : '').
              _("Please contact your support department."). "<br>\n";
    }
    else if ($stat === true && !empty($vacation)) {
        // Look for headers at the beginning of the file and strip them out.
        // If we find the subject, store it to return to the calling function.
        // The rest of the file is the message body, returned as a string
        // of joined lines.
        $hdr = array();

        while (preg_match('/^(\S+):\s+(.*)/', $vacation[0], $hdr)) {
            if (!strcasecmp($hdr[1], 'Subject')) {
                $subject = $hdr[2];
            }
            array_shift($vacation);
            reset($vacation);
        }

        // if we stripped headers, the vacation may still have a leading
        // newline or return/newline that needs to be stripped
        if (@ereg("^\r?\n?$", $vacation[0])) {
            array_shift($vacation);

        }
        $message = implode("\n", $vacation);
    }

    return array($subject, $message);
}


/**
 * ar_edit_vacation()
 * 
 * Function unused at present, but designed to allow a popup or otherwise
 * standalone window to edit a vacation file.  Provides a form with inputs
 * for changing the subject and email message body of the vacation message.
 * 
 * Parameters:
 *   none
 * 
 * Returns:
 *   nothing
 */
function ar_edit_vacation()
{
    global $AUTORESPOND_OPTS, $color, $message, $subject;
    sqgetGlobalVar('message', $message, SQ_FORM);
    sqgetGlobalVar('subject', $subject, SQ_FORM);

    // if a message and subject weren't defined already (can this ever happen?),
    // we need to read them from the user's vacation file
    //
    // if they were set, then we need to build up a new vacation file message
    // and upload it to the account's home via ftp
    if (!$subject && !$message) {
        list($subject, $message) = ar_read_vacation();
    }
    else {
        if (! @ereg("\n$", $message)) {
            $message .= "\n";
        }
        $data = "Subject: $subject\n\n". $message;
        $data = str_replace("\r\n", "\n", $data);
        list($stat, $ftpmesg) = ar_upload($AUTORESPOND_OPTS['vacation_file'],
         $data);
        if ($stat === false) {
            print _("There was a problem uploading your vacation file:").
                  '&nbsp;&nbsp;"'. $ftpmesg. "\".&nbsp;&nbsp;";
                  _("Please contact your support department.").
                  "<br>\n";
        }
    }

    // set vacation subject and message defaults based on predefined values
    // if they weren't passed through a form or loaded from the vacation file
    if (!$subject) {
        $subject = $AUTORESPOND_OPTS['default_subject'];
    }
    if (!$message) {
        $message = $AUTORESPOND_OPTS['default_message'];
    }

    $subj_label = _("Subject:");
    $mesg_label = _("Message:");
    echo <<<EOform
  <form action="options.php?action=editvacation" method=POST
   onSubmit="window.refresh()">
  <tr valign=top bgcolor="$color[4]">
    <td>$subj_label</td>
    <td><input type=text name=subject size=40 value="$subject"></input></td>
  </tr>
  <tr valign=top bgcolor="$color[4]">
    <td>$mesg_label</td>
    <td><textarea name=message rows=12 cols=75>$message</textarea></td>
  </tr>

  <tr>
    <td colspan=3> 
      <br><input type=submit value="Update"> &nbsp; <input type=reset>
    </td>
  </tr>
  </form>

EOform;
}


// define sys_get_temp_dir() if the PHP installation doesn't already have it
// (from comments at http://us.php.net/manual/en/function.sys-get-temp-dir.php)
if (! function_exists('sys_get_temp_dir'))
{
    // Based on http://www.phpit.net/
    // article/creating-zip-tar-archives-dynamically-php/2/
    function sys_get_temp_dir()
    {
        // Try to get the temp directory from environment variables
        if (!empty($_ENV['TMP'])) {
            return realpath($_ENV['TMP']);
        }
        else if (!empty($_ENV['TMPDIR'])) {
            return realpath($_ENV['TMPDIR']);
        }
        else if (!empty($_ENV['TEMP'])) {
            return realpath($_ENV['TEMP']);
        }
        // otherwise detect it by creating a temporary file
        else {
            // Try to use system's temporary directory
            // as random name shouldn't exist
            $temp_file = tempnam(md5(uniqid(rand(), true)), '');
            if ($temp_file) {
                $temp_dir = realpath(dirname($temp_file));
                unlink($temp_file);
                return $temp_dir;
            }
            else {
                return FALSE;
            }
        }
    }
}

?>

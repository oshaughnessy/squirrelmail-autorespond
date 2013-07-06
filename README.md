Auto Respond is a plugin for SquirrelMail that lets you set up a forward
file or vacation message via FTP.  If you have some kind of mail filter
available through your forward files, it will also let you turn that on
or off.

See also:  http://www.squirrelmail.org/plugin_view.php?id=172.


## Requirements

- an FTP server that can authenticate your users using the same
  credentials as your IMAP server
- ftp support in PHP (see http://www.php.net/manual/en/ref.ftp.php)
- a forward file somewhere under the user's FTP directory
- version 2.x of the compatibility plugin


## Features

- creates a new block under Options
- toggle keeping a message in the local mailbox, either through a piped
  filter or unfiltered
- toggle forwarding all email, remembering the last address used to forward
- toggle use of a vacation program and editing of the response message
- support for alternate email addresses (aliases) in the vacation app
- fairly simple (?) customization of the messages a user sees in the
  various option pages
- capable of using locale translations, but available translations are
  *very* limited.  Please send your translations to me and I'll include
  them in the next release!  I have a Japanese translation, but I'm
  mystefied as to why it doesn't work.  If any Japanese users can offer
  suggestions, I'd love to hear from you.


## Installation

1. Install this directory into your squirrelmail source dir at
   plugins/autorespond.

1. Ensure that you have an FTP server which allows your users to log in
   using the same username and password they use to login to Squirrelmail.

1. Copy config.php.ex to config.php and modify the file as described within.
   You may also want to leave this file intact and simply put your own
   variable settings into config_local.php instead, which will override
   anything defined in config.php.

1. Enable the plugin through conf.pl.

1. Test it out!  Make sure to double-check the contents of your forward file
   and vacation files after making changes through SquirrelMail.

1. It has been brought to my attention that some vacation programs need
   to be initialized before they can be used.  This isn't exactly something
   that lends itself well to our web environment, but it's something you
   may need to be aware of.  Check your own vacation docs and test a bit
   to learn whether you need to handle this.  I don't have a good solution
   for this that can operate through our limited FTP-only environment,
   and I strongly prefer to avoid making the web server run scripts on
   the user's behalf.  If you need this, perhaps you could run a nightly
   or hourly cron job to initialize vacation dbs for users.  Feel free to
   contact me if you're in this situation and need a hand.


## Upgrading

If you've used autorespond in the past, you can now override config.php's
settings in config_local.php.  Any variables defined in config.php
can be put there.  Since it's loaded after config.php, your settings
will override the defaults.

Release 0.5 added internationalization and included major updates to
the strings in config.php.  I strongly recommend that you don't just
keep your old pre-0.5 config, but rather back it up, copy config.php.ex
to config.php, then add your customizations to config_local.php.


## Questions and support

I've tested this plugin quite a bit and it has worked well on my systems
since 2002.  It has also worked well for many other people.  I have tried
to make it resistant to unexpected problems and easy to change for
different environments, but I'm sure there are shortcomings.  If you have
any questions, comments, or feature requests, please contact me through
shaug-sqml @ wumpus.org.


## Wishlist

See the TODO file.


## License

This code is licensed under the Perl Artistic License, version 2.0.  For
more information, please see the file Artistic_2.0, which was included with
this distribution, or http://opensource.org/licenses/artistic-license-2.0.php

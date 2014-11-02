# About

FeedOnFeeds is a lightweight server-based RSS aggregator and reader,
allowing you to keep up with syndicated content (blogs, comics, and so
forth) without having to keep track of what you've read. Being
server-based means all of your feeds and history are kept in one
place, and being lightweight means you can install it pretty much
anywhere without needing a fancy dedicated server or the like.

FeedOnFeeds was originally written by Steve Minutillo; this fork
strives to stay up-to-date with changes to PHP while improving
security, multiuser support, and overall design.

#Requirements

FeedOnFeeds requires:

* A web server running PHP
* Access to a PDO-capable database (MySQL and SQLite are currently supported,
  and more are easy to add)
* Specific features may require specific PHP extensions; it is highly
  recommended (but not required) that you have Xlib, cURL, and iconv.

#Installation

To install, simply download a snapshot or clone from your favorite git
repository.  Then copy fof-config.php.dist to fof-config.php and edit
it as appropriate for your setup. If you're on shared hosting, be sure
to point `FOF_DATA_PATH` to somewhere in your home directory.

After that, point a web browser to install.php; For example, if you've
installed it at http://example.com/fof, go to
http://example.com/fof/install.php and then everything should be set
up automatically.

# FAQ/troubleshooting

#### Which database backend should I use?

Most of the time, SQLite is what you want. You should only consider MySQL if
you're going to support many concurrent users (i.e. 10 or more people using the
app simultaneously), as SQLite has much lower administrative overhead and makes
it easier to port your data to a new webhost. This is the case even if you
already havea working MySQL installation, and using SQLite will not interfere
with your existing MySQL in any way.

#### I'm having an error message at installation:

##### `couldn't open logfile /path/to/fof-data/fof-install.log`

You need to set the value of `FOF_DATA_PATH` in fof-config.php

##### I'm getting `Syntax error or access violation` after "Cannot upgrade table: `[CREATE TRIGGER ...`

This occurs if you're on MySQL shared hosting and you don't have access to
trigger creation, which is fairly common.  Find the following in
`fof-config.php`:

    // define('SQL_NO_TRIGGERS', 1);

and remove the `//` at the beginning of the line.

#### How do I delete a feed?

In the feeds list there's a little triangle (that looks like &Delta;). Hover
over it and enjoy the context menu.

If you've set the UI theme to "simple," just click on the "d" in the feeds
list.

# Upgrading

Upgrading to a newer FeedOnFeeds usually just involves downloading a
new snapshot or issuing a git pull, and then pointing a browser at install.php
again.

# Setting up automatic updates

FeedOnFeeds works best if you have it set to automatically update your feeds
on-the-fly. The best way to do this is to set up a cron job like
so:

     * * * * * curl http://example.com/fof/update-quiet.php

Don't worry about updates occurring too frequently - FeedOnFeeds will only
update feeds which are due for an update. By default it will
update every feed at most once an hour, but if you enable dynamic
update intervals in the admin preferences, it will adjust the polling
update for feeds based on their historical update frequencies.

If you're on Dreamhost, you can set up your cron job from the "goodies" section
of the panel, and create a new cron job with the following:

* Command to run: `curl http://path/to/fof/update-quiet.php` (setting the URL
  appropriately)
* When to run: Custom
* Minutes: Every 10 minutes
* Hours: Every Hour
* Day of month: Every Day
* Month: Every Month
* Day of Week: Every Day of Week



# Legal

FeedOnFeeds is distributed under the GPL.


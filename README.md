# About

FeedOnFeeds is a lightweight server-based **RSS aggregator** and reader,
allowing you to keep up with syndicated content (blogs, comics, and so
forth) without having to keep track of what you've read. Being
server-based means all of your feeds and history are kept in one
place, and being lightweight means you can install it pretty much
anywhere without needing a fancy dedicated server or the like.

FeedOnFeeds was originally written by Steve Minutillo,
this fork strives to stay up-to-date with changes to PHP while improving
security, usability, multiuser support, and overall design.

For a complete list of contributors see the end of this file.

## Features

* subscribe to RSS feeds, reddits or YouTube channels
* automatic updating for new entries
* tagging of items or feeds
* blacklist for unwanted item content
* automatic item purging based on similarity (e.g. for getting rid of reposts)
* different sidebar layouts
* multi user capable
* public compilations to share tagged content with others
* search stored feed entries
* Support for [WebSub](https://en.wikipedia.org/wiki/WebSub) push notifications

### YouTube integration

You can also use FeedOnFeeds for keeping track of your favourite YouTube
channels without having to register with Google. Just activate the integrated
**YouTube plugin** and add subscriptions to the channels you want to follow.

### Reddit integration

While reddit doesn't advertise its RSS feeds you can still subscribe to reddits
or users. An integrated **Reddit plugin** also tries to optimise the tiny
thumbnail images served by the feed by replacing them with their original
images if possible.

# Installation

## Requirements

FeedOnFeeds requires:

* A web server running *PHP* 7.x or higher (8.x recommended)
* Access to a PDO-capable database (*MySQL* and *SQLite* are currently supported,
  and more are easy to add)
* Specific features may require specific PHP extensions; it is highly
  recommended (but not required) that you have *Xlib*, *cURL*, and *iconv*.
* [Composer](https://getcomposer.org/)

To install:

1. Download a snapshot or clone from your favorite git repository
2. Run `composer install` to install/update the library dependencies
3. Copy `fof-config.php.dist` to `fof-config.php` and edit it as appropriate for your setup
  * If you're on shared hosting, be sure to point `FOF_DATA_PATH` to somewhere in your home directory.
4. Point a web browser to `install.php`

  For example, if you've installed FeedOnFeeds at `http://example.com/fof`, go to
  `http://example.com/fof/install.php` and then everything should be set
  up automatically.

### Which database backend should I use?

Most of the time, SQLite is what you want. You should only consider MySQL if
you're going to support many concurrent users (i.e. 10 or more people using the
app simultaneously), as SQLite has much lower administrative overhead and makes
it easier to port your data to a new webhost. This is the case even if you
already have a working MySQL installation, and using SQLite will not interfere
with your existing MySQL in any way.

## Upgrading

Upgrading to a newer FeedOnFeeds usually just involves downloading a
new snapshot or issuing a `git pull`, and then pointing a browser at `install.php`
again.

## Setting up automatic updates

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

# FAQ/troubleshooting

## I'm having an error message during installation

### couldn't open logfile /path/to/fof-data/fof-install.log

You need to set the value of `FOF_DATA_PATH` in `fof-config.php`. Also the file
has to be writable for the web server process. You can set it to `chmod 0777`, or
if you have root access you can do something like `chown -R www-data /path/to/fof-data/`
with `www-data` being the user under which the web server process is running. Depending on
your configuration it may also be `httpd` or something else entirely. Check your server
config for the correct user name.

### Syntax error or access violation after "Cannot upgrade table: [CREATE TRIGGER ...

This occurs if you're on MySQL shared hosting and you don't have access to
trigger creation, which is fairly common. Find the following in `fof-config.php`:

    // define('SQL_NO_TRIGGERS', 1);

and remove the `//` at the beginning of the line.

## How do I delete a feed?

In the feeds list there's a little menu symbol (≡). Hover
over it and enjoy the context menu. If you've set the UI theme to "simple," just
click on the `d` in the feed list.

**Note:** By unsubscribing from a feed you also delete all its tagged and starred
items.

## WebSub isn't working

Are you running it on a private network? WebSub can only work if your instance is visible to the Internet at large, and needs to be reachable using the hostname that you're connecting via.

If you're using the nginx webserver, you need to make sure that URL paths are passed correctly; for example, this should be how you enable PHP script support in your nginx configuration file:

```nginx
        location ~ \.php(/|$) {
              include snippets/fastcgi-php.conf;

              # With php-fpm (or other unix sockets):
              fastcgi_pass unix:/var/run/php/php-fpm.sock;
       }
```

as opposed to the common, but incorrect, location of `\.php$`.

# Legal

FeedOnFeeds is distributed under the GPL.

## Contributors

*ordered by date of first contribution:*

* [Steve Minutillo](http://feedonfeeds.com/ "original FeedOnFeeds homepage")
* [Alexander Schulze](https://github.com/RomanSixty)
* mdt (discontinued github account)
* [fluffy](https://github.com/fluffy-critter)
* [Justin Wind](https://github.com/thylacine)
* [Ian Tearle](https://github.com/iantearle)
* [_darch](https://github.com/sorenpeter)

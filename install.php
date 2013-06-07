<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * install.php - creates tables and cache directory, if they don't exist
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

define('VERSION_REQUIRED_PHP', '5.0.0');
define('VERSION_REQUIRED_CURL', '7.10.5');

$install_early_warn = NULL;
try {
    require_once('fof-install.php');
} catch (Exception $e) {
    $install_early_warn .= "<div class='trouble'>Trouble encountered: <span class='warn'><pre>" . $e->GetMessage() . "</pre></span>  Trying to continue...</div>\n";
}

fof_set_content_type();

?>
<!DOCTYPE html>
<html>
    <head>
        <title>Feed on Feeds - Installation</title>
        <link rel="stylesheet" href="fof.css" media="screen" />
        <script src="fof.js" type="text/javascript"></script>
        <meta name="ROBOTS" content="NOINDEX, NOFOLLOW" />
        <style>
            body {
                font-family: georgia;
                font-size: 16px;
            }
            div {
                background: #eee;
                border: 1px solid black;
                width: 75%;
                margin: 5em auto;
                padding: 1.5em;
            }
            hr {
                height:0;
                border:0;
                border-top:1px solid #999;
            }
            .fail { color: red; }
            .pass { color: green; }
            .warn { color: #a60; }
        </style>
    </head>

    <body>
<?php
    if (!empty($install_early_warn)) {
        echo $install_early_warn;
    }
?>
        <div>
            <center style="font-size: 20px;">
                <h1><a href="http://feedonfeeds.com/">Feed on Feeds</a> - Installation</h1>
            </center>
            <br>


<?php
if (isset($_POST['password']) && isset($_POST['password2'])) {
    if ($_POST['password'] == $_POST['password2']) {
        fof_db_add_user_all(1, 'admin', $_POST['password'], 'admin');
        fof_log("admin user created");
        echo '<center><b>OK!  Setup complete! <a href=".">Login as admin</a>, and start subscribing!</center></b>';
        echo '</div></body></html>';
        exit();
    } else {
        echo "<center><span class='fail'>Passwords do not match!</span></center><br><br>";
    }
}
else
{
    fof_log("install started");
?>
<h2>Checking compatibility...</h2>
<?php

    $compat_fatal = 0;

    $php_ok = (function_exists('version_compare') && version_compare(phpversion(), VERSION_REQUIRED_PHP, '>='));
    $compat_fatal |= fof_install_compat_notice($php_ok, "PHP", "Your PHP version is too old!", "Feed on Feeds requires at least PHP version " . VERSION_REQUIRED_PHP, 1);

    $compat_fatal |= fof_install_compat_notice(extension_loaded('xml'), "XML", "Your PHP installation is missing the XML extension!", "This is required by Feed on Feeds.", 1);
    $compat_fatal |= fof_install_compat_notice(extension_loaded('pcre'), "PCRE", "Your PHP installation is missing the PCRE extension!", "This is required by Feed on Feeds.", 1);
    $compat_fatal |= fof_install_compat_notice(extension_loaded('pdo'), "PDO", "Your PHP installation is missing the PDO extension!", "This is required by Feed on Feeds.", 1);

    $mysql_ok = extension_loaded('pdo_mysql');
    $sqlite_ok = extension_loaded('pdo_sqlite');
    $compat_fatal |= fof_install_compat_notice($sqlite_ok, "SQLite", "Your PHP installation does not support the SQLite database.", "This is not required if another database is available.");
    $compat_fatal |= fof_install_compat_notice($mysql_ok, "MySQL", "Your PHP installation does not support the MySQL database.", "This is not required if another database is available.");
    $compat_fatal |= fof_install_compat_notice($sqlite_ok || $mysql_ok, "PDO database", "Your PHP installation is missing a supported PDO database extension!", "This is required by Feed on Feeds.", 1);

    $curl_ok = (extension_loaded('curl') && version_compare(get_curl_version(), VERSION_REQUIRED_CURL, '>='));
    $compat_fatal |= fof_install_compat_notice($curl_ok, "cURL", "Your PHP installation is either missing the cURL extension, or it is too old!", "cURL version " . VERSION_REQUIRED_CURL . " or later is required to be able to subscribe to https or digest authenticated feeds.");

    $compat_fatal |= fof_install_compat_notice(extension_loaded('zlib'), "zlib", "Your PHP installation is missing the zlib extension!", "Feed on Feeds will not be able to save bandwidth by requesting compressed feeds.");
    $compat_fatal |= fof_install_compat_notice(extension_loaded('iconv'), "iconv", "Your PHP installation is missing the iconv extension!", "The number of international languages that Feed on Feeds can handle will be reduced.");
    $compat_fatal |= fof_install_compat_notice(extension_loaded('mbstring'), "mbstring", "Your PHP installation is missing the mbstring extension!", "The number of international languages that Feed on Feeds can handle will be reduced.");

    if ($compat_fatal) {
        echo "</div></body></html>";
        exit();
    }

?>
<br>Minimum requirements met!
<hr>

<h2>Creating tables...</h2>
<?php
    fof_install_database(fof_install_schema(), 1);
?>
<br>Tables exist.
<hr>

<h2>Updating tables...</h2>
<?php
    fof_install_database_update_old_tables();
?>
<br>Tables up to date.
<hr>

<h2>Inserting initial data...</h2>
<?php
    fof_install_database_populate();
?>
<br>Data initialized.
<hr>

<h2>Checking cache directory...</h2>
<?php
    if ( ! fof_install_cachedir() ) {
        echo "</div></body></html>\n";
        exit();
    }
?>
<br>Cache directory ready.
<hr>

<?php
}
?>

<h2>Checking admin user...</h2>
<?php
if ( ! fof_install_user_level_exists('admin')) {
?>

You now need to choose an initial password for the 'admin' account:<br>

<form method="POST">
<table>
<tr><td>Password:</td><td><input type=password name=password></td></tr>
<tr><td>Password again:</td><td><input type=password name=password2></td></tr>
</table>
<input type=submit value="Set Password">
</form>

<?php
} else {
?>

<br>'admin' account already exists.
<br><b><center>OK!  Setup complete! <a href=".">Login as admin</a>, and start subscribing!</center></b>
<?php
}
?>

</div></body></html>

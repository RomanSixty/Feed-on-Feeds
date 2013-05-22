<?php
/*
    Installation and update routines.

 */

/* skip authentication if true, user_id will be 1 */
$fof_no_login = true;

/* installer is running if true, skip auth, no user_id will be set, no prefs loaded, no plugins initialized */
$fof_installer = true;

/* Pull in the fof core, config will be parsed and database will be connected. */
require_once('fof-main.php');

/* parses version string out of assorted curl extension responses */
/* from SimplePie */
function get_curl_version() {
    $cv = curl_version();
    if (is_array($cv)) {
        return $cv['version'];
    }
    if (preg_match('/curl\/(\S+)(\s|$)/', $curl, $match)) {
        return $match[1];
    }
    return 0;
}

/* renders pretty message about a compatibility check */
function fof_install_compat_notice($is_ok, $what, $fail_msg, $fail_extra, $is_required=0) {
    $requirement_failed = 0;
    if ($is_ok) {
        echo "<br><span class='pass'>$what OK</span>\n";
        return 0;
    }
    if ($is_required) {
        echo "<br><span class='fail'>$fail_msg</span>  <span>$fail_extra</span>\n";
        return 1;
    }
    echo "<br><span class='warn'>$fail_msg</span>  <span>$fail_extra</span>\n";
}


/* ensure the cache dir is present and usable */
function fof_install_cachedir() {
    $cachedir = defined('FOF_CACHE_DIR') ? FOF_CACHE_DIR : "cache";
    $cachedir_full_html = '<code>' . htmlspecialchars(implode(DIRECTORY_SEPARATOR, array(getcwd(), $cachedir))) . '</code>';

    if ( ! file_exists($cachedir) ) {
        $status = @mkdir($cachedir, 0755);
        if ( ! $status ) {
            echo "<span class='fail'>Can't create cache directory $cachedir_full_html.";
            echo "<br>Create it before continuing.";
            echo "</span>\n";
            return false;
        }
    }

    if ( ! is_writable($cachedir)) {
        echo "<span class='fail'>Cache directory $cachedir_full_html exists, but is not writable.";
        echo "<br>Ensure it is writable before continuing.";
        echo "</span>\n";
        return false;
    }

    echo "<span class='pass'>Cache directory $cachedir_full_html exists and is writable.</span>\n";

    return true;
}


/*
   SQLite and MySQL speak slightly different CREATE dialects, mostly concerning
   primary keys and indices, so schema generation has been rendered into a
   rudimentary templated format, to avoid duplicating each statement for each
   driver.
   This was thrown together off-the-cuff -- there is assuredly a far more
   mature solution to this problem in existence somewhere..
 */

if ( ! defined('MYSQL_ENGINE')) define('MYSQL_ENGINE', 'MyISAM');
/* generates a query string to create a table, given an array of columns */
function fof_install_create_table_query($table_name, $column_array) {
    $query = "CREATE TABLE IF NOT EXISTS $table_name (\n  ";
    $query .= implode(",\n  ", $column_array);
    $query .= "\n)";
    if (defined('USE_MYSQL')) {
        $query .= "ENGINE=" . MYSQL_ENGINE . " DEFAULT CHARSET=UTF8";
    }

    return $query . ';';
}

/* generates a query string to create an index, given a table, an index, and an array defining the index */
function fof_install_create_index_query($table_name, $index_name, $index_def) {
    /* unpack the index definition */
    list($idx_type, $idx_val) = $index_def;

    if (defined('USE_MYSQL')) {
        /* mysql defines indexes in table creation */
    } else if (defined('USE_SQLITE')) {
        $query = "CREATE $idx_type IF NOT EXISTS $index_name ON $table_name ( $idx_val );";
        return $query;
    }

    return NULL;
}

/* define all tables as arrays */
/* most of the fiddly driver-specific quirks are finagled in here */
function fof_install_schema() {
    $tables = array();
    $indices = array();

    /* different drivers prefer different types */
    if (defined('USE_MYSQL')) {
        $driver_int_type = 'INT(11)';
    } else if (defined('USE_SQLITE')) {
        $driver_int_type = 'INTEGER';
    }

    /* FOF_FEED_TABLE *
    /* columns */
    if (defined('USE_MYSQL')) {
        $tables[FOF_FEED_TABLE][] = "feed_id $driver_int_type NOT NULL AUTO_INCREMENT";
    } else if (defined('USE_SQLITE')) {
        $tables[FOF_FEED_TABLE][] = "feed_id $driver_int_type PRIMARY KEY AUTOINCREMENT NOT NULL";
    }
    $tables[FOF_FEED_TABLE][] = "feed_url TEXT NOT NULL";
    $tables[FOF_FEED_TABLE][] = "feed_title TEXT NOT NULL";
    $tables[FOF_FEED_TABLE][] = "feed_link TEXT NOT NULL";
    $tables[FOF_FEED_TABLE][] = "feed_description TEXT NOT NULL";
    $tables[FOF_FEED_TABLE][] = "feed_image TEXT";
    $tables[FOF_FEED_TABLE][] = "alt_image TEXT";
    $tables[FOF_FEED_TABLE][] = "feed_image_cache_date $driver_int_type DEFAULT '0'";
    $tables[FOF_FEED_TABLE][] = "feed_cache_date $driver_int_type DEFAULT '0'";
    $tables[FOF_FEED_TABLE][] = "feed_cache_attempt_date $driver_int_type DEFAULT '0'";
    $tables[FOF_FEED_TABLE][] = "feed_cache_next_attempt $driver_int_type DEFAULT '0'";
    $tables[FOF_FEED_TABLE][] = "feed_cache TEXT";
    if (defined('USE_MYSQL')) {
        $tables[FOF_FEED_TABLE][] = "PRIMARY KEY ( feed_id )";
        $tables[FOF_FEED_TABLE][] = "KEY feed_cache_next_attempt ( feed_cache_next_attempt )";
    }

    /* indices */
    if (defined('USE_SQLITE')) {
        $indices[FOF_FEED_TABLE]['feed_cache_next_attempt_idx'] = array('INDEX', 'feed_cache_next_attempt');
    }


    /* FOF_ITEM_TABLE */
    /* columns */
    if (defined('USE_MYSQL')) {
        $tables[FOF_ITEM_TABLE][] = "item_id $driver_int_type NOT NULL AUTO_INCREMENT";
    } else if (defined('USE_SQLITE')) {
        $tables[FOF_ITEM_TABLE][] = "item_id $driver_int_type PRIMARY KEY AUTOINCREMENT NOT NULL";
    }
    $tables[FOF_ITEM_TABLE][] = "feed_id $driver_int_type NOT NULL DEFAULT '0' REFERENCES " . FOF_FEED_TABLE . " ( feed_id ) ON UPDATE CASCADE ON DELETE CASCADE";
    $tables[FOF_ITEM_TABLE][] = "item_guid TEXT NOT NULL";
    $tables[FOF_ITEM_TABLE][] = "item_link TEXT NOT NULL";
    $tables[FOF_ITEM_TABLE][] = "item_cached $driver_int_type NOT NULL DEFAULT '0'";
    $tables[FOF_ITEM_TABLE][] = "item_published $driver_int_type NOT NULL DEFAULT '0'";
    $tables[FOF_ITEM_TABLE][] = "item_updated $driver_int_type NOT NULL DEFAULT '0'";
    $tables[FOF_ITEM_TABLE][] = "item_title TEXT NOT NULL";
    $tables[FOF_ITEM_TABLE][] = "item_content TEXT NOT NULL";
    if (defined('USE_MYSQL')) {
        $tables[FOF_ITEM_TABLE][] = "PRIMARY KEY (item_id)";
        $tables[FOF_ITEM_TABLE][] = "KEY feed_id (feed_id)";
        $tables[FOF_ITEM_TABLE][] = "KEY item_guid ( item_guid(255) )";
        $tables[FOF_ITEM_TABLE][] = "KEY feed_id_item_cached ( feed_id, item_cached )";
        $tables[FOF_ITEM_TABLE][] = "KEY feed_id_item_updated ( feed_id, item_updated )";
    }

    /* indices */
    if (defined('USE_SQLITE')) {
        $indices[FOF_ITEM_TABLE]['feed_id_idx'] = array('INDEX', 'feed_id');
        $indices[FOF_ITEM_TABLE]['item_guid_idx'] = array('INDEX', 'item_guid');
        $indices[FOF_ITEM_TABLE]['feed_id_item_cached_idx'] = array('INDEX', 'feed_id, item_cached');
        $indices[FOF_ITEM_TABLE]['feed_id_item_updated_idx'] = array('INDEX', 'feed_id, item_updated');
    }


    /* FOF_ITEM_TAG_TABLE */
    /* columns */
    $tables[FOF_ITEM_TAG_TABLE][] = "user_id $driver_int_type NOT NULL DEFAULT '0' REFERENCES " . FOF_USER_TABLE . " ( user_id ) ON UPDATE CASCADE ON DELETE CASCADE";
    $tables[FOF_ITEM_TAG_TABLE][] = "item_id $driver_int_type NOT NULL DEFAULT '0' REFERENCES " . FOF_ITEM_TABLE . " ( item_id ) ON UPDATE CASCADE ON DELETE CASCADE";
    $tables[FOF_ITEM_TAG_TABLE][] = "tag_id $driver_int_type NOT NULL DEFAULT '0' REFERENCES " . FOF_TAG_TABLE . " ( tag_id ) ON UPDATE CASCADE ON DELETE CASCADE";
    $tables[FOF_ITEM_TAG_TABLE][] = "PRIMARY KEY ( user_id, item_id, tag_id )";


    /* FOF_SUBSCRIPTION_TABLE */
    /* columns */
    $tables[FOF_SUBSCRIPTION_TABLE][] = "feed_id $driver_int_type NOT NULL DEFAULT '0' REFERENCES " . FOF_FEED_TABLE . " ( feed_id ) ON UPDATE CASCADE ON DELETE CASCADE";
    $tables[FOF_SUBSCRIPTION_TABLE][] = "user_id $driver_int_type NOT NULL DEFAULT '0' REFERENCES " . FOF_USER_TABLE . " ( user_id ) ON UPDATE CASCADE ON DELETE CASCADE";
    $tables[FOF_SUBSCRIPTION_TABLE][] = "subscription_prefs TEXT";
    $tables[FOF_SUBSCRIPTION_TABLE][] = "PRIMARY KEY ( feed_id, user_id )";


    /* FOF_TAG_TABLE */
    /* columns */
    if (defined('USE_MYSQL')) {
        $tables[FOF_TAG_TABLE][] = "tag_id $driver_int_type NOT NULL AUTO_INCREMENT";
    } else if (defined('USE_SQLITE')) {
        $tables[FOF_TAG_TABLE][] = "tag_id $driver_int_type PRIMARY KEY AUTOINCREMENT NOT NULL";
    }
    $tables[FOF_TAG_TABLE][] = "tag_name CHAR(100) NOT NULL DEFAULT ''";
    if (defined('USE_MYSQL')) {
        $tables[FOF_TAG_TABLE][] = "PRIMARY KEY ( tag_id )";
        $tables[FOF_TAG_TABLE][] = "UNIQUE KEY ( tag_name )";
    }

    /* indices */
    if (defined('USE_SQLITE')) {
        $indices[FOF_TAG_TABLE]['tag_name_idx'] = array('UNIQUE INDEX', 'tag_name');
    }


    /* FOF_USER_LEVELS_TABLE */
    /* SQLite doesn't support ENUM, so it gets another table.. */
    /* columns */
    if (defined('USE_SQLITE')) {
        $tables[FOF_USER_LEVELS_TABLE][] = "seq INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL";
        $tables[FOF_USER_LEVELS_TABLE][] = "level TEXT NOT NULL";
        $indices[FOF_USER_LEVELS_TABLE]['level_idx'] = array('UNIQUE INDEX', 'level');
    }


    /* FOF_USER_TABLE */
    /* columns */
    if (defined('USE_MYSQL')) {
        $tables[FOF_USER_TABLE][] = "user_id $driver_int_type NOT NULL";
    } else if (defined('USE_SQLITE')) {
        $tables[FOF_USER_TABLE][] = "user_id $driver_int_type PRIMARY KEY AUTOINCREMENT NOT NULL";
    }
    $tables[FOF_USER_TABLE][] = "user_name VARCHAR(100) NOT NULL DEFAULT ''";
    $tables[FOF_USER_TABLE][] = "user_password_hash VARCHAR(32) NOT NULL DEFAULT ''";
    if (defined('USE_MYSQL')) {
        $tables[FOF_USER_TABLE][] = "user_level ENUM ( 'user', 'admin' ) NOT NULL DEFAULT 'user'";
    } else if (defined('USE_SQLITE')) {
        $tables[FOF_USER_TABLE][] = "user_level TEXT NOT NULL DEFAULT 'user' REFERENCES " . FOF_USER_LEVELS_TABLE . " ( level ) ON UPDATE CASCADE";
    }
    $tables[FOF_USER_TABLE][] = "user_prefs TEXT";
    if (defined('USE_MYSQL')) {
        $tables[FOF_USER_TABLE][] = "PRIMARY KEY ( user_id )";
    }

    return array($tables, $indices);
}


/* given a schema array, returns an array of queries to define the database */
/* if exec is set, execute the statements, to create the database */
function fof_install_database($schema, $exec=0) {
    list($tables, $indices) = $schema;

    try {
        $query_history = array();
        foreach ($tables as $table_name => $column_array) {
            $query = fof_install_create_table_query($table_name, $column_array);
            if ( ! empty($query) ) {
                $query_history[] = $query;
                if ($exec) {
                    echo "<br><span>table $table_name ";
                        if (fof_db_exec($query) === false) {
                            echo "<span class='fail'>FAIL</span>";
                        } else {
                            echo "<span class='pass'>OK</span>";
                        }
                    echo "</span>\n";
                }
            }
            if (isset($indices[$table_name]) && is_array($indices[$table_name])) {
                foreach ($indices[$table_name] as $index_name => $index_def) {
                    $query = fof_install_create_index_query($table_name, $index_name, $index_def);
                    if ( ! empty($query) ) {
                        $query_history[] = $query;
                        if ($exec) {
                            echo "<br><span>table $table_name index $index_name ";
                            if (fof_db_exec($query) === false) {
                                echo "<span class='fail>FAIL</span>";
                            } else {
                                echo "<span class='pass'>OK</span>";
                            }
                            echo "</span>\n";
                        }
                    }
                }
            }
        }
    } catch (PDOException $e) {
        echo "<span class='fail'>[<code>$query</code>] <pre>" . $e->GetMessage() . "</pre></span>\n";
    }

    return array_filter($query_history);
}

/* migrate any old tables to new versions */
/* FIXME: this is mysql-specific... */
/* but since only old databases could be on mysql, this should be okay for now */
function fof_install_database_update_old_tables() {
    if (defined('USE_MYSQL')) {
        try {
            $query = "SHOW COLUMNS FROM " . FOF_FEED_TABLE . " LIKE 'feed_image_cache_date'";
            if ( count(fof_db_query($query)->fetchAll()) == 0 ) {
                echo "Upgrading " . FOF_FEED_TABLE . ": 'feed_image_cache_date'... ";

                $query = "ALTER TABLE " . FOF_FEED_TABLE . " ADD 'feed_image_cache_date' INT DEFAULT '0' AFTER 'feed_image'";
                fof_db_exec($query);

                echo "Done.<hr>";
            }

            $query = "SHOW COLUMNS FROM " . FOF_USER_TABLE . " LIKE 'user_password_hash'";
            if ( count(fof_db_query($query)->fetchAll()) == 0 ) {
                echo "Upgrading " . FOF_USER_TABLE . ": 'user_password_hash'... ";

                $query = "ALTER TABLE " . FOF_USER_TABLE . " CHANGE 'user_password' 'user_password_hash' VARCHAR(32) NOT NULL";
                fof_db_exec($query);

                $query = "UPDATE " . FOF_USER_TABLE . " SET user_password_hash = md5(concat(user_password_hash, user_name))";
                fof_db_exec($query);

                echo "Done.<hr>";
            }

            $query = "SHOW COLUMNS FROM " . FOF_FEED_TABLE . " LIKE 'feed_cache_attempt_date'";
            if ( count(fof_db_query($query)->fetchAll()) == 0 ) {
                echo "Upgrading " . FOF_FEED_TABLE . ": 'feed_cache_attempt_date'... ";

                $query = "ALTER TABLE " . FOF_FEED_TABLE . " ADD 'feed_cache_attempt_date' INT DEFAULT '0' AFTER 'feed_cache_date'";
                fof_db_exec($query);

                echo "Done.<hr>";
            }

            $query = "SHOW COLUMNS FROM " . FOF_FEED_TABLE . " LIKE 'feed_cache_next_attempt'";
            if ( count(fof_db_query($query)->fetchAll()) == 0 ) {
                echo "Upgrading " . FOF_FEED_TABLE . ": 'feed_cache_next_attempt'... ";

                $query = "ALTER TABLE " . FOF_FEED_TABLE . " ADD 'feed_cache_next_attempt' INT DEFAULT '0'";
                fof_db_exec($query);

                $query = "ALTER TABLE " . FOF_FEED_TABLE . " ADD KEY 'feed_cache_next_attempt' ('feed_cache_next_attempt')";
                fof_db_exec($query);

                echo "Done.<hr>";
            }

            $query = "SHOW INDEXES FROM " . FOF_ITEM_TABLE . " WHERE key_name LIKE 'feed_id_item_updated'";
            if ( count(fof_db_query($query)->fetchAll()) == 0 ) {
                echo "Upgrading " . FOF_ITEM_TABLE . " 'feed_id_item_updated'... ";

                $query = "ALTER TABLE " . FOF_ITEM_TABLE . " ADD KEY 'feed_id_item_updated' ('feed_id', 'item_updated')";
                fof_db_exec($query);

                echo "Done.<hr>";
            }
        } catch (PDOException $e) {
            die('Cannot migrate table: <pre>' . $e->GetMessage() . '</pre>');
        }
    } /* USE_MYSQL */
}

/* install initial values into database */
function fof_install_database_populate() {
    if (defined('USE_SQLITE')) {
        /* populate user level table */
        echo "<br>Populating " . FOF_USER_LEVELS_TABLE . "... \n";
        $entries = array();
        $entries[] = array('level' => 'admin');
        $entries[] = array('level' => 'user');

        $query_insert = "INSERT INTO " . FOF_USER_LEVELS_TABLE . " ( level ) VALUES ( :level )";
        $statement_insert = fof_db_statement_prepare($query_insert);

        $query_check = "SELECT * FROM " . FOF_USER_LEVELS_TABLE . " WHERE level = :level";
        $statement_check = fof_db_statement_prepare($query_check);

        foreach ($entries as $entry) {
            try {
                echo "<br><span>" . $entry['level'];
                $result_check = fof_db_statement_execute($statement_check, $entry);
                $rows_check = $statement_check->fetchAll();
            } catch (PDOException $e) {
                echo "Cannot check " . $entry['level'] . " [<code>$query_check</code>] <pre>" . $e->GetMessage() . "</pre>\n";
                exit();
            }
            if (count($rows_check)) {
                echo " <span class='pass'>exists</span>";
            } else {
                try {
                    if (($result_insert = fof_db_statement_execute($statement_insert, $entry)) !== false) {
                        echo " <span class='pass'>added</span>";
                    } else {
                        echo " <span class='fail'>failed</span>";
                    }
                    $statement_insert->closeCursor();
                } catch (PDOException $e) {
                    echo "Cannot populate " . $entry['level'] . " [<code>$query_insert</code>] <pre>" . $e->GetMessage() . "</pre>\n";
                    exit();
                }
            }
            echo "</span>\n";
        }
        echo " Done!\n";
    } /* USE_SQLITE */

    /* populate tag table */
    echo "<br>Populating " . FOF_TAG_TABLE . "... \n";
    $entries = array();
    $entries[] = array('tag_name' => 'unread');
    $entries[] = array('tag_name' => 'star');
    $entries[] = array('tag_name' => 'folded');

    $query_insert = "INSERT INTO " . FOF_TAG_TABLE . " ( tag_name ) VALUES ( :tag_name )";
    $statement_insert = fof_db_statement_prepare($query_insert);

    $query_check = "SELECT * FROM " . FOF_TAG_TABLE . " WHERE tag_name = :tag_name";
    $statement_check = fof_db_statement_prepare($query_check);

    foreach ($entries as $entry) {
        try {
            echo "<br><span>" . $entry['tag_name'];
            $result_check = fof_db_statement_execute($statement_check, $entry);
            $rows_check = $statement_check->fetchAll();
        } catch (PDOException $e) {
            echo "Cannot check " . $entry['tag_name'] . " [<code>$query_check</code>] <pre>" . $e->GetMessage() . "</pre>\n";
            exit();
        }
        if (count($rows_check)) {
            echo " <span class='pass'>exists</span>";
        } else {
            try {
                if (($result_insert = fof_db_statement_execute($statement_insert, $entry)) !== false) {
                    echo " <span class='pass'>added</span>";
                } else {
                    echo " <span class='fail'>failed</span>";
                }
                $statement_insert->closeCursor();
            } catch (PDOException $e) {
                echo "Cannot populate " . $entry['tag_name'] . " [<code>$query_insert</code>] <pre>" . $e->GetMessage() . "</pre>\n";
                exit();
            }
        }
        echo "</span>\n";
    }
    echo " Done!\n";
}

function fof_install_user_exists($user='admin') {
    global $fof_connection;

    $query = "SELECT * FROM " . FOF_USER_TABLE . " WHERE user_name = " . $fof_connection->quote($user);

    try {
        $statement = fof_db_query($query);
        $rows = $statement->fetchAll();
    } catch (PDOException $e) {
        echo "Cannot select user: <pre>" . $e->GetMessage() . "</pre>";
        exit();
    }
    return ( ! empty($rows));
}
?>
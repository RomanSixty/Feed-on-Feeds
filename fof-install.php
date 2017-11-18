<?php
/*
Installation and update routines.

 */

/* skip authentication if true, user_id will be 1 */
$fof_no_login = true;

/* installer is running if true, skip auth, no user_id will be set, no prefs loaded, no plugins initialized */
$fof_installer = true;

/* Pull in the fof core, config will be parsed and database will be connected. */
require_once 'fof-main.php';

/* different drivers prefer different types */
$driver_count = 0;
if (defined('USE_MYSQL')) {
	define('SQL_DRIVER_INT_TYPE', 'INT(11)');
	$driver_count++;
} else if (defined('USE_SQLITE')) {
	define('SQL_DRIVER_INT_TYPE', 'INTEGER');
	$driver_count++;
} else {
	throw new Exception('Unimplemented pdo driver.');
}
if ($driver_count != 1) {
	throw new Exception('Need to have exactly one SQL backend enabled');
}

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
function fof_install_compat_notice($is_ok, $what, $fail_msg, $fail_extra, $is_required = 0) {
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

/* ensure the data dir is present and usable */
function fof_install_datadir() {
	if (!defined('FOF_DATA_PATH')) {
		echo '<span class="fail">Required configuration FOF_DATA_PATH not found</span>';
		return false;
	}

	$datadir = FOF_DATA_PATH;
	if (!file_exists($datadir)) {
		$status = @mkdir($datadir, 0700);
		if (!$status) {
			echo '<span class="fail">Can\'t create data directory <code>$datadir</code>; '
				. 'please ensure that it was configured correctly and is in a location '
				. 'you have write access to.</span>';
			return false;
		}
	}

	if (!is_writable($datadir)) {
		echo '<span class="fail">Can\'t write to data directory <code>$datadir</code>; '
			. 'please ensure that it was configured correctly and is in a location '
			. 'you have write access to.</span>';
		return false;
	}

	echo "<span class='pass'>Data directory <code>$datadir</code> exists and is writable.</span>\n";

	return true;
}

/* ensure the cache dir is present and usable */
function fof_install_cachedir() {
	$cachedir = defined('FOF_CACHE_DIR') ? FOF_CACHE_DIR : "cache";
	$cachedir_full_html = '<code>' . htmlspecialchars(implode(DIRECTORY_SEPARATOR, array(getcwd(), $cachedir))) . '</code>';

	if (!file_exists($cachedir)) {
		$status = @mkdir($cachedir, 0755);
		if (!$status) {
			echo "<span class='fail'>Can't create cache directory $cachedir_full_html.";
			echo "<br>Create it before continuing.";
			echo "</span>\n";
			return false;
		}
	}

	if (!is_writable($cachedir)) {
		echo "<span class='fail'>Cache directory $cachedir_full_html exists, but is not writable.";
		echo "<br>Ensure it is writable before continuing.";
		echo "</span>\n";
		return false;
	}

	echo "<span class='pass'>Cache directory $cachedir_full_html exists and is writable.</span>\n";

	return true;
}

/** Generate a query string to create a table, given an array of columns.
SQLite and MySQL speak slightly different CREATE dialects, mostly concerning
primary keys and indices, so schema generation has been rendered into a
rudimentary templated format, to avoid duplicating each statement for each
driver.
This was thrown together off-the-cuff -- there is assuredly a far more
mature solution to this problem in existence somewhere..
 */
function fof_install_create_table_query($table_name, $column_array) {
	$query = "CREATE TABLE IF NOT EXISTS $table_name (\n  ";
	$query .= implode(",\n  ", $column_array);
	$query .= "\n)";
	if (defined('USE_MYSQL')) {
		$query .= " ENGINE=" . MYSQL_ENGINE . " DEFAULT CHARSET=UTF8";
	}

	return $query . ';';
}

/** Generate a query string to create an index on a table.
This assumes that the table already exists.
N.B. SQLite index names will have '_idx' postfixed automatically
 */
function fof_install_create_index_query($table_name, $index_name, $index_def) {
	/* unpack the index definition */
	list($idx_type, $idx_val) = $index_def;

	if (defined('USE_MYSQL')) {
		str_replace('INDEX', 'KEY', $idx_type);
		$query = "ALTER TABLE $table_name ADD KEY $index_name ($idx_val)";
		return $query;
	}
	if (defined('USE_SQLITE')) {
		$index_name = $index_name . '_idx';
		$query = "CREATE $idx_type IF NOT EXISTS $index_name ON $table_name ( $idx_val );";
		return $query;
	}
	throw new Exception('Query not implemented for this pdo driver.');
}

/** Generate a query to create a foreign key reference on a column.
 */
function fof_install_create_reference_query($table_name, $column_name, $reference_def) {
	list($ref_table, $ref_column) = $reference_def;
	if (defined('USE_MYSQL')) {
		$query = "ALTER TABLE " . $table_name . " ADD FOREIGN KEY(" . $column_name . ") REFERENCES " . $ref_table . " (" . $ref_column . ") ON DELETE CASCADE ON UPDATE CASCADE";
		return $query;
	}
	if (defined('USE_SQLITE')) {
		/*
			I guess this isn't possible without creating a new table, migrating
			the data, then dropping the old table.
			FIXME: Supporting this is going to be ugly.
		*/
		return NULL;
	}
	throw new Exception('Query not implemented for this pdo driver.');
}

/* define all tables as arrays */
/* most of the fiddly driver-specific quirks are finagled in here */
function fof_install_schema() {
	$tables = array();
	$indices = array();

	/* FOF_FEED_TABLE *
    /* columns */
	if (defined('USE_MYSQL')) {
		$tables[FOF_FEED_TABLE][] = "feed_id " . SQL_DRIVER_INT_TYPE . " NOT NULL AUTO_INCREMENT";
	} else if (defined('USE_SQLITE')) {
		$tables[FOF_FEED_TABLE][] = "feed_id " . SQL_DRIVER_INT_TYPE . " PRIMARY KEY AUTOINCREMENT NOT NULL";
	}
	$tables[FOF_FEED_TABLE][] = "feed_url TEXT NOT NULL";
	$tables[FOF_FEED_TABLE][] = "feed_title TEXT NOT NULL";
	$tables[FOF_FEED_TABLE][] = "feed_link TEXT NOT NULL";
	$tables[FOF_FEED_TABLE][] = "feed_description TEXT NOT NULL";
	$tables[FOF_FEED_TABLE][] = "feed_image TEXT";
	$tables[FOF_FEED_TABLE][] = "feed_image_cache_date " . SQL_DRIVER_INT_TYPE . " DEFAULT '0'";
	$tables[FOF_FEED_TABLE][] = "feed_cache_date " . SQL_DRIVER_INT_TYPE . " DEFAULT '0'";
	$tables[FOF_FEED_TABLE][] = "feed_cache_attempt_date " . SQL_DRIVER_INT_TYPE . " DEFAULT '0'";
	$tables[FOF_FEED_TABLE][] = "feed_cache_next_attempt " . SQL_DRIVER_INT_TYPE . " DEFAULT '0'";
	$tables[FOF_FEED_TABLE][] = "feed_cache TEXT"; /* FIXME: unused column? */
	$tables[FOF_FEED_TABLE][] = "feed_cache_last_attempt_status TEXT";
	if (defined('USE_MYSQL')) {
		$tables[FOF_FEED_TABLE][] = "PRIMARY KEY ( feed_id )";
		$tables[FOF_FEED_TABLE][] = "KEY feed_cache_next_attempt ( feed_cache_next_attempt )";
	}

	/* indices */
	if (defined('USE_SQLITE')) {
		$indices[FOF_FEED_TABLE]['feed_cache_next_attempt'] = array('INDEX', 'feed_cache_next_attempt');
	}

	/* FOF_ITEM_TABLE */
	/* columns */
	if (defined('USE_MYSQL')) {
		$tables[FOF_ITEM_TABLE][] = "item_id " . SQL_DRIVER_INT_TYPE . " NOT NULL AUTO_INCREMENT";
	} else if (defined('USE_SQLITE')) {
		$tables[FOF_ITEM_TABLE][] = "item_id " . SQL_DRIVER_INT_TYPE . " PRIMARY KEY AUTOINCREMENT NOT NULL";
	}
	$tables[FOF_ITEM_TABLE][] = "feed_id " . SQL_DRIVER_INT_TYPE . " NOT NULL DEFAULT '0' REFERENCES " . FOF_FEED_TABLE . " ( feed_id ) ON UPDATE CASCADE ON DELETE CASCADE";
	$tables[FOF_ITEM_TABLE][] = "item_guid TEXT NOT NULL";
	$tables[FOF_ITEM_TABLE][] = "item_link TEXT NOT NULL";
	$tables[FOF_ITEM_TABLE][] = "item_cached " . SQL_DRIVER_INT_TYPE . " NOT NULL DEFAULT '0'";
	$tables[FOF_ITEM_TABLE][] = "item_published " . SQL_DRIVER_INT_TYPE . " NOT NULL DEFAULT '0'";
	$tables[FOF_ITEM_TABLE][] = "item_updated " . SQL_DRIVER_INT_TYPE . " NOT NULL DEFAULT '0'";
	$tables[FOF_ITEM_TABLE][] = "item_title TEXT NOT NULL";
	$tables[FOF_ITEM_TABLE][] = "item_content TEXT NOT NULL";
	$tables[FOF_ITEM_TABLE][] = "item_author TEXT";

	/* indices */
	if (defined('USE_MYSQL')) {
		$tables[FOF_ITEM_TABLE][] = "PRIMARY KEY (item_id)";
		$tables[FOF_ITEM_TABLE][] = "FOREIGN KEY (feed_id) REFERENCES " . FOF_FEED_TABLE . " (feed_id) ON UPDATE CASCADE ON DELETE CASCADE";
		$tables[FOF_ITEM_TABLE][] = "KEY item_guid ( item_guid(255) )";
		$tables[FOF_ITEM_TABLE][] = "KEY feed_id_item_cached ( feed_id, item_cached )";
		$tables[FOF_ITEM_TABLE][] = "KEY feed_id_item_updated ( feed_id, item_updated )";
	}
	if (defined('USE_SQLITE')) {
		$indices[FOF_ITEM_TABLE]['feed_id'] = array('INDEX', 'feed_id');
		$indices[FOF_ITEM_TABLE]['item_guid'] = array('INDEX', 'item_guid');
		$indices[FOF_ITEM_TABLE]['item_title'] = array('INDEX', 'item_title');
		$indices[FOF_ITEM_TABLE]['feed_id_item_cached'] = array('INDEX', 'feed_id, item_cached');
		$indices[FOF_ITEM_TABLE]['feed_id_item_updated'] = array('INDEX', 'feed_id, item_updated');
	}

	/* FOF_ITEM_TAG_TABLE */
	/* columns */
	$tables[FOF_ITEM_TAG_TABLE][] = "user_id " . SQL_DRIVER_INT_TYPE . " NOT NULL DEFAULT '0' REFERENCES " . FOF_USER_TABLE . " ( user_id ) ON UPDATE CASCADE ON DELETE CASCADE";
	$tables[FOF_ITEM_TAG_TABLE][] = "item_id " . SQL_DRIVER_INT_TYPE . " NOT NULL DEFAULT '0' REFERENCES " . FOF_ITEM_TABLE . " ( item_id ) ON UPDATE CASCADE ON DELETE CASCADE";
	$tables[FOF_ITEM_TAG_TABLE][] = "tag_id " . SQL_DRIVER_INT_TYPE . " NOT NULL DEFAULT '0' REFERENCES " . FOF_TAG_TABLE . " ( tag_id ) ON UPDATE CASCADE ON DELETE CASCADE";
	$tables[FOF_ITEM_TAG_TABLE][] = "PRIMARY KEY ( user_id, item_id, tag_id )";

	/* indices */
	if (defined('USE_MYSQL')) {
		$tables[FOF_ITEM_TAG_TABLE][] = "FOREIGN KEY (user_id) REFERENCES " . FOF_USER_TABLE . " (user_id) ON UPDATE CASCADE ON DELETE CASCADE";
		$tables[FOF_ITEM_TAG_TABLE][] = "FOREIGN KEY (item_id) REFERENCES " . FOF_ITEM_TABLE . " (item_id) ON UPDATE CASCADE ON DELETE CASCADE";
		$tables[FOF_ITEM_TAG_TABLE][] = "FOREIGN KEY (tag_id) REFERENCES " . FOF_TAG_TABLE . " (tag_id) ON UPDATE CASCADE ON DELETE CASCADE";
		$tables[FOF_ITEM_TAG_TABLE][] = "KEY tag_id ( tag_id )";
	}
	if (defined('USE_SQLITE')) {
		$indices[FOF_ITEM_TAG_TABLE]['tag_id'] = array('INDEX', 'tag_id');
	}

	/* FOF_SUBSCRIPTION_TABLE */
	/* columns */
	$tables[FOF_SUBSCRIPTION_TABLE][] = "feed_id " . SQL_DRIVER_INT_TYPE . " NOT NULL DEFAULT '0' REFERENCES " . FOF_FEED_TABLE . " ( feed_id ) ON UPDATE CASCADE ON DELETE CASCADE";
	$tables[FOF_SUBSCRIPTION_TABLE][] = "user_id " . SQL_DRIVER_INT_TYPE . " NOT NULL DEFAULT '0' REFERENCES " . FOF_USER_TABLE . " ( user_id ) ON UPDATE CASCADE ON DELETE CASCADE";
	$tables[FOF_SUBSCRIPTION_TABLE][] = "subscription_prefs TEXT";
	$tables[FOF_SUBSCRIPTION_TABLE][] = "PRIMARY KEY ( feed_id, user_id )";
	if (defined('USE_MYSQL')) {
		$tables[FOF_SUBSCRIPTION_TABLE][] = "FOREIGN KEY (feed_id) REFERENCES " . FOF_FEED_TABLE . " (feed_id) ON UPDATE CASCADE ON DELETE CASCADE";
		$tables[FOF_SUBSCRIPTION_TABLE][] = "FOREIGN KEY (user_id) REFERENCES " . FOF_USER_TABLE . " (user_id) ON UPDATE CASCADE ON DELETE CASCADE";
	}

	/* FOF_VIEW_TABLE */
	/* Stores the details about the preferred methods of displaying a collection of items. */
	/* columns */
	if (defined('USE_MYSQL')) {
		$tables[FOF_VIEW_TABLE][] = "view_id " . SQL_DRIVER_INT_TYPE . " NOT NULL AUTO_INCREMENT";
	} else if (defined('USE_SQLITE')) {
		$tables[FOF_VIEW_TABLE][] = "view_id " . SQL_DRIVER_INT_TYPE . " PRIMARY KEY AUTOINCREMENT NOT NULL";
	}
	$tables[FOF_VIEW_TABLE][] = "view_settings TEXT";
	if (defined('USE_MYSQL')) {
		$tables[FOF_VIEW_TABLE][] = "PRIMARY KEY (view_id)";
	}

	/* FOF_VIEW_STATE_TABLE */
	/* Associates a group of feed/tags with a view. */
	/*
		        This table also has triggers.  They will be created in the update
		        section below, as it's easier to check if they exist via script than
		        to craft a driver-portable create-if-not-exists statement here.
	*/

	/* columns */
	$tables[FOF_VIEW_STATE_TABLE][] = "user_id " . SQL_DRIVER_INT_TYPE . " NOT NULL REFERENCES " . FOF_USER_TABLE . " (user_id) ON UPDATE CASCADE ON DELETE CASCADE";
	$tables[FOF_VIEW_STATE_TABLE][] = "feed_id " . SQL_DRIVER_INT_TYPE . " REFERENCES " . FOF_FEED_TABLE . " (feed_id) ON UPDATE CASCADE ON DELETE CASCADE";
	$tables[FOF_VIEW_STATE_TABLE][] = "tag_id " . SQL_DRIVER_INT_TYPE . " REFERENCES " . FOF_TAG_TABLE . " (tag_id) ON UPDATE CASCADE ON DELETE CASCADE";
	$tables[FOF_VIEW_STATE_TABLE][] = "view_id " . SQL_DRIVER_INT_TYPE . " NOT NULL REFERENCES " . FOF_VIEW_TABLE . " (view_id) ON UPDATE CASCADE ON DELETE CASCADE";
	$tables[FOF_VIEW_STATE_TABLE][] = "CHECK ((feed_id IS NULL) != (tag_id IS NULL))";
	if (defined('USE_MYSQL')) {
		$tables[FOF_VIEW_STATE_TABLE][] = "FOREIGN KEY (user_id) REFERENCES " . FOF_USER_TABLE . " (user_id) ON UPDATE CASCADE ON DELETE CASCADE";
		$tables[FOF_VIEW_STATE_TABLE][] = "FOREIGN KEY (feed_id) REFERENCES " . FOF_FEED_TABLE . " (feed_id) ON UPDATE CASCADE ON DELETE CASCADE";
		$tables[FOF_VIEW_STATE_TABLE][] = "FOREIGN KEY (tag_id) REFERENCES " . FOF_TAG_TABLE . " (tag_id) ON UPDATE CASCADE ON DELETE CASCADE";
		$tables[FOF_VIEW_STATE_TABLE][] = "FOREIGN KEY (view_id) REFERENCES " . FOF_VIEW_TABLE . " (view_id) ON UPDATE CASCADE ON DELETE CASCADE";
	}

	/* FOF_TAG_TABLE */
	/* columns */
	if (defined('USE_MYSQL')) {
		$tables[FOF_TAG_TABLE][] = "tag_id " . SQL_DRIVER_INT_TYPE . " NOT NULL AUTO_INCREMENT";
	} else if (defined('USE_SQLITE')) {
		$tables[FOF_TAG_TABLE][] = "tag_id " . SQL_DRIVER_INT_TYPE . " PRIMARY KEY AUTOINCREMENT NOT NULL";
	}
	$tables[FOF_TAG_TABLE][] = "tag_name CHAR(100) NOT NULL DEFAULT ''";
	if (defined('USE_MYSQL')) {
		$tables[FOF_TAG_TABLE][] = "PRIMARY KEY ( tag_id )";
		$tables[FOF_TAG_TABLE][] = "UNIQUE KEY ( tag_name )";
	}

	/* indices */
	if (defined('USE_SQLITE')) {
		$indices[FOF_TAG_TABLE]['tag_name'] = array('UNIQUE INDEX', 'tag_name');
	}

	/* FOF_USER_LEVELS_TABLE */
	/* SQLite doesn't support ENUM, so it gets another table.. */
	/* columns */
	if (defined('USE_SQLITE')) {
		$tables[FOF_USER_LEVELS_TABLE][] = "seq INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL";
		$tables[FOF_USER_LEVELS_TABLE][] = "level TEXT NOT NULL";
		$indices[FOF_USER_LEVELS_TABLE]['level'] = array('UNIQUE INDEX', 'level');
	}

	/* FOF_USER_TABLE */
	/* columns */
	if (defined('USE_MYSQL')) {
		$tables[FOF_USER_TABLE][] = "user_id " . SQL_DRIVER_INT_TYPE . " NOT NULL";
	} else if (defined('USE_SQLITE')) {
		$tables[FOF_USER_TABLE][] = "user_id " . SQL_DRIVER_INT_TYPE . " PRIMARY KEY AUTOINCREMENT NOT NULL";
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
function fof_install_database($schema, $exec = 0) {
	global $fof_connection;
	list($tables, $indices) = $schema;

	try {
		$query_history = array();
		foreach ($tables as $table_name => $column_array) {
			$query = fof_install_create_table_query($table_name, $column_array);
			if (!empty($query)) {
				$query_history[] = $query;
				if ($exec) {
					echo "<br><span>table $table_name ";
					if ($fof_connection->exec($query) === false) {
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
					if (!empty($query)) {
						$query_history[] = $query;
						if ($exec) {
							echo "<br><span>table $table_name index $index_name ";
							if ($fof_connection->exec($query) === false) {
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

/** Determine if a column exists in a table.
 */
function fof_install_database_column_exists($table, $name) {
	global $fof_connection;

	if (defined('USE_MYSQL')) {
		$query = "SHOW COLUMNS FROM " . $table . " LIKE '" . $name . "'";
		$statement = $fof_connection->query($query);
		$column = fof_db_get_row($statement, NULL, TRUE);
		return !empty($column);
	}
	if (defined('USE_SQLITE')) {
		$query = "PRAGMA table_info(" . $table . ")";
		$statement = $fof_connection->query($query);
		while (($column = fof_db_get_row($statement, 'name')) !== false) {
			if ($column == $name) {
				$statement->closeCursor();
				return true;
			}
		}
		return false;
	}
	throw new Exception('Query not implemented for this pdo driver.');
}

/** Update queries if column doesn't exist.
 */
function fof_install_migrate_column(&$queries, $table, $column, $what) {
	if (!is_array($what)) {
		$what = array($what);
	}

	$desc = array($table, $column, 'column');
	if (!fof_install_database_column_exists($table, $column)) {
		foreach ($what as $n => $q) {
			if (is_string($n)) {
				$desc[] = $n;
			}

			$queries[implode('.', $desc)] = $q;
		}
	} else {
		echo '<div class="exists">' . implode('.', $desc) . ' is up to date.</div>' . "\n";
	}
}

/** Determine if an index exists on a table.
N.B. This requires a per-backend naming convention: SQLite index names shall
be postfixed with '_idx'.  This is enacted by the index creation
function fof_install_create_index_query().
 */
function fof_install_database_index_exists($table, $index) {
	global $fof_connection;

	if (defined('USE_MYSQL')) {
		$query = "SHOW INDEXES FROM " . $table . " WHERE key_name LIKE '" . $index . "'";
		$statement = $fof_connection->query($query);
		$row = fof_db_get_row($statement, NULL, TRUE);
		return !empty($row);
	}
	if (defined('USE_SQLITE')) {
		$index = $index . "_idx";
		$query = "SELECT * FROM sqlite_master WHERE type='index' AND tbl_name='" . $table . "' AND name = '" . $index . "'";
		$statement = $fof_connection->query($query);
		$row = fof_db_get_row($statement, NULL, TRUE);
		return !empty($row);
	}
	throw new Exception('Query not implemented for this pdo driver.');
}

/** Update queries if index doesn't exist.
 */
function fof_install_migrate_index(&$queries, $table, $index, $what) {
	if (!is_array($what)) {
		$what = array($what);
	}

	$desc = array($table, $index, 'index');
	if (!fof_install_database_index_exists($table, $index)) {
		foreach ($what as $n => $q) {
			if (is_string($n)) {
				$desc[] = $n;
			}

			$queries[implode('.', $desc)] = $q;
		}
	} else {
		echo '<div class="exists">' . implode('.', $desc) . ' is up to date.</div>' . "\n";
	}
}

/** Determine if a stored procedure exists.
 */
function fof_install_database_procedure_exists($proc) {
	global $fof_connection;

	if (defined('USE_MYSQL')) {
		$query = "SELECT * FROM INFORMATION_SCHEMA.ROUTINES WHERE ROUTINE_TYPE='PROCEDURE' AND ROUTINE_SCHEMA='" . FOF_DB_DBNAME . "' AND ROUTINE_NAME LIKE '" . $proc . "'";
		$statement = $fof_connection->query($query);
		$row = fof_db_get_row($statement, NULL, TRUE);
		return !empty($row);
	}
	throw new Exception('Query not implemented for this pdo driver.');
}

/** Determine if a trigger exists.
 */
function fof_install_database_trigger_exists($table, $trigger) {
	global $fof_connection;

	if (defined('USE_MYSQL')) {
		$query = "SELECT * FROM INFORMATION_SCHEMA.TRIGGERS WHERE EVENT_OBJECT_TABLE='" . $table . "' AND TRIGGER_NAME='" . $trigger . "'";
		$statement = $fof_connection->query($query);
		$row = fof_db_get_row($statement, NULL, TRUE);
		return !empty($row);
	}
	if (defined('USE_SQLITE')) {
		$query = "SELECT * FROM sqlite_master WHERE type='trigger' AND tbl_name='" . $table . "' AND name='" . $trigger . "'";
		$statement = $fof_connection->query($query);
		$row = fof_db_get_row($statement, NULL, TRUE);
		return !empty($row);
	}
	throw new Exception('Query not implemented for this pdo driver.');
}

/** Determine if a foreign key exists.
 */
function fof_install_database_reference_exists($table, $column) {
	global $fof_connection;

	if (defined('USE_MYSQL')) {
		$query = "SELECT * FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA='" . FOF_DB_DBNAME . "' AND TABLE_NAME='" . $table . "' AND COLUMN_NAME='" . $column . "' AND CONSTRAINT_NAME NOT LIKE 'PRIMARY'";

		$statement = $fof_connection->query($query);
		$row = fof_db_get_row($statement, NULL, TRUE);
		return !empty($row);
	}
	if (defined('USE_SQLITE')) {
		$query = "PRAGMA foreign_key_list('" . $table . "')";
		$statement = $fof_connection->query($query);
		while (($row = fof_db_get_row($statement)) !== false) {
			if ($row['from'] == $column) {
				$statement->closeCursor();
				return true;
			}
		}
		return false;
	}
	throw new Exception('Query not implemented for this pdo driver.');
}

/** Update queries if reference doesn't exist.
 */
function fof_install_migrate_reference(&$queries, $table, $index, $what) {
	if (defined('USE_MYSQL') && MYSQL_ENGINE == 'MyISAM') {
		/* MyISAM tables don't support foreign keys */
		return;
	}

	if (!is_array($what)) {
		$what = array($what);
	}

	$desc = array($table, $index, 'reference');
	if (!fof_install_database_reference_exists($table, $index)) {
		foreach ($what as $n => $q) {
			if (is_string($n)) {
				$desc[] = $n;
			}

			$queries[implode('.', $desc)] = $q;
		}
	} else {
		echo '<div class="exists">' . implode('.', $desc) . ' is up to date.</div>' . "\n";
	}
}

/** Ensure tables are up to date.
FIXME: A lot of this is duplicated from table creation, it'd be nice to
coalesce them.
 */
function fof_install_database_update_old_tables() {
	global $fof_connection;
	$queries = array();

	try {
		/* triggers */
		if (!defined('SQL_NO_TRIGGERS')) {
			/*
				Some setups might not allow triggers, so skip installing them
				entirely if that situation has been declared.
			*/
			if (defined('USE_MYSQL')) {
				/*
					N.B. MySQL doesn't actually honor any CHECK expressions, so
					the xor-null constraint on the view_state table needs to be
					enforced via a trigger.
				*/
				if (!fof_install_database_trigger_exists(FOF_VIEW_STATE_TABLE, 'xor_null_before_insert')) {
					$queries[FOF_VIEW_STATE_TABLE . '.xor_null_before_insert.trigger'] = "CREATE TRIGGER xor_null_before_insert BEFORE INSERT ON " . FOF_VIEW_STATE_TABLE . "
FOR EACH ROW
BEGIN
    IF ((NEW.feed_id IS NULL) = (NEW.tag_id IS NULL)) THEN
        SIGNAL SQLSTATE '23513' SET MESSAGE_TEXT = 'xor-null constraint failed in INSERT on " . FOF_VIEW_STATE_TABLE . "';
    END IF;
END";
				} else {
					echo '<div class="exists">' . FOF_VIEW_STATE_TABLE . ' before_insert_trigger is up to date.</div>' . "\n";
				}

				if (!fof_install_database_trigger_exists(FOF_VIEW_STATE_TABLE, 'xor_null_before_update')) {
					$queries[FOF_VIEW_STATE_TABLE . '.xor_null_before_update.trigger'] = "CREATE TRIGGER xor_null_before_update BEFORE UPDATE ON " . FOF_VIEW_STATE_TABLE . "
FOR EACH ROW
BEGIN
    IF ((NEW.feed_id IS NULL) = (NEW.tag_id IS NULL)) THEN
        SIGNAL SQLSTATE '23513' SET MESSAGE_TEXT = 'xor-null constraint failed in UPDATE on " . FOF_VIEW_STATE_TABLE . "';
    END IF;
END";
				} else {
					echo '<div class="exists">' . FOF_VIEW_STATE_TABLE . ' before_update_trigger is up to date.</div>' . "\n";
				}
			} /* USE_MYSQL */

			/* If a tag or feed is deleted, purge any view states which included them. */
			if (!fof_install_database_trigger_exists(FOF_VIEW_STATE_TABLE, 'cascade_view_delete')) {
				$queries[FOF_VIEW_STATE_TABLE . '.cascade_view_delete.trigger'] = "CREATE TRIGGER cascade_view_delete AFTER DELETE ON " . FOF_VIEW_STATE_TABLE . "
FOR EACH ROW
BEGIN
    DELETE FROM " . FOF_VIEW_STATE_TABLE . " WHERE view_id = OLD.view_id;
    DELETE FROM " . FOF_VIEW_TABLE . " WHERE view_id = OLD.view_id;
END";
			} else {
				echo '<div class="exists">' . FOF_VIEW_STATE_TABLE . ' after_delete_trigger is up to date.</div>' . "\n";
			}
		} /* SQL_NO_TRIGGERS */

		/* FOF_USER_TABLE */
		fof_install_migrate_column($queries, FOF_USER_TABLE, 'user_password_hash', array(
			'rename' => "ALTER TABLE " . FOF_USER_TABLE . " CHANGE user_password user_password_hash VARCHAR(32) NOT NULL",
			'convert' => "UPDATE " . FOF_USER_TABLE . " SET user_password_hash = md5(concat(user_password_hash, user_name))",
		));

		/* FOF_FEED_TABLE */
		fof_install_migrate_column($queries, FOF_FEED_TABLE, 'feed_image_cache_date', array(
			'add' => "ALTER TABLE " . FOF_FEED_TABLE . " ADD feed_image_cache_date " . SQL_DRIVER_INT_TYPE . " DEFAULT 0 AFTER feed_image",
		));

		fof_install_migrate_column($queries, FOF_FEED_TABLE, 'feed_cache_attempt_date', array(
			'add' => "ALTER TABLE " . FOF_FEED_TABLE . " ADD feed_cache_attempt_date " . SQL_DRIVER_INT_TYPE . " DEFAULT '0' AFTER feed_cache_date",
		));

		fof_install_migrate_column($queries, FOF_FEED_TABLE, 'feed_cache_next_attempt', array(
			'add' => "ALTER TABLE " . FOF_FEED_TABLE . " ADD feed_cache_next_attempt " . SQL_DRIVER_INT_TYPE . " DEFAULT '0' AFTER feed_cache_attempt_date",
		));

		fof_install_migrate_index($queries, FOF_FEED_TABLE, 'feed_cache_next_attempt', array(
			'add' => fof_install_create_index_query(FOF_FEED_TABLE, 'feed_cache_next_attempt', array('INDEX', 'feed_cache_next_attempt')),
		));

		fof_install_migrate_column($queries, FOF_FEED_TABLE, 'feed_cache_last_attempt_status', array(
			'add' => "ALTER TABLE " . FOF_FEED_TABLE . " ADD feed_cache_last_attempt_status TEXT AFTER feed_cache_next_attempt",
		));

		if (!defined('USE_SQLITE')) {
			/*  SQLite cannot drop columns without creating a new table, copying
				data, dropping the old table, and renaming the new one...
				A few unused columns won't hurt anything for a while.
			*/
			if (fof_install_database_column_exists(FOF_FEED_TABLE, 'alt_image')) {
				$queries[FOF_FEED_TABLE . '.alt_image.drop'] = "ALTER TABLE " . FOF_FEED_TABLE . " DROP COLUMN alt_image";
			} else {
				echo '<div class="exists">' . FOF_FEED_TABLE . '.' . 'alt_image' . ' is up to date.</div>' . "\n";
			}
		}

		/* FOF_ITEM_TABLE */
		fof_install_migrate_index($queries, FOF_ITEM_TABLE, 'feed_id_item_updated', array(
			'add' => fof_install_create_index_query(FOF_ITEM_TABLE, 'feed_id_item_updated', array('INDEX', 'feed_id, item_updated')),
		));

		fof_install_migrate_index($queries, FOF_ITEM_TABLE, 'item_title', array(
			'add' => fof_install_create_index_query(FOF_ITEM_TABLE, 'item_title', array('INDEX', 'item_title(255)')),
		));

		fof_install_migrate_reference($queries, FOF_ITEM_TABLE, 'feed_id', array(
			'add' => fof_install_create_reference_query(FOF_ITEM_TABLE, 'feed_id', array(FOF_FEED_TABLE, 'feed_id')),
		));

		fof_install_migrate_column($queries, FOF_ITEM_TABLE, 'item_author', array(
			'add' => "ALTER TABLE " . FOF_ITEM_TABLE . " ADD item_author TEXT AFTER item_content",
		));

		/* FOF_ITEM_TAG_TABLE */
		fof_install_migrate_reference($queries, FOF_ITEM_TAG_TABLE, 'user_id', array(
			'add' => fof_install_create_reference_query(FOF_ITEM_TAG_TABLE, 'user_id', array(FOF_USER_TABLE, 'user_id')),
		));

		fof_install_migrate_reference($queries, FOF_ITEM_TAG_TABLE, 'item_id', array(
			'add' => fof_install_create_reference_query(FOF_ITEM_TAG_TABLE, 'item_id', array(FOF_ITEM_TABLE, 'item_id')),
		));

		fof_install_migrate_reference($queries, FOF_ITEM_TAG_TABLE, 'tag_id', array(
			'add' => fof_install_create_reference_query(FOF_ITEM_TAG_TABLE, 'tag_id', array(FOF_TAG_TABLE, 'tag_id')),
		));

		/* FOF_SUBSCRIPTION_TABLE */
		fof_install_migrate_reference($queries, FOF_SUBSCRIPTION_TABLE, 'user_id', array(
			'add' => fof_install_create_reference_query(FOF_SUBSCRIPTION_TABLE, 'user_id', array(FOF_USER_TABLE, 'user_id')),
		));

		fof_install_migrate_reference($queries, FOF_SUBSCRIPTION_TABLE, 'feed_id', array(
			'add' => fof_install_create_reference_query(FOF_SUBSCRIPTION_TABLE, 'feed_id', array(FOF_FEED_TABLE, 'feed_id')),
		));

		/* FOF_VIEW_STATE_TABLE */
		fof_install_migrate_reference($queries, FOF_VIEW_STATE_TABLE, 'user_id', array(
			'add' => fof_install_create_reference_query(FOF_VIEW_STATE_TABLE, 'user_id', array(FOF_USER_TABLE, 'user_id')),
		));

		fof_install_migrate_reference($queries, FOF_VIEW_STATE_TABLE, 'feed_id', array(
			'add' => fof_install_create_reference_query(FOF_VIEW_STATE_TABLE, 'feed_id', array(FOF_FEED_TABLE, 'feed_id')),
		));

		fof_install_migrate_reference($queries, FOF_VIEW_STATE_TABLE, 'tag_id', array(
			'add' => fof_install_create_reference_query(FOF_VIEW_STATE_TABLE, 'tag_id', array(FOF_TAG_TABLE, 'tag_id')),
		));

		fof_install_migrate_reference($queries, FOF_VIEW_STATE_TABLE, 'view_id', array(
			'add' => fof_install_create_reference_query(FOF_VIEW_STATE_TABLE, 'view_id', array(FOF_VIEW_TABLE, 'view_id')),
		));

		$queries = array_filter($queries); /* SQLite may generate some empty queries, as it can't yet alter references.. */

		echo '<span>' . count($queries) . ' updates needed.</span>' . "\n";

		$i = 1;
		$j = count($queries);
		foreach ($queries as $what => $query) {
			echo '<div class="update">[' . $i++ . '/' . $j . '] Updating ' . $what . ': ';
			try {
				$result = $fof_connection->exec($query);
			} catch (PDOException $e) {
				echo "<span class='fail'>Cannot upgrade table: [<code>$query</code>] <pre>" . $e->GetMessage() . "</pre></span>\n";
				$result = false;
			}
			if ($result !== false) {
				echo '<span class="pass" title="' . $result . ' rows affected">OK</span>';
			} else {
				echo '<span class="fail">FAIL</span>';
			}
			echo "</div>\n";
		}

	} catch (PDOException $e) {
		echo "<span class='fail'>Cannot upgrade table: [<code>$query</code>] <pre>" . $e->GetMessage() . "</pre></span>\n";
	}
}

/* install initial values into database */
function fof_install_database_populate() {
	global $fof_connection;

	if (defined('USE_SQLITE')) {
		/* populate user level table */
		echo "<br>Populating " . FOF_USER_LEVELS_TABLE . "... \n";
		$entries = array();
		$entries[] = array('level' => 'admin');
		$entries[] = array('level' => 'user');

		$query_insert = "INSERT INTO " . FOF_USER_LEVELS_TABLE . " ( level ) VALUES ( :level )";
		$statement_insert = $fof_connection->prepare($query_insert);

		$query_check = "SELECT * FROM " . FOF_USER_LEVELS_TABLE . " WHERE level = :level";
		$statement_check = $fof_connection->prepare($query_check);

		foreach ($entries as $entry) {
			try {
				echo "<br><span>" . $entry['level'];
				$result_check = $statement_check->execute($entry);
				$rows_check = $statement_check->fetchAll();
			} catch (PDOException $e) {
				echo "Cannot check " . $entry['level'] . " [<code>$query_check</code>] <pre>" . $e->GetMessage() . "</pre>\n";
				exit();
			}
			if (count($rows_check)) {
				echo " <span class='pass'>exists</span>";
			} else {
				try {
					if (($result_insert = $statement_insert->execute($entry)) !== false) {
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
	$statement_insert = $fof_connection->prepare($query_insert);

	$query_check = "SELECT * FROM " . FOF_TAG_TABLE . " WHERE tag_name = :tag_name";
	$statement_check = $fof_connection->prepare($query_check);

	foreach ($entries as $entry) {
		try {
			echo "<br><span>" . $entry['tag_name'];
			$result_check = $statement_check->execute($entry);
			$rows_check = $statement_check->fetchAll();
		} catch (PDOException $e) {
			echo "Cannot check " . $entry['tag_name'] . " [<code>$query_check</code>] <pre>" . $e->GetMessage() . "</pre>\n";
			exit();
		}
		if (count($rows_check)) {
			echo " <span class='pass'>exists</span>";
		} else {
			try {
				if (($result_insert = $statement_insert->execute($entry)) !== false) {
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

function fof_install_user_exists($user = 'admin') {
	global $fof_connection;

	$query = "SELECT * FROM " . FOF_USER_TABLE . " WHERE user_name = " . $fof_connection->quote($user);

	try {
		$statement = $fof_connection->query($query);
		$rows = $statement->fetchAll();
	} catch (PDOException $e) {
		echo "Cannot select user: <pre>" . $e->GetMessage() . "</pre>";
		exit();
	}
	return (!empty($rows));
}

/* checks if an admin-level user exists */
function fof_install_user_level_exists($level = 'admin') {
	global $fof_connection;

	$query = "SELECT * FROM " . FOF_USER_TABLE . " WHERE user_level = " . $fof_connection->quote($level);
	try {
		$statement = $fof_connection->query($query);
		$rows = $statement->fetchAll();
	} catch (PDOException $e) {
		echo "Could not check for admin user: <pre>" . $e->GetMessage() . "</pre>";
		exit();
	}
	return (!empty($rows));
}
?>

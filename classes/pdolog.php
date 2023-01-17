<?php
/** Log PDO activity.
	This class extends the build-in PDO class to add a callback for logging all
	database queries and noting their elapsed times, as well as logging the
	values of any bound parameters in prepared statements.

	function callback($query_string, $elapsed_time, $result, $rows_affected=null, $parameters=null)

	TODO: Also allow for logging exceptions, via a second callback.
*/

class PDOLog extends PDO {
	public static $logfn = null;

	public function __construct($dsn, $username=null, $password=null, $driver_options=array()) {
		parent::__construct($dsn, $username, $password, $driver_options);
		$this->setAttribute(PDO::ATTR_STATEMENT_CLASS, array('PDOStatementLog', array($this)));
	}

	public function query($query, $fetchMode = null, ...$fetchModeArgs):PDOStatement|false {
		if (empty(self::$logfn))
			return parent::query($query);

		$time = 0.0 - microtime(true);
		$result = parent::query($query);
		$time += microtime(true);
		call_user_func(self::$logfn, $query, $time, ($result !== false));
		return $result;
	}

	public function exec($statement):int {
		if (empty(self::$logfn))
			return parent::exec($statement);

		$time = 0.0 - microtime(true);
		$rows_affected = parent::exec($statement);
		$time += microtime(true);
		call_user_func(self::$logfn, $statement, $time, ($rows_affected !== false), $rows_affected);
		return $rows_affected;
	}
}

class PDOStatementLog extends PDOStatement {
	protected $dbh;
	protected $parameters;
	protected function __construct($dbh) {
		$this->dbh = $dbh;
		$this->parameters = array();
	}

	public function bindParam($parameter, &$variable, $data_type=PDO::PARAM_STR, $length=0, $driver_options=null):bool {
		$this->parameters[$parameter] = $variable;
		parent::bindParam($parameter, $variable, $data_type, $length, $driver_options);
	}

	public function bindValue($parameter, $value, $data_type=PDO::PARAM_STR):bool {
		$this->parameters[$parameter] = $value;
		return parent::bindValue($parameter, $value, $data_type);
	}

	public function execute($input_parameters=null):bool {
		if (empty(PDOLog::$logfn))
			return parent::execute($input_parameters);

		$time = 0.0 - microtime(true);
		$result = parent::execute($input_parameters);
		$time += microtime(true);

		call_user_func(PDOLog::$logfn, $this->queryString, $time, $result, $this->rowCount(), is_null($input_parameters) ? $this->parameters : $input_parameters);
		return $result;
	}
}

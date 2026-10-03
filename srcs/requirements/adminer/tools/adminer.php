<?php
/** Adminer - Compact database management
* @link https://www.adminer.org/
* @author Jakub Vrana, https://www.vrana.cz/
* @copyright 2007 Jakub Vrana
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
* @version 5.4.2-dev
*/
// this is matched by compile.php

namespace Adminer;

?>
<?php
?>
<?php
const VERSION = "5.4.2-dev";

?>
<?php
error_reporting(24575); // all but E_DEPRECATED (overriding mysqli methods without types is deprecated)
set_error_handler(function ($errno, $errstr) {
	// "Undefined array key" mutes $_GET["q"] if there's no ?q=
	// "Undefined offset" and "Undefined index" are older messages for the same thing
	return !!preg_match('~^Undefined (array key|offset|index)~', $errstr);
}, E_WARNING | E_NOTICE); // warning since PHP 8.0

// this is matched by compile.php


// disable filter.default
$filter = !preg_match('~^(unsafe_raw)?$~', ini_get("filter.default"));
if ($filter || ini_get("filter.default_flags")) {
	foreach (array('_GET', '_POST', '_COOKIE', '_SERVER') as $val) {
		$unsafe = filter_input_array(constant("INPUT$val"), FILTER_UNSAFE_RAW);
		if ($unsafe) {
			$$val = $unsafe;
		}
	}
}

if (function_exists("mb_internal_encoding")) {
	mb_internal_encoding("8bit");
}

?>
<?php
// This file is used both in Adminer and Adminer Editor.

/** Get database connection
* @param ?Db $connection2 custom connection to use instead of the default
* @return Db
*/
function connection($connection2 = null) {
	// can be used in customization, Db::$instance is minified
	return ($connection2 ?: Db::$instance);
}

/** Get Adminer object
* @return Adminer|Plugins
*/
function adminer() {
	return Adminer::$instance;
}

/** Get Driver object */
function driver() {
	return Driver::$instance;
}

/** Connect to the database */
function connect() {
	$credentials = adminer()->credentials();
	$return = Driver::connect($credentials[0], $credentials[1], $credentials[2]);
	return (is_object($return) ? $return : null);
}

/** Unescape database identifier
* @param string $idf text inside ``
*/
function idf_unescape($idf) {
	if (!preg_match('~^[`\'"[]~', $idf)) {
		return $idf;
	}
	$last = substr($idf, -1);
	return str_replace($last . $last, $last, substr($idf, 1, -1));
}

/** Shortcut for connection()->quote($string) */
function q($string) {
	return connection()->quote($string);
}

/** Escape string to use inside '' */
function escape_string($val) {
	return substr(q($val), 1, -1);
}

/** Get a possibly missing item from a possibly missing array
* idx($row, $key) is better than $row[$key] ?? null because PHP will report error for undefined $row
* @param ?mixed[] $array
* @param array-key $key
* @param mixed $default
* @return mixed
*/
function idx($array, $key, $default = null) {
	return ($array && array_key_exists($key, $array) ? $array[$key] : $default);
}

/** Remove non-digits from a string; used instead of intval() to not corrupt big numbers
* @return numeric-string
*/
function number($val) {
	return preg_replace('~[^0-9]+~', '', $val);
}

/** Get regular expression to match numeric types */
function number_type() {
	return '((?<!o)int(?!er)|numeric|real|float|double|decimal|money)'; // not point, not interval
}

/** Disable magic_quotes_gpc
* @param list<array> $process e.g. [&$_GET, &$_POST, &$_COOKIE]
* @param bool $filter whether to leave values as is
* @return void modified in place
*/
function remove_slashes(array $process, $filter = false) {
	if (function_exists("get_magic_quotes_gpc") && get_magic_quotes_gpc()) {
		while (list($key, $val) = each($process)) {
			foreach ($val as $k => $v) {
				unset($process[$key][$k]);
				if (is_array($v)) {
					$process[$key][stripslashes($k)] = $v;
					$process[] = &$process[$key][stripslashes($k)];
				} else {
					$process[$key][stripslashes($k)] = ($filter ? $v : stripslashes($v));
				}
			}
		}
	}
}

/** Escape or unescape string to use inside form [] */
function bracket_escape($idf, $back = false) {
	// escape brackets inside name="x[]"
	static $trans = array(':' => ':1', ']' => ':2', '[' => ':3', '"' => ':4');
	return strtr($idf, ($back ? array_flip($trans) : $trans));
}

/** Check if connection has at least the given version
* @param string|float $version required version
* @param string|float $maria_db required MariaDB version
*/
function min_version($version, $maria_db = "", $connection2 = null) {
	$connection2 = connection($connection2);
	$server_info = $connection2->server_info;
	if ($maria_db && preg_match('~([\d.]+)-MariaDB~', $server_info, $match)) {
		$server_info = $match[1];
		$version = $maria_db;
	}
	return $version && version_compare($server_info, $version) >= 0;
}

/** Get connection charset */
function charset(Db $connection) {
	return (min_version("5.5.3", 0, $connection) ? "utf8mb4" : "utf8"); // SHOW CHARSET would require an extra query
}

/** Get INI boolean value */
function ini_bool($ini) {
	$val = ini_get($ini);
	return (preg_match('~^(on|true|yes)$~i', $val) || (int) $val); // boolean values set by php_value are strings
}

/** Get INI bytes value */
function ini_bytes($ini) {
	$val = ini_get($ini);
	switch (strtolower(substr($val, -1))) {
		case 'g':
			$val = (int) $val * 1024; // no break
		case 'm':
			$val = (int) $val * 1024; // no break
		case 'k':
			$val = (int) $val * 1024;
	}
	return $val;
}

/** Check if SID is necessary */
function sid() {
	static $return;
	if ($return === null) { // restart_session() defines SID
		$return = (SID && !($_COOKIE && ini_bool("session.use_cookies"))); // $_COOKIE - don't pass SID with permanent login
	}
	return $return;
}

/** Set password to session */
function set_password($vendor, $server, $username, $password) {
	$_SESSION["pwds"][$vendor][$server][$username] = ($_COOKIE["adminer_key"] && is_string($password)
		? array(encrypt_string($password, $_COOKIE["adminer_key"]))
		: $password
	);
}

/** Get password from session
* @return string|false|null null for missing password, false for expired password
*/
function get_password() {
	$return = get_session("pwds");
	if (is_array($return)) {
		$return = ($_COOKIE["adminer_key"]
			? decrypt_string($return[0], $_COOKIE["adminer_key"])
			: false
		);
	}
	return $return;
}

/** Get single value from database
* @return string|false false if error
*/
function get_val($query, $field = 0, $conn = null) {
	$conn = connection($conn);
	$result = $conn->query($query);
	if (!is_object($result)) {
		return false;
	}
	$row = $result->fetch_row();
	return ($row ? $row[$field] : false);
}

/** Get list of values from database
* @param array-key $column
* @return list<string>
*/
function get_vals($query, $column = 0) {
	$return = array();
	$result = connection()->query($query);
	if (is_object($result)) {
		while ($row = $result->fetch_row()) {
			$return[] = $row[$column];
		}
	}
	return $return;
}

/** Get keys from first column and values from second
* @return string[]
*/
function get_key_vals($query, $connection2 = null, $set_keys = true) {
	$connection2 = connection($connection2);
	$return = array();
	$result = $connection2->query($query);
	if (is_object($result)) {
		while ($row = $result->fetch_row()) {
			if ($set_keys) {
				$return[$row[0]] = $row[1];
			} else {
				$return[] = $row[0];
			}
		}
	}
	return $return;
}

/** Get all rows of result
* @return list<string[]> of associative arrays
*/
function get_rows($query, $connection2 = null, $error = "<p class='error'>") {
	$conn = connection($connection2);
	$return = array();
	$result = $conn->query($query);
	if (is_object($result)) { // can return true
		while ($row = $result->fetch_assoc()) {
			$return[] = $row;
		}
	} elseif (!$result && !$connection2 && $error && (defined('Adminer\PAGE_HEADER') || $error == "-- ")) {
		echo $error . error() . "\n";
	}
	return $return;
}

/** Find unique identifier of a row
* @param string[] $row
* @param Index[] $indexes
* @return string[]|void null if there is no unique identifier
*/
function unique_array($row, array $indexes) {
	foreach ($indexes as $index) {
		if (preg_match("~PRIMARY|UNIQUE~", $index["type"]) && !$index["partial"]) {
			$return = array();
			foreach ($index["columns"] as $key) {
				if (!isset($row[$key])) { // NULL is ambiguous
					continue 2;
				}
				$return[$key] = $row[$key];
			}
			return $return;
		}
	}
}

/** Escape column key used in where() */
function escape_key($key) {
	if (preg_match('(^([\w(]+)(' . str_replace("_", ".*", preg_quote(idf_escape("_"))) . ')([ \w)]+)$)', $key, $match)) { //! columns looking like functions
		return $match[1] . idf_escape(idf_unescape($match[2])) . $match[3]; //! SQL injection
	}
	return idf_escape($key);
}

/** Create SQL condition from parsed query string
* @param array{where:string[], null:list<string>} $where parsed query string
* @param Field[] $fields
*/
function where(array $where, array $fields = array()) {
	$return = array();
	foreach ((array) $where["where"] as $key => $val) {
		$key = bracket_escape($key, true); // true - back
		$column = escape_key($key);
		$field = idx($fields, $key, array());
		$field_type = $field["type"];
		$return[] = $column
			. (JUSH == "sql" && $field_type == "json" ? " = CAST(" . q($val) . " AS JSON)"
				: (JUSH == "pgsql" && preg_match('~^json~', $field_type) ? "::jsonb = " . q($val) . "::jsonb"
				: (JUSH == "sql" && is_numeric($val) && preg_match('~\.~', $val) ? " LIKE " . q($val) // LIKE because of floats but slow with ints
				: (JUSH == "mssql" && strpos($field_type, "datetime") === false ? " LIKE " . q(preg_replace('~[_%[]~', '[\0]', $val)) // LIKE because of text but it does not work with datetime
				: " = " . unconvert_field($field, q($val))))))
		; //! enum and set
		if (JUSH == "sql" && preg_match('~char|text~', $field_type) && preg_match("~[^ -@]~", $val)) { // not just [a-z] to catch non-ASCII characters
			$return[] = "$column = " . q($val) . " COLLATE " . charset(connection()) . "_bin";
		}
	}
	foreach ((array) $where["null"] as $key) {
		$return[] = escape_key($key) . " IS NULL";
	}
	return implode(" AND ", $return);
}

/** Create SQL condition from query string
* @param Field[] $fields
*/
function where_check($val, array $fields = array()) {
	parse_str($val, $check);
	remove_slashes(array(&$check));
	return where($check, $fields);
}

/** Create query string where condition from value
* @param int $i condition order
* @param string $column column identifier
*/
function where_link($i, $column, $value, $operator = "=") {
	return "&where%5B$i%5D%5Bcol%5D=" . urlencode($column) . "&where%5B$i%5D%5Bop%5D=" . urlencode(($value !== null ? $operator : "IS NULL")) . "&where%5B$i%5D%5Bval%5D=" . urlencode($value);
}

/** Get select clause for convertible fields
* @param mixed[] $columns only keys are used
* @param Field[] $fields
* @param list<string> $select
*/
function convert_fields(array $columns, array $fields, array $select = array()) {
	$return = "";
	foreach ($columns as $key => $val) {
		if ($select && !in_array(idf_escape($key), $select)) {
			continue;
		}
		$as = convert_field($fields[$key]);
		if ($as) {
			$return .= ", $as AS " . idf_escape($key);
		}
	}
	return $return;
}

/** Set cookie valid on current path
* @param int $lifetime number of seconds, 0 for session cookie, 2592000 - 30 days
*/
function cookie($name, $value, $lifetime = 2592000) {
	header(
		"Set-Cookie: $name=" . urlencode($value)
			. ($lifetime ? "; expires=" . gmdate("D, d M Y H:i:s", time() + $lifetime) . " GMT" : "")
			. "; path=" . preg_replace('~\?.*~', '', $_SERVER["REQUEST_URI"])
			. (HTTPS ? "; secure" : "")
			. "; HttpOnly; SameSite=lax",
		false
	);
}

/** Get settings stored in a cookie
* @return mixed[]
*/
function get_settings($cookie) {
	parse_str($_COOKIE[$cookie], $settings);
	return $settings;
}

/** Get setting stored in a cookie
* @param mixed $default
* @return mixed
*/
function get_setting($key, $cookie = "adminer_settings", $default = null) {
	return idx(get_settings($cookie), $key, $default);
}

/** Store settings to a cookie
* @param mixed[] $settings
*/
function save_settings(array $settings, $cookie = "adminer_settings") {
	$value = http_build_query($settings + get_settings($cookie));
	cookie($cookie, $value);
	$_COOKIE[$cookie] = $value;
}

/** Restart stopped session */
function restart_session() {
	if (!ini_bool("session.use_cookies") && (!function_exists('session_status') || session_status() == 1)) { // 1 - PHP_SESSION_NONE, session_status() available since PHP 5.4
		session_start();
	}
}

/** Stop session if possible */
function stop_session($force = false) {
	$use_cookies = ini_bool("session.use_cookies");
	if (!$use_cookies || $force) {
		session_write_close(); // improves concurrency if a user opens several pages at once, may be restarted later
		if ($use_cookies && @ini_set("session.use_cookies", '0') === false) { // @ - may be disabled
			session_start();
		}
	}
}

/** Get session variable for current server
* @return mixed
*/
function &get_session($key) {
	return $_SESSION[$key][DRIVER][SERVER][$_GET["username"]];
}

/** Set session variable for current server
* @param mixed $val
* @return mixed
*/
function set_session($key, $val) {
	$_SESSION[$key][DRIVER][SERVER][$_GET["username"]] = $val; // used also in auth.inc.php
}

/** Get authenticated URL */
function auth_url($vendor, $server, $username, $db = null) {
	$uri = remove_from_uri(implode("|", array_keys(SqlDriver::$drivers))
		. "|username|ext|"
		. ($db !== null ? "db|" : "")
		. ($vendor == 'mssql' || $vendor == 'pgsql' ? "" : "ns|") // we don't have access to support() here
		. session_name())
	;
	preg_match('~([^?]*)\??(.*)~', $uri, $match);
	return "$match[1]?"
		. (sid() ? SID . "&" : "")
		. ($vendor != "server" || $server != "" ? urlencode($vendor) . "=" . urlencode($server) . "&" : "")
		. ($_GET["ext"] ? "ext=" . urlencode($_GET["ext"]) . "&" : "")
		. "username=" . urlencode($username)
		. ($db != "" ? "&db=" . urlencode($db) : "")
		. ($match[2] ? "&$match[2]" : "")
	;
}

/** Find whether it is an AJAX request */
function is_ajax() {
	return ($_SERVER["HTTP_X_REQUESTED_WITH"] == "XMLHttpRequest");
}

/** Send Location header and exit
* @param ?string $location null to only set a message
*/
function redirect($location, $message = null) {
	if ($message !== null) {
		restart_session();
		$_SESSION["messages"][preg_replace('~^[^?]*~', '', ($location !== null ? $location : $_SERVER["REQUEST_URI"]))][] = $message;
	}
	if ($location !== null) {
		if ($location == "") {
			$location = ".";
		}
		header("Location: $location");
		exit;
	}
}

/** Execute query and redirect if successful
* @param bool $redirect
*/
function query_redirect($query, $location, $message, $redirect = true, $execute = true, $failed = false, $time = "") {
	if ($execute) {
		$start = microtime(true);
		$failed = !connection()->query($query);
		$time = format_time($start);
	}
	$sql = ($query ? adminer()->messageQuery($query, $time, $failed) : "");
	if ($failed) {
		adminer()->error .= error() . $sql . script("messagesPrint();") . "<br>";
		return false;
	}
	if ($redirect) {
		redirect($location, $message . $sql);
	}
	return true;
}

class Queries {
	/** @var string[] */ static $queries = array();
	static $start = 0;
}

/** Execute and remember query
* @param string $query end with ';' to use DELIMITER
* @return Result|bool
*/
function queries($query) {
	if (!Queries::$start) {
		Queries::$start = microtime(true);
	}
	Queries::$queries[] = (preg_match('~;$~', $query) ? "DELIMITER ;;\n$query;\nDELIMITER " : $query) . ";";
	return connection()->query($query);
}

/** Apply command to all array items
* @param list<string> $tables
* @param callable(string):string $escape
*/
function apply_queries($query, array $tables, $escape = 'Adminer\table') {
	foreach ($tables as $table) {
		if (!queries("$query " . $escape($table))) {
			return false;
		}
	}
	return true;
}

/** Redirect by remembered queries
* @param bool $redirect
*/
function queries_redirect($location, $message, $redirect) {
	$queries = implode("\n", Queries::$queries);
	$time = format_time(Queries::$start);
	return query_redirect($queries, $location, $message, $redirect, false, !$redirect, $time);
}

/** Format elapsed time
* @param float $start output of microtime(true)
* @return string HTML code
*/
function format_time($start) {
	return sprintf('%.3f s', max(0, microtime(true) - $start));
}

/** Get relative REQUEST_URI */
function relative_uri() {
	return str_replace(":", "%3a", preg_replace('~^[^?]*/([^?]*)~', '\1', $_SERVER["REQUEST_URI"]));
}

/** Remove parameter from query string */
function remove_from_uri($param = "") {
	return substr(preg_replace("~(?<=[?&])($param" . (SID ? "" : "|" . session_name()) . ")=[^&]*&~", '', relative_uri() . "&"), 0, -1);
}

/** Get file contents from $_FILES
* @return mixed int for error, string otherwise
*/
function get_file($key, $decompress = false, $delimiter = "") {
	$file = $_FILES[$key];
	if (!$file) {
		return null;
	}
	foreach ($file as $key => $val) {
		$file[$key] = (array) $val;
	}
	$return = '';
	foreach ($file["error"] as $key => $error) {
		if ($error) {
			return $error;
		}
		$name = $file["name"][$key];
		$tmp_name = $file["tmp_name"][$key];
		$content = file_get_contents(
			$decompress && preg_match('~\.gz$~', $name)
			? "compress.zlib://$tmp_name"
			: $tmp_name
		); //! may not be reachable because of open_basedir
		if ($decompress) {
			$start = substr($content, 0, 3);
			if (function_exists("iconv") && preg_match("~^\xFE\xFF|^\xFF\xFE~", $start)) { // not ternary operator to save memory
				$content = iconv("utf-16", "utf-8", $content);
			} elseif ($start == "\xEF\xBB\xBF") { // UTF-8 BOM
				$content = substr($content, 3);
			}
		}
		$return .= $content;
		if ($delimiter) {
			$return .= (preg_match("($delimiter\\s*\$)", $content) ? "" : $delimiter) . "\n\n";
		}
	}
	return $return;
}

/** Determine upload error */
function upload_error($error) {
	$max_size = ($error == UPLOAD_ERR_INI_SIZE ? ini_get("upload_max_filesize") : 0); // post_max_size is checked in index.php
	return ($error ? 'Unable to upload a file.' . ($max_size ? " " . sprintf('Maximum allowed file size is %sB.', $max_size) : "") : 'File does not exist.');
}

/** Create repeat pattern for preg */
function repeat_pattern($pattern, $length) {
	// fix for Compilation failed: number too big in {} quantifier
	return str_repeat("$pattern{0,65535}", $length / 65535) . "$pattern{0," . ($length % 65535) . "}"; // can create {0,0} which is OK
}

/** Check whether the string is in UTF-8 */
function is_utf8($val) {
	// don't print control chars except \t\r\n
	return (preg_match('~~u', $val) && !preg_match('~[\0-\x8\xB\xC\xE-\x1F]~', $val));
}

/** Format decimal number
* @param float|numeric-string $val
*/
function format_number($val) {
	return strtr(number_format($val, 0, ".", ','), preg_split('~~u', '0123456789', -1, PREG_SPLIT_NO_EMPTY));
}

/** Generate friendly URL */
function friendly_url($val) {
	// used for blobs and export
	return preg_replace('~\W~i', '-', $val);
}

/** Get status of a single table and fall back to name on error
* @return TableStatus one element from table_status()
*/
function table_status1($table, $fast = false) {
	$return = table_status($table, $fast);
	return ($return ? reset($return) : array("Name" => $table));
}

/** Find out foreign keys for each column
* @return list<ForeignKey>[] [$col => []]
*/
function column_foreign_keys($table) {
	$return = array();
	foreach (adminer()->foreignKeys($table) as $foreign_key) {
		foreach ($foreign_key["source"] as $val) {
			$return[$val][] = $foreign_key;
		}
	}
	return $return;
}

/** Compute fields() from $_POST edit data; used by Mongo and SimpleDB
* @return Field[] same as fields()
*/
function fields_from_edit() {
	$return = array();
	foreach ((array) $_POST["field_keys"] as $key => $val) {
		if ($val != "") {
			$val = bracket_escape($val);
			$_POST["function"][$val] = $_POST["field_funs"][$key];
			$_POST["fields"][$val] = $_POST["field_vals"][$key];
		}
	}
	foreach ((array) $_POST["fields"] as $key => $val) {
		$name = bracket_escape($key, true); // true - back
		$return[$name] = array(
			"field" => $name,
			"privileges" => array("insert" => 1, "update" => 1, "where" => 1, "order" => 1),
			"null" => 1,
			"auto_increment" => ($key == driver()->primary),
		);
	}
	return $return;
}

/** Send headers for export
* @return string extension
*/
function dump_headers($identifier, $multi_table = false) {
	$return = adminer()->dumpHeaders($identifier, $multi_table);
	$output = $_POST["output"];
	if ($output != "text") {
		header("Content-Disposition: attachment; filename=" . adminer()->dumpFilename($identifier) . ".$return" . ($output != "file" && preg_match('~^[0-9a-z]+$~', $output) ? ".$output" : ""));
	}
	session_write_close();
	if (!ob_get_level()) {
		ob_start(null, 4096);
	}
	ob_flush();
	flush();
	return $return;
}

/** Print CSV row
* @param string[] $row
*/
function dump_csv(array $row) {
	foreach ($row as $key => $val) {
		if (preg_match('~["\n,;\t]|^0.|\.\d*0$~', $val) || $val === "") {
			$row[$key] = '"' . str_replace('"', '""', $val) . '"';
		}
	}
	echo implode(($_POST["format"] == "csv" ? "," : ($_POST["format"] == "tsv" ? "\t" : ";")), $row) . "\r\n";
}

/** Apply SQL function
* @param string $column escaped column identifier
*/
function apply_sql_function($function, $column) {
	return ($function ? ($function == "unixepoch" ? "DATETIME($column, '$function')" : ($function == "count distinct" ? "COUNT(DISTINCT " : strtoupper("$function(")) . "$column)") : $column);
}

/** Get path of the temporary directory */
function get_temp_dir() {
	$return = ini_get("upload_tmp_dir"); // session_save_path() may contain other storage path
	if (!$return) {
		if (function_exists('sys_get_temp_dir')) {
			$return = sys_get_temp_dir();
		} else {
			$filename = @tempnam("", ""); // @ - temp directory can be disabled by open_basedir
			if (!$filename) {
				return '';
			}
			$return = dirname($filename);
			unlink($filename);
		}
	}
	return $return;
}

/** Open and exclusively lock a file
* @return resource|void null for error
*/
function file_open_lock($filename) {
	if (is_link($filename)) {
		return; // https://cwe.mitre.org/data/definitions/61.html
	}
	$fp = @fopen($filename, "c+"); // @ - may not be writable
	if (!$fp) {
		return;
	}
	@chmod($filename, 0660); // @ - may not be permitted
	if (!flock($fp, LOCK_EX)) {
		fclose($fp);
		return;
	}
	return $fp;
}

/** Write and unlock a file
* @param resource $fp
*/
function file_write_unlock($fp, $data) {
	rewind($fp);
	fwrite($fp, $data);
	ftruncate($fp, strlen($data));
	file_unlock($fp);
}

/** Unlock and close a file
* @param resource $fp
*/
function file_unlock($fp) {
	flock($fp, LOCK_UN);
	fclose($fp);
}

/** Get first element of an array
* @param mixed[] $array
* @return mixed if not found
*/
function first(array $array) {
	// reset(f()) triggers a notice
	return reset($array);
}

/** Read password from file adminer.key in temporary directory or create one
* @return string '' if the file can not be created
*/
function password_file($create) {
	$filename = get_temp_dir() . "/adminer.key";
	if (!$create && !file_exists($filename)) {
		return '';
	}
	$fp = file_open_lock($filename);
	if (!$fp) {
		return '';
	}
	$return = stream_get_contents($fp);
	if (!$return) {
		$return = rand_string();
		file_write_unlock($fp, $return);
	} else {
		file_unlock($fp);
	}
	return $return;
}

/** Get a random string
* @return string 32 hexadecimal characters
*/
function rand_string() {
	return md5(uniqid(strval(mt_rand()), true));
}

/** Format value to use in select
* @param string|string[]|list<string[]> $val
* @param array{type: string} $field
* @param ?numeric-string $text_length
* @return string HTML
*/
function select_value($val, $link, array $field, $text_length) {
	if (is_array($val)) {
		$return = "";
		if (array_filter($val, 'is_array') == array_values($val)) { // list of arrays
			$keys = array();
			foreach ($val as $v) {
				$keys += array_fill_keys(array_keys($v), null);
			}
			foreach (array_keys($keys) as $k) {
				$return .= "<th>" . h($k);
			}
			foreach ($val as $v) {
				$return .= "<tr>";
				foreach (array_merge($keys, $v) as $v2) {
					$return .= "<td>" . select_value($v2, $link, $field, $text_length);
				}
			}
		} else {
			foreach ($val as $k => $v) {
				$return .= "<tr>"
					. ($val != array_values($val) ? "<th>" . h($k) : "")
					. "<td>" . select_value($v, $link, $field, $text_length)
				;
			}
		}
		return "<table>$return</table>";
	}
	if (!$link) {
		$link = adminer()->selectLink($val, $field);
	}
	if ($link === null) {
		if (is_mail($val)) {
			$link = "mailto:$val";
		}
		if (is_url($val)) {
			$link = $val; // IE 11 and all modern browsers hide referrer
		}
	}
	$return = adminer()->editVal($val, $field);
	if ($return !== null) {
		if (!is_utf8($return)) {
			$return = "\0"; // htmlspecialchars of binary data returns an empty string
		} elseif ($text_length != "" && is_shortable($field)) {
			$return = shorten_utf8($return, max(0, +$text_length)); // usage of LEFT() would reduce traffic but complicate query - expected average speedup: .001 s VS .01 s on local network
		} else {
			$return = h($return);
		}
	}
	return adminer()->selectVal($return, $link, $field, $val);
}

/** Check whether the field type is blob or equivalent
* @param array{type: string} $field
*/
function is_blob(array $field) {
	return preg_match('~blob|bytea|raw|file~', $field["type"]) && !in_array($field["type"], idx(driver()->structuredTypes(), 'User types', array()));
}

/** Check whether the string is e-mail address */
function is_mail($email) {
	$atom = '[-a-z0-9!#$%&\'*+/=?^_`{|}~]'; // characters of local-name
	$domain = '[a-z0-9]([-a-z0-9]{0,61}[a-z0-9])'; // one domain component
	$pattern = "$atom+(\\.$atom+)*@($domain?\\.)+$domain";
	return is_string($email) && preg_match("(^$pattern(,\\s*$pattern)*\$)i", $email);
}

/** Check whether the string is URL address */
function is_url($string) {
	$domain = '[a-z0-9]([-a-z0-9]{0,61}[a-z0-9])'; // one domain component //! IDN
	return preg_match("~^((https?):)?//($domain?\\.)+$domain(:\\d+)?(/.*)?(\\?.*)?(#.*)?\$~i", $string); //! restrict path, query and fragment characters
}

/** Check if field should be shortened
* @param array{type: string} $field
*/
function is_shortable(array $field) {
	return preg_match('~char|text|json|lob|geometry|point|linestring|polygon|string|bytea|hstore~', $field["type"]);
}

/** Split server into host and (port or socket)
* @return array{0: string, 1: string}
*/
function host_port($server) {
	return (preg_match('~^(\[(.+)]|([^:]+)):([^:]+)$~', $server, $match) // [a:b] - IPv6
		? array($match[2] . $match[3], $match[4])
		: array($server, '')
	);
}

/** Get query to compute number of found rows
* @param list<string> $where
* @param list<string> $group
*/
function count_rows($table, array $where, $is_group, array $group) {
	$query = " FROM " . table($table) . ($where ? " WHERE " . implode(" AND ", $where) : "");
	return ($is_group && (JUSH == "sql" || count($group) == 1)
		? "SELECT COUNT(DISTINCT " . implode(", ", $group) . ")$query"
		: "SELECT COUNT(*)" . ($is_group ? " FROM (SELECT 1$query GROUP BY " . implode(", ", $group) . ") x" : $query)
	);
}

/** Run query which can be killed by AJAX call after timing out
* @return string[]
*/
function slow_query($query) {
	$db = adminer()->database();
	$timeout = adminer()->queryTimeout();
	$slow_query = driver()->slowQuery($query, $timeout);
	$connection2 = null;
	if (!$slow_query && support("kill")) {
		$connection2 = connect();
		if ($connection2 && ($db == "" || $connection2->select_db($db))) {
			$kill = get_val(connection_id(), 0, $connection2); // MySQL and MySQLi can use thread_id but it's not in PDO_MySQL
			echo script("const timeout = setTimeout(() => { ajax('" . js_escape(ME) . "script=kill', function () {}, 'kill=$kill&token=" . get_token() . "'); }, 1000 * $timeout);");
		}
	}
	ob_flush();
	flush();
	$return = @get_key_vals(($slow_query ?: $query), $connection2, false); // @ - may be killed
	if ($connection2) {
		echo script("clearTimeout(timeout);");
		ob_flush();
		flush();
	}
	return $return;
}

/** Generate BREACH resistant CSRF token */
function get_token() {
	$rand = rand(1, 1e6);
	return ($rand ^ $_SESSION["token"]) . ":$rand";
}

/** Verify if supplied CSRF token is valid */
function verify_token() {
	list($token, $rand) = explode(":", $_POST["token"]);
	return ($rand ^ $_SESSION["token"]) == $token;
}

// used in compiled version
function lzw_decompress($binary) {
	// convert binary string to codes
	$dictionary_count = 256;
	$bits = 8; // ceil(log($dictionary_count, 2))
	$codes = array();
	$rest = 0;
	$rest_length = 0;
	for ($i=0; $i < strlen($binary); $i++) {
		$rest = ($rest << 8) + ord($binary[$i]);
		$rest_length += 8;
		if ($rest_length >= $bits) {
			$rest_length -= $bits;
			$codes[] = $rest >> $rest_length;
			$rest &= (1 << $rest_length) - 1;
			$dictionary_count++;
			if ($dictionary_count >> $bits) {
				$bits++;
			}
		}
	}
	// decompression
	/** @var list<?string> */
	$dictionary = range("\0", "\xFF");
	$return = "";
	$word = "";
	foreach ($codes as $i => $code) {
		$element = $dictionary[$code];
		if (!isset($element)) {
			$element = $word . $word[0];
		}
		$return .= $element;
		if ($i) {
			$dictionary[] = $word . $element[0];
		}
		$word = $element;
	}
	return $return;
}

?>
<?php
/** Return <script> element */
function script($source, $trailing = "\n") {
	return "<script" . nonce() . ">$source</script>$trailing";
}

/** Return <script src> element */
function script_src($url, $defer = false) {
	return "<script src='" . h($url) . "'" . nonce() . ($defer ? " defer" : "") . "></script>\n";
}

/** Get a nonce="" attribute with CSP nonce */
function nonce() {
	return ' nonce="' . get_nonce() . '"';
}

/** Get <input type="hidden">
* @param string|int $value
* @return string HTML
*/
function input_hidden($name, $value = "") {
	return "<input type='hidden' name='" . h($name) . "' value='" . h($value) . "'>\n";
}

/** Get CSRF <input type="hidden" name="token">
* @return string HTML
*/
function input_token() {
	return input_hidden("token", get_token());
}

/** Get a target="_blank" attribute */
function target_blank() {
	return ' target="_blank" rel="noreferrer noopener"';
}

/** Escape for HTML */
function h($string) {
	return str_replace("\0", "&#0;", htmlspecialchars($string, ENT_QUOTES, 'utf-8'));
}

/** Convert \n to <br> */
function nl_br($string) {
	return str_replace("\n", "<br>", $string); // nl2br() uses XHTML before PHP 5.3
}

/** Generate HTML checkbox
* @param string|int $value
*/
function checkbox($name, $value, $checked, $label = "", $onclick = "", $class = "", $labelled_by = "") {
	$return = "<input type='checkbox' name='$name' value='" . h($value) . "'"
		. ($checked ? " checked" : "")
		. ($labelled_by ? " aria-labelledby='$labelled_by'" : "")
		. ">"
		. ($onclick ? script("qsl('input').onclick = function () { $onclick };", "") : "")
	;
	return ($label != "" || $class ? "<label" . ($class ? " class='$class'" : "") . ">$return" . h($label) . "</label>" : $return);
}

/** Generate list of HTML options
* @param string[]|string[][] $options array of strings or arrays (creates optgroup)
* @param mixed $selected
* @param bool $use_keys always use array keys for value="", otherwise only string keys are used
*/
function optionlist($options, $selected = null, $use_keys = false) {
	$return = "";
	foreach ($options as $k => $v) {
		$opts = array($k => $v);
		if (is_array($v)) {
			$return .= '<optgroup label="' . h($k) . '">';
			$opts = $v;
		}
		foreach ($opts as $key => $val) {
			$return .= '<option'
				. ($use_keys || is_string($key) ? ' value="' . h($key) . '"' : '')
				. ($selected !== null && ($use_keys || is_string($key) ? (string) $key : $val) === $selected ? ' selected' : '')
				. '>' . h($val)
			;
		}
		if (is_array($v)) {
			$return .= '</optgroup>';
		}
	}
	return $return;
}

/** Generate HTML <select>
* @param string[] $options
*/
function html_select($name, array $options, $value = "", $onchange = "", $labelled_by = "") {
	static $label = 0;
	$label_option = "";
	if (!$labelled_by && substr($options[""], 0, 1) == "(") {
		$label++;
		$labelled_by = "label-$label";
		$label_option = "<option value='' id='$labelled_by'>" . h($options[""]);
		unset($options[""]);
	}
	return "<select name='" . h($name) . "'"
		. ($labelled_by ? " aria-labelledby='$labelled_by'" : "")
		. ">" . $label_option . optionlist($options, $value) . "</select>"
		. ($onchange ? script("qsl('select').onchange = function () { $onchange };", "") : "")
	;
}

/** Generate HTML radio list
* @param string[] $options
*/
function html_radios($name, array $options, $value = "", $separator = "") {
	$return = "";
	foreach ($options as $key => $val) {
		$return .= "<label><input type='radio' name='" . h($name) . "' value='" . h($key) . "'" . ($key == $value ? " checked" : "") . ">" . h($val) . "</label>$separator";
	}
	return $return;
}

/** Get onclick confirmation */
function confirm($message = "", $selector = "qsl('input')") {
	return script("$selector.onclick = () => confirm('" . ($message ? js_escape($message) : 'Are you sure?') . "');", "");
}

/** Print header for hidden fieldset (close by </div></fieldset>)
* @param bool $visible
*/
function print_fieldset($id, $legend, $visible = false) {
	echo "<fieldset><legend>";
	echo "<a href='#fieldset-$id'>$legend</a>";
	echo script("qsl('a').onclick = partial(toggle, 'fieldset-$id');", "");
	echo "</legend>";
	echo "<div id='fieldset-$id'" . ($visible ? "" : " class='hidden'") . ">\n";
}

/** Return class='active' if $bold is true */
function bold($bold, $class = "") {
	return ($bold ? " class='active $class'" : ($class ? " class='$class'" : ""));
}

/** Escape string for JavaScript apostrophes */
function js_escape($string) {
	return addcslashes($string, "\r\n'\\/"); // slash for <script>
}

/** Generate page number for pagination */
function pagination($page, $current) {
	return " " . ($page == $current
		? $page + 1
		: '<a href="' . h(remove_from_uri("page") . ($page ? "&page=$page" . ($_GET["next"] ? "&next=" . urlencode($_GET["next"]) : "") : "")) . '">' . ($page + 1) . "</a>"
	);
}

/** Print hidden fields
* @param mixed[] $process
* @param list<string> $ignore
*/
function hidden_fields(array $process, array $ignore = array(), $prefix = '') {
	$return = false;
	foreach ($process as $key => $val) {
		if (!in_array($key, $ignore)) {
			if (is_array($val)) {
				hidden_fields($val, array(), $key);
			} else {
				$return = true;
				echo input_hidden(($prefix ? $prefix . "[$key]" : $key), $val);
			}
		}
	}
	return $return;
}

/** Print hidden fields for GET forms */
function hidden_fields_get() {
	echo (sid() ? input_hidden(session_name(), session_id()) : '');
	echo (SERVER !== null ? input_hidden(DRIVER, SERVER) : "");
	echo input_hidden("username", $_GET["username"]);
}

/** Get <input type='file'> */
function file_input($input) {
	$max_file_uploads = "max_file_uploads";
	$max_file_uploads_value = ini_get($max_file_uploads);
	$upload_max_filesize = "upload_max_filesize";
	$upload_max_filesize_value = ini_get($upload_max_filesize);
	return (ini_bool("file_uploads")
		? $input . script("qsl('input[type=\"file\"]').onchange = partialArg(fileChange, "
				. "$max_file_uploads_value, '" . sprintf('Increase %s.', "$max_file_uploads = $max_file_uploads_value") . "', " // ignore post_max_size because it is for all form fields together and bytes computing would be necessary
				. ini_bytes("upload_max_filesize") . ", '" . sprintf('Increase %s.', "$upload_max_filesize = $upload_max_filesize_value") . "')")
		: 'File uploads are disabled.'
	);
}

/** Print enum or set input field
* @param 'radio'|'checkbox' $type
* @param Field $field
* @param string|string[]|false|null $value false means original value
*/
function enum_input($type, $attrs, array $field, $value, $empty = "") {
	preg_match_all("~'((?:[^']|'')*)'~", $field["length"], $matches);
	$prefix = ($field["type"] == "enum" ? "val-" : "");
	$checked = (is_array($value) ? in_array("null", $value) : $value === null);
	$return = ($field["null"] && $prefix ? "<label><input type='$type'$attrs value='null'" . ($checked ? " checked" : "") . "><i>$empty</i></label>" : "");
	foreach ($matches[1] as $val) {
		$val = stripcslashes(str_replace("''", "'", $val));
		$checked = (is_array($value) ? in_array($prefix . $val, $value) : $value === $val);
		$return .= " <label><input type='$type'$attrs value='" . h($prefix . $val) . "'" . ($checked ? ' checked' : '') . '>' . h(adminer()->editVal($val, $field)) . '</label>';
	}
	return $return;
}

/** Print edit input field
* @param Field|RoutineField $field
* @param mixed $value
*/
function input(array $field, $value, $function, $autofocus = false) {
	$name = h(bracket_escape($field["field"]));
	echo "<td class='function'>";
	if (is_array($value) && !$function) {
		$value = json_encode($value, 128 | 64 | 256); // 128 - JSON_PRETTY_PRINT, 64 - JSON_UNESCAPED_SLASHES, 256 - JSON_UNESCAPED_UNICODE available since PHP 5.4
		$function = "json";
	}
	$reset = (JUSH == "mssql" && $field["auto_increment"]);
	if ($reset && !$_POST["save"]) {
		$function = null;
	}
	$functions = (isset($_GET["select"]) || $reset ? array("orig" => 'original') : array()) + adminer()->editFunctions($field);
	$enums = driver()->enumLength($field);
	if ($enums) {
		$field["type"] = "enum";
		$field["length"] = $enums;
	}
	$disabled = stripos($field["default"], "GENERATED ALWAYS AS ") === 0 ? " disabled" : "";
	$attrs = " name='fields[$name]" . ($field["type"] == "enum" || $field["type"] == "set" ? "[]" : "") . "'$disabled" . ($autofocus ? " autofocus" : "");
	echo driver()->unconvertFunction($field) . " ";
	$table = $_GET["edit"] ?: $_GET["select"];
	if ($field["type"] == "enum") {
		echo h($functions[""]) . "<td>" . adminer()->editInput($table, $field, $attrs, $value);
	} else {
		$has_function = (in_array($function, $functions) || isset($functions[$function]));
		echo (count($functions) > 1
			? "<select name='function[$name]'$disabled>" . optionlist($functions, $function === null || $has_function ? $function : "") . "</select>"
				. on_help("event.target.value.replace(/^SQL\$/, '')", 1)
				. script("qsl('select').onchange = functionChange;", "")
			: h(reset($functions))
		) . '<td>';
		$input = adminer()->editInput($table, $field, $attrs, $value); // usage in call is without a table
		if ($input != "") {
			echo $input;
		} elseif (preg_match('~bool~', $field["type"])) {
			echo "<input type='hidden'$attrs value='0'>"
				. "<input type='checkbox'" . (preg_match('~^(1|t|true|y|yes|on)$~i', $value) ? " checked='checked'" : "") . "$attrs value='1'>";
		} elseif ($field["type"] == "set") {
			echo enum_input("checkbox", $attrs, $field, (is_string($value) ? explode(",", $value) : $value));
		} elseif (is_blob($field) && ini_bool("file_uploads")) {
			echo "<input type='file' name='fields-$name'>";
		} elseif ($function == "json" || preg_match('~^jsonb?$~', $field["type"])) {
			echo "<textarea$attrs cols='50' rows='12' class='jush-js'>" . h($value == '' || (JUSH == "pgsql" && $field["type"] == "json")
				? $value
				: json_encode(json_decode($value), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
				. '</textarea>';
		} elseif (($text = preg_match('~text|lob|memo~i', $field["type"])) || preg_match("~\n~", $value)) {
			if ($text && JUSH != "sqlite") {
				$attrs .= " cols='50' rows='12'";
			} else {
				$rows = min(12, substr_count($value, "\n") + 1);
				$attrs .= " cols='30' rows='$rows'";
			}
			echo "<textarea$attrs>" . h($value) . '</textarea>';
		} else {
			// int(3) is only a display hint
			$types = driver()->types();
			$maxlength = (!preg_match('~int~', $field["type"]) && preg_match('~^(\d+)(,(\d+))?$~', $field["length"], $match)
				? ((preg_match("~binary~", $field["type"]) ? 2 : 1) * $match[1] + ($match[3] ? 1 : 0) + ($match[2] && !$field["unsigned"] ? 1 : 0))
				: ($types[$field["type"]] ? $types[$field["type"]] + ($field["unsigned"] ? 0 : 1) : 0)
			);
			if (JUSH == 'sql' && min_version(5.6) && preg_match('~time~', $field["type"])) {
				$maxlength += 7; // microtime
			}
			// type='date' and type='time' display localized value which may be confusing, type='datetime' uses 'T' as date and time separator
			echo "<input"
				. ((!$has_function || $function === "") && preg_match('~(?<!o)int(?!er)~', $field["type"]) && !preg_match('~\[\]~', $field["full_type"]) ? " type='number'" : "")
				. " value='" . h($value) . "'" . ($maxlength ? " data-maxlength='$maxlength'" : "")
				. (preg_match('~char|binary~', $field["type"]) && $maxlength > 20 ? " size='" . ($maxlength > 99 ? 60 : 40) . "'" : "")
				. "$attrs>"
			;
		}
		echo adminer()->editHint($table, $field, $value);
		// skip 'original'
		$first = 0;
		foreach ($functions as $key => $val) {
			if ($key === "" || !$val) {
				break;
			}
			$first++;
		}
		if ($first && count($functions) > 1) {
			echo script("qsl('td').oninput = partial(skipOriginal, $first);");
		}
	}
}

/** Process edit input field
* @param Field|RoutineField $field
* @return mixed false to leave the original value
*/
function process_input(array $field) {
	if (stripos($field["default"], "GENERATED ALWAYS AS ") === 0) {
		return;
	}
	$idf = bracket_escape($field["field"]);
	$function = idx($_POST["function"], $idf);
	$value = idx($_POST["fields"], $idf);
	if ($field["type"] == "enum" || driver()->enumLength($field)) {
		$value = $value[0];
		if ($value == "orig") {
			return false;
		}
		if ($value == "null") {
			return "NULL";
		}
		$value = substr($value, 4); // 4 - strlen("val-")
	}
	if ($field["auto_increment"] && $value == "") {
		return null;
	}
	if ($function == "orig") {
		return (preg_match('~^CURRENT_TIMESTAMP~i', $field["on_update"]) ? idf_escape($field["field"]) : false);
	}
	if ($function == "NULL") {
		return "NULL";
	}
	if ($field["type"] == "set") {
		$value = implode(",", (array) $value);
	}
	if ($function == "json") {
		$function = "";
		$value = json_decode($value, true);
		if (!is_array($value)) {
			return false; //! report errors
		}
		return $value;
	}
	if (is_blob($field) && ini_bool("file_uploads")) {
		$file = get_file("fields-$idf");
		if (!is_string($file)) {
			return false; //! report errors
		}
		return driver()->quoteBinary($file);
	}
	return adminer()->processInput($field, $value, $function);
}

/** Print results of search in all tables
* @uses $_GET["where"][0]
* @uses $_POST["tables"]
*/
function search_tables() {
	$_GET["where"][0]["val"] = $_POST["query"];
	$sep = "<ul>\n";
	foreach (table_status('', true) as $table => $table_status) {
		$name = adminer()->tableName($table_status);
		if (isset($table_status["Engine"]) && $name != "" && (!$_POST["tables"] || in_array($table, $_POST["tables"]))) {
			$result = connection()->query("SELECT" . limit("1 FROM " . table($table), " WHERE " . implode(" AND ", adminer()->selectSearchProcess(fields($table), array())), 1));
			if (!$result || $result->fetch_row()) {
				$print = "<a href='" . h(ME . "select=" . urlencode($table) . "&where[0][op]=" . urlencode($_GET["where"][0]["op"]) . "&where[0][val]=" . urlencode($_GET["where"][0]["val"])) . "'>$name</a>";
				echo "$sep<li>" . ($result ? $print : "<p class='error'>$print: " . error()) . "\n";
				$sep = "";
			}
		}
	}
	echo ($sep ? "<p class='message'>" . 'No tables.' : "</ul>") . "\n";
}

/** Return events to display help on mouse over
* @param string $command JS expression
* @param int $side 0 top, 1 left
*/
function on_help($command, $side = 0) {
	return script("mixin(qsl('select, input'), {onmouseover: function (event) { helpMouseover.call(this, event, $command, $side) }, onmouseout: helpMouseout});", "");
}

/** Print edit data form
* @param Field[] $fields
* @param mixed $row
*/
function edit_form($table, array $fields, $row, $update, $error = '') {
	$table_name = adminer()->tableName(table_status1($table, true));
	page_header(
		($update ? 'Edit' : 'Insert'),
		$error,
		array("select" => array($table, $table_name)),
		$table_name
	);
	adminer()->editRowPrint($table, $fields, $row, $update);
	if ($row === false) {
		echo "<p class='error'>" . 'No rows.' . "\n";
		return;
	}
	echo "<form action='' method='post' enctype='multipart/form-data' id='form'>\n";
	if (!$fields) {
		echo "<p class='error'>" . 'You have no privileges to update this table.' . "\n";
	} else {
		echo "<table class='layout'>" . script("qsl('table').onkeydown = editingKeydown;");
		$autofocus = !$_POST;
		foreach ($fields as $name => $field) {
			echo "<tr><th>" . adminer()->fieldName($field);
			$default = idx($_GET["set"], bracket_escape($name));
			if ($default === null) {
				$default = $field["default"];
				if ($field["type"] == "bit" && preg_match("~^b'([01]*)'\$~", $default, $regs)) {
					$default = $regs[1];
				}
				if (JUSH == "sql" && preg_match('~binary~', $field["type"])) {
					$default = bin2hex($default); // same as UNHEX
				}
			}
			$value = ($row !== null
				? ($row[$name] != "" && JUSH == "sql" && preg_match("~enum|set~", $field["type"]) && is_array($row[$name])
					? implode(",", $row[$name])
					: (is_bool($row[$name]) ? +$row[$name] : $row[$name])
				)
				: (!$update && $field["auto_increment"]
					? ""
					: (isset($_GET["select"]) ? false : $default)
				)
			);
			if (!$_POST["save"] && is_string($value)) {
				$value = adminer()->editVal($value, $field);
			}
			$function = ($_POST["save"]
				? idx($_POST["function"], $name, "")
				: ($update && preg_match('~^CURRENT_TIMESTAMP~i', $field["on_update"])
					? "now"
					: ($value === false ? null : ($value !== null ? '' : 'NULL'))
				)
			);
			if (!$_POST && !$update && $value == $field["default"] && preg_match('~^[\w.]+\(~', $value)) {
				$function = "SQL";
			}
			if (preg_match("~time~", $field["type"]) && preg_match('~^CURRENT_TIMESTAMP~i', $value)) {
				$value = "";
				$function = "now";
			}
			if ($field["type"] == "uuid" && $value == "uuid()") {
				$value = "";
				$function = "uuid";
			}
			if ($autofocus !== false) {
				$autofocus = ($field["auto_increment"] || $function == "now" || $function == "uuid" ? null : true); // null - don't autofocus this input but check the next one
			}
			input($field, $value, $function, $autofocus);
			if ($autofocus) {
				$autofocus = false;
			}
			echo "\n";
		}
		if (!support("table") && !fields($table)) {
			echo "<tr>"
				. "<th><input name='field_keys[]'>"
				. script("qsl('input').oninput = fieldChange;")
				. "<td class='function'>" . html_select("field_funs[]", adminer()->editFunctions(array("null" => isset($_GET["select"]))))
				. "<td><input name='field_vals[]'>"
				. "\n"
			;
		}
		echo "</table>\n";
	}
	echo "<p>\n";
	if ($fields) {
		echo "<input type='submit' value='" . 'Save' . "'>\n";
		if (!isset($_GET["select"])) {
			echo "<input type='submit' name='insert' value='" . ($update
				? 'Save and continue edit'
				: 'Save and insert next'
			) . "' title='Ctrl+Shift+Enter'>\n";
			echo ($update ? script("qsl('input').onclick = function () { return !ajaxForm(this.form, '" . 'Saving' . "…', this); };") : "");
		}
	}
	echo ($update ? "<input type='submit' name='delete' value='" . 'Delete' . "'>" . confirm() . "\n" : "");
	if (isset($_GET["select"])) {
		hidden_fields(array("check" => (array) $_POST["check"], "clone" => $_POST["clone"], "all" => $_POST["all"]));
	}
	echo input_hidden("referer", (isset($_POST["referer"]) ? $_POST["referer"] : $_SERVER["HTTP_REFERER"]));
	echo input_hidden("save", 1);
	echo input_token();
	echo "</form>\n";
}

/** Shorten UTF-8 string
* @return string escaped string with appended ...
*/
function shorten_utf8($string, $length = 80, $suffix = "") {
	if (!preg_match("(^(" . repeat_pattern("[\t\r\n -\x{10FFFF}]", $length) . ")($)?)u", $string, $match)) { // ~s causes trash in $match[2] under some PHP versions, (.|\n) is slow
		preg_match("(^(" . repeat_pattern("[\t\r\n -~]", $length) . ")($)?)", $string, $match);
	}
	return h($match[1]) . $suffix . (isset($match[2]) ? "" : "<i>…</i>");
}

/** Get button with icon */
function icon($icon, $name, $html, $title) {
	return "<button type='submit' name='$name' title='" . h($title) . "' class='icon icon-$icon'><span>$html</span></button>";
}


// used only in compiled file
if (isset($_GET["file"])) {
	?>
<?php
if (substr(VERSION, -4) != '-dev') {
	if ($_SERVER["HTTP_IF_MODIFIED_SINCE"]) {
		header("HTTP/1.1 304 Not Modified");
		exit;
	}
	header("Expires: " . gmdate("D, d M Y H:i:s", time() + 365*24*60*60) . " GMT");
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
	header("Cache-Control: immutable");
}

@ini_set("zlib.output_compression", '1'); // @ - may be disabled

if ($_GET["file"] == "default.css") {
	header("Content-Type: text/css; charset=utf-8");
	echo lzw_decompress("h:M��h��g�б���\"P�i��m��cQCa��	2ó��d<��f�a��:;NB�q�R;1Lf�9��u7&)�l;3�����J/��CQX�r2M�a�i0���)��e:LuÝh�-9��23l��i7��m�Zw4���њ<-��̴�!�U,��Fé�vt2��S,��a�҇F�VX�a�Nq�)�-���ǜh�:n5���9�Y�;j��-��_�9kr��ٓ;.�tTq�o�0�����{��y��\r�Hn��GS��Zh��;�i^�ux�WΒC@����k��=��b����/A��0�+�(���l���\\��x�:\r��b8\0�0!\0F�\nB�͎�(�3�\r\\�����Ȅa���'I�|�(i�\n�\r���4O�g@�4�C��@@�!�QB��	°�c��¯�q,\r1Eh��&2PZ���iG�H9G�\"v���������4r����D�R�\n�pJ�-A�|/.�c�Du�����:,��=��R�]U5�mV�k�LLQ@-\\����@9��%�S�r���MPD��Ia\r�(YY\\�@X�p��:��p�l�LC �������O,\r�2]7�?m06�p�T��aҥC�;_˗�yȴd�>��bn���n�ܣ3�X���8\r�[ˀ-)�i>V[Y�y&L3�#�X|�	�X�\\ù`�C���#��H��2�2.#���Z�`�<��s����Ò��\0u�h־��M��_\niZeO/CӒ_�`3���1>�=��k3����R/;�/d��\0�����ڵm���7/���A�X�������q.�s�L��� :\$�F�������w�8�߾~�H�j��\"�����Գ7gS���FL�ί�Q�_��O'W��]c=�5�1X~7;��i��\r�*\n��JS1Z���������c���t��A�V�86f�d�y;Y�]��zI�p�����c�3�Y�]}@�\$.+�1�'>Z�cpd���GL��#k�8Pz�Y�Au�v�]s9���_Aq���:���\nK�hB�;���XbAHq,��CI�`����j�S[ˌ�1�V�r���;�p�B��)#鐉;4�H��/*�<�3L��;lf�\n�s\$K`�}��Ք���7�jx`d�%j]��4��Y��HbY��J`�GG��.��K��f�I�)2�Mfָ�X�RC��̱V,���~g\0���g6�:�[j�1H�:AlIq�u3\"���q��|8<9s'�Q]J�|�\0�`p���jf�O�b�����q��\$����1J�>R�H(ǔq\n#r����@�e(y�VJ�0�Q҈��6�P�[C:�G伞���4���^����PZ��\\���(\n��)�~���9R%�Sj�{��7�0�_��s	z|8�H�	\"@�#9DVL�\$H5�WJ@��z�a�J �^	�)�2\nQv��]�����Շ�Q�p.�˵��&h�����2�d�M1���#[[kf,�ոΆ��Wq�'f�Lkgn����6��G�;e��הE���CVY���{\0���D�5�R�x\"����h\"�ݟ7�W�,ň��u�c31Cxp<����If���]�ѱ^��{�����/���H�&s��ρi�9\$֒b�0�َI����K\0]���IDG}�ED���V���N����ý�m�Q3�1KT����3�zq��|���#���!�\"�	҃k�/p���hy-��.�!� ��0d���m��K�����~��au0�>\$�:�\$V����M��f٨k6��:������3L@����d���1������zlg���֩�i���X���9�7�<�׭#a}s���\0\\m�Z��X�Q�'n�.�9��KC9?lX�q깗\$M�2�Fq��ЎK�B:����bo�#�K�XnM���z��Ȥ\r�=��fm�%f�i�.q������;-7x���(@#��9��F�)�/M-�6�.�V�^�*�g\n��!\$�(7���Gxw��D����W�b�a�{����1-��Ԑ��G��w\r���9њ �(�:����'�%�?�0Ά���)�t�jHV�8}f\$��f^w�lK�l=�1Z�Z��Ei���g����ֺ1���X���ث���b��e�&���,2r���k>��޲O{�-�A��]�mMD�ȁ�>G;�З�g��Qo:��0�.�7lL�שl���|:nD��탉��-�K����*NY�3�x�.d�t>�Ў��)\$�\$���9�B���eeR�Gwi�r[62Ǳ9�Xk0̱��5��^��P��O�>�C `���XOk�7�\0K��l���x;�4�8�L���\$09*�9 �hN�����F>\0�rPQo<��	�\\F���d'���L�:�b���.4�,2����9��@�Hnb�-��� #�2�`Z�%�.��L�\r�P�P\"��^.�F����h��\0�����\r\0���\n`�	 � ��n�o\0���\r��\r��1`���0���	��� �	1\n �G\0�`� V\0�\n��\r\0�\n�z��\n@�\0�\r��\n��	 �\n@�@�\r\0�' ��� �@� ��z��1\"*���P���#Ap���H��\r���������\r�%�+Q7�>\rCqK�\r@�@�� ����~\r �\0�\r�d@���3�Q�ёQ������\n �p�í�h>�����\r��0���1��qqq�#q����81<\r�QI�Q  ���l@�'�����:	����\0� �	������#iE\n�\"f.�Q�\$��RQ�U\r ��]cri�m2s�w�}1(2��\0�)1����z �Q\\\r����\r����@�\n�h\n��� ��@�\0�`�	��@�'��q�)@Z�.RG��q�%p��_2e��	Q&	�o���x1�'��1��1����%�f��`�\0���f�\0j\n�f`�	 �\n`�@�\$n=`�\0�Ҍ nI�=\0�@�d'������8�.�/2Y8��@���q.	42��E��(���\r�:\n6	H*@�	�`\n � �	��l�����\r�ɠ�\r�Hl�h���\0����(�d��4����\0]IL^\\��K\0\\��}J��C\n���}����EK���I�B>�͈��IJt��dfht��/Fb���xYE���M4�oj�\"�m�N@�t�خ�~d�J2U4�I`\\A���uQ���U.uh��\\�����M��Q�؉\"G!Tn�Mt�P���X#�]Q�-K�Th\$�c��CD�Ɗ����-�Ib�O`Pv�^W@tdSS0�S�ܙ�<�)gMhF<\rj*\$4���'��\r��H�[���ɪ��W-�Fh:,��\\/���ɾ��J���^�OD����ٍj'�\r�T�-&[���DĪ��N����[��E B\"�`��euj����_���\r�`��h��F\r�JV��%�!MZlP^��:�o>֯X��xC��):mei/\n���8�I2\rp2����)��oC[�mViug��h6Zf���|DUw\\�W��C4�5U�\r��W6�m�zؖ���h��c��K�og6N��d6�P�`�Veo�<%�t?uf�!YU�,+��e.W%�\\����\0v8쭖X���n��UBcfv@i�N�=u�;NlKqwqKQ!v�[\\7�TO0��{yj.�`ȡi��z�%�b���H�\"\"\"h��_\$b��|j�\0f\"�tĮ*���}�\$�Z�W�\"@r��(\r`� �C��Ȃ(0&�.`�Nk9B\n&#(�j�@䂯��fX^�֮�� �@�`�I-}C0��\n�B�}B5�]|���W{�ʩb�|��}\"��˅�)}���g���ň�,=cڪ��{��&�b�P���/�C� \0��V\r�׊�틈=���BN\\��=�K��r�\$xՌWь�����8��d������H'�ζ8�}�=��=\0���\0���]���\n���(7�z��y+��卨�\r��`�DY�gŉ��~��|k3��ڠ�E�9C��C��ё�e��@�w�,@5�|fx�࿖����JlY%�xE����=�Aj@�O�G���� ٚ�e����ė�֖L\n�%��/��y�`ߜ�?��o�ya��e�������`��tG��� ��H�J5׃�M�Y�(��6_�eh����tDz&�x��9�jZ*�/�K���:,j�5�U�����>ǡ��ߓ����\"�e��q�ףY���<�Z���u�Z{��!��e�zV �%�,|m��xS�w㦺=�#��� @��:���X	����+�ڻ��C���:5�Zt \0�rZ�����!������=�{���� 2������ñ���ìy��Ǥ��X����fð�%��c�s��x+�8��A�{/� ���U�9��y�#1����y�,.5�u��k����e��_�������y��\r�~����{��[����Z:\0�\\���8�3���m��A�9'�8��:i���|Y����/+��9����R ˺�Á��X7�σ:�\r��	��+�^��;M��A�Ϋ�0Y��*2<7��Y����\$���E�����S�[�Z���ţ�5vp\\�i���F�r1�:�\">�`ɝ�W��ϔ<u������ħ���x�<����\\��֥���f����ƙۙ`e�j�?�ec���(����<�㣂fCZ�SDv���\r��#٦��U ");
} elseif ($_GET["file"] == "dark.css") {
	header("Content-Type: text/css; charset=utf-8");
	echo lzw_decompress("h:M��h��g���h0�LЁ�d91�S!��	�F�!��\"-6N����bd�Gg���:;Nr�)��c7�\r�(H�b81��s9���k\r�c)�m8�O��VA��c1��c34Of*��-�P��1��r41��6��d2�ց���o���#3���B�f#	��g9Φ�،fc\r�I���b6E�C&��,�bu��m7a�V���s��#m!��h��r���v\\3\rL:SA��dk5�n������aF��3��e6fS��y���r!�L��-�K,�3L�@��J��˲�*J��쵣����	������b�c��9���9���@����H�8��\\���6>�`�Ŏ��;�A��<T�'�p&q�qE��4�\rl���h�<5#p��R �#I��%��fBI��ܲ��>�ʫ29<��C�j2��7j��8j��c(n���?(a\0�@�5*3:δ�6����0��-�A�lL��P�4@�ɰ�\$�H�4�n31��1�t�0��͙9���WO!�r��������H����9�Q��96�F���<�7�\r�-xC\n ��@�������:\$i�ضm���4�Kid��{\n6\r���xhˋ�#^'4V�@a��<�#h0�S�-�c��9�+p���a�2�cy�h�BO\$��9�w�iX�ɔ�VY9�*r�Htm	�@b��|@�/��l�\$z���+�%p2l���.�������7�;�&{��m��X�C<l9��6x9�m�������7R��0\\�4��P�)A�o��x���q�O#����f[;��6~P�\r�a��T�GT0���u�ޟ���\n3�\\ \\ʎ�J�ud�CG���PZ�>����d8�Ҩ������C?V��dL��L.(ti���>�,�֜�R+9i��ޞC\$��#\"�AC�hV�b\n��6�T2�ew�\nf��6m	!1'c��;��*eLRn\r�G\$�2S\$��0���a�'�l6�&�~A�d\$�J�\$s� �ȃB4���j�.�RC̔�Q�j�\"7\n�Xs!�6=�BȀ}");
} elseif ($_GET["file"] == "functions.js") {
	header("Content-Type: text/javascript; charset=utf-8");
	echo lzw_decompress("':�̢���i1��1��	4������Q6a&��:OAI��e:NF�D|�!���Cy��m2��\"���r<�̱���/C�#����:DbqSe�J�˦Cܺ\n\n��ǱS\rZ��H\$RAܞS+XKvtd�g:��6��EvXŞ�j��mҩej�2�M�����B��&ʮ�L�C�3���Q0�L��-x�\n��D���yNa�Pn:�����s��͐�(�cL��/���(�5{���Qy4��g-�����i4ڃf��(��bU���k��o7�&�ä�*ACb����`.����\r����������\n��Ch�<\r)`�إ`�7�Cʒ���Z���X�<�Q�1X���@�0dp9EQ�f����F�\r��!���(h��)��\np'#Č��H�(i*�r��&<#��7K��~�# ��A:N6�����l�,�\r��JP�3�!@�2>Cr���h�N��]�(a0M3�2��6��U��E2'!<��#3R�<�����X���CH�7�#n�+��a\$!��2��P�0�.�wd�r:Y����E��!]�<��j��@�\\�pl�_\r�Z���ғ�TͩZ�s�3\"�~9���j��P�)Q�YbݕD�Yc��`��z�c��Ѩ��'�#t�BOh�*2��<ŒO�fg-Z����#��8a�^��+r2b��\\��~0�������W����n��p!#�`��Z��6�1�2��@�ky��9\r��B3�pޅ�6��<�!p�G�9�n�o�6s��#F�3���bA��6�9���Z�#��6��%?�s��\"��|؂�)�b�Jc\r����N�s��ih8����ݟ�:�;��H�ތ�u�I5�@�1��A�PaH^\$H�v��@ÛL~���b9�'�����S?P�-���0�C�\nR�m�4���ȓ:���Ը�2��4��h(k\njI��6\"�EY�#��W�r�\r��G8�@t���Xԓ��BS\nc0�k�C I\rʰ<u`A!�)��2��C�\0=��� ���P�1�ӢK!�!��p�Is�,6�d���i1+����k���<��^�	�\n��20�Fԉ_\$�)f\0��C8E^��/3W!א)�u�*���&\$�2�Y\n�]��Ek�DV�\$�J���xTse!�RY� R��`=L���ޫ\nl_.!�V!�\r\nH�k��\$א`{1	|�����i<jRrPTG|��w�4b�\r���4d�,�E��6���<�h[N�q@Oi�>'ѩ\r����;�]#��}�0�ASI�Jd�A/Q����⸵�@t\r�UG��_G�<��<y-I�z򄤝�\"�P��B\0������q`��vA��a̡J�R�ʮ)��JB.�T��L��y����Cpp�\0(7�cYY�a��M��1�em4�c��r��S)o����p�C!I���Sb�0m��(d�EH����߳�X���/���P���y�X��85��\$+�֖���gd�����y��ϝ�J��� �lE��ur�,dCX�}e������m�]��2�̽�(-z����Z��;I��\\�) ,�\n�>�)����\rVS\njx*w`ⴷSFi��d��,���Z�JFM}Њ ��\\Z�P��`�z�Z�E]�d��ɟO�cmԁ]� ������%�\"w4��\n\$��zV�SQD�:�6���G�wM��S0B�-s��)�Z��c|�^R��E�8kM���s�d�ka�)h%\"P��0nn��/��#;��g\rd��8��F<3\$�,�P);<4`��<2\n����@w-��͗A�0�����Lr�Yh�XC�a�>��t��L��2�yto;2��Q��t��frm�:��A�����AN��\\\"k�5oV�Ƀ=��t�7r1�p�Av\\+�9���{��^(i��f�=�r����u���t�]y�ޅ��C���������gi�vf���+�Ø|��;�����]�~��|\re��쿓�݂�'�����������	�\0+W��co�w6wd Su�j�3@���0!��\n .w�m[8x<��cM�\n9���'a���1>���[���d��ux��<\"Y�c��B!i���w�}��5U�k�����]������{�IךR����=f W~�]�(bea�'ub�m�>�)\$��P��-��6��R*IGu#ƕUK�AX�t�(�`_��\"���p� &U���I��]��YG6P�]Ar!b� *ЙJ�o��ӯ�������v��*���!�~_���4B���_~RB�iK����`�&J�\0���N\0�\$�����C�K �S���jZ�����0pvMJ�bN`L��e�/`RO.0P�82`�	����d Gx�bP�-(@ɸ�@�4�H%<&���Z����p����%\0�p��Є���	��	��/\"��J��\ns��_��\r��g�`��!k�pX	��:�v��6p\$�'���RUeZ��d\$�\nL�B���.�d�n����tm�>v�j��)�	M�\r\0�.�ʊH��\"�5�*!e�ZJ�����f(dc��(x��jg\0\\������ Z@���|`^��r)<�(������)������@Yk�m��l3Qyс@���ѐf��Pn�����T��N�mR�q���Vmv�N֍�|�ШZ��Ȇ�(Yp��\"�4Ǩ���&��%�l�P`Ā�Xx bbd�r0Fr5�<�C��z���6�he!��\rdz���K;�t��\n�͠�HƋQ�\$Q�Enn�n\r���#�T\$��ˈ(ȟѩ|c�,�-�#��\r���J�{d�E\n\$��Br�iT��+�2PED�Be�}&%Rf��\n��^�C��Z�Z RV��A,�;���<���\0O1���c^\r%�\r ��`�n\0y1��.��\r�ĂK1�M3H�\r\"�0\0NkX�Pr��{3 �}	\nS�d��ڗ�x.Z�RT�wS;53 .�s4sO3F��2�S~YFpZs�'�@ّOqR4\n�6q6@Dh�6��7vE�l\"�^;-�(�&�b*�*��.! �\r�!#�x'G\"�͆w��\"�� �2!\"R(v�X��|\"D�v��)@�,�zm�A�wT@��  �\n����ЫhдID�P\$m>�\r&`�>�4��A#*�#�<�w\$T{\$�4@��dӴRem6�-#Dd�%E�DT\\�\$)@��WC�(t�\"M��#@�TF�\r,g�\rP8�~��֣J��c����ĹƂ� ʎ\"�L�Z��\r+P4�=���S�T�A)�0\"�CDh�M\n�%F�p���|�fLNlFtDmH����5�=H�\n��ļ4���\$�K�6\rbZ�\r\"pEQ%�wJ��V0��M%�l\"h�PF�A��A㌮�/G�6�h6]5�\$�f�S�CLiRT?R���C����HU�Z��YbF�/�.�Z�\"\"^�y�6R�G ���n��܌�\$���\\&O�(v^ �KU�Ѯ��am�(\r������\$_��%�+KTt��.ٖ36\n�c��:�@6 �jP�AQ�F�/S�k\"<4A�gA�aU�\$'����f��QO\"�k~�S;����.��:��k��9�����e]`n���-7��;��+V��8W��2H�U��YlB�v��⯎�Ԇ����	����p���l�m\0�4B�)�X�\0��Q�qFSq�4��nFx+p��E�Sov�GW7o�w�KRW�\r4`|cq�e7,�19�u��u�cq�\"LC�t�h�)�\r��J�\\�W@�	�|D#S\r�%�5l�!%+�+�^�k^ʙ`/�7��(z*񘋀���E��{�S(W��-�Xė0V��0�����=��a	~�fB�˕2Q���ru mC�����t�r(\0Q!K;xN�W������?b<�@�`�X,��`0e�ƂN'�����&~��t��u�\"| �i� �B� 7�R�� ��lSu��8A��dF%(�������?3@A-oQ�ź@|~�K���^@x��b��~�D�@س�����TN�Z�C�	W���ix<\0P|��\n\0�\n`�����\"&?st|ï�w�%����md�u�N�^8�[t�9��B\$�������'\">U�~�98����ÔF�f ���u����/)9����\0��A�z\"FWAx�\$'�jG�(\"� �s%T��H����e,	M�7�b� ǅ�a� ˓�ƃ�&wY�φ3���� /�\rϖ�����{�\"�ݜp{%4b��`팤��~n��E3	������9��3X�d���ՏZ��9�'��@����l�f����Q�bP�*G�o���`8������A��B|�z	@�	��b�Zn_�h�'ѢF\$f���`��HdDd�H%4\rs�AjLR�'��f�9g I��,R\\����>\n��H[�\"���\rӁ����L�,%�FLl8gzL�<0k�o\$�k��`��KP�v�@d�'V�:V��M�%���@�6�<\r��T���LE��NԀS#�.�[�x4�a�̭�LL����\n@��\0۫tٲ�\n^F�������5`� R��7�lL�u�(��d���� �\r�Bf/uCf�4�cҞ B���_�nL�\0� \$��aYƦ���~�Uk�v�e�˥�˲\0�Z�aZ����Xأ��|C�q��/<}س���ú���� Z��*�w\nO��z`�5��18�c����������I�Q2Ys�K�����\n�\\��\"�� ð�c��*�B����.�R1<3+���*�S�[�4�m쭛:R�h��ITdev�I�H���-Zw\\�%n�56�\n�W�i�\$�ōow��+�����r��&Jq+�}�D����j��d��?�U%BBe�/M��Nm=τ�U��b\$HRf�wb|��x d�2�NiS���g�@�q@��>�Sv�������|�kr�x��\0{�R�=F������#r��8	��Z�v�8*ʳ�{2S�+;S���Ө�+yL\$\"_��B�8��\"E�%������\n����p�p''�p��wUҪ\"8бI\\ @���ʾ �Ln���R�#M�D��q�LN��\n\\��̎\$`~@`\0u�~^@��l�-{5�,@bru�o[�����}�/�y.�� {�6q��R�p��\$�+1�3����+��O!D)����\nu�<��,����=�Jd�+}��d#�0ɞc��3U3�EY���\r��tj5ҥ7�e��wׄǡ���^��q߂�9�<\$}k���RI-���+'_Ne?S�R�hd*X�4��c}��\"@��vi>;5>Dn� �\r��)bN�uP@Y�G<��6i�#PB2A�-�0d0+���gK����?�n���d�d�O������c�i<����0\0�\\����g����ꡖ��NTi'����;i�mj�܈�����u�J+�V~����'ol`����\",�������F��	��{C�����T a�NEۃQ�p� p��+?�\n�>�'l��* t�Kάp�(YC\n-q̔0�\"*ɕ�,#���7��\"%�+q���B��=�i.@�x7:�%GcYI��0*��Ðk�ۈ�\\����Q_{����#��\r�{H�[p� >7�ch�n����.����S|&J�MǾ8��m�Oh���	��qJ&�a�ݢ�'�.b�Op��\$�����D@�C�HB�	��&�ݡ|\$Ԭ-6��+�+ �����p��ଡAC\r�ɓ��/�0�����M��iZ�nE�͢j*>��!Ңu%��g�0���@��5}r��+3�%��-m��G�<���T;0�����DV�d�g�9'lM��H�� F@�P��un�tFB%�M�t'�G�2��@2�<�e��;�`��=LX�2���X�}oc.L�+�xӎ�&D�a����ɫ�F2\ngL�E��.\\xSL�x�;lw�D=0_QV,a 5�+L��+�|\$�i�jZ\n��D�E�,B�t\\�'H0����R~(\\\"��:��n*���(��o�1w��Q��r���E�te�F��\$�Sђ]�\rL�yF���\\B�i�h��hd��&ᚇh;fo��B-y`���0��J�lP�xao�\$�Xq�,(���C*	��:�/����HG\"��c��C���Q�\nF�Ԅ�#�8��F:У\0��Ok��D��])�ϚtT8L�𒨔�n�`���|�HJ���� �� \"�6�{����?=I<HGc ŤF�@�,C ��@j�\$L���(�nEʑP��jb�n�Α���W� \r�Lq����sPH�ꉝz\\V\$k�ҏtr5�,��l����<�'\0^S02�0f -5\"ac�\"3U�p��\"ܘ�%��\0'Zt\"96��9_ @Z{�0I��D�ZE@��N�h`�\"�`�\0�����ɹ(G�H��Ch� �I��f`@ZD�\$)�K�;Z��\0�/�C�T>r_R@O�`1r�TҨIb\0�*�8�����h\$�_�p�Rĕ\$��Ni^ʪP/O)��.ŹT6�\\�ٔ@T���rą`)���T=�n\0��2��e�+�9ʢ\\��@�����>�PH�1	�y#���r�<�a�e�K��/�c�M@_.\09ˈ��������B����0i���a�\n��de�a�%|S2�����#����n��D�\$/�+E�d����_2P��\$s,ok�#�<�	�A�đr{B���A-Q4Ҥ�\n�\ry�!�b䱎���O��@ɬ��k�� �\"�r��*�݇��Y��/��ȑ a0��%�.gE~��&� 89����#@M_ ���7K䃸J`�X)�B\$�(	:�g��n*�|�M6PZ��Ht�Jtq�Cx�[ڼ����l=\n���U3�f\\̔J�P	,�:�}TA�SYH(�\n���I�ٲ�!t(2U\"�\\�X�^s�	��a!�\nPr��`�X3fnb�����J���&�z�zQSf ���t�!T?�9%�(Q��B�}6B�kP\0�>�g�&~fhU�r��,� p5Hi��p����qɚ�g�V�V��Og�WEJ8�0G��ak���@N NM��U�UxȪ��S�x	��	�K�@c�1y�VlϠ��C����2Q^rP6|�I^M�,�j%d�`ܫ��F��\\#%�|�C����7싢�G�TN�����i��H���Q�O���C�yB��\$�%T���*�>z\r�MM Kp� ��J7O۷�4�%�\$�p����4������͂��EҪ\"T��\0O�\0��@>	r�O�]���x�}^�I��@� źqn��0�Bb�ȵ�I�(�M/�;���}RN\n�C�<�b�PԵu?�=Pe�C����L^'�S��?}4)��S-���1\r5S�OE�SF����AOR+�ޙ+v��5�&C)ِ��KSDB߳N|E\rc�U�Yʾ���V���?H�)実+sF��k�LPW-�,�U:�&��t{��Vo���J�l'��W�e74X�n GF�'���`��Cc��%Il�j�u6����v�U��Z�\0*���Nԟ#��(���n�-;|��4�]X���y'����;��Z���) s9����%��R+\$��	��Q��(\"�_kX��������\nM#���\"!p~:�*����\$�3O������6�+���\nB�{1��|H�K<[`3��#��F@��ǐ! |�؊\0��>�����[nrMM�+��mO_�2��Ȇ�\0�e^	�7Z�&�B�J褓h7QO%rf�p��΁�֞�m�ب�Ç�4E�l���+���V��i�N S�Z�Wt�2W�[;��v\"%��\$^�-(I\$��S@R-&�T�z��k(��	�%R8�uY\0[9-���(�)E��8�=^����G�5#����)�1V��b\r]�Ne;&�Y�`r��I��Pݱ���ֲ��\0�@P�7���0H���؍R�x�\0000C|�n=��`��TT��\rEhON���'��&�tc�K ��ܕU5��������P3\\��2\"\0y�5�V]���6>�U!��@�hu��(�\"E%07B��6��d�HN������ij';@��e�MzlSfjKY�֍���-uh��H���smL@��\"r�j���j'l7	�(u�u��E��e�a�@�+�K�:ӕ�%n�z�V���;�[�_Vz_��E���8�<�Sb�������6g��:c����7\n����%Q�� K�7�ܮB����w�u�5��0��֚���y�ncnK����T8�ʙ�s��W=+�=K\n_[p�G���C5����'�D\"��M<\":|Mq4���f�s�x	�qlͰ��QP��aOY�E=���6nT떒�Bt�h�C\0p��@n��D(a�P�\"���'ZN��۬��\r�LNX�g��<!w�����[��B)��)~���c�x��v�i¦�q�����a�@K��7s�EQdý��k����?\"�3�-\"U��|������|21D>߳�]­&���\\h�TƳ5�\0`Tz���s -�N����\"�f��N�LU�]n(D�(��&%\"�e\\��O��N�Inۿ��\0����ƕ���@����V�|R�MYC�T����b�UH�p)���S�s� q�i���`Z5vt坉�*�OO\n�(�����F��58�!ax@�{^P����?���eh}\\�j^2�L�,6�.�N	K�%����u���ip��!?�l��� -5�w���K\"V��\\�Is��2!��\$4�5v\n�����gr��N��}��;��������W%D(pWa�\0�v'��6��V��ƿ0W��E4�EUl�8�LD��E�<kO��H��DU�	`vS��L��!DTMbnWV��Cd��)Ze蟀���:�2�d8��K�ބ�4�-G�b;wQW�30\r�f\0�,�`Qhl�֍�0�P��0h@\\�r�8��T���⛜�1�`�&���w�X�>�F?��|P�*�M�qZѯ��}��0k`��#�իc�'[�ֱˍ|s�IJ��\r����<OaƼ@�W��u�T��:��E^������!k�����a\$�>5��u_��KcCQ�r-ъ�'\r�iC������@8�S�PS�_Xgl�%�	�n1r.<�w_aɺĳ�Gh�4\n�W�Z��aBn,\\\0���DU�\nbbZ'���72���r�¢��}�Y>/�w\\Y�`^7J�j�S�������S.��o%�Jg\0GD,���>7���R�0������3��6�%i\0S�^L��A��\ri��O<���a phv[�{���\0�E�^x�ܼg�YzW�yG�a��:(�>C�����e\0���])�3yts_a�7�+��B��C�eT��f�o�P����2E�C��v�>�w�l�z�*p�Y����q�����Q�p\nv[|q�ҨE[�Xi���=�z(	�M�n�]7F\r��Cs4|-} ���Ŀ(NU�?,��څ��������q	��p�q~��� ��F��%�88��靦��\$�ް�[���r�o!3��(����g���ץpJ!���q�Z�v?��c���L��7��6��\$�m���q��8l!��5�C�;Q,��d�sF�-O��fÈ�\$���6�%U�C��f\"��e(j�\rMt�F����R�x;n�B\$��SS�x'��G��陊M�	��4ͬ'k��~��#9e��Y���~��뭈;f�+�j�K�9p���M�'X�/rt�\0�\\�J%Q���R�\rвO3�|�寚���ϱ�4��xF���s5E�Ԑ;ԒWR��JX�ʶ�J�\$��wzO��&ǵ��z�k�S�\n�\nNUP���.��0���bdk��P���	G6�+B�z�1ΎhQ>sHv�����Q�٠E�p��M��)��\n�\\�ў�Pz���.s��� g��)a~��ȥ�!(!�G�hr[�*�����բ�`��~�\"!�O���5�G3Ş*qkgB�,\$���**1�c.�n	8��\$d���VSne�MiZ���7žg�A�5�����\n�`�,�2��a�ү��mMkʻ��ɯ��/-��6�@?#`��)�Ԁ�ha���)Vc�]�_=�Rz\\�VR��=�ط�(-�ot�\$ܥ�\n���dSm�y��fө�N\r�m(t;D���p�2�ݶ��ZRl)�9M̛�,/��Yix��kя)�.�2@S^���u���d�6�!��>VB�� x<��Kt06���@��\nG�A�P�(��NbD��K\n�\"��cN��\ră.p���'2L��d�ꟲ���\\Ly�A=	��D��m3�%�@��������8�qbSP\"�ޢ�Ʈ/�Dz�C&�O��\0007f��D^1�X��/��,\n��v�Wx%f)��' �D�dQ@��I(ҋ7Y��|���A�Q��D��ڠe 8ׇ7k)_ �@\"\"��%�}�	�(��1�1؍�\r����e���?-ɵH��&�����\rL���'�eۮ0�T�]��C!�emNz�	Uz���Ɉ���S�ܜaf�7�M�^C�D���(_������#\"�dr5�9���81��hf�ȭ�a_�×tZX\0�U����{2nn]��;FR��!�}>s�Hi��y#���?\"Ť�����>{���/?7�F��Y����?Aj��.�U�!5`H��\$r\0��'\n�\":.��dԂٙƪ�q�Rխoh��>���{��1��+�>����t��k�%-D�=9�}�C@�8cm�Hr���W�n��\0Ď<(�RR�8����YV��`�pp�.U�e_`����^���쵛n^�_�R|�r΅p�7/!M5���|���\n�&�F��VVz��O�A�~ш|ƛ��4NȒ��Ք��g�yh-���\nN\"r\"���Gc�s����D�'�Xo٧���O�{��{Y{��E�=T�e�Z������{\";�H��Xz�t��w�*-����U���w�-��\"��<A^�O��T �]�D?:���������<��p�q�[���,)�&`�{xKI�I`�`��c��0����D�y8���qC��Y��CF���J���nk�[�8����:\n^�ց��T�!X*M�<�5`\0��6A�2o�P.��a�AH��#x[�����▞�� '�o@��O0^���h|�P�=+�)�d[����X-��W�!����Æ�/:\"�0k#XǞ<����h�CG�ݠ@F�(�k����l�&H�F0OSz���w�Q��3���z|+��\r9b�T�}'ܬwA�\r�nF�����!�g0�lp��l�1�+�|�h�kz��i&��u�D�{K��\\���\$t(�;���ì��H�r|Bw�D3[M�!:(�{�Z��(|-�Hy0�^�'׽�}�*����NK������5KU���jM�\"��w�]%���{1q��z���)]�Ů[k�\0O4������UF�\0�c���mZEGt�sDQZ�)n;7�<�qhlXx�I��^�V��&�ͷ�C�`,ɑ%��1\"@1�|�)�R�k��V��}S,�#!��G���]��Ex���YT��<%�Qѿ�@�����m���Jc��B��B i����G��f2����cD��nէ�=J���I_�������'����iA�&,��{��c��4��oV�%�d�2�x�e���#s_U�H�ՉW�!  =۷�O�<(y\0�.��G�'�\r����57�pV�(�þ:��}�RRHHy[��	����� 1����O\")��L�l���1�������������+<~�	\0���s���?�B@��d�����?n��~�&LЄ��?���@:@;��y���Q�>�����f���:\0�t�+j�sz�K�,b^�p���HX�?�P�\\D�?v\"�����\"�&� ?�����t��`�V?�\0���J�wC1O���#�Ɛ�*	��@̿�\0���Ƈ���/#8\"�O�\"�\0���6�Nc�ä�[�p@C�h\0{\0	�pDO��Ft��H/!h@��L�;�@���w���I��~C�ˀ¸)�E��4+���)���Eb�?]�d��\$�<���`o������?}�8�b���/�J���o#��IV,Ac��3�Xa ��o�xi����\"椌CU���D�k�YȊ�}�\n\r\0,G�\0�|q�� �.Ŋ���N�q�pN�Д�jBO\$|C�p}��4`����\\*4��bA���+�D_������X�\$�����@��6\n\0\$�~ˣ�\0��Jb݅��� U�p�X�iD\"�ێ��lg�t'���� �+x�<���N��51e���0`��B8q�\"O- 	C!�Қ�mɵ����*��f@#�6�ZЛ9���ZR�ǁ������	HZL� e����9�9�� T n��?xX\$0��%\0002�\n�y�!��e�:\$�QssA��nxK���l1'��Nz!p���.Ṇ�c�p���1@��)m�:@P�\0�1\n�(CR�5D(���P�1#	�d7�+\n��Bu��ha�M	a�\0�>�1W���\0a�4 s�-ׂ'�jp���\nJmQ����)�");
} elseif ($_GET["file"] == "jush.js") {
	header("Content-Type: text/javascript; charset=utf-8");
	echo lzw_decompress("v0��F����==��FS	��_6MƳ���r:�E�CI��o:�C��Xc��\r�؄J(:=�E���a28�x�?�'�i�SANN���xs�NB��Vl0���S	��Ul�(D|҄��P��>�E�㩶yHch��-3Eb�� �b��pE�p�9.����~\n�?Kb�iw|�`��d.�x8EN��!��2��3���\r���Y���y6GFmY�8o7\n\r�0�<d4�E'�\n#�\r���.�C!�^t�(��bqH��.���s���2�N�q٤�9��#{�c�����3nӸ2��r�:<�+�9�CȨ���\n<�\r`��/b�\\���!�H�2SڙF#8Ј�I�78�K��*ں�!���鎑��+��:+���&�2|�:��9���:��N���pA/#�� �0D�\\�'�1����2�a@��+J�.�c,�����1��@^.B��ь�`OK=�`B��P�6����>(�eK%! ^!Ϭ�B��HS�s8^9�3�O1��.Xj+���M	#+�F�:�7�S�\$0�V(�FQ�\r!I��*�X�/̊���67=�۪X3݆؇���^��gf#W��g��8ߋ�h�7��E�k\r�ŹG�)��t�We4�Vו��\rC+����8\r�\0a�Rؾ7��0���^v�6�n��xP\\����@y��A�R��� �o��`�K~f����\n�{�f9�����ť��~�!�`���@C�.�A��޺.�����9���z��\n�l�빨w~��\${XHpɉ�ح/��Ԥg����=Ѥ�c�H�f�d��%j�����c5�^cH{\$��\n��\r!�4��n����6���cH���[�.6��`ӥ��λ�\\7��������W��ޔ>�}���hW���^����L����ژ� rY_���WV:@v\n����øi�4�0�B���E��*`z|ڑ���\"�����C(m��ΈQ�\$X����awK���- �M0�՚�^��?�\"\r��t\r��h�� ���}���y��z��Ƀ�I? �b�wA?���щ��A����h�d6���A��^2a�˃��Z�R ��иհ(�,>�����)���ޑ�K��2��!C���l�\"�\$!�@[fzXHytA�'X��Sʒ%�H\n�4&4�VSZ_������3�RlM�M}��\$R:�NiՁ@崿�,���c�Y�2�c�g�p::؁ Hh�PZ`�F8�A�W�^�|CH+g���\"8G�\0t��������\n�t��&\n#�s����Ԗ�u�'N���0ب�\$')�/`�	\$\0\\D05�6K㲿=A~B�E�Z�ʖ�٤��@�Y�;'n�S�=D2���l�'/�j���x�i&�{WнY;�q\"D�A8a^1\r�1��l�x�)傄�*R:p �*P{��0�r��i���2f��vM3ק����!�d�Q3��<\n9�No���4Sjo���6\0D	�	����hKYj��=I�xU-�u�\$�ܹ��B���n�{�a�\0ra�\\ N�-\0ᆃ���C�wZ��9��+�T!��ª\0Ԡ`��!��.��Z\n�r�P�?�l�he>w�0�A)��_!�7��[0�J��>�TR�)�x�8p牱DM��-E�pq�\"\r���\$q�����Td�Uz����V�L�|�P2§��s鉰)�=�C'�pٔ�E��`ʉ�\"\\��[�/N����|�Foy�&�Y��\"YJ�4�v�{;b��>:�]�9���:�H	[Cv4�hA�1?�&���3�Җg����jL�I�`�7ʀZ^��:��k\0�5|�ՠ�h'2���\$�����`�C��!�D���m]-@�R�}<� 1u�y\r؅`�e��h�c�N�\r=�̴��p6�.��l��夘6�ED�p�}ǹU��Raqւy�����;����Ӽ	p=��7A�=�T�^6��9�#�����6![�E\\�@:���oo���#3��/�^�)�;�{K�. v8�<b�m)�W���\n�2�s��θ]�a'o���%���C���{����x�\\\n��Y�������uh�G\0�hp;|*�[�8���+vj~��ȟ��|r�S˷�x�Lwtwʻ�����=��>��v��\nL1���E\nA�!� �}|�H'�\0��pA	�>0�¹n*�L!���B	��`�l�\0��B!L\"�Ϳ�w���E[sf8�oPݞA�w�)���E����|�#�\\S�sU3���&�>^��{����_ϖj/���bR/�/���L��\n�ΰ�δ�E\$��� �N@<�vzo����m�\0\0�p4�P�.v�Ƭ��\$��M��N��:�0�o6�O����!�.��������0�p̔!��\0\ro�p^��c���	�Z��0k\nP���P�f��|�b�	��%m	���?P�\n�PB��6��.��Эn���\r�����P<�p�� ����pXR`\\�_�����\$M���(�'��N��A(~jÂL&�`Bh\r,�O��`�c�k���٢��)1*����el����d\0р~�fڻƖ`K����\rn�'��qt�`¶�V���W�D: ؐ���6\r���\r���ҀZ�q�۱�xI����|F��bR%k���͆� �@�*�r%\"���+�!bX�rzif���I�P>�\$�E&X�%>��Bm`r^%Rb��Bd�m#�a!�V���!d�����\rˎ�RO'�Χ�o��&�S&R�@�Q�9&��g���q�L���Lc�Gb{j{g�+�l��@\\�<B|�b���-��\"��kD���&\n��\0�D��Κ�(��q����L8Ւ��o��Nv��\n��	 �%fP��� �\r�n�Kt��[)�V�g\n\"�Խ�N��\$�W���d��4�'r�\r�#`����\0��ǲ6�@��v�����(�\rr�����b\$��@��[f��k9�r7`\\	�8�� D�\"�b`�E@�\$\0�RT�&�\"~0��`��\nb�G�)	=@�[AJi��8ge��`d���k�;�`�����7\"�z4F\$n42��z��7�I�� ��E)^ь�8�䝀�\rŨ���N�sq0���Ȏ�bP���A+I:`W��NJmI�W4<n�P�JT�J��LbTJ�OC��J�����fٔXN��=���Q9�`7L��f�O��O�z�S�5���� ����P��\r�\$=�%4�dnX\n�Xd�ɋ����O�橧�\r��)F�*h���n B�5\$���jLbs�M�;+P\"�n�Sw7�QӃ8��#j�\"��QDn��fgrwe�W�%)d�XH�W�Q�\$TK`�5�q���H���\"�u��kVU�RCH��7@���1�\"\r��'�SU�Ӓf��W����4��� K0n�;��=	��	�����cT��D�F���fX���]����澵�e�Lq��FE\"��xx\r(���\$�4�S\$)F	J�tDʕDE(��EF�&T�v\"�*`�>����|U��/Q��dR��v���\\ZS�\r�Oa����ҍ6��n.ֱ\rem�x҃��vqPS�]��\0we��Ot_Pcw&mjo�z�f�@�o�#o�mO�%g���`�=3�m�D?�A/�]5erk':uj|�@wH�3s���;���v��t�v�#��k��&��c�_��#�m���^c�Ĳ�)n�j�\\��wbဿ_�B�Q��R҆�=�=��/�R+M���Yx��9����T\$�z�9a��Ҳȡ�2�� �7\0�&�yB\0(���l\"*>�������M�X�@)xVW�}�.K�`T\r@� �Z��'��-IkH�7��5b?�ςl�>w�dܺ`��>\r뀔�}xZ�8��`�ɂ���g]�Qd�g��O_Fn�dLv54\"�2�xwW����1�[2�2K��uL%��8��������(\r�-��Ӎ��w��×�e�A5���hW�>xf�(��w��zx-v���	�V'L��v�R����!�vV0�y-0�\r�W�����K��]�b̖O�\\�0Nj�����S义���D�ج���\r�g�3��lQ�YEwdĘ���L��X'Lb����NǹJȬf��2�ļ�{���8Ƞ���#�xs��Id�E��w����tɗ�/Ȱ=�UTYvGG+����u�Y��#RJ\$JP)8د�̬���J�K顅�x�0z5� �c����\0n7������Y�T��9`D����u_Yy�����#����n:%��Q��9�Y���8�@��B��i����U���^9�T�OD��ڥT�z#\$o����j󨹛��O�\n'�YF�MN�*��ؗ��몶��z���٧:���c�:����ٖÚ��z)�������,u��0�Ă?�����!ĵ~2	�{ ��%;�ȏw�V6�y�k�]��~F���e�|G�`Sh�խ�ZO��Xշ���X��Y���K���w;�L\$��p@�k�*`�߳,�Z?�8˺�+��ɣ\$ۡ�9�{��zA��7-�n���{١�]~��x_�����j۠�������V����d�׎��;���\r�z9�l�����������\\�W�c�w��e�����xK���();{�gϟ��d�q�Jm�Z��<:`��8�����5BK�+U�#��L�ZI�zM���|KTZ�a��g��s�����TZ��G]��=���3�X�˺%\0�Z��U��ZnJ\"�X\r�X�;�Ӑ�����@���Y�ȌQ�#�LV�9��\0ۛ�c�Lp�#���U�=1�x��)��T�u9`Sڀ�!���@�\\߫%c:F�U����[�<�<��>T;�x�`�����O�]�}�9��y�ę?��Y��3�=%�2)�ls�]@�����K��Q�`�ecէ����Y�X��E&��g\r��@例����6%=i���Q|g���|�:9*���5��n�~9�k�+a���f(\0�+5I�5�޷��õ���q���]�iet���N�݌����M�-5v3�sa��?��e��|R>z��u駦�^q�Q�N����ۏ{Ξg�d\0rȇ~{�4���~M>��>�勥��_���>i�w�I��W�>��d�ub?����ʬ�AP\n'�r ?�@T�QpՆKD�K�M��I6@ZMI�I�����a3�X	&U\0�`�Vq�Gvz����N��O���?f�+�T��n\r<��P��?c�s�<?�S�0����\$��S���'��b}`�-�`�!��`�:Oj��F�~=Tڡ������lL���0Ҿ�������^�������T�5�閨��.Oᩘ�P�-Y���XK��\$�9+����6	�˷\0Љ���.��MO\0�ƿ����|��l��/�1����V�׬:��KzS������\$F\"������?����{k��j������ow���_�Pg��DLk�Q�	�\$�	��kL���ޱ5�/Ѻ�2ڼ������_}B��\08�\\�n\n8�V�k\\�`?�\nE�Z�b�-V��t�S6�r_%��������I��id�d�H�^�sCI��-�b�̄�)[�	X6��rTj��\")�`��{qB������7��V�YHɆ˙J�U������A�|��\\��Y���E����j'L�*kF	�����\\��#�C�	O�V�,�!�Hហ\"f�(�Z���̀\\�gܜ\0����pQƎT�=�.!G�8^l�tk�\nkxZ�G|І��\r��_� @X��<<�\"�j�#E:=�\n�g�� ����\"OR�����\0�\"��~*\0�eV���I��T)�az��\\?4>��x���ܦ焨е/�}K��h�@��K4xy\n�Ƙ��9\rA�\n{<�`P��D-=o7{Ѝ���r���\nj�T�5�|�dg9͑RGyf��3�\$��x84��a�g�<�@b������/�����\r\"��~^!�5#�.pHhs���p ���dq'�7��їX�c5�95�<h�F0ƚ.Q���cT�D�Fz3\0 �{�����F|�q���m�r��ݸ�`�@Q��+�&/��4b\n��F,я8*���@���D�	��=���u	(@a���t��\0���1�i�\n��c���B�,h�<�3�%b0\nhFw ��)(�*�XV\"�\0���}��4��N�u�q׎Lp��[���2(�����1�H�/.�y���u5���4	�����չ:A���b�~�vHRD\r���#yG\nIjFx�*�rߏL��S��J �8@aJ\$rcX�0�H��_h�C\$Yɬ'Rm4���1����s��mBd0��I�'cUd�\$���\0�P�\"�}Ba(�/�T\$B��\n��(�N�D'{�|�B~\0�I�T�	����	P,Pt�h]%+�ɓdܜ)��ҟ�,�Y1&�_I�����he�;���Օ��ed�@\0�@�Ah�A&)]:P!�\\��k,�M�\\Lq���\0E,�PKDBޖ�O\0�*�����RWH�g\$��sLa�v�@��m4C�(�LFx�=l��b�h@�Pݛ<9�U�|_@\"��@	��\\�0	�E6p\$�8��R�&b@'̼\n�P>9�f^@\"̬�  1�\0�2�\"�`\n\0L�T�)�/M&isMqZy�L�e�4�fg3Y�L��Ny馟5ٙ�f�.\\-�S5d��\nǟ�@�2�'�T�;bhPP,�Y��z|ٮzp�-����M`�[����8��2ms����{�N&hB��@��Ɓrq���)�(	�1�L����0	�D�@�P)���|���:��NFo3:���6x[��k3-��\n�~�)�Rf3-��a�9�Ȁ�t�G���g�;��\0�S��i��s�L�\0B�\0�+O�e�\0��?Y��4	 T�z�!>0M�r31q�c;0'L�e`F��즜)��N�u𰞔�f�>�Ϫws�����)��������i�P�T�T�g���O�{��\\��B)�Т����XhJP�rt2�<Ѩ9ɫ����¡���>�P��\0O�}g�C:'P֊3��h�A�O��\\�U(�D���>|4Z�x��9���������(�E�\$�z�ӑ��&�2T��)�*�@����&\0�A�Q�L�`N����9�B�̰��O�\\l|gk�c0��>�L�f�R��)��A�R\0/\$X�E�)`ȅ�\\�kȮ˸p)�G�T�ք�Gd�4��E2�rp Ϫ��Z��6N�<����]3��s���ґ@l`��(�P'��d!�q��D	���b�m��8wN ��Ɯ��#<J��(\0S\\Cu���V�k�Zc\n��@��N*x2\0���Xw���@����a�E�P�.�iJ!%D@�P�z΢&l�=Bi�P�-Ԋc|Z�jAS*�����x�i|*2p�T��cq��z@���!5/�I��7�Ӳ�Ƭ�F�,t?U��|�\0��4��j���H6���ѩ��N���HtD���3p'��qS<��(,鷗\0[媻�j��M�w��<�������?��ux\n�Y<b�\0ꨴ�E^�G)����|����8I���o;-e�0\\\n����=��L'���p\0{X�U07`,,S9���y����P|i�Oh	�-���D�Ͳ��LSY[�:�Ӊv����%&v9�4=O�AN��t��1��\n�\0�}T��U{�^�MSR�*کBtVR�׌Qgkz�~@�	�\0�����nl��脵�^Y/���Y����W�aQsaʱX�Ĵ�]c�,E`Z�X���0�0�mRkX�8#��[����R��w�����P\$�HK�u\rk�B���銂uN�����Q�P��6cF\0�\0��n� r��!��;/��=�D�����e�4Y�A5]O*�`:��Z`f�s,~+<�\"dU�3e���\0���f�%��Pw�^�Hמ1` �����yY�q⚰��,^j�\nhK#4p���1�����v�������eZ��b)2�l�1����m�U��K\$\0���n�t/�n�x�Z����8�mEjJ��p#6F~E��(�����yR�凰%B7�\n�V6e�TΈ�-j�bY����j䇐��e��^B{6U\n�@cW���n�y�C�`u}yiV��t�%0b�J,p)g1��\r��VX@R4;�\$V@=s���x Ɗ�P�̐��H�H*IȡX1u�i�;ᕦؐp�Q���q����[��|��(���`+�G]rǅ���\$O��9�d��Z�lE�	���NK��z5u�5_�!Wp'L�w�|=�¬.I��l��v��N���O�P��Z\n]������o+?��O�~'������DZ#�Sǜ}ڮ�v��*l��\$�B�i2E]�c�k�]��ER\n��t�)d7��fL�Z��.�xi�U��/`�����z`/^���b5�yNv���ĕ+R%���U�6��6��TC�U;�W.�/��lQ`X���Ů]���|�t��1��\"��G6��b��+�W�/W�e\0k�{���ԍ�`�e����?d@�(N��t�)��\n)a¬R>��_!��;}Ff3�\"�\0\$�%�E�a,���R�����%D�g��K��o�!4!�gY20_����	�X����X��	8r����ͣ��X�F�H� `��*�	Ax�#�E��ԝ�X��:�S�����=#)�i�C��L(�9T)̙�\\a#����h���P(�5�T.#�q�_���ZM>Td=\0�}�	f�F��1�2��BJ��C�+X�#5�É2-J�P�\\�+������,�6aY�b3]�o@�`c}a��6J�b�\\F��&���c\$0��x�1+���.�O�q_�\$��W�5�%��M�0W��7b�\r�xƆ,�,Ba�lX��._�q�8?�-X�+�G���,d�����`�q1����6��q<C\$e)��V�W���c�a��� 0�2��E�'���{�0:bT�G�20�\$�h�̝՚a.8���,C��d��ǀ�@ZV�<#X�_�1U������%ƈ�1��x\\X��72y����ɰ�G�DY<��\$���0H�Ӓ��iVYN(�rw��X+�Y�FT�g�Pe\\~p�X-���Ld++�3��,q�K�:�B��YO��H��`Ox\rŴ2r��p�x<�&]�U���d�b*�\0�I�����09���x1�\"�\0�.,����7�D3�}b�h��[���2�^j��ь��G*��.��S��x�!�Ċ0:	(?�	^��\0x2�`ͫ7\$I\"�H򴒴�ė��Iq?*P��T�\0�T[�c�8��'��R�La�\\ B��c\n�,���\\������>g��(���Ҫ\\�[���6jނ@�0��R?���u�S��l�:�K�)͆�&�%h�@\$�� ��Q���@D�ՏU&%��uRz*'����/��	+<�0��4\0L�`3�w��RmPR���`�Q��q��5�%X\"�lY|ɥG�:U�\"���\"�P�p��!�p\n&-��f�*dil� t�N,��J���R2Qp\"\\^\0?���m��}4m�b��j@�b01�N!�\r�Q�R��N��/����s���O����Ef�Q�d�p\r_�/�D\nG������?�'P:j�\"�5@-�E�[T���N�\0��V��U:���½��>DF�Z�����s���\0�Yڿ�])Ȇ�j��K#�(���mkB(���Yct.�<s�\\��'p)��Եkn������īݓ���f��ׅ�\\�^~;t�X]yZ��\rn��=~��l�Q����G��t��؅#��~ċk�Ru=���F�!x\"���#�\$C��7-�׫=��`�\n����fF���<D��\\	����\$\$p)�~�+g&��J]y-h\r�����Ҁ+�i�^�#�5�;�9�T��sف|\$,�p�l\n���4�c�\09/&�������m%٫\n�n�A,�;E���G��p�	+(����f{�&�����=n8�ۑ(X�Ț��nh;J�ܠA����k8\"wf��1�ҋ�5�)WKa�P4n�e#ƪQj7E��-�s�c���J-�M��;[�/ Ŵ\\��d��V�7`���t�����w6=����]:x�	���1�9��[,HP<OZ�=���3O��<@e+�y�魡x�OX�ߎ�\0w�4���i���������)�\"�ap��^�����Qp�K�Y� K��:��u�����j����n����[S�\n�.Ty�����ȅˀ�%����~���4\0>@���5^Da��ɀ�=�p06y��J�e�,RU�α7E�8��k`y�kS����o�)�b�ֱ��iY\"��o\r�;�Ѕ�K������5�m@��~��gm\r��a�W*\rH0/��q��b�^���Օ:���j�/����)���.<޸ʸ2b��5��J��L�з�{�݈�D�Z3q24r����ܻ#�Xyk��c德�� �E�}=7����u��1���>�3��\\m�`<�Z?7C�/s��<彿&>gnt�c&do���4\"�;�0t_�5�F�rHr���0��O1��kk��i�N�bvi�t���^p�u��mEo�VOF���\rZ���4_u�8�	���܌�ޢ�\"��\n�+�n��x�F�=��9+؏��n�槒���&��~\rt7r��9N(^����'�J�0�W/���*�7����~\rY��G+�pRnX�ˣڞ�WJM����M�����-��s����C����5K� t���D���\\t�8,��sE�͡��W p�z]�����ݎJ�V�\r��}�3�c���ו0=O�n�}p�D�w_�w@��1����鈷zh��(��=���O4%��m��4����P�7�,�s��l�'��|��7}�JO`��.}�Ϣ\\~�3�\r�?~y޹+�=��C;��=P�O\\B�V��)\0'B�q�:��xD���R����vbyK���}y.�c�X��rG3���&\r�C�0>�|��MJ�#@��ܿL���S�=\nqב{�_����\rV\r��P}|\0D�s<6-�#��I��6fŪ���߭�l�%@�Fb)��/��F�B������e�4+�yzN����ba�����E��gT|�L�7�\\ r��\n�4��).�!n/Ɣ��ըB@ð�iL�����5����}G�?7���1�F/W����c>�|~+��\0����\0�q��L���]��q�d�AN��F04�N\nsՃ��\0��`�������g�1:	��\n��>�0���n	�A�*Ow�N���\"��������E�e�]��҅����G�]�o,e\"*v�7�&�i{���\\��4o�<�_�b�b�I�����1��^��y��s����6����U��?\$m��X��f��+�9��I^P�A���~-{��o���ͼ���rB.ǕÂ\$��d�9���.��n\\+�Oa#�\0�������T�H7��lo�~���W���x��+�.��YW�+�K�=���~�}|=�� t�����W&dt�J(�<��ݶ㚑<#��T�}oJz\\�1<%M1m�dv����=�߉����\\#~��_�7�9��a�\\R<k�9��Li�f�I�0K8�'pz/-�D͍w�f&������&~+�~!UdRҋ���Di��\r�@��c���nqB��|�M(ZB/�׏�!н#�J���p[|	+��c�MRb*Mj�w��N���ǲ� �\"�F?�����#�H���U�`��\"�h�������l�Äa�\0�(���<������T�n�?�\0c5�w\0�ژ�&\0���Ӹ;��&���5f��hZ(k#-��\0ae��\0��a�\"?\0[00�������<���lo��\0005{�`�\r��B�@<����7>`��	@�����p(>DT��@���+|\n����5�zC�.p�����պ�kɎT���YJr	�����{��,\nV��B`������^��[��3^����@��\$⠴�ۮ��(�d����xw@�9 ��4@��b�qj-	����Ļx˷����*�\\�5?,�X(������iE\$��)I30\0R`���\$ؔ`'O���\r2N&O�G��T�A���*R\0=��	��펤*�.�\\����z�k���	?� ���f2%�j^i@�^���M������%x�H%<�(.�NA�.�V�[������N��\0B��3\0006 ��(�.����!�;1���ʦ�>��\$�k��@F�#@֓N\$!�a\$��N`���!�]��X\\䄂RPi�^8�.���L�2��a}��/� �	A��t#�A�����Lc���-\\�A��ڮ�B���U����	\n�\"�«|PnAy\n!�\"�A|�� AƖt�s��J^ЈA�������~��� &�\$��N`\$�;:T\n[�w	�થ�\n\nEJ�4�_ÉhAʑ���\\/IW���%p�A����B�:S��'bN��7i�2P�B�/��p���]�BS	\\%���	�&P�YX�B��)d�\n���(��p�=HP82 �Õ�:��\rzO�@%	�2pݎ���UiP���(e��'\r��VB���PЋ��4�v�L8\"R0|CT�5���\0:����B�xRp�%ALp�A�T>�����,\0B�)�Y��i��y	�j��60�D\":��\nÁ�&p�B��4pvD/�D@��C\\c\"qA�`�\$�`0�J\rT-Q	�\$>�����?1���4��CO�S�Ղ�3�IX�(�@>���>0t�]\n�: �C�	0��\"�l��;�9D%\n(>h�xx5�׶�;��@7���+�\r�xB�>���\"'Nx�B�%�nl2�;B��\0�=����;�g0�����,	�A��F�0'�a&��h���`6?���|�����O����Rc5�A���\0Z�P��)���`� )�^yQ���\0���ښ^fS/�bL�ME�Y�	����\n {�a\r\r��1eE�8�h],Z�t��QKE:#ȫ�oE�|\\�i��L_\0�E�c�]�pF�`Qj�	X\"�,l6��q�ś\\ZQsE�x�����d��ؙ�Q�0ź<Q�&��`;��SCdq�E\"r��FT��[1v�5{��FO��M�1�TQEI0	�T�R�z�-�*qyF3�Z�7Ų�[`�2H�g1��1�\\�Ư�k`<�O�b�qF�Z���A�E�����T`1�E��m񬀌�nхF�doQ�E��8�F\0���`9GL^1�Ʃ�p���j�ơp��ƭ�qPE�x�L�;Elf4BA	�k�51=��x\r�3�Ȕ@�8\0^/�B@�T��\ra�F�Lw�Q�Be�VH���M48Cq���2`�3��jrd�䋀0�(�>B�P���o��v8'�P���i���8�L#ɍ��Q�1&�32��}*Aǈ��Be�6�=PO���\r�\n�߀�0�M����&\0���`'H�X	�#�e l���h�	#ڀ���(6H<����Y����`H+2\0� �R�H����\0�v\09	!���Hz�-������;AR\$\$\"ii� ^#pT���	(�!G`���\0�+h2F����fH�@a/Ⱥ��1���[�#�����ŀ�jqи��PH���0\r@���*�D0��ߪ��w��z��@2H�\"̊��Ȱ���L�����΅!\$|�@)����J5���D�\0�\$�:���<~��RQ>��4g�E�04�rU�LM4JM��!��>�4��r�\$�+\0�ژ!�^�f��0���f����!�n��d��6P��R��\"���G�� h���􆦓����\\Hn�������8�r ;�Z��\0�#��A,�J���;,�(0��&���\0��D/#��/@3��!���2\0�vyw�B���Kh�F�h��c�,�\"JNGa霄O+���@;�H8�G��Z~�;bJ:*�<���P24�a\r)&/���)�\$�f�����B�i)����@���L��RC�(8�3�ؘ�g�L��ɛ�!r?��D���GT�)�9*����\0��ҥ�o!���&��\"��B��.q7c����2�:\0�H�y��?�T�r��̙���\0�Ԝ����dTi>�63_+�2��������!`2��������O��|ï��+�e���\$4��\r@蕒���\$��`\0�|���D������Z��CKx�|��Gr\$�oK}L�`��-�5�+>Cѩ��O�\"���+�P�2��l���\n�+{�@�@�T\0��;#(�\"!�;<2\$� :?�+q�q���Ʉ�[I�|���J����v6�\r�؆\"��⻊\n��b(�ߒb�I�04��J30�5��&.g!+���xJ��`>�9�NΞW1����1U�wJ�4����J��p\r <�a�H@�� �T���d��a��T���|�\$��!�\$�\$�FG��\"hMRȚ!��l�:%���пF�]�._/�b�2������Mʬ��ß�` �Ӄ�+�Z��L��ܻR�K����=*��:��0�\ns9��+�����Z!���*=,!_ČL�0ѳ<��4�3G˻4l� 22�%�_\"7\0������HQ�BB�\"����3a4��0\n�%3��.��]4���Y3_4�հC̴���f��5���6\$<|�U��3|Ձ�M^t��eMi(��ҺL�\rlٲ�P��H��/!ӄE0?6��5Ͳ@��,ͳ3`�p!��D�#a��`d�@<ʩ6�5��4�7�\$��U7'>��7�fp\n͸t�\0002��D�V���J�݀ĺp���U!X�j\0�6�s��8��������'��+�*ŧ(�� �IH�8UM,\0�4i������	�j�p�C��6��awҢ��'t���ܟ���-�������.~�+Ӑn\n�~�������S%�t�ή��^�+?	9P�醄��D�a��){Aɣ#��RY�����������|��4�j�*�?,A%\08\0������V�@;ϵ/�+4�\r�0@[�H���\n͢�\n#L��	0�<��3	G�ai�ڢ&�*����\ns�Y!R�B(��O@(���)�.���#)�jr���hR\nr5W\0t��܀�x[�	\$#/#\0��;.R�rk��,�sӃZ�`\n���/=I�2RO�>��(\r�����GϷ=+�@)H�>z���\0�+�R�49\$1v�%��(\0!B�U%\"�DP�w3i��.��2ǐ��e��L�I��O��T�\n�2kjq�O)@l�Iy�t�6���g b%�\0��ۍ���(��̅A\0S&Ξ(�N�(4H~�����s@�-�5�`�3�(_� L�`FSЁ2S�Pzdɪ���*s�w2�m��jY�\r�&J����R�M�@��s`� ��3\")�B��1\0�C�Ӯ�\"H5�B�@��i�\r�Fz_����T�-�N\"	P2p��+�I��g )	�%�\09\nb(��A�Ɏ\r|ԧ��2!�Rq�Y!�Z�;��3��a�ʈ��O8t���o�ʈ1��U;!���LD�ma\n\0�Gd���%B�BK�O@�8�3���8M��e6�S�_6C��%+FJ����.��4c��DPRZ�`)P\$2\$����fG�N\r]2�\0����� ��sp8i0�5��\0�<�*�I D��l�oE��3ж����l�sH���M�\r\\���ƑP�=)�[,�l� &�����\$q9i�v�d~震-J\"��(�HVi\$��*��Ԗ+3�o�r\0�d�A��O��a�� K&)YI�?T�����r��1[4ŭ�h��9�ғt.������J�b:JJ������I�Ԯ{I��t�Rv�%'��R��,��A=K],�#R�J�����68�+�J�,�4K lT��\"�.����J%04�R�K�9�Q�0@lT�R�K�t�RiK�+���LĀ��R�Lm��R�K}3�!ҍB�2��R+n���\$9t��2�[#�6l2\"(�5��UD}7!S��K�8T�\0\\\n@E�6J0 (���	RY8Ĭ2\\}\$0%S���x���0��T�CӴ�  %�J�������Ӈ�0w/��R��@�82T�!�C�͎�4� ZxQԃ<�/��J�7\r6\$e��P;t��B5A+Q\$�9M!���\$�T�L2���P� -���!KD2���\$�\$��\0�+�e��B1��͠��D<�J���I����\$���\rA?��rR��62��_<�K�#1�5���\rx82�Q;A+r[zQ�ӓ^��)�#�7��O\\��L���Ln���&��#�=S��X�`4�-&j�4Й�!U?c��&�h��/H����9Xh� �p���\0��r/lM& D/a�@�bN�<\$�R��P	d*��B�2Pz��P{�0>�\$���0\n:�5\n\0WP�?�؏f�\"cހ��)��EH�\n倦�����8	�'��`\\�ڦ�\no!r���\0)�z��Z4>\0�8(�1���� 1���d�,��#WD�]�d.�X�)%���J�[T�S!�eT��0|�Tio�J��S��0��R`!\0��5��%@�2c�Ϣ*����� ���׀����\$E��;�H��2�U�7�\r5R�LU5W܅u~�Q�^9��*8��%�X�U��U%Y���lWtJ���M�^����W�euNVZ;=e�.��d6 �VqXdL�VFi5d�r�!�2��P�U�kU��D��-�����V���2�u���xg��@<� ��\r�E\0�x\r��M@\r�HEi[DuI�u�m�;V�[�v�	V�[�\r��V�/�e����kK�mW\"E\rrq�W,�r@9\0Z^BhW\"�-`Gt�-tAM�\\�dùO]Y+u��^��� %��WV�XQU�Wi\\�Y��V.p��]uuu�\n1]ug��[]��W��t�s�d��g�Y]�����M^\0(���vg]s����lQ�0<��`�Wd0��֬�]u~\0;״�'��w_�z���]�BAH�L;��W�C}���M`M~�ꃢ�uvCl�\"���{�a]p?�\n���u����`��Vׂъ� �^�\0006�^�xj���6iR¹�e@h\rvW�aQ�@�W����5�>�h9\0�qO�H���� x��hx�u�ET�z��焀�=�U��TM !,؃������\0�<�Aj�ebc�X�`d���Я 7\0�b����\nJ\r��O�X�\\�\r�;��c͆�+\0NS�`9���Yt��\0�d-��@X�T܈��\0�c�5��T�/}��\0��v0X������5c-�@7Y6����0�A9V�Y<'u�P�5eh[VW�)cV����l��4YacU��U\0�e��`�L�S��eM��a�Ob�I����fSV�Zمf �a���fe��U��G8!�C��У�j4�eu�ѓ��b��s����Ŗ�7Y�֑�i��^����i�T),��*W�^e���Y��wL>��\$œ'�Z\nm��We[�����Ge�@������`(�gy=x��f \"Q�Oi��t��6͂��_�6���iy%�E^E�5�H8�\"\0Z���Jd1�i����Zwj+U�*�j�֤\0�lѡ}ڙi�9�`\r��ڍjY6�y^E�3X�fm�6����5��W�fݝ�X��ť��}f%�RX�ke�V��Oh�CG�Y{k�Y�6�ܙ}�v�Y�l���W�|��6���f U6��lPS@1�l��6��52�n}�1ce������ �m�V��=�+\"��\rm=�V�X�=� �'l��6��Of�w@7�w,xl`�6�.pP��7�B��[Hm�����l�b*��_m�%Vߵ�m�O���n*�`:[�e5��PZ�ly�!�ۧd�U�i����:�P��[�:��\0�۵f�j��[�M����ub8��n�v��{dS�v�)7nm�V���Ҡv\0E~\r��Ѕ�d0�5ٵd�b4�C��\r��.u�V#5�bHft�Z�l��c���\\\0I���WO�ÌM[�n�ŏ!\\tx�Q9z`Uڂ+�76N��[�:�bZ 115���q\\��EcE�7%�Yf��b�WM��u�\\�	MrcAZ<M�p������f\r�7+\\�c���/ܟi��1��),\r�B�r��w9��@u�\"�Usݢ7>�sg��\0006�?b��VW\\���6V\\�t�Ά�l��6]��o���b\\�i=���c��VZ�t��7I�@��Bܟtu�\0��=uU̗K��}�w];eؤ��3^m�SW�u���[]|#��N\\�u���U��u����Ou��Wf�j�\r� �\\�<�����\\��Wd�uv]�֧�Mv�7p]�w�WN�N�L��DMEtY6]Mu��ַݿ\\���y�tm��H]e\0�Uaj�E�!X��w�ߡ\r]�`x>�X^f~CXb���]�w�����v���Ar���vT\n���Kq�j�7�g\\\$O��\\�q�qW+\\y��Wt��Z����]�/��/���藅�'C}総ؾ0m���Uu�&K_H�6W��Hi���Z�~�G�<�� )\n�h\0#&cU��\0�A�%\0)�r���b�\$<=\0�W��V��o��{������\ne��\$�ƛ-��>���)���{���<�鲨@	��^�; 3\0*�r�����f���Bh\n�\0�|U��^�|��w�D3{`(�\0V�\n5fv�d��*\0���3���=f +\0��\nC��ʟ��܃\n����,��-�ɲ\0��3�Ѐ����!&cVJxarՒ����|M��KX���_}}m�7׃E{u����:o��_�{@,7�_�X��&ZJo7��M��W�_�|�;����x`\"���]���C{������e��Ԁt-�@\$�����w���{�\n\$��X)�_�|0i����\0W�^�}\n	��v��w�_��X��e�7���|93�#�C~.�`6\n�l�r�L���\r_e�7��)}q�\0�_5����_B�p�����9-��\0���R�^�}�����q~��k��{�2��_�~0*��_�@�c��(u����{=�x&�.�(`g��)`q�fW�QX����Z�ҀW�ߢ}�wꀋ~���a ��w��V.!����m�W�_�~��xO߱���R��=�w��\$����dU�<��c�\0�V��HUx��Z�\0\0�*{\0'��V�`�&W{�	���l=���a��c��&\0�jfXkn�\ri�Uy}\r�u�\0�{8�\0��W@a\r9i�\0��8�qa�}�XY�ni�������a}V^5g��{#�'��& *�G����v�Q�~�d��>!X\0�X��3���{��x�z>H�c�x=��'�I~􂷳&V*�靧��}��)^�=񘒀W��^�h>P`%�w�E���Uz=����Ac�'�p�r��������=�e�%⑅�*X�����+镏I��*���>Ub���~0��C����b�^&��b勺fx���F,��U��n/��8���JR�(��\$��=�Q��X���bO�f%X�b[�����^��2��_����ˀ^��!��.��bh�4؜���N08��	����`���i�E��R+���bf >\0������+p�F*`\"&V=��&\\�Jf��b�4�oa֭��%����Y  ��{ �r�Ǌ��w��)�\\���׏��c�,i\$�=}=�b}~��#�e��)��R=�3X�V5U�.�H���0�����f0��'5��=Y	''�:i��V4�\nlx`d%�6A8��M��%J4(���*�`�V\"����|}*�ۀ�4�r�E��.�d^\n��\n�<�GC��U�W�_b|�F��a��~Yd��Ro1Q�J�l� ��^/x���n@��d���-�䝒�K8<�>�Ly-bא-ؖ�{ry�d�=����\n�~���\n�*���O|@\n��d�{�c�h���Pi�����O`��J)��C�n-ɽ㟓�!�Jd3������4��/�@e��9Le�nJX#E�h\n�]���J���=�����i��Tڦq�\r�yM��zfW�c��N;X��:��*��%VMc)��s��W�ԧ?��@\$��\"�	��e��Ucu��~�����G�	X������[��V�)�c���Ө�=~�&b�^�P`j��^���@	��������െ-���ar�X\np�aݐ�*�di��K	�䋓�����{�2oi��K����.ɖ�P�!\0��p��C\0��8�����=�P5^`ښby�*����O��\0���ar�>= ��V�!w���{c�g�0��*�U�b8�e�F1�*\0�-b�6���F��毚�8��f�\n�f7ŀ������G��i*�����,f�.g������gxk(p��j\0���h���i��@\$�\0.p��M}\niU�g�Y���x�ܫwAN	\0_�>fH������<=�\n�/�=&tw�߮�0��!�R�uu[�}\$���a1 �v��b��+�*���v�\0���n���!�j�#�&皝W��'��<J�b{����秉4�`!�X�x)�d5�x��H���	(����\$Uu��*U]կ��+���IVMb�%�����޸��ʀy&����W�~g�����v�F/x����u��=VMu�䷇���,��=�3�h,����X�ʷZ\n���	Ԫc ���g��\ne��'�{(8�V<��c=�17��H��4�hy�&��짷��U����ӏ{��b��\0����ɻ_�X�Y���0��������x〞=@��c���h�\nX�h�.Vx�.����8������'�E�@\$\0��}�W�'��\nZ�@=f�Zh�L��Z�S�Ɛ:B�-��|Xy�����Xߦb�\nĦe��CI��r���	��f�\0\n�0�o�*�{��P3@�\"J	�����]���n��/�u�L��U���d�����O��\0����孡NyZc`����ʠ��hػ�i��M���G_=����oH��>Z�O�߈vfY�a;���߷�&�������泄���\$筧�w�~ehb~���0�ބ���1���I�'��nQ��a�N�= �>��a���\0j�j3����\"i�=H��-�a�cYgV/���u�`>J}��N�@�٘�{�(�YbX�ط�'�^��Bh?����M网-X9h��YZ�8���`� �e`,`}.I�6b=N\r�V���xx6i�8�	he����g��z��q����c����)�jǈWU]����Z�<�J��AZ&b���U��z��7����N7yq�i�گ8������d8�j�I�fo�Z��>g����8�d��x�x�\0��\0��ޯ��|�XS���,�O�ek���~x���a���?��\0<��͢�l�����8��`�RO50:�������^4�!�6�!Ę �j\\�!��!^�Ӎ.Pa@�ӠT����~�5��gY��?��(��?Nɯ|�����9�:��k�n��`��E>N��F����A�N#�;�%Hz\r�\r1�l�X\r�6�\n��T>l&H~�ʂ�K�h���!(Xe~�0]@A�S�<��6��\n��:�lW�/�W�0;HW��b*�:�_@0�J�D�t�/�}��\\�\0��`<��=��cߦ˟8\n`+'��N��� X���_?���i��\0(	��hG�UZ�&���*{Xhdm���Ilџ�Z9f��c[7�ճ�Hxgg���el�*~��lϫ�����{=W{@�F�i�m�]��Zߋ�b��P�:����g%�����%�mkUE�t�H����g������\0�(M���n��!.AO�%� _���Y���<�`��Frc��&�h���f��U&��`���D�Բ`�K1��SN�\$��bH6���2�\$�JrP 0\rv�z@���H\$(��fk� ��@�m�v��^�\"\nR�#Hg%��`��aȝ�INm2@��#pZ�R���ۇ�~	h���3P�n`�zKj3�h��.�>\0��5{k�D�@h��\0ƿF���!A2H]!t�U!�ǒ�>�!@1�g �5�N˻!��HB!�T�]�!e܂`�ȃ)e�Ȇ@�: ��D܈�M�II<�@8ș'f�\0⍝'ɼz�1�ykv�����2��®���Y�d���u��o����ûɓ����(]�K�Ǽf�ҏ��f�Mż ���<Qܺ��zI\"����	b��%\n93�ܖ嶊1��A�oO����m���h�̉L%1�҆g&�ҷobEL3	�g�a���;�HD�n�.j�\rv�Y��dA)�;�xj[�o(0{:���EnZ�k�;����2&�ӱ�N��;�o�(��j�}�\0%[f��.�2��;���̄���01��7�[�����>o�v�S�#�3_A��＋G����Hh�ތ\n�����9?�&��l�\$k�/,/�3���(�ɧ �))������yi�6����F%�n6N�|�>Zy_�\0�A�8��p/�|\0���m7I�0\rM�a�\$bw!l��T��<��([���T]@5���YOf������X���f\n���@�fu��|ʵ[��@/�͹�2�nT������\"\0���\r�K��HW��S4@mA�\$�<:���\r\\XiO���U�R ��m���0*��f�N.�ŧ��n�D�I�A>�`�+G�D��T�K}��e��zG�\r�F��a7�%-=�<HS���>����\$0>���d���M�O�>��\r�T�\$�rM}��]���\09��(XM��'��q�p�h�7������o:cI6�B��7N��R0�L��qN\n�5<v��	\r���6PF3�q�D:BA7O�����f4ӗ\"��1� �oչ��k������:�c=�9׀�C[�_r'o ���Q*%����Rl�8\r�I\0007D��\\��\0�)Qi�j�����Q�@�*r���8�c�To�<���g�׉[r�Q�(����>�[WG|��KD\r�HD��%�W*��HV��+j!D8`�	m� |����%�[������[��n�;��P(��r�W(���{��/\\��{�7/���2��)��/\\�s	_-ܵ���ܖ��̀\r{r�E�2<���̥��Qw��/|��A��%|��o2<�s\rN74�sE��2��l?��#��a#��DP�d\r��<��Ǳn�\\�li���4~s���RB��'+���\$�C5=mC7��e��b=ԮY;�]�m��T>	�r���A}�;�1R�<��1�}K�E)�x���&_�a5C�U���\\Ϻ��%q}���]s� �7|���e0#\n�Ш��t��AA�s�8�>�	�<��2���CNt=1�\0�>�*�=���!fU�#�H���X|���tb	����m�!���C7gG{r���8A�	��E���8�H7'\0�ì:������=H7I�j�l� ��x!a���<!.���.�#�'a��U��s�Gv�aB�`���N�\"V]�a҄>BC�c!�N1����5�=�wФZ\\�t\r�/P�!t1�_<�F�`/�;��@2'P0��-*�zm�t�����A9��{twx�=O� \n�T9I\r�O�M����A=O�����t�}v�M�n�v0l7�UN�O`���X@�\$�a>�é�}5tʨ�@}is�h���S��H2u��)��K�I>}%����`1��6��rrn_�\r��0��i��y�����G��װ}~Yw�	����5���~G�׷O��}GĜ݆u��7c��b��/��d�\$\nW�5�L�'t3]��\0�M�</q�a<�K]x,^ce���ga�h@��4���g\0`0���T��=��e}�u�X��׀7=%�q�0fa���7eRw��D�e���h����n�b�+�ع+}�������%�OO=Avl/Q\\\\��wP���<֧nV�u�yi�fv�e��	��<<����<�p]�w\nK�^�����a	�k�Zݡؿ`�a�`#wb�Q6�o=~p]��gr��/����u��_r�~��	��^���or�ހ�ؽ��I�;�O�cȆqX�1)��@\"|KX_Ҵ��j��UWhv��/@��vB�+5�[��P�{�8}�vT��Ƕ����%}�ve��ݰ��/��wv. ��#,]��pP��/�ܽ��E��r�.�����]���!�\n2���Q\\�ˍu�6b���p\"�l�Ʊ��8��v4��\r��O�3u� 6v/��2[_x�l�I���׭Fw�ܷ��~x�H7>�Y��F���J1�;2���<������χ�Z�\"��� .�عt��HT?}��K����u�dw��Ot!P�}=@1�=��\0���u����wq�K�� u��?�������ܵm�7xnw�`^7P� ׎� ș_b��W�ؿ\n)y�6���1]0�D���/��_��ݷ������	w�\n��ㅩ��M�o�w�~Z-�5N�-��.w���HxO�D�ٌMؿz!Q��S�\r��p�����ȳڪ�a=�T6g��\ri��m5����y�a�J	y���it��ro�\"m�\r�>mf\r��	]�/�ՆLw�����r%�K/=�u�����c�wP]�w�]-�,m�����w��\0!�����	#k����*Ϙ��G����w�e5��]^rs>�������i5��	'�u�x+�J��J��@}�������v�ͬ�qa���-@�\\=ɇ[d��\r��_\\&�����o�%������dP�1���m���9�7�g#��}Xy�ݗ�>�)87n�T�ف2�w\r-t�@��e�[��o�w��-k�e�;�₲ퟭN�n�⩏�{�ب�����\$v�O�ޫ�\r�﾿m����u�V\\[t�x����%�O�P�r{4N�Q�5�v	���P	�:���?����]!�?d2�L~���K����͎�J\r��;ce�V�=�?,���?ѷ��h��H#�U{��KA6��j=������7n��)�����į��������? /���O�^��BU\0���齯�>m�~|�����l\\Hv�<��=�����^����/4C����?^�];�K���L�f�,_���\$�D|���F�lh��^y��w���y�۱`��e~|F��!�>�ۏ�}��%p��M����U�����\"S4���T�n=�}���u�ך��v�!�i~�k߷M�c��7�T�R���=�|{�Gn]���M�e�������.wsǷ̀�{���n^��}��D�4t���j���J	��]�^+�w����󗣥U|��������_X�TWƫ�T;�����G v����o�)�0�}��#w�|�41'L,6�<\rm	��ymߧ�3{�ͯ����f���(��Q[�K!z7ү�a.u���?]t�8e���{�%�������?c�r	g׿Y����\n�<ܐ�\\}e�<�C\"T��\0*T|������Ϩ�>�^V<b=����w��xx�E���jY� ��]������`}�b_I�RJ�^���>ɡ1XQ���/����}�ۈ�����panL�nn�aFSU* 53���f�{z{\r��Y�}��[���=��8V?Ns�˛�ğ�yY��f��~[ۀ�6\$_��R��#�䝸�9ׅ��p��0D߇���obf��\r�7�r~��O�T��đ\"�>L�����M�K�\$���g쪁��~��(���X8�'�x'��Iq���ݾUV���t�P?�b(v��xN	g�T�֧�=j�#���ƫ�k�Q����}Z�\$�8Js���潼?��D^�u�S��o\0ɹ��e�L��V�ĩ7�?V�.���jv�ޠ7u��U�/�ݸ�y޵���)���]*ش\n�����'^T(�r~��\r���~��eU)�h�������y�i��� ��Ril�1���Ν��7�?���\n�D���e��˺�BS�s����uk��@��B&��`I�� U���@����ZE(�v\"S�B���r%^ʧ�L�D\0^\0p6\$ �vXw��(��W2 DK菀��V�.6]�\nb ]�\n'T\0�{h�B]\n��Y�/ �Ll�\0� :�\$;v\0�,�Ħ�`�FVP\0�\0�)�@h`�\"a\$�Nt5k\$��#�dB�\n��hЌH\0tm:��*�dQ�Ɂ���?u\\z,�����CV܍�5����i%���OUOJ���^\\@��rb�%~��Ώ+U!c�]H���H,BOL-��e�E[|�4�-b�;��|V]����m��e}�*�k]�ڔ�=�)�-��[]r�xkF�Q�lX*Iv�\nd_ `��[�.�<�cp'����pݡ\"�1`_;�����MtZ�5�i	F�RX�Ӫ��������h�IOq@�Y H��\$z�Q;@aޮ�)�/{�3�}E�|�;���\0/0�h ��5�S�d,�\0�T�r\$�@�(�	/h�xK\0��6��J�!#�a\r%\"	ܷLY�6EA&�]8v\"\nie۴e��:�,��\r�x�t_�} 7����:����<���Ҁ��ȫ��V ĕ�/XV������\0�����aV���&Pl�Ug�V'&ݡ�� ��A^O�q��{���D���d�V���w�\0�a������zf��_���X��U�0�Q�U�4*_r��>I4͞Y`�d�,\n��D���7Aul�m��� A���U��\"�[�V�e ��qcJy�3V'���#3�*����83���2X&���#vc\0\n�	��b�����fR�I��b|����fG�i\"���jM\$=I�6�N�/���Ǒ|@.U��@(+byQ�\\vP�� �/�cKՋ��l�|�ڳ4��^S7�Wl���B\0�Mj�M�@Cϴ�`��<��Q����`jM�?X6�j��U}9ZVI�m`�A�'�WH=�!F\"P�`�2mjq�>c3�p� ݱ}c��C�l�!�x&}��8D�+W���dՊ8D�VZ�B'����X��k݃�A�'�2���@�2�c��A+;?	l��l4Oh<�̞���Y����	x�>vL%�`րE\0���%�=,aX���d�N�\n�@fUl��4����\"|&����XTA�g�	�+=�N�`�A�&�ǵ�(h;pwa?��� �+Lh���lb��\n�I(+P{��0_���	7���|��Z���|�@�h!1B��ƪ�xB� ٳ���V\n�(Cp�Cݲ�x��H+P����ÄR�\n\\+�,`��I�c\n�s	��ܡ_�9b�V�F�	���B�c��\$+P���BEH8O:D�8\$�ϰ�cжs�t�W�2-c\"��\n�&XL�b�Bj'���X_���M���9:x90�!=�a��t=0�P0t������\n�|QJ�!FC��]�3 �b�y��B���ᤔ/�S�|!|B���\n��&e�0Z`�B��<�u�K�U�o!W��y\n��;�_�a���`-\r�/�C�o!�u�G\r1�l3vW�U�&�����l,�7�ȡ~��co\rv�6X[p�!�RaQ��\\3�7���\"A܅�V4\nl/�8,%�/�RMI�![֋m4��4�(��՗T(�����¾c��̮�5�<-.�\0Kl���=�2�CLؚe�lj��X��! 	z��/��\n�)>L���/o_��Y��/v`�\$*�^�	Q�;\"eY�`�4�d�Ì�t+��l��@C�+z�\$��:��f��vbG�;fHd�a�1���=3hyj��DB����5�T<�\"�@�.7o���k�g��B�&6��mO�}��!�T3��h��	({�![�8;p���36b�L�	���f��`�\0VpL��4.f\0�͐:ְmiZ&��iO�j\$���ES>6�<+6�S��ʳh'Oq�c+F���[ 4(g��]��|�0�	ɤ�'�dL��4��	��&O��*�%&^0l���g���/�l��*5\0���\0��@�-\r�3�l�Ω,&�� ����Ϊ�DF��+��g�u�+;vwl^�-6Cc�a��VN�RX�4A+�L��U6��C�4hh�\n@�F�k���0�h��ؼ)Wx�d�Y���'�P1��<�Fp~T�N'���+9w���R���ib]�C/6���3�^���2F֝0�D�H��H�OF�� ��Bj����i�@�P'��5�����M�{51<�p={W�Y�e�'6BcO +K`\nq\n�ٲRj��=�Lf7�_X���&�����XRMb�_�Mk��#1<LH �4�g���2�v���\"��m,�D,3r�`����6���0�t�!xۢSn���!��P-�����S��U��DT�f�^%^����I���A��%mt�&��r􆀛R -HWy!�C@an�� 7`=*⭶k��E^��Pm��%����%��ҷn@u�8wv�-��?�!	�%+�����:6�}<ۭ*Sn䇮	��!�K���ba'�a��<�)D\$PP�QX�Г_�M|Q��n��N��\\Zv2=�JCE?��[X�i�ߕ�3�7��8tkz�qd�|��j�q�/o`��&�aֿM�з�%l���,>��>_��|_(2#wh��ݖ��vz��D�`�@�7�o(��tX�?Ð�%\0�9���������/UzA��3�o���=Do���,�Ow�/�\"�P���\n����Rw�GJ�v�\"\n�A\"�t6�k���L��w�\0��S��~��;��!N�c��\$�e!�\" FOq��=�꺹7^������\$�y<�7ߘ�J�E�O��z-X?u@~��Ÿ41��k�κ�`�#�to�`7�ԣ;0�/�0S~V�1�Sk7�n;�0k�*S�\nI �	�b���*R�4��%!�	ĉ�3q�����'��\0�,�6�1�cQn)��:~*��1ӎ���/��	�H!��vh��f�E\0��1���`R���D��n�8��p��h]�R2��t��3��j^R��K���p��\0����k�>���W<�mM8�&*xчP�⧻�p��\r��g��1��-\\�	M��gG1S^�/E\\�\r5��α� =.�{�f&����[B >YQt/ ��ѭ��ƟzL��pT��ѩ�Pƨ����\$jY��-F��g��2�1��FF�L�;z*tRǗK��B�:�j42��آ�cd!�Y�.68�����F����n6����#`?�[Z6b���ѷ�n�tTz`����ю�:���^�D��o��P=���4ϔm�6@��`��>I9��h�Q���\0rY&��8\"���huc\\��}f���bɪ�Ң-���>��Q��Eڇ�\r�B��B�F'Or�]�:����|O�?�\r0��d�Jc�C&�P�7�sS�1�㜡�\r�\rY���OQ����Bu۝<�tH{�ѐ�V�W%�t�HN#įƌ��赽�tB�O���m��+�b��o��Mj;0@؂8ab(�Q��\r�F޷�;����1�O�+~��9�_��q�	F�y��;�H��ڝ6?gT��lwՋQ�Jǈ~�)���'�q�ݺQ�-�÷H���Ǚ�\n.�<<y����>/+�AP���:���\0	逴�u)~h\"�'R:��dz=��UJ�[���oX	c�ד��U*�f��馴�E#�Gȏ��!�C�7*1�#��WHk�c��q��,���M���Gi��ǹ�a](��61��G�3]u�\0���R5G叝�!D=�}jK��Gޏ������G����=M%���i�;9Xܔ%cҤ�n���ŵ��1(��趎�ߨ;t���v���ѯ���!����B1��	��H�~AXX�bwR5�q�\n�\n)Ζ�����Ģ�;����JM��N��<O��Ϛ�C����*zJ��O�ބ+�J!P��n�o��|5�m��@�� ~��G#�?}�z�%�wߵE�KN't �O6\$�5��2��lr�\rbE�����#����\"���Jl���������8�^j'���T#�F<WK�*�\"��&\0�?����S�N���㑭�U\"M���8��Uc�@�\\�Y�3BS&����w(�m���7�w]�/|�i�<�ww�I�Wt�.|��Y�s��N���'ɑ����K�'�O�\$hEf	�DM�#�����J�e|�!bG� m����u(��G� ?29	:�| ��%���Q�W�s;Uy\rUt���i�H\$}��E�*8n�����]���G��@5�m����0_��CB��\0���H��fg`���.��S����O׏=�T��8Z�E��ɬ�p0�Mۥ�S_6<�~�QЂ�	�\0E�QKt���\$�'�K���%3\$�kr�ϯ^���`�!��a��O�X:�I��ZFd~7	I꽏\0�K�;�UI�c�C��F��M�nT� ��6=\\{1�U[�9`�]���,�.��d�m#�JO�%i�SnV���㘧�|Jۙ��U���Cŕ1 �;tSUnN�S�Iv��F:k̈���%��2��N�W���ߗɢ���	�X���\$-��~^��-rK94���2Ɇ�b���s�x�ϒ�_�lut�M�ui7�mSҺ�w=&:8f\rz_%:l*�e��w�R�r�ЎrB����R+�o�5�{�צ-���90^*��4��	,QgO�>�m���^t��u¿z]�@��>�@?�%m%5�S��o�P�f*,z�}<@1�oBB��N*�U���R��-QRM(Q�\"�	�\0����(\\��2SQ?�LJ\n{I\"�P|��S%=�8�P|\r�Eω���	��Q����ҪI&��(��P(�G�#������^Q����]���MPx�c�J��G�L�)D(3����q����e��	K��W-���!�`����Ӫ��Qv�Is���\r�(2JDO�%?�ǀ�d\0����E�r���^u�Đ�����rެ�G��}�{n���Qj� �u��%G������u'%����n�FHym�FCd����d�����	I��#1�eV>9���U\\��.��WJ��!�(*U�oT���� a�۪IX�S�_��vL���l��5�A!:L�&��Q�����q+h_ܤ�]�a�����~��J(��n��+�pv߼������IYP�����I4�t���P\"�w���e��f=\n(^9@��%\0Jƕ��m�\\�������Z�}��<�T�H�6-��,p\\���N����@�,e�\"a%�O���%�W)5.\"��e��˹�U�WY.%g�к�B���)4;%\$�,�D�%X*��hJ�@9<D�\0!%(c��n�\"��\0��S3v%�\n\0�@T\$�J�Z��j� \$K=���Z\0HJ�e�<���q���_ha���V�^[�'�e���^\\B>��N�؉qbE1��Aۨ�Q�/,��l��S���4�����a�\$PK�\0�;�W].^K��s���+��F�,.��e�R�+�W�.f]D�Ĥ*�ջK�ڲ�<�pa`s\0�˸X�.]gX4�w��](�ŗ���y\\�j��+m����4P��WW=K�^p:^���y\"c%�˅��\\��z(����XR�~,��O�u��K~��/aa�)��׬��)\0�{;0���K_�d����g�0��'&�@���7��a��l��A� ora��Bc�&�+I&A���cgO��i[�x a᳚��O䙣D�\\�aa'3�������y���D�_\n�5�|�5�,؟�a^��фk��s	��<`i0��b��t,1��L:a�хDĖ���=/��RQ\"a	<F���\n\nB>J�.!DB�g�C߱�g�ψ�#>�<L�C�3�g���W�J�!@�`g}�\"�E��1(\"k4_L�\0z��ذDj��б��K�M%3��M&#�G9�\":�MJ��F�t�q�TA��X���2}��ٴ�*��ԭ�HF�1�D/�e��u�#*����J��Nc�ҭ��x�����D�*��R%,J���\"T�[+z�\n%�52���@#��j Q%�3���\0Z��x']����AmJZ�L����z�M땍��L��}\$�]�cYW��e^�>'Bf�l=��;]G��q�@�U>����?�ڲg�ݖՋwV異Y\n��D�G����R��&���鳠a*&�H����a!2C8�>��r2ܘs��h��[�n*�9�ҝ��ƻb	ב�	\\�d��>b�)n R�v������\$�*��XV�k:���*��Ix5!�G#\r�����r|t� ��% �J\0\\�`���e�G/̬�>Nf�L,�3rP睸�Q�;��/p���ۃ��r���X)���'べ? ��`�=���ZE�TY�`\nrJ���q� 0dm���E��s,�)<��,�)�G��Bxr�&�k\r\$S��|r�\rҬ���Ԅ�85t�'=�[ȇ�N����naZ=\"{wя:c�Uth��S�����H����\$%+�*�h�����(th��Fk���ύѷyp�*nl�טa|\0����b�ڪ��a�&�,PV�U֤��nC?�q�_���i;�����8I7� !�~�;|&;����i��2��7��_���	`�0!V+Z��T�1�D��\"�Ӗ�!��n=���%�-	Z�UjӅP�V�vZ� �pJ�g\nː��:d!Φ�N?A��V\0�UD��ؐCA6�4:d�� \r���/�p\0��P��\0s�r\0��rKF�#s\0j\0���Y� \r�\0006\0k9Jr\0Y��'!�8\0o9H�H9�3�\0\0004��9Jr��̳�\0�I��8�rl�ʳ�\r��2\0s9��)�s�\0NH\0m9�r0p�\r�ND��8�� �'8�3��9�q�����G\08��:ۜ�@�,I\0g9^t��)��'�7\0r\0�t��9�`gFN;��\0�uL�Cm3��PN��u8�u����`\r�(��\0�\0���Y��'Nw�|\0�t�)֩��`\0000�a;u����gA\0004�{;Fs��9π\0�6��9���ֳ�'\$N���\0�t��ɠ��g�':(��y��'`�X\0o9>ul��\0\rgq΍n:Btt�9ϳ��yNM��;t<���3�'TN���;\0�� s�\0N����x��i�S�gW��C:x��ۓ�g<N��I:\nvD�i�ӷ\0�Н�:\ns�y�s�'{�h�8�xx��s��eNl�7;�r<����gSO@�;�y��9����nO�w;����@\r@�N��;�y����3�g*Χ��8��Iߓ�'S�d*�<�y8I�sʧi\0i<�s#s��S��|�r��;�������V΃��<�s���'GNx�<&{|���gΓ�a;�tD�i���tOl��<�s�������t\0i<V{�Y�ӟgA�\\\0d\0�q����3ާ@Ψ��L�y|��s��kO��w=Ft�i�S�'m�ΟG:�������N��9:N{���3��1��9�{����s�g�ϸ��:z��I���'SO��7;����� g'Ϭ�w=�zB\0S���N<�}>*xL����'�K�c8�4���'�Nj�?=.}��yﳧ�N|\0c=F�)�T\0gL�Ȝ�>�rl���\n꧉����>w��	���'s����9vx�Y�ӚggNX��@�u4�	̓��Nj�O>�������/N��G;\"~�I�s�'?��3?]}YⳲ'�ύ��:|��)�3��!O���:����	�3��%�3�k9�}	���g�O~\0a@�r�J	t�1�T�G9�z���3���N�6�=:{t�Y�ا�O���>Nr��S�g��R��;�}��S�'v�]��?�����S�B�h�WA��i�s�ʹΗPqA&v�� \$���O�;ށ����֧oP+��>����S����o�IA΅��Ք'�P>��m�v��ޓ֨J�3��A,�M)�Ӕ'�Oi�[AvrM����tN\\�?=r{��s���O��9V����	&�hNS�;怕	��hU�[���s��J3�g�ψ��B�}\\���gTP`��CVvl�Y���\rN�sA\nr����'PΨ�7;����i�Ә��PV�5;6|�Zs�(��_:s�\nS��=Q��@����͠\rg�Nm��BBx��*%��*O;��>�ry�T7gY�m��>y<�IГ�\0N֠a@���ӭ�p�=��9����I�tD�N���A2{I�S�����\0a:����7�g��Ȝ�?�}-Y�\n'<��u>nsm��s�(�\r��<͹��Y�+�l���B�u��Z�h��Z�7B�|��\n\"���'O���9zr�'tZ�D���AZs�����g��Q��Dbt-:4R'��h�?=΀��*��g�P4�%>Nt%�ϔKh��V��<�v-	�n'�Oj��<&t\$��!S���N���@�}�I�Q�nN���@Btt�*6�0�~Q��YE>r��sΨ~Ѿ�G\n�����V�a�G���*\r�RhR���8�����\$���@��=B}4���R(\rPz��D*�-�.��'xѓ�9>.�%*&4M�6΃�u=�yz'��g_O��Gwe\nJAS�(�ό�[A6���I�4Z�[N��E�u�\0����'��G�t	T�E�'��眑@�y��Y�'!Q�o<J��Z7����L��9����'�'6�A�iG~���*\rTS�QݝF�E��s�\0N��>�y�:I�Χ�PL��:�v�p���(DO��u?r�j3k�ѯ�OIR����%���?�;c�p������IC���� g����<\nv�!�LTc��{��@�w�����(�Ѵ��H��=%��Tpg\$Oh�<�tU(�ST��=N���?�-�-������IBZ�-�\0��h��%�uA�u|��B��\0�ϟ�Q?V�]�2���ґ��F.v����S�'�Ҙ��FⓌ��\0Ih�Nv��D��]\$IՔ��<��?>���:�(�����<2}���3�iPb��:b�=):Z�iN靉J���*�[T���X�[J�\\�*\0<�aP�C�z]��S�h|P���K�u='ZTj�>ҧ��Fz����3��O\n�!9��<�zAS�(%��?>�U��tC�\"ΐ��B�}�c�Q��O��B��\\���t{'\"AD��q,�vz+ݎ#/�^��1��N�������_�͝��o3)�2�i�Oi{4EL�أ�|c��e��5F(��X71:@�ݦ+�l,�!33e�YM��40EZ͝؟��g30��l2�����yh\0�L=\0)!J���M���p�ii�C�f�\0(�:�Si�l�0��/���D��C���q0z����@�S��.����Jl���P�Z�y��6pt���1����&p.R��M�6ӷ������86+��z�_�	~�<%�*�⠸LY�O}#Bjx��Zm0�Y�%^��m{��\n��Od�������.�׬GZjf��sG���h���sB�.��@3�'G0�5\0000�\"��CeT�eX�	%b��X�����R�9[�1�n!Z�j��m��H��LƉ��viPU C0d��*CuH�=��A�\n�E��ӣ!5��`0����rԎ�H����qA����)M�����\ra��_\"L⡫vcL^[5�E/\n\r����p|\0(\0�M����h����TS&�Lҝ �ܢ���6�\0�E�Ej��@)��(��\$�4V;�D���k`�3I�9Jk5C��X�`�B�S�w�����\0���3	&�l\$��\0sk�1�0_�\0׸TeWP5׫_�%K���3�f���/�a��@W�K2��:�R��6�v`aZ{2��}�M<)!g��g2Q�-JE*��0�bvٜL�4����2�'���\rJ��̈�Sf`S\n�6���V�n4����T:�00�YvA����,�l���w���k.�4�ôa�P!�{1���UG\0W�ŮD�I��;��z4�>���:{@\n]�EEGR���;�kS�c��*ZfOZT�|\0\n\nԃE+Rʚ�P�dmY�1��ā��4�\n�\0����:���[5�l���VF��J�f�S��\nU���	(+L���Bf���&l�;��)�A��uP�>`���@*�ԧ�sS&��Hy���/ă����\$�mMSj��J�r�\r��UL���/���	3�O <\0C��R2�xz*�M굴b��ť�F\rB�9�䪬�ݠ�'\n���aU8��]ᡈz\n�L%!�U�_�R��4��V���rb;V.KG:���/ܪ�VI�,Æ�_��3R�(��=kgJ���\0�~^�0ل+�K�)�Uw��\0��\\)�Y�eᳫ�����)J�Ъ�BStd�T�L3�k��3?&�Ui�S5�/�򩳲6`]	��n� *c�9a�W!�E]�zUtY�B��z��5F\\j�:b�UZ�6�uU*��ib�M���,r{P����̩QU��ETJ��I�3gl�Wq�M`O��`�B@��\n9�U���w\0K�Pꚝ\\@h��l�����4V��j�A���XnT6꧍����٧�Xq�{��p�k�F��W�c+ʩ��؟4|`�XŒU:��\$���Q��X�R�z�\rRk2Ja�� �1ʬ�Y�������ud��%�@*�q`OW���a�`�[��VG��M�=X�U���S�����k2�ku{+-B��Ҿ�&%d��\r���{��Vu�Y�=sk;���=VL=�+��U�W�U�����UhJ����~�i��X���uZ�}+VO�p���f�mU��\n�,�y��i�mUq�Q2���T�}]Pl(�XB��J��v�u�W�&� �ޮ�kx25w�BU�{W���^J��y��U��Yb�Ud��5�X�Ifc[2��YE��U���VªMmZ��\n�'��t���m�m��ք�[~�m`�O��'��z�ް�;J�PmkU�f�T���b��M@!���]R�����:�`��1� ��\r�c:�̊ZaV7�^�Pmk!U]_�\\=��W��U���A���Y:��z�5�aDL�k[�MW6���j���Yp\n�D��Չ+lU\0��V�-rJ�5a؎Vq�uZ��hMQ���?��Vi��tj��fk@ա+�[���i��mXy�Pb�V�\r�h��5˘�V��i���s�ӕ���V�]�-i����B\r�'R�%v�qM��TṶUZ���u�	�t��V�_\"��#9�B��4ֲfN�2�S������%d�\\�%^2�uܫ��(��UҶMy\n���X��˫_M�mj���k�Vބq[���nZ�P̘=�����갃<z�u��fV�b�[ư�\0�<䦁<q��b(q�ٖ/��w_=5�pw@/�_�H����+}UC`z����v�i٤SN=_l�}rmU͗�T�P�=|Xj�����j�N��:�s�'T�_�ѱ�2�3_I��	��\0��.�}:�Wv\0�\\�|g5_���:�,L7�/�4��}��S*x�4+�4���8�2�y��i�L������?���Y�D@&d�	�}�e`��#�.]���f�vko4��� s>zu���2�/O�5<zh�W�Ua\0���&+te-�� ��f~Of���uQ��XRga^���6Z��XDUni�Onpy�\n4��X.��\\�X�:�uf�>U�a�`r� zz6�2�\0�aҾu�-�,2U\0l�a���+�ک��B��O���?H�@k0,�f�����\$�,F��(N�#E���GX�\0�V��͉v'Wғ�dT��B��t!3���b��KV�7X/���F �];�\"��2�=b������/laX��«\n�%���M�,-0�3c1��[63�:��a��>���5�X�T��1�U�FN�����Ğ�u{&.� ,u�i��UՖ��6L]���새^ǕB��,3T+e�c���,�s0���W^��]q��QJ��+��Vެ�D<�A[ ��e�\0�l}��@\0��W��̓BՕ��\n���쎪�(��*{\$u�I�R�VN�Z�*��^k��4'�b�:f(��#�h��h5�-�<�(m ��|������\n�u�-�Z�,�\"0TS��`�=-�fuA�TV`__r�;,,S��3S'�Ў�UV���� �Zk'ev�X6'Pd���B��Q8��\n��l��߃�PdA���ޫ�Y���f����Q&��gYآ�A�m�\0�\n�1�\0��˸z3�����Б�3��׸5j\0���[\0\n\n�g&�&�:��i�ŉ��ާ�T�\$�EBw�[I���_X�����)���eA��{e�)5��mM��Y���R���e��6T�bN���	*��*O�N��Pƥb�Di+��5�=a��C[<�l���j���\0SU`�LcY�9Z&ZŔ{1>Wҳ��˽KHB!�XCQ��@�HKp��3�	�|��+��VQU^L�a�f��6s6�UX�e�Ǌ�U��e��2�g���}\\9�v.*]��x�I��&M^�h�[����-�kCa�)���_�_�����́l@B���}ګ�	!bY\0a��&�9�I�2�'��T�M��� C�\\��ba�ih�À�kl�Y�cl���lNF9v6��U\0_ia��~;�>LwavP�\0f]�k�4\0e�T�{\rhE̔�W1�ù��[Q6QX��3i�f�kL`\nv����i�S��i8vv�5�L�goj^����6�Z�bƥV��:�T���(��+qS���u�p��e1�(����QFyvaɜZ�gYj��`:���Xi�A��\r��`k9����08�M�������j�3����=��> \n�W�\0���i�!G�lA���T^�\0�\\A\0002Z[��R(+��^ QcW�����+Z�\0hCcԹӛ��Dt�il�hK��U�K�p��i*� w_ ��6�1��\0ɀ.#�4��3����\0f\0�yY��`3��=�HKl�1�G�q(����d\0^�1Ĳ�m<\0\\���S�NЀ0�ⲉ`�o�N�z���@�L���[h��s��\0��\\�h6ӂ��6��&�/�S������H\nr\0���;k�I@�a��mfv�l�k���=��e�+m�IH5[p��m��e�Kn��-��J��mvۜ�m`����V��m�����p��-��`�m����[q��Ё\$�m\nܭ��h��m�P��/m:ܪ)�rvЧ`�ܷ?m\"�-�[sv���O��Mn��u��uH�m�[��]n��]��u�\r-Ӆ��]mB݅��u���ۀ\0�n��U�[q��[ʶ�n�ܕ��u��mހ_��njݥ�������[���n��ͼi���-�����oޕ�I���-�ۈ��oV{=��}��'��ѷ�ozޥ�[zӷ-�[ݷ�nz���Ku��m�\\��nr܅�+|��ҽ��p\n�E�{p�\0��\\��p\n�M�+��U��d�;nZ�\r�˃����\r��p~�%�K������nq��;�VӮ�\$��p��]��hWn�3��9���k���-��@�sp��u������@�#q0����.!����ߕ����-[��Uq\rA��y�7�#�|��?�����Wg��b�3qvq��+���!����IV��kx��2�{�?q��U�K���;�o��ru��Z��nC�_��q��E\rˏ�\$n[���r��\rˊw&hM܊��rz�������i��rZ��K�ӷ.I�x�Mq.�m�k��S\\��i;r�5�+�wnT[1�=q��EǋANn ܍��rB���K���.b\\��us坸���-�i%��m�f��K�'-�[���m�����pW6�T%��s~����� ��o�s\0Osf��K�#.w�ܸ�s�f]��j7<.g\\�s��=�k�7=�u����r����I��ڮ��l��s���竟w7'�]��>��ϙݗFn�NP�9t:tU��cs������\0�L����`]]�Ot�u����7Kn�N��D���pt��N���sf������P���圓u\n�-��7Og��L�!sr~\r�˧�T.~�P��uB�-ϚWS.����t��M���6h�]`��s�w���7B�-]`��s�z���З\\�����u���k��>g+]r��t:vE�+��B�o]=�yv�ϙ�Wa��݊��t*w��k��H�A]��t:u5���Wf���\n�uvj�m���C� ݚ�'t�r�٫��H͹�=6�v������7k��ݲ�Gt�%���N�t]��Wt�ymӠ�7p�b�Y�l��%�Rc`I.ہ���w��K�k*\0�]���r��mܻ�7M\r6(8�cwj���r��\\h��w���I۷p��컬���r͹�7C.p�Թ�w��=�+�󑮊���Ew\n����׀������9���k��{/�ứ9��M����P��5	A��}Ի��So\r�߻�uF���I��ڮ�Ob�KwF��ỽ�W��]a�[xzr4���WYo��w��<�+�w@��IV�\r�k��[Ab�=|�����W�Q�F�A�r]份�\\n�]s��x�v����.�Oy��x����˷����T��w���{�Ӣ��]���x�=���wgA�m��w.r���u.�����w\n����wr�*ހ��w:�}��՗p��ފ��=��e�;�tgpތ���.��ӣ�7��B��Dv����A�'xޚ��w����˿ghoKޝ��s�d�%[��/M\\'�Ww��A��J���*ݻ��z3E���\$��*ޭ�us���W���^��w��t��7�oh���{s��i�W)�#��GM\n�D�K,o���l�~���[J�S\"�%�y��]J�4J���y֕�~(�� j�pˠ�£�\0�&����f��t��^�F�3�ٱ�\0��^��j�-mYjWr�age��|��u⯍�ҩ���fj��a`לd�V>��M3��]W�b+�-z��w���ת�k[�_����� �Cφ�[M|]eZ�t�j6X%�YR��x������U_�CT��]c��]���h��Ģ�;7�f57!;���)U��a5����\0)����Wr��U��u��z�㫹{���Z�1���B��~���S���P�x�Y|�����W��BUܾ|B�4����#�u��B���f�67o�\\�+Wr��zf4���	_�s|z|��7�*�W��%f�]�������&�[r�;�,.�3�Z��~��ua*�̍���Z��0��=>2t��Uk0�`�E_!4�!~�L��Z���\n������0��:Ť/��������L��hKݗ��B_i�	}��C�搾/�^��U}�b�hK�����VO���[�����V��P6���� �/�V�`~F��������'���Sb�&hK����CN�Y[j�}��l��o�^��[�bmhK�p�a�_�\0�a��z�nW�b��&��f�}B�y�R*i�c1P��/�al\$�<Ժ�=fR��:�5 �ɴ_���ը�-�B���Ի�AS�g.\0ʜ�֪s�7&� ��0�\n���]���=Uٗ;���CR|��m`��C��E.N(�/���\n�V�+�6Y8�v֗e\"v���S\\ɂ\n���UTX`.���7�u����[�5�)�P(���`r��.Nv��HbN	g�c\rIy�H�������1���%OF���~��*��S�&�Gx�<BD�>�Y��S�\$� �S��O�\n^��Q�c5�qc�.�=x�p���7�O�c�X)�\$��\\����+�~@I Qd���w���B��-g�����p��r\0�@�.��p�,�j��;�`�i��|�P�]j��Q�l)V�	�w����?�ENX\"�JR��M=Bv�`\0=6 �-�	�VC����v��a������aR>�twvvv\r�\$��L�E,����G�72a���.X�t|��JM�p�+3L6�t6'*D�)���)�UB�F��\\�C����,�ʑ\0���[�;]m��-��A n���� \0a�7p5��[��]����.�⟺�!V�p\$����8D��P�@��]�L;t��1��J�/Mǣ�4��C���0sO�L0u�kx��� �E���H	P���e��aIu�����0�\"����E�����3���8Sa�m(���P��0���,���\n���.ɰ���_��+���W���*⹝ÀG�:c����l�<\rjr�Xr��.��8Y��\\6Xq��ὂhx��\r�Y�}�����.�=)P��;���H�p%M�����������?#,\0007��.T,��Ȭ,?8����!z	�i� hȵ!`�H=���2BW����U����7\\@�L�b���(f\"��Mp����n�F!�nԕ%�W�q��![�E�nS �D�\"�x��|�[nS��!V�v1CE;���#	� %b;#D`�u�.lH'���rbM�z�/��p�M�q,M���=�F%lK���\"���f?~&;i�)b;)�wdVe|���7����)OS0�J���H)\r��X=c-�:<=�N1��ܟU�TY' ��Z�E��v���)WjX>�5�nS�0\n�	�RIJ�Qb�S��_PU+�1T��b᡿�X��8W�vbB� �(\n�]���4é�׊��t�ٔ�>[򉒋?=�,|U��0ʶ�ߗpV��Ʉ�E~Ŏ	Hfà�1h�ڑf�{,a �Oi\"��o����X�]\r��c�q������r�@�J�;+d*M�@����q4\r�>`���b�8S�\0�H!L<XBp`E�Ɋ��~\n`w�����s���.*ux��@��cp��pGiO 7�\0�t6i.מ2����(�J�^�U3|b�c3�����2�BCvq�F�F5��7�j|f�QJ�� �b��Y�ՔnO\nG�w\0Rp}��^Avբ�uVӍ04�|�?�+Tf�\$+���\0q���7�lu�<����`���\0��i��u�C@�\0����\"e�t�_��\nh�[<o�E���X:R;�ϐP�@*�K:�qf�^\"e�nS\r��*��z1�q���-]H�b��/��!\"ʲN)ڤ`h�B�<�?[��i2K�MN\0�1�EI��>��9���It���)�'�i����VGQ�\"5�Ob-V!\"�\n��@��p!AE�G��\\�9%}���.�\\T�	��Z�Hg�+\0[�����yWA�9As��)Rt���`kqտ�ľ���⩞�/]�yY�%��");
} elseif ($_GET["file"] == "logo.png") {
	header("Content-Type: image/png");
  readfile("logo.png");
}
exit;

}

if ($_GET["script"] == "version") {
	$filename = get_temp_dir() . "/adminer.version";
	@unlink($filename); // it may not be writable by us, @ - it may not exist
	$fp = file_open_lock($filename);
	if ($fp) {
		file_write_unlock($fp, serialize(array("signature" => $_POST["signature"], "version" => $_POST["version"])));
	}
	exit;
}

// Adminer doesn't use any global variables; they used to be declared here

if (!$_SERVER["REQUEST_URI"]) { // IIS 5 compatibility
	$_SERVER["REQUEST_URI"] = $_SERVER["ORIG_PATH_INFO"];
}
if (!strpos($_SERVER["REQUEST_URI"], '?') && $_SERVER["QUERY_STRING"] != "") { // IIS 7 compatibility
	$_SERVER["REQUEST_URI"] .= "?$_SERVER[QUERY_STRING]";
}
if ($_SERVER["HTTP_X_FORWARDED_PREFIX"]) {
	$_SERVER["REQUEST_URI"] = $_SERVER["HTTP_X_FORWARDED_PREFIX"] . $_SERVER["REQUEST_URI"];
}
define('Adminer\HTTPS', ($_SERVER["HTTPS"] && strcasecmp($_SERVER["HTTPS"], "off")) || ini_bool("session.cookie_secure")); // session.cookie_secure could be set on HTTP if we are behind a reverse proxy

@ini_set("session.use_trans_sid", '0'); // protect links in export, @ - may be disabled
if (!defined("SID")) {
	session_cache_limiter(""); // to allow restarting session
	session_name("adminer_sid"); // use specific session name to get own namespace
	session_set_cookie_params(0, preg_replace('~\?.*~', '', $_SERVER["REQUEST_URI"]), "", HTTPS, true); // ini_set() may be disabled
	session_start();
}

// disable magic quotes to be able to use database escaping function
remove_slashes(array(&$_GET, &$_POST, &$_COOKIE), $filter);
if (function_exists("get_magic_quotes_runtime") && get_magic_quotes_runtime()) {
	set_magic_quotes_runtime(false);
}
@set_time_limit(0); // @ - can be disabled
@ini_set("precision", '15'); // @ - can be disabled, 15 - internal PHP precision

?>
<?php
/** Translate string
* @param literal-string $idf
* @param float|string $number
*/
function lang($idf, $number = null) {
	$args = func_get_args();
	// this is matched by compile.php
	$args[0] = $idf;
	return call_user_func_array('Adminer\lang_format', $args);
}

/** Format translation, usable also by plugins
* @param string|list<string> $translation
* @param float|string $number
*/
function lang_format($translation, $number = null) {
	if (is_array($translation)) {
		// this is matched by compile.php
		$pos = ($number == 1 ? 0
			: 1
		); // http://www.gnu.org/software/gettext/manual/html_node/Plural-forms.html
		$translation = $translation[$pos];
	}
	$translation = str_replace("'", '’', $translation); // translations can contain HTML or be used in optionlist (we couldn't escape them here) but they can also be used e.g. in title='' //! escape plaintext translations
	$args = func_get_args();
	array_shift($args);
	$format = str_replace("%d", "%s", $translation);
	if ($format != $translation) {
		$args[0] = format_number($number);
	}
	return vsprintf($format, $args);
}

// this is matched by compile.php
define('Adminer\LANG', 'en');


?>
<?php
// this could be interface when "Db extends \mysqli" can have compatible type declarations (PHP 7)
// interfaces can include properties only since PHP 8.4
abstract class SqlDb {
	/** @var Db */ static $instance;

	/** @var string */ public $extension; // extension name
	/** @var string */ public $flavor = ''; // different vendor with the same API, e.g. MariaDB; usually stays empty
	/** @var string */ public $server_info; // server version
	/** @var int */ public $affected_rows = 0; // number of affected rows
	/** @var string */ public $info = ''; // see https://php.net/mysql_info
	/** @var int */ public $errno = 0; // last error code
	/** @var string */ public $error = ''; // last error message
	/** @var Result|bool */ protected $multi; // used for multiquery

	/** Connect to server
	* @return string error message
	*/
	abstract function attach($server, $username, $password);

	/** Quote string to use in SQL
	* @return string escaped string enclosed in '
	*/
	abstract function quote($string);

	/** Select database
	* @return bool boolish
	*/
	abstract function select_db($database);

	/** Send query
	* @return Result|bool
	*/
	abstract function query($query, $unbuffered = false);

	/** Send query with more resultsets
	* @return Result|bool
	*/
	function multi_query($query) {
		return $this->multi = $this->query($query);
	}

	/** Get current resultset
	* @return Result|bool
	*/
	function store_result() {
		return $this->multi;
	}

	/** Fetch next resultset */
	function next_result() {
		return false;
	}
}

?>
<?php
// PDO can be used in several database drivers
if (extension_loaded('pdo')) {
	abstract class PdoDb extends SqlDb {
		protected $pdo;

		/** Connect to server using DSN
		* @param mixed[] $options
		* @return string error message
		*/
		function dsn($dsn, $username, $password, array $options = array()) {
			$options[\PDO::ATTR_ERRMODE] = \PDO::ERRMODE_SILENT;
			$options[\PDO::ATTR_STATEMENT_CLASS] = array('Adminer\PdoResult');
			try {
				$this->pdo = new \PDO($dsn, $username, $password, $options);
			} catch (\Exception $ex) {
				return $ex->getMessage();
			}
			$this->server_info = @$this->pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
			return '';
		}

		function quote($string) {
			return $this->pdo->quote($string);
		}

		function query($query, $unbuffered = false) {
			/** @var Result|bool */
			$result = $this->pdo->query($query);
			$this->error = "";
			if (!$result) {
				list(, $this->errno, $this->error) = $this->pdo->errorInfo();
				if (!$this->error) {
					$this->error = 'Unknown error.';
				}
				return false;
			}
			$this->store_result($result);
			return $result;
		}

		function store_result($result = null) {
			if (!$result) {
				$result = $this->multi;
				if (!$result) {
					return false;
				}
			}
			if ($result->columnCount()) {
				$result->num_rows = $result->rowCount(); // is not guaranteed to work with all drivers
				return $result;
			}
			$this->affected_rows = $result->rowCount();
			return true;
		}

		function next_result() {
			/** @var PdoResult|bool */
			$result = $this->multi;
			if (!is_object($result)) {
				return false;
			}
			$result->_offset = 0;
			return @$result->nextRowset(); // @ - PDO_PgSQL doesn't support it
		}
	}

	class PdoResult extends \PDOStatement {
		public $_offset = 0, $num_rows;

		function fetch_assoc() {
			return $this->fetch_array(\PDO::FETCH_ASSOC);
		}

		function fetch_row() {
			return $this->fetch_array(\PDO::FETCH_NUM);
		}

		private function fetch_array($mode) {
			$return = $this->fetch($mode);
			return ($return ? array_map(array($this, 'unresource'), $return) : $return);
		}

		private function unresource($val) {
			return (is_resource($val) ? stream_get_contents($val) : $val);
		}

		function fetch_field() {
			$row = (object) $this->getColumnMeta($this->_offset++);
			$type = $row->pdo_type;
			$row->type = ($type == \PDO::PARAM_INT ? 0 : 15);
			$row->charsetnr = ($type == \PDO::PARAM_LOB || (isset($row->flags) && in_array("blob", (array) $row->flags)) ? 63 : 0);
			return $row;
		}

		function seek($offset) {
			for ($i=0; $i < $offset; $i++) {
				$this->fetch();
			}
		}
	}
}

?>
<?php
/** Add or overwrite a driver */
function add_driver($id, $name) {
	SqlDriver::$drivers[$id] = $name;
}

/** Get driver name */
function get_driver($id) {
	return SqlDriver::$drivers[$id];
}

abstract class SqlDriver {
	/** @var Driver */ static $instance;
	/** @var string[] */ static $drivers = array(); // all available drivers
	/** @var list<string> */ static $extensions = array(); // possible extensions in the current driver
	/** @var string */ static $jush; // JUSH identifier

	/** @var Db */ protected $conn;
	/** @var int[][] */ protected $types = array(); // [$group => [$type => $maximum_unsigned_length, ...], ...]
	/** @var string[] */ public $insertFunctions = array(); // ["$type|$type2" => "$function/$function2"] functions used in edit and insert
	/** @var string[] */ public $editFunctions = array(); // ["$type|$type2" => "$function/$function2"] functions used in edit only
	/** @var list<string> */ public $unsigned = array(); // number variants
	/** @var list<string> */ public $operators = array(); // operators used in select
	/** @var list<string> */ public $functions = array(); // functions used in select
	/** @var list<string> */ public $grouping = array(); // grouping functions used in select
	/** @var string */ public $onActions = "RESTRICT|NO ACTION|CASCADE|SET NULL|SET DEFAULT"; // used in foreign_keys()
	/** @var list<string> */ public $partitionBy = array(); // supported partitioning types
	/** @var string */ public $inout = "IN|OUT|INOUT"; // used in routines
	/** @var string */ public $enumLength = "'(?:''|[^'\\\\]|\\\\.)*'"; // regular expression for parsing enum lengths
	/** @var list<string> */ public $generated = array(); // allowed types of generated columns

	/** Connect to the database
	* @return Db|string string for error
	*/
	static function connect($server, $username, $password) {
		$connection = new Db;
		return ($connection->attach($server, $username, $password) ?: $connection);
	}

	/** Create object for performing database operations */
	function __construct(Db $connection) {
		$this->conn = $connection;
	}

	/** Get all types
	* @return int[] [$type => $maximum_unsigned_length, ...]
	*/
	function types() {
		return call_user_func_array('array_merge', array_values($this->types));
	}

	/** Get structured types
	* @return list<string>[]|list<string> [$description => [$type, ...], ...]
	*/
	function structuredTypes() {
		return array_map('array_keys', $this->types);
	}

	/** Get enum values
	* @param Field $field
	* @return string|void
	*/
	function enumLength(array $field) {
	}

	/** Function used to convert the value inputted by user
	* @param Field $field
	* @return string|void
	*/
	function unconvertFunction(array $field) {
	}

	/** Select data from table
	* @param list<string> $select result of adminer()->selectColumnsProcess()[0]
	* @param list<string> $where result of adminer()->selectSearchProcess()
	* @param list<string> $group result of adminer()->selectColumnsProcess()[1]
	* @param list<string> $order result of adminer()->selectOrderProcess()
	* @param int $limit result of adminer()->selectLimitProcess()
	* @param int $page index of page starting at zero
	* @param bool $print whether to print the query
	* @return Result|false
	*/
	function select($table, array $select, array $where, array $group, array $order = array(), $limit = 1, $page = 0, $print = false) {
		$is_group = (count($group) < count($select));
		$query = adminer()->selectQueryBuild($select, $where, $group, $order, $limit, $page);
		if (!$query) {
			$query = "SELECT" . limit(
				($_GET["page"] != "last" && $limit && $group && $is_group && JUSH == "sql" ? "SQL_CALC_FOUND_ROWS " : "") . implode(", ", $select) . "\nFROM " . table($table),
				($where ? "\nWHERE " . implode(" AND ", $where) : "") . ($group && $is_group ? "\nGROUP BY " . implode(", ", $group) : "") . ($order ? "\nORDER BY " . implode(", ", $order) : ""),
				$limit,
				($page ? $limit * $page : 0),
				"\n"
			);
		}
		$start = microtime(true);
		$return = $this->conn->query($query);
		if ($print) {
			echo adminer()->selectQuery($query, $start, !$return);
		}
		return $return;
	}

	/** Delete data from table
	* @param string $queryWhere " WHERE ..."
	* @param int $limit 0 or 1
	* @return Result|bool
	*/
	function delete($table, $queryWhere, $limit = 0) {
		$query = "FROM " . table($table);
		return queries("DELETE" . ($limit ? limit1($table, $query, $queryWhere) : " $query$queryWhere"));
	}

	/** Update data in table
	* @param string[] $set escaped columns in keys, quoted data in values
	* @param string $queryWhere " WHERE ..."
	* @param int $limit 0 or 1
	* @return Result|bool
	*/
	function update($table, array $set, $queryWhere, $limit = 0, $separator = "\n") {
		$values = array();
		foreach ($set as $key => $val) {
			$values[] = "$key = $val";
		}
		$query = table($table) . " SET$separator" . implode(",$separator", $values);
		return queries("UPDATE" . ($limit ? limit1($table, $query, $queryWhere, $separator) : " $query$queryWhere"));
	}

	/** Insert data into table
	* @param string[] $set escaped columns in keys, quoted data in values
	* @return Result|bool
	*/
	function insert($table, array $set) {
		return queries("INSERT INTO " . table($table) . ($set
			? " (" . implode(", ", array_keys($set)) . ")\nVALUES (" . implode(", ", $set) . ")"
			: " DEFAULT VALUES"
		) . $this->insertReturning($table));
	}

	/** Get RETURNING clause for INSERT queries (PostgreSQL specific) */
	function insertReturning($table) {
		return "";
	}

	/** Insert or update data in table
	* @param list<string[]> $rows of arrays with escaped columns in keys and quoted data in values
	* @param int[] $primary column names in keys
	* @return Result|bool
	*/
	function insertUpdate($table, array $rows, array $primary) {
		return false;
	}

	/** Begin transaction
	* @return Result|bool
	*/
	function begin() {
		return queries("BEGIN");
	}

	/** Commit transaction
	* @return Result|bool
	*/
	function commit() {
		return queries("COMMIT");
	}

	/** Rollback transaction
	* @return Result|bool
	*/
	function rollback() {
		return queries("ROLLBACK");
	}

	/** Return query with a timeout
	* @param int $timeout seconds
	* @return string|void null if the driver doesn't support query timeouts
	*/
	function slowQuery($query, $timeout) {
	}

	/** Convert column to be searchable
	* @param string $idf escaped column name
	* @param array{op:string, val:string} $val
	* @param Field $field
	*/
	function convertSearch($idf, array $val, array $field) {
		return $idf;
	}

	/** Convert value returned by database to actual value
	* @param Field $field
	*/
	function value($val, array $field) {
		return (method_exists($this->conn, 'value') ? $this->conn->value($val, $field) : $val);
	}

	/** Quote binary string */
	function quoteBinary($s) {
		return q($s);
	}

	/** Get warnings about the last command
	* @return string|void HTML
	*/
	function warnings() {
	}

	/** Get help link for table
	* @return string|void relative URL
	*/
	function tableHelp($name, $is_view = false) {
	}

	/** Get tables this table inherits from
	* @return list<string>
	*/
	function inheritsFrom($table) {
		return array();
	}

	/** Get inherited tables
	* @return list<string>
	*/
	function inheritedTables($table) {
		return array();
	}

	/** Get partitions info
	* @return Partitions
	*/
	function partitionsInfo($table) {
		return array();
	}

	/** Check if C-style escapes are supported */
	function hasCStyleEscapes() {
		return false;
	}

	/** Get supported engines
	* @return list<string>
	*/
	function engines() {
		return array();
	}

	/** Check whether table supports indexes
	* @param TableStatus $table_status
	*/
	function supportsIndex(array $table_status) {
		return !is_view($table_status);
	}

	/** Return list of supported index algorithms, first one is default
	 * @param TableStatus $tableStatus
	 * @return list<string>
	 */
	function indexAlgorithms(array $tableStatus) {
		return array();
	}

	/** Get defined check constraints
	* @return string[] [$name => $clause]
	*/
	function checkConstraints($table) {
		// MariaDB contains CHECK_CONSTRAINTS.TABLE_NAME, MySQL and PostrgreSQL not
		return get_key_vals("SELECT c.CONSTRAINT_NAME, CHECK_CLAUSE
FROM INFORMATION_SCHEMA.CHECK_CONSTRAINTS c
JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS t ON c.CONSTRAINT_SCHEMA = t.CONSTRAINT_SCHEMA AND c.CONSTRAINT_NAME = t.CONSTRAINT_NAME
WHERE c.CONSTRAINT_SCHEMA = " . q($_GET["ns"] != "" ? $_GET["ns"] : DB) . "
AND t.TABLE_NAME = " . q($table) . "
AND CHECK_CLAUSE NOT LIKE '% IS NOT NULL'", $this->conn); // ignore default IS NOT NULL checks in PostrgreSQL
	}

	/** Get all fields in the current schema
	* @return array<list<array{field:string, null:bool, type:string, length:?numeric-string}>> optionally also 'primary'
	*/
	function allFields() {
		$return = array();
		if (DB != "") {
			foreach (
				get_rows("SELECT TABLE_NAME AS tab, COLUMN_NAME AS field, IS_NULLABLE AS nullable, DATA_TYPE AS type, CHARACTER_MAXIMUM_LENGTH AS length" . (JUSH == 'sql' ? ", COLUMN_KEY = 'PRI' AS `primary`" : "") . "
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = " . q($_GET["ns"] != "" ? $_GET["ns"] : DB) . "
ORDER BY TABLE_NAME, ORDINAL_POSITION", $this->conn) as $row
			) {
				$row["null"] = ($row["nullable"] == "YES");
				$return[$row["tab"]][] = $row;
			}
		}
		return $return;
	}
}

?>
<?php
add_driver("sqlite", "SQLite");

if (isset($_GET["sqlite"])) {
	define('Adminer\DRIVER', "sqlite");

	if (class_exists("SQLite3") && $_GET["ext"] != "pdo") {
		abstract class SqliteDb extends SqlDb {
			public $extension = "SQLite3";
			private $link;

			function attach($filename, $username, $password) {
				$this->link = new \SQLite3($filename);
				$version = $this->link->version();
				$this->server_info = $version["versionString"];
				return '';
			}

			function query($query, $unbuffered = false) {
				$result = @$this->link->query($query);
				$this->error = "";
				if (!$result) {
					$this->errno = $this->link->lastErrorCode();
					$this->error = $this->link->lastErrorMsg();
					return false;
				} elseif ($result->numColumns()) {
					return new Result($result);
				}
				$this->affected_rows = $this->link->changes();
				return true;
			}

			function quote($string) {
				return (is_utf8($string)
					? "'" . $this->link->escapeString($string) . "'"
					: "x'" . first(unpack('H*', $string)) . "'"
				);
			}
		}

		class Result {
			public $num_rows;
			private $result, $offset = 0;

			function __construct($result) {
				$this->result = $result;
			}

			function fetch_assoc() {
				return $this->result->fetchArray(SQLITE3_ASSOC);
			}

			function fetch_row() {
				return $this->result->fetchArray(SQLITE3_NUM);
			}

			function fetch_field() {
				$column = $this->offset++;
				$type = $this->result->columnType($column);
				return (object) array(
					"name" => $this->result->columnName($column),
					"type" => ($type == SQLITE3_TEXT ? 15 : 0),
					"charsetnr" => ($type == SQLITE3_BLOB ? 63 : 0), // 63 - binary
				);
			}

			function __destruct() {
				$this->result->finalize();
			}
		}

	} elseif (extension_loaded("pdo_sqlite")) {
		abstract class SqliteDb extends PdoDb {
			public $extension = "PDO_SQLite";

			function attach($filename, $username, $password) {
				return $this->dsn(DRIVER . ":$filename", "", "");
			}
		}

	}

	if (class_exists('Adminer\SqliteDb')) {
		class Db extends SqliteDb {
			function attach($filename, $username, $password) {
				parent::attach($filename, $username, $password);
				$this->query("PRAGMA foreign_keys = 1");
				$this->query("PRAGMA busy_timeout = 500");
				return '';
			}

			function select_db($filename) {
				if (is_readable($filename) && $this->query("ATTACH " . $this->quote(preg_match("~(^[/\\\\]|:)~", $filename) ? $filename : dirname($_SERVER["SCRIPT_FILENAME"]) . "/$filename") . " AS a")) {
					return !self::attach($filename, '', '');
				}
				return false;
			}
		}
	}



	class Driver extends SqlDriver {
		static $extensions = array("SQLite3", "PDO_SQLite");
		static $jush = "sqlite";

		protected $types = array(array("integer" => 0, "real" => 0, "numeric" => 0, "text" => 0, "blob" => 0));

		public $insertFunctions = array(); // "text" => "date('now')/time('now')/datetime('now')",
		public $editFunctions = array(
			"integer|real|numeric" => "+/-",
			// "text" => "date/time/datetime",
			"text" => "||",
		);

		public $operators = array("=", "<", ">", "<=", ">=", "!=", "LIKE", "LIKE %%", "IN", "IS NULL", "NOT LIKE", "NOT IN", "IS NOT NULL", "SQL"); // REGEXP can be user defined function
		public $functions = array("hex", "length", "lower", "round", "unixepoch", "upper");
		public $grouping = array("avg", "count", "count distinct", "group_concat", "max", "min", "sum");

		static function connect($server, $username, $password) {
			if ($password != "") {
				return 'Database does not support password.';
			}
			return parent::connect(":memory:", "", "");
		}

		function __construct(Db $connection) {
			parent::__construct($connection);
			if (min_version(3.31, 0, $connection)) {
				$this->generated = array("STORED", "VIRTUAL");
			}
		}

		function structuredTypes() {
			return array_keys($this->types[0]);
		}

		function insertUpdate($table, array $rows, array $primary) {
			$values = array();
			foreach ($rows as $set) {
				$values[] = "(" . implode(", ", $set) . ")";
			}
			return queries("REPLACE INTO " . table($table) . " (" . implode(", ", array_keys(reset($rows))) . ") VALUES\n" . implode(",\n", $values));
		}

		function tableHelp($name, $is_view = false) {
			if ($name == "sqlite_sequence") {
				return "fileformat2.html#seqtab";
			}
			if ($name == "sqlite_master") {
				return "fileformat2.html#$name";
			}
		}

		function checkConstraints($table) {
			preg_match_all('~ CHECK *(\( *(((?>[^()]*[^() ])|(?1))*) *\))~', get_val("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = " . q($table), 0, $this->conn), $matches); //! could be inside a comment
			return array_combine($matches[2], $matches[2]);
		}

		function allFields() {
			$return = array();
			foreach (tables_list() as $table => $type) {
				foreach (fields($table) as $field) {
					$return[$table][] = $field;
				}
			}
			return $return;
		}
	}



	function idf_escape($idf) {
		return '"' . str_replace('"', '""', $idf) . '"';
	}

	function table($idf) {
		return idf_escape($idf);
	}

	function get_databases($flush) {
		return array();
	}

	function limit($query, $where, $limit, $offset = 0, $separator = " ") {
		return " $query$where" . ($limit ? $separator . "LIMIT $limit" . ($offset ? " OFFSET $offset" : "") : "");
	}

	function limit1($table, $query, $where, $separator = "\n") {
		return (preg_match('~^INTO~', $query) || get_val("SELECT sqlite_compileoption_used('ENABLE_UPDATE_DELETE_LIMIT')")
			? limit($query, $where, 1, 0, $separator)
			: " $query WHERE rowid = (SELECT rowid FROM " . table($table) . $where . $separator . "LIMIT 1)" //! use primary key in tables with WITHOUT rowid
		);
	}

	function db_collation($db, $collations) {
		return get_val("PRAGMA encoding"); // there is no database list so $db == DB
	}

	function logged_user() {
		return get_current_user(); // should return effective user
	}

	function tables_list() {
		return get_key_vals("SELECT name, type FROM sqlite_master WHERE type IN ('table', 'view') ORDER BY (name = 'sqlite_sequence'), name");
	}

	function count_tables($databases) {
		return array();
	}

	function table_status($name = "") {
		$return = array();
		foreach (get_rows("SELECT name AS Name, type AS Engine, 'rowid' AS Oid, '' AS Auto_increment FROM sqlite_master WHERE type IN ('table', 'view') " . ($name != "" ? "AND name = " . q($name) : "ORDER BY name")) as $row) {
			$row["Rows"] = get_val("SELECT COUNT(*) FROM " . idf_escape($row["Name"]));
			$return[$row["Name"]] = $row;
		}
		foreach (get_rows("SELECT * FROM sqlite_sequence" . ($name != "" ? " WHERE name = " . q($name) : ""), null, "") as $row) {
			$return[$row["name"]]["Auto_increment"] = $row["seq"];
		}
		return $return;
	}

	function is_view($table_status) {
		return $table_status["Engine"] == "view";
	}

	function fk_support($table_status) {
		return !get_val("SELECT sqlite_compileoption_used('OMIT_FOREIGN_KEY')");
	}

	function fields($table) {
		$return = array();
		$primary = "";
		foreach (get_rows("PRAGMA table_" . (min_version(3.31) ? "x" : "") . "info(" . table($table) . ")") as $row) {
			$name = $row["name"];
			$type = strtolower($row["type"]);
			$default = $row["dflt_value"];
			$return[$name] = array(
				"field" => $name,
				"type" => (preg_match('~int~i', $type) ? "integer" : (preg_match('~char|clob|text~i', $type) ? "text" : (preg_match('~blob~i', $type) ? "blob" : (preg_match('~real|floa|doub~i', $type) ? "real" : "numeric")))),
				"full_type" => $type,
				"default" => (preg_match("~^'(.*)'$~", $default, $match) ? str_replace("''", "'", $match[1]) : ($default == "NULL" ? null : $default)),
				"null" => !$row["notnull"],
				"privileges" => array("select" => 1, "insert" => 1, "update" => 1, "where" => 1, "order" => 1),
				"primary" => $row["pk"],
			);
			if ($row["pk"]) {
				if ($primary != "") {
					$return[$primary]["auto_increment"] = false;
				} elseif (preg_match('~^integer$~i', $type)) {
					$return[$name]["auto_increment"] = true;
				}
				$primary = $name;
			}
		}
		$sql = get_val("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = " . q($table));
		$idf = '(("[^"]*+")+|[a-z0-9_]+)';
		preg_match_all('~' . $idf . '\s+text\s+COLLATE\s+(\'[^\']+\'|\S+)~i', $sql, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$name = str_replace('""', '"', preg_replace('~^"|"$~', '', $match[1]));
			if ($return[$name]) {
				$return[$name]["collation"] = trim($match[3], "'");
			}
		}
		preg_match_all('~' . $idf . '\s.*GENERATED ALWAYS AS \((.+)\) (STORED|VIRTUAL)~i', $sql, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$name = str_replace('""', '"', preg_replace('~^"|"$~', '', $match[1]));
			$return[$name]["default"] = $match[3];
			$return[$name]["generated"] = strtoupper($match[4]);
		}
		return $return;
	}

	function indexes($table, $connection2 = null) {
		$connection2 = connection($connection2);
		$return = array();
		$sql = get_val("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = " . q($table), 0, $connection2);
		if (preg_match('~\bPRIMARY\s+KEY\s*\((([^)"]+|"[^"]*"|`[^`]*`)++)~i', $sql, $match)) {
			$return[""] = array("type" => "PRIMARY", "columns" => array(), "lengths" => array(), "descs" => array());
			preg_match_all('~((("[^"]*+")+|(?:`[^`]*+`)+)|(\S+))(\s+(ASC|DESC))?(,\s*|$)~i', $match[1], $matches, PREG_SET_ORDER);
			foreach ($matches as $match) {
				$return[""]["columns"][] = idf_unescape($match[2]) . $match[4];
				$return[""]["descs"][] = (preg_match('~DESC~i', $match[5]) ? '1' : null);
			}
		}
		if (!$return) {
			foreach (fields($table) as $name => $field) {
				if ($field["primary"]) {
					$return[""] = array("type" => "PRIMARY", "columns" => array($name), "lengths" => array(), "descs" => array(null));
				}
			}
		}
		$sqls = get_key_vals("SELECT name, sql FROM sqlite_master WHERE type = 'index' AND tbl_name = " . q($table), $connection2);
		foreach (get_rows("PRAGMA index_list(" . table($table) . ")", $connection2) as $row) {
			$name = $row["name"];
			$index = array("type" => ($row["unique"] ? "UNIQUE" : "INDEX"));
			$index["lengths"] = array();
			$index["descs"] = array();
			foreach (get_rows("PRAGMA index_info(" . idf_escape($name) . ")", $connection2) as $row1) {
				$index["columns"][] = $row1["name"];
				$index["descs"][] = null;
			}
			if (preg_match('~^CREATE( UNIQUE)? INDEX ' . preg_quote(idf_escape($name) . ' ON ' . idf_escape($table), '~') . ' \((.*)\)$~i', $sqls[$name], $regs)) {
				preg_match_all('/("[^"]*+")+( DESC)?/', $regs[2], $matches);
				foreach ($matches[2] as $key => $val) {
					if ($val) {
						$index["descs"][$key] = '1';
					}
				}
			}
			if (!$return[""] || $index["type"] != "UNIQUE" || $index["columns"] != $return[""]["columns"] || $index["descs"] != $return[""]["descs"] || !preg_match("~^sqlite_~", $name)) {
				$return[$name] = $index;
			}
		}
		return $return;
	}

	function foreign_keys($table) {
		$return = array();
		foreach (get_rows("PRAGMA foreign_key_list(" . table($table) . ")") as $row) {
			$foreign_key = &$return[$row["id"]];
			if (!$foreign_key) {
				$foreign_key = $row;
			}
			$foreign_key["source"][] = $row["from"];
			$foreign_key["target"][] = $row["to"];
		}
		return $return;
	}

	function view($name) {
		return array("select" => preg_replace('~^(?:[^`"[]+|`[^`]*`|"[^"]*")* AS\s+~iU', '', get_val("SELECT sql FROM sqlite_master WHERE type = 'view' AND name = " . q($name)))); //! identifiers may be inside []
	}

	function collations() {
		return (isset($_GET["create"]) ? get_vals("PRAGMA collation_list", 1) : array());
	}

	function information_schema($db) {
		return false;
	}

	function error() {
		return h(connection()->error);
	}

	function check_sqlite_name($name) {
		// avoid creating PHP files on unsecured servers
		$extensions = "db|sdb|sqlite";
		if (!preg_match("~^[^\\0]*\\.($extensions)\$~", $name)) {
			connection()->error = sprintf('Please use one of the extensions %s.', str_replace("|", ", ", $extensions));
			return false;
		}
		return true;
	}

	function create_database($db, $collation) {
		if (file_exists($db)) {
			connection()->error = 'File exists.';
			return false;
		}
		if (!check_sqlite_name($db)) {
			return false;
		}
		try {
			$link = new Db();
			$link->attach($db, '', '');
		} catch (\Exception $ex) {
			connection()->error = $ex->getMessage();
			return false;
		}
		$link->query('PRAGMA encoding = "UTF-8"');
		$link->query('CREATE TABLE adminer (i)'); // otherwise creates empty file
		$link->query('DROP TABLE adminer');
		return true;
	}

	function drop_databases($databases) {
		connection()->attach(":memory:", '', ''); // to unlock file, doesn't work in PDO on Windows
		foreach ($databases as $db) {
			if (!@unlink($db)) {
				connection()->error = 'File exists.';
				return false;
			}
		}
		return true;
	}

	function rename_database($name, $collation) {
		if (!check_sqlite_name($name)) {
			return false;
		}
		connection()->attach(":memory:", '', '');
		connection()->error = 'File exists.';
		return @rename(DB, $name);
	}

	function auto_increment() {
		return " PRIMARY KEY AUTOINCREMENT";
	}

	function alter_table($table, $name, $fields, $foreign, $comment, $engine, $collation, $auto_increment, $partitioning) {
		$use_all_fields = ($table == "" || $foreign);
		foreach ($fields as $field) {
			if ($field[0] != "" || !$field[1] || $field[2]) {
				$use_all_fields = true;
				break;
			}
		}
		$alter = array();
		$originals = array();
		foreach ($fields as $field) {
			if ($field[1]) {
				$alter[] = ($use_all_fields ? $field[1] : "ADD " . implode($field[1]));
				if ($field[0] != "") {
					$originals[$field[0]] = $field[1][0];
				}
			}
		}
		if (!$use_all_fields) {
			foreach ($alter as $val) {
				if (!queries("ALTER TABLE " . table($table) . " $val")) {
					return false;
				}
			}
			if ($table != $name && !queries("ALTER TABLE " . table($table) . " RENAME TO " . table($name))) {
				return false;
			}
		} elseif (!recreate_table($table, $name, $alter, $originals, $foreign, $auto_increment)) {
			return false;
		}
		if ($auto_increment) {
			queries("BEGIN");
			queries("UPDATE sqlite_sequence SET seq = $auto_increment WHERE name = " . q($name)); // ignores error
			if (!connection()->affected_rows) {
				queries("INSERT INTO sqlite_sequence (name, seq) VALUES (" . q($name) . ", $auto_increment)");
			}
			queries("COMMIT");
		}
		return true;
	}

	/** Recreate table
	* @param string $table original name
	* @param string $name new name
	* @param list<list<string>> $fields [process_field()], empty to preserve
	* @param string[] $originals [$original => idf_escape($new_column)], empty to preserve
	* @param string[] $foreign [format_foreign_key()], empty to preserve
	* @param numeric-string|'' $auto_increment set auto_increment to this value, "" to preserve
	* @param list<array{string, string, list<string>|'DROP'}> $indexes [[$type, $name, $columns]], empty to preserve
	* @param string $drop_check CHECK constraint to drop
	* @param string $add_check CHECK constraint to add
	*/
	function recreate_table($table, $name, array $fields, array $originals, array $foreign, $auto_increment = "", $indexes = array(), $drop_check = "", $add_check = "") {
		if ($table != "") {
			if (!$fields) {
				foreach (fields($table) as $key => $field) {
					if ($indexes) {
						$field["auto_increment"] = 0;
					}
					$fields[] = process_field($field, $field);
					$originals[$key] = idf_escape($key);
				}
			}
			$primary_key = false;
			foreach ($fields as $field) {
				if ($field[6]) {
					$primary_key = true;
				}
			}
			$drop_indexes = array();
			foreach ($indexes as $key => $val) {
				if ($val[2] == "DROP") {
					$drop_indexes[$val[1]] = true;
					unset($indexes[$key]);
				}
			}
			foreach (indexes($table) as $key_name => $index) {
				$columns = array();
				foreach ($index["columns"] as $key => $column) {
					if (!$originals[$column]) {
						continue 2;
					}
					$columns[] = $originals[$column] . ($index["descs"][$key] ? " DESC" : "");
				}
				if (!$drop_indexes[$key_name]) {
					if ($index["type"] != "PRIMARY" || !$primary_key) {
						$indexes[] = array($index["type"], $key_name, $columns);
					}
				}
			}
			foreach ($indexes as $key => $val) {
				if ($val[0] == "PRIMARY") {
					unset($indexes[$key]);
					$foreign[] = "  PRIMARY KEY (" . implode(", ", $val[2]) . ")";
				}
			}
			foreach (foreign_keys($table) as $key_name => $foreign_key) {
				foreach ($foreign_key["source"] as $key => $column) {
					if (!$originals[$column]) {
						continue 2;
					}
					$foreign_key["source"][$key] = idf_unescape($originals[$column]);
				}
				if (!isset($foreign[" $key_name"])) {
					$foreign[] = " " . format_foreign_key($foreign_key);
				}
			}
			queries("BEGIN");
		}
		$changes = array();
		foreach ($fields as $field) {
			if (preg_match('~GENERATED~', $field[3])) {
				unset($originals[array_search($field[0], $originals)]);
			}
			$changes[] = "  " . implode($field);
		}
		$changes = array_merge($changes, array_filter($foreign));
		foreach (driver()->checkConstraints($table) as $check) {
			if ($check != $drop_check) {
				$changes[] = "  CHECK ($check)";
			}
		}
		if ($add_check) {
			$changes[] = "  CHECK ($add_check)";
		}
		$temp_name = ($table == $name ? "adminer_$name" : $name);
		if (!queries("CREATE TABLE " . table($temp_name) . " (\n" . implode(",\n", $changes) . "\n)")) {
			// implicit ROLLBACK to not overwrite connection()->error
			return false;
		}
		if ($table != "") {
			if ($originals && !queries("INSERT INTO " . table($temp_name) . " (" . implode(", ", $originals) . ") SELECT " . implode(", ", array_map('Adminer\idf_escape', array_keys($originals))) . " FROM " . table($table))) {
				return false;
			}
			$triggers = array();
			foreach (triggers($table) as $trigger_name => $timing_event) {
				$trigger = trigger($trigger_name, $table);
				$triggers[] = "CREATE TRIGGER " . idf_escape($trigger_name) . " " . implode(" ", $timing_event) . " ON " . table($name) . "\n$trigger[Statement]";
			}
			$auto_increment = $auto_increment ? "" : get_val("SELECT seq FROM sqlite_sequence WHERE name = " . q($table)); // if $auto_increment is set then it will be updated later
			if (
				!queries("DROP TABLE " . table($table)) // drop before creating indexes and triggers to allow using old names
				|| ($table == $name && !queries("ALTER TABLE " . table($temp_name) . " RENAME TO " . table($name)))
				|| !alter_indexes($name, $indexes)
			) {
				return false;
			}
			if ($auto_increment) {
				queries("UPDATE sqlite_sequence SET seq = $auto_increment WHERE name = " . q($name)); // ignores error
			}
			foreach ($triggers as $trigger) {
				if (!queries($trigger)) {
					return false;
				}
			}
			queries("COMMIT");
		}
		return true;
	}

	function index_sql($table, $type, $name, $columns) {
		return "CREATE $type " . ($type != "INDEX" ? "INDEX " : "")
			. idf_escape($name != "" ? $name : uniqid($table . "_"))
			. " ON " . table($table)
			. " $columns"
		;
	}

	function alter_indexes($table, $alter) {
		foreach ($alter as $primary) {
			if ($primary[0] == "PRIMARY") {
				return recreate_table($table, $table, array(), array(), array(), "", $alter);
			}
		}
		foreach (array_reverse($alter) as $val) {
			if (
				!queries($val[2] == "DROP"
				? "DROP INDEX " . idf_escape($val[1])
				: index_sql($table, $val[0], $val[1], "(" . implode(", ", $val[2]) . ")"))
			) {
				return false;
			}
		}
		return true;
	}

	function truncate_tables($tables) {
		return apply_queries("DELETE FROM", $tables);
	}

	function drop_views($views) {
		return apply_queries("DROP VIEW", $views);
	}

	function drop_tables($tables) {
		return apply_queries("DROP TABLE", $tables);
	}

	function move_tables($tables, $views, $target) {
		return false;
	}

	function trigger($name, $table) {
		if ($name == "") {
			return array("Statement" => "BEGIN\n\t;\nEND");
		}
		$idf = '(?:[^`"\s]+|`[^`]*`|"[^"]*")+';
		$trigger_options = trigger_options();
		preg_match(
			"~^CREATE\\s+TRIGGER\\s*$idf\\s*(" . implode("|", $trigger_options["Timing"]) . ")\\s+([a-z]+)(?:\\s+OF\\s+($idf))?\\s+ON\\s*$idf\\s*(?:FOR\\s+EACH\\s+ROW\\s)?(.*)~is",
			get_val("SELECT sql FROM sqlite_master WHERE type = 'trigger' AND name = " . q($name)),
			$match
		);
		$of = $match[3];
		return array(
			"Timing" => strtoupper($match[1]),
			"Event" => strtoupper($match[2]) . ($of ? " OF" : ""),
			"Of" => idf_unescape($of),
			"Trigger" => $name,
			"Statement" => $match[4],
		);
	}

	function triggers($table) {
		$return = array();
		$trigger_options = trigger_options();
		foreach (get_rows("SELECT * FROM sqlite_master WHERE type = 'trigger' AND tbl_name = " . q($table)) as $row) {
			preg_match('~^CREATE\s+TRIGGER\s*(?:[^`"\s]+|`[^`]*`|"[^"]*")+\s*(' . implode("|", $trigger_options["Timing"]) . ')\s*(.*?)\s+ON\b~i', $row["sql"], $match);
			$return[$row["name"]] = array($match[1], $match[2]);
		}
		return $return;
	}

	function trigger_options() {
		return array(
			"Timing" => array("BEFORE", "AFTER", "INSTEAD OF"),
			"Event" => array("INSERT", "UPDATE", "UPDATE OF", "DELETE"),
			"Type" => array("FOR EACH ROW"),
		);
	}

	function begin() {
		return queries("BEGIN");
	}

	function last_id($result) {
		return get_val("SELECT LAST_INSERT_ROWID()");
	}

	function explain($connection, $query) {
		return $connection->query("EXPLAIN QUERY PLAN $query");
	}

	function found_rows($table_status, $where) {
	}

	function types() {
		return array();
	}

	function create_sql($table, $auto_increment, $style) {
		$return = get_val("SELECT sql FROM sqlite_master WHERE type IN ('table', 'view') AND name = " . q($table));
		foreach (indexes($table) as $name => $index) {
			if ($name == '') {
				continue;
			}
			$return .= ";\n\n" . index_sql($table, $index['type'], $name, "(" . implode(", ", array_map('Adminer\idf_escape', $index['columns'])) . ")");
		}
		return $return;
	}

	function truncate_sql($table) {
		return "DELETE FROM " . table($table);
	}

	function use_sql($database, $style = "") {
	}

	function trigger_sql($table) {
		return implode(get_vals("SELECT sql || ';;\n' FROM sqlite_master WHERE type = 'trigger' AND tbl_name = " . q($table)));
	}

	function show_variables() {
		$return = array();
		foreach (get_rows("PRAGMA pragma_list") as $row) {
			$name = $row["name"];
			if ($name != "pragma_list" && $name != "compile_options") {
				$return[$name] = array($name, '');
				foreach (get_rows("PRAGMA $name") as $row) {
					$return[$name][1] .= implode(", ", $row) . "\n";
				}
			}
		}
		return $return;
	}

	function show_status() {
		$return = array();
		foreach (get_vals("PRAGMA compile_options") as $option) {
			$return[] = explode("=", $option, 2) + array('', '');
		}
		return $return;
	}

	function convert_field($field) {
	}

	function unconvert_field($field, $return) {
		return $return;
	}

	function support($feature) {
		return preg_match('~^(check|columns|database|drop_col|dump|indexes|descidx|move_col|sql|status|table|trigger|variables|view|view_trigger)$~', $feature);
	}
}

?>
<?php
add_driver("pgsql", "PostgreSQL");

if (isset($_GET["pgsql"])) {
	define('Adminer\DRIVER', "pgsql");

	if (extension_loaded("pgsql") && $_GET["ext"] != "pdo") {
		class PgsqlDb extends SqlDb {
			public $extension = "PgSQL";
			public $timeout = 0;
			private $link, $string, $database = true;

			function _error($errno, $error) {
				if (ini_bool("html_errors")) {
					$error = html_entity_decode(strip_tags($error));
				}
				$error = preg_replace('~^[^:]*: ~', '', $error);
				$this->error = $error;
			}

			function attach($server, $username, $password) {
				$db = adminer()->database();
				set_error_handler(array($this, '_error'));
				list($host, $port) = host_port(addcslashes($server, "'\\"));
				$this->string = "host='$host'" . ($port ? " port='$port'" : "") . " user='" . addcslashes($username, "'\\") . "' password='" . addcslashes($password, "'\\") . "'";
				$ssl = adminer()->connectSsl();
				if (isset($ssl["mode"])) {
					$this->string .= " sslmode='" . $ssl["mode"] . "'";
				}
				$this->link = @pg_connect("$this->string dbname='" . ($db != "" ? addcslashes($db, "'\\") : "postgres") . "'", PGSQL_CONNECT_FORCE_NEW);
				if (!$this->link && $db != "") {
					// try to connect directly with database for performance
					$this->database = false;
					$this->link = @pg_connect("$this->string dbname='postgres'", PGSQL_CONNECT_FORCE_NEW);
				}
				restore_error_handler();
				if ($this->link) {
					pg_set_client_encoding($this->link, "UTF8");
				}
				return ($this->link ? '' : $this->error);
			}

			function quote($string) {
				return (function_exists('pg_escape_literal')
					? pg_escape_literal($this->link, $string) // available since PHP 5.4.4
					: "'" . pg_escape_string($this->link, $string) . "'"
				);
			}

			function value($val, array $field) {
				return ($field["type"] == "bytea" && $val !== null ? pg_unescape_bytea($val) : $val);
			}

			function select_db($database) {
				if ($database == adminer()->database()) {
					return $this->database;
				}
				$return = @pg_connect("$this->string dbname='" . addcslashes($database, "'\\") . "'", PGSQL_CONNECT_FORCE_NEW);
				if ($return) {
					$this->link = $return;
				}
				return $return;
			}

			function close() {
				$this->link = @pg_connect("$this->string dbname='postgres'");
			}

			function query($query, $unbuffered = false) {
				$result = @pg_query($this->link, $query);
				$this->error = "";
				if (!$result) {
					$this->error = pg_last_error($this->link);
					$return = false;
				} elseif (!pg_num_fields($result)) {
					$this->affected_rows = pg_affected_rows($result);
					$return = true;
				} else {
					$return = new Result($result);
				}
				if ($this->timeout) {
					$this->timeout = 0;
					$this->query("RESET statement_timeout");
				}
				return $return;
			}

			function warnings() {
				return h(pg_last_notice($this->link)); // second parameter is available since PHP 7.1.0
			}

			/** Copy from array into a table
			* @param list<string> $rows
			*/
			function copyFrom($table, array $rows) {
				$this->error = '';
				set_error_handler(function ($errno, $error) {
					$this->error = (ini_bool('html_errors') ? html_entity_decode($error) : $error);
					return true;
				});
				$return = pg_copy_from($this->link, $table, $rows);
				restore_error_handler();
				return $return;
			}
		}

		class Result {
			public $num_rows;
			private $result, $offset = 0;

			function __construct($result) {
				$this->result = $result;
				$this->num_rows = pg_num_rows($result);
			}

			function fetch_assoc() {
				return pg_fetch_assoc($this->result);
			}

			function fetch_row() {
				return pg_fetch_row($this->result);
			}

			function fetch_field() {
				$column = $this->offset++;
				$return = new \stdClass;
				$return->orgtable = pg_field_table($this->result, $column);
				$return->name = pg_field_name($this->result, $column);
				$type = pg_field_type($this->result, $column);
				$return->type = (preg_match(number_type(), $type) ? 0 : 15);
				$return->charsetnr = ($type == "bytea" ? 63 : 0); // 63 - binary
				return $return;
			}

			function __destruct() {
				pg_free_result($this->result);
			}
		}

	} elseif (extension_loaded("pdo_pgsql")) {
		class PgsqlDb extends PdoDb {
			public $extension = "PDO_PgSQL";
			public $timeout = 0;

			function attach($server, $username, $password) {
				$db = adminer()->database();
				list($host, $port) = host_port(addcslashes($server, "'\\"));
				//! client_encoding is supported since 9.1, but we can't yet use min_version here
				$dsn = "pgsql:host='$host'" . ($port ? " port='$port'" : "") . " client_encoding=utf8 dbname='" . ($db != "" ? addcslashes($db, "'\\") : "postgres") . "'";
				$ssl = adminer()->connectSsl();
				if (isset($ssl["mode"])) {
					$dsn .= " sslmode='" . $ssl["mode"] . "'";
				}
				return $this->dsn($dsn, $username, $password);
			}

			function select_db($database) {
				return (adminer()->database() == $database);
			}

			function query($query, $unbuffered = false) {
				$return = parent::query($query, $unbuffered);
				if ($this->timeout) {
					$this->timeout = 0;
					parent::query("RESET statement_timeout");
				}
				return $return;
			}

			function warnings() {
				// not implemented in PDO_PgSQL as of PHP 7.2.1
			}

			function copyFrom($table, array $rows) {
				$return = $this->pdo->pgsqlCopyFromArray($table, $rows);
				$this->error = idx($this->pdo->errorInfo(), 2) ?: '';
				return $return;
			}

			function close() {
			}
		}

	}



	if (class_exists('Adminer\PgsqlDb')) {
		class Db extends PgsqlDb {
			function multi_query($query) {
				if (preg_match('~\bCOPY\s+(.+?)\s+FROM\s+stdin;\n?(.*)\n\\\\\.$~is', str_replace("\r\n", "\n", $query), $match)) { // no ^ to allow leading comments
					$rows = explode("\n", $match[2]);
					$this->affected_rows = count($rows);
					return $this->copyFrom($match[1], $rows);
				}
				return parent::multi_query($query);
			}
		}
	}



	class Driver extends SqlDriver {
		static $extensions = array("PgSQL", "PDO_PgSQL");
		static $jush = "pgsql";

		public $operators = array("=", "<", ">", "<=", ">=", "!=", "~", "!~", "LIKE", "LIKE %%", "ILIKE", "ILIKE %%", "IN", "IS NULL", "NOT LIKE", "NOT ILIKE", "NOT IN", "IS NOT NULL", "SQL"); //! SQL - same-site CSRF
		public $functions = array("char_length", "lower", "round", "to_hex", "to_timestamp", "upper");
		public $grouping = array("avg", "count", "count distinct", "max", "min", "sum");

		public $nsOid = "(SELECT oid FROM pg_namespace WHERE nspname = current_schema())";

		static function connect($server, $username, $password) {
			$connection = parent::connect($server, $username, $password);
			if (is_string($connection)) {
				return $connection;
			}
			$version = get_val("SELECT version()", 0, $connection);
			$connection->flavor = (preg_match('~CockroachDB~', $version) ? 'cockroach' : '');
			$connection->server_info = preg_replace('~^\D*([\d.]+[-\w]*).*~', '\1', $version);
			if (min_version(9, 0, $connection)) {
				$connection->query("SET application_name = 'Adminer'");
			}
			if ($connection->flavor == 'cockroach') { // we don't use "PostgreSQL / CockroachDB" by default because it's too long
				add_driver(DRIVER, "CockroachDB");
			}
			return $connection;
		}

		function __construct(Db $connection) {
			parent::__construct($connection);
			$this->types = array( //! arrays
				'Numbers' => array("smallint" => 5, "integer" => 10, "bigint" => 19, "boolean" => 1, "numeric" => 0, "real" => 7, "double precision" => 16, "money" => 20),
				'Date and time' => array("date" => 13, "time" => 17, "timestamp" => 20, "timestamptz" => 21, "interval" => 0),
				'Strings' => array("character" => 0, "character varying" => 0, "text" => 0, "tsquery" => 0, "tsvector" => 0, "uuid" => 0, "xml" => 0),
				'Binary' => array("bit" => 0, "bit varying" => 0, "bytea" => 0),
				'Network' => array("cidr" => 43, "inet" => 43, "macaddr" => 17, "macaddr8" => 23, "txid_snapshot" => 0),
				'Geometry' => array("box" => 0, "circle" => 0, "line" => 0, "lseg" => 0, "path" => 0, "point" => 0, "polygon" => 0),
			);
			if (min_version(9.2, 0, $connection)) {
				$this->types['Strings']["json"] = 4294967295;
				if (min_version(9.4, 0, $connection)) {
					$this->types['Strings']["jsonb"] = 4294967295;
				}
			}
			$this->insertFunctions = array(
				"char" => "md5",
				"date|time" => "now",
			);
			$this->editFunctions = array(
				number_type() => "+/-",
				"date|time" => "+ interval/- interval", //! escape
				"char|text" => "||",
			);
			if (min_version(12, 0, $connection)) {
				$this->generated = array("STORED");
			}
			$this->partitionBy = array("RANGE", "LIST");
			if (!$connection->flavor) {
				$this->partitionBy[] = "HASH";
			}
		}

		function enumLength(array $field) {
			$enum = $this->types['User types'][$field["type"]];
			return ($enum ? type_values($enum) : "");
		}

		function setUserTypes($types) {
			$this->types['User types'] = array_flip($types);
		}

		function insertReturning($table) {
			$auto_increment = array_filter(fields($table), function ($field) {
				return $field['auto_increment'];
			});
			return (count($auto_increment) == 1 ? " RETURNING " . idf_escape(key($auto_increment)) : "");
		}

		function insertUpdate($table, array $rows, array $primary) {
			foreach ($rows as $set) {
				$update = array();
				$where = array();
				foreach ($set as $key => $val) {
					$update[] = "$key = $val";
					if (isset($primary[idf_unescape($key)])) {
						$where[] = "$key = $val";
					}
				}
				if (
					!(($where && queries("UPDATE " . table($table) . " SET " . implode(", ", $update) . " WHERE " . implode(" AND ", $where)) && $this->conn->affected_rows)
					|| queries("INSERT INTO " . table($table) . " (" . implode(", ", array_keys($set)) . ") VALUES (" . implode(", ", $set) . ")"))
				) {
					return false;
				}
			}
			return true;
		}

		function slowQuery($query, $timeout) {
			$this->conn->query("SET statement_timeout = " . (1000 * $timeout));
			$this->conn->timeout = 1000 * $timeout;
			return $query;
		}

		function convertSearch($idf, array $val, array $field) {
			$textTypes = "char|text";
			if (strpos($val["op"], "LIKE") === false) {
				$textTypes .= "|date|time(stamp)?|boolean|uuid|inet|cidr|macaddr|" . number_type();
			}

			return (preg_match("~$textTypes~", $field["type"]) ? $idf : "CAST($idf AS text)");
		}

		function quoteBinary($s) {
			return "'\\x" . bin2hex($s) . "'"; // available since PostgreSQL 8.1
		}

		function warnings() {
			return $this->conn->warnings();
		}

		function tableHelp($name, $is_view = false) {
			$links = array(
				"information_schema" => "infoschema",
				"pg_catalog" => ($is_view ? "view" : "catalog"),
			);
			$link = $links[$_GET["ns"]];
			if ($link) {
				return "$link-" . str_replace("_", "-", $name) . ".html";
			}
		}

		function inheritsFrom($table) {
			return get_vals("SELECT relname FROM pg_class JOIN pg_inherits ON inhparent = oid WHERE inhrelid = " . $this->tableOid($table) . " ORDER BY 1");
		}

		function inheritedTables($table) {
			return get_vals("SELECT relname FROM pg_inherits JOIN pg_class ON inhrelid = oid WHERE inhparent = " . $this->tableOid($table) . " ORDER BY 1");
		}

		function partitionsInfo($table) {
			$row = (min_version(10) ? $this->conn->query("SELECT * FROM pg_partitioned_table WHERE partrelid = " . $this->tableOid($table))->fetch_assoc() : null);
			if ($row) {
				$attrs = get_vals("SELECT attname FROM pg_attribute WHERE attrelid = $row[partrelid] AND attnum IN (" . str_replace(" ", ", ", $row["partattrs"]) . ")"); //! ordering
				$by = array('h' => 'HASH', 'l' => 'LIST', 'r' => 'RANGE');
				return array(
					"partition_by" => $by[$row["partstrat"]],
					"partition" => implode(", ", array_map('Adminer\idf_escape', $attrs)),
				);
			}
			return array();
		}

		function tableOid($table) {
			return "(SELECT oid FROM pg_class WHERE relnamespace = $this->nsOid AND relname = " . q($table) . " AND relkind IN ('r', 'm', 'v', 'f', 'p'))";
		}

		function indexAlgorithms(array $tableStatus) {
			static $return = array();
			if (!$return) {
				$return = get_vals("SELECT amname FROM pg_am" . (min_version(9.6) ? " WHERE amtype = 'i'" : "") . " ORDER BY amname = '" . ($this->conn->flavor == 'cockroach' ? "prefix" : "btree") . "' DESC, amname");
			}
			return $return;
		}

		function supportsIndex(array $table_status) {
			// returns true for "materialized view"
			return $table_status["Engine"] != "view";
		}

		function hasCStyleEscapes() {
			static $c_style;
			if ($c_style === null) {
				$c_style = (get_val("SHOW standard_conforming_strings", 0, $this->conn) == "off");
			}
			return $c_style;
		}
	}



	function idf_escape($idf) {
		return '"' . str_replace('"', '""', $idf) . '"';
	}

	function table($idf) {
		return idf_escape($idf);
	}

	function get_databases($flush) {
		return get_vals("SELECT datname FROM pg_database
WHERE datallowconn = TRUE AND has_database_privilege(datname, 'CONNECT')
ORDER BY datname");
	}

	function limit($query, $where, $limit, $offset = 0, $separator = " ") {
		return " $query$where" . ($limit ? $separator . "LIMIT $limit" . ($offset ? " OFFSET $offset" : "") : "");
	}

	function limit1($table, $query, $where, $separator = "\n") {
		return (preg_match('~^INTO~', $query)
			? limit($query, $where, 1, 0, $separator)
			: " $query" . (is_view(table_status1($table)) ? $where : $separator . "WHERE ctid = (SELECT ctid FROM " . table($table) . $where . $separator . "LIMIT 1)")
		);
	}

	function db_collation($db, $collations) {
		return get_val("SELECT datcollate FROM pg_database WHERE datname = " . q($db));
	}

	function logged_user() {
		return get_val("SELECT user");
	}

	function tables_list() {
		$query = "SELECT table_name, table_type FROM information_schema.tables WHERE table_schema = current_schema()";
		if (support("materializedview")) {
			$query .= "
UNION ALL
SELECT matviewname, 'MATERIALIZED VIEW'
FROM pg_matviews
WHERE schemaname = current_schema()";
		}
		$query .= "
ORDER BY 1";
		return get_key_vals($query);
	}

	function count_tables($databases) {
		$return = array();
		foreach ($databases as $db) {
			if (connection()->select_db($db)) {
				$return[$db] = count(tables_list());
			}
		}
		return $return;
	}

	function table_status($name = "") {
		static $has_size;
		if ($has_size === null) {
			// https://github.com/cockroachdb/cockroach/issues/40391
			$has_size = get_val("SELECT 'pg_table_size'::regproc");
		}
		$return = array();
		foreach (
			get_rows("SELECT
	relname AS \"Name\",
	CASE relkind WHEN 'v' THEN 'view' WHEN 'm' THEN 'materialized view' ELSE 'table' END AS \"Engine\"" . ($has_size ? ",
	pg_table_size(c.oid) AS \"Data_length\",
	pg_indexes_size(c.oid) AS \"Index_length\"" : "") . ",
	obj_description(c.oid, 'pg_class') AS \"Comment\",
	" . (min_version(12) ? "''" : "CASE WHEN relhasoids THEN 'oid' ELSE '' END") . " AS \"Oid\",
	reltuples AS \"Rows\",
	" . (min_version(10) ? "relispartition::int AS partition," : "") . "
	current_schema() AS nspname
FROM pg_class c
WHERE relkind IN ('r', 'm', 'v', 'f', 'p')
AND relnamespace = " . driver()->nsOid . "
" . ($name != "" ? "AND relname = " . q($name) : "ORDER BY relname")) as $row //! Auto_increment
		) {
			$return[$row["Name"]] = $row;
		}
		return $return;
	}

	function is_view($table_status) {
		return in_array($table_status["Engine"], array("view", "materialized view"));
	}

	function fk_support($table_status) {
		return true;
	}

	function fields($table) {
		$return = array();
		$aliases = array(
			'timestamp without time zone' => 'timestamp',
			'timestamp with time zone' => 'timestamptz',
		);
		foreach (
			get_rows("SELECT
	a.attname AS field,
	format_type(a.atttypid, a.atttypmod) AS full_type,
	pg_get_expr(d.adbin, d.adrelid) AS default,
	a.attnotnull::int,
	i.indrelid AS primary,
	col_description(a.attrelid, a.attnum) AS comment" . (min_version(10) ? ",
	a.attidentity" . (min_version(12) ? ",
	a.attgenerated" : "") : "") . "
FROM pg_attribute a
LEFT JOIN pg_attrdef d ON a.attrelid = d.adrelid AND a.attnum = d.adnum
LEFT JOIN pg_index i ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey) AND i.indisprimary
WHERE a.attrelid = " . driver()->tableOid($table) . "
AND NOT a.attisdropped
AND a.attnum > 0
ORDER BY a.attnum") as $row
		) {
			//! collation
			preg_match('~([^([]+)(\((.*)\))?([a-z ]+)?((\[[0-9]*])*)$~', $row["full_type"], $match);
			list(, $type, $length, $row["length"], $addon, $array) = $match;
			$row["length"] .= $array;
			$check_type = $type . $addon;
			if (isset($aliases[$check_type])) {
				$row["type"] = $aliases[$check_type];
				$row["full_type"] = $row["type"] . $length . $array;
			} else {
				$row["type"] = $type;
				$row["full_type"] = $row["type"] . $length . $addon . $array;
			}
			if (in_array($row['attidentity'], array('a', 'd'))) {
				$row['default'] = 'GENERATED ' . ($row['attidentity'] == 'd' ? 'BY DEFAULT' : 'ALWAYS') . ' AS IDENTITY';
			}
			$row["generated"] = ($row["attgenerated"] == "s" ? "STORED" : "");
			$row["null"] = !$row["attnotnull"];
			$row["auto_increment"] = $row['attidentity'] || preg_match('~^nextval\(~i', $row["default"])
				|| preg_match('~^unique_rowid\(~', $row["default"]); // CockroachDB
			$row["privileges"] = array("insert" => 1, "select" => 1, "update" => 1, "where" => 1, "order" => 1);
			if (!$row['generated'] && preg_match('~(.+)::[^,)]+(.*)~', $row["default"], $match)) {
				$row["default"] = ($match[1] == "NULL" ? null : idf_unescape($match[1]) . $match[2]);
			}
			$return[$row["field"]] = $row;
		}
		return $return;
	}

	function indexes($table, $connection2 = null) {
		$connection2 = connection($connection2);
		$return = array();
		$table_oid = driver()->tableOid($table);
		$columns = get_key_vals("SELECT attnum, attname FROM pg_attribute WHERE attrelid = $table_oid AND attnum > 0", $connection2);
		foreach (
			get_rows("SELECT relname, indisunique::int, indisprimary::int, indkey, indoption, amname, pg_get_expr(indpred, indrelid, true) AS partial, pg_get_expr(indexprs, indrelid) AS indexpr
FROM pg_index
JOIN pg_class ON indexrelid = oid
JOIN pg_am ON pg_am.oid = pg_class.relam
WHERE indrelid = $table_oid
ORDER BY indisprimary DESC, indisunique DESC", $connection2) as $row
		) {
			$relname = $row["relname"];
			$return[$relname]["type"] = ($row["indisprimary"] ? "PRIMARY" : ($row["indisunique"] ? "UNIQUE" : "INDEX"));
			$return[$relname]["columns"] = array();
			$return[$relname]["descs"] = array();
			$return[$relname]["algorithm"] = $row["amname"];
			$return[$relname]["partial"] = $row["partial"];
			$indexpr = preg_split('~(?<=\)), (?=\()~', $row["indexpr"]); //! '), (' used in expression
			foreach (explode(" ", $row["indkey"]) as $indkey) {
				$return[$relname]["columns"][] = ($indkey ? $columns[$indkey] : array_shift($indexpr));
			}
			foreach (explode(" ", $row["indoption"]) as $indoption) {
				$return[$relname]["descs"][] = (intval($indoption) & 1 ? '1' : null); // 1 - INDOPTION_DESC
			}
			$return[$relname]["lengths"] = array();
		}
		return $return;
	}

	function foreign_keys($table) {
		$return = array();
		foreach (
			get_rows("SELECT conname, condeferrable::int AS deferrable, condeferred::int AS deferred, pg_get_constraintdef(oid) AS definition
FROM pg_constraint
WHERE conrelid = " . driver()->tableOid($table) . "
AND contype = 'f'::char
ORDER BY conkey, conname") as $row
		) {
			$row['deferrable'] = ($row['deferrable'] ? '' : 'NOT ') . 'DEFERRABLE' . ($row['deferred'] ? ' INITIALLY DEFERRED' : '');
			if (preg_match('~FOREIGN KEY\s*\((.+)\)\s*REFERENCES (.+)\((.+)\)(.*)$~iA', $row['definition'], $match)) {
				$row['source'] = array_map('Adminer\idf_unescape', array_map('trim', explode(',', $match[1])));
				if (preg_match('~^(("([^"]|"")+"|[^"]+)\.)?"?("([^"]|"")+"|[^"]+)$~', $match[2], $match2)) {
					$row['ns'] = idf_unescape($match2[2]);
					$row['table'] = idf_unescape($match2[4]);
				}
				$row['target'] = array_map('Adminer\idf_unescape', array_map('trim', explode(',', $match[3])));
				$row['on_delete'] = (preg_match("~ON DELETE (" . driver()->onActions . ")~", $match[4], $match2) ? $match2[1] : 'NO ACTION');
				$row['on_update'] = (preg_match("~ON UPDATE (" . driver()->onActions . ")~", $match[4], $match2) ? $match2[1] : 'NO ACTION');
				$return[$row['conname']] = $row;
			}
		}
		return $return;
	}

	function view($name) {
		return array("select" => trim(get_val("SELECT pg_get_viewdef(" . driver()->tableOid($name) . ")")));
	}

	function collations() {
		//! supported in CREATE DATABASE
		return array();
	}

	function information_schema($db) {
		return get_schema() == "information_schema";
	}

	function error() {
		$return = h(connection()->error);
		if (preg_match('~^(.*\n)?([^\n]*)\n( *)\^(\n.*)?$~s', $return, $match)) {
			$return = $match[1] . preg_replace('~((?:[^&]|&[^;]*;){' . strlen($match[3]) . '})(.*)~', '\1<b>\2</b>', $match[2]) . $match[4];
		}
		return nl_br($return);
	}

	function create_database($db, $collation) {
		return queries("CREATE DATABASE " . idf_escape($db) . ($collation ? " ENCODING " . idf_escape($collation) : ""));
	}

	function drop_databases($databases) {
		connection()->close();
		return apply_queries("DROP DATABASE", $databases, 'Adminer\idf_escape');
	}

	function rename_database($name, $collation) {
		connection()->close();
		return queries("ALTER DATABASE " . idf_escape(DB) . " RENAME TO " . idf_escape($name));
	}

	function auto_increment() {
		return "";
	}

	function alter_table($table, $name, $fields, $foreign, $comment, $engine, $collation, $auto_increment, $partitioning) {
		$alter = array();
		$queries = array();
		if ($table != "" && $table != $name) {
			$queries[] = "ALTER TABLE " . table($table) . " RENAME TO " . table($name);
		}
		$sequence = "";
		foreach ($fields as $field) {
			$column = idf_escape($field[0]);
			$val = $field[1];
			if (!$val) {
				$alter[] = "DROP $column";
			} else {
				$val5 = $val[5];
				unset($val[5]);
				if ($field[0] == "") {
					if (isset($val[6])) { // auto_increment
						$val[1] = ($val[1] == " bigint" ? " big" : ($val[1] == " smallint" ? " small" : " ")) . "serial";
					}
					$alter[] = ($table != "" ? "ADD " : "  ") . implode($val);
					if (isset($val[6])) {
						$alter[] = ($table != "" ? "ADD" : " ") . " PRIMARY KEY ($val[0])";
					}
				} else {
					if ($column != $val[0]) {
						$queries[] = "ALTER TABLE " . table($name) . " RENAME $column TO $val[0]";
					}
					$alter[] = "ALTER $column TYPE$val[1]";
					$sequence_name = $table . "_" . idf_unescape($val[0]) . "_seq";
					$alter[] = "ALTER $column " . ($val[3] ? "SET" . preg_replace('~GENERATED ALWAYS(.*) STORED~', 'EXPRESSION\1', $val[3])
						: (isset($val[6]) ? "SET DEFAULT nextval(" . q($sequence_name) . ")"
						: "DROP DEFAULT" //! change to DROP EXPRESSION with generated columns
					));
					if (isset($val[6])) {
						$sequence = "CREATE SEQUENCE IF NOT EXISTS " . idf_escape($sequence_name) . " OWNED BY " . idf_escape($table) . ".$val[0]";
					}
					$alter[] = "ALTER $column " . ($val[2] == " NULL" ? "DROP NOT" : "SET") . $val[2];
				}
				if ($field[0] != "" || $val5 != "") {
					$queries[] = "COMMENT ON COLUMN " . table($name) . ".$val[0] IS " . ($val5 != "" ? substr($val5, 9) : "''");
				}
			}
		}
		$alter = array_merge($alter, $foreign);
		if ($table == "") {
			$status = "";
			if ($partitioning) {
				$cockroach = (connection()->flavor == 'cockroach');
				$status = " PARTITION BY $partitioning[partition_by]($partitioning[partition])";
				if ($partitioning["partition_by"] == 'HASH') {
					$partitions = +$partitioning["partitions"];
					for ($i=0; $i < $partitions; $i++) {
						$queries[] = "CREATE TABLE " . idf_escape($name . "_$i") . " PARTITION OF " . idf_escape($name) . " FOR VALUES WITH (MODULUS $partitions, REMAINDER $i)";
					}
				} else {
					$prev = "MINVALUE";
					foreach ($partitioning["partition_names"] as $i => $val) {
						$value = $partitioning["partition_values"][$i];
						$partition = " VALUES " . ($partitioning["partition_by"] == 'LIST' ? "IN ($value)" : "FROM ($prev) TO ($value)");
						if ($cockroach) {
							$status .= ($i ? "," : " (") . "\n  PARTITION " . (preg_match('~^DEFAULT$~i', $val) ? $val : idf_escape($val)) . "$partition";
						} else {
							$queries[] = "CREATE TABLE " . idf_escape($name . "_$val") . " PARTITION OF " . idf_escape($name) . " FOR$partition";
						}
						$prev = $value;
					}
					$status .= ($cockroach ? "\n)" : "");
				}
			}
			array_unshift($queries, "CREATE TABLE " . table($name) . " (\n" . implode(",\n", $alter) . "\n)$status");
		} elseif ($alter) {
			array_unshift($queries, "ALTER TABLE " . table($table) . "\n" . implode(",\n", $alter));
		}
		if ($sequence) {
			array_unshift($queries, $sequence);
		}
		if ($comment !== null) {
			$queries[] = "COMMENT ON TABLE " . table($name) . " IS " . q($comment);
		}
		// if ($auto_increment != "") {
			//! $queries[] = "SELECT setval(pg_get_serial_sequence(" . q($name) . ", ), $auto_increment)";
		// }
		foreach ($queries as $query) {
			if (!queries($query)) {
				return false;
			}
		}
		return true;
	}

	function alter_indexes($table, $alter) {
		$create = array();
		$drop = array();
		$queries = array();
		foreach ($alter as $val) {
			if ($val[0] != "INDEX") {
				//! descending UNIQUE indexes result in syntax error
				$create[] = ($val[2] == "DROP"
					? "\nDROP CONSTRAINT " . idf_escape($val[1])
					: "\nADD" . ($val[1] != "" ? " CONSTRAINT " . idf_escape($val[1]) : "") . " $val[0] " . ($val[0] == "PRIMARY" ? "KEY " : "") . "(" . implode(", ", $val[2]) . ")"
				);
			} elseif ($val[2] == "DROP") {
				$drop[] = idf_escape($val[1]);
			} else {
				$queries[] = "CREATE INDEX " . idf_escape($val[1] != "" ? $val[1] : uniqid($table . "_"))
					. " ON " . table($table)
					. ($val[3] ? " USING $val[3]" : "")
					. " (" . implode(", ", $val[2]) . ")"
					. ($val[4] ? " WHERE $val[4]" : "")
				;
			}
		}
		if ($create) {
			array_unshift($queries, "ALTER TABLE " . table($table) . implode(",", $create));
		}
		if ($drop) {
			array_unshift($queries, "DROP INDEX " . implode(", ", $drop));
		}
		foreach ($queries as $query) {
			if (!queries($query)) {
				return false;
			}
		}
		return true;
	}

	function truncate_tables($tables) {
		return queries("TRUNCATE " . implode(", ", array_map('Adminer\table', $tables)));
	}

	function drop_views($views) {
		return drop_tables($views);
	}

	function drop_tables($tables) {
		foreach ($tables as $table) {
			$status = table_status1($table);
			if (!queries("DROP " . strtoupper($status["Engine"]) . " " . table($table))) {
				return false;
			}
		}
		return true;
	}

	function move_tables($tables, $views, $target) {
		foreach (array_merge($tables, $views) as $table) {
			$status = table_status1($table);
			if (!queries("ALTER " . strtoupper($status["Engine"]) . " " . table($table) . " SET SCHEMA " . idf_escape($target))) {
				return false;
			}
		}
		return true;
	}

	function trigger($name, $table) {
		if ($name == "") {
			return array("Statement" => "EXECUTE PROCEDURE ()");
		}
		$columns = array();
		$where = "WHERE trigger_schema = current_schema() AND event_object_table = " . q($table) . " AND trigger_name = " . q($name);
		foreach (get_rows("SELECT * FROM information_schema.triggered_update_columns $where") as $row) {
			$columns[] = $row["event_object_column"];
		}
		$return = array();
		foreach (
			get_rows('SELECT trigger_name AS "Trigger", action_timing AS "Timing", event_manipulation AS "Event", \'FOR EACH \' || action_orientation AS "Type", action_statement AS "Statement"
FROM information_schema.triggers' . "
$where
ORDER BY event_manipulation DESC") as $row
		) {
			if ($columns && $row["Event"] == "UPDATE") {
				$row["Event"] .= " OF";
			}
			$row["Of"] = implode(", ", $columns);
			if ($return) {
				$row["Event"] .= " OR $return[Event]";
			}
			$return = $row;
		}
		return $return;
	}

	function triggers($table) {
		$return = array();
		foreach (get_rows("SELECT * FROM information_schema.triggers WHERE trigger_schema = current_schema() AND event_object_table = " . q($table)) as $row) {
			$trigger = trigger($row["trigger_name"], $table);
			$return[$trigger["Trigger"]] = array($trigger["Timing"], $trigger["Event"]);
		}
		return $return;
	}

	function trigger_options() {
		return array(
			"Timing" => array("BEFORE", "AFTER"),
			"Event" => array("INSERT", "UPDATE", "UPDATE OF", "DELETE", "INSERT OR UPDATE", "INSERT OR UPDATE OF", "DELETE OR INSERT", "DELETE OR UPDATE", "DELETE OR UPDATE OF", "DELETE OR INSERT OR UPDATE", "DELETE OR INSERT OR UPDATE OF"),
			"Type" => array("FOR EACH ROW", "FOR EACH STATEMENT"),
		);
	}

	function routine($name, $type) {
		$rows = get_rows('SELECT routine_definition AS definition, LOWER(external_language) AS language, *
FROM information_schema.routines
WHERE routine_schema = current_schema() AND specific_name = ' . q($name));
		$return = idx($rows, 0, array());
		$return["returns"] = array("type" => $return["type_udt_name"]);
		$return["fields"] = get_rows('SELECT COALESCE(parameter_name, ordinal_position::text) AS field, data_type AS type, character_maximum_length AS length, parameter_mode AS inout
FROM information_schema.parameters
WHERE specific_schema = current_schema() AND specific_name = ' . q($name) . '
ORDER BY ordinal_position');
		return $return;
	}

	function routines() {
		return get_rows('SELECT specific_name AS "SPECIFIC_NAME", routine_type AS "ROUTINE_TYPE", routine_name AS "ROUTINE_NAME", type_udt_name AS "DTD_IDENTIFIER"
FROM information_schema.routines
WHERE routine_schema = current_schema()
ORDER BY SPECIFIC_NAME');
	}

	function routine_languages() {
		return get_vals("SELECT LOWER(lanname) FROM pg_catalog.pg_language");
	}

	function routine_id($name, $row) {
		$return = array();
		foreach ($row["fields"] as $field) {
			$length = $field["length"];
			$return[] = $field["type"] . ($length ? "($length)" : "");
		}
		return idf_escape($name) . "(" . implode(", ", $return) . ")";
	}

	function last_id($result) {
		$row = (is_object($result) ? $result->fetch_row() : array());
		return ($row ? $row[0] : 0);
	}

	function explain($connection, $query) {
		return $connection->query("EXPLAIN $query");
	}

	function found_rows($table_status, $where) {
		if (preg_match("~ rows=([0-9]+)~", get_val("EXPLAIN SELECT * FROM " . idf_escape($table_status["Name"]) . ($where ? " WHERE " . implode(" AND ", $where) : "")), $regs)) {
			return $regs[1];
		}
	}

	function types() {
		return get_key_vals(
			"SELECT oid, typname
FROM pg_type
WHERE typnamespace = " . driver()->nsOid . "
AND typtype IN ('b','d','e')
AND typelem = 0"
		);
	}

	function type_values($id) {
		// to get values from type string: unnest(enum_range(NULL::"$type"))
		$enums = get_vals("SELECT enumlabel FROM pg_enum WHERE enumtypid = $id ORDER BY enumsortorder");
		return ($enums ? "'" . implode("', '", array_map('addslashes', $enums)) . "'" : "");
	}

	function schemas() {
		return get_vals("SELECT nspname FROM pg_namespace ORDER BY nspname");
	}

	function get_schema() {
		return get_val("SELECT current_schema()");
	}

	function set_schema($schema, $connection2 = null) {
		if (!$connection2) {
			$connection2 = connection();
		}
		$return = $connection2->query("SET search_path TO " . idf_escape($schema));
		driver()->setUserTypes(types()); //! get types from current_schemas('t')
		return $return;
	}

	// create_sql() produces CREATE TABLE without FK CONSTRAINTs
	// foreign_keys_sql() produces all FK CONSTRAINTs as ALTER TABLE ... ADD CONSTRAINT
	// so that all FKs can be added after all tables have been created, avoiding any need to reorder CREATE TABLE statements in order of their FK dependencies
	function foreign_keys_sql($table) {
		$return = "";

		$status = table_status1($table);
		$fkeys = foreign_keys($table);
		ksort($fkeys);

		foreach ($fkeys as $fkey_name => $fkey) {
			$return .= "ALTER TABLE ONLY " . idf_escape($status['nspname']) . "." . idf_escape($status['Name']) . " ADD CONSTRAINT " . idf_escape($fkey_name) . " $fkey[definition];\n";
		}

		return ($return ? "$return\n" : $return);
	}

	function create_sql($table, $auto_increment, $style) {
		$return_parts = array();
		$sequences = array();

		$status = table_status1($table);
		$ns = idf_escape($status['nspname']);
		if (is_view($status)) {
			$view = view($table);
			return rtrim("CREATE VIEW $ns." . idf_escape($table) . " AS $view[select]", ";");
		}
		$fields = fields($table);

		if (count($status) < 2 || empty($fields)) {
			return false;
		}

		$return = "CREATE TABLE $ns." . idf_escape($status['Name']) . " (\n    ";

		// fields' definitions
		foreach ($fields as $field) {
			$part = idf_escape($field['field']) . ' ' . $field['full_type']
				. default_value($field)
				. ($field['null'] ? "" : " NOT NULL");
			$return_parts[] = $part;

			// sequences for fields
			if (preg_match('~nextval\(\'([^\']+)\'\)~', $field['default'], $matches)) {
				$sequence_name = $matches[1];
				$sq = first(get_rows((min_version(10)
					? "SELECT *, cache_size AS cache_value FROM pg_sequences WHERE schemaname = current_schema() AND sequencename = " . q(idf_unescape($sequence_name))
					: "SELECT * FROM $sequence_name"
				), null, "-- "));
				$sequences[] = ($style == "DROP+CREATE" ? "DROP SEQUENCE IF EXISTS $ns.$sequence_name;\n" : "")
					. "CREATE SEQUENCE $ns.$sequence_name INCREMENT $sq[increment_by] MINVALUE $sq[min_value] MAXVALUE $sq[max_value]"
					. ($auto_increment && $sq['last_value'] ? " START " . ($sq["last_value"] + 1) : "")
					. " CACHE $sq[cache_value];"
				;
			}
		}

		// adding sequences before table definition
		if (!empty($sequences)) {
			$return = implode("\n\n", $sequences) . "\n\n$return";
		}

		$primary = "";
		foreach (indexes($table) as $index_name => $index) {
			if ($index['type'] == 'PRIMARY') {
				$primary = $index_name;
				$return_parts[] = "CONSTRAINT " . idf_escape($index_name) . " PRIMARY KEY (" . implode(', ', array_map('Adminer\idf_escape', $index['columns'])) . ")";
			}
		}

		foreach (driver()->checkConstraints($table) as $conname => $consrc) {
			$return_parts[] = "CONSTRAINT " . idf_escape($conname) . " CHECK ($consrc)";
		}
		$return .= implode(",\n    ", $return_parts) . "\n)";

		$partition = driver()->partitionsInfo($status['Name']);
		if ($partition) {
			$return .= "\nPARTITION BY $partition[partition_by]($partition[partition])";
		}
		//! parse pg_class.relpartbound to create PARTITION OF
		//! don't insert partitioned data twice

		$return .= "\nWITH (oids = " . ($status['Oid'] ? 'true' : 'false') . ");";

		// comments for table & fields
		if ($status['Comment']) {
			$return .= "\n\nCOMMENT ON TABLE $ns." . idf_escape($status['Name']) . " IS " . q($status['Comment']) . ";";
		}

		foreach ($fields as $field_name => $field) {
			if ($field['comment']) {
				$return .= "\n\nCOMMENT ON COLUMN $ns." . idf_escape($status['Name']) . "." . idf_escape($field_name) . " IS " . q($field['comment']) . ";";
			}
		}

		foreach (get_rows("SELECT indexdef FROM pg_catalog.pg_indexes WHERE schemaname = current_schema() AND tablename = " . q($table) . ($primary ? " AND indexname != " . q($primary) : ""), null, "-- ") as $row) {
			$return .= "\n\n$row[indexdef];";
		}

		return rtrim($return, ';');
	}

	function truncate_sql($table) {
		return "TRUNCATE " . table($table);
	}

	function trigger_sql($table) {
		$status = table_status1($table);
		$return = "";
		foreach (triggers($table) as $trg_id => $trg) {
			$trigger = trigger($trg_id, $status['Name']);
			$return .= "\nCREATE TRIGGER " . idf_escape($trigger['Trigger']) . " $trigger[Timing] $trigger[Event] ON " . idf_escape($status["nspname"]) . "." . idf_escape($status['Name']) . " $trigger[Type] $trigger[Statement];;\n";
		}
		return $return;
	}


	function use_sql($database, $style = "") {
		$name = idf_escape($database);
		$return = "";
		if (preg_match('~CREATE~', $style)) {
			if ($style == "DROP+CREATE") {
				$return = "DROP DATABASE IF EXISTS $name;\n";
			}
			$return .= "CREATE DATABASE $name;\n"; //! get info from pg_database
		}
		return "$return\\connect $name";
	}

	function show_variables() {
		return get_rows("SHOW ALL");
	}

	function process_list() {
		return get_rows("SELECT * FROM pg_stat_activity ORDER BY " . (min_version(9.2) ? "pid" : "procpid"));
	}

	function convert_field($field) {
	}

	function unconvert_field($field, $return) {
		return $return;
	}

	function support($feature) {
		return preg_match('~^(check|columns|comment|database|drop_col|dump|descidx|indexes|kill|partial_indexes|routine|scheme|sequence|sql|table|trigger|type|variables|view'
			. (min_version(9.3) ? '|materializedview' : '')
			. (min_version(11) ? '|procedure' : '')
			. (connection()->flavor == 'cockroach' ? '' : '|processlist') // https://github.com/cockroachdb/cockroach/issues/24745
			. ')$~', $feature)
		;
	}

	function kill_process($val) {
		return queries("SELECT pg_terminate_backend(" . number($val) . ")");
	}

	function connection_id() {
		return "SELECT pg_backend_pid()";
	}

	function max_connections() {
		return get_val("SHOW max_connections");
	}
}

?>
<?php
add_driver("oracle", "Oracle (beta)");

if (isset($_GET["oracle"])) {
	define('Adminer\DRIVER', "oracle");

	if (extension_loaded("oci8") && $_GET["ext"] != "pdo") {
		class Db extends SqlDb {
			public $extension = "oci8";
			public $_current_db;
			private $link;

			function _error($errno, $error) {
				if (ini_bool("html_errors")) {
					$error = html_entity_decode(strip_tags($error));
				}
				$error = preg_replace('~^[^:]*: ~', '', $error);
				$this->error = $error;
			}

			function attach($server, $username, $password) {
				$this->link = @oci_new_connect($username, $password, $server, "AL32UTF8");
				if ($this->link) {
					$this->server_info = oci_server_version($this->link);
					return '';
				}
				$error = oci_error();
				return $error["message"];
			}

			function quote($string) {
				return "'" . str_replace("'", "''", $string) . "'";
			}

			function select_db($database) {
				$this->_current_db = $database;
				return true;
			}

			function query($query, $unbuffered = false) {
				$result = oci_parse($this->link, $query);
				$this->error = "";
				if (!$result) {
					$error = oci_error($this->link);
					$this->errno = $error["code"];
					$this->error = $error["message"];
					return false;
				}
				set_error_handler(array($this, '_error'));
				$return = @oci_execute($result);
				restore_error_handler();
				if ($return) {
					if (oci_num_fields($result)) {
						return new Result($result);
					}
					$this->affected_rows = oci_num_rows($result);
					oci_free_statement($result);
				}
				return $return;
			}

			function timeout($ms) {
				return oci_set_call_timeout($this->link, $ms);
			}
		}

		class Result {
			public $num_rows;
			private $result, $offset = 1;

			function __construct($result) {
				$this->result = $result;
			}

			private function convert($row) {
				foreach ((array) $row as $key => $val) {
					if (is_a($val, 'OCILob') || is_a($val, 'OCI-Lob')) {
						$row[$key] = $val->load();
					}
				}
				return $row;
			}

			function fetch_assoc() {
				return $this->convert(oci_fetch_assoc($this->result));
			}

			function fetch_row() {
				return $this->convert(oci_fetch_row($this->result));
			}

			function fetch_field() {
				$column = $this->offset++;
				$return = new \stdClass;
				$return->name = oci_field_name($this->result, $column);
				$return->type = oci_field_type($this->result, $column); //! map to MySQL numbers
				$return->charsetnr = (preg_match("~raw|blob|bfile~", $return->type) ? 63 : 0); // 63 - binary
				return $return;
			}

			function __destruct() {
				oci_free_statement($this->result);
			}
		}

	} elseif (extension_loaded("pdo_oci")) {
		class Db extends PdoDb {
			public $extension = "PDO_OCI";
			public $_current_db;

			function attach($server, $username, $password) {
				return $this->dsn("oci:dbname=//$server;charset=AL32UTF8", $username, $password);
			}

			function select_db($database) {
				$this->_current_db = $database;
				return true;
			}
		}

	}



	class Driver extends SqlDriver {
		static $extensions = array("OCI8", "PDO_OCI");
		static $jush = "oracle";

		public $insertFunctions = array( //! no parentheses
			"date" => "current_date",
			"timestamp" => "current_timestamp",
		);
		public $editFunctions = array(
			"number|float|double" => "+/-",
			"date|timestamp" => "+ interval/- interval",
			"char|clob" => "||",
		);

		public $operators = array("=", "<", ">", "<=", ">=", "!=", "LIKE", "LIKE %%", "IN", "IS NULL", "NOT LIKE", "NOT IN", "IS NOT NULL", "SQL");
		public $functions = array("length", "lower", "round", "upper");
		public $grouping = array("avg", "count", "count distinct", "max", "min", "sum");

		function __construct(Db $connection) {
			parent::__construct($connection);
			$this->types = array(
				'Numbers' => array("number" => 38, "binary_float" => 12, "binary_double" => 21),
				'Date and time' => array("date" => 10, "timestamp" => 29, "interval year" => 12, "interval day" => 28), //! year(), day() to second()
				'Strings' => array("char" => 2000, "varchar2" => 4000, "nchar" => 2000, "nvarchar2" => 4000, "clob" => 4294967295, "nclob" => 4294967295),
				'Binary' => array("raw" => 2000, "long raw" => 2147483648, "blob" => 4294967295, "bfile" => 4294967296),
			);
		}

		//! support empty $set in insert()

		function begin() {
			return true; // automatic start
		}

		function insertUpdate($table, array $rows, array $primary) {
			foreach ($rows as $set) {
				$update = array();
				$where = array();
				foreach ($set as $key => $val) {
					$update[] = "$key = $val";
					if (isset($primary[idf_unescape($key)])) {
						$where[] = "$key = $val";
					}
				}
				if (
					!(($where && queries("UPDATE " . table($table) . " SET " . implode(", ", $update) . " WHERE " . implode(" AND ", $where)) && $this->conn->affected_rows)
					|| queries("INSERT INTO " . table($table) . " (" . implode(", ", array_keys($set)) . ") VALUES (" . implode(", ", $set) . ")"))
				) {
					return false;
				}
			}
			return true;
		}

		function hasCStyleEscapes() {
			return true;
		}
	}



	function idf_escape($idf) {
		return '"' . str_replace('"', '""', $idf) . '"';
	}

	function table($idf) {
		return idf_escape($idf);
	}

	function get_databases($flush) {
		return get_vals(
			"SELECT DISTINCT tablespace_name FROM (
SELECT tablespace_name FROM user_tablespaces
UNION SELECT tablespace_name FROM all_tables WHERE tablespace_name IS NOT NULL
)
ORDER BY 1"
		);
	}

	function limit($query, $where, $limit, $offset = 0, $separator = " ") {
		return ($offset ? " * FROM (SELECT t.*, rownum AS rnum FROM (SELECT $query$where) t WHERE rownum <= " . ($limit + $offset) . ") WHERE rnum > $offset"
			: ($limit ? " * FROM (SELECT $query$where) WHERE rownum <= " . ($limit + $offset)
			: " $query$where"
		));
	}

	function limit1($table, $query, $where, $separator = "\n") {
		return " $query$where"; //! limit
	}

	function db_collation($db, $collations) {
		return get_val("SELECT value FROM nls_database_parameters WHERE parameter = 'NLS_CHARACTERSET'"); //! respect $db
	}

	function logged_user() {
		return get_val("SELECT USER FROM DUAL");
	}

	function get_current_db() {
		$db = connection()->_current_db ?: DB;
		unset(connection()->_current_db);
		return $db;
	}

	function where_owner($prefix, $owner = "owner") {
		if (!$_GET["ns"]) {
			return '';
		}
		return "$prefix$owner = sys_context('USERENV', 'CURRENT_SCHEMA')";
	}

	function views_table($columns) {
		$owner = where_owner('');
		return "(SELECT $columns FROM all_views WHERE " . ($owner ?: "rownum < 0") . ")";
	}

	function tables_list() {
		$view = views_table("view_name");
		$owner = where_owner(" AND ");
		return get_key_vals(
			"SELECT table_name, 'table' FROM all_tables WHERE tablespace_name = " . q(DB) . "$owner
UNION SELECT view_name, 'view' FROM $view
ORDER BY 1"
		); //! views don't have schema
	}

	function count_tables($databases) {
		$return = array();
		foreach ($databases as $db) {
			$return[$db] = get_val("SELECT COUNT(*) FROM all_tables WHERE tablespace_name = " . q($db));
		}
		return $return;
	}

	function table_status($name = "") {
		$return = array();
		$search = q($name);
		$db = get_current_db();
		$view = views_table("view_name");
		$owner = where_owner(" AND ");
		foreach (
			get_rows('SELECT table_name "Name", \'table\' "Engine", avg_row_len * num_rows "Data_length", num_rows "Rows" FROM all_tables WHERE tablespace_name = ' . q($db) . $owner . ($name != "" ? " AND table_name = $search" : "") . "
UNION SELECT view_name, 'view', 0, 0 FROM $view" . ($name != "" ? " WHERE view_name = $search" : "") . "
ORDER BY 1") as $row
		) {
			$return[$row["Name"]] = $row;
		}
		return $return;
	}

	function is_view($table_status) {
		return $table_status["Engine"] == "view";
	}

	function fk_support($table_status) {
		return true;
	}

	function fields($table) {
		$return = array();
		$owner = where_owner(" AND ");
		foreach (get_rows("SELECT * FROM all_tab_columns WHERE table_name = " . q($table) . "$owner ORDER BY column_id") as $row) {
			$type = $row["DATA_TYPE"];
			$length = "$row[DATA_PRECISION],$row[DATA_SCALE]";
			if ($length == ",") {
				$length = $row["CHAR_COL_DECL_LENGTH"];
			} //! int
			$return[$row["COLUMN_NAME"]] = array(
				"field" => $row["COLUMN_NAME"],
				"full_type" => $type . ($length ? "($length)" : ""),
				"type" => strtolower($type),
				"length" => $length,
				"default" => $row["DATA_DEFAULT"],
				"null" => ($row["NULLABLE"] == "Y"),
				//! "auto_increment" => false,
				//! "collation" => $row["CHARACTER_SET_NAME"],
				"privileges" => array("insert" => 1, "select" => 1, "update" => 1, "where" => 1, "order" => 1),
				//! "comment" => $row["Comment"],
				//! "primary" => ($row["Key"] == "PRI"),
			);
		}
		return $return;
	}

	function indexes($table, $connection2 = null) {
		$return = array();
		$owner = where_owner(" AND ", "aic.table_owner");
		foreach (
			get_rows("SELECT aic.*, ac.constraint_type, atc.data_default
FROM all_ind_columns aic
LEFT JOIN all_constraints ac ON aic.index_name = ac.constraint_name AND aic.table_name = ac.table_name AND aic.index_owner = ac.owner
LEFT JOIN all_tab_cols atc ON aic.column_name = atc.column_name AND aic.table_name = atc.table_name AND aic.index_owner = atc.owner
WHERE aic.table_name = " . q($table) . "$owner
ORDER BY ac.constraint_type, aic.column_position", $connection2) as $row
		) {
			$index_name = $row["INDEX_NAME"];
			$column_name = $row["DATA_DEFAULT"];
			$column_name = ($column_name ? trim($column_name, '"') : $row["COLUMN_NAME"]); // trim - possibly wrapped in quotes but never contains quotes inside
			$return[$index_name]["type"] = ($row["CONSTRAINT_TYPE"] == "P" ? "PRIMARY" : ($row["CONSTRAINT_TYPE"] == "U" ? "UNIQUE" : "INDEX"));
			$return[$index_name]["columns"][] = $column_name;
			$return[$index_name]["lengths"][] = ($row["CHAR_LENGTH"] && $row["CHAR_LENGTH"] != $row["COLUMN_LENGTH"] ? $row["CHAR_LENGTH"] : null);
			$return[$index_name]["descs"][] = ($row["DESCEND"] && $row["DESCEND"] == "DESC" ? '1' : null);
		}
		return $return;
	}

	function view($name) {
		$view = views_table("view_name, text");
		$rows = get_rows('SELECT text "select" FROM ' . $view . ' WHERE view_name = ' . q($name));
		return reset($rows);
	}

	function collations() {
		return array(); //!
	}

	function information_schema($db) {
		return get_schema() == "INFORMATION_SCHEMA";
	}

	function error() {
		return h(connection()->error); //! highlight sqltext from offset
	}

	function explain($connection, $query) {
		$connection->query("EXPLAIN PLAN FOR $query");
		return $connection->query("SELECT * FROM plan_table");
	}

	function found_rows($table_status, $where) {
	}

	function auto_increment() {
		return "";
	}

	function alter_table($table, $name, $fields, $foreign, $comment, $engine, $collation, $auto_increment, $partitioning) {
		$alter = $drop = array();
		$orig_fields = ($table ? fields($table) : array());
		foreach ($fields as $field) {
			$val = $field[1];
			if ($val && $field[0] != "" && idf_escape($field[0]) != $val[0]) {
				queries("ALTER TABLE " . table($table) . " RENAME COLUMN " . idf_escape($field[0]) . " TO $val[0]");
			}
			$orig_field = $orig_fields[$field[0]];
			if ($val && $orig_field) {
				$old = process_field($orig_field, $orig_field);
				if ($val[2] == $old[2]) {
					$val[2] = "";
				}
			}
			if ($val) {
				$alter[] = ($table != "" ? ($field[0] != "" ? "MODIFY (" : "ADD (") : "  ") . implode($val) . ($table != "" ? ")" : ""); //! error with name change only
			} else {
				$drop[] = idf_escape($field[0]);
			}
		}
		if ($table == "") {
			return queries("CREATE TABLE " . table($name) . " (\n" . implode(",\n", $alter) . "\n)");
		}
		return (!$alter || queries("ALTER TABLE " . table($table) . "\n" . implode("\n", $alter)))
			&& (!$drop || queries("ALTER TABLE " . table($table) . " DROP (" . implode(", ", $drop) . ")"))
			&& ($table == $name || queries("ALTER TABLE " . table($table) . " RENAME TO " . table($name)))
		;
	}

	function alter_indexes($table, $alter) {
		$drop = array();
		$queries = array();
		foreach ($alter as $val) {
			if ($val[0] != "INDEX") {
				$val[2] = preg_replace('~ DESC$~', '', $val[2]);
				$create = ($val[2] == "DROP"
					? "\nDROP CONSTRAINT " . idf_escape($val[1])
					: "\nADD" . ($val[1] != "" ? " CONSTRAINT " . idf_escape($val[1]) : "") . " $val[0] " . ($val[0] == "PRIMARY" ? "KEY " : "") . "(" . implode(", ", $val[2]) . ")"
				);
				array_unshift($queries, "ALTER TABLE " . table($table) . $create);
			} elseif ($val[2] == "DROP") {
				$drop[] = idf_escape($val[1]);
			} else {
				$queries[] = "CREATE INDEX " . idf_escape($val[1] != "" ? $val[1] : uniqid($table . "_")) . " ON " . table($table) . " (" . implode(", ", $val[2]) . ")";
			}
		}
		if ($drop) {
			array_unshift($queries, "DROP INDEX " . implode(", ", $drop));
		}
		foreach ($queries as $query) {
			if (!queries($query)) {
				return false;
			}
		}
		return true;
	}

	function foreign_keys($table) {
		$return = array();
		$query = "SELECT c_list.CONSTRAINT_NAME as NAME,
c_src.COLUMN_NAME as SRC_COLUMN,
c_dest.OWNER as DEST_DB,
c_dest.TABLE_NAME as DEST_TABLE,
c_dest.COLUMN_NAME as DEST_COLUMN,
c_list.DELETE_RULE as ON_DELETE
FROM ALL_CONSTRAINTS c_list, ALL_CONS_COLUMNS c_src, ALL_CONS_COLUMNS c_dest
WHERE c_list.CONSTRAINT_NAME = c_src.CONSTRAINT_NAME
AND c_list.R_CONSTRAINT_NAME = c_dest.CONSTRAINT_NAME
AND c_list.CONSTRAINT_TYPE = 'R'
AND c_src.TABLE_NAME = " . q($table);
		foreach (get_rows($query) as $row) {
			$return[$row['NAME']] = array(
				"db" => $row['DEST_DB'],
				"table" => $row['DEST_TABLE'],
				"source" => array($row['SRC_COLUMN']),
				"target" => array($row['DEST_COLUMN']),
				"on_delete" => $row['ON_DELETE'],
				"on_update" => null,
			);
		}
		return $return;
	}

	function truncate_tables($tables) {
		return apply_queries("TRUNCATE TABLE", $tables);
	}

	function drop_views($views) {
		return apply_queries("DROP VIEW", $views);
	}

	function drop_tables($tables) {
		return apply_queries("DROP TABLE", $tables);
	}

	function last_id($result) {
		return 0; //!
	}

	function schemas() {
		$return = get_vals("SELECT DISTINCT owner FROM dba_segments WHERE owner IN (SELECT username FROM dba_users WHERE default_tablespace NOT IN ('SYSTEM','SYSAUX')) ORDER BY 1");
		return ($return ?: get_vals("SELECT DISTINCT owner FROM all_tables WHERE tablespace_name = " . q(DB) . " ORDER BY 1"));
	}

	function get_schema() {
		return get_val("SELECT sys_context('USERENV', 'SESSION_USER') FROM dual");
	}

	function set_schema($scheme, $connection2 = null) {
		if (!$connection2) {
			$connection2 = connection();
		}
		return $connection2->query("ALTER SESSION SET CURRENT_SCHEMA = " . idf_escape($scheme));
	}

	function show_variables() {
		return get_rows('SELECT name, display_value FROM v$parameter');
	}

	function show_status() {
		$return = array();
		$rows = get_rows('SELECT * FROM v$instance');
		foreach (reset($rows) as $key => $val) {
			$return[] = array($key, $val);
		}
		return $return;
	}

	function process_list() {
		return get_rows('SELECT
	sess.process AS "process",
	sess.username AS "user",
	sess.schemaname AS "schema",
	sess.status AS "status",
	sess.wait_class AS "wait_class",
	sess.seconds_in_wait AS "seconds_in_wait",
	sql.sql_text AS "sql_text",
	sess.machine AS "machine",
	sess.port AS "port"
FROM v$session sess LEFT OUTER JOIN v$sql sql
ON sql.sql_id = sess.sql_id
WHERE sess.type = \'USER\'
ORDER BY PROCESS
');
	}

	function convert_field($field) {
	}

	function unconvert_field($field, $return) {
		return $return;
	}

	function support($feature) {
		return preg_match('~^(columns|database|drop_col|indexes|descidx|processlist|scheme|sql|status|table|variables|view)$~', $feature); //!
	}
}

?>
<?php
/**
* @author Jakub Cernohuby
* @author Vladimir Stastka
* @author Jakub Vrana
*/

add_driver("mssql", "MS SQL");

if (isset($_GET["mssql"])) {
	define('Adminer\DRIVER', "mssql");

	if (extension_loaded("sqlsrv") && $_GET["ext"] != "pdo") {
		class Db extends SqlDb {
			public $extension = "sqlsrv";
			private $link, $result;

			private function get_error() {
				$this->error = "";
				foreach (sqlsrv_errors() as $error) {
					$this->errno = $error["code"];
					$this->error .= "$error[message]\n";
				}
				$this->error = rtrim($this->error);
			}

			function attach($server, $username, $password) {
				$connection_info = array("UID" => $username, "PWD" => $password, "CharacterSet" => "UTF-8");
				$ssl = adminer()->connectSsl();
				if (isset($ssl["Encrypt"])) {
					$connection_info["Encrypt"] = $ssl["Encrypt"];
				}
				if (isset($ssl["TrustServerCertificate"])) {
					$connection_info["TrustServerCertificate"] = $ssl["TrustServerCertificate"];
				}
				$db = adminer()->database();
				if ($db != "") {
					$connection_info["Database"] = $db;
				}
				list($host, $port) = host_port($server);
				$this->link = @sqlsrv_connect($host . ($port ? ",$port" : ""), $connection_info);
				if ($this->link) {
					$info = sqlsrv_server_info($this->link);
					$this->server_info = $info['SQLServerVersion'];
				} else {
					$this->get_error();
				}
				return ($this->link ? '' : $this->error);
			}

			function quote($string) {
				$unicode = strlen($string) != strlen(utf8_decode($string));
				return ($unicode ? "N" : "") . "'" . str_replace("'", "''", $string) . "'";
			}

			function select_db($database) {
				return $this->query(use_sql($database));
			}

			function query($query, $unbuffered = false) {
				$result = sqlsrv_query($this->link, $query); //! , array(), ($unbuffered ? array() : array("Scrollable" => "keyset"))
				$this->error = "";
				if (!$result) {
					$this->get_error();
					return false;
				}
				return $this->store_result($result);
			}

			function multi_query($query) {
				$this->result = sqlsrv_query($this->link, $query);
				$this->error = "";
				if (!$this->result) {
					$this->get_error();
					return false;
				}
				return true;
			}

			function store_result($result = null) {
				if (!$result) {
					$result = $this->result;
				}
				if (!$result) {
					return false;
				}
				if (sqlsrv_field_metadata($result)) {
					return new Result($result);
				}
				$this->affected_rows = sqlsrv_rows_affected($result);
				return true;
			}

			function next_result() {
				return $this->result ? !!sqlsrv_next_result($this->result) : false;
			}
		}

		class Result {
			public $num_rows;
			private $result, $offset = 0, $fields;

			function __construct($result) {
				$this->result = $result;
				// $this->num_rows = sqlsrv_num_rows($result); // available only in scrollable results
			}

			private function convert($row) {
				foreach ((array) $row as $key => $val) {
					if (is_a($val, 'DateTime')) {
						$row[$key] = $val->format("Y-m-d H:i:s");
					}
					//! stream
				}
				return $row;
			}

			function fetch_assoc() {
				return $this->convert(sqlsrv_fetch_array($this->result, SQLSRV_FETCH_ASSOC));
			}

			function fetch_row() {
				return $this->convert(sqlsrv_fetch_array($this->result, SQLSRV_FETCH_NUMERIC));
			}

			function fetch_field() {
				if (!$this->fields) {
					$this->fields = sqlsrv_field_metadata($this->result);
				}
				$field = $this->fields[$this->offset++];
				$return = new \stdClass;
				$return->name = $field["Name"];
				$return->type = ($field["Type"] == 1 ? 254 : 15);
				$return->charsetnr = 0;
				return $return;
			}

			function seek($offset) {
				for ($i=0; $i < $offset; $i++) {
					sqlsrv_fetch($this->result); // SQLSRV_SCROLL_ABSOLUTE added in sqlsrv 1.1
				}
			}

			function __destruct() {
				sqlsrv_free_stmt($this->result);
			}
		}

		function last_id($result) {
			return get_val("SELECT SCOPE_IDENTITY()"); // @@IDENTITY can return trigger INSERT
		}

		function explain($connection, $query) {
			$connection->query("SET SHOWPLAN_ALL ON");
			$return = $connection->query($query);
			$connection->query("SET SHOWPLAN_ALL OFF"); // connection is used also for indexes
			return $return;
		}

	} else {
		abstract class MssqlDb extends PdoDb {
			function select_db($database) {
				// database selection is separated from the connection so dbname in DSN can't be used
				return $this->query(use_sql($database));
			}

			function lastInsertId() {
				return $this->pdo->lastInsertId();
			}
		}

		function last_id($result) {
			return connection()->lastInsertId();
		}

		function explain($connection, $query) {
		}

		if (extension_loaded("pdo_sqlsrv")) {
			class Db extends MssqlDb {
				public $extension = "PDO_SQLSRV";

				function attach($server, $username, $password) {
					list($host, $port) = host_port($server);
					return $this->dsn("sqlsrv:Server=$host" . ($port ? ",$port" : ""), $username, $password);
				}
			}

		} elseif (extension_loaded("pdo_dblib")) {
			class Db extends MssqlDb {
				public $extension = "PDO_DBLIB";

				function attach($server, $username, $password) {
					list($host, $port) = host_port($server);
					return $this->dsn("dblib:charset=utf8;host=$host" . ($port ? (is_numeric($port) ? ";port=" : ";unix_socket=") . $port : ""), $username, $password);
				}
			}
		}
	}


	class Driver extends SqlDriver {
		static $extensions = array("SQLSRV", "PDO_SQLSRV", "PDO_DBLIB");
		static $jush = "mssql";

		public $insertFunctions = array("date|time" => "getdate");
		public $editFunctions = array(
			"int|decimal|real|float|money|datetime" => "+/-",
			"char|text" => "+",
		);

		public $operators = array("=", "<", ">", "<=", ">=", "!=", "LIKE", "LIKE %%", "IN", "IS NULL", "NOT LIKE", "NOT IN", "IS NOT NULL");
		public $functions = array("len", "lower", "round", "upper");
		public $grouping = array("avg", "count", "count distinct", "max", "min", "sum");
		public $generated = array("PERSISTED", "VIRTUAL");
		public $onActions = "NO ACTION|CASCADE|SET NULL|SET DEFAULT";

		static function connect($server, $username, $password) {
			if ($server == "") {
				$server = "localhost:1433";
			}
			return parent::connect($server, $username, $password);
		}

		function __construct(Db $connection) {
			parent::__construct($connection);
			$this->types = array( //! use sys.types
				'Numbers' => array("tinyint" => 3, "smallint" => 5, "int" => 10, "bigint" => 20, "bit" => 1, "decimal" => 0, "real" => 12, "float" => 53, "smallmoney" => 10, "money" => 20),
				'Date and time' => array("date" => 10, "smalldatetime" => 19, "datetime" => 19, "datetime2" => 19, "time" => 8, "datetimeoffset" => 10),
				'Strings' => array("char" => 8000, "varchar" => 8000, "text" => 2147483647, "nchar" => 4000, "nvarchar" => 4000, "ntext" => 1073741823),
				'Binary' => array("binary" => 8000, "varbinary" => 8000, "image" => 2147483647),
			);
		}

		function insertUpdate($table, array $rows, array $primary) {
			$fields = fields($table);
			$update = array();
			$where = array();
			$set = reset($rows);
			$columns = "c" . implode(", c", range(1, count($set)));
			$c = 0;
			$insert = array();
			foreach ($set as $key => $val) {
				$c++;
				$name = idf_unescape($key);
				if (!$fields[$name]["auto_increment"]) {
					$insert[$key] = "c$c";
				}
				if (isset($primary[$name])) {
					$where[] = "$key = c$c";
				} else {
					$update[] = "$key = c$c";
				}
			}
			$values = array();
			foreach ($rows as $set) {
				$values[] = "(" . implode(", ", $set) . ")";
			}
			if ($where) {
				$identity = queries("SET IDENTITY_INSERT " . table($table) . " ON");
				$return = queries(
					"MERGE " . table($table) . " USING (VALUES\n\t" . implode(",\n\t", $values) . "\n) AS source ($columns) ON " . implode(" AND ", $where) //! source, c1 - possible conflict
					. ($update ? "\nWHEN MATCHED THEN UPDATE SET " . implode(", ", $update) : "")
					. "\nWHEN NOT MATCHED THEN INSERT (" . implode(", ", array_keys($identity ? $set : $insert)) . ") VALUES (" . ($identity ? $columns : implode(", ", $insert)) . ");" // ; is mandatory
				);
				if ($identity) {
					queries("SET IDENTITY_INSERT " . table($table) . " OFF");
				}
			} else {
				$return = queries("INSERT INTO " . table($table) . " (" . implode(", ", array_keys($set)) . ") VALUES\n" . implode(",\n", $values));
			}
			return $return;
		}

		function begin() {
			return queries("BEGIN TRANSACTION");
		}

		function tableHelp($name, $is_view = false) {
			$links = array(
				"sys" => "catalog-views/sys-",
				"INFORMATION_SCHEMA" => "information-schema-views/",
			);
			$link = $links[get_schema()];
			if ($link) {
				return "relational-databases/system-$link" . preg_replace('~_~', '-', strtolower($name)) . "-transact-sql";
			}
		}
	}



	function idf_escape($idf) {
		return "[" . str_replace("]", "]]", $idf) . "]";
	}

	function table($idf) {
		return ($_GET["ns"] != "" ? idf_escape($_GET["ns"]) . "." : "") . idf_escape($idf);
	}

	function get_databases($flush) {
		return get_vals("SELECT name FROM sys.databases WHERE name NOT IN ('master', 'tempdb', 'model', 'msdb')");
	}

	function limit($query, $where, $limit, $offset = 0, $separator = " ") {
		return ($limit ? " TOP (" . ($limit + $offset) . ")" : "") . " $query$where"; // seek later
	}

	function limit1($table, $query, $where, $separator = "\n") {
		return limit($query, $where, 1, 0, $separator);
	}

	function db_collation($db, $collations) {
		return get_val("SELECT collation_name FROM sys.databases WHERE name = " . q($db));
	}

	function logged_user() {
		return get_val("SELECT SUSER_NAME()");
	}

	function tables_list() {
		return get_key_vals("SELECT name, type_desc FROM sys.all_objects WHERE schema_id = SCHEMA_ID(" . q(get_schema()) . ") AND type IN ('S', 'U', 'V') ORDER BY name");
	}

	function count_tables($databases) {
		$return = array();
		foreach ($databases as $db) {
			connection()->select_db($db);
			$return[$db] = get_val("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES");
		}
		return $return;
	}

	function table_status($name = "") {
		$return = array();
		foreach (
			get_rows("SELECT ao.name AS Name, ao.type_desc AS Engine, (SELECT value FROM fn_listextendedproperty(default, 'SCHEMA', schema_name(schema_id), 'TABLE', ao.name, null, null)) AS Comment
FROM sys.all_objects AS ao
WHERE schema_id = SCHEMA_ID(" . q(get_schema()) . ") AND type IN ('S', 'U', 'V') " . ($name != "" ? "AND name = " . q($name) : "ORDER BY name")) as $row
		) {
			$return[$row["Name"]] = $row;
		}
		return $return;
	}

	function is_view($table_status) {
		return $table_status["Engine"] == "VIEW";
	}

	function fk_support($table_status) {
		return true;
	}

	function fields($table) {
		$comments = get_key_vals("SELECT objname, cast(value as varchar(max)) FROM fn_listextendedproperty('MS_DESCRIPTION', 'schema', " . q(get_schema()) . ", 'table', " . q($table) . ", 'column', NULL)");
		$return = array();
		$table_id = get_val("SELECT object_id FROM sys.all_objects WHERE schema_id = SCHEMA_ID(" . q(get_schema()) . ") AND type IN ('S', 'U', 'V') AND name = " . q($table));
		foreach (
			get_rows("SELECT c.max_length, c.precision, c.scale, c.name, c.is_nullable, c.is_identity, c.collation_name, t.name type, d.definition [default], d.name default_constraint, i.is_primary_key
FROM sys.all_columns c
JOIN sys.types t ON c.user_type_id = t.user_type_id
LEFT JOIN sys.default_constraints d ON c.default_object_id = d.object_id
LEFT JOIN sys.index_columns ic ON c.object_id = ic.object_id AND c.column_id = ic.column_id
LEFT JOIN sys.indexes i ON ic.object_id = i.object_id AND ic.index_id = i.index_id
WHERE c.object_id = " . q($table_id)) as $row
		) {
			$type = $row["type"];
			$length = (preg_match("~char|binary~", $type)
				? intval($row["max_length"]) / ($type[0] == 'n' ? 2 : 1)
				: ($type == "decimal" ? "$row[precision],$row[scale]" : "")
			);
			$return[$row["name"]] = array(
				"field" => $row["name"],
				"full_type" => $type . ($length ? "($length)" : ""),
				"type" => $type,
				"length" => $length,
				"default" => (preg_match("~^\('(.*)'\)$~", $row["default"], $match) ? str_replace("''", "'", $match[1]) : $row["default"]),
				"default_constraint" => $row["default_constraint"],
				"null" => $row["is_nullable"],
				"auto_increment" => $row["is_identity"],
				"collation" => $row["collation_name"],
				"privileges" => array("insert" => 1, "select" => 1, "update" => 1, "where" => 1, "order" => 1),
				"primary" => $row["is_primary_key"],
				"comment" => $comments[$row["name"]],
			);
		}
		foreach (get_rows("SELECT * FROM sys.computed_columns WHERE object_id = " . q($table_id)) as $row) {
			$return[$row["name"]]["generated"] = ($row["is_persisted"] ? "PERSISTED" : "VIRTUAL");
			$return[$row["name"]]["default"] = $row["definition"];
		}
		return $return;
	}

	function indexes($table, $connection2 = null) {
		$return = array();
		// sp_statistics doesn't return information about primary key
		foreach (
			get_rows("SELECT i.name, key_ordinal, is_unique, is_primary_key, c.name AS column_name, is_descending_key
FROM sys.indexes i
INNER JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
INNER JOIN sys.columns c ON ic.object_id = c.object_id AND ic.column_id = c.column_id
WHERE OBJECT_NAME(i.object_id) = " . q($table), $connection2) as $row
		) {
			$name = $row["name"];
			$return[$name]["type"] = ($row["is_primary_key"] ? "PRIMARY" : ($row["is_unique"] ? "UNIQUE" : "INDEX"));
			$return[$name]["lengths"] = array();
			$return[$name]["columns"][$row["key_ordinal"]] = $row["column_name"];
			$return[$name]["descs"][$row["key_ordinal"]] = ($row["is_descending_key"] ? '1' : null);
		}
		return $return;
	}

	function view($name) {
		return array("select" => preg_replace('~^(?:[^[]|\[[^]]*])*\s+AS\s+~isU', '', get_val("SELECT VIEW_DEFINITION FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA = SCHEMA_NAME() AND TABLE_NAME = " . q($name))));
	}

	function collations() {
		$return = array();
		foreach (get_vals("SELECT name FROM fn_helpcollations()") as $collation) {
			$return[preg_replace('~_.*~', '', $collation)][] = $collation;
		}
		return $return;
	}

	function information_schema($db) {
		return get_schema() == "INFORMATION_SCHEMA";
	}

	function error() {
		return nl_br(h(preg_replace('~^(\[[^]]*])+~m', '', connection()->error)));
	}

	function create_database($db, $collation) {
		return queries("CREATE DATABASE " . idf_escape($db) . (preg_match('~^[a-z0-9_]+$~i', $collation) ? " COLLATE $collation" : ""));
	}

	function drop_databases($databases) {
		return queries("DROP DATABASE " . implode(", ", array_map('Adminer\idf_escape', $databases)));
	}

	function rename_database($name, $collation) {
		if (preg_match('~^[a-z0-9_]+$~i', $collation)) {
			queries("ALTER DATABASE " . idf_escape(DB) . " COLLATE $collation");
		}
		queries("ALTER DATABASE " . idf_escape(DB) . " MODIFY NAME = " . idf_escape($name));
		return true; //! false negative "The database name 'test2' has been set."
	}

	function auto_increment() {
		return " IDENTITY" . ($_POST["Auto_increment"] != "" ? "(" . number($_POST["Auto_increment"]) . ",1)" : "") . " PRIMARY KEY";
	}

	function alter_table($table, $name, $fields, $foreign, $comment, $engine, $collation, $auto_increment, $partitioning) {
		$alter = array();
		$comments = array();
		$orig_fields = fields($table);
		foreach ($fields as $field) {
			$column = idf_escape($field[0]);
			$val = $field[1];
			if (!$val) {
				$alter["DROP"][] = " COLUMN $column";
			} else {
				$val[1] = preg_replace("~( COLLATE )'(\\w+)'~", '\1\2', $val[1]);
				$comments[$field[0]] = $val[5];
				unset($val[5]);
				if (preg_match('~ AS ~', $val[3])) {
					unset($val[1], $val[2]);
				}
				if ($field[0] == "") {
					$alter["ADD"][] = "\n  " . implode("", $val) . ($table == "" ? substr($foreign[$val[0]], 16 + strlen($val[0])) : ""); // 16 - strlen("  FOREIGN KEY ()")
				} else {
					$default = $val[3];
					unset($val[3]); // default values are set separately
					unset($val[6]); //! identity can't be removed
					if ($column != $val[0]) {
						queries("EXEC sp_rename " . q(table($table) . ".$column") . ", " . q(idf_unescape($val[0])) . ", 'COLUMN'");
					}
					$alter["ALTER COLUMN " . implode("", $val)][] = "";
					$orig_field = $orig_fields[$field[0]];
					if (default_value($orig_field) != $default) {
						if ($orig_field["default"] !== null) {
							$alter["DROP"][] = " " . idf_escape($orig_field["default_constraint"]);
						}
						if ($default) {
							$alter["ADD"][] = "\n $default FOR $column";
						}
					}
				}
			}
		}
		if ($table == "") {
			return queries("CREATE TABLE " . table($name) . " (" . implode(",", (array) $alter["ADD"]) . "\n)");
		}
		if ($table != $name) {
			queries("EXEC sp_rename " . q(table($table)) . ", " . q($name));
		}
		if ($foreign) {
			$alter[""] = $foreign;
		}
		foreach ($alter as $key => $val) {
			if (!queries("ALTER TABLE " . table($name) . " $key" . implode(",", $val))) {
				return false;
			}
		}
		foreach ($comments as $key => $val) {
			$comment = substr($val, 9); // 9 - strlen(" COMMENT ")
			queries("EXEC sp_dropextendedproperty @name = N'MS_Description', @level0type = N'Schema', @level0name = " . q(get_schema()) . ", @level1type = N'Table', @level1name = " . q($name) . ", @level2type = N'Column', @level2name = " . q($key));
			queries("EXEC sp_addextendedproperty
@name = N'MS_Description',
@value = $comment,
@level0type = N'Schema',
@level0name = " . q(get_schema()) . ",
@level1type = N'Table',
@level1name = " . q($name) . ",
@level2type = N'Column',
@level2name = " . q($key))
			;
		}
		return true;
	}

	function alter_indexes($table, $alter) {
		$index = array();
		$drop = array();
		foreach ($alter as $val) {
			if ($val[2] == "DROP") {
				if ($val[0] == "PRIMARY") { //! sometimes used also for UNIQUE
					$drop[] = idf_escape($val[1]);
				} else {
					$index[] = idf_escape($val[1]) . " ON " . table($table);
				}
			} elseif (
				!queries(($val[0] != "PRIMARY"
					? "CREATE $val[0] " . ($val[0] != "INDEX" ? "INDEX " : "") . idf_escape($val[1] != "" ? $val[1] : uniqid($table . "_")) . " ON " . table($table)
					: "ALTER TABLE " . table($table) . " ADD PRIMARY KEY"
				) . " (" . implode(", ", $val[2]) . ")")
			) {
				return false;
			}
		}
		return (!$index || queries("DROP INDEX " . implode(", ", $index)))
			&& (!$drop || queries("ALTER TABLE " . table($table) . " DROP " . implode(", ", $drop)))
		;
	}

	function found_rows($table_status, $where) {
	}

	function foreign_keys($table) {
		$return = array();
		$on_actions = array("CASCADE", "NO ACTION", "SET NULL", "SET DEFAULT");
		foreach (get_rows("EXEC sp_fkeys @fktable_name = " . q($table) . ", @fktable_owner = " . q(get_schema())) as $row) {
			$foreign_key = &$return[$row["FK_NAME"]];
			$foreign_key["db"] = $row["PKTABLE_QUALIFIER"];
			$foreign_key["ns"] = $row["PKTABLE_OWNER"];
			$foreign_key["table"] = $row["PKTABLE_NAME"];
			$foreign_key["on_update"] = $on_actions[$row["UPDATE_RULE"]];
			$foreign_key["on_delete"] = $on_actions[$row["DELETE_RULE"]];
			$foreign_key["source"][] = $row["FKCOLUMN_NAME"];
			$foreign_key["target"][] = $row["PKCOLUMN_NAME"];
		}
		return $return;
	}

	function truncate_tables($tables) {
		return apply_queries("TRUNCATE TABLE", $tables);
	}

	function drop_views($views) {
		return queries("DROP VIEW " . implode(", ", array_map('Adminer\table', $views)));
	}

	function drop_tables($tables) {
		return queries("DROP TABLE " . implode(", ", array_map('Adminer\table', $tables)));
	}

	function move_tables($tables, $views, $target) {
		return apply_queries("ALTER SCHEMA " . idf_escape($target) . " TRANSFER", array_merge($tables, $views));
	}

	function trigger($name, $table) {
		if ($name == "") {
			return array();
		}
		$rows = get_rows(
			"SELECT s.name [Trigger],
CASE WHEN OBJECTPROPERTY(s.id, 'ExecIsInsertTrigger') = 1 THEN 'INSERT' WHEN OBJECTPROPERTY(s.id, 'ExecIsUpdateTrigger') = 1 THEN 'UPDATE' WHEN OBJECTPROPERTY(s.id, 'ExecIsDeleteTrigger') = 1 THEN 'DELETE' END [Event],
CASE WHEN OBJECTPROPERTY(s.id, 'ExecIsInsteadOfTrigger') = 1 THEN 'INSTEAD OF' ELSE 'AFTER' END [Timing],
c.text
FROM sysobjects s
JOIN syscomments c ON s.id = c.id
WHERE s.xtype = 'TR' AND s.name = " . q($name)
		); // triggers are not schema-scoped
		$return = reset($rows);
		if ($return) {
			$return["Statement"] = preg_replace('~^.+\s+AS\s+~isU', '', $return["text"]); //! identifiers, comments
		}
		return $return;
	}

	function triggers($table) {
		$return = array();
		foreach (
			get_rows("SELECT sys1.name,
CASE WHEN OBJECTPROPERTY(sys1.id, 'ExecIsInsertTrigger') = 1 THEN 'INSERT' WHEN OBJECTPROPERTY(sys1.id, 'ExecIsUpdateTrigger') = 1 THEN 'UPDATE' WHEN OBJECTPROPERTY(sys1.id, 'ExecIsDeleteTrigger') = 1 THEN 'DELETE' END [Event],
CASE WHEN OBJECTPROPERTY(sys1.id, 'ExecIsInsteadOfTrigger') = 1 THEN 'INSTEAD OF' ELSE 'AFTER' END [Timing]
FROM sysobjects sys1
JOIN sysobjects sys2 ON sys1.parent_obj = sys2.id
WHERE sys1.xtype = 'TR' AND sys2.name = " . q($table)) as $row
		) { // triggers are not schema-scoped
			$return[$row["name"]] = array($row["Timing"], $row["Event"]);
		}
		return $return;
	}

	function trigger_options() {
		return array(
			"Timing" => array("AFTER", "INSTEAD OF"),
			"Event" => array("INSERT", "UPDATE", "DELETE"),
			"Type" => array("AS"),
		);
	}

	function schemas() {
		return get_vals("SELECT name FROM sys.schemas");
	}

	function get_schema() {
		if ($_GET["ns"] != "") {
			return $_GET["ns"];
		}
		return get_val("SELECT SCHEMA_NAME()");
	}

	function set_schema($schema) {
		$_GET["ns"] = $schema;
		return true; // ALTER USER is permanent
	}

	function create_sql($table, $auto_increment, $style) {
		if (is_view(table_status1($table))) {
			$view = view($table);
			return "CREATE VIEW " . table($table) . " AS $view[select]";
		}
		$fields = array();
		$primary = false;
		foreach (fields($table) as $name => $field) {
			$val = process_field($field, $field);
			if ($val[6]) {
				$primary = true;
			}
			$fields[] = implode("", $val);
		}
		foreach (indexes($table) as $name => $index) {
			if (!$primary || $index["type"] != "PRIMARY") {
				$columns = array();
				foreach ($index["columns"] as $key => $val) {
					$columns[] = idf_escape($val) . ($index["descs"][$key] ? " DESC" : "");
				}
				$name = idf_escape($name);
				$fields[] = ($index["type"] == "INDEX" ? "INDEX $name" : "CONSTRAINT $name " . ($index["type"] == "UNIQUE" ? "UNIQUE" : "PRIMARY KEY")) . " (" . implode(", ", $columns) . ")";
			}
		}
		foreach (driver()->checkConstraints($table) as $name => $check) {
			$fields[] = "CONSTRAINT " . idf_escape($name) . " CHECK ($check)";
		}
		return "CREATE TABLE " . table($table) . " (\n\t" . implode(",\n\t", $fields) . "\n)";
	}

	function foreign_keys_sql($table) {
		$fields = array();
		foreach (foreign_keys($table) as $foreign) {
			$fields[] = ltrim(format_foreign_key($foreign));
		}
		return ($fields ? "ALTER TABLE " . table($table) . " ADD\n\t" . implode(",\n\t", $fields) . ";\n\n" : "");
	}

	function truncate_sql($table) {
		return "TRUNCATE TABLE " . table($table);
	}

	function use_sql($database, $style = "") {
		return "USE " . idf_escape($database);
	}

	function trigger_sql($table) {
		$return = "";
		foreach (triggers($table) as $name => $trigger) {
			$return .= create_trigger(" ON " . table($table), trigger($name, $table)) . ";";
		}
		return $return;
	}

	function convert_field($field) {
	}

	function unconvert_field($field, $return) {
		return $return;
	}

	function support($feature) {
		return preg_match('~^(check|comment|columns|database|drop_col|dump|indexes|descidx|scheme|sql|table|trigger|view|view_trigger)$~', $feature); //! routine|
	}
}

?>
<?php
// any method change in this file should be transferred to editor/include/adminer.inc.php

/** Default Adminer plugin; it should call methods via adminer()->f() instead of $this->f() to give chance to other plugins */
class Adminer {
	/** @var Adminer|Plugins */ static $instance;
	/** @visibility protected(set) */ public $error = ''; // HTML

	/** Name in title and navigation
	* @return string HTML code
	*/
	function name() {
		return "<a href='https://www.adminer.org/'" . target_blank() . " id='h1'><img src='" . h(preg_replace("~\\?.*~", "", ME) . "?file=logo.png&version=5.4.2-dev") . "' width='24' height='24' alt='' id='logo'>Adminer</a>";
	}

	/** Connection parameters
	* @return array{string, string, string}
	*/
	function credentials() {
		return array(SERVER, $_GET["username"], get_password());
	}

	/** Get SSL connection options
	* @return string[]|void
	*/
	function connectSsl() {
	}

	/** Get key used for permanent login
	* @return string cryptic string which gets combined with password or '' in case of an error
	*/
	function permanentLogin($create = false) {
		return password_file($create);
	}

	/** Return key used to group brute force attacks; behind a reverse proxy, you want to return the last part of X-Forwarded-For */
	function bruteForceKey() {
		return $_SERVER["REMOTE_ADDR"];
	}

	/** Get server name displayed in breadcrumbs
	* @return string HTML code or null
	*/
	function serverName($server) {
		return h($server);
	}

	/** Identifier of selected database */
	function database() {
		// should be used everywhere instead of DB
		return DB;
	}

	/** Get cached list of databases
	* @return list<string>
	*/
	function databases($flush = true) {
		return get_databases($flush);
	}

	/** Print links after list of plugins */
	function pluginsLinks() {
	}

	/** Operators used in select
	* @return list<string> operators
	*/
	function operators() {
		return driver()->operators;
	}

	/** Get list of schemas
	* @return list<string>
	*/
	function schemas() {
		return schemas();
	}

	/** Specify limit for waiting on some slow queries like DB list
	* @return float number of seconds
	*/
	function queryTimeout() {
		return 2;
	}

	/** Called after connecting and selecting a database */
	function afterConnect() {
	}

	/** Headers to send before HTML output */
	function headers() {
	}

	/** Get Content Security Policy headers
	* @param list<string[]> $csp of arrays with directive name in key, allowed sources in value
	* @return list<string[]> same as $csp
	*/
	function csp(array $csp) {
		return $csp;
	}

	/** Print HTML code inside <head>
	* @param bool $dark dark CSS: false to disable, true to force, null to base on user preferences
	* @return bool true to link favicon.ico
	*/
	function head($dark = null) {
		// this is matched by compile.php
		
		
		return true;
	}

	/** Print extra classes in <body class>; must start with a space */
	function bodyClass() {
		echo " adminer";
	}

	/** Get URLs of the CSS files
	* @return string[] key is URL, value is either 'light' (supports only light color scheme), 'dark' or '' (both)
	*/
	function css() {
		$return = array();
		foreach (array("", "-dark") as $mode) {
			$filename = "adminer$mode.css";
			if (file_exists($filename)) {
				$file = file_get_contents($filename);
				$return["$filename?v=" . crc32($file)] = ($mode
					? "dark"
					: (preg_match('~prefers-color-scheme:\s*dark~', $file) ? '' : 'light')
				);
			}
		}
		return $return;
	}

	/** Print login form */
	function loginForm() {
		echo "<table class='layout'>\n";
		// this is matched by compile.php
		echo adminer()->loginFormField('driver', '<tr><th>' . 'System' . '<td>', html_select("auth[driver]", SqlDriver::$drivers, DRIVER, "loginDriver(this);"));
		echo adminer()->loginFormField('server', '<tr><th>' . 'Server' . '<td>', '<input name="auth[server]" value="' . h(SERVER) . '" title="' . 'hostname[:port] or :socket' . '" placeholder="localhost" autocapitalize="off">');
		// this is matched by compile.php
		echo adminer()->loginFormField('username', '<tr><th>' . 'Username' . '<td>', '<input name="auth[username]" id="username" autofocus value="' . h($_GET["username"]) . '" autocomplete="username" autocapitalize="off">' . script("const authDriver = qs('#username').form['auth[driver]']; authDriver && authDriver.onchange();"));
		echo adminer()->loginFormField('password', '<tr><th>' . 'Password' . '<td>', '<input type="password" name="auth[password]" autocomplete="current-password">');
		echo adminer()->loginFormField('db', '<tr><th>' . 'Database' . '<td>', '<input name="auth[db]" value="' . h($_GET["db"]) . '" autocapitalize="off">');
		echo "</table>\n";
		echo "<p><input type='submit' value='" . 'Login' . "'>\n";
		echo checkbox("auth[permanent]", 1, $_COOKIE["adminer_permanent"], 'Permanent login') . "\n";
	}

	/** Get login form field
	* @param string $heading HTML
	* @param string $value HTML
	*/
	function loginFormField($name, $heading, $value) {
		return $heading . $value . "\n";
	}

	/** Authorize the user
	* @return mixed true for success, string for error message, false for unknown error
	*/
	function login($login, $password) {
		// if ($password == "") {
		// 	return sprintf('Adminer does not support accessing a database without a password, <a href="https://www.adminer.org/en/password/"%s>more information</a>.', target_blank());
		// }
		return true;
	}

	/** Table caption used in navigation and headings
	* @param TableStatus $tableStatus
	* @return string HTML code, "" to ignore table
	*/
	function tableName(array $tableStatus) {
		return h($tableStatus["Name"]);
	}

	/** Field caption used in select and edit
	* @param Field|RoutineField $field
	* @param int $order order of column in select
	* @return string HTML code, "" to ignore field
	*/
	function fieldName(array $field, $order = 0) {
		$type = $field["full_type"];
		$comment = $field["comment"];
		return '<span title="' . h($type . ($comment != "" ? ($type ? ": " : "") . $comment : '')) . '">' . h($field["field"]) . '</span>';
	}

	/** Print links after select heading
	* @param TableStatus $tableStatus
	* @param ?string $set new item options, NULL for no new item
	*/
	function selectLinks(array $tableStatus, $set = "") {
		$name = $tableStatus["Name"];
		echo '<p class="links">';
		$links = array("select" => 'Select data');
		if (support("table") || support("indexes")) {
			$links["table"] = 'Show structure';
		}
		$is_view = false;
		if (support("table")) {
			$is_view = is_view($tableStatus);
			if ($is_view) {
				if (support("view")) {
					$links["view"] = 'Alter view';
				}
			} elseif (function_exists('Adminer\alter_table')) {
				$links["create"] = 'Alter table';
			}
		}
		if ($set !== null) {
			$links["edit"] = 'New item';
		}
		foreach ($links as $key => $val) {
			echo " <a href='" . h(ME) . "$key=" . urlencode($name) . ($key == "edit" ? $set : "") . "'" . bold(isset($_GET[$key])) . ">$val</a>";
		}
		echo doc_link(array(JUSH => driver()->tableHelp($name, $is_view)), "?");
		echo "\n";
	}

	/** Get foreign keys for table
	* @return ForeignKey[] same format as foreign_keys()
	*/
	function foreignKeys($table) {
		return foreign_keys($table);
	}

	/** Find backward keys for table
	* @return BackwardKey[]
	*/
	function backwardKeys($table, $tableName) {
		return array();
	}

	/** Print backward keys for row
	* @param BackwardKey[] $backwardKeys
	* @param string[] $row
	*/
	function backwardKeysPrint(array $backwardKeys, array $row) {
	}

	/** Query printed in select before execution
	* @param string $query query to be executed
	* @param float $start start time of the query
	*/
	function selectQuery($query, $start, $failed = false) {
		$return = "</p>\n"; // required for IE9 inline edit
		if (!$failed && ($warnings = driver()->warnings())) {
			$id = "warnings";
			$return = ", <a href='#$id'>" . 'Warnings' . "</a>" . script("qsl('a').onclick = partial(toggle, '$id');", "")
				. "$return<div id='$id' class='hidden'>\n$warnings</div>\n"
			;
		}
		return "<p><code class='jush-" . JUSH . "'>" . h(str_replace("\n", " ", $query)) . "</code> <span class='time'>(" . format_time($start) . ")</span>"
			. (support("sql") ? " <a href='" . h(ME) . "sql=" . urlencode($query) . "'>" . 'Edit' . "</a>" : "")
			. $return
		;
	}

	/** Query printed in SQL command before execution
	* @param string $query query to be executed
	* @return string escaped query to be printed
	*/
	function sqlCommandQuery($query) {
		return shorten_utf8(trim($query), 1000);
	}

	/** Print HTML code just before the Execute button in SQL command */
	function sqlPrintAfter() {
	}

	/** Description of a row in a table
	* @return string SQL expression, empty string for no description
	*/
	function rowDescription($table) {
		return "";
	}

	/** Get descriptions of selected data
	* @param list<string[]> $rows all data to print
	* @param list<ForeignKey>[] $foreignKeys
	* @return list<string[]>
	*/
	function rowDescriptions(array $rows, array $foreignKeys) {
		return $rows;
	}

	/** Get a link to use in select table
	* @param string $val raw value of the field
	* @param array{type: string} $field
	* @return string|void null to create the default link
	*/
	function selectLink($val, array $field) {
	}

	/** Value printed in select table
	* @param ?string $val HTML-escaped value to print
	* @param ?string $link link to foreign key
	* @param array{type: string} $field
	* @param string $original original value before applying editVal() and escaping
	*/
	function selectVal($val, $link, array $field, $original) {
		$return = ($val === null ? "<i>NULL</i>"
			: (preg_match("~char|binary|boolean~", $field["type"]) && !preg_match("~var~", $field["type"]) ? "<code>$val</code>"
			: (preg_match('~json~', $field["type"]) ? "<code class='jush-js'>$val</code>"
			: $val)
		));
		if (is_blob($field) && !is_utf8($val)) {
			$return = "<i>" . lang_format(array('%d byte', '%d bytes'), strlen($original)) . "</i>";
		}
		return ($link ? "<a href='" . h($link) . "'" . (is_url($link) ? target_blank() : "") . ">$return</a>" : $return);
	}

	/** Value conversion used in select and edit
	* @param array{type: string} $field
	*/
	function editVal($val, array $field) {
		return $val;
	}

	/** Get configuration options for AdminerConfig
	* @return string[] key is config description, value is HTML
	*/
	function config() {
		return array();
	}

	/** Print table structure in tabular format
	* @param Field[] $fields
	* @param TableStatus $tableStatus
	*/
	function tableStructurePrint(array $fields, $tableStatus = null) {
		echo "<div class='scrollable'>\n";
		echo "<table class='nowrap odds'>\n";
		echo "<thead><tr><th>" . 'Column' . "<td>" . 'Type' . (support("comment") ? "<td>" . 'Comment' : "") . "</thead>\n";
		$structured_types = driver()->structuredTypes();
		foreach ($fields as $field) {
			echo "<tr><th>" . h($field["field"]);
			$type = h($field["full_type"]);
			$collation = h($field["collation"]);
			echo "<td><span title='$collation'>"
				. (in_array($type, (array) $structured_types['User types'])
					? "<a href='" . h(ME . 'type=' . urlencode($type)) . "'>$type</a>"
					: $type . ($collation && isset($tableStatus["Collation"]) && $collation != $tableStatus["Collation"] ? " $collation" : ""))
				. "</span>"
			;
			echo ($field["null"] ? " <i>NULL</i>" : "");
			echo ($field["auto_increment"] ? " <i>" . 'Auto Increment' . "</i>" : "");
			$default = h($field["default"]);
			echo (isset($field["default"]) ? " <span title='" . 'Default value' . "'>[<b>" . ($field["generated"] ? "<code class='jush-" . JUSH . "'>$default</code>" : $default) . "</b>]</span>" : "");
			echo (support("comment") ? "<td>" . h($field["comment"]) : "");
			echo "\n";
		}
		echo "</table>\n";
		echo "</div>\n";
	}

	/** Print list of indexes on table in tabular format
	* @param Index[] $indexes
	* @param TableStatus $tableStatus
	*/
	function tableIndexesPrint(array $indexes, array $tableStatus) {
		$partial = false;
		foreach ($indexes as $name => $index) {
			$partial |= !!$index["partial"];
		}
		echo "<table>\n";
		$default_algorithm = first(driver()->indexAlgorithms($tableStatus));
		foreach ($indexes as $name => $index) {
			ksort($index["columns"]); // enforce correct columns order
			$print = array();
			foreach ($index["columns"] as $key => $val) {
				$print[] = "<i>" . h($val) . "</i>"
					. ($index["lengths"][$key] ? "(" . $index["lengths"][$key] . ")" : "")
					. ($index["descs"][$key] ? " DESC" : "")
				;
			}

			echo "<tr title='" . h($name) . "'>";
			echo "<th>$index[type]" . ($default_algorithm && $index['algorithm'] != $default_algorithm ? " ($index[algorithm])" : "");
			echo "<td>" . implode(", ", $print);
			if ($partial) {
				echo "<td>" . ($index['partial'] ? "<code class='jush-" . JUSH . "'>WHERE " . h($index['partial']) : "");
			}
			echo "\n";
		}
		echo "</table>\n";
	}

	/** Print columns box in select
	* @param list<string> $select result of selectColumnsProcess()[0]
	* @param string[] $columns selectable columns
	*/
	function selectColumnsPrint(array $select, array $columns) {
		print_fieldset("select", 'Select', $select);
		$i = 0;
		$select[""] = array();
		foreach ($select as $key => $val) {
			$val = idx($_GET["columns"], $key, array());
			$column = select_input(
				" name='columns[$i][col]'",
				$columns,
				$val["col"],
				($key !== "" ? "selectFieldChange" : "selectAddRow")
			);
			echo "<div>" . (driver()->functions || driver()->grouping ? html_select("columns[$i][fun]", array(-1 => "") + array_filter(array('Functions' => driver()->functions, 'Aggregation' => driver()->grouping)), $val["fun"])
				. on_help("event.target.value && event.target.value.replace(/ |\$/, '(') + ')'", 1)
				. script("qsl('select').onchange = function () { helpClose();" . ($key !== "" ? "" : " qsl('select, input', this.parentNode).onchange();") . " };", "")
				. "($column)" : $column) . "</div>\n";
			$i++;
		}
		echo "</div></fieldset>\n";
	}

	/** Print search box in select
	* @param list<string> $where result of selectSearchProcess()
	* @param string[] $columns selectable columns
	* @param Index[] $indexes
	*/
	function selectSearchPrint(array $where, array $columns, array $indexes) {
		print_fieldset("search", 'Search', $where);
		foreach ($indexes as $i => $index) {
			if ($index["type"] == "FULLTEXT") {
				echo "<div>(<i>" . implode("</i>, <i>", array_map('Adminer\h', $index["columns"])) . "</i>) AGAINST";
				echo " <input type='search' name='fulltext[$i]' value='" . h(idx($_GET["fulltext"], $i)) . "'>";
				echo script("qsl('input').oninput = selectFieldChange;", "");
				echo (JUSH == 'sql' ? checkbox("boolean[$i]", 1, isset($_GET["boolean"][$i]), "BOOL") : '');
				echo "</div>\n";
			}
		}
		$change_next = "this.parentNode.firstChild.onchange();";
		foreach (array_merge((array) $_GET["where"], array(array())) as $i => $val) {
			if (!$val || ("$val[col]$val[val]" != "" && in_array($val["op"], adminer()->operators()))) {
				echo "<div>" . select_input(
					" name='where[$i][col]'",
					$columns,
					$val["col"],
					($val ? "selectFieldChange" : "selectAddRow"),
					"(" . 'anywhere' . ")"
				);
				echo html_select("where[$i][op]", adminer()->operators(), $val["op"], $change_next);
				echo "<input type='search' name='where[$i][val]' value='" . h($val["val"]) . "'>";
				echo script("mixin(qsl('input'), {oninput: function () { $change_next }, onkeydown: selectSearchKeydown, onsearch: selectSearchSearch});", "");
				echo "</div>\n";
			}
		}
		echo "</div></fieldset>\n";
	}

	/** Print order box in select
	* @param list<string> $order result of selectOrderProcess()
	* @param string[] $columns selectable columns
	* @param Index[] $indexes
	*/
	function selectOrderPrint(array $order, array $columns, array $indexes) {
		print_fieldset("sort", 'Sort', $order);
		$i = 0;
		foreach ((array) $_GET["order"] as $key => $val) {
			if ($val != "") {
				echo "<div>" . select_input(" name='order[$i]'", $columns, $val, "selectFieldChange");
				echo checkbox("desc[$i]", 1, isset($_GET["desc"][$key]), 'descending') . "</div>\n";
				$i++;
			}
		}
		echo "<div>" . select_input(" name='order[$i]'", $columns, "", "selectAddRow");
		echo checkbox("desc[$i]", 1, false, 'descending') . "</div>\n";
		echo "</div></fieldset>\n";
	}

	/** Print limit box in select */
	function selectLimitPrint($limit) {
		echo "<fieldset><legend>" . 'Limit' . "</legend><div>"; // <div> for easy styling
		echo "<input type='number' name='limit' class='size' value='" . intval($limit) . "'>";
		echo script("qsl('input').oninput = selectFieldChange;", "");
		echo "</div></fieldset>\n";
	}

	/** Print text length box in select
	* @param numeric-string $text_length result of selectLengthProcess()
	*/
	function selectLengthPrint($text_length) {
		if ($text_length !== null) {
			echo "<fieldset><legend>" . 'Text length' . "</legend><div>";
			echo "<input type='number' name='text_length' class='size' value='" . h($text_length) . "'>";
			echo "</div></fieldset>\n";
		}
	}

	/** Print action box in select
	* @param Index[] $indexes
	*/
	function selectActionPrint(array $indexes) {
		echo "<fieldset><legend>" . 'Action' . "</legend><div>";
		echo "<input type='submit' value='" . 'Select' . "'>";
		echo " <span id='noindex' title='" . 'Full table scan' . "'></span>";
		echo "<script" . nonce() . ">\n";
		echo "const indexColumns = ";
		$columns = array();
		foreach ($indexes as $index) {
			$current_key = reset($index["columns"]);
			if ($index["type"] != "FULLTEXT" && $current_key) {
				$columns[$current_key] = 1;
			}
		}
		$columns[""] = 1;
		foreach ($columns as $key => $val) {
			json_row($key);
		}
		echo ";\n";
		echo "selectFieldChange.call(qs('#form')['select']);\n";
		echo "</script>\n";
		echo "</div></fieldset>\n";
	}

	/** Print command box in select
	* @return bool whether to print default commands
	*/
	function selectCommandPrint() {
		return !information_schema(DB);
	}

	/** Print import box in select
	* @return bool whether to print default import
	*/
	function selectImportPrint() {
		return !information_schema(DB);
	}

	/** Print extra text in the end of a select form
	* @param string[] $emailFields fields holding e-mails
	* @param string[] $columns selectable columns
	*/
	function selectEmailPrint(array $emailFields, array $columns) {
	}

	/** Process columns box in select
	* @param string[] $columns selectable columns
	* @param Index[] $indexes
	* @return list<list<string>> [[select_expressions], [group_expressions]]
	*/
	function selectColumnsProcess(array $columns, array $indexes) {
		$select = array(); // select expressions, empty for *
		$group = array(); // expressions without aggregation - will be used for GROUP BY if an aggregation function is used
		foreach ((array) $_GET["columns"] as $key => $val) {
			if ($val["fun"] == "count" || ($val["col"] != "" && (!$val["fun"] || in_array($val["fun"], driver()->functions) || in_array($val["fun"], driver()->grouping)))) {
				$select[$key] = apply_sql_function($val["fun"], ($val["col"] != "" ? idf_escape($val["col"]) : "*"));
				if (!in_array($val["fun"], driver()->grouping)) {
					$group[] = $select[$key];
				}
			}
		}
		return array($select, $group);
	}

	/** Process search box in select
	* @param Field[] $fields
	* @param Index[] $indexes
	* @return list<string> expressions to join by AND
	*/
	function selectSearchProcess(array $fields, array $indexes) {
		$return = array();
		foreach ($indexes as $i => $index) {
			if ($index["type"] == "FULLTEXT" && idx($_GET["fulltext"], $i) != "") {
				$return[] = "MATCH (" . implode(", ", array_map('Adminer\idf_escape', $index["columns"])) . ") AGAINST (" . q($_GET["fulltext"][$i]) . (isset($_GET["boolean"][$i]) ? " IN BOOLEAN MODE" : "") . ")";
			}
		}
		foreach ((array) $_GET["where"] as $key => $val) {
			$col = $val["col"];
			if ("$col$val[val]" != "" && in_array($val["op"], adminer()->operators())) {
				$conds = array();
				foreach (($col != "" ? array($col => $fields[$col]) : $fields) as $name => $field) {
					$prefix = "";
					$cond = " $val[op]";
					if (preg_match('~IN$~', $val["op"])) {
						$in = process_length($val["val"]);
						$cond .= " " . ($in != "" ? $in : "(NULL)");
					} elseif ($val["op"] == "SQL") {
						$cond = " $val[val]"; // SQL injection
					} elseif (preg_match('~^(I?LIKE) %%$~', $val["op"], $match)) {
						$cond = " $match[1] " . adminer()->processInput($field, "%$val[val]%");
					} elseif ($val["op"] == "FIND_IN_SET") {
						$prefix = "$val[op](" . q($val["val"]) . ", ";
						$cond = ")";
					} elseif (!preg_match('~NULL$~', $val["op"])) {
						$cond .= " " . adminer()->processInput($field, $val["val"]);
					}
					if ($col != "" || ( // find anywhere
						isset($field["privileges"]["where"])
						&& (preg_match('~^[-\d.' . (preg_match('~IN$~', $val["op"]) ? ',' : '') . ']+$~', $val["val"]) || !preg_match('~' . number_type() . '|bit~', $field["type"]))
						&& (!preg_match("~[\x80-\xFF]~", $val["val"]) || preg_match('~char|text|enum|set~', $field["type"]))
						&& (!preg_match('~date|timestamp~', $field["type"]) || preg_match('~^\d+-\d+-\d+~', $val["val"]))
					)) {
						$conds[] = $prefix . driver()->convertSearch(idf_escape($name), $val, $field) . $cond;
					}
				}
				$return[] =
					(count($conds) == 1 ? $conds[0] :
					($conds ? "(" . implode(" OR ", $conds) . ")" :
					"1 = 0"
				));
			}
		}
		return $return;
	}

	/** Process order box in select
	* @param Field[] $fields
	* @param Index[] $indexes
	* @return list<string> expressions to join by comma
	*/
	function selectOrderProcess(array $fields, array $indexes) {
		$return = array();
		foreach ((array) $_GET["order"] as $key => $val) {
			if ($val != "") {
				$return[] = (preg_match('~^((COUNT\(DISTINCT |[A-Z0-9_]+\()(`(?:[^`]|``)+`|"(?:[^"]|"")+")\)|COUNT\(\*\))$~', $val) ? $val : idf_escape($val)) //! MS SQL uses []
					. (isset($_GET["desc"][$key]) ? " DESC" : "")
				;
			}
		}
		return $return;
	}

	/** Process limit box in select */
	function selectLimitProcess() {
		return (isset($_GET["limit"]) ? intval($_GET["limit"]) : 50);
	}

	/** Process length box in select
	* @return numeric-string number of characters to shorten texts, will be escaped, empty string means unlimited
	*/
	function selectLengthProcess() {
		return (isset($_GET["text_length"]) ? "$_GET[text_length]" : "100");
	}

	/** Process extras in select form
	* @param string[] $where AND conditions
	* @param list<ForeignKey>[] $foreignKeys
	* @return bool true if processed, false to process other parts of form
	*/
	function selectEmailProcess(array $where, array $foreignKeys) {
		return false;
	}

	/** Build SQL query used in select
	* @param list<string> $select result of selectColumnsProcess()[0]
	* @param list<string> $where result of selectSearchProcess()
	* @param list<string> $group result of selectColumnsProcess()[1]
	* @param list<string> $order result of selectOrderProcess()
	* @param int $limit result of selectLimitProcess()
	* @param int $page index of page starting at zero
	* @return string empty string to use default query
	*/
	function selectQueryBuild(array $select, array $where, array $group, array $order, $limit, $page) {
		return "";
	}

	/** Query printed after execution in the message
	* @param string $query executed query
	* @param string $time elapsed time
	*/
	function messageQuery($query, $time, $failed = false) {
		restart_session();
		$history = &get_session("queries");
		if (!idx($history, $_GET["db"])) {
			$history[$_GET["db"]] = array();
		}
		if (strlen($query) > 1e6) {
			$query = preg_replace('~[\x80-\xFF]+$~', '', substr($query, 0, 1e6)) . "\n…"; // [\x80-\xFF] - valid UTF-8, \n - can end by one-line comment
		}
		$history[$_GET["db"]][] = array($query, time(), $time); // not DB - $_GET["db"] is changed in database.inc.php //! respect $_GET["ns"]
		$sql_id = "sql-" . count($history[$_GET["db"]]);
		$return = "<a href='#$sql_id' class='toggle'>" . 'SQL command' . "</a> <a href='' class='jsonly copy'>🗐</a>\n";
		if (!$failed && ($warnings = driver()->warnings())) {
			$id = "warnings-" . count($history[$_GET["db"]]);
			$return = "<a href='#$id' class='toggle'>" . 'Warnings' . "</a>, $return<div id='$id' class='hidden'>\n$warnings</div>\n";
		}
		return " <span class='time'>" . @date("H:i:s") . "</span>" // @ - time zone may be not set
			. " $return<div id='$sql_id' class='hidden'><pre><code class='jush-" . JUSH . "'>" . shorten_utf8($query, 1e4) . "</code></pre>"
			. ($time ? " <span class='time'>($time)</span>" : '')
			. (support("sql") ? '<p><a href="' . h(str_replace("db=" . urlencode(DB), "db=" . urlencode($_GET["db"]), ME) . 'sql=&history=' . (count($history[$_GET["db"]]) - 1)) . '">' . 'Edit' . '</a>' : '')
			. '</div>'
		;
	}

	/** Print before edit form
	* @param Field[] $fields
	* @param mixed $row
	*/
	function editRowPrint($table, array $fields, $row, $update) {
	}

	/** Functions displayed in edit form
	* @param Field|array{null:bool} $field
	* @return string[]
	*/
	function editFunctions(array $field) {
		$return = ($field["null"] ? "NULL/" : "");
		$update = isset($_GET["select"]) || where($_GET);
		foreach (array(driver()->insertFunctions, driver()->editFunctions) as $key => $functions) {
			if (!$key || (!isset($_GET["call"]) && $update)) { // relative functions
				foreach ($functions as $pattern => $val) {
					if (!$pattern || preg_match("~$pattern~", $field["type"])) {
						$return .= "/$val";
					}
				}
			}
			if ($key && $functions && !preg_match('~set|bool~', $field["type"]) && !is_blob($field)) {
				$return .= "/SQL";
			}
		}
		if ($field["auto_increment"] && !$update) {
			$return = 'Auto Increment';
		}
		return explode("/", $return);
	}

	/** Get options to display edit field
	* @param ?string $table null in call.inc.php
	* @param Field $field
	* @param string $attrs attributes to use inside the tag
	* @param string|string[]|false|null $value false means original value
	* @return string custom input field or empty string for default
	*/
	function editInput($table, array $field, $attrs, $value) {
		if ($field["type"] == "enum") {
			return (isset($_GET["select"]) ? "<label><input type='radio'$attrs value='orig' checked><i>" . 'original' . "</i></label> " : "")
				. enum_input("radio", $attrs, $field, $value, "NULL")
			;
		}
		return "";
	}

	/** Get hint for edit field
	* @param ?string $table null in call.inc.php
	* @param Field $field
	*/
	function editHint($table, array $field, $value) {
		return "";
	}

	/** Process sent input
	* @param Field $field
	* @return string expression to use in a query
	*/
	function processInput(array $field, $value, $function = "") {
		if ($function == "SQL") {
			return $value; // SQL injection
		}
		$name = $field["field"];
		$return = q($value);
		if (preg_match('~^(now|getdate|uuid)$~', $function)) {
			$return = "$function()";
		} elseif (preg_match('~^current_(date|timestamp)$~', $function)) {
			$return = $function;
		} elseif (preg_match('~^([+-]|\|\|)$~', $function)) {
			$return = idf_escape($name) . " $function $return";
		} elseif (preg_match('~^[+-] interval$~', $function)) {
			$return = idf_escape($name) . " $function " . (preg_match("~^(\\d+|'[0-9.: -]') [A-Z_]+\$~i", $value) && JUSH != "pgsql" ? $value : $return);
		} elseif (preg_match('~^(addtime|subtime|concat)$~', $function)) {
			$return = "$function(" . idf_escape($name) . ", $return)";
		} elseif (preg_match('~^(md5|sha1|password|encrypt)$~', $function)) {
			$return = "$function($return)";
		}
		return unconvert_field($field, $return);
	}

	/** Return export output options
	* @return string[]
	*/
	function dumpOutput() {
		$return = array('text' => 'open', 'file' => 'save');
		if (function_exists('gzencode')) {
			$return['gz'] = 'gzip';
		}
		return $return;
	}

	/** Return export format options
	* @return string[] empty to disable export
	*/
	function dumpFormat() {
		return (support("dump") ? array('sql' => 'SQL') : array()) + array('csv' => 'CSV,', 'csv;' => 'CSV;', 'tsv' => 'TSV');
	}

	/** Export database structure
	* @return void prints data
	*/
	function dumpDatabase($db) {
	}

	/** Export table structure
	* @param int $is_view 0 table, 1 view, 2 temporary view table
	* @return void prints data
	*/
	function dumpTable($table, $style, $is_view = 0) {
		if ($_POST["format"] != "sql") {
			echo "\xef\xbb\xbf"; // UTF-8 byte order mark
			if ($style) {
				dump_csv(array_keys(fields($table)));
			}
		} else {
			if ($is_view == 2) {
				$fields = array();
				foreach (fields($table) as $name => $field) {
					$fields[] = idf_escape($name) . " $field[full_type]";
				}
				$create = "CREATE TABLE " . table($table) . " (" . implode(", ", $fields) . ")";
			} else {
				$create = create_sql($table, $_POST["auto_increment"], $style);
			}
			set_utf8mb4($create);
			if ($style && $create) {
				if ($style == "DROP+CREATE" || $is_view == 1) {
					echo "DROP " . ($is_view == 2 ? "VIEW" : "TABLE") . " IF EXISTS " . table($table) . ";\n";
				}
				if ($is_view == 1) {
					$create = remove_definer($create);
				}
				echo "$create;\n\n";
			}
		}
	}

	/** Export table data
	* @return void prints data
	*/
	function dumpData($table, $style, $query) {
		if ($style) {
			$max_packet = (JUSH == "sqlite" ? 0 : 1048576); // default, minimum is 1024
			$fields = array();
			$identity_insert = false;
			if ($_POST["format"] == "sql") {
				if ($style == "TRUNCATE+INSERT") {
					echo truncate_sql($table) . ";\n";
				}
				$fields = fields($table);
				if (JUSH == "mssql") {
					foreach ($fields as $field) {
						if ($field["auto_increment"]) {
							echo "SET IDENTITY_INSERT " . table($table) . " ON;\n";
							$identity_insert = true;
							break;
						}
					}
				}
			}
			$result = connection()->query($query, 1); // 1 - MYSQLI_USE_RESULT
			if ($result) {
				$insert = "";
				$buffer = "";
				$keys = array();
				$generated = array();
				$suffix = "";
				$fetch_function = ($table != '' ? 'fetch_assoc' : 'fetch_row');
				$count = 0;
				while ($row = $result->$fetch_function()) {
					if (!$keys) {
						$values = array();
						foreach ($row as $val) {
							$field = $result->fetch_field();
							if (idx($fields[$field->name], 'generated')) {
								$generated[$field->name] = true;
								continue;
							}
							$keys[] = $field->name;
							$key = idf_escape($field->name);
							$values[] = "$key = VALUES($key)";
						}
						$suffix = ($style == "INSERT+UPDATE" ? "\nON DUPLICATE KEY UPDATE " . implode(", ", $values) : "") . ";\n";
					}
					if ($_POST["format"] != "sql") {
						if ($style == "table") {
							dump_csv($keys);
							$style = "INSERT";
						}
						dump_csv($row);
					} else {
						if (!$insert) {
							$insert = "INSERT INTO " . table($table) . " (" . implode(", ", array_map('Adminer\idf_escape', $keys)) . ") VALUES";
						}
						foreach ($row as $key => $val) {
							if ($generated[$key]) {
								unset($row[$key]);
								continue;
							}
							$field = $fields[$key];
							$row[$key] = ($val !== null
								? unconvert_field($field, preg_match(number_type(), $field["type"]) && !preg_match('~\[~', $field["full_type"]) && is_numeric($val) ? $val : q(($val === false ? 0 : $val)))
								: "NULL"
							);
						}
						$s = ($max_packet ? "\n" : " ") . "(" . implode(",\t", $row) . ")";
						if (!$buffer) {
							$buffer = $insert . $s;
						} elseif (JUSH == 'mssql'
							? $count % 1000 != 0 // https://learn.microsoft.com/en-us/sql/t-sql/queries/table-value-constructor-transact-sql#limitations-and-restrictions
							: strlen($buffer) + 4 + strlen($s) + strlen($suffix) < $max_packet // 4 - length specification
						) {
							$buffer .= ",$s";
						} else {
							echo $buffer . $suffix;
							$buffer = $insert . $s;
						}
					}
					$count++;
				}
				if ($buffer) {
					echo $buffer . $suffix;
				}
			} elseif ($_POST["format"] == "sql") {
				echo "-- " . str_replace("\n", " ", connection()->error) . "\n";
			}
			if ($identity_insert) {
				echo "SET IDENTITY_INSERT " . table($table) . " OFF;\n";
			}
		}
	}

	/** Set export filename
	* @return string filename without extension
	*/
	function dumpFilename($identifier) {
		return friendly_url($identifier != "" ? $identifier : (SERVER ?: "localhost"));
	}

	/** Send headers for export
	* @return string extension
	*/
	function dumpHeaders($identifier, $multi_table = false) {
		$output = $_POST["output"];
		$ext = (preg_match('~sql~', $_POST["format"]) ? "sql" : ($multi_table ? "tar" : "csv")); // multiple CSV packed to TAR
		header("Content-Type: " .
			($output == "gz" ? "application/x-gzip" :
			($ext == "tar" ? "application/x-tar" :
			($ext == "sql" || $output != "file" ? "text/plain" : "text/csv") . "; charset=utf-8"
		)));
		if ($output == "gz") {
			ob_start(function ($string) {
				// ob_start() callback receives an optional parameter $phase but gzencode() accepts optional parameter $level
				return gzencode($string);
			}, 1e6);
		}
		return $ext;
	}

	/** Print text after export
	* @return void prints data
	*/
	function dumpFooter() {
		if ($_POST["format"] == "sql") {
			echo "-- " . gmdate("Y-m-d H:i:s e") . "\n";
		}
	}

	/** Set the path of the file for webserver load
	* @return string path of the sql dump file
	*/
	function importServerPath() {
		return "adminer.sql";
	}

	/** Print homepage
	* @return bool whether to print default homepage
	*/
	function homepage() {
		echo '<p class="links">' . ($_GET["ns"] == "" && support("database") ? '<a href="' . h(ME) . 'database=">' . 'Alter database' . "</a>\n" : "");
		echo (support("scheme") ? "<a href='" . h(ME) . "scheme='>" . ($_GET["ns"] != "" ? 'Alter schema' : 'Create schema') . "</a>\n" : "");
		echo ($_GET["ns"] !== "" ? '<a href="' . h(ME) . 'schema=">' . 'Database schema' . "</a>\n" : "");
		echo (support("privileges") ? "<a href='" . h(ME) . "privileges='>" . 'Privileges' . "</a>\n" : "");
		if ($_GET["ns"] !== "") {
			echo (support("routine") ? "<a href='#routines'>" . 'Routines' . "</a>\n" : "");
			echo (support("sequence") ? "<a href='#sequences'>" . 'Sequences' . "</a>\n" : "");
			echo (support("type") ? "<a href='#user-types'>" . 'User types' . "</a>\n" : "");
			echo (support("event") ? "<a href='#events'>" . 'Events' . "</a>\n" : "");
		}
		return true;
	}

	/** Print navigation after Adminer title
	* @param string $missing can be "auth" if there is no database connection, "db" if there is no database selected, "ns" with invalid schema
	*/
	function navigation($missing) {
		echo "<h1>" . adminer()->name() . " <span class='version'>" . VERSION;
		$new_version = $_COOKIE["adminer_version"];
		echo " <a href='https://www.adminer.org/#download'" . target_blank() . " id='version'>" . (version_compare(VERSION, $new_version) < 0 ? h($new_version) : "") . "</a>";
		echo "</span></h1>\n";
		// this is matched by compile.php
		
		if ($missing == "auth") {
			$output = "";
			foreach ((array) $_SESSION["pwds"] as $vendor => $servers) {
				foreach ($servers as $server => $usernames) {
					$name = h(get_setting("vendor-$vendor-$server") ?: get_driver($vendor));
					foreach ($usernames as $username => $password) {
						if ($password !== null) {
							$dbs = $_SESSION["db"][$vendor][$server][$username];
							foreach (($dbs ? array_keys($dbs) : array("")) as $db) {
								$output .= "<li><a href='" . h(auth_url($vendor, $server, $username, $db)) . "'>($name) " . h("$username@" . ($server != "" ? adminer()->serverName($server) : "") . ($db != "" ? " - $db" : "")) . "</a>\n";
							}
						}
					}
				}
			}
			if ($output) {
				echo "<ul id='logins'>\n$output</ul>\n" . script("mixin(qs('#logins'), {onmouseover: menuOver, onmouseout: menuOut});");
			}
		} else {
			$tables = array();
			if ($_GET["ns"] !== "" && !$missing && DB != "") {
				connection()->select_db(DB);
				$tables = table_status('', true);
			}
			adminer()->syntaxHighlighting($tables);
			adminer()->databasesPrint($missing);
			$actions = array();
			if (DB == "" || !$missing) {
				if (support("sql")) {
					$actions[] = "<a href='" . h(ME) . "sql='" . bold(isset($_GET["sql"]) && !isset($_GET["import"])) . ">" . 'SQL command' . "</a>";
					$actions[] = "<a href='" . h(ME) . "import='" . bold(isset($_GET["import"])) . ">" . 'Import' . "</a>";
				}
				$actions[] = "<a href='" . h(ME) . "dump=" . urlencode(isset($_GET["table"]) ? $_GET["table"] : $_GET["select"]) . "' id='dump'" . bold(isset($_GET["dump"])) . ">" . 'Export' . "</a>";
			}
			$in_db = $_GET["ns"] !== "" && !$missing && DB != "";
			if ($in_db && function_exists('Adminer\alter_table')) {
				$actions[] = '<a href="' . h(ME) . 'create="' . bold($_GET["create"] === "") . ">" . 'Create table' . "</a>";
			}
			echo ($actions ? "<p class='links'>\n" . implode("\n", $actions) . "\n" : "");
			if ($in_db) {
				if ($tables) {
					adminer()->tablesPrint($tables);
				} else {
					echo "<p class='message'>" . 'No tables.' . "</p>\n";
				}
			}
		}
	}

	/** Set up syntax highlight for code and <textarea>
	* @param TableStatus[] $tables
	*/
	function syntaxHighlighting(array $tables) {
		// this is matched by compile.php
		echo script_src(preg_replace("~\\?.*~", "", ME) . "?file=jush.js&version=5.4.2-dev", true);
		if (support("sql")) {
			echo "<script" . nonce() . ">\n";
			if ($tables) {
				$links = array();
				foreach ($tables as $table => $type) {
					$links[] = preg_quote($table, '/');
				}
				echo "var jushLinks = { " . JUSH . ":";
				json_row(js_escape(ME) . (support("table") ? "table" : "select") . '=$&', '/\b(' . implode('|', $links) . ')\b/g', false);
				if (support('routine')) {
					foreach (routines() as $row) {
						json_row(js_escape(ME) . 'function=' . urlencode($row["SPECIFIC_NAME"]) . '&name=$&', '/\b' . preg_quote($row["ROUTINE_NAME"], '/') . '(?=["`]?\()/g', false);
					}
				}
				json_row('');
				echo "};\n";
				foreach (array("bac", "bra", "sqlite_quo", "mssql_bra") as $val) {
					echo "jushLinks.$val = jushLinks." . JUSH . ";\n";
				}
				if (isset($_GET["sql"]) || isset($_GET["trigger"]) || isset($_GET["check"])) {
					$tablesColumns = array_fill_keys(array_keys($tables), array());
					foreach (driver()->allFields() as $table => $fields) {
						foreach ($fields as $field) {
							$tablesColumns[$table][] = $field["field"];
						}
					}
					echo "addEventListener('DOMContentLoaded', () => { autocompleter = jush.autocompleteSql('" . idf_escape("") . "', " . json_encode($tablesColumns) . "); });\n";
				}
			}
			echo "</script>\n";
		}
		echo script("syntaxHighlighting('" . preg_replace('~^(\d\.?\d).*~s', '\1', connection()->server_info) . "', '" . connection()->flavor . "');");
	}

	/** Print databases list in menu */
	function databasesPrint($missing) {
		$databases = adminer()->databases();
		if (DB && $databases && !in_array(DB, $databases)) {
			array_unshift($databases, DB);
		}
		echo "<form action=''>\n<p id='dbs'>\n";
		hidden_fields_get();
		$db_events = script("mixin(qsl('select'), {onmousedown: dbMouseDown, onchange: dbChange});");
		echo "<label title='" . 'Database' . "'>" . 'DB' . ": " . ($databases
			? html_select("db", array("" => "") + $databases, DB) . $db_events
			: "<input name='db' value='" . h(DB) . "' autocapitalize='off' size='19'>\n"
		) . "</label>";
		echo "<input type='submit' value='" . 'Use' . "'" . ($databases ? " class='hidden'" : "") . ">\n";
		if (support("scheme")) {
			if ($missing != "db" && DB != "" && connection()->select_db(DB)) {
				echo "<br><label>" . 'Schema' . ": " . html_select("ns", array("" => "") + adminer()->schemas(), $_GET["ns"]) . "$db_events</label>";
				if ($_GET["ns"] != "") {
					set_schema($_GET["ns"]);
				}
			}
		}
		foreach (array("import", "sql", "schema", "dump", "privileges") as $val) {
			if (isset($_GET[$val])) {
				echo input_hidden($val);
				break;
			}
		}
		echo "</p></form>\n";
	}

	/** Print table list in menu
	* @param TableStatus[] $tables
	*/
	function tablesPrint(array $tables) {
		echo "<ul id='tables'>" . script("mixin(qs('#tables'), {onmouseover: menuOver, onmouseout: menuOut});");
		foreach ($tables as $table => $status) {
			$table = "$table"; // do not highlight "0" as active everywhere
			$name = adminer()->tableName($status);
			if ($name != "" && !$status["partition"]) {
				echo '<li><a href="' . h(ME) . 'select=' . urlencode($table) . '"'
					. bold($_GET["select"] == $table || $_GET["edit"] == $table, "select")
					. " title='" . 'Select data' . "'>" . 'select' . "</a> "
				;
				echo (support("table") || support("indexes")
					? '<a href="' . h(ME) . 'table=' . urlencode($table) . '"'
						. bold(in_array($table, array($_GET["table"], $_GET["create"], $_GET["indexes"], $_GET["foreign"], $_GET["trigger"], $_GET["check"], $_GET["view"])), (is_view($status) ? "view" : "structure"))
						. " title='" . 'Show structure' . "'>$name</a>"
					: "<span>$name</span>"
				) . "\n";
			}
		}
		echo "</ul>\n";
	}

	/** Get server variables
	* @return list<string[]> [[$name, $value]]
	*/
	function showVariables() {
		return show_variables();
	}

	/** Get status variables
	* @return list<string[]> [[$name, $value]]
	*/
	function showStatus() {
		return show_status();
	}

	/** Get process list
	* @return list<string[]> [$row]
	*/
	function processList() {
		return process_list();
	}

	/** Kill a process
	* @param numeric-string $id
	* @return Result|bool
	*/
	function killProcess($id) {
		return kill_process($id);
	}
}

?>
<?php
class Plugins {
	/** @var true[] */ private static $append = array('dumpFormat' => true, 'dumpOutput' => true, 'editRowPrint' => true, 'editFunctions' => true, 'config' => true); // these hooks expect the value to be appended to the result

	/** @var list<object> @visibility protected(set) */ public $plugins;
	/** @visibility protected(set) */ public $error = ''; // HTML
	/** @var list<object>[] */ private $hooks = array();

	/** Register plugins
	* @param ?list<object> $plugins object instances or null to autoload plugins from adminer-plugins/
	*/
	function __construct($plugins) {
		if ($plugins === null) {
			$plugins = array();
			$basename = "adminer-plugins";
			if (is_dir($basename)) {
				foreach (glob("$basename/*.php") as $filename) {
					$include = include_once "./$filename";
				}
			}
			$help = " href='https://www.adminer.org/plugins/#use'" . target_blank();
			if (file_exists("$basename.php")) {
				$include = include_once "./$basename.php"; // example: return array(new AdminerLoginOtp($secret))
				if (is_array($include)) {
					foreach ($include as $plugin) {
						$plugins[get_class($plugin)] = $plugin;
					}
				} else {
					$this->error .= sprintf('%s must <a%s>return an array</a>.', "<b>$basename.php</b>", $help) . "<br>";
				}
			}
			foreach (get_declared_classes() as $class) {
				if (!$plugins[$class] && preg_match('~^Adminer\w~i', $class)) {
					// we need to use reflection because PHP 7.1 throws ArgumentCountError for missing arguments but older versions issue a warning
					$reflection = new \ReflectionClass($class);
					$constructor = $reflection->getConstructor();
					if ($constructor && $constructor->getNumberOfRequiredParameters()) {
						$this->error .= sprintf('<a%s>Configure</a> %s in %s.', $help, "<b>$class</b>", "<b>$basename.php</b>") . "<br>";
					} else {
						$plugins[$class] = new $class;
					}
				}
			}
		}
		$this->plugins = $plugins;

		$adminer = new Adminer;
		$plugins[] = $adminer;
		$reflection = new \ReflectionObject($adminer);
		foreach ($reflection->getMethods() as $method) {
			foreach ($plugins as $plugin) {
				$name = $method->getName();
				if (method_exists($plugin, $name)) {
					$this->hooks[$name][] = $plugin;
				}
			}
		}
	}

	/**
	* @param literal-string $name
	* @param mixed[] $params
	* @return mixed
	*/
	function __call($name, array $params) {
		$args = array();
		foreach ($params as $key => $val) {
			// some plugins accept params by reference - we don't need to propagate it outside, just to the other plugins
			$args[] = &$params[$key];
		}
		$return = null;
		foreach ($this->hooks[$name] as $plugin) {
			$value = call_user_func_array(array($plugin, $name), $args);
			if ($value !== null) {
				if (!self::$append[$name]) { // non-null value from non-appending method short-circuits the other plugins
					return $value;
				}
				$return = $value + (array) $return;
			}
		}
		return $return;
	}
}

?>
<?php
// the overridable methods don't use return type declarations so that plugins can be compatible with PHP 5
abstract class Plugin {
	/** @var array<literal-string, string|list<string>>[] */ protected $translations = array(); // key is language code

	/** Get plain text plugin description; empty string means to use the first line of class doc-comment
	* @return string
	*/
	function description() {
		return $this->lang('');
	}

	/** Get URL of plugin screenshot
	* @return string
	*/
	function screenshot() {
		return "";
	}

	/** Translate a string from $this->translations; Adminer\lang() doesn't work for single language versions
	* @param literal-string $idf
	* @param float|string $number
	*/
	protected function lang($idf, $number = null) {
		$args = func_get_args();
		$args[0] = idx($this->translations[LANG], $idf) ?: $idf;
		return call_user_func_array('Adminer\lang_format', $args);
	}
}


Adminer::$instance =
	(function_exists('adminer_object') ? adminer_object() :
	(is_dir("adminer-plugins") || file_exists("adminer-plugins.php") ? new Plugins(null) :
	new Adminer
));

// this is matched by compile.php
?>
<?php
SqlDriver::$drivers = array("server" => "MySQL / MariaDB") + SqlDriver::$drivers;

if (!defined('Adminer\DRIVER')) {
	define('Adminer\DRIVER', "server"); // server - backwards compatibility

	// MySQLi supports everything, MySQL doesn't support multiple result sets, PDO_MySQL doesn't support orgtable
	if (extension_loaded("mysqli") && $_GET["ext"] != "pdo") {
		class Db extends \MySQLi {
			/** @var Db */ static $instance;
			public $extension = "MySQLi", $flavor = '';

			function __construct() {
				parent::init();
			}

			function attach($server, $username, $password) {
				mysqli_report(MYSQLI_REPORT_OFF); // stays between requests, not required since PHP 5.3.4
				list($host, $port) = host_port($server);
				$ssl = adminer()->connectSsl();
				if ($ssl) {
					$this->ssl_set($ssl['key'], $ssl['cert'], $ssl['ca'], '', '');
				}
				$return = @$this->real_connect(
					($server != "" ? $host : ini_get("mysqli.default_host")),
					($server . $username != "" ? $username : ini_get("mysqli.default_user")),
					($server . $username . $password != "" ? $password : ini_get("mysqli.default_pw")),
					null,
					(is_numeric($port) ? intval($port) : ini_get("mysqli.default_port")),
					(is_numeric($port) ? null : $port),
					($ssl ? ($ssl['verify'] !== false ? 2048 : 64) : 0) // 2048 - MYSQLI_CLIENT_SSL, 64 - MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT (not available before PHP 5.6.16)
				);
				$this->options(MYSQLI_OPT_LOCAL_INFILE, 0);
				return ($return ? '' : $this->error);
			}

			function set_charset($charset) {
				if (parent::set_charset($charset)) {
					return true;
				}
				// the client library may not support utf8mb4
				parent::set_charset('utf8');
				return $this->query("SET NAMES $charset");
			}

			function next_result() {
				return self::more_results() && parent::next_result(); // triggers E_STRICT on PHP < 7.4 otherwise
			}

			function quote($string) {
				return "'" . $this->escape_string($string) . "'";
			}
		}

	} elseif (extension_loaded("mysql") && !((ini_bool("sql.safe_mode") || ini_bool("mysql.allow_local_infile")) && extension_loaded("pdo_mysql"))) {
		class Db extends SqlDb {
			/** @var resource */ private $link;

			function attach($server, $username, $password) {
				if (ini_bool("mysql.allow_local_infile")) {
					return sprintf('Disable %s or enable %s or %s extensions.', "'mysql.allow_local_infile'", "MySQLi", "PDO_MySQL");
				}
				$this->link = @mysql_connect(
					($server != "" ? $server : ini_get("mysql.default_host")),
					($server . $username != "" ? $username : ini_get("mysql.default_user")),
					($server . $username . $password != "" ? $password : ini_get("mysql.default_password")),
					true,
					131072 // CLIENT_MULTI_RESULTS for CALL
				);
				if (!$this->link) {
					return mysql_error();
				}
				$this->server_info = mysql_get_server_info($this->link);
				return '';
			}

			/** Set the client character set */
			function set_charset($charset) {
				if (function_exists('mysql_set_charset')) {
					if (mysql_set_charset($charset, $this->link)) {
						return true;
					}
					// the client library may not support utf8mb4
					mysql_set_charset('utf8', $this->link);
				}
				return $this->query("SET NAMES $charset");
			}

			function quote($string) {
				return "'" . mysql_real_escape_string($string, $this->link) . "'";
			}

			function select_db($database) {
				return mysql_select_db($database, $this->link);
			}

			function query($query, $unbuffered = false) {
				$result = @($unbuffered ? mysql_unbuffered_query($query, $this->link) : mysql_query($query, $this->link)); // @ - mute mysql.trace_mode
				$this->error = "";
				if (!$result) {
					$this->errno = mysql_errno($this->link);
					$this->error = mysql_error($this->link);
					return false;
				}
				if ($result === true) {
					$this->affected_rows = mysql_affected_rows($this->link);
					$this->info = mysql_info($this->link);
					return true;
				}
				return new Result($result);
			}
		}

		class Result {
			public $num_rows; // number of rows in the result
			/** @var resource */ private $result;
			private $offset = 0;

			/** @param resource $result */
			function __construct($result) {
				$this->result = $result;
				$this->num_rows = mysql_num_rows($result);
			}

			/** Fetch next row as associative array
			* @return array<?string>|false
			*/
			function fetch_assoc() {
				return mysql_fetch_assoc($this->result);
			}

			/** Fetch next row as numbered array
			* @return list<?string>|false
			*/
			function fetch_row() {
				return mysql_fetch_row($this->result);
			}

			/** Fetch next field
			* @return \stdClass properties: name, type (0 number, 15 varchar, 254 char), charsetnr (63 binary); optionally: table, orgtable, orgname
			*/
			function fetch_field() {
				$return = mysql_fetch_field($this->result, $this->offset++); // offset required under certain conditions
				$return->orgtable = $return->table;
				$return->charsetnr = ($return->blob ? 63 : 0);
				return $return;
			}

			/** Free result set */
			function __destruct() {
				mysql_free_result($this->result);
			}
		}

	} elseif (extension_loaded("pdo_mysql")) {
		class Db extends PdoDb {
			public $extension = "PDO_MySQL";

			function attach($server, $username, $password) {
				$options = array(\PDO::MYSQL_ATTR_LOCAL_INFILE => false);
				$ssl = adminer()->connectSsl();
				if ($ssl) {
					if ($ssl['key']) {
						$options[\PDO::MYSQL_ATTR_SSL_KEY] = $ssl['key'];
					}
					if ($ssl['cert']) {
						$options[\PDO::MYSQL_ATTR_SSL_CERT] = $ssl['cert'];
					}
					if ($ssl['ca']) {
						$options[\PDO::MYSQL_ATTR_SSL_CA] = $ssl['ca'];
					}
					if (isset($ssl['verify'])) {
						$options[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $ssl['verify'];
					}
				}
				list($host, $port) = host_port($server);
				return $this->dsn(
					"mysql:charset=utf8;host=$host" . ($port ? (is_numeric($port) ? ";port=" : ";unix_socket=") . $port : ""),
					$username,
					$password,
					$options
				);
			}

			function set_charset($charset) {
				return $this->query("SET NAMES $charset"); // charset in DSN is ignored before PHP 5.3.6
			}

			function select_db($database) {
				// database selection is separated from the connection so dbname in DSN can't be used
				return $this->query("USE " . idf_escape($database));
			}

			function query($query, $unbuffered = false) {
				$this->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, !$unbuffered);
				return parent::query($query, $unbuffered);
			}
		}

	}



	class Driver extends SqlDriver {
		static $extensions = array("MySQLi", "MySQL", "PDO_MySQL");
		static $jush = "sql"; // JUSH identifier

		public $unsigned = array("unsigned", "zerofill", "unsigned zerofill");
		public $operators = array("=", "<", ">", "<=", ">=", "!=", "LIKE", "LIKE %%", "REGEXP", "IN", "FIND_IN_SET", "IS NULL", "NOT LIKE", "NOT REGEXP", "NOT IN", "IS NOT NULL", "SQL");
		public $functions = array("char_length", "date", "from_unixtime", "lower", "round", "floor", "ceil", "sec_to_time", "time_to_sec", "upper");
		public $grouping = array("avg", "count", "count distinct", "group_concat", "max", "min", "sum");

		static function connect($server, $username, $password) {
			$connection = parent::connect($server, $username, $password);
			if (is_string($connection)) {
				if (function_exists('iconv') && !is_utf8($connection) && strlen($s = iconv("windows-1250", "utf-8", $connection)) > strlen($connection)) { // windows-1250 - most common Windows encoding
					$connection = $s;
				}
				return $connection;
			}
			$connection->set_charset(charset($connection));
			$connection->query("SET sql_quote_show_create = 1, autocommit = 1");
			$connection->flavor = (preg_match('~MariaDB~', $connection->server_info) ? 'maria' : 'mysql');
			add_driver(DRIVER, ($connection->flavor == 'maria' ? "MariaDB" : "MySQL"));
			return $connection;
		}

		function __construct(Db $connection) {
			parent::__construct($connection);
			$this->types = array(
				'Numbers' => array("tinyint" => 3, "smallint" => 5, "mediumint" => 8, "int" => 10, "bigint" => 20, "decimal" => 66, "float" => 12, "double" => 21),
				'Date and time' => array("date" => 10, "datetime" => 19, "timestamp" => 19, "time" => 10, "year" => 4),
				'Strings' => array("char" => 255, "varchar" => 65535, "tinytext" => 255, "text" => 65535, "mediumtext" => 16777215, "longtext" => 4294967295),
				'Lists' => array("enum" => 65535, "set" => 64),
				'Binary' => array("bit" => 20, "binary" => 255, "varbinary" => 65535, "tinyblob" => 255, "blob" => 65535, "mediumblob" => 16777215, "longblob" => 4294967295),
				'Geometry' => array("geometry" => 0, "point" => 0, "linestring" => 0, "polygon" => 0, "multipoint" => 0, "multilinestring" => 0, "multipolygon" => 0, "geometrycollection" => 0),
			);
			$this->insertFunctions = array(
				"char" => "md5/sha1/password/encrypt/uuid",
				"binary" => "md5/sha1",
				"date|time" => "now",
			);
			$this->editFunctions = array(
				number_type() => "+/-",
				"date" => "+ interval/- interval",
				"time" => "addtime/subtime",
				"char|text" => "concat",
			);
			if (min_version('5.7.8', 10.2, $connection)) {
				$this->types['Strings']["json"] = 4294967295;
			}
			if (min_version('', 10.7, $connection)) {
				$this->types['Strings']["uuid"] = 128;
				$this->insertFunctions['uuid'] = 'uuid';
			}
			if (min_version(9, '', $connection)) {
				$this->types['Numbers']["vector"] = 16383;
				$this->insertFunctions['vector'] = 'string_to_vector';
			}
			if (min_version(5.1, '', $connection)) {
				$this->partitionBy = array("HASH", "LINEAR HASH", "KEY", "LINEAR KEY", "RANGE", "LIST");
			}
			if (min_version(5.7, 10.2, $connection)) {
				$this->generated = array("STORED", "VIRTUAL");
			}
		}

		function unconvertFunction(array $field) {
			return (preg_match("~binary~", $field["type"]) ? "<code class='jush-sql'>UNHEX</code>"
				: ($field["type"] == "bit" ? doc_link(array('sql' => 'bit-value-literals.html'), "<code>b''</code>")
				: (preg_match("~geometry|point|linestring|polygon~", $field["type"]) ? "<code class='jush-sql'>GeomFromText</code>"
				: "")));
		}

		function insert($table, array $set) {
			return ($set ? parent::insert($table, $set) : queries("INSERT INTO " . table($table) . " ()\nVALUES ()"));
		}

		function insertUpdate($table, array $rows, array $primary) {
			$columns = array_keys(reset($rows));
			$prefix = "INSERT INTO " . table($table) . " (" . implode(", ", $columns) . ") VALUES\n";
			$values = array();
			foreach ($columns as $key) {
				$values[$key] = "$key = VALUES($key)";
			}
			$suffix = "\nON DUPLICATE KEY UPDATE " . implode(", ", $values);
			$values = array();
			$length = 0;
			foreach ($rows as $set) {
				$value = "(" . implode(", ", $set) . ")";
				if ($values && (strlen($prefix) + $length + strlen($value) + strlen($suffix) > 1e6)) { // 1e6 - default max_allowed_packet
					if (!queries($prefix . implode(",\n", $values) . $suffix)) {
						return false;
					}
					$values = array();
					$length = 0;
				}
				$values[] = $value;
				$length += strlen($value) + 2; // 2 - strlen(",\n")
			}
			return queries($prefix . implode(",\n", $values) . $suffix);
		}

		function slowQuery($query, $timeout) {
			if (min_version('5.7.8', '10.1.2')) {
				if ($this->conn->flavor == 'maria') {
					return "SET STATEMENT max_statement_time=$timeout FOR $query";
				} elseif (preg_match('~^(SELECT\b)(.+)~is', $query, $match)) {
					return "$match[1] /*+ MAX_EXECUTION_TIME(" . ($timeout * 1000) . ") */ $match[2]";
				}
			}
		}

		function convertSearch($idf, array $val, array $field) {
			return (preg_match('~char|text|enum|set~', $field["type"]) && !preg_match("~^utf8~", $field["collation"]) && preg_match('~[\x80-\xFF]~', $val['val'])
				? "CONVERT($idf USING " . charset($this->conn) . ")"
				: $idf
			);
		}

		function warnings() {
			$result = $this->conn->query("SHOW WARNINGS");
			if ($result && $result->num_rows) {
				ob_start();
				print_select_result($result); // print_select_result() usually needs to print a big table progressively
				return ob_get_clean();
			}
		}

		function tableHelp($name, $is_view = false) {
			$maria = ($this->conn->flavor == 'maria');
			if (information_schema(DB)) {
				return strtolower("information-schema-" . ($maria ? "$name-table/" : str_replace("_", "-", $name) . "-table.html"));
			}
			if (DB == "mysql") {
				return ($maria ? "mysql$name-table/" : "system-schema.html"); //! more precise link
			}
		}

		function partitionsInfo($table) {
			$from = "FROM information_schema.PARTITIONS WHERE TABLE_SCHEMA = " . q(DB) . " AND TABLE_NAME = " . q($table);
			$result = $this->conn->query("SELECT PARTITION_METHOD, PARTITION_EXPRESSION, PARTITION_ORDINAL_POSITION $from ORDER BY PARTITION_ORDINAL_POSITION DESC LIMIT 1");
			$return = array();
			list($return["partition_by"], $return["partition"], $return["partitions"]) = $result->fetch_row();
			$partitions = get_key_vals("SELECT PARTITION_NAME, PARTITION_DESCRIPTION $from AND PARTITION_NAME != '' ORDER BY PARTITION_ORDINAL_POSITION");
			$return["partition_names"] = array_keys($partitions);
			$return["partition_values"] = array_values($partitions);
			return $return;
		}

		function hasCStyleEscapes() {
			static $c_style;
			if ($c_style === null) {
				$sql_mode = get_val("SHOW VARIABLES LIKE 'sql_mode'", 1, $this->conn);
				$c_style = (strpos($sql_mode, 'NO_BACKSLASH_ESCAPES') === false);
			}
			return $c_style;
		}

		function engines() {
			$return = array();
			foreach (get_rows("SHOW ENGINES") as $row) {
				if (preg_match("~YES|DEFAULT~", $row["Support"])) {
					$return[] = $row["Engine"];
				}
			}
			return $return;
		}

		function indexAlgorithms(array $tableStatus) {
			return (preg_match('~^(MEMORY|NDB)$~', $tableStatus["Engine"]) ? array("HASH", "BTREE") : array());
		}
	}



	/** Escape database identifier */
	function idf_escape($idf) {
		return "`" . str_replace("`", "``", $idf) . "`";
	}

	/** Get escaped table name */
	function table($idf) {
		return idf_escape($idf);
	}

	/** Get cached list of databases
	* @return list<string>
	*/
	function get_databases($flush) {
		// SHOW DATABASES can take a very long time so it is cached
		$return = get_session("dbs");
		if ($return === null) {
			$query = "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA ORDER BY SCHEMA_NAME"; // SHOW DATABASES can be disabled by skip_show_database
			$return = ($flush ? slow_query($query) : get_vals($query));
			restart_session();
			set_session("dbs", $return);
			stop_session();
		}
		return $return;
	}

	/** Formulate SQL query with limit
	* @param string $query everything after SELECT
	* @param string $where including WHERE
	*/
	function limit($query, $where, $limit, $offset = 0, $separator = " ") {
		return " $query$where" . ($limit ? $separator . "LIMIT $limit" . ($offset ? " OFFSET $offset" : "") : "");
	}

	/** Formulate SQL modification query with limit 1
	* @param string $query everything after UPDATE or DELETE
	*/
	function limit1($table, $query, $where, $separator = "\n") {
		return limit($query, $where, 1, 0, $separator);
	}

	/** Get database collation
	* @param string[][] $collations result of collations()
	*/
	function db_collation($db, array $collations) {
		$return = null;
		$create = get_val("SHOW CREATE DATABASE " . idf_escape($db), 1);
		if (preg_match('~ COLLATE ([^ ]+)~', $create, $match)) {
			$return = $match[1];
		} elseif (preg_match('~ CHARACTER SET ([^ ]+)~', $create, $match)) {
			// default collation
			$return = $collations[$match[1]][-1];
		}
		return $return;
	}

	/** Get logged user */
	function logged_user() {
		return get_val("SELECT USER()");
	}

	/** Get tables list
	* @return string[] [$name => $type]
	*/
	function tables_list() {
		return get_key_vals("SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME");
	}

	/** Count tables in all databases
	* @param list<string> $databases
	* @return int[] [$db => $tables]
	*/
	function count_tables(array $databases) {
		$return = array();
		foreach ($databases as $db) {
			$return[$db] = count(get_vals("SHOW TABLES IN " . idf_escape($db)));
		}
		return $return;
	}

	/** Get table status
	* @param bool $fast return only "Name", "Engine" and "Comment" fields
	* @return array<string, TableStatus>
	*/
	function table_status($name = "", $fast = false) {
		$return = array();
		foreach (
			get_rows(
				$fast
				? "SELECT TABLE_NAME AS Name, ENGINE AS Engine, TABLE_COMMENT AS Comment FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() " . ($name != "" ? "AND TABLE_NAME = " . q($name) : "ORDER BY Name")
				: "SHOW TABLE STATUS" . ($name != "" ? " LIKE " . q(addcslashes($name, "%_\\")) : "")
			) as $row
		) {
			if ($row["Engine"] == "InnoDB") {
				// ignore internal comment, unnecessary since MySQL 5.1.21
				$row["Comment"] = preg_replace('~(?:(.+); )?InnoDB free: .*~', '\1', $row["Comment"]);
			}
			if (!isset($row["Engine"])) {
				$row["Comment"] = "";
			}
			if ($name != "") {
				// MariaDB: Table name is returned as lowercase on macOS, so we fix it here.
				$row["Name"] = $name;
			}
			$return[$row["Name"]] = $row;
		}
		return $return;
	}

	/** Find out whether the identifier is view
	* @param TableStatus $table_status
	*/
	function is_view(array $table_status) {
		return $table_status["Engine"] === null;
	}

	/** Check if table supports foreign keys
	* @param TableStatus $table_status
	*/
	function fk_support(array $table_status) {
		return preg_match('~InnoDB|IBMDB2I' . (min_version(5.6) ? '|NDB' : '') . '~i', $table_status["Engine"]);
	}

	/** Get information about fields
	* @return Field[]
	*/
	function fields($table) {
		$maria = (connection()->flavor == 'maria');
		$return = array();
		foreach (get_rows("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . q($table) . " ORDER BY ORDINAL_POSITION") as $row) {
			$field = $row["COLUMN_NAME"];
			$type = $row["COLUMN_TYPE"];
			$generation = $row["GENERATION_EXPRESSION"];
			$extra = $row["EXTRA"];
			// https://mariadb.com/kb/en/library/show-columns/, https://github.com/vrana/adminer/pull/359#pullrequestreview-276677186
			preg_match('~^(VIRTUAL|PERSISTENT|STORED)~', $extra, $generated);
			preg_match('~^([^( ]+)(?:\((.+)\))?( unsigned)?( zerofill)?$~', $type, $match_type);
			$default = $row["COLUMN_DEFAULT"];
			if ($default != "") {
				$is_text = preg_match('~text|json~', $match_type[1]);
				if (!$maria && $is_text) {
					// default value a'b of text column is stored as _utf8mb4\'a\\\'b\' in MySQL
					$default = preg_replace("~^(_\w+)?('.*')$~", '\2', stripslashes($default));
				}
				if ($maria || $is_text) {
					$default = ($default == "NULL" ? null : preg_replace_callback("~^'(.*)'$~", function ($match) {
						return stripslashes(str_replace("''", "'", $match[1]));
					}, $default));
				}
				if (!$maria && preg_match('~binary~', $match_type[1]) && preg_match('~^0x(\w*)$~', $default, $match)) {
					$default = pack("H*", $match[1]);
				}
			}
			$return[$field] = array(
				"field" => $field,
				"full_type" => $type,
				"type" => $match_type[1],
				"length" => $match_type[2],
				"unsigned" => ltrim($match_type[3] . $match_type[4]),
				"default" => ($generated
					? ($maria ? $generation : stripslashes($generation))
					: $default
				),
				"null" => ($row["IS_NULLABLE"] == "YES"),
				"auto_increment" => ($extra == "auto_increment"),
				"on_update" => (preg_match('~\bon update (\w+)~i', $extra, $match) ? $match[1] : ""), //! available since MySQL 5.1.23
				"collation" => $row["COLLATION_NAME"],
				"privileges" => array_flip(explode(",", "$row[PRIVILEGES],where,order")),
				"comment" => $row["COLUMN_COMMENT"],
				"primary" => ($row["COLUMN_KEY"] == "PRI"),
				"generated" => ($generated[1] == "PERSISTENT" ? "STORED" : $generated[1]),
			);
		}
		return $return;
	}

	/** Get table indexes
	* @return Index[]
	*/
	function indexes($table, $connection2 = null) {
		$return = array();
		foreach (get_rows("SHOW INDEX FROM " . table($table), $connection2) as $row) {
			$name = $row["Key_name"];
			$return[$name]["type"] = ($name == "PRIMARY" ? "PRIMARY" : ($row["Index_type"] == "FULLTEXT" ? "FULLTEXT" : ($row["Non_unique"] ? ($row["Index_type"] == "SPATIAL" ? "SPATIAL" : "INDEX") : "UNIQUE")));
			$return[$name]["columns"][] = $row["Column_name"];
			$return[$name]["lengths"][] = ($row["Index_type"] == "SPATIAL" ? null : $row["Sub_part"]);
			$return[$name]["descs"][] = null;
			$return[$name]["algorithm"] = $row["Index_type"];
		}
		return $return;
	}

	/** Get foreign keys in table
	* @return ForeignKey[]
	*/
	function foreign_keys($table) {
		static $pattern = '(?:`(?:[^`]|``)+`|"(?:[^"]|"")+")';
		$return = array();
		$create_table = get_val("SHOW CREATE TABLE " . table($table), 1);
		if ($create_table) {
			preg_match_all(
				"~CONSTRAINT ($pattern) FOREIGN KEY ?\\(((?:$pattern,? ?)+)\\) REFERENCES ($pattern)(?:\\.($pattern))? \\(((?:$pattern,? ?)+)\\)(?: ON DELETE (" . driver()->onActions . "))?(?: ON UPDATE (" . driver()->onActions . "))?~",
				$create_table,
				$matches,
				PREG_SET_ORDER
			);
			foreach ($matches as $match) {
				preg_match_all("~$pattern~", $match[2], $source);
				preg_match_all("~$pattern~", $match[5], $target);
				$return[idf_unescape($match[1])] = array(
					"db" => idf_unescape($match[4] != "" ? $match[3] : $match[4]),
					"table" => idf_unescape($match[4] != "" ? $match[4] : $match[3]),
					"source" => array_map('Adminer\idf_unescape', $source[0]),
					"target" => array_map('Adminer\idf_unescape', $target[0]),
					"on_delete" => ($match[6] ?: "RESTRICT"),
					"on_update" => ($match[7] ?: "RESTRICT"),
				);
			}
		}
		return $return;
	}

	/** Get view SELECT
	* @return array{select:string}
	*/
	function view($name) {
		return array("select" => preg_replace('~^(?:[^`]|`[^`]*`)*\s+AS\s+~isU', '', get_val("SHOW CREATE VIEW " . table($name), 1)));
	}

	/** Get sorted grouped list of collations
	* @return string[][]
	*/
	function collations() {
		$return = array();
		foreach (get_rows("SHOW COLLATION") as $row) {
			if ($row["Default"]) {
				$return[$row["Charset"]][-1] = $row["Collation"];
			} else {
				$return[$row["Charset"]][] = $row["Collation"];
			}
		}
		ksort($return);
		foreach ($return as $key => $val) {
			sort($return[$key]);
		}
		return $return;
	}

	/** Find out if database is information_schema */
	function information_schema($db) {
		return ($db == "information_schema")
			|| (min_version(5.5) && $db == "performance_schema");
	}

	/** Get escaped error message */
	function error() {
		return h(preg_replace('~^You have an error.*syntax to use~U', "Syntax error", connection()->error));
	}

	/** Create database
	* @return Result
	*/
	function create_database($db, $collation) {
		return queries("CREATE DATABASE " . idf_escape($db) . ($collation ? " COLLATE " . q($collation) : ""));
	}

	/** Drop databases
	* @param list<string> $databases
	*/
	function drop_databases(array $databases) {
		$return = apply_queries("DROP DATABASE", $databases, 'Adminer\idf_escape');
		restart_session();
		set_session("dbs", null);
		return $return;
	}

	/** Rename database from DB
	* @param string $name new name
	*/
	function rename_database($name, $collation) {
		$return = false;
		if (create_database($name, $collation)) {
			$tables = array();
			$views = array();
			foreach (tables_list() as $table => $type) {
				if ($type == 'VIEW') {
					$views[] = $table;
				} else {
					$tables[] = $table;
				}
			}
			$return = (!$tables && !$views) || move_tables($tables, $views, $name);
			drop_databases($return ? array(DB) : array());
		}
		return $return;
	}

	/** Generate modifier for auto increment column */
	function auto_increment() {
		$auto_increment_index = " PRIMARY KEY";
		// don't overwrite primary key by auto_increment
		if ($_GET["create"] != "" && $_POST["auto_increment_col"]) {
			foreach (indexes($_GET["create"]) as $index) {
				if (in_array($_POST["fields"][$_POST["auto_increment_col"]]["orig"], $index["columns"], true)) {
					$auto_increment_index = "";
					break;
				}
				if ($index["type"] == "PRIMARY") {
					$auto_increment_index = " UNIQUE";
				}
			}
		}
		return " AUTO_INCREMENT$auto_increment_index";
	}

	/** Run commands to create or alter table
	* @param string $table "" to create
	* @param string $name new name
	* @param list<array{string, list<string>, string}> $fields of [$orig, $process_field, $after]
	* @param string[] $foreign
	* @param numeric-string|'' $auto_increment
	* @param ?Partitions $partitioning null means remove partitioning
	* @return Result|bool
	*/
	function alter_table($table, $name, array $fields, array $foreign, $comment, $engine, $collation, $auto_increment, $partitioning) {
		$alter = array();
		foreach ($fields as $field) {
			if ($field[1]) {
				$default = $field[1][3];
				if (preg_match('~ GENERATED~', $default)) {
					// swap default and null
					$field[1][3] = (connection()->flavor == 'maria' ? "" : $field[1][2]); // MariaDB doesn't support NULL on virtual columns
					$field[1][2] = $default;
				}
				$alter[] = ($table != "" ? ($field[0] != "" ? "CHANGE " . idf_escape($field[0]) : "ADD") : " ") . " " . implode($field[1]) . ($table != "" ? $field[2] : "");
			} else {
				$alter[] = "DROP " . idf_escape($field[0]);
			}
		}
		$alter = array_merge($alter, $foreign);
		$status = ($comment !== null ? " COMMENT=" . q($comment) : "")
			. ($engine ? " ENGINE=" . q($engine) : "")
			. ($collation ? " COLLATE " . q($collation) : "")
			. ($auto_increment != "" ? " AUTO_INCREMENT=$auto_increment" : "")
		;

		if ($partitioning) {
			$partitions = array();
			if ($partitioning["partition_by"] == 'RANGE' || $partitioning["partition_by"] == 'LIST') {
				foreach ($partitioning["partition_names"] as $key => $val) {
					$value = $partitioning["partition_values"][$key];
					$partitions[] = "\n  PARTITION " . idf_escape($val) . " VALUES " . ($partitioning["partition_by"] == 'RANGE' ? "LESS THAN" : "IN") . ($value != "" ? " ($value)" : " MAXVALUE"); //! SQL injection
				}
			}
			// $partitioning["partition"] can be expression, not only column
			$status .= "\nPARTITION BY $partitioning[partition_by]($partitioning[partition])";
			if ($partitions) {
				$status .= " (" . implode(",", $partitions) . "\n)";
			} elseif ($partitioning["partitions"]) {
				$status .= " PARTITIONS " . (+$partitioning["partitions"]);
			}
		} elseif ($partitioning === null) {
			$status .= "\nREMOVE PARTITIONING";
		}

		if ($table == "") {
			return queries("CREATE TABLE " . table($name) . " (\n" . implode(",\n", $alter) . "\n)$status");
		}
		if ($table != $name) {
			$alter[] = "RENAME TO " . table($name);
		}
		if ($status) {
			$alter[] = ltrim($status);
		}
		return ($alter ? queries("ALTER TABLE " . table($table) . "\n" . implode(",\n", $alter)) : true);
	}

	/** Run commands to alter indexes
	* @param string $table escaped table name
	* @param list<array{string, string, 'DROP'|list<string>, 3?: string, 4?: string}> $alter of ["index type", "name", ["column definition", ...], "algorithm", "condition"] or ["index type", "name", "DROP"]
	* @return Result|bool
	*/
	function alter_indexes($table, $alter) {
		$changes = array();
		foreach ($alter as $val) {
			$changes[] = ($val[2] == "DROP"
				? "\nDROP INDEX " . idf_escape($val[1])
				: "\nADD $val[0] " . ($val[0] == "PRIMARY" ? "KEY " : "") . ($val[1] != "" ? idf_escape($val[1]) . " " : "") . "(" . implode(", ", $val[2]) . ")"
			);
		}
		return queries("ALTER TABLE " . table($table) . implode(",", $changes));
	}

	/** Run commands to truncate tables
	* @param list<string> $tables
	*/
	function truncate_tables(array $tables) {
		return apply_queries("TRUNCATE TABLE", $tables);
	}

	/** Drop views
	* @param list<string> $views
	* @return Result|bool
	*/
	function drop_views(array $views) {
		return queries("DROP VIEW " . implode(", ", array_map('Adminer\table', $views)));
	}

	/** Drop tables
	* @param list<string> $tables
	* @return Result|bool
	*/
	function drop_tables(array $tables) {
		return queries("DROP TABLE " . implode(", ", array_map('Adminer\table', $tables)));
	}

	/** Move tables to other schema
	* @param list<string> $tables
	* @param list<string> $views
	*/
	function move_tables(array $tables, array $views, $target) {
		$rename = array();
		foreach ($tables as $table) {
			$rename[] = table($table) . " TO " . idf_escape($target) . "." . table($table);
		}
		if (!$rename || queries("RENAME TABLE " . implode(", ", $rename))) {
			$definitions = array();
			foreach ($views as $table) {
				$definitions[table($table)] = view($table);
			}
			connection()->select_db($target);
			$db = idf_escape(DB);
			foreach ($definitions as $name => $view) {
				if (!queries("CREATE VIEW $name AS " . str_replace(" $db.", " ", $view["select"])) || !queries("DROP VIEW $db.$name")) {
					return false;
				}
			}
			return true;
		}
		//! move triggers
		return false;
	}

	/** Copy tables to other schema
	* @param list<string> $tables
	* @param list<string> $views
	*/
	function copy_tables(array $tables, array $views, $target) {
		queries("SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");
		foreach ($tables as $table) {
			$name = ($target == DB ? table("copy_$table") : idf_escape($target) . "." . table($table));
			if (
				($_POST["overwrite"] && !queries("\nDROP TABLE IF EXISTS $name"))
				|| !queries("CREATE TABLE $name LIKE " . table($table))
				|| !queries("INSERT INTO $name SELECT * FROM " . table($table))
			) {
				return false;
			}
			foreach (get_rows("SHOW TRIGGERS LIKE " . q(addcslashes($table, "%_\\"))) as $row) {
				$trigger = $row["Trigger"];
				if (!queries("CREATE TRIGGER " . ($target == DB ? idf_escape("copy_$trigger") : idf_escape($target) . "." . idf_escape($trigger)) . " $row[Timing] $row[Event] ON $name FOR EACH ROW\n$row[Statement];")) {
					return false;
				}
			}
		}
		foreach ($views as $table) {
			$name = ($target == DB ? table("copy_$table") : idf_escape($target) . "." . table($table));
			$view = view($table);
			if (
				($_POST["overwrite"] && !queries("DROP VIEW IF EXISTS $name"))
				|| !queries("CREATE VIEW $name AS $view[select]") //! USE to avoid db.table
			) {
				return false;
			}
		}
		return true;
	}

	/** Get information about trigger
	* @param string $name trigger name
	* @return Trigger
	*/
	function trigger($name, $table) {
		if ($name == "") {
			return array();
		}
		$rows = get_rows("SHOW TRIGGERS WHERE `Trigger` = " . q($name));
		return reset($rows);
	}

	/** Get defined triggers
	* @return array{string, string}[]
	*/
	function triggers($table) {
		$return = array();
		foreach (get_rows("SHOW TRIGGERS LIKE " . q(addcslashes($table, "%_\\"))) as $row) {
			$return[$row["Trigger"]] = array($row["Timing"], $row["Event"]);
		}
		return $return;
	}

	/** Get trigger options
	* @return array{Timing: list<string>, Event: list<string>, Type: list<string>}
	*/
	function trigger_options() {
		return array(
			"Timing" => array("BEFORE", "AFTER"),
			"Event" => array("INSERT", "UPDATE", "DELETE"),
			"Type" => array("FOR EACH ROW"),
		);
	}

	/** Get information about stored routine
	* @param 'FUNCTION'|'PROCEDURE' $type
	* @return Routine
	*/
	function routine($name, $type) {
		$fields = get_rows("SELECT
	PARAMETER_NAME field,
	DATA_TYPE type,
	CHARACTER_MAXIMUM_LENGTH length,
	REGEXP_REPLACE(DTD_IDENTIFIER, '^[^ ]+ ', '') `unsigned`,
	1 `null`,
	DTD_IDENTIFIER full_type,
	PARAMETER_MODE `inout`,
	CHARACTER_SET_NAME collation
FROM information_schema.PARAMETERS
WHERE SPECIFIC_SCHEMA = DATABASE() AND ROUTINE_TYPE = '$type' AND SPECIFIC_NAME = " . q($name) . "
ORDER BY ORDINAL_POSITION");
		$return = connection()->query("SELECT ROUTINE_COMMENT comment, ROUTINE_DEFINITION definition, 'SQL' language
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_TYPE = '$type' AND ROUTINE_NAME = " . q($name))->fetch_assoc();
		if ($fields && $fields[0]['field'] == '') {
			$return['returns'] = array_shift($fields);
		}
		$return['fields'] = $fields;
		/** @phpstan-var Routine */
		return $return;
	}

	/** Get list of routines
	* @return list<string[]> ["SPECIFIC_NAME" => , "ROUTINE_NAME" => , "ROUTINE_TYPE" => , "DTD_IDENTIFIER" => ]
	*/
	function routines() {
		return get_rows("SELECT SPECIFIC_NAME, ROUTINE_NAME, ROUTINE_TYPE, DTD_IDENTIFIER FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE()");
	}

	/** Get list of available routine languages
	* @return list<string>
	*/
	function routine_languages() {
		return array(); // "SQL" not required
	}

	/** Get routine signature
	* @param Routine $row
	*/
	function routine_id($name, array $row) {
		return idf_escape($name);
	}

	/** Get last auto increment ID
	* @param Result|bool $result
	*/
	function last_id($result) {
		return get_val("SELECT LAST_INSERT_ID()"); // mysql_insert_id() truncates bigint
	}

	/** Explain select
	* @return Result
	*/
	function explain(Db $connection, $query) {
		return $connection->query("EXPLAIN " . (min_version(5.1) && !min_version(5.7) ? "PARTITIONS " : "") . $query);
	}

	/** Get approximate number of rows
	* @param TableStatus $table_status
	* @param list<string> $where
	* @return numeric-string|null null if approximate number can't be retrieved
	*/
	function found_rows(array $table_status, array $where) {
		return ($where || $table_status["Engine"] != "InnoDB" ? null : $table_status["Rows"]);
	}

	/** Get SQL command to create table */
	function create_sql($table, $auto_increment, $style) {
		$return = get_val("SHOW CREATE TABLE " . table($table), 1);
		if (!$auto_increment) {
			$return = preg_replace('~ AUTO_INCREMENT=\d+~', '', $return); //! skip comments
		}
		return $return;
	}

	/** Get SQL command to truncate table */
	function truncate_sql($table) {
		return "TRUNCATE " . table($table);
	}

	/** Get SQL command to change database */
	function use_sql($database, $style = "") {
		$name = idf_escape($database);
		$return = "";
		if (preg_match('~CREATE~', $style) && ($create = get_val("SHOW CREATE DATABASE $name", 1))) {
			set_utf8mb4($create);
			if ($style == "DROP+CREATE") {
				$return = "DROP DATABASE IF EXISTS $name;\n";
			}
			$return .= "$create;\n";
		}
		return $return . "USE $name";
	}

	/** Get SQL commands to create triggers */
	function trigger_sql($table) {
		$return = "";
		foreach (get_rows("SHOW TRIGGERS LIKE " . q(addcslashes($table, "%_\\")), null, "-- ") as $row) {
			$return .= "\nCREATE TRIGGER " . idf_escape($row["Trigger"]) . " $row[Timing] $row[Event] ON " . table($row["Table"]) . " FOR EACH ROW\n$row[Statement];;\n";
		}
		return $return;
	}

	/** Get server variables
	* @return list<string[]> [[$name, $value]]
	*/
	function show_variables() {
		return get_rows("SHOW VARIABLES");
	}

	/** Get status variables
	* @return list<string[]> [[$name, $value]]
	*/
	function show_status() {
		return get_rows("SHOW STATUS");
	}

	/** Get process list
	* @return list<string[]> [$row]
	*/
	function process_list() {
		return get_rows("SHOW FULL PROCESSLIST");
	}

	/** Convert field in select and edit
	* @param Field $field
	* @return string|void
	*/
	function convert_field(array $field) {
		if (preg_match("~binary~", $field["type"])) {
			return "HEX(" . idf_escape($field["field"]) . ")";
		}
		if ($field["type"] == "bit") {
			return "BIN(" . idf_escape($field["field"]) . " + 0)"; // + 0 is required outside MySQLnd
		}
		if (preg_match("~geometry|point|linestring|polygon~", $field["type"])) {
			return (min_version(8) ? "ST_" : "") . "AsWKT(" . idf_escape($field["field"]) . ")";
		}
	}

	/** Convert value in edit after applying functions back
	* @param Field $field
	* @param string $return SQL expression
	*/
	function unconvert_field(array $field, $return) {
		if (preg_match("~binary~", $field["type"])) {
			$return = "UNHEX($return)";
		}
		if ($field["type"] == "bit") {
			$return = "CONVERT(b$return, UNSIGNED)";
		}
		if (preg_match("~geometry|point|linestring|polygon~", $field["type"])) {
			$prefix = (min_version(8) ? "ST_" : "");
			$return = $prefix . "GeomFromText($return, $prefix" . "SRID($field[field]))";
		}
		return $return;
	}

	/** Check whether a feature is supported
	* @param literal-string $feature check|comment|columns|copy|database|descidx|drop_col|dump|event|indexes|kill|materializedview
	* |move_col|privileges|procedure|processlist|routine|scheme|sequence|sql|status|table|trigger|type|variables|view|view_trigger
	*/
	function support($feature) {
		return preg_match(
			'~^(comment|columns|copy|database|drop_col|dump|indexes|kill|privileges|move_col|procedure|processlist|routine|sql|status|table|trigger|variables|view'
				. (min_version(5.1) ? '|event' : '')
				. (min_version(8) ? '|descidx' : '')
				. (min_version('8.0.16', '10.2.1') ? '|check' : '')
				. ')$~',
			$feature
		);
	}

	/** Kill a process
	* @param numeric-string $id
	* @return Result|bool
	*/
	function kill_process($id) {
		return queries("KILL " . number($id));
	}

	/** Return query to get connection ID */
	function connection_id() {
		return "SELECT CONNECTION_ID()";
	}

	/** Get maximum number of connections
	* @return numeric-string
	*/
	function max_connections() {
		return get_val("SELECT @@max_connections");
	}

	// Not used is MySQL but checked in compile.php:

	/** Get user defined types
	* @return string[] [$id => $name]
	*/
	function types() {
		return array();
	}

	/** Get values of user defined type */
	function type_values($id) {
		return "";
	}

	/** Get existing schemas
	* @return list<string>
	*/
	function schemas() {
		return array();
	}

	/** Get current schema */
	function get_schema() {
		return "";
	}

	/** Set current schema
	*/
	function set_schema($schema, $connection2 = null) {
		return true;
	}
}
 // must be included as last driver

define('Adminer\JUSH', Driver::$jush);
define('Adminer\SERVER', "" . $_GET[DRIVER]); // read from pgsql=localhost, '' means default server
define('Adminer\DB', "$_GET[db]"); // for the sake of speed and size
define(
	'Adminer\ME',
	preg_replace('~\?.*~', '', relative_uri()) . '?'
		. (sid() ? SID . '&' : '')
		. (SERVER !== null ? DRIVER . "=" . urlencode(SERVER) . '&' : '')
		. ($_GET["ext"] ? "ext=" . urlencode($_GET["ext"]) . '&' : '')
		. (isset($_GET["username"]) ? "username=" . urlencode($_GET["username"]) . '&' : '')
		. (DB != "" ? 'db=' . urlencode(DB) . '&' . (isset($_GET["ns"]) ? "ns=" . urlencode($_GET["ns"]) . "&" : "") : '')
);

?>
<?php
/** Print HTML header
* @param string $title used in title, breadcrumb and heading, should be HTML escaped
* @param mixed $breadcrumb ["key" => "link", "key2" => ["link", "desc"]], null for nothing, false for driver only, true for driver and server
* @param string $title2 used after colon in title and heading, should be HTML escaped
*/
function page_header($title, $error = "", $breadcrumb = array(), $title2 = "") {
	page_headers();
	if (is_ajax() && $error) {
		page_messages($error);
		exit;
	}
	if (!ob_get_level()) {
		ob_start('ob_gzhandler', 4096);
	}
	$title_all = $title . ($title2 != "" ? ": $title2" : "");
	$title_page = strip_tags($title_all . (SERVER != "" && SERVER != "localhost" ? h(" - " . SERVER) : "") . " - " . adminer()->name());
	// initial-scale=1 is the default but Chrome 134 on iOS is not able to zoom out without it
	?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="robots" content="noindex">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo $title_page; ?></title>
<link rel="stylesheet" href="<?php echo h(preg_replace("~\\?.*~", "", ME) . "?file=default.css&version=5.4.2-dev"); ?>">
<?php

	$css = adminer()->css();
	if (is_int(key($css))) { // legacy return value
		$css = array_fill_keys($css, 'light');
	}
	$has_light = in_array('light', $css) || in_array('', $css);
	$has_dark = in_array('dark', $css) || in_array('', $css);
	$dark = ($has_light
		? ($has_dark ? null : false) // both styles - autoswitching, only adminer.css - light
		: ($has_dark ?: null) // only adminer-dark.css - dark, neither - autoswitching
	);
	$media = " media='(prefers-color-scheme: dark)'";
	if ($dark !== false) {
		echo "<link rel='stylesheet'" . ($dark ? "" : $media) . " href='" . h(preg_replace("~\\?.*~", "", ME) . "?file=dark.css&version=5.4.2-dev") . "'>\n";
	}
	echo "<meta name='color-scheme' content='" . ($dark === null ? "light dark" : ($dark ? "dark" : "light")) . "'>\n";

	// this is matched by compile.php
	echo script_src(preg_replace("~\\?.*~", "", ME) . "?file=functions.js&version=5.4.2-dev");
		if (adminer()->head($dark)) {
		echo "<link rel='icon' href='data:image/gif;base64,R0lGODlhEAAQAJEAAAQCBPz+/PwCBAROZCH5BAEAAAAALAAAAAAQABAAAAI2hI+pGO1rmghihiUdvUBnZ3XBQA7f05mOak1RWXrNq5nQWHMKvuoJ37BhVEEfYxQzHjWQ5qIAADs='>\n";
		echo "<link rel='apple-touch-icon' href='" . h(preg_replace("~\\?.*~", "", ME) . "?file=logo.png&version=5.4.2-dev") . "'>\n";
	}
	foreach ($css as $url => $mode) {
		$attrs = ($mode == 'dark' && !$dark
			? $media
			: ($mode == 'light' && $has_dark ? " media='(prefers-color-scheme: light)'" : "")
		);
		echo "<link rel='stylesheet'$attrs href='" . h($url) . "'>\n";
	}
	echo "\n<body class='" . 'ltr' . " nojs";
	adminer()->bodyClass();
	echo "'>\n";
	$filename = get_temp_dir() . "/adminer.version";
	if (!$_COOKIE["adminer_version"] && function_exists('openssl_verify') && file_exists($filename) && filemtime($filename) + 86400 > time()) { // 86400 - 1 day in seconds
		$version = unserialize(file_get_contents($filename));
		$public = "-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAwqWOVuF5uw7/+Z70djoK
RlHIZFZPO0uYRezq90+7Amk+FDNd7KkL5eDve+vHRJBLAszF/7XKXe11xwliIsFs
DFWQlsABVZB3oisKCBEuI71J4kPH8dKGEWR9jDHFw3cWmoH3PmqImX6FISWbG3B8
h7FIx3jEaw5ckVPVTeo5JRm/1DZzJxjyDenXvBQ/6o9DgZKeNDgxwKzH+sw9/YCO
jHnq1cFpOIISzARlrHMa/43YfeNRAm/tsBXjSxembBPo7aQZLAWHmaj5+K19H10B
nCpz9Y++cipkVEiKRGih4ZEvjoFysEOdRLj6WiD/uUNky4xGeA6LaJqh5XpkFkcQ
fQIDAQAB
-----END PUBLIC KEY-----
";
		if (openssl_verify($version["version"], base64_decode($version["signature"]), $public) == 1) {
			$_COOKIE["adminer_version"] = $version["version"]; // doesn't need to send to the browser
		}
	}
	echo script("mixin(document.body, {onkeydown: bodyKeydown, onclick: bodyClick"
		. (isset($_COOKIE["adminer_version"]) ? "" : ", onload: partial(verifyVersion, '" . VERSION . "', '" . js_escape(ME) . "', '" . get_token() . "')")
		. "});
document.body.classList.replace('nojs', 'js');
const offlineMessage = '" . js_escape('You are offline.') . "';
const thousandsSeparator = '" . js_escape(',') . "';")
	;
	echo "<div id='help' class='jush-" . JUSH . " jsonly hidden'></div>\n";
	echo script("mixin(qs('#help'), {onmouseover: () => { helpOpen = 1; }, onmouseout: helpMouseout});");
	echo "<div id='content'>\n";
	echo "<span id='menuopen' class='jsonly'>" . icon("move", "", "menu", "") . "</span>" . script("qs('#menuopen').onclick = event => { qs('#foot').classList.toggle('foot'); event.stopPropagation(); }");
	if ($breadcrumb !== null) {
		$link = substr(preg_replace('~\b(username|db|ns)=[^&]*&~', '', ME), 0, -1);
		echo '<p id="breadcrumb"><a href="' . h($link ?: ".") . '">' . get_driver(DRIVER) . '</a> » ';
		$link = substr(preg_replace('~\b(db|ns)=[^&]*&~', '', ME), 0, -1);
		$server = adminer()->serverName(SERVER);
		$server = ($server != "" ? $server : 'Server');
		if ($breadcrumb === false) {
			echo "$server\n";
		} else {
			echo "<a href='" . h($link) . "' accesskey='1' title='Alt+Shift+1'>$server</a> » ";
			if ($_GET["ns"] != "" || (DB != "" && is_array($breadcrumb))) {
				echo '<a href="' . h($link . "&db=" . urlencode(DB) . (support("scheme") ? "&ns=" : "")) . '">' . h(DB) . '</a> » ';
			}
			if (is_array($breadcrumb)) {
				if ($_GET["ns"] != "") {
					echo '<a href="' . h(substr(ME, 0, -1)) . '">' . h($_GET["ns"]) . '</a> » ';
				}
				foreach ($breadcrumb as $key => $val) {
					$desc = (is_array($val) ? $val[1] : h($val));
					if ($desc != "") {
						echo "<a href='" . h(ME . "$key=") . urlencode(is_array($val) ? $val[0] : $val) . "'>$desc</a> » ";
					}
				}
			}
			echo "$title\n";
		}
	}
	echo "<h2>$title_all</h2>\n";
	echo "<div id='ajaxstatus' class='jsonly hidden'></div>\n";
	restart_session();
	page_messages($error);
	$databases = &get_session("dbs");
	if (DB != "" && $databases && !in_array(DB, $databases, true)) {
		$databases = null;
	}
	stop_session();
	define('Adminer\PAGE_HEADER', 1);
}

/** Send HTTP headers */
function page_headers() {
	header("Content-Type: text/html; charset=utf-8");
	header("Cache-Control: no-cache");
	header("X-Frame-Options: deny"); // ClickJacking protection in IE8, Safari 4, Chrome 2, Firefox 3.6.9
	header("X-XSS-Protection: 0"); // prevents introducing XSS in IE8 by removing safe parts of the page
	header("X-Content-Type-Options: nosniff");
	header("Referrer-Policy: origin-when-cross-origin");
	foreach (adminer()->csp(csp()) as $csp) {
		$header = array();
		foreach ($csp as $key => $val) {
			$header[] = "$key $val";
		}
		header("Content-Security-Policy: " . implode("; ", $header));
	}
	adminer()->headers();
}

/** Get Content Security Policy headers
* @return list<string[]> of arrays with directive name in key, allowed sources in value
*/
function csp() {
	return array(
		array(
			"script-src" => "'self' 'unsafe-inline' 'nonce-" . get_nonce() . "' 'strict-dynamic'", // 'self' is a fallback for browsers not supporting 'strict-dynamic', 'unsafe-inline' is a fallback for browsers not supporting 'nonce-'
			"connect-src" => "'self'",
			"frame-src" => "https://www.adminer.org",
			"object-src" => "'none'",
			"base-uri" => "'none'",
			"form-action" => "'self'",
		),
	);
}

/** Get a CSP nonce
* @return string Base64 value
*/
function get_nonce() {
	static $nonce;
	if (!$nonce) {
		$nonce = base64_encode(rand_string());
	}
	return $nonce;
}

/** Print flash and error messages */
function page_messages($error) {
	$uri = preg_replace('~^[^?]*~', '', $_SERVER["REQUEST_URI"]);
	$messages = idx($_SESSION["messages"], $uri);
	if ($messages) {
		echo "<div class='message'>" . implode("</div>\n<div class='message'>", $messages) . "</div>" . script("messagesPrint();");
		unset($_SESSION["messages"][$uri]);
	}
	if ($error) {
		echo "<div class='error'>$error</div>\n";
	}
	if (adminer()->error) { // separate <div>
		echo "<div class='error'>" . adminer()->error . "</div>\n";
	}
}

/** Print HTML footer
* @param ''|'auth'|'db'|'ns' $missing
*/
function page_footer($missing = "") {
	echo "</div>\n\n<div id='foot' class='foot'>\n<div id='menu'>\n";
	adminer()->navigation($missing);
	echo "</div>\n";
	if ($missing != "auth") {
		?>
<form action="" method="post">
<p class="logout">
<span><?php echo h($_GET["username"]) . "\n"; ?></span>
<input type="submit" name="logout" value="Logout" id="logout">
<?php echo input_token(); ?>
</form>
<?php
	}
	echo "</div>\n\n";
	echo script("setupSubmitHighlight(document);");
}

?>
<?php
/** PHP implementation of XXTEA encryption algorithm
* @author Ma Bingyao <andot@ujn.edu.cn>
* @link http://www.coolcode.cn/?action=show&id=128
*/

function int32($n) {
	while ($n >= 2147483648) {
		$n -= 4294967296;
	}
	while ($n <= -2147483649) {
		$n += 4294967296;
	}
	return (int) $n;
}

/**
* @param int[] $v
*/
function long2str(array $v, $w) {
	$s = '';
	foreach ($v as $val) {
		$s .= pack('V', $val);
	}
	if ($w) {
		return substr($s, 0, end($v));
	}
	return $s;
}

/**
* @return int[]
*/
function str2long($s, $w) {
	$v = array_values(unpack('V*', str_pad($s, 4 * ceil(strlen($s) / 4), "\0")));
	if ($w) {
		$v[] = strlen($s);
	}
	return $v;
}

function xxtea_mx($z, $y, $sum, $k) {
	return int32((($z >> 5 & 0x7FFFFFF) ^ $y << 2) + (($y >> 3 & 0x1FFFFFFF) ^ $z << 4)) ^ int32(($sum ^ $y) + ($k ^ $z));
}

/** Cipher
* @param string $str plain-text password
* @return string binary cipher
*/
function encrypt_string($str, $key) {
	if ($str == "") {
		return "";
	}
	$key = array_values(unpack("V*", pack("H*", md5($key))));
	$v = str2long($str, true);
	$n = count($v) - 1;
	$z = $v[$n];
	$y = $v[0];
	$q = floor(6 + 52 / ($n + 1));
	$sum = 0;
	while ($q-- > 0) {
		$sum = int32($sum + 0x9E3779B9);
		$e = $sum >> 2 & 3;
		for ($p=0; $p < $n; $p++) {
			$y = $v[$p + 1];
			$mx = xxtea_mx($z, $y, $sum, $key[$p & 3 ^ $e]);
			$z = int32($v[$p] + $mx);
			$v[$p] = $z;
		}
		$y = $v[0];
		$mx = xxtea_mx($z, $y, $sum, $key[$p & 3 ^ $e]);
		$z = int32($v[$n] + $mx);
		$v[$n] = $z;
	}
	return long2str($v, false);
}

/** Decipher
* @param string $str binary cipher
* @return string|false plain-text password
*/
function decrypt_string($str, $key) {
	if ($str == "") {
		return "";
	}
	if (!$key) {
		return false;
	}
	$key = array_values(unpack("V*", pack("H*", md5($key))));
	$v = str2long($str, false);
	$n = count($v) - 1;
	$z = $v[$n];
	$y = $v[0];
	$q = floor(6 + 52 / ($n + 1));
	$sum = int32($q * 0x9E3779B9);
	while ($sum) {
		$e = $sum >> 2 & 3;
		for ($p=$n; $p > 0; $p--) {
			$z = $v[$p - 1];
			$mx = xxtea_mx($z, $y, $sum, $key[$p & 3 ^ $e]);
			$y = int32($v[$p] - $mx);
			$v[$p] = $y;
		}
		$z = $v[$n];
		$mx = xxtea_mx($z, $y, $sum, $key[$p & 3 ^ $e]);
		$y = int32($v[0] - $mx);
		$v[0] = $y;
		$sum = int32($sum - 0x9E3779B9);
	}
	return long2str($v, true);
}

?>
<?php
$permanent = array();
if ($_COOKIE["adminer_permanent"]) {
	foreach (explode(" ", $_COOKIE["adminer_permanent"]) as $val) {
		list($key) = explode(":", $val);
		$permanent[$key] = $val;
	}
}

function add_invalid_login() {
	$base = get_temp_dir() . "/adminer.invalid";
	// adminer.invalid may not be writable by us, try the files with random suffixes
	foreach (glob("$base*") ?: array($base) as $filename) {
		$fp = file_open_lock($filename);
		if ($fp) {
			break;
		}
	}
	if (!$fp) {
		$fp = file_open_lock("$base-" . rand_string());
	}
	if (!$fp) {
		return;
	}
	$invalids = unserialize(stream_get_contents($fp));
	$time = time();
	if ($invalids) {
		foreach ($invalids as $ip => $val) {
			if ($val[0] < $time) {
				unset($invalids[$ip]);
			}
		}
	}
	$invalid = &$invalids[adminer()->bruteForceKey()];
	if (!$invalid) {
		$invalid = array($time + 30*60, 0); // active for 30 minutes
	}
	$invalid[1]++;
	file_write_unlock($fp, serialize($invalids));
}

/** @param string[] $permanent */
function check_invalid_login(array &$permanent) {
	$invalids = array();
	foreach (glob(get_temp_dir() . "/adminer.invalid*") as $filename) {
		$fp = file_open_lock($filename);
		if ($fp) {
			$invalids = unserialize(stream_get_contents($fp));
			file_unlock($fp);
			break;
		}
	}
	/** @var array{int, int} */
	$invalid = idx($invalids, adminer()->bruteForceKey(), array());
	$next_attempt = ($invalid[1] > 29 ? $invalid[0] - time() : 0); // allow 30 invalid attempts
	if ($next_attempt > 0) { //! do the same with permanent login
		auth_error(lang_format(array('Too many unsuccessful logins, try again in %d minute.', 'Too many unsuccessful logins, try again in %d minutes.'), ceil($next_attempt / 60)), $permanent);
	}
}

$auth = $_POST["auth"];
if ($auth) {
	session_regenerate_id(); // defense against session fixation
	$vendor = $auth["driver"];
	$server = $auth["server"];
	$username = $auth["username"];
	$password = (string) $auth["password"];
	$db = $auth["db"];
	set_password($vendor, $server, $username, $password);
	$_SESSION["db"][$vendor][$server][$username][$db] = true;
	if ($auth["permanent"]) {
		$key = implode("-", array_map('base64_encode', array($vendor, $server, $username, $db)));
		$private = adminer()->permanentLogin(true);
		$permanent[$key] = "$key:" . base64_encode($private ? encrypt_string($password, $private) : "");
		cookie("adminer_permanent", implode(" ", $permanent));
	}
	if (
		count($_POST) == 1 // 1 - auth
		|| DRIVER != $vendor
		|| SERVER != $server
		|| $_GET["username"] !== $username // "0" == "00"
		|| DB != $db
	) {
		redirect(auth_url($vendor, $server, $username, $db));
	}

} elseif ($_POST["logout"] && (!$_SESSION["token"] || verify_token())) {
	foreach (array("pwds", "db", "dbs", "queries") as $key) {
		set_session($key, null);
	}
	unset_permanent($permanent);
	redirect(substr(preg_replace('~\b(username|db|ns)=[^&]*&~', '', ME), 0, -1), 'Logout successful.' . ' ' . 'Thanks for using Adminer, consider <a href="https://www.adminer.org/en/donation/">donating</a>.');

} elseif ($permanent && !$_SESSION["pwds"]) {
	session_regenerate_id();
	$private = adminer()->permanentLogin();
	foreach ($permanent as $key => $val) {
		list(, $cipher) = explode(":", $val);
		list($vendor, $server, $username, $db) = array_map('base64_decode', explode("-", $key));
		set_password($vendor, $server, $username, decrypt_string(base64_decode($cipher), $private));
		$_SESSION["db"][$vendor][$server][$username][$db] = true;
	}
}

/** Remove credentials from permanent login
* @param string[] $permanent
*/
function unset_permanent(array &$permanent) {
	foreach ($permanent as $key => $val) {
		list($vendor, $server, $username, $db) = array_map('base64_decode', explode("-", $key));
		if ($vendor == DRIVER && $server == SERVER && $username == $_GET["username"] && $db == DB) {
			unset($permanent[$key]);
		}
	}
	cookie("adminer_permanent", implode(" ", $permanent));
}

/** Render an error message and a login form
* @param string $error plain text
* @param string[] $permanent
* @return never
*/
function auth_error($error, array &$permanent) {
	$session_name = session_name();
	if (isset($_GET["username"])) {
		header("HTTP/1.1 403 Forbidden"); // 401 requires sending WWW-Authenticate header
		if (($_COOKIE[$session_name] || $_GET[$session_name]) && !$_SESSION["token"]) {
			$error = 'Session expired, please login again.';
		} else {
			restart_session();
			add_invalid_login();
			$password = get_password();
			if ($password !== null) {
				if ($password === false) {
					$error .= ($error ? '<br>' : '') . sprintf('Master password expired. <a href="https://www.adminer.org/en/extension/"%s>Implement</a> %s method to make it permanent.', target_blank(), '<code>permanentLogin()</code>');
				}
				set_password(DRIVER, SERVER, $_GET["username"], null);
			}
			unset_permanent($permanent);
		}
	}
	if (!$_COOKIE[$session_name] && $_GET[$session_name] && ini_bool("session.use_only_cookies")) {
		$error = 'Session support must be enabled.';
	}
	$params = session_get_cookie_params();
	cookie("adminer_key", ($_COOKIE["adminer_key"] ?: rand_string()), $params["lifetime"]);
	if (!$_SESSION["token"]) {
		$_SESSION["token"] = rand(1, 1e6); // this is for next attempt
	}
	page_header('Login', $error, null);
	echo "<form action='' method='post'>\n";
	echo "<div>";
	if (hidden_fields($_POST, array("auth"))) { // expired session
		echo "<p class='message'>" . 'The action will be performed after successful login with the same credentials.' . "\n";
	}
	echo "</div>\n";
	adminer()->loginForm();
	echo "</form>\n";
	page_footer("auth");
	exit;
}

if (isset($_GET["username"]) && !class_exists('Adminer\Db')) {
	unset($_SESSION["pwds"][DRIVER]);
	unset_permanent($permanent);
	page_header('No extension', sprintf('None of the supported PHP extensions (%s) are available.', implode(", ", Driver::$extensions)), false);
	page_footer("auth");
	exit;
}

$connection = '';
if (isset($_GET["username"]) && is_string(get_password())) {
	list(, $port) = host_port(SERVER);
	if (preg_match('~^\s*([-+]?\d+)~', $port, $match) && ($match[1] < 1024 || $match[1] > 65535)) { // is_numeric('80#') would still connect to port 80
		auth_error('Connecting to privileged ports is not allowed.', $permanent);
	}
	check_invalid_login($permanent);
	$credentials = adminer()->credentials();
	$connection = Driver::connect($credentials[0], $credentials[1], $credentials[2]);
	if (is_object($connection)) {
		Db::$instance = $connection;
		Driver::$instance = new Driver($connection);
		if ($connection->flavor) {
			save_settings(array("vendor-" . DRIVER . "-" . SERVER => get_driver(DRIVER)));
		}
	}
}

$login = null;
if (!is_object($connection) || ($login = adminer()->login($_GET["username"], get_password())) !== true) {
	$error = (is_string($connection) ? nl_br(h($connection)) : (is_string($login) ? $login : 'Invalid credentials.'))
		. (preg_match('~^ | $~', get_password()) ? '<br>' . 'There is a space in the input password which might be the cause.' : '');
	auth_error($error, $permanent);
}

if ($_POST["logout"] && $_SESSION["token"] && !verify_token()) {
	page_header('Logout', 'Invalid CSRF token. Send the form again.');
	page_footer("db");
	exit;
}

if (!$_SESSION["token"]) {
	$_SESSION["token"] = rand(1, 1e6); // defense against cross-site request forgery
}
stop_session(true);
if ($auth && $_POST["token"]) {
	$_POST["token"] = get_token(); // reset token after explicit login
}

$error = ''; ///< @var string
if ($_POST) {
	if (!verify_token()) {
		$ini = "max_input_vars";
		$max_vars = ini_get($ini);
		if (extension_loaded("suhosin")) {
			foreach (array("suhosin.request.max_vars", "suhosin.post.max_vars") as $key) {
				$val = ini_get($key);
				if ($val && (!$max_vars || $val < $max_vars)) {
					$ini = $key;
					$max_vars = $val;
				}
			}
		}
		$error = (!$_POST["token"] && $max_vars
			? sprintf('Maximum number of allowed fields exceeded. Please increase %s.', "'$ini'")
			: 'Invalid CSRF token. Send the form again.' . ' ' . 'If you did not send this request from Adminer then close this page.'
		);
	}

} elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
	// posted form with no data means that post_max_size exceeded because Adminer always sends token at least
	$error = sprintf('Too big POST data. Reduce the data or increase the %s configuration directive.', "'post_max_size'");
	if (isset($_GET["sql"])) {
		$error .= ' ' . 'You can upload a big SQL file via FTP and import it from server.';
	}
}

?>
<?php
// This file is not used in Adminer Editor.

/** Print select result
* @param Result $result
* @param string[] $orgtables
* @param int|numeric-string $limit
* @return string[] $orgtables
*/
function print_select_result($result, $connection2 = null, array $orgtables = array(), $limit = 0) {
	$links = array(); // colno => orgtable - create links from these columns
	$indexes = array(); // orgtable => array(column => colno) - primary keys
	$columns = array(); // orgtable => array(column => ) - not selected columns in primary key
	$blobs = array(); // colno => bool - display bytes for blobs
	$types = array(); // colno => type - display char in <code>
	$return = array(); // table => orgtable - mapping to use in EXPLAIN
	for ($i=0; (!$limit || $i < $limit) && ($row = $result->fetch_row()); $i++) {
		if (!$i) {
			echo "<div class='scrollable'>\n";
			echo "<table class='nowrap odds'>\n";
			echo "<thead><tr>";
			for ($j=0; $j < count($row); $j++) {
				$field = $result->fetch_field();
				$name = $field->name;
				$orgtable = (isset($field->orgtable) ? $field->orgtable : "");
				$orgname = (isset($field->orgname) ? $field->orgname : $name);
				if ($orgtables && JUSH == "sql") { // MySQL EXPLAIN
					$links[$j] = ($name == "table" ? "table=" : ($name == "possible_keys" ? "indexes=" : null));
				} elseif ($orgtable != "") {
					if (isset($field->table)) {
						$return[$field->table] = $orgtable;
					}
					if (!isset($indexes[$orgtable])) {
						// find primary key in each table
						$indexes[$orgtable] = array();
						foreach (indexes($orgtable, $connection2) as $index) {
							if ($index["type"] == "PRIMARY") {
								$indexes[$orgtable] = array_flip($index["columns"]);
								break;
							}
						}
						$columns[$orgtable] = $indexes[$orgtable];
					}
					if (isset($columns[$orgtable][$orgname])) {
						unset($columns[$orgtable][$orgname]);
						$indexes[$orgtable][$orgname] = $j;
						$links[$j] = $orgtable;
					}
				}
				if ($field->charsetnr == 63) { // 63 - binary
					$blobs[$j] = true;
				}
				$types[$j] = $field->type;
				echo "<th" . ($orgtable != "" || $field->name != $orgname ? " title='" . h(($orgtable != "" ? "$orgtable." : "") . $orgname) . "'" : "") . ">" . h($name)
					. ($orgtables ? doc_link(array(
						'sql' => "explain-output.html#explain_" . strtolower($name),
						'mariadb' => "explain/#the-columns-in-explain-select",
					)) : "")
				;
			}
			echo "</thead>\n";
		}
		echo "<tr>";
		foreach ($row as $key => $val) {
			$link = "";
			if (isset($links[$key]) && !$columns[$links[$key]]) {
				if ($orgtables && JUSH == "sql") { // MySQL EXPLAIN
					$table = $row[array_search("table=", $links)];
					$link = ME . $links[$key] . urlencode($orgtables[$table] != "" ? $orgtables[$table] : $table);
				} else {
					$link = ME . "edit=" . urlencode($links[$key]);
					foreach ($indexes[$links[$key]] as $col => $j) {
						if ($row[$j] === null) {
							$link = "";
							break;
						}
						$link .= "&where" . urlencode("[" . bracket_escape($col) . "]") . "=" . urlencode($row[$j]);
					}
				}
			}
			$field = array(
				'type' => ($blobs[$key] ? 'blob' : ($types[$key] == 254 ? 'char' : '')),
			);
			$val = select_value($val, $link, $field, null);
			// https://dev.mysql.com/doc/dev/mysql-server/latest/field__types_8h.html
			echo "<td" . ($types[$key] <= 9 || $types[$key] == 246 ? " class='number'" : "") . ">$val";
		}
	}
	echo ($i ? "</table>\n</div>" : "<p class='message'>" . 'No rows.') . "\n";
	return $return;
}

/** Get referencable tables with single column primary key except self
* @return array<string, Field> [$table_name => $field]
*/
function referencable_primary($self) {
	$return = array(); // table_name => field
	foreach (table_status('', true) as $table_name => $table) {
		if ($table_name != $self && fk_support($table)) {
			foreach (fields($table_name) as $field) {
				if ($field["primary"]) {
					if ($return[$table_name]) { // multi column primary key
						unset($return[$table_name]);
						break;
					}
					$return[$table_name] = $field;
				}
			}
		}
	}
	return $return;
}

/** Print SQL <textarea> tag
* @param string|list<array{string}> $value
*/
function textarea($name, $value, $rows = 10, $cols = 80) {
	echo "<textarea name='" . h($name) . "' rows='$rows' cols='$cols' class='sqlarea jush-" . JUSH . "' spellcheck='false' wrap='off'>";
	if (is_array($value)) {
		foreach ($value as $val) { // not implode() to save memory
			echo h($val[0]) . "\n\n\n"; // $val == array($query, $time, $elapsed)
		}
	} else {
		echo h($value);
	}
	echo "</textarea>";
}

/** Generate HTML <select> or <input> if $options are empty
* @param string[] $options
*/
function select_input($attrs, array $options, $value = "", $onchange = "", $placeholder = "") {
	$tag = ($options ? "select" : "input");
	return "<$tag$attrs" . ($options
		? "><option value=''>$placeholder" . optionlist($options, $value, true) . "</select>"
		: " size='10' value='" . h($value) . "' placeholder='$placeholder'>"
	) . ($onchange ? script("qsl('$tag').onchange = $onchange;", "") : ""); //! use oninput for input
}

/** Print one row in JSON object
* @param string $key or "" to close the object
* @param string|int $val
*/
function json_row($key, $val = null, $escape = true) {
	static $first = true;
	if ($first) {
		echo "{";
	}
	if ($key != "") {
		echo ($first ? "" : ",") . "\n\t\"" . addcslashes($key, "\r\n\t\"\\/") . '": ' . ($val !== null ? ($escape ? '"' . addcslashes($val, "\r\n\"\\/") . '"' : $val) : 'null');
		$first = false;
	} else {
		echo "\n}\n";
		$first = true;
	}
}

/** Print table columns for type edit
* @param Field $field
* @param list<string> $collations
* @param string[] $foreign_keys
* @param list<string> $extra_types extra types to prepend
*/
function edit_type($key, array $field, array $collations, array $foreign_keys = array(), array $extra_types = array()) {
	$type = $field["type"];
	echo "<td><select name='" . h($key) . "[type]' class='type' aria-labelledby='label-type'>";
	if ($type && !array_key_exists($type, driver()->types()) && !isset($foreign_keys[$type]) && !in_array($type, $extra_types)) {
		$extra_types[] = $type;
	}
	$structured_types = driver()->structuredTypes();
	if ($foreign_keys) {
		$structured_types['Foreign keys'] = $foreign_keys;
	}
	echo optionlist(array_merge($extra_types, $structured_types), $type);
	echo "</select><td>";
	echo "<input name='" . h($key) . "[length]' value='" . h($field["length"]) . "' size='3'"
		. (!$field["length"] && preg_match('~var(char|binary)$~', $type) ? " class='required'" : "") //! type="number" with enabled JavaScript
		. " aria-labelledby='label-length'>";
	echo "<td class='options'>";
	echo ($collations
		? "<input list='collations' name='" . h($key) . "[collation]'" . (preg_match('~(char|text|enum|set)$~', $type) ? "" : " class='hidden'") . " value='" . h($field["collation"]) . "' placeholder='(" . 'collation' . ")'>"
		: ''
	);
	echo (driver()->unsigned ? "<select name='" . h($key) . "[unsigned]'" . (!$type || preg_match(number_type(), $type) ? "" : " class='hidden'") . '><option>' . optionlist(driver()->unsigned, $field["unsigned"]) . '</select>' : '');
	echo (isset($field['on_update']) ? "<select name='" . h($key) . "[on_update]'" . (preg_match('~timestamp|datetime~', $type) ? "" : " class='hidden'") . '>'
		. optionlist(array("" => "(" . 'ON UPDATE' . ")", "CURRENT_TIMESTAMP"), (preg_match('~^CURRENT_TIMESTAMP~i', $field["on_update"]) ? "CURRENT_TIMESTAMP" : $field["on_update"]))
		. '</select>' : ''
	);
	echo ($foreign_keys
		? "<select name='" . h($key) . "[on_delete]'" . (preg_match("~`~", $type) ? "" : " class='hidden'") . "><option value=''>(" . 'ON DELETE' . ")" . optionlist(explode("|", driver()->onActions), $field["on_delete"]) . "</select> "
		: " " // space for IE
	);
}

/** Filter length value including enums */
function process_length($length) {
	$enum_length = driver()->enumLength;
	return (preg_match("~^\\s*\\(?\\s*$enum_length(?:\\s*,\\s*$enum_length)*+\\s*\\)?\\s*\$~", $length) && preg_match_all("~$enum_length~", $length, $matches)
		? "(" . implode(",", $matches[0]) . ")"
		: preg_replace('~^[0-9].*~', '(\0)', preg_replace('~[^-0-9,+()[\]]~', '', $length))
	);
}

/** Create SQL string from field type
* @param FieldType $field
*/
function process_type(array $field, $collate = "COLLATE") {
	return " $field[type]"
		. process_length($field["length"])
		. (preg_match(number_type(), $field["type"]) && in_array($field["unsigned"], driver()->unsigned) ? " $field[unsigned]" : "")
		. (preg_match('~char|text|enum|set~', $field["type"]) && $field["collation"] ? " $collate " . (JUSH == "mssql" ? $field["collation"] : q($field["collation"])) : "")
	;
}

/** Create SQL string from field
* @param Field $field basic field information
* @param Field $type_field information about field type
* @return list<string> ["field", "type", "NULL", "DEFAULT", "ON UPDATE", "COMMENT", "AUTO_INCREMENT"]
*/
function process_field(array $field, array $type_field) {
	// MariaDB exports CURRENT_TIMESTAMP as a function.
	if ($field["on_update"]) {
		$field["on_update"] = str_ireplace("current_timestamp()", "CURRENT_TIMESTAMP", $field["on_update"]);
	}
	return array(
		idf_escape(trim($field["field"])),
		process_type($type_field),
		($field["null"] ? " NULL" : " NOT NULL"), // NULL for timestamp
		default_value($field),
		(preg_match('~timestamp|datetime~', $field["type"]) && $field["on_update"] ? " ON UPDATE $field[on_update]" : ""),
		(support("comment") && $field["comment"] != "" ? " COMMENT " . q($field["comment"]) : ""),
		($field["auto_increment"] ? auto_increment() : null),
	);
}

/** Get default value clause
* @param Field $field
*/
function default_value(array $field) {
	if ($field["default"] === null) {
		return "";
	}
	$default = str_replace("\r", "", $field["default"]);
	$generated = $field["generated"];
	return (in_array($generated, driver()->generated)
		? (JUSH == "mssql" ? " AS ($default)" . ($generated == "VIRTUAL" ? "" : " $generated") : " GENERATED ALWAYS AS ($default) $generated")
		: " DEFAULT " . (!preg_match('~^GENERATED ~i', $default) && (preg_match('~char|binary|text|json|enum|set~', $field["type"]) || preg_match('~^(?![a-z])~i', $default))
			? (JUSH == "sql" && preg_match('~text|json~', $field["type"]) ? "(" . q($default) . ")" : q($default)) // MySQL requires () around default value of text column
			: str_ireplace("current_timestamp()", "CURRENT_TIMESTAMP", (JUSH == "sqlite" ? "($default)" : $default))
		)
	);
}

/** Get type class to use in CSS
* @return string|void class=''
*/
function type_class($type) {
	foreach (
		array(
			'char' => 'text',
			'date' => 'time|year',
			'binary' => 'blob',
			'enum' => 'set',
		) as $key => $val
	) {
		if (preg_match("~$key|$val~", $type)) {
			return " class='$key'";
		}
	}
}

/** Print table interior for fields editing
* @param (Field|RoutineField)[] $fields
* @param list<string> $collations
* @param 'TABLE'|'PROCEDURE' $type
* @param string[] $foreign_keys
*/
function edit_fields(array $fields, array $collations, $type = "TABLE", array $foreign_keys = array()) {
	$fields = array_values($fields);
	$default_class = (($_POST ? $_POST["defaults"] : get_setting("defaults")) ? "" : " class='hidden'");
	$comment_class = (($_POST ? $_POST["comments"] : get_setting("comments")) ? "" : " class='hidden'");
	echo "<thead><tr>\n";
	echo ($type == "PROCEDURE" ? "<td>" : "");
	echo "<th id='label-name'>" . ($type == "TABLE" ? 'Column name' : 'Parameter name');
	echo "<td id='label-type'>" . 'Type' . "<textarea id='enum-edit' rows='4' cols='12' wrap='off' style='display: none;'></textarea>" . script("qs('#enum-edit').onblur = editingLengthBlur;");
	echo "<td id='label-length'>" . 'Length';
	echo "<td>" . 'Options'; // no label required, options have their own label
	if ($type == "TABLE") {
		echo "<td id='label-null'>NULL\n";
		echo "<td><input type='radio' name='auto_increment_col' value=''><abbr id='label-ai' title='" . 'Auto Increment' . "'>AI</abbr>";
		echo doc_link(array(
			'sql' => "example-auto-increment.html",
			'mariadb' => "auto_increment/",
			'sqlite' => "autoinc.html",
			'pgsql' => "datatype-numeric.html#DATATYPE-SERIAL",
			'mssql' => "t-sql/statements/create-table-transact-sql-identity-property",
		));
		echo "<td id='label-default'$default_class>" . 'Default value';
		echo (support("comment") ? "<td id='label-comment'$comment_class>" . 'Comment' : "");
	}
	echo "<td>" . icon("plus", "add[" . (support("move_col") ? 0 : count($fields)) . "]", "+", 'Add next');
	echo "</thead>\n<tbody>\n";
	echo script("mixin(qsl('tbody'), {onclick: editingClick, onkeydown: editingKeydown, oninput: editingInput});");
	foreach ($fields as $i => $field) {
		$i++;
		$orig = $field[($_POST ? "orig" : "field")];
		$display = (isset($_POST["add"][$i-1]) || (isset($field["field"]) && !idx($_POST["drop_col"], $i))) && (support("drop_col") || $orig == "");
		echo "<tr" . ($display ? "" : " style='display: none;'") . ">\n";
		echo ($type == "PROCEDURE" ? "<td>" . html_select("fields[$i][inout]", explode("|", driver()->inout), $field["inout"]) : "") . "<th>";
		if ($display) {
			echo "<input name='fields[$i][field]' value='" . h($field["field"]) . "' data-maxlength='64' autocapitalize='off' aria-labelledby='label-name'" . (isset($_POST["add"][$i-1]) ? " autofocus" : "") . ">";
		}
		echo input_hidden("fields[$i][orig]", $orig);
		edit_type("fields[$i]", $field, $collations, $foreign_keys);
		if ($type == "TABLE") {
			echo "<td>" . checkbox("fields[$i][null]", 1, $field["null"], "", "", "block", "label-null");
			echo "<td><label class='block'><input type='radio' name='auto_increment_col' value='$i'" . ($field["auto_increment"] ? " checked" : "") . " aria-labelledby='label-ai'></label>";
			echo "<td$default_class>" . (driver()->generated
				? html_select("fields[$i][generated]", array_merge(array("", "DEFAULT"), driver()->generated), $field["generated"]) . " "
				: checkbox("fields[$i][generated]", 1, $field["generated"], "", "", "", "label-default")
			);
			$attrs = " name='fields[$i][default]' aria-labelledby='label-default'";
			$value = h($field["default"]);
			echo (preg_match('~\n~', $field["default"]) ? "<textarea$attrs rows='2' cols='30' style='vertical-align: bottom;'>\n$value</textarea>" : "<input$attrs value='$value'>"); // \n to preserve the leading newline
			echo (support("comment") ? "<td$comment_class><input name='fields[$i][comment]' value='" . h($field["comment"]) . "' data-maxlength='" . (min_version(5.5) ? 1024 : 255) . "' aria-labelledby='label-comment'>" : "");
		}
		echo "<td>";
		echo (support("move_col") ?
			icon("plus", "add[$i]", "+", 'Add next') . " "
			. icon("up", "up[$i]", "↑", 'Move up') . " "
			. icon("down", "down[$i]", "↓", 'Move down') . " "
		: "");
		echo ($orig == "" || support("drop_col") ? icon("cross", "drop_col[$i]", "x", 'Remove') : "");
	}
}

/** Move fields up and down or add field
* @param Field[] $fields
*/
function process_fields(array &$fields) {
	$offset = 0;
	if ($_POST["up"]) {
		$last = 0;
		foreach ($fields as $key => $field) {
			if (key($_POST["up"]) == $key) {
				unset($fields[$key]);
				array_splice($fields, $last, 0, array($field));
				break;
			}
			if (isset($field["field"])) {
				$last = $offset;
			}
			$offset++;
		}
	} elseif ($_POST["down"]) {
		$found = false;
		foreach ($fields as $key => $field) {
			if (isset($field["field"]) && $found) {
				unset($fields[key($_POST["down"])]);
				array_splice($fields, $offset, 0, array($found));
				break;
			}
			if (key($_POST["down"]) == $key) {
				$found = $field;
			}
			$offset++;
		}
	} elseif ($_POST["add"]) {
		$fields = array_values($fields);
		array_splice($fields, key($_POST["add"]), 0, array(array()));
	} elseif (!$_POST["drop_col"]) {
		return false;
	}
	return true;
}

/** Callback used in routine()
* @param list<string> $match
*/
function normalize_enum(array $match) {
	$val = $match[0];
	return "'" . str_replace("'", "''", addcslashes(stripcslashes(str_replace($val[0] . $val[0], $val[0], substr($val, 1, -1))), '\\')) . "'";
}

/** Issue grant or revoke commands
* @param 'GRANT'|'REVOKE' $grant
* @param list<string> $privileges
* @return Result|bool
*/
function grant($grant, array $privileges, $columns, $on) {
	if (!$privileges) {
		return true;
	}
	if ($privileges == array("ALL PRIVILEGES", "GRANT OPTION")) {
		// can't be granted or revoked together
		return ($grant == "GRANT"
			? queries("$grant ALL PRIVILEGES$on WITH GRANT OPTION")
			: queries("$grant ALL PRIVILEGES$on") && queries("$grant GRANT OPTION$on")
		);
	}
	return queries("$grant " . preg_replace('~(GRANT OPTION)\([^)]*\)~', '\1', implode("$columns, ", $privileges) . $columns) . $on);
}

/** Drop old object and create a new one
* @param string $drop drop old object query
* @param string $create create new object query
* @param string $drop_created drop new object query
* @param string $test create test object query
* @param string $drop_test drop test object query
* @return void redirect on success
*/
function drop_create($drop, $create, $drop_created, $test, $drop_test, $location, $message_drop, $message_alter, $message_create, $old_name, $new_name) {
	if ($_POST["drop"]) {
		query_redirect($drop, $location, $message_drop);
	} elseif ($old_name == "") {
		query_redirect($create, $location, $message_create);
	} elseif ($old_name != $new_name) {
		$created = queries($create);
		queries_redirect($location, $message_alter, $created && queries($drop));
		if ($created) {
			queries($drop_created);
		}
	} else {
		queries_redirect(
			$location,
			$message_alter,
			queries($test) && queries($drop_test) && queries($drop) && queries($create)
		);
	}
}

/** Generate SQL query for creating trigger
* @param Trigger $row
*/
function create_trigger($on, array $row) {
	$timing_event = " $row[Timing] $row[Event]" . (preg_match('~ OF~', $row["Event"]) ? " $row[Of]" : ""); // SQL injection
	return "CREATE TRIGGER "
		. idf_escape($row["Trigger"])
		. (JUSH == "mssql" ? $on . $timing_event : $timing_event . $on)
		. rtrim(" $row[Type]\n$row[Statement]", ";")
		. ";"
	;
}

/** Generate SQL query for creating routine
* @param 'PROCEDURE'|'FUNCTION' $routine
* @param Routine $row
*/
function create_routine($routine, array $row) {
	$set = array();
	$fields = (array) $row["fields"];
	ksort($fields); // enforce fields order
	foreach ($fields as $field) {
		if ($field["field"] != "") {
			$set[] = (preg_match("~^(" . driver()->inout . ")\$~", $field["inout"]) ? "$field[inout] " : "") . idf_escape($field["field"]) . process_type($field, "CHARACTER SET");
		}
	}
	$definition = rtrim($row["definition"], ";");
	return "CREATE $routine "
		. idf_escape(trim($row["name"]))
		. " (" . implode(", ", $set) . ")"
		. ($routine == "FUNCTION" ? " RETURNS" . process_type($row["returns"], "CHARACTER SET") : "")
		. ($row["language"] ? " LANGUAGE $row[language]" : "")
		. (JUSH == "pgsql" ? " AS " . q($definition) : "\n$definition;")
	;
}

/** Remove current user definer from SQL command */
function remove_definer($query) {
	return preg_replace('~^([A-Z =]+) DEFINER=`' . preg_replace('~@(.*)~', '`@`(%|\1)', logged_user()) . '`~', '\1', $query); //! proper escaping of user
}

/** Format foreign key to use in SQL query
* @param ForeignKey $foreign_key
*/
function format_foreign_key(array $foreign_key) {
	$db = $foreign_key["db"];
	$ns = $foreign_key["ns"];
	return " FOREIGN KEY (" . implode(", ", array_map('Adminer\idf_escape', $foreign_key["source"])) . ") REFERENCES "
		. ($db != "" && $db != $_GET["db"] ? idf_escape($db) . "." : "")
		. ($ns != "" && $ns != $_GET["ns"] ? idf_escape($ns) . "." : "")
		. idf_escape($foreign_key["table"])
		. " (" . implode(", ", array_map('Adminer\idf_escape', $foreign_key["target"])) . ")" //! reuse $name - check in older MySQL versions
		. (preg_match("~^(" . driver()->onActions . ")\$~", $foreign_key["on_delete"]) ? " ON DELETE $foreign_key[on_delete]" : "")
		. (preg_match("~^(" . driver()->onActions . ")\$~", $foreign_key["on_update"]) ? " ON UPDATE $foreign_key[on_update]" : "")
		. ($foreign_key["deferrable"] ? " $foreign_key[deferrable]" : "")
	;
}

/** Add a file to TAR
* @param TmpFile $tmp_file
* @return void prints the output
*/
function tar_file($filename, $tmp_file) {
	$return = pack("a100a8a8a8a12a12", $filename, 644, 0, 0, decoct($tmp_file->size), decoct(time()));
	$checksum = 8*32; // space for checksum itself
	for ($i=0; $i < strlen($return); $i++) {
		$checksum += ord($return[$i]);
	}
	$return .= sprintf("%06o", $checksum) . "\0 ";
	echo $return;
	echo str_repeat("\0", 512 - strlen($return));
	$tmp_file->send();
	echo str_repeat("\0", 511 - ($tmp_file->size + 511) % 512);
}

/** Create link to database documentation
* @param string[] $paths JUSH => $path
* @param string $text HTML code
* @return string HTML code
*/
function doc_link(array $paths, $text = "<sup>?</sup>") {
	$server_info = connection()->server_info;
	$version = preg_replace('~^(\d\.?\d).*~s', '\1', $server_info); // two most significant digits
	$urls = array(
		'sql' => "https://dev.mysql.com/doc/refman/$version/en/",
		'sqlite' => "https://www.sqlite.org/",
		'pgsql' => "https://www.postgresql.org/docs/" . (connection()->flavor == 'cockroach' ? "current" : $version) . "/",
		'mssql' => "https://learn.microsoft.com/en-us/sql/",
		'oracle' => "https://www.oracle.com/pls/topic/lookup?ctx=db" . preg_replace('~^.* (\d+)\.(\d+)\.\d+\.\d+\.\d+.*~s', '\1\2', $server_info) . "&id=",
	);
	if (connection()->flavor == 'maria') {
		$urls['sql'] = "https://mariadb.com/kb/en/";
		$paths['sql'] = (isset($paths['mariadb']) ? $paths['mariadb'] : str_replace(".html", "/", $paths['sql']));
	}
	return ($paths[JUSH] ? "<a href='" . h($urls[JUSH] . $paths[JUSH] . (JUSH == 'mssql' ? "?view=sql-server-ver$version" : "")) . "'" . target_blank() . ">$text</a>" : "");
}

/** Compute size of database
* @return string formatted
*/
function db_size($db) {
	if (!connection()->select_db($db)) {
		return "?";
	}
	$return = 0;
	foreach (table_status() as $table_status) {
		$return += $table_status["Data_length"] + $table_status["Index_length"];
	}
	return format_number($return);
}

/** Print SET NAMES if utf8mb4 might be needed */
function set_utf8mb4($create) {
	static $set = false;
	if (!$set && preg_match('~\butf8mb4~i', $create)) { // possible false positive
		$set = true;
		echo "SET NAMES " . charset(connection()) . ";\n\n";
	}
}

?>
<?php
if (isset($_GET["status"])) {
	$_GET["variables"] = $_GET["status"];
}
if (isset($_GET["import"])) {
	$_GET["sql"] = $_GET["import"];
}

if (
	!(DB != ""
		? connection()->select_db(DB)
		: isset($_GET["sql"]) || isset($_GET["dump"]) || isset($_GET["database"]) || isset($_GET["processlist"]) || isset($_GET["privileges"]) || isset($_GET["user"]) || isset($_GET["variables"])
			|| $_GET["script"] == "connect" || $_GET["script"] == "kill"
	)
) {
	if (DB != "" || $_GET["refresh"]) {
		restart_session();
		set_session("dbs", null);
	}
	if (DB != "") {
		header("HTTP/1.1 404 Not Found");
		page_header('Database' . ": " . h(DB), 'Invalid database.', true);
	} else {
		if ($_POST["db"] && !$error) {
			queries_redirect(substr(ME, 0, -1), 'Databases have been dropped.', drop_databases($_POST["db"]));
		}

		page_header('Select database', $error, false);
		echo "<p class='links'>\n";
		foreach (
			array(
				'database' => 'Create database',
				'privileges' => 'Privileges',
				'processlist' => 'Process list',
				'variables' => 'Variables',
				'status' => 'Status',
			) as $key => $val
		) {
			if (support($key)) {
				echo "<a href='" . h(ME) . "$key='>$val</a>\n";
			}
		}
		echo "<p>" . sprintf('%s version: %s through PHP extension %s', get_driver(DRIVER), "<b>" . h(connection()->server_info) . "</b>", "<b>" . connection()->extension . "</b>") . "\n";
		echo "<p>" . sprintf('Logged as: %s', "<b>" . h(logged_user()) . "</b>") . "\n";

		$databases = adminer()->databases();
		if ($databases) {
			$scheme = support("scheme");
			$collations = collations();
			echo "<form action='' method='post'>\n";
			echo "<table class='checkable odds'>\n";
			echo script("mixin(qsl('table'), {onclick: tableClick, ondblclick: partialArg(tableClick, true)});");
			echo "<thead><tr>"
				. (support("database") ? "<td>" : "")
				. "<th>" . 'Database' . (get_session("dbs") !== null ? " - <a href='" . h(ME) . "refresh=1'>" . 'Refresh' . "</a>" : "")
				. "<td>" . 'Collation'
				. "<td>" . 'Tables'
				. "<td>" . 'Size' . " - <a href='" . h(ME) . "dbsize=1'>" . 'Compute' . "</a>" . script("qsl('a').onclick = partial(ajaxSetHtml, '" . js_escape(ME) . "script=connect');", "")
				. "</thead>\n"
			;

			$databases = ($_GET["dbsize"] ? count_tables($databases) : array_flip($databases));
			foreach ($databases as $db => $tables) {
				$root = h(ME) . "db=" . urlencode($db);
				$id = h("Db-" . $db);
				echo "<tr>" . (support("database") ? "<td>" . checkbox("db[]", $db, in_array($db, (array) $_POST["db"]), "", "", "", $id) : "");
				echo "<th><a href='$root' id='$id'>" . h($db) . "</a>";
				$collation = h(db_collation($db, $collations));
				echo "<td>" . (support("database") ? "<a href='$root" . ($scheme ? "&amp;ns=" : "") . "&amp;database=' title='" . 'Alter database' . "'>$collation</a>" : $collation);
				echo "<td align='right'><a href='$root&amp;schema=' id='tables-" . h($db) . "' title='" . 'Database schema' . "'>" . ($_GET["dbsize"] ? $tables : "?") . "</a>";
				echo "<td align='right' id='size-" . h($db) . "'>" . ($_GET["dbsize"] ? db_size($db) : "?");
				echo "\n";
			}

			echo "</table>\n";
			echo (support("database")
				? "<div class='footer'><div>\n"
					. "<fieldset><legend>" . 'Selected' . " <span id='selected'></span></legend><div>\n"
					. input_hidden("all") . script("qsl('input').onclick = function () { selectCount('selected', formChecked(this, /^db/)); };") // used by trCheck()
					. "<input type='submit' name='drop' value='" . 'Drop' . "'>" . confirm() . "\n"
					. "</div></fieldset>\n"
					. "</div></div>\n"
				: ""
			);
			echo input_token();
			echo "</form>\n";
			echo script("tableCheck();");
		}

		if (!empty(adminer()->plugins)) {
			echo "<div class='plugins'>\n";
			echo "<h3>" . 'Loaded plugins' . "</h3>\n<ul>\n";
			foreach (adminer()->plugins as $plugin) {
				$description = (method_exists($plugin, 'description') ? $plugin->description() : "");
				if (!$description) {
					$reflection = new \ReflectionObject($plugin);
					if (preg_match('~^/[\s*]+(.+)~', $reflection->getDocComment(), $match)) {
						$description = $match[1];
					}
				}
				$screenshot = (method_exists($plugin, 'screenshot') ? $plugin->screenshot() : "");
				echo "<li><b>" . get_class($plugin) . "</b>"
					. h($description ? ": $description" : "")
					. ($screenshot ? " (<a href='" . h($screenshot) . "'" . target_blank() . ">" . 'screenshot' . "</a>)" : "")
					. "\n"
				;
			}
			echo "</ul>\n";
			adminer()->pluginsLinks();
			echo "</div>\n";
		}
	}

	page_footer("db");
	exit;
}

if (support("scheme")) {
	if (DB != "" && $_GET["ns"] !== "") {
		if (!isset($_GET["ns"])) {
			redirect(preg_replace('~ns=[^&]*&~', '', ME) . "ns=" . get_schema());
		}
		if (!set_schema($_GET["ns"])) {
			header("HTTP/1.1 404 Not Found");
			page_header('Schema' . ": " . h($_GET["ns"]), 'Invalid schema.', true);
			page_footer("ns");
			exit;
		}
	}
}


adminer()->afterConnect();

?>
<?php
class TmpFile {
	/** @var resource */ private $handler;
	/** @visibility protected(set) */ public $size;

	function __construct() {
		$this->handler = tmpfile();
	}

	function write($contents) {
		$this->size += strlen($contents);
		fwrite($this->handler, $contents);
	}

	function send() {
		fseek($this->handler, 0);
		fpassthru($this->handler);
		fclose($this->handler);
	}
}


if (isset($_GET["select"]) && ($_POST["edit"] || $_POST["clone"]) && !$_POST["save"]) {
	$_GET["edit"] = $_GET["select"];
}
// this is matched by compile.php
if (isset($_GET["callf"])) {
	$_GET["call"] = $_GET["callf"];
}
if (isset($_GET["function"])) {
	$_GET["procedure"] = $_GET["function"];
}

if (isset($_GET["download"])) {
	?>
<?php
$TABLE = $_GET["download"];
$fields = fields($TABLE);
header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=" . friendly_url("$TABLE-" . implode("_", $_GET["where"])) . "." . friendly_url($_GET["field"]));
$select = array(idf_escape($_GET["field"]));
$result = driver()->select($TABLE, $select, array(where($_GET, $fields)), $select);
$row = ($result ? $result->fetch_row() : array());
echo driver()->value($row[0], $fields[$_GET["field"]]);
exit; // don't output footer

} elseif (isset($_GET["table"])) {
	?>
<?php
$TABLE = $_GET["table"];
$fields = fields($TABLE);
if (!$fields) {
	$error = error() ?: 'No tables.';
}
$table_status = table_status1($TABLE);
$name = adminer()->tableName($table_status);

page_header(($fields && is_view($table_status) ? $table_status['Engine'] == 'materialized view' ? 'Materialized view' : 'View' : 'Table') . ": " . ($name != "" ? $name : h($TABLE)), $error);

$rights = array();
foreach ($fields as $key => $field) {
	$rights += $field["privileges"];
}
adminer()->selectLinks($table_status, (isset($rights["insert"]) || !support("table") ? "" : null));

$comment = $table_status["Comment"];
if ($comment != "") {
	echo "<p class='nowrap'>" . 'Comment' . ": " . h($comment) . "\n";
}

if ($fields) {
	adminer()->tableStructurePrint($fields, $table_status);
}

/** Print links to tables
* @param list<string> $tables
*/
function tables_links(array $tables) {
	echo "<ul>\n";
	foreach ($tables as $table) {
		echo "<li><a href='" . h(ME . "table=" . urlencode($table)) . "'>" . h($table) . "</a>";
	}
	echo "</ul>\n";
}

$inherits = driver()->inheritsFrom($TABLE);
if ($inherits) {
	echo "<h3>" . 'Inherits from' . "</h3>\n";
	tables_links($inherits);
}

if (support("indexes") && driver()->supportsIndex($table_status)) {
	echo "<h3 id='indexes'>" . 'Indexes' . "</h3>\n";
	$indexes = indexes($TABLE);
	if ($indexes) {
		adminer()->tableIndexesPrint($indexes, $table_status);
	}
	echo '<p class="links"><a href="' . h(ME) . 'indexes=' . urlencode($TABLE) . '">' . 'Alter indexes' . "</a>\n";
}

if (!is_view($table_status)) {
	if (fk_support($table_status)) {
		echo "<h3 id='foreign-keys'>" . 'Foreign keys' . "</h3>\n";
		$foreign_keys = foreign_keys($TABLE);
		if ($foreign_keys) {
			echo "<table>\n";
			echo "<thead><tr><th>" . 'Source' . "<td>" . 'Target' . "<td>" . 'ON DELETE' . "<td>" . 'ON UPDATE' . "<td></thead>\n";
			foreach ($foreign_keys as $name => $foreign_key) {
				echo "<tr title='" . h($name) . "'>";
				echo "<th><i>" . implode("</i>, <i>", array_map('Adminer\h', $foreign_key["source"])) . "</i>";
				$link = ($foreign_key["db"] != ""
					? preg_replace('~db=[^&]*~', "db=" . urlencode($foreign_key["db"]), ME)
					: ($foreign_key["ns"] != "" ? preg_replace('~ns=[^&]*~', "ns=" . urlencode($foreign_key["ns"]), ME) : ME)
				);
				echo "<td><a href='" . h($link . "table=" . urlencode($foreign_key["table"])) . "'>"
					. ($foreign_key["db"] != "" && $foreign_key["db"] != DB ? "<b>" . h($foreign_key["db"]) . "</b>." : "")
					. ($foreign_key["ns"] != "" && $foreign_key["ns"] != $_GET["ns"] ? "<b>" . h($foreign_key["ns"]) . "</b>." : "")
					. h($foreign_key["table"])
					. "</a>"
				;
				echo "(<i>" . implode("</i>, <i>", array_map('Adminer\h', $foreign_key["target"])) . "</i>)";
				echo "<td>" . h($foreign_key["on_delete"]);
				echo "<td>" . h($foreign_key["on_update"]);
				echo '<td><a href="' . h(ME . 'foreign=' . urlencode($TABLE) . '&name=' . urlencode($name)) . '">' . 'Alter' . '</a>';
				echo "\n";
			}
			echo "</table>\n";
		}
		echo '<p class="links"><a href="' . h(ME) . 'foreign=' . urlencode($TABLE) . '">' . 'Add foreign key' . "</a>\n";
	}

	if (support("check")) {
		echo "<h3 id='checks'>" . 'Checks' . "</h3>\n";
		$check_constraints = driver()->checkConstraints($TABLE);
		if ($check_constraints) {
			echo "<table>\n";
			foreach ($check_constraints as $key => $val) {
				echo "<tr title='" . h($key) . "'>";
				echo "<td><code class='jush-" . JUSH . "'>" . h($val);
				echo "<td><a href='" . h(ME . 'check=' . urlencode($TABLE) . '&name=' . urlencode($key)) . "'>" . 'Alter' . "</a>";
				echo "\n";
			}
			echo "</table>\n";
		}
		echo '<p class="links"><a href="' . h(ME) . 'check=' . urlencode($TABLE) . '">' . 'Create check' . "</a>\n";
	}
}

if (support(is_view($table_status) ? "view_trigger" : "trigger")) {
	echo "<h3 id='triggers'>" . 'Triggers' . "</h3>\n";
	$triggers = triggers($TABLE);
	if ($triggers) {
		echo "<table>\n";
		foreach ($triggers as $key => $val) {
			echo "<tr valign='top'><td>" . h($val[0]) . "<td>" . h($val[1]) . "<th>" . h($key) . "<td><a href='" . h(ME . 'trigger=' . urlencode($TABLE) . '&name=' . urlencode($key)) . "'>" . 'Alter' . "</a>\n";
		}
		echo "</table>\n";
	}
	echo '<p class="links"><a href="' . h(ME) . 'trigger=' . urlencode($TABLE) . '">' . 'Add trigger' . "</a>\n";
}

$inherited = driver()->inheritedTables($TABLE);
if ($inherited) {
	echo "<h3 id='partitions'>" . 'Inherited by' . "</h3>\n";
	$partition = driver()->partitionsInfo($TABLE);
	if ($partition) {
		echo "<p><code class='jush-" . JUSH . "'>BY " . h("$partition[partition_by]($partition[partition])") . "</code>\n";
	}
	tables_links($inherited);
}

} elseif (isset($_GET["schema"])) {
	?>
<?php
page_header('Database schema', "", array(), h(DB . ($_GET["ns"] ? ".$_GET[ns]" : "")));

/** @var array{float, float}[] */
$table_pos = array();
$table_pos_js = array();
$SCHEMA = ($_GET["schema"] ?: $_COOKIE["adminer_schema-" . str_replace(".", "_", DB)]); // $_COOKIE["adminer_schema"] was used before 3.2.0 //! ':' in table name
preg_match_all('~([^:]+):([-0-9.]+)x([-0-9.]+)(_|$)~', $SCHEMA, $matches, PREG_SET_ORDER);
foreach ($matches as $i => $match) {
	$table_pos[$match[1]] = array($match[2], $match[3]);
	$table_pos_js[] = "\n\t'" . js_escape($match[1]) . "': [ $match[2], $match[3] ]";
}

$top = 0;
$base_left = -1;
/** @var array{fields:Field[], pos:array{float, float}, references:string[][][]}[] */
$schema = array(); // table => array("fields" => array(name => field), "pos" => array(top, left), "references" => array(table => array(left => array(source, target))))
$referenced = array(); // target_table => array(table => array(left => target_column))
/** @var array<numeric-string, bool> */
$lefts = array();
$all_fields = driver()->allFields();
foreach (table_status('', true) as $table => $table_status) {
	if (is_view($table_status)) {
		continue;
	}
	$pos = 0;
	$schema[$table]["fields"] = array();
	foreach ($all_fields[$table] as $field) {
		$pos += 1.25;
		$field["pos"] = $pos;
		$schema[$table]["fields"][$field["field"]] = $field;
	}
	$schema[$table]["pos"] = ($table_pos[$table] ?: array($top, 0));
	foreach (adminer()->foreignKeys($table) as $val) {
		if (!$val["db"]) {
			$left = $base_left;
			if (idx($table_pos[$table], 1) || idx($table_pos[$val["table"]], 1)) {
				$left = min(idx($table_pos[$table], 1, 0), idx($table_pos[$val["table"]], 1, 0)) - 1;
			} else {
				$base_left -= .1;
			}
			while ($lefts[(string) $left]) {
				// find free $left
				$left -= .0001;
			}
			$schema[$table]["references"][$val["table"]][(string) $left] = array($val["source"], $val["target"]);
			$referenced[$val["table"]][$table][(string) $left] = $val["target"];
			$lefts[(string) $left] = true;
		}
	}
	$top = max($top, $schema[$table]["pos"][0] + 2.5 + $pos);
}

?>
<div id="schema" style="height: <?php echo $top; ?>em;">
<script<?php echo nonce(); ?>>
qs('#schema').onselectstart = () => false;
const tablePos = {<?php echo implode(",", $table_pos_js) . "\n"; ?>};
const em = qs('#schema').offsetHeight / <?php echo $top; ?>;
document.onmousemove = schemaMousemove;
document.onmouseup = partialArg(schemaMouseup, '<?php echo js_escape(DB); ?>');
</script>
<?php
foreach ($schema as $name => $table) {
	echo "<div class='table' style='top: " . $table["pos"][0] . "em; left: " . $table["pos"][1] . "em;'>";
	echo '<a href="' . h(ME) . 'table=' . urlencode($name) . '"><b>' . h($name) . "</b></a>";
	echo script("qsl('div').onmousedown = schemaMousedown;");

	foreach ($table["fields"] as $field) {
		$val = '<span' . type_class($field["type"]) . ' title="' . h($field["type"] . ($field["length"] ? "($field[length])" : "") . ($field["null"] ? " NULL" : '')) . '">' . h($field["field"]) . '</span>';
		echo "<br>" . ($field["primary"] ? "<i>$val</i>" : $val);
	}

	foreach ((array) $table["references"] as $target_name => $refs) {
		foreach ($refs as $left => $ref) {
			$left1 = $left - idx($table_pos[$name], 1);
			$i = 0;
			foreach ($ref[0] as $source) {
				echo "\n<div class='references' title='" . h($target_name) . "' id='refs$left-" . ($i++) . "' style='left: $left1" . "em; top: " . $table["fields"][$source]["pos"] . "em; padding-top: .5em;'>"
					. "<div style='border-top: 1px solid gray; width: " . (-$left1) . "em;'></div></div>"
				;
			}
		}
	}

	foreach ((array) $referenced[$name] as $target_name => $refs) {
		foreach ($refs as $left => $columns) {
			$left1 = $left - idx($table_pos[$name], 1);
			$i = 0;
			foreach ($columns as $target) {
				echo "\n<div class='references arrow' title='" . h($target_name) . "' id='refd$left-" . ($i++) . "' style='left: $left1" . "em; top: " . $table["fields"][$target]["pos"] . "em;'>"
					. "<div style='height: .5em; border-bottom: 1px solid gray; width: " . (-$left1) . "em;'></div>"
					. "</div>"
				;
			}
		}
	}

	echo "\n</div>\n";
}

foreach ($schema as $name => $table) {
	foreach ((array) $table["references"] as $target_name => $refs) {
		foreach ($refs as $left => $ref) {
			$min_pos = $top;
			$max_pos = -10;
			foreach ($ref[0] as $key => $source) {
				$pos1 = $table["pos"][0] + $table["fields"][$source]["pos"];
				$pos2 = $schema[$target_name]["pos"][0] + $schema[$target_name]["fields"][$ref[1][$key]]["pos"];
				$min_pos = min($min_pos, $pos1, $pos2);
				$max_pos = max($max_pos, $pos1, $pos2);
			}
			echo "<div class='references' id='refl$left' style='left: $left" . "em; top: $min_pos" . "em; padding: .5em 0;'><div style='border-right: 1px solid gray; margin-top: 1px; height: " . ($max_pos - $min_pos) . "em;'></div></div>\n";
		}
	}
}
?>
</div>
<p class="links"><a href="<?php echo h(ME . "schema=" . urlencode($SCHEMA)); ?>" id="schema-link">Permanent link</a>
<?php
} elseif (isset($_GET["dump"])) {
	?>
<?php
$TABLE = $_GET["dump"];

if ($_POST && !$error) {
	save_settings(
		array_intersect_key($_POST, array_flip(array("output", "format", "db_style", "types", "routines", "events", "table_style", "auto_increment", "triggers", "data_style"))),
		"adminer_export"
	);
	$tables = array_flip((array) $_POST["tables"]) + array_flip((array) $_POST["data"]);
	$ext = dump_headers(
		(count($tables) == 1 ? key($tables) : DB),
		(DB == "" || count($tables) > 1)
	);
	$is_sql = preg_match('~sql~', $_POST["format"]);

	if ($is_sql) {
		echo "-- Adminer " . VERSION . " " . get_driver(DRIVER) . " " . str_replace("\n", " ", connection()->server_info) . " dump\n\n";
		if (JUSH == "sql") {
			echo "SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
" . ($_POST["data_style"] ? "SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
" : "") . "
";
			connection()->query("SET time_zone = '+00:00'");
			connection()->query("SET sql_mode = ''");
		}
	}

	$style = $_POST["db_style"];
	$databases = array(DB);
	if (DB == "") {
		$databases = $_POST["databases"];
		if (is_string($databases)) {
			$databases = explode("\n", rtrim(str_replace("\r", "", $databases), "\n"));
		}
	}

	foreach ((array) $databases as $db) {
		adminer()->dumpDatabase($db);
		if (connection()->select_db($db)) {
			if ($is_sql) {
				if ($style) {
					echo use_sql($db, $style) . ";\n\n";
				}
				$out = "";

				if ($_POST["types"]) {
					foreach (types() as $id => $type) {
						$enums = type_values($id);
						if ($enums) {
							$out .= ($style != 'DROP+CREATE' ? "DROP TYPE IF EXISTS " . idf_escape($type) . ";;\n" : "") . "CREATE TYPE " . idf_escape($type) . " AS ENUM ($enums);\n\n";
						} else {
							//! https://github.com/postgres/postgres/blob/REL_17_4/src/bin/pg_dump/pg_dump.c#L10846
							$out .= "-- Could not export type $type\n\n";
						}
					}
				}

				if ($_POST["routines"]) {
					foreach (routines() as $row) {
						$name = $row["ROUTINE_NAME"];
						$routine = $row["ROUTINE_TYPE"];
						$create = create_routine($routine, array("name" => $name) + routine($row["SPECIFIC_NAME"], $routine));
						set_utf8mb4($create);
						$out .= ($style != 'DROP+CREATE' ? "DROP $routine IF EXISTS " . idf_escape($name) . ";;\n" : "") . "$create;\n\n";
					}
				}

				if ($_POST["events"]) {
					foreach (get_rows("SHOW EVENTS", null, "-- ") as $row) {
						$create = remove_definer(get_val("SHOW CREATE EVENT " . idf_escape($row["Name"]), 3));
						set_utf8mb4($create);
						$out .= ($style != 'DROP+CREATE' ? "DROP EVENT IF EXISTS " . idf_escape($row["Name"]) . ";;\n" : "") . "$create;;\n\n";
					}
				}

				echo ($out && JUSH == 'sql' ? "DELIMITER ;;\n\n$out" . "DELIMITER ;\n\n" : $out);
			}

			if ($_POST["table_style"] || $_POST["data_style"]) {
				$views = array();
				foreach (table_status('', true) as $name => $table_status) {
					$table = (DB == "" || in_array($name, (array) $_POST["tables"]));
					$data = (DB == "" || in_array($name, (array) $_POST["data"]));
					if ($table || $data) {
						$tmp_file = null;
						if ($ext == "tar") {
							$tmp_file = new TmpFile;
							ob_start(array($tmp_file, 'write'), 1e5);
						}

						adminer()->dumpTable($name, ($table ? $_POST["table_style"] : ""), (is_view($table_status) ? 2 : 0));
						if (is_view($table_status)) {
							$views[] = $name;
						} elseif ($data) {
							$fields = fields($name);
							adminer()->dumpData($name, $_POST["data_style"], "SELECT *" . convert_fields($fields, $fields) . " FROM " . table($name));
						}
						if ($is_sql && $_POST["triggers"] && $table && ($triggers = trigger_sql($name))) {
							echo "\nDELIMITER ;;\n$triggers\nDELIMITER ;\n";
						}

						if ($ext == "tar") {
							ob_end_flush();
							tar_file((DB != "" ? "" : "$db/") . "$name.csv", $tmp_file);
						} elseif ($is_sql) {
							echo "\n";
						}
					}
				}

				// add FKs after creating tables (except in MySQL which uses SET FOREIGN_KEY_CHECKS=0)
				if (function_exists('Adminer\foreign_keys_sql')) {
					foreach (table_status('', true) as $name => $table_status) {
						$table = (DB == "" || in_array($name, (array) $_POST["tables"]));
						if ($table && !is_view($table_status)) {
							echo foreign_keys_sql($name);
						}
					}
				}

				foreach ($views as $view) {
					adminer()->dumpTable($view, $_POST["table_style"], 1);
				}

				if ($ext == "tar") {
					echo pack("x512");
				}
			}
		}
	}

	adminer()->dumpFooter();
	exit;
}

page_header('Export', $error, ($_GET["export"] != "" ? array("table" => $_GET["export"]) : array()), h(DB));
?>

<form action="" method="post">
<table class="layout">
<?php
$db_style = array('', 'USE', 'DROP+CREATE', 'CREATE');
$table_style = array('', 'DROP+CREATE', 'CREATE');
$data_style = array('', 'TRUNCATE+INSERT', 'INSERT');
if (JUSH == "sql") { //! use insertUpdate() in all drivers
	$data_style[] = 'INSERT+UPDATE';
}
$row = get_settings("adminer_export");
if (!$row) {
	$row = array("output" => "text", "format" => "sql", "db_style" => (DB != "" ? "" : "CREATE"), "table_style" => "DROP+CREATE", "data_style" => "INSERT");
}
if (!isset($row["events"])) { // backwards compatibility
	$row["routines"] = $row["events"] = ($_GET["dump"] == "");
	$row["triggers"] = $row["table_style"];
}

echo "<tr><th>" . 'Output' . "<td>" . html_radios("output", adminer()->dumpOutput(), $row["output"]) . "\n";

echo "<tr><th>" . 'Format' . "<td>" . html_radios("format", adminer()->dumpFormat(), $row["format"]) . "\n";

echo (JUSH == "sqlite" ? "" : "<tr><th>" . 'Database' . "<td>" . html_select('db_style', $db_style, $row["db_style"])
	. (support("type") ? checkbox("types", 1, $row["types"], 'User types') : "")
	. (support("routine") ? checkbox("routines", 1, $row["routines"], 'Routines') : "")
	. (support("event") ? checkbox("events", 1, $row["events"], 'Events') : "")
);

echo "<tr><th>" . 'Tables' . "<td>" . html_select('table_style', $table_style, $row["table_style"])
	. checkbox("auto_increment", 1, $row["auto_increment"], 'Auto Increment')
	. (support("trigger") ? checkbox("triggers", 1, $row["triggers"], 'Triggers') : "")
;

echo "<tr><th>" . 'Data' . "<td>" . html_select('data_style', $data_style, $row["data_style"]);
?>
</table>
<p><input type="submit" value="Export">
<?php echo input_token(); ?>

<table>
<?php
echo script("qsl('table').onclick = dumpClick;");
$prefixes = array();
if (DB != "") {
	$checked = ($TABLE != "" ? "" : " checked");
	echo "<thead><tr>";
	echo "<th style='text-align: left;'><label class='block'><input type='checkbox' id='check-tables'$checked>" . 'Tables' . "</label>" . script("qs('#check-tables').onclick = partial(formCheck, /^tables\\[/);", "");
	echo "<th style='text-align: right;'><label class='block'>" . 'Data' . "<input type='checkbox' id='check-data'$checked></label>" . script("qs('#check-data').onclick = partial(formCheck, /^data\\[/);", "");
	echo "</thead>\n";

	$views = "";
	$tables_list = tables_list();
	foreach ($tables_list as $name => $type) {
		$prefix = preg_replace('~_.*~', '', $name);
		$checked = ($TABLE == "" || $TABLE == (substr($TABLE, -1) == "%" ? "$prefix%" : $name)); //! % may be part of table name
		$print = "<tr><td>" . checkbox("tables[]", $name, $checked, $name, "", "block");
		if ($type !== null && !preg_match('~table~i', $type)) {
			$views .= "$print\n";
		} else {
			echo "$print<td align='right'><label class='block'><span id='Rows-" . h($name) . "'></span>" . checkbox("data[]", $name, $checked) . "</label>\n";
		}
		$prefixes[$prefix]++;
	}
	echo $views;

	if ($tables_list) {
		echo script("ajaxSetHtml('" . js_escape(ME) . "script=db');");
	}

} else {
	echo "<thead><tr><th style='text-align: left;'>";
	echo "<label class='block'><input type='checkbox' id='check-databases'" . ($TABLE == "" ? " checked" : "") . ">" . 'Database' . "</label>";
	echo script("qs('#check-databases').onclick = partial(formCheck, /^databases\\[/);", "");
	echo "</thead>\n";
	$databases = adminer()->databases();
	if ($databases) {
		foreach ($databases as $db) {
			if (!information_schema($db)) {
				$prefix = preg_replace('~_.*~', '', $db);
				echo "<tr><td>" . checkbox("databases[]", $db, $TABLE == "" || $TABLE == "$prefix%", $db, "", "block") . "\n";
				$prefixes[$prefix]++;
			}
		}
	} else {
		echo "<tr><td><textarea name='databases' rows='10' cols='20'></textarea>";
	}
}
?>
</table>
</form>
<?php
$first = true;
foreach ($prefixes as $key => $val) {
	if ($key != "" && $val > 1) {
		echo ($first ? "<p>" : " ") . "<a href='" . h(ME) . "dump=" . urlencode("$key%") . "'>" . h($key) . "</a>";
		$first = false;
	}
}

} elseif (isset($_GET["privileges"])) {
	?>
<?php
page_header('Privileges');

echo '<p class="links"><a href="' . h(ME) . 'user=">' . 'Create user' . "</a>";

$result = connection()->query("SELECT User, Host FROM mysql." . (DB == "" ? "user" : "db WHERE " . q(DB) . " LIKE Db") . " ORDER BY Host, User");
$grant = $result;
if (!$result) {
	// list logged user, information_schema.USER_PRIVILEGES lists just the current user too
	$result = connection()->query("SELECT SUBSTRING_INDEX(CURRENT_USER, '@', 1) AS User, SUBSTRING_INDEX(CURRENT_USER, '@', -1) AS Host");
}

echo "<form action=''><p>\n";
hidden_fields_get();
echo input_hidden("db", DB);
echo ($grant ? "" : input_hidden("grant"));
echo "<table class='odds'>\n";
echo "<thead><tr><th>" . 'Username' . "<th>" . 'Server' . "<th></thead>\n";

while ($row = $result->fetch_assoc()) {
	echo '<tr><td>' . h($row["User"]) . "<td>" . h($row["Host"]) . '<td><a href="' . h(ME . 'user=' . urlencode($row["User"]) . '&host=' . urlencode($row["Host"])) . '">' . 'Edit' . "</a>\n";
}

if (!$grant || DB != "") {
	echo "<tr><td><input name='user' autocapitalize='off'><td><input name='host' value='localhost' autocapitalize='off'><td><input type='submit' value='" . 'Edit' . "'>\n";
}

echo "</table>\n";
echo "</form>\n";

} elseif (isset($_GET["sql"])) {
	?>
<?php
if (!$error && $_POST["export"]) {
	save_settings(array("output" => $_POST["output"], "format" => $_POST["format"]), "adminer_import");
	dump_headers("sql");
	if ($_POST["format"] == "sql") {
		echo "$_POST[query]\n";
	} else {
		adminer()->dumpTable("", "");
		adminer()->dumpData("", "table", $_POST["query"]);
		adminer()->dumpFooter();
	}
	exit;
}

restart_session();
$history_all = &get_session("queries");
$history = &$history_all[DB];
if (!$error && $_POST["clear"]) {
	$history = array();
	redirect(remove_from_uri("history"));
}
stop_session();

page_header((isset($_GET["import"]) ? 'Import' : 'SQL command'), $error);
$line_comment = '--' . (JUSH == 'sql' ? ' ' : '');

if (!$error && $_POST) {
	$fp = false;
	if (!isset($_GET["import"])) {
		$query = $_POST["query"];
	} elseif ($_POST["webfile"]) {
		$sql_file_path = adminer()->importServerPath();
		$fp = @fopen((file_exists($sql_file_path)
			? $sql_file_path
			: "compress.zlib://$sql_file_path.gz"
		), "rb");
		$query = ($fp ? fread($fp, 1e6) : false);
	} else {
		$query = get_file("sql_file", true, ";");
	}

	if (is_string($query)) { // get_file() returns error as number, fread() as false
		if (function_exists('memory_get_usage') && ($memory_limit = ini_bytes("memory_limit")) != "-1") {
			@ini_set("memory_limit", max($memory_limit, strval(2 * strlen($query) + memory_get_usage() + 8e6))); // @ - may be disabled, 2 - substr and trim, 8e6 - other variables
		}

		if ($query != "" && strlen($query) < 1e6) { // don't add big queries
			$q = $query . (preg_match("~;[ \t\r\n]*\$~", $query) ? "" : ";"); //! doesn't work with DELIMITER |
			if (!$history || first(end($history)) != $q) { // no repeated queries
				restart_session();
				$history[] = array($q, time()); //! add elapsed time
				set_session("queries", $history_all); // required because reference is unlinked by stop_session()
				stop_session();
			}
		}

		$space = "(?:\\s|/\\*[\s\S]*?\\*/|(?:#|$line_comment)[^\n]*\n?|--\r?\n)";
		$delimiter = ";";
		$offset = 0;
		$empty = true;
		$connection2 = connect(); // connection for exploring indexes and EXPLAIN (to not replace FOUND_ROWS()) //! PDO - silent error
		if ($connection2 && DB != "") {
			$connection2->select_db(DB);
			if ($_GET["ns"] != "") {
				set_schema($_GET["ns"], $connection2);
			}
		}
		$commands = 0;
		$errors = array();
		$parse = '[\'"' . (JUSH == "sql" ? '`#' : (JUSH == "sqlite" ? '`[' : (JUSH == "mssql" ? '[' : ''))) . ']|/\*|' . $line_comment . '|$' . (JUSH == "pgsql" ? '|\$([a-zA-Z]\w*)?\$' : '');
		$total_start = microtime(true);
		$adminer_export = get_settings("adminer_import"); // this doesn't offer SQL export so we match the import/export style at select

		while ($query != "") {
			if (!$offset && preg_match("~^$space*+DELIMITER\\s+(\\S+)~i", $query, $match)) {
				$delimiter = preg_quote($match[1]);
				$query = substr($query, strlen($match[0]));
			} elseif (!$offset && JUSH == 'pgsql' && preg_match("~^($space*+COPY\\s+)[^;]+\\s+FROM\\s+stdin;~i", $query, $match)) {
				$delimiter = "\n\\\\\\.\r?\n";
				$offset = strlen($match[0]);
			} else {
				preg_match("($delimiter\\s*|$parse)", $query, $match, PREG_OFFSET_CAPTURE, $offset); // always matches
				list($found, $pos) = $match[0];
				if (!$found && $fp && !feof($fp)) {
					$query .= fread($fp, 1e5);
				} else {
					if (!$found && rtrim($query) == "") {
						break;
					}
					$offset = $pos + strlen($found);

					if ($found && !preg_match("(^$delimiter)", $found)) { // find matching quote or comment end
						$c_style_escapes = driver()->hasCStyleEscapes() || (JUSH == "pgsql" && ($pos > 0 && strtolower($query[$pos - 1]) == "e"));

						$pattern =
							($found == '/*' ? '\*/' :
							($found == '[' ? ']' :
							(preg_match("~^$line_comment|^#~", $found) ? "\n" :
							preg_quote($found) . ($c_style_escapes ? '|\\\\.' : ''))))
						;

						while (preg_match("($pattern|\$)s", $query, $match, PREG_OFFSET_CAPTURE, $offset)) {
							$s = $match[0][0];
							if (!$s && $fp && !feof($fp)) {
								$query .= fread($fp, 1e5);
							} else {
								$offset = $match[0][1] + strlen($s);
								if (!$s || $s[0] != "\\") {
									break;
								}
							}
						}

					} else { // end of a query
						$empty = false;
						$q = substr($query, 0, $pos + ($delimiter[0] == "\n" ? 3 : 0)); // 3 - pass "\n\\." to PostgreSQL COPY
						$commands++;
						$print = "<pre id='sql-$commands'><code class='jush-" . JUSH . "'>" . adminer()->sqlCommandQuery($q) . "</code></pre>\n";
						if (JUSH == "sqlite" && preg_match("~^$space*+ATTACH\\b~i", $q, $match)) {
							// PHP doesn't support setting SQLITE_LIMIT_ATTACHED
							echo $print;
							echo "<p class='error'>" . 'ATTACH queries are not supported.' . "\n";
							$errors[] = " <a href='#sql-$commands'>$commands</a>";
							if ($_POST["error_stops"]) {
								break;
							}
						} else {
							if (!$_POST["only_errors"]) {
								echo $print;
								ob_flush();
								flush(); // can take a long time - show the running query
							}
							$start = microtime(true);
							//! don't allow changing of character_set_results, convert encoding of displayed query
							if (connection()->multi_query($q) && $connection2 && preg_match("~^$space*+USE\\b~i", $q)) {
								$connection2->query($q);
							}

							do {
								$result = connection()->store_result();

								if (connection()->error) {
									echo ($_POST["only_errors"] ? $print : "");
									echo "<p class='error'>" . 'Error in query' . (connection()->errno ? " (" . connection()->errno . ")" : "") . ": " . error() . "\n";
									$errors[] = " <a href='#sql-$commands'>$commands</a>";
									if ($_POST["error_stops"]) {
										break 2;
									}

								} else {
									$time = " <span class='time'>(" . format_time($start) . ")</span>"
										. (strlen($q) < 1000 ? " <a href='" . h(ME) . "sql=" . urlencode(trim($q)) . "'>" . 'Edit' . "</a>" : "") // 1000 - maximum length of encoded URL in IE is 2083 characters
									;
									$affected = connection()->affected_rows; // getting warnings overwrites this
									$warnings = ($_POST["only_errors"] ? "" : driver()->warnings());
									$warnings_id = "warnings-$commands";
									if ($warnings) {
										$time .= ", <a href='#$warnings_id'>" . 'Warnings' . "</a>" . script("qsl('a').onclick = partial(toggle, '$warnings_id');", "");
									}
									$explain = null;
									$orgtables = null;
									$explain_id = "explain-$commands";
									if (is_object($result)) {
										$limit = $_POST["limit"];
										$orgtables = print_select_result($result, $connection2, array(), $limit);
										if (!$_POST["only_errors"]) {
											echo "<form action='' method='post'>\n";
											$num_rows = $result->num_rows;
											echo "<p class='sql-footer'>" . ($num_rows ? ($limit && $num_rows > $limit ? sprintf('%d / ', $limit) : "") . lang_format(array('%d row', '%d rows'), $num_rows) : "");
											echo $time;
											if ($connection2 && preg_match("~^($space|\\()*+SELECT\\b~i", $q) && ($explain = explain($connection2, $q))) {
												echo ", <a href='#$explain_id'>Explain</a>" . script("qsl('a').onclick = partial(toggle, '$explain_id');", "");
											}
											$id = "export-$commands";
											echo ", <a href='#$id'>" . 'Export' . "</a>" . script("qsl('a').onclick = partial(toggle, '$id');", "") . "<span id='$id' class='hidden'>: "
												. html_select("output", adminer()->dumpOutput(), $adminer_export["output"]) . " "
												. html_select("format", adminer()->dumpFormat(), $adminer_export["format"])
												. input_hidden("query", $q)
												. "<input type='submit' name='export' value='" . 'Export' . "'>" . input_token() . "</span>\n"
												. "</form>\n"
											;
										}

									} else {
										if (preg_match("~^$space*+(CREATE|DROP|ALTER)$space++(DATABASE|SCHEMA)\\b~i", $q)) {
											restart_session();
											set_session("dbs", null); // clear cache
											stop_session();
										}
										if (!$_POST["only_errors"]) {
											echo "<p class='message' title='" . h(connection()->info) . "'>" . lang_format(array('Query executed OK, %d row affected.', 'Query executed OK, %d rows affected.'), $affected) . "$time\n";
										}
									}
									echo ($warnings ? "<div id='$warnings_id' class='hidden'>\n$warnings</div>\n" : "");
									if ($explain) {
										echo "<div id='$explain_id' class='hidden explain'>\n";
										print_select_result($explain, $connection2, $orgtables);
										echo "</div>\n";
									}
								}

								$start = microtime(true);
							} while (connection()->next_result());
						}

						$query = substr($query, $offset);
						$offset = 0;
					}

				}
			}
		}

		if ($empty) {
			echo "<p class='message'>" . 'No commands to execute.' . "\n";
		} elseif ($_POST["only_errors"]) {
			echo "<p class='message'>" . lang_format(array('%d query executed OK.', '%d queries executed OK.'), $commands - count($errors));
			echo " <span class='time'>(" . format_time($total_start) . ")</span>\n";
		} elseif ($errors && $commands > 1) {
			echo "<p class='error'>" . 'Error in query' . ": " . implode("", $errors) . "\n";
		}
		//! MS SQL - SET SHOWPLAN_ALL OFF

	} else {
		echo "<p class='error'>" . upload_error($query) . "\n";
	}
}
?>

<form action="" method="post" enctype="multipart/form-data" id="form">
<?php
$execute = "<input type='submit' value='" . 'Execute' . "' title='Ctrl+Enter'>";
if (!isset($_GET["import"])) {
	$q = $_GET["sql"]; // overwrite $q from if ($_POST) to save memory
	if ($_POST) {
		$q = $_POST["query"];
	} elseif ($_GET["history"] == "all") {
		$q = $history;
	} elseif ($_GET["history"] != "") {
		$q = idx($history[$_GET["history"]], 0);
	}
	echo "<p>";
	textarea("query", $q, 20);
	echo script(($_POST ? "" : "qs('textarea').focus();\n") . "qs('#form').onsubmit = partial(sqlSubmit, qs('#form'), '" . js_escape(remove_from_uri("sql|limit|error_stops|only_errors|history")) . "');");
	echo "<p>";
	adminer()->sqlPrintAfter();
	echo "$execute\n";
	echo 'Limit rows' . ": <input type='number' name='limit' class='size' value='" . h($_POST ? $_POST["limit"] : $_GET["limit"]) . "'>\n";

} else {
	$gz = (extension_loaded("zlib") ? "[.gz]" : "");
	echo "<fieldset><legend>" . 'File upload' . "</legend><div>";
	echo file_input("SQL$gz: <input type='file' name='sql_file[]' multiple>\n$execute");
	echo "</div></fieldset>\n";
	$importServerPath = adminer()->importServerPath();
	if ($importServerPath) {
		echo "<fieldset><legend>" . 'From server' . "</legend><div>";
		echo sprintf('Webserver file %s', "<code>" . h($importServerPath) . "$gz</code>");
		echo ' <input type="submit" name="webfile" value="' . 'Run file' . '">';
		echo "</div></fieldset>\n";
	}
	echo "<p>";
}

echo checkbox("error_stops", 1, ($_POST ? $_POST["error_stops"] : isset($_GET["import"]) || $_GET["error_stops"]), 'Stop on error') . "\n";
echo checkbox("only_errors", 1, ($_POST ? $_POST["only_errors"] : isset($_GET["import"]) || $_GET["only_errors"]), 'Show only errors') . "\n";
echo input_token();

if (!isset($_GET["import"]) && $history) {
	print_fieldset("history", 'History', $_GET["history"] != "");
	for ($val = end($history); $val; $val = prev($history)) { // not array_reverse() to save memory
		$key = key($history);
		list($q, $time, $elapsed) = $val;
		echo '<a href="' . h(ME . "sql=&history=$key") . '">' . 'Edit' . "</a>"
			. " <span class='time' title='" . @date('Y-m-d', $time) . "'>" . @date("H:i:s", $time) . "</span>" // @ - time zone may be not set
			. " <code class='jush-" . JUSH . "'>" . shorten_utf8(ltrim(str_replace("\n", " ", str_replace("\r", "", preg_replace("~^(#|$line_comment).*~m", '', $q)))), 80, "</code>")
			. ($elapsed ? " <span class='time'>($elapsed)</span>" : "")
			. "<br>\n"
		;
	}
	echo "<input type='submit' name='clear' value='" . 'Clear' . "'>\n";
	echo "<a href='" . h(ME . "sql=&history=all") . "'>" . 'Edit all' . "</a>\n";
	echo "</div></fieldset>\n";
}
?>
</form>
<?php
} elseif (isset($_GET["edit"])) {
	?>
<?php
$TABLE = $_GET["edit"];
$fields = fields($TABLE);
$where = (isset($_GET["select"])
	? ($_POST["check"] && count($_POST["check"]) == 1 ? where_check($_POST["check"][0], $fields) : "")
	: where($_GET, $fields)
);
$update = (isset($_GET["select"]) ? $_POST["edit"] : $where);
foreach ($fields as $name => $field) {
	if (!isset($field["privileges"][$update ? "update" : "insert"]) || adminer()->fieldName($field) == "" || $field["generated"]) {
		unset($fields[$name]);
	}
}

if ($_POST && !$error && !isset($_GET["select"])) {
	$location = $_POST["referer"];
	if ($_POST["insert"]) { // continue edit or insert
		$location = ($update ? null : $_SERVER["REQUEST_URI"]);
	} elseif (!preg_match('~^.+&select=.+$~', $location)) {
		$location = ME . "select=" . urlencode($TABLE);
	}

	$indexes = indexes($TABLE);
	$unique_array = unique_array($_GET["where"], $indexes);
	$query_where = "\nWHERE $where";

	if (isset($_POST["delete"])) {
		queries_redirect(
			$location,
			'Item has been deleted.',
			driver()->delete($TABLE, $query_where, $unique_array ? 0 : 1)
		);

	} else {
		$set = array();
		foreach ($fields as $name => $field) {
			$val = process_input($field);
			if ($val !== false && $val !== null) {
				$set[idf_escape($name)] = $val;
			}
		}

		if ($update) {
			if (!$set) {
				redirect($location);
			}
			queries_redirect(
				$location,
				'Item has been updated.',
				driver()->update($TABLE, $set, $query_where, $unique_array ? 0 : 1)
			);
			if (is_ajax()) {
				page_headers();
				page_messages($error);
				exit;
			}
		} else {
			$result = driver()->insert($TABLE, $set);
			$last_id = ($result ? last_id($result) : 0);
			queries_redirect($location, sprintf('Item%s has been inserted.', ($last_id ? " $last_id" : "")), $result); //! link
		}
	}
}

$row = null;
if ($_POST["save"]) {
	$row = (array) $_POST["fields"];
} elseif ($where) {
	$select = array();
	foreach ($fields as $name => $field) {
		if (isset($field["privileges"]["select"])) {
			$as = ($_POST["clone"] && $field["auto_increment"] ? "''" : convert_field($field));
			$select[] = ($as ? "$as AS " : "") . idf_escape($name);
		}
	}
	$row = array();
	if (!support("table")) {
		$select = array("*");
	}
	if ($select) {
		$result = driver()->select($TABLE, $select, array($where), $select, array(), (isset($_GET["select"]) ? 2 : 1));
		if (!$result) {
			$error = error();
		} else {
			$row = $result->fetch_assoc();
			if (!$row) { // MySQLi returns null
				$row = false;
			}
		}
		if (isset($_GET["select"]) && (!$row || $result->fetch_assoc())) { // $result->num_rows != 1 isn't available in all drivers
			$row = null;
		}
	}
}

if (!support("table") && !$fields) { // used by Mongo and SimpleDB
	if (!$where) { // insert
		$result = driver()->select($TABLE, array("*"), array(), array("*"));
		$row = ($result ? $result->fetch_assoc() : false);
		if (!$row) {
			$row = array(driver()->primary => "");
		}
	}
	if ($row) {
		foreach ($row as $key => $val) {
			if (!$where) {
				$row[$key] = null;
			}
			$fields[$key] = array("field" => $key, "null" => ($key != driver()->primary), "auto_increment" => ($key == driver()->primary));
		}
	}
}

edit_form($TABLE, $fields, $row, $update, $error);

} elseif (isset($_GET["create"])) {
	?>
<?php
$TABLE = $_GET["create"];
$partition_by = driver()->partitionBy;
$partitions_info = ($partition_by ? driver()->partitionsInfo($TABLE) : array());

$referencable_primary = referencable_primary($TABLE);
$foreign_keys = array();
foreach ($referencable_primary as $table_name => $field) {
	$foreign_keys[str_replace("`", "``", $table_name) . "`" . str_replace("`", "``", $field["field"])] = $table_name; // not idf_escape() - used in JS
}

$orig_fields = array();
$table_status = array();
if ($TABLE != "") {
	$orig_fields = fields($TABLE);
	$table_status = table_status1($TABLE);
	if (count($table_status) < 2) { // there's only the Name field
		$error = 'No tables.';
	}
}

$row = $_POST;
$row["fields"] = (array) $row["fields"];
if ($row["auto_increment_col"]) {
	$row["fields"][$row["auto_increment_col"]]["auto_increment"] = true;
}

if ($_POST) {
	save_settings(array("comments" => $_POST["comments"], "defaults" => $_POST["defaults"]));
}

if ($_POST && !process_fields($row["fields"]) && !$error) {
	if ($_POST["drop"]) {
		queries_redirect(substr(ME, 0, -1), 'Table has been dropped.', drop_tables(array($TABLE)));
	} else {
		$fields = array();
		$all_fields = array();
		$use_all_fields = false;
		$foreign = array();
		$orig_field = reset($orig_fields);
		$after = " FIRST";

		foreach ($row["fields"] as $key => $field) {
			$foreign_key = $foreign_keys[$field["type"]];
			$type_field = ($foreign_key !== null ? $referencable_primary[$foreign_key] : $field); //! can collide with user defined type
			if ($field["field"] != "") {
				if (!$field["generated"]) {
					$field["default"] = null;
				}
				$process_field = process_field($field, $type_field);
				$all_fields[] = array($field["orig"], $process_field, $after);
				if (!$orig_field || $process_field !== process_field($orig_field, $orig_field)) {
					$fields[] = array($field["orig"], $process_field, $after);
					if ($field["orig"] != "" || $after) {
						$use_all_fields = true;
					}
				}
				if ($foreign_key !== null) {
					$foreign[idf_escape($field["field"])] = ($TABLE != "" && JUSH != "sqlite" ? "ADD" : " ") . format_foreign_key(array(
						'table' => $foreign_keys[$field["type"]],
						'source' => array($field["field"]),
						'target' => array($type_field["field"]),
						'on_delete' => $field["on_delete"],
					));
				}
				$after = " AFTER " . idf_escape($field["field"]);
			} elseif ($field["orig"] != "") {
				$use_all_fields = true;
				$fields[] = array($field["orig"]);
			}
			if ($field["orig"] != "") {
				$orig_field = next($orig_fields);
				if (!$orig_field) {
					$after = "";
				}
			}
		}

		$partitioning = array();
		if (in_array($row["partition_by"], $partition_by)) {
			foreach ($row as $key => $val) {
				if (preg_match('~^partition~', $key)) {
					$partitioning[$key] = $val;
				}
			}
			foreach ($partitioning["partition_names"] as $key => $name) {
				if ($name == "") {
					unset($partitioning["partition_names"][$key]);
					unset($partitioning["partition_values"][$key]);
				}
			}
			$partitioning["partition_names"] = array_values($partitioning["partition_names"]);
			$partitioning["partition_values"] = array_values($partitioning["partition_values"]);
			if ($partitioning == $partitions_info) {
				$partitioning = array();
			}
		} elseif (preg_match("~partitioned~", $table_status["Create_options"])) {
			$partitioning = null;
		}

		$message = 'Table has been altered.';
		if ($TABLE == "") {
			cookie("adminer_engine", $row["Engine"]);
			$message = 'Table has been created.';
		}
		$name = trim($row["name"]);

		queries_redirect(ME . (support("table") ? "table=" : "select=") . urlencode($name), $message, alter_table(
			$TABLE,
			$name,
			(JUSH == "sqlite" && ($use_all_fields || $foreign) ? $all_fields : $fields),
			$foreign,
			($row["Comment"] != $table_status["Comment"] ? $row["Comment"] : null),
			($row["Engine"] && $row["Engine"] != $table_status["Engine"] ? $row["Engine"] : ""),
			($row["Collation"] && $row["Collation"] != $table_status["Collation"] ? $row["Collation"] : ""),
			($row["Auto_increment"] != "" ? number($row["Auto_increment"]) : ""),
			$partitioning
		));
	}
}

page_header(($TABLE != "" ? 'Alter table' : 'Create table'), $error, array("table" => $TABLE), h($TABLE));

if (!$_POST) {
	$types = driver()->types();
	$row = array(
		"Engine" => $_COOKIE["adminer_engine"],
		"fields" => array(array("field" => "", "type" => (isset($types["int"]) ? "int" : (isset($types["integer"]) ? "integer" : "")), "on_update" => "")),
		"partition_names" => array(""),
	);

	if ($TABLE != "") {
		$row = $table_status;
		$row["name"] = $TABLE;
		$row["fields"] = array();
		if (!$_GET["auto_increment"]) { // don't prefill by original Auto_increment for the sake of performance and not reusing deleted ids
			$row["Auto_increment"] = "";
		}
		foreach ($orig_fields as $field) {
			$field["generated"] = $field["generated"] ?: (isset($field["default"]) ? "DEFAULT" : "");
			$row["fields"][] = $field;
		}

		if ($partition_by) {
			$row += $partitions_info;
			$row["partition_names"][] = "";
			$row["partition_values"][] = "";
		}
	}
}

$collations = collations();
if (is_array(reset($collations))) {
	$collations = call_user_func_array('array_merge', array_values($collations));
}
$engines = driver()->engines();
// case of engine may differ
foreach ($engines as $engine) {
	if (!strcasecmp($engine, $row["Engine"])) {
		$row["Engine"] = $engine;
		break;
	}
}
?>

<form action="" method="post" id="form">
<p>
<?php
if (support("columns") || $TABLE == "") {
	echo 'Table name' . ": <input name='name'" . ($TABLE == "" && !$_POST ? " autofocus" : "") . " data-maxlength='64' value='" . h($row["name"]) . "' autocapitalize='off'>\n";
	echo ($engines ? html_select("Engine", array("" => "(" . 'engine' . ")") + $engines, $row["Engine"]) . on_help("event.target.value", 1) . script("qsl('select').onchange = helpClose;") . "\n" : "");
	if ($collations) {
		echo "<datalist id='collations'>" . optionlist($collations) . "</datalist>\n";
		echo (preg_match("~sqlite|mssql~", JUSH) ? "" : "<input list='collations' name='Collation' value='" . h($row["Collation"]) . "' placeholder='(" . 'collation' . ")'>\n");
	}
	echo "<input type='submit' value='" . 'Save' . "'>\n";
}

if (support("columns")) {
	echo "<div class='scrollable'>\n";
	echo "<table id='edit-fields' class='nowrap'>\n";
	edit_fields($row["fields"], $collations, "TABLE", $foreign_keys);
	echo "</table>\n";
	echo script("editFields();");
	echo "</div>\n<p>\n";
	echo 'Auto Increment' . ": <input type='number' name='Auto_increment' class='size' value='" . h($row["Auto_increment"]) . "'>\n";
	echo checkbox("defaults", 1, ($_POST ? $_POST["defaults"] : get_setting("defaults")), 'Default values', "columnShow(this.checked, 5)", "jsonly");
	$comments = ($_POST ? $_POST["comments"] : get_setting("comments"));
	echo (support("comment")
		? checkbox("comments", 1, $comments, 'Comment', "editingCommentsClick(this, true);", "jsonly")
			. ' ' . (preg_match('~\n~', $row["Comment"])
				? "<textarea name='Comment' rows='2' cols='20'" . ($comments ? "" : " class='hidden'") . ">" . h($row["Comment"]) . "</textarea>"
				: '<input name="Comment" value="' . h($row["Comment"]) . '" data-maxlength="' . (min_version(5.5) ? 2048 : 60) . '"' . ($comments ? "" : " class='hidden'") . '>'
			)
		: '')
	;
	?>
<p>
<input type="submit" value="Save">
<?php } ?>

<?php if ($TABLE != "") { ?>
<input type="submit" name="drop" value="Drop"><?php echo confirm(sprintf('Drop %s?', $TABLE)); ?>
<?php } ?>
<?php
if ($partition_by && (JUSH == 'sql' || $TABLE == "")) {
	$partition_table = preg_match('~RANGE|LIST~', $row["partition_by"]);
	print_fieldset("partition", 'Partition by', $row["partition_by"]);
	echo "<p>" . html_select("partition_by", array_merge(array(""), $partition_by), $row["partition_by"]) . on_help("event.target.value.replace(/./, 'PARTITION BY \$&')", 1) . script("qsl('select').onchange = partitionByChange;");
	echo "(<input name='partition' value='" . h($row["partition"]) . "'>)\n";
	echo 'Partitions' . ": <input type='number' name='partitions' class='size" . ($partition_table || !$row["partition_by"] ? " hidden" : "") . "' value='" . h($row["partitions"]) . "'>\n";
	echo "<table id='partition-table'" . ($partition_table ? "" : " class='hidden'") . ">\n";
	echo "<thead><tr><th>" . 'Partition name' . "<th>" . 'Values' . "</thead>\n";
	foreach ($row["partition_names"] as $key => $val) {
		echo '<tr>';
		echo '<td><input name="partition_names[]" value="' . h($val) . '" autocapitalize="off">';
		echo ($key == count($row["partition_names"]) - 1 ? script("qsl('input').oninput = partitionNameChange;") : '');
		echo '<td><input name="partition_values[]" value="' . h(idx($row["partition_values"], $key)) . '">';
	}
	echo "</table>\n</div></fieldset>\n";
}
echo input_token();
?>
</form>
<?php
} elseif (isset($_GET["indexes"])) {
	?>
<?php
$TABLE = $_GET["indexes"];
$index_types = array("PRIMARY", "UNIQUE", "INDEX");
$table_status = table_status1($TABLE, true);
$index_algorithms = driver()->indexAlgorithms($table_status);
if (preg_match('~MyISAM|M?aria' . (min_version(5.6, '10.0.5') ? '|InnoDB' : '') . '~i', $table_status["Engine"])) {
	$index_types[] = "FULLTEXT";
}
if (preg_match('~MyISAM|M?aria' . (min_version(5.7, '10.2.2') ? '|InnoDB' : '') . '~i', $table_status["Engine"])) {
	$index_types[] = "SPATIAL";
}
$indexes = indexes($TABLE);
$fields = fields($TABLE);
$primary = array();
if (JUSH == "mongo") { // doesn't support primary key
	$primary = $indexes["_id_"];
	unset($index_types[0]);
	unset($indexes["_id_"]);
}
$row = $_POST;
if ($row) {
	save_settings(array("index_options" => $row["options"]));
}
if ($_POST && !$error && !$_POST["add"] && !$_POST["drop_col"]) {
	$alter = array();
	foreach ($row["indexes"] as $index) {
		$name = $index["name"];
		if (in_array($index["type"], $index_types)) {
			$columns = array();
			$lengths = array();
			$descs = array();
			$index_condition = (support("partial_indexes") ? $index["partial"] : "");
			$index_algorithm = (in_array($index["algorithm"], $index_algorithms) ? $index["algorithm"] : "");
			$set = array();
			ksort($index["columns"]);
			foreach ($index["columns"] as $key => $column) {
				if ($column != "") {
					$length = idx($index["lengths"], $key);
					$desc = idx($index["descs"], $key);
					$set[] = ($fields[$column] ? idf_escape($column) : $column) . ($length ? "(" . (+$length) . ")" : "") . ($desc ? " DESC" : "");
					$columns[] = $column;
					$lengths[] = ($length ?: null);
					$descs[] = $desc;
				}
			}

			$existing = $indexes[$name];
			if ($existing) {
				ksort($existing["columns"]);
				ksort($existing["lengths"]);
				ksort($existing["descs"]);
				if (
					$index["type"] == $existing["type"]
					&& array_values($existing["columns"]) === $columns
					&& (!$existing["lengths"] || array_values($existing["lengths"]) === $lengths)
					&& array_values($existing["descs"]) === $descs
					&& $existing["partial"] == $index_condition
					&& (!$index_algorithms || $existing["algorithm"] == $index_algorithm)
				) {
					// skip existing index
					unset($indexes[$name]);
					continue;
				}
			}
			if ($columns) {
				$alter[] = array($index["type"], $name, $set, $index_algorithm, $index_condition);
			}
		}
	}

	// drop removed indexes
	foreach ($indexes as $name => $existing) {
		$alter[] = array($existing["type"], $name, "DROP");
	}
	if (!$alter) {
		redirect(ME . "table=" . urlencode($TABLE));
	}
	queries_redirect(ME . "table=" . urlencode($TABLE), 'Indexes have been altered.', alter_indexes($TABLE, $alter));
}

page_header('Indexes', $error, array("table" => $TABLE), h($TABLE));

$fields_keys = array_keys($fields);
if ($_POST["add"]) {
	foreach ($row["indexes"] as $key => $index) {
		if ($index["columns"][count($index["columns"])] != "") {
			$row["indexes"][$key]["columns"][] = "";
		}
	}
	$index = end($row["indexes"]);
	if ($index["type"] || array_filter($index["columns"], 'strlen')) {
		$row["indexes"][] = array("columns" => array(1 => ""));
	}
}
if (!$row) {
	foreach ($indexes as $key => $index) {
		$indexes[$key]["name"] = $key;
		$indexes[$key]["columns"][] = "";
	}
	$indexes[] = array("columns" => array(1 => ""));
	$row["indexes"] = $indexes;
}
$lengths = (JUSH == "sql" || JUSH == "mssql");
$show_options = ($_POST ? $_POST["options"] : get_setting("index_options"));
?>

<form action="" method="post">
<div class="scrollable">
<table class="nowrap">
<thead><tr>
<th id="label-type">Index Type
<?php
$idxopts = " class='idxopts" . ($show_options ? "" : " hidden") . "'";
if ($index_algorithms) {
	echo "<th id='label-algorithm'$idxopts>" . 'Algorithm' . doc_link(array(
		'sql' => 'create-index.html#create-index-storage-engine-index-types',
		'mariadb' => 'storage-engine-index-types/',
		'pgsql' => 'indexes-types.html',
	));
}
?>
<th><input type="submit" class="wayoff"><?php
echo 'Columns' . ($lengths ? "<span$idxopts> (" . 'length' . ")</span>" : "");
if ($lengths || support("descidx")) {
	echo checkbox("options", 1, $show_options, 'Options', "indexOptionsShow(this.checked)", "jsonly") . "\n";
}
?>
<th id="label-name">Name
<?php
if (support("partial_indexes")) {
	echo "<th id='label-condition'$idxopts>" . 'Condition';
}
?>
<th><noscript><?php echo icon("plus", "add[0]", "+", 'Add next'); ?></noscript>
</thead>
<?php
if ($primary) {
	echo "<tr><td>PRIMARY<td>";
	foreach ($primary["columns"] as $key => $column) {
		echo select_input(" disabled", $fields_keys, $column);
		echo "<label><input disabled type='checkbox'>" . 'descending' . "</label> ";
	}
	echo "<td><td>\n";
}
$j = 1;
foreach ($row["indexes"] as $index) {
	if (!$_POST["drop_col"] || $j != key($_POST["drop_col"])) {
		echo "<tr><td>" . html_select("indexes[$j][type]", array(-1 => "") + $index_types, $index["type"], ($j == count($row["indexes"]) ? "indexesAddRow.call(this);" : ""), "label-type");

		if ($index_algorithms) {
			echo "<td$idxopts>" . html_select("indexes[$j][algorithm]", array_merge(array(""), $index_algorithms), $index['algorithm'], "label-algorithm");
		}

		echo "<td>";
		ksort($index["columns"]);
		$i = 1;
		foreach ($index["columns"] as $key => $column) {
			echo "<span>" . select_input(
				" name='indexes[$j][columns][$i]' title='" . 'Column' . "'",
				($fields && ($column == "" || $fields[$column]) ? array_combine($fields_keys, $fields_keys) : array()),
				$column,
				"partial(" . ($i == count($index["columns"]) ? "indexesAddColumn" : "indexesChangeColumn") . ", '" . js_escape(JUSH == "sql" ? "" : $_GET["indexes"] . "_") . "')"
			);
			echo "<span$idxopts>";
			echo ($lengths ? "<input type='number' name='indexes[$j][lengths][$i]' class='size' value='" . h(idx($index["lengths"], $key)) . "' title='" . 'Length' . "'>" : "");
			echo (support("descidx") ? checkbox("indexes[$j][descs][$i]", 1, idx($index["descs"], $key), 'descending') : "");
			echo "</span> </span>";
			$i++;
		}

		echo "<td><input name='indexes[$j][name]' value='" . h($index["name"]) . "' autocapitalize='off' aria-labelledby='label-name'>\n";
		if (support("partial_indexes")) {
			echo "<td$idxopts><input name='indexes[$j][partial]' value='" . h($index["partial"]) . "' autocapitalize='off' aria-labelledby='label-condition'>\n";
		}
		echo "<td>" . icon("cross", "drop_col[$j]", "x", 'Remove') . script("qsl('button').onclick = partial(editingRemoveRow, 'indexes\$1[type]');");
	}
	$j++;
}
?>
</table>
</div>
<p>
<input type="submit" value="Save">
<?php echo input_token(); ?>
</form>
<?php
} elseif (isset($_GET["database"])) {
	?>
<?php
$row = $_POST;

if ($_POST && !$error && !$_POST["add"]) {
	$name = trim($row["name"]);
	if ($_POST["drop"]) {
		$_GET["db"] = ""; // to save in global history
		queries_redirect(remove_from_uri("db|database"), 'Database has been dropped.', drop_databases(array(DB)));
	} elseif (DB !== $name) {
		// create or rename database
		if (DB != "") {
			$_GET["db"] = $name;
			queries_redirect(preg_replace('~\bdb=[^&]*&~', '', ME) . "db=" . urlencode($name), 'Database has been renamed.', rename_database($name, $row["collation"]));
		} else {
			$databases = explode("\n", str_replace("\r", "", $name));
			$success = true;
			$last = "";
			foreach ($databases as $db) {
				if (count($databases) == 1 || $db != "") { // ignore empty lines but always try to create single database
					if (!create_database($db, $row["collation"])) {
						$success = false;
					}
					$last = $db;
				}
			}
			restart_session();
			set_session("dbs", null);
			queries_redirect(ME . "db=" . urlencode($last), 'Database has been created.', $success);
		}
	} else {
		// alter database
		if (!$row["collation"]) {
			redirect(substr(ME, 0, -1));
		}
		query_redirect("ALTER DATABASE " . idf_escape($name) . (preg_match('~^[a-z0-9_]+$~i', $row["collation"]) ? " COLLATE $row[collation]" : ""), substr(ME, 0, -1), 'Database has been altered.');
	}
}

page_header(DB != "" ? 'Alter database' : 'Create database', $error, array(), h(DB));

$collations = collations();
$name = DB;
if ($_POST) {
	$name = $row["name"];
} elseif (DB != "") {
	$row["collation"] = db_collation(DB, $collations);
} elseif (JUSH == "sql") {
	// propose database name with limited privileges
	foreach (get_vals("SHOW GRANTS") as $grant) {
		if (preg_match('~ ON (`(([^\\\\`]|``|\\\\.)*)%`\.\*)?~', $grant, $match) && $match[1]) {
			$name = stripcslashes(idf_unescape("`$match[2]`"));
			break;
		}
	}
}
?>

<form action="" method="post">
<p>
<?php
echo ($_POST["add"] || strpos($name, "\n")
	? '<textarea autofocus name="name" rows="10" cols="40">' . h($name) . '</textarea><br>'
	: '<input name="name" autofocus value="' . h($name) . '" data-maxlength="64" autocapitalize="off">'
) . "\n" . ($collations ? html_select("collation", array("" => "(" . 'collation' . ")") + $collations, $row["collation"]) . doc_link(array(
	'sql' => "charset-charsets.html",
	'mariadb' => "supported-character-sets-and-collations/",
	'mssql' => "relational-databases/system-functions/sys-fn-helpcollations-transact-sql",
)) : "");
?>
<input type="submit" value="Save">
<?php
if (DB != "") {
	echo "<input type='submit' name='drop' value='" . 'Drop' . "'>" . confirm(sprintf('Drop %s?', DB)) . "\n";
} elseif (!$_POST["add"] && $_GET["db"] == "") {
	echo icon("plus", "add[0]", "+", 'Add next') . "\n";
}
echo input_token();
?>
</form>
<?php
} elseif (isset($_GET["scheme"])) {
	?>
<?php
$row = $_POST;

if ($_POST && !$error) {
	$link = preg_replace('~ns=[^&]*&~', '', ME) . "ns=";
	if ($_POST["drop"]) {
		query_redirect("DROP SCHEMA " . idf_escape($_GET["ns"]), $link, 'Schema has been dropped.');
	} else {
		$name = trim($row["name"]);
		$link .= urlencode($name);
		if ($_GET["ns"] == "") {
			query_redirect("CREATE SCHEMA " . idf_escape($name), $link, 'Schema has been created.');
		} elseif ($_GET["ns"] != $name) {
			query_redirect("ALTER SCHEMA " . idf_escape($_GET["ns"]) . " RENAME TO " . idf_escape($name), $link, 'Schema has been altered.'); //! sp_rename in MS SQL
		} else {
			redirect($link);
		}
	}
}

page_header($_GET["ns"] != "" ? 'Alter schema' : 'Create schema', $error);

if (!$row) {
	$row["name"] = $_GET["ns"];
}
?>

<form action="" method="post">
<p><input name="name" autofocus value="<?php echo h($row["name"]); ?>" autocapitalize="off">
<input type="submit" value="Save">
<?php
if ($_GET["ns"] != "") {
	echo "<input type='submit' name='drop' value='" . 'Drop' . "'>" . confirm(sprintf('Drop %s?', $_GET["ns"])) . "\n";
}
echo input_token();
?>
</form>
<?php
} elseif (isset($_GET["call"])) {
	?>
<?php
$PROCEDURE = ($_GET["name"] ?: $_GET["call"]);
page_header('Call' . ": " . h($PROCEDURE), $error);

$routine = routine($_GET["call"], (isset($_GET["callf"]) ? "FUNCTION" : "PROCEDURE"));
$in = array();
$out = array();
foreach ($routine["fields"] as $i => $field) {
	if (substr($field["inout"], -3) == "OUT" && JUSH == 'sql') {
		$out[$i] = "@" . idf_escape($field["field"]) . " AS " . idf_escape($field["field"]);
	}
	if (!$field["inout"] || substr($field["inout"], 0, 2) == "IN") {
		$in[] = $i;
	}
}

if (!$error && $_POST) {
	$call = array();
	foreach ($routine["fields"] as $key => $field) {
		$val = "";
		if (in_array($key, $in)) {
			$val = process_input($field);
			if ($val === false) {
				$val = "''";
			}
			if (isset($out[$key])) {
				connection()->query("SET @" . idf_escape($field["field"]) . " = $val");
			}
		}
		if (isset($out[$key])) {
			$call[] = "@" . idf_escape($field["field"]);
		} elseif (in_array($key, $in)) {
			$call[] = $val;
		}
	}

	$query = (isset($_GET["callf"]) ? "SELECT " : "CALL ") . (idx($routine["returns"], "type") == "record" ? "* FROM " : "") . table($PROCEDURE) . "(" . implode(", ", $call) . ")";
	$start = microtime(true);
	$result = connection()->multi_query($query);
	$affected = connection()->affected_rows; // getting warnings overwrites this
	echo adminer()->selectQuery($query, $start, !$result);

	if (!$result) {
		echo "<p class='error'>" . error() . "\n";
	} else {
		$connection2 = connect();
		if ($connection2) {
			$connection2->select_db(DB);
		}

		do {
			$result = connection()->store_result();
			if (is_object($result)) {
				print_select_result($result, $connection2);
			} else {
				echo "<p class='message'>" . lang_format(array('Routine has been called, %d row affected.', 'Routine has been called, %d rows affected.'), $affected)
					. " <span class='time'>" . @date("H:i:s") . "</span>\n" // @ - time zone may be not set
				;
			}
		} while (connection()->next_result());

		if ($out) {
			print_select_result(connection()->query("SELECT " . implode(", ", $out)));
		}
	}
}
?>

<form action="" method="post">
<?php
if ($in) {
	echo "<table class='layout'>\n";
	foreach ($in as $key) {
		$field = $routine["fields"][$key];
		$name = $field["field"];
		echo "<tr><th>" . adminer()->fieldName($field);
		$value = idx($_POST["fields"], $name);
		if ($value != "") {
			if ($field["type"] == "set") {
				$value = implode(",", $value);
			}
		}
		input($field, $value, idx($_POST["function"], $name, "")); // param name can be empty
		echo "\n";
	}
	echo "</table>\n";
}
?>
<p>
<input type="submit" value="Call">
<?php echo input_token(); ?>
</form>

<pre>
<?php
/** Format string as table row
* @return string HTML
*/
function pre_tr($s) {
	return preg_replace('~^~m', '<tr>', preg_replace('~\|~', '<td>', preg_replace('~\|$~m', "", rtrim($s))));
}

$table = '(\+--[-+]+\+\n)';
$row = '(\| .* \|\n)';
echo preg_replace_callback(
	"~^$table?$row$table?($row*)$table?~m",
	function ($match) {
		$first_row = pre_tr($match[2]);
		return "<table>\n" . ($match[1] ? "<thead>$first_row</thead>\n" : $first_row) . pre_tr($match[4]) . "\n</table>";
	},
	preg_replace(
		'~(\n(    -|mysql)&gt; )(.+)~',
		"\\1<code class='jush-sql'>\\3</code>",
		preg_replace('~(.+)\n---+\n~', "<b>\\1</b>\n", h($routine['comment']))
	)
);
?>
</pre>
<?php
} elseif (isset($_GET["foreign"])) {
	?>
<?php
$TABLE = $_GET["foreign"];
$name = $_GET["name"];
$row = $_POST;

if ($_POST && !$error && !$_POST["add"] && !$_POST["change"] && !$_POST["change-js"]) {
	if (!$_POST["drop"]) {
		$row["source"] = array_filter($row["source"], 'strlen');
		ksort($row["source"]); // enforce input order
		$target = array();
		foreach ($row["source"] as $key => $val) {
			$target[$key] = $row["target"][$key];
		}
		$row["target"] = $target;
	}

	if (JUSH == "sqlite") {
		$result = recreate_table($TABLE, $TABLE, array(), array(), array(" $name" => ($row["drop"] ? "" : " " . format_foreign_key($row))));
	} else {
		$alter = "ALTER TABLE " . table($TABLE);
		$result = ($name == "" || queries("$alter DROP " . (JUSH == "sql" ? "FOREIGN KEY " : "CONSTRAINT ") . idf_escape($name)));
		if (!$row["drop"]) {
			$result = queries("$alter ADD" . format_foreign_key($row));
		}
	}
	queries_redirect(
		ME . "table=" . urlencode($TABLE),
		($row["drop"] ? 'Foreign key has been dropped.' : ($name != "" ? 'Foreign key has been altered.' : 'Foreign key has been created.')),
		$result
	);
	if (!$row["drop"]) {
		$error = 'Source and target columns must have the same data type, there must be an index on the target columns and referenced data must exist.'; //! no partitioning
	}
}

page_header('Foreign key', $error, array("table" => $TABLE), h($TABLE));

if ($_POST) {
	ksort($row["source"]);
	if ($_POST["add"]) {
		$row["source"][] = "";
	} elseif ($_POST["change"] || $_POST["change-js"]) {
		$row["target"] = array();
	}
} elseif ($name != "") {
	$foreign_keys = foreign_keys($TABLE);
	$row = $foreign_keys[$name];
	$row["source"][] = "";
} else {
	$row["table"] = $TABLE;
	$row["source"] = array("");
}
?>

<form action="" method="post">
<?php
$source = array_keys(fields($TABLE)); //! no text and blob
if ($row["db"] != "") {
	connection()->select_db($row["db"]);
}
if ($row["ns"] != "") {
	$orig_schema = get_schema();
	set_schema($row["ns"]);
}
$referencable = array_keys(array_filter(table_status('', true), 'Adminer\fk_support'));
$target = array_keys(fields(in_array($row["table"], $referencable) ? $row["table"] : reset($referencable)));
$onchange = "this.form['change-js'].value = '1'; this.form.submit();";
echo "<p><label>" . 'Target table' . ": " . html_select("table", $referencable, $row["table"], $onchange) . "</label>\n";
if (support("scheme")) {
	$schemas = array_filter(adminer()->schemas(), function ($schema) {
		return !preg_match('~^information_schema$~i', $schema);
	});
	echo "<label>" . 'Schema' . ": " . html_select("ns", $schemas, $row["ns"] != "" ? $row["ns"] : $_GET["ns"], $onchange) . "</label>";
	if ($row["ns"] != "") {
		set_schema($orig_schema);
	}
} elseif (JUSH != "sqlite") {
	$dbs = array();
	foreach (adminer()->databases() as $db) {
		if (!information_schema($db)) {
			$dbs[] = $db;
		}
	}
	echo "<label>" . 'DB' . ": " . html_select("db", $dbs, $row["db"] != "" ? $row["db"] : $_GET["db"], $onchange) . "</label>";
}
echo input_hidden("change-js");
?>
<noscript><p><input type="submit" name="change" value="Change"></noscript>
<table>
<thead><tr><th id="label-source">Source<th id="label-target">Target</thead>
<?php
$j = 0;
foreach ($row["source"] as $key => $val) {
	echo "<tr>";
	echo "<td>" . html_select("source[" . (+$key) . "]", array(-1 => "") + $source, $val, ($j == count($row["source"]) - 1 ? "foreignAddRow.call(this);" : ""), "label-source");
	echo "<td>" . html_select("target[" . (+$key) . "]", $target, idx($row["target"], $key), "", "label-target");
	$j++;
}
?>
</table>
<p>
<label>ON DELETE: <?php echo html_select("on_delete", array(-1 => "") + explode("|", driver()->onActions), $row["on_delete"]); ?></label>
<label>ON UPDATE: <?php echo html_select("on_update", array(-1 => "") + explode("|", driver()->onActions), $row["on_update"]); ?></label>
<?php echo (DRIVER === 'pgsql' ? html_select("deferrable", array('NOT DEFERRABLE', 'DEFERRABLE', 'DEFERRABLE INITIALLY DEFERRED'), $row["deferrable"]) . ' ' : ''); ?>
<?php echo doc_link(array(
	'sql' => "innodb-foreign-key-constraints.html",
	'mariadb' => "foreign-keys/",
	'pgsql' => "sql-createtable.html#SQL-CREATETABLE-PARMS-REFERENCES",
	'mssql' => "t-sql/statements/create-table-transact-sql",
	'oracle' => "SQLRF01111",
)); ?>
<p>
<input type="submit" value="Save">
<noscript><p><input type="submit" name="add" value="Add column"></noscript>
<?php if ($name != "") { ?>
<input type="submit" name="drop" value="Drop"><?php echo confirm(sprintf('Drop %s?', $name)); ?>
<?php } ?>
<?php echo input_token(); ?>
</form>
<?php
} elseif (isset($_GET["view"])) {
	?>
<?php
$TABLE = $_GET["view"];
$row = $_POST;
$orig_type = "VIEW";
if (JUSH == "pgsql" && $TABLE != "") {
	$status = table_status1($TABLE);
	$orig_type = strtoupper($status["Engine"]);
}

if ($_POST && !$error) {
	$name = trim($row["name"]);
	$as = " AS\n$row[select]";
	$location = ME . "table=" . urlencode($name);
	$message = 'View has been altered.';

	$type = ($_POST["materialized"] ? "MATERIALIZED VIEW" : "VIEW");

	if (!$_POST["drop"] && $TABLE == $name && JUSH != "sqlite" && $type == "VIEW" && $orig_type == "VIEW") {
		query_redirect((JUSH == "mssql" ? "ALTER" : "CREATE OR REPLACE") . " VIEW " . table($name) . $as, $location, $message);
	} else {
		$temp_name = $name . "_adminer_" . uniqid();
		drop_create(
			"DROP $orig_type " . table($TABLE),
			"CREATE $type " . table($name) . $as,
			"DROP $type " . table($name),
			"CREATE $type " . table($temp_name) . $as,
			"DROP $type " . table($temp_name),
			($_POST["drop"] ? substr(ME, 0, -1) : $location),
			'View has been dropped.',
			$message,
			'View has been created.',
			$TABLE,
			$name
		);
	}
}

if (!$_POST && $TABLE != "") {
	$row = view($TABLE);
	$row["name"] = $TABLE;
	$row["materialized"] = ($orig_type != "VIEW");
	if (!$error) {
		$error = error();
	}
}

page_header(($TABLE != "" ? 'Alter view' : 'Create view'), $error, array("table" => $TABLE), h($TABLE));
?>

<form action="" method="post">
<p>Name: <input name="name" value="<?php echo h($row["name"]); ?>" data-maxlength="64" autocapitalize="off">
<?php echo (support("materializedview") ? " " . checkbox("materialized", 1, $row["materialized"], 'Materialized view') : ""); ?>
<p><?php textarea("select", $row["select"]); ?>
<p>
<input type="submit" value="Save">
<?php if ($TABLE != "") { ?>
<input type="submit" name="drop" value="Drop"><?php echo confirm(sprintf('Drop %s?', $TABLE)); ?>
<?php } ?>
<?php echo input_token(); ?>
</form>
<?php
} elseif (isset($_GET["event"])) {
	?>
<?php
$EVENT = $_GET["event"];
$intervals = array("YEAR", "QUARTER", "MONTH", "DAY", "HOUR", "MINUTE", "WEEK", "SECOND", "YEAR_MONTH", "DAY_HOUR", "DAY_MINUTE", "DAY_SECOND", "HOUR_MINUTE", "HOUR_SECOND", "MINUTE_SECOND");
$statuses = array("ENABLED" => "ENABLE", "DISABLED" => "DISABLE", "SLAVESIDE_DISABLED" => "DISABLE ON SLAVE");
$row = $_POST;

if ($_POST && !$error) {
	if ($_POST["drop"]) {
		query_redirect("DROP EVENT " . idf_escape($EVENT), substr(ME, 0, -1), 'Event has been dropped.');
	} elseif (in_array($row["INTERVAL_FIELD"], $intervals) && isset($statuses[$row["STATUS"]])) {
		$schedule = "\nON SCHEDULE " . ($row["INTERVAL_VALUE"]
			? "EVERY " . q($row["INTERVAL_VALUE"]) . " $row[INTERVAL_FIELD]"
			. ($row["STARTS"] ? " STARTS " . q($row["STARTS"]) : "")
			. ($row["ENDS"] ? " ENDS " . q($row["ENDS"]) : "") //! ALTER EVENT doesn't drop ENDS - MySQL bug #39173
			: "AT " . q($row["STARTS"])
			) . " ON COMPLETION" . ($row["ON_COMPLETION"] ? "" : " NOT") . " PRESERVE"
		;

		queries_redirect(
			substr(ME, 0, -1),
			($EVENT != "" ? 'Event has been altered.' : 'Event has been created.'),
			queries(
				($EVENT != ""
				? "ALTER EVENT " . idf_escape($EVENT) . $schedule . ($EVENT != $row["EVENT_NAME"] ? "\nRENAME TO " . idf_escape($row["EVENT_NAME"]) : "")
				: "CREATE EVENT " . idf_escape($row["EVENT_NAME"]) . $schedule
				) . "\n" . $statuses[$row["STATUS"]] . " COMMENT " . q($row["EVENT_COMMENT"])
				. rtrim(" DO\n$row[EVENT_DEFINITION]", ";") . ";"
			)
		);
	}
}

page_header(($EVENT != "" ? 'Alter event' . ": " . h($EVENT) : 'Create event'), $error);

if (!$row && $EVENT != "") {
	$rows = get_rows("SELECT * FROM information_schema.EVENTS WHERE EVENT_SCHEMA = " . q(DB) . " AND EVENT_NAME = " . q($EVENT));
	$row = reset($rows);
}
?>

<form action="" method="post">
<table class="layout">
<tr><th>Name<td><input name="EVENT_NAME" value="<?php echo h($row["EVENT_NAME"]); ?>" data-maxlength="64" autocapitalize="off">
<tr><th title="datetime">Start<td><input name="STARTS" value="<?php echo h("$row[EXECUTE_AT]$row[STARTS]"); ?>">
<tr><th title="datetime">End<td><input name="ENDS" value="<?php echo h($row["ENDS"]); ?>">
<tr><th>Every<td><input type="number" name="INTERVAL_VALUE" value="<?php echo h($row["INTERVAL_VALUE"]); ?>" class="size"> <?php echo html_select("INTERVAL_FIELD", $intervals, $row["INTERVAL_FIELD"]); ?>
<tr><th>Status<td><?php echo html_select("STATUS", $statuses, $row["STATUS"]); ?>
<tr><th>Comment<td><input name="EVENT_COMMENT" value="<?php echo h($row["EVENT_COMMENT"]); ?>" data-maxlength="64">
<tr><th><td><?php echo checkbox("ON_COMPLETION", "PRESERVE", $row["ON_COMPLETION"] == "PRESERVE", 'On completion preserve'); ?>
</table>
<p><?php textarea("EVENT_DEFINITION", $row["EVENT_DEFINITION"]); ?>
<p>
<input type="submit" value="Save">
<?php if ($EVENT != "") { ?>
<input type="submit" name="drop" value="Drop"><?php echo confirm(sprintf('Drop %s?', $EVENT)); ?>
<?php } ?>
<?php echo input_token(); ?>
</form>
<?php
} elseif (isset($_GET["procedure"])) {
	?>
<?php
$PROCEDURE = ($_GET["name"] ?: $_GET["procedure"]);
$routine = (isset($_GET["function"]) ? "FUNCTION" : "PROCEDURE");
$row = $_POST;
$row["fields"] = (array) $row["fields"];

if ($_POST && !process_fields($row["fields"]) && !$error) {
	$orig = routine($_GET["procedure"], $routine);
	$temp_name = "$row[name]_adminer_" . uniqid();
	foreach ($row["fields"] as $key => $field) {
		if ($field["field"] == "") {
			unset($row["fields"][$key]);
		}
	}
	drop_create(
		"DROP $routine " . routine_id($PROCEDURE, $orig),
		create_routine($routine, $row),
		"DROP $routine " . routine_id($row["name"], $row),
		create_routine($routine, array("name" => $temp_name) + $row),
		"DROP $routine " . routine_id($temp_name, $row),
		substr(ME, 0, -1),
		'Routine has been dropped.',
		'Routine has been altered.',
		'Routine has been created.',
		$PROCEDURE,
		$row["name"]
	);
}

page_header(($PROCEDURE != "" ? (isset($_GET["function"]) ? 'Alter function' : 'Alter procedure') . ": " . h($PROCEDURE) : (isset($_GET["function"]) ? 'Create function' : 'Create procedure')), $error);

if (!$_POST) {
	if ($PROCEDURE == "") {
		$row["language"] = "sql";
	} else {
		$row = routine($_GET["procedure"], $routine);
		$row["name"] = $PROCEDURE;
	}
}

$collations = get_vals("SHOW CHARACTER SET");
sort($collations);
$routine_languages = routine_languages();
echo ($collations ? "<datalist id='collations'>" . optionlist($collations) . "</datalist>" : "");
?>

<form action="" method="post" id="form">
<p>Name: <input name="name" value="<?php echo h($row["name"]); ?>" data-maxlength="64" autocapitalize="off">
<?php echo ($routine_languages ? "<label>" . 'Language' . ": " . html_select("language", $routine_languages, $row["language"]) . "</label>\n" : ""); ?>
<input type="submit" value="Save">
<div class="scrollable">
<table class="nowrap">
<?php
edit_fields($row["fields"], $collations, $routine);
if (isset($_GET["function"])) {
	echo "<tr><td>" . 'Return type';
	edit_type("returns", (array) $row["returns"], $collations, array(), (JUSH == "pgsql" ? array("void", "trigger") : array()));
}
?>
</table>
<?php echo script("editFields();"); ?>
</div>
<p><?php textarea("definition", $row["definition"], 20); ?>
<p>
<input type="submit" value="Save">
<?php if ($PROCEDURE != "") { ?>
<input type="submit" name="drop" value="Drop"><?php echo confirm(sprintf('Drop %s?', $PROCEDURE)); ?>
<?php } ?>
<?php echo input_token(); ?>
</form>
<?php
} elseif (isset($_GET["sequence"])) {
	?>
<?php
$SEQUENCE = $_GET["sequence"];
$row = $_POST;

if ($_POST && !$error) {
	$link = substr(ME, 0, -1);
	$name = trim($row["name"]);
	if ($_POST["drop"]) {
		query_redirect("DROP SEQUENCE " . idf_escape($SEQUENCE), $link, 'Sequence has been dropped.');
	} elseif ($SEQUENCE == "") {
		query_redirect("CREATE SEQUENCE " . idf_escape($name), $link, 'Sequence has been created.');
	} elseif ($SEQUENCE != $name) {
		query_redirect("ALTER SEQUENCE " . idf_escape($SEQUENCE) . " RENAME TO " . idf_escape($name), $link, 'Sequence has been altered.');
	} else {
		redirect($link);
	}
}

page_header($SEQUENCE != "" ? 'Alter sequence' . ": " . h($SEQUENCE) : 'Create sequence', $error);

if (!$row) {
	$row["name"] = $SEQUENCE;
}
?>

<form action="" method="post">
<p><input name="name" value="<?php echo h($row["name"]); ?>" autocapitalize="off">
<input type="submit" value="Save">
<?php
if ($SEQUENCE != "") {
	echo "<input type='submit' name='drop' value='" . 'Drop' . "'>" . confirm(sprintf('Drop %s?', $SEQUENCE)) . "\n";
}
echo input_token();
?>
</form>
<?php
} elseif (isset($_GET["type"])) {
	?>
<?php
$TYPE = $_GET["type"];
$row = $_POST;

if ($_POST && !$error) {
	$link = substr(ME, 0, -1);
	if ($_POST["drop"]) {
		query_redirect("DROP TYPE " . idf_escape($TYPE), $link, 'Type has been dropped.');
	} else {
		query_redirect("CREATE TYPE " . idf_escape(trim($row["name"])) . " $row[as]", $link, 'Type has been created.');
	}
}

page_header($TYPE != "" ? 'Alter type' . ": " . h($TYPE) : 'Create type', $error);

if (!$row) {
	$row["as"] = "AS ";
}
?>

<form action="" method="post">
<p>
<?php
if ($TYPE != "") {
	$types = driver()->types();
	$enums = type_values($types[$TYPE]);
	if ($enums) {
		echo "<code class='jush-" . JUSH . "'>ENUM (" . h($enums) . ")</code>\n<p>";
	}
	echo "<input type='submit' name='drop' value='" . 'Drop' . "'>" . confirm(sprintf('Drop %s?', $TYPE)) . "\n";
} else {
	echo 'Name' . ": <input name='name' value='" . h($row['name']) . "' autocapitalize='off'>\n";
	echo doc_link(array(
		'pgsql' => "datatype-enum.html",
	), "?");
	textarea("as", $row["as"]);
	echo "<p><input type='submit' value='" . 'Save' . "'>\n";
}
echo input_token();
?>
</form>
<?php
} elseif (isset($_GET["check"])) {
	?>
<?php
$TABLE = $_GET["check"];
$name = $_GET["name"];
$row = $_POST;

if ($row && !$error) {
	if (JUSH == "sqlite") {
		$result = recreate_table($TABLE, $TABLE, array(), array(), array(), "", array(), "$name", ($row["drop"] ? "" : $row["clause"]));
	} else {
		$result = ($name == "" || queries("ALTER TABLE " . table($TABLE) . " DROP CONSTRAINT " . idf_escape($name)));
		if (!$row["drop"]) {
			$result = queries("ALTER TABLE " . table($TABLE) . " ADD" . ($row["name"] != "" ? " CONSTRAINT " . idf_escape($row["name"]) : "") . " CHECK ($row[clause])"); //! SQL injection
		}
	}
	queries_redirect(
		ME . "table=" . urlencode($TABLE),
		($row["drop"] ? 'Check has been dropped.' : ($name != "" ? 'Check has been altered.' : 'Check has been created.')),
		$result
	);
}

page_header(($name != "" ? 'Alter check' . ": " . h($name) : 'Create check'), $error, array("table" => $TABLE));

if (!$row) {
	$checks = driver()->checkConstraints($TABLE);
	$row = array("name" => $name, "clause" => $checks[$name]);
}
?>

<form action="" method="post">
<p><?php
if (JUSH != "sqlite") {
	echo 'Name' . ': <input name="name" value="' . h($row["name"]) . '" data-maxlength="64" autocapitalize="off"> ';
}
echo doc_link(array(
	'sql' => "create-table-check-constraints.html",
	'mariadb' => "constraint/",
	'pgsql' => "ddl-constraints.html#DDL-CONSTRAINTS-CHECK-CONSTRAINTS",
	'mssql' => "relational-databases/tables/create-check-constraints",
	'sqlite' => "lang_createtable.html#check_constraints",
), "?");
?>
<p><?php textarea("clause", $row["clause"]); ?>
<p><input type="submit" value="Save">
<?php if ($name != "") { ?>
<input type="submit" name="drop" value="Drop"><?php echo confirm(sprintf('Drop %s?', $name)); ?>
<?php } ?>
<?php echo input_token(); ?>
</form>
<?php
} elseif (isset($_GET["trigger"])) {
	?>
<?php
$TABLE = $_GET["trigger"];
$name = "$_GET[name]";
$trigger_options = trigger_options();
$row = (array) trigger($name, $TABLE) + array("Trigger" => $TABLE . "_bi");

if ($_POST) {
	if (!$error && in_array($_POST["Timing"], $trigger_options["Timing"]) && in_array($_POST["Event"], $trigger_options["Event"]) && in_array($_POST["Type"], $trigger_options["Type"])) {
		// don't use drop_create() because there may not be more triggers for the same action
		$on = " ON " . table($TABLE);
		$drop = "DROP TRIGGER " . idf_escape($name) . (JUSH == "pgsql" ? $on : "");
		$location = ME . "table=" . urlencode($TABLE);
		if ($_POST["drop"]) {
			query_redirect($drop, $location, 'Trigger has been dropped.');
		} else {
			if ($name != "") {
				queries($drop);
			}
			queries_redirect(
				$location,
				($name != "" ? 'Trigger has been altered.' : 'Trigger has been created.'),
				queries(create_trigger($on, $_POST))
			);
			if ($name != "") {
				queries(create_trigger($on, $row + array("Type" => reset($trigger_options["Type"]))));
			}
		}
	}
	$row = $_POST;
}

page_header(($name != "" ? 'Alter trigger' . ": " . h($name) : 'Create trigger'), $error, array("table" => $TABLE));
?>

<form action="" method="post" id="form">
<table class="layout">
<tr><th>Time<td><?php echo html_select("Timing", $trigger_options["Timing"], $row["Timing"], "triggerChange(/^" . preg_quote($TABLE, "/") . "_[ba][iud]$/, '" . js_escape($TABLE) . "', this.form);"); ?>
<tr><th>Event<td><?php echo html_select("Event", $trigger_options["Event"], $row["Event"], "this.form['Timing'].onchange();"); ?>
<?php echo (in_array("UPDATE OF", $trigger_options["Event"]) ? " <input name='Of' value='" . h($row["Of"]) . "' class='hidden'>": ""); ?>
<tr><th>Type<td><?php echo html_select("Type", $trigger_options["Type"], $row["Type"]); ?>
</table>
<p>Name: <input name="Trigger" value="<?php echo h($row["Trigger"]); ?>" data-maxlength="64" autocapitalize="off">
<?php echo script("qs('#form')['Timing'].onchange();"); ?>
<p><?php textarea("Statement", $row["Statement"]); ?>
<p>
<input type="submit" value="Save">
<?php if ($name != "") { ?>
<input type="submit" name="drop" value="Drop"><?php echo confirm(sprintf('Drop %s?', $name)); ?>
<?php } ?>
<?php echo input_token(); ?>
</form>
<?php
} elseif (isset($_GET["user"])) {
	?>
<?php
$USER = $_GET["user"];
$privileges = array("" => array("All privileges" => ""));
foreach (get_rows("SHOW PRIVILEGES") as $row) {
	foreach (explode(",", ($row["Privilege"] == "Grant option" ? "" : $row["Context"])) as $context) {
		$privileges[$context][$row["Privilege"]] = $row["Comment"];
	}
}
$privileges["Server Admin"] += $privileges["File access on server"];
$privileges["Databases"]["Create routine"] = $privileges["Procedures"]["Create routine"]; // MySQL bug #30305
unset($privileges["Procedures"]["Create routine"]);
$privileges["Columns"] = array();
foreach (array("Select", "Insert", "Update", "References") as $val) {
	$privileges["Columns"][$val] = $privileges["Tables"][$val];
}
unset($privileges["Server Admin"]["Usage"]);
foreach ($privileges["Tables"] as $key => $val) {
	unset($privileges["Databases"][$key]);
}

$new_grants = array();
if ($_POST) {
	foreach ($_POST["objects"] as $key => $val) {
		$new_grants[$val] = (array) $new_grants[$val] + idx($_POST["grants"], $key, array());
	}
}
$grants = array();
$old_pass = "";

if (isset($_GET["host"]) && ($result = connection()->query("SHOW GRANTS FOR " . q($USER) . "@" . q($_GET["host"])))) { //! use information_schema for MySQL 5 - column names in column privileges are not escaped
	while ($row = $result->fetch_row()) {
		if (preg_match('~GRANT (.*) ON (.*) TO ~', $row[0], $match) && preg_match_all('~ *([^(,]*[^ ,(])( *\([^)]+\))?~', $match[1], $matches, PREG_SET_ORDER)) { //! escape the part between ON and TO
			foreach ($matches as $val) {
				if ($val[1] != "USAGE") {
					$grants["$match[2]$val[2]"][$val[1]] = true;
				}
				if (preg_match('~ WITH GRANT OPTION~', $row[0])) { //! don't check inside strings and identifiers
					$grants["$match[2]$val[2]"]["GRANT OPTION"] = true;
				}
			}
		}
		if (preg_match("~ IDENTIFIED BY PASSWORD '([^']+)~", $row[0], $match)) {
			$old_pass = $match[1];
		}
	}
}

if ($_POST && !$error) {
	$old_user = (isset($_GET["host"]) ? q($USER) . "@" . q($_GET["host"]) : "''");
	if ($_POST["drop"]) {
		query_redirect("DROP USER $old_user", ME . "privileges=", 'User has been dropped.');
	} else {
		$new_user = q($_POST["user"]) . "@" . q($_POST["host"]); // if $_GET["host"] is not set then $new_user is always different
		$pass = $_POST["pass"];
		if ($pass != '' && !$_POST["hashed"] && !min_version(8)) {
			// compute hash in a separate query so that plain text password is not saved to history
			$pass = get_val("SELECT PASSWORD(" . q($pass) . ")");
			$error = !$pass;
		}

		$created = false;
		if (!$error) {
			if ($old_user != $new_user) {
				$created = queries((min_version(5) ? "CREATE USER" : "GRANT USAGE ON *.* TO") . " $new_user IDENTIFIED BY " . (min_version(8) ? "" : "PASSWORD ") . q($pass));
				$error = !$created;
			} elseif ($pass != $old_pass) {
				queries("SET PASSWORD FOR $new_user = " . q($pass));
			}
		}

		if (!$error) {
			$revoke = array();
			foreach ($new_grants as $object => $grant) {
				if (isset($_GET["grant"])) {
					$grant = array_filter($grant);
				}
				$grant = array_keys($grant);
				if (isset($_GET["grant"])) {
					// no rights to mysql.user table
					$revoke = array_diff(array_keys(array_filter($new_grants[$object], 'strlen')), $grant);
				} elseif ($old_user == $new_user) {
					$old_grant = array_keys((array) $grants[$object]);
					$revoke = array_diff($old_grant, $grant);
					$grant = array_diff($grant, $old_grant);
					unset($grants[$object]);
				}
				if (
					preg_match('~^(.+)\s*(\(.*\))?$~U', $object, $match) && (
					!grant("REVOKE", $revoke, $match[2], " ON $match[1] FROM $new_user") //! SQL injection
					|| !grant("GRANT", $grant, $match[2], " ON $match[1] TO $new_user"))
				) {
					$error = true;
					break;
				}
			}
		}

		if (!$error && isset($_GET["host"])) {
			if ($old_user != $new_user) {
				queries("DROP USER $old_user");
			} elseif (!isset($_GET["grant"])) {
				foreach ($grants as $object => $revoke) {
					if (preg_match('~^(.+)(\(.*\))?$~U', $object, $match)) {
						grant("REVOKE", array_keys($revoke), $match[2], " ON $match[1] FROM $new_user");
					}
				}
			}
		}

		queries_redirect(ME . "privileges=", (isset($_GET["host"]) ? 'User has been altered.' : 'User has been created.'), !$error);

		if ($created) {
			// delete new user in case of an error
			connection()->query("DROP USER $new_user");
		}
	}
}

page_header((isset($_GET["host"]) ? 'Username' . ": " . h("$USER@$_GET[host]") : 'Create user'), $error, array("privileges" => array('', 'Privileges')));

$row = $_POST;
if ($row) {
	$grants = $new_grants;
} else {
	$row = $_GET + array("host" => get_val("SELECT SUBSTRING_INDEX(CURRENT_USER, '@', -1)")); // create user on the same domain by default
	$row["pass"] = $old_pass;
	if ($old_pass != "") {
		$row["hashed"] = true;
	}
	$grants[(DB == "" || $grants ? "" : idf_escape(addcslashes(DB, "%_\\"))) . ".*"] = array();
}

?>
<form action="" method="post">
<table class="layout">
<tr><th>Server<td><input name="host" data-maxlength="60" value="<?php echo h($row["host"]); ?>" autocapitalize="off">
<tr><th>Username<td><input name="user" data-maxlength="80" value="<?php echo h($row["user"]); ?>" autocapitalize="off">
<tr><th>Password<td><input name="pass" id="pass" value="<?php echo h($row["pass"]); ?>" autocomplete="new-password">
<?php echo ($row["hashed"] ? "" : script("typePassword(qs('#pass'));")); ?>
<?php echo (min_version(8) ? "" : checkbox("hashed", 1, $row["hashed"], 'Hashed', "typePassword(this.form['pass'], this.checked);")); ?>
</table>

<?php
//! MAX_* limits, REQUIRE
echo "<table class='odds'>\n";
echo "<thead><tr><th colspan='2'>" . 'Privileges' . doc_link(array('sql' => "grant.html#priv_level"));
$i = 0;
foreach ($grants as $object => $grant) {
	echo '<th>' . ($object != "*.*"
		? "<input name='objects[$i]' value='" . h($object) . "' size='10' autocapitalize='off'>"
		: input_hidden("objects[$i]", "*.*") . "*.*"
	); //! separate db, table, columns, PROCEDURE|FUNCTION, routine
	$i++;
}
echo "</thead>\n";

foreach (
	array(
		"" => "",
		"Server Admin" => 'Server',
		"Databases" => 'Database',
		"Tables" => 'Table',
		"Columns" => 'Column',
		"Procedures" => 'Routine',
	) as $context => $desc
) {
	foreach ((array) $privileges[$context] as $privilege => $comment) {
		echo "<tr><td" . ($desc ? ">$desc<td" : " colspan='2'") . ' lang="en" title="' . h($comment) . '">' . h($privilege);
		$i = 0;
		foreach ($grants as $object => $grant) {
			$name = "'grants[$i][" . h(strtoupper($privilege)) . "]'";
			$value = $grant[strtoupper($privilege)];
			if ($context == "Server Admin" && $object != (isset($grants["*.*"]) ? "*.*" : ".*")) {
				echo "<td>";
			} elseif (isset($_GET["grant"])) {
				echo "<td><select name=$name><option><option value='1'" . ($value ? " selected" : "") . ">" . 'Grant' . "<option value='0'" . ($value == "0" ? " selected" : "") . ">" . 'Revoke' . "</select>";
			} else {
				echo "<td align='center'><label class='block'>";
				echo "<input type='checkbox' name=$name value='1'" . ($value ? " checked" : "") . ($privilege == "All privileges"
					? " id='grants-$i-all'>" //! uncheck all except grant if all is checked
					: ">" . ($privilege == "Grant option" ? "" : script("qsl('input').onclick = function () { if (this.checked) formUncheck('grants-$i-all'); };")));
				echo "</label>";
			}
			$i++;
		}
	}
}

echo "</table>\n";
?>
<p>
<input type="submit" value="Save">
<?php if (isset($_GET["host"])) { ?>
<input type="submit" name="drop" value="Drop"><?php echo confirm(sprintf('Drop %s?', "$USER@$_GET[host]")); ?>
<?php } ?>
<?php echo input_token(); ?>
</form>
<?php
} elseif (isset($_GET["processlist"])) {
	?>
<?php
if (support("kill")) {
	if ($_POST && !$error) {
		$killed = 0;
		foreach ((array) $_POST["kill"] as $val) {
			if (adminer()->killProcess($val)) {
				$killed++;
			}
		}
		queries_redirect(ME . "processlist=", lang_format(array('%d process has been killed.', '%d processes have been killed.'), $killed), $killed || !$_POST["kill"]);
	}
}

page_header('Process list', $error);
?>

<form action="" method="post">
<div class="scrollable">
<table class="nowrap checkable odds">
<?php
echo script("mixin(qsl('table'), {onclick: tableClick, ondblclick: partialArg(tableClick, true)});");
// HTML valid because there is always at least one process
$i = -1;
foreach (adminer()->processList() as $i => $row) {
	if (!$i) {
		echo "<thead><tr lang='en'>" . (support("kill") ? "<th>" : "");
		foreach ($row as $key => $val) {
			echo "<th>$key" . doc_link(array(
				'sql' => "show-processlist.html#processlist_" . strtolower($key),
				'pgsql' => "monitoring-stats.html#PG-STAT-ACTIVITY-VIEW",
				'oracle' => "REFRN30223",
			));
		}
		echo "</thead>\n";
	}
	echo "<tr>" . (support("kill") ? "<td>" . checkbox("kill[]", $row[JUSH == "sql" ? "Id" : "pid"], 0) : "");
	foreach ($row as $key => $val) {
		echo "<td>" . (
			(JUSH == "sql" && $key == "Info" && preg_match("~Query|Killed~", $row["Command"]) && $val != "") ||
			(JUSH == "pgsql" && $key == "current_query" && $val != "<IDLE>") ||
			(JUSH == "oracle" && $key == "sql_text" && $val != "")
			? "<code class='jush-" . JUSH . "'>" . shorten_utf8($val, 100, "</code>") . ' <a href="' . h(ME . ($row["db"] != "" ? "db=" . urlencode($row["db"]) . "&" : "") . "sql=" . urlencode($val)) . '">' . 'Clone' . '</a>'
			: h($val)
		);
	}
	echo "\n";
}
?>
</table>
</div>
<p>
<?php
if (support("kill")) {
	echo ($i + 1) . "/" . sprintf('%d in total', max_connections());
	echo "<p><input type='submit' value='" . 'Kill' . "'>\n";
}
echo input_token();
?>
</form>
<?php echo script("tableCheck();"); ?>
<?php
} elseif (isset($_GET["select"])) {
	?>
<?php
$TABLE = $_GET["select"];
$table_status = table_status1($TABLE);
$indexes = indexes($TABLE);
$fields = fields($TABLE);
$foreign_keys = column_foreign_keys($TABLE);
$oid = $table_status["Oid"];
$adminer_import = get_settings("adminer_import");

$rights = array(); // privilege => 0
$columns = array(); // selectable columns
$search_columns = array(); // searchable columns
$order_columns = array(); // searchable columns
$text_length = "";
foreach ($fields as $key => $field) {
	$name = adminer()->fieldName($field);
	$name_plain = html_entity_decode(strip_tags($name), ENT_QUOTES);
	if (isset($field["privileges"]["select"]) && $name != "") {
		$columns[$key] = $name_plain;
		if (is_shortable($field)) {
			$text_length = adminer()->selectLengthProcess();
		}
	}
	if (isset($field["privileges"]["where"]) && $name != "") {
		$search_columns[$key] = $name_plain;
	}
	if (isset($field["privileges"]["order"]) && $name != "") {
		$order_columns[$key] = $name_plain;
	}
	$rights += $field["privileges"];
}

list($select, $group) = adminer()->selectColumnsProcess($columns, $indexes);
$select = array_unique($select);
$group = array_unique($group);
$is_group = count($group) < count($select);
$where = adminer()->selectSearchProcess($fields, $indexes);
$order = adminer()->selectOrderProcess($fields, $indexes);
$limit = adminer()->selectLimitProcess();

if ($_GET["val"] && is_ajax()) {
	header("Content-Type: text/plain; charset=utf-8");
	foreach ($_GET["val"] as $unique_idf => $row) {
		$as = convert_field($fields[key($row)]);
		$select = array($as ?: idf_escape(key($row)));
		$where[] = where_check($unique_idf, $fields);
		$return = driver()->select($TABLE, $select, $where, $select);
		if ($return) {
			echo first($return->fetch_row());
		}
	}
	exit;
}

$primary = $unselected = array();
foreach ($indexes as $index) {
	if ($index["type"] == "PRIMARY") {
		$primary = array_flip($index["columns"]);
		$unselected = ($select ? $primary : array());
		foreach ($unselected as $key => $val) {
			if (in_array(idf_escape($key), $select)) {
				unset($unselected[$key]);
			}
		}
		break;
	}
}
if ($oid && !$primary) {
	$primary = $unselected = array($oid => 0);
	$indexes[] = array("type" => "PRIMARY", "columns" => array($oid));
}

if ($_POST && !$error) {
	$where_check = $where;
	if (!$_POST["all"] && is_array($_POST["check"])) {
		$checks = array();
		foreach ($_POST["check"] as $check) {
			$checks[] = where_check($check, $fields);
		}
		$where_check[] = "((" . implode(") OR (", $checks) . "))";
	}
	$where_check = ($where_check ? "\nWHERE " . implode(" AND ", $where_check) : "");
	if ($_POST["export"]) {
		save_settings(array("output" => $_POST["output"], "format" => $_POST["format"]), "adminer_import");
		dump_headers($TABLE);
		adminer()->dumpTable($TABLE, "");
		$from = ($select ? implode(", ", $select) : "*")
			. convert_fields($columns, $fields, $select)
			. "\nFROM " . table($TABLE);
		$group_by = ($group && $is_group ? "\nGROUP BY " . implode(", ", $group) : "") . ($order ? "\nORDER BY " . implode(", ", $order) : "");
		$query = "SELECT $from$where_check$group_by";
		if (is_array($_POST["check"]) && !$primary) {
			$union = array();
			foreach ($_POST["check"] as $val) {
				// where is not unique so OR can't be used
				$union[] = "(SELECT" . limit($from, "\nWHERE " . ($where ? implode(" AND ", $where) . " AND " : "") . where_check($val, $fields) . $group_by, 1) . ")";
			}
			$query = implode(" UNION ALL ", $union);
		}
		adminer()->dumpData($TABLE, "table", $query);
		adminer()->dumpFooter();
		exit;
	}

	if (!adminer()->selectEmailProcess($where, $foreign_keys)) {
		if ($_POST["save"] || $_POST["delete"]) { // edit
			$result = true;
			$affected = 0;
			$set = array();
			if (!$_POST["delete"]) {
				foreach ($_POST["fields"] as $name => $val) {
					$val = process_input($fields[$name]);
					if ($val !== null && ($_POST["clone"] || $val !== false)) {
						$set[idf_escape($name)] = ($val !== false ? $val : idf_escape($name));
					}
				}
			}
			if ($_POST["delete"] || $set) {
				$query = ($_POST["clone"] ? "INTO " . table($TABLE) . " (" . implode(", ", array_keys($set)) . ")\nSELECT " . implode(", ", $set) . "\nFROM " . table($TABLE) : "");
				if ($_POST["all"] || ($primary && is_array($_POST["check"])) || $is_group) {
					$result = ($_POST["delete"]
						? driver()->delete($TABLE, $where_check)
						: ($_POST["clone"]
							? queries("INSERT $query$where_check" . driver()->insertReturning($TABLE))
							: driver()->update($TABLE, $set, $where_check)
						)
					);
					$affected = connection()->affected_rows;
					if (is_object($result)) { // PostgreSQL with RETURNING fills num_rows
						$affected += $result->num_rows;
					}
				} else {
					foreach ((array) $_POST["check"] as $val) {
						// where is not unique so OR can't be used
						$where2 = "\nWHERE " . ($where ? implode(" AND ", $where) . " AND " : "") . where_check($val, $fields);
						$result = ($_POST["delete"]
							? driver()->delete($TABLE, $where2, 1)
							: ($_POST["clone"]
								? queries("INSERT" . limit1($TABLE, $query, $where2))
								: driver()->update($TABLE, $set, $where2, 1)
							)
						);
						if (!$result) {
							break;
						}
						$affected += connection()->affected_rows;
					}
				}
			}
			$message = lang_format(array('%d item has been affected.', '%d items have been affected.'), $affected);
			if ($_POST["clone"] && $result && $affected == 1) {
				$last_id = last_id($result);
				if ($last_id) {
					$message = sprintf('Item%s has been inserted.', " $last_id");
				}
			}
			queries_redirect(remove_from_uri($_POST["all"] && $_POST["delete"] ? "page" : ""), $message, $result);
			if (!$_POST["delete"]) {
				$post_fields = (array) $_POST["fields"];
				edit_form($TABLE, array_intersect_key($fields, $post_fields), $post_fields, !$_POST["clone"], $error);
				page_footer();
				exit;
			}

		} elseif (!$_POST["import"]) { // modify
			if (!$_POST["val"]) {
				$error = 'Ctrl+click on a value to modify it.';
			} else {
				$result = true;
				$affected = 0;
				foreach ($_POST["val"] as $unique_idf => $row) {
					$set = array();
					foreach ($row as $key => $val) {
						$key = bracket_escape($key, true); // true - back
						$set[idf_escape($key)] = (preg_match('~char|text~', $fields[$key]["type"]) || $val != "" ? adminer()->processInput($fields[$key], $val) : "NULL");
					}
					$result = driver()->update(
						$TABLE,
						$set,
						" WHERE " . ($where ? implode(" AND ", $where) . " AND " : "") . where_check($unique_idf, $fields),
						($is_group || $primary ? 0 : 1),
						" "
					);
					if (!$result) {
						break;
					}
					$affected += connection()->affected_rows;
				}
				queries_redirect(remove_from_uri(), lang_format(array('%d item has been affected.', '%d items have been affected.'), $affected), $result);
			}

		} elseif (!is_string($file = get_file("csv_file", true))) {
			$error = upload_error($file);
		} elseif (!preg_match('~~u', $file)) {
			$error = 'File must be in UTF-8 encoding.';
		} else {
			save_settings(array("output" => $adminer_import["output"], "format" => $_POST["separator"]), "adminer_import");
			$result = true;
			$cols = array_keys($fields);
			preg_match_all('~(?>"[^"]*"|[^"\r\n]+)+~', $file, $matches);
			$affected = count($matches[0]);
			driver()->begin();
			$separator = ($_POST["separator"] == "csv" ? "," : ($_POST["separator"] == "tsv" ? "\t" : ";"));
			$rows = array();
			foreach ($matches[0] as $key => $val) {
				preg_match_all("~((?>\"[^\"]*\")+|[^$separator]*)$separator~", $val . $separator, $matches2);
				if (!$key && !array_diff($matches2[1], $cols)) { //! doesn't work with column names containing ",\n
					// first row corresponds to column names - use it for table structure
					$cols = $matches2[1];
					$affected--;
				} else {
					$set = array();
					foreach ($matches2[1] as $i => $col) {
						$set[idf_escape($cols[$i])] = ($col == "" && $fields[$cols[$i]]["null"] ? "NULL" : q(preg_match('~^".*"$~s', $col) ? str_replace('""', '"', substr($col, 1, -1)) : $col));
					}
					$rows[] = $set;
				}
			}
			$result = (!$rows || driver()->insertUpdate($TABLE, $rows, $primary));
			if ($result) {
				driver()->commit();
			}
			queries_redirect(remove_from_uri("page"), lang_format(array('%d row has been imported.', '%d rows have been imported.'), $affected), $result);
			driver()->rollback(); // after queries_redirect() to not overwrite error

		}
	}
}

$table_name = adminer()->tableName($table_status);
if (is_ajax()) {
	page_headers();
	ob_start();
} else {
	page_header('Select' . ": $table_name", $error);
}

$set = null;
if (isset($rights["insert"]) || !support("table")) {
	$params = array();
	foreach ((array) $_GET["where"] as $val) {
		if (
			isset($foreign_keys[$val["col"]]) && count($foreign_keys[$val["col"]]) == 1
			&& ($val["op"] == "=" || (!$val["op"] && (is_array($val["val"]) || !preg_match('~[_%]~', $val["val"])))) // LIKE in Editor
		) {
			$params["set" . "[" . bracket_escape($val["col"]) . "]"] = $val["val"];
		}
	}

	$set = $params ? "&" . http_build_query($params) : "";
}
adminer()->selectLinks($table_status, $set);

if (!$columns && support("table")) {
	echo "<p class='error'>" . 'Unable to select the table' . ($fields ? "." : ": " . error()) . "\n";
} else {
	echo "<form action='' id='form'>\n";
	echo "<div style='display: none;'>";
	hidden_fields_get();
	echo (DB != "" ? input_hidden("db", DB) . (isset($_GET["ns"]) ? input_hidden("ns", $_GET["ns"]) : "") : ""); // not used in Editor
	echo input_hidden("select", $TABLE);
	echo "</div>\n";
	adminer()->selectColumnsPrint($select, $columns);
	adminer()->selectSearchPrint($where, $search_columns, $indexes);
	adminer()->selectOrderPrint($order, $order_columns, $indexes);
	adminer()->selectLimitPrint($limit);
	adminer()->selectLengthPrint($text_length);
	adminer()->selectActionPrint($indexes);
	echo "</form>\n";

	$page = $_GET["page"];
	$found_rows = null;
	if ($page == "last") {
		$found_rows = get_val(count_rows($TABLE, $where, $is_group, $group));
		$page = floor(max(0, intval($found_rows) - 1) / $limit);
	}

	$select2 = $select;
	$group2 = $group;
	if (!$select2) {
		$select2[] = "*";
		$convert_fields = convert_fields($columns, $fields, $select);
		if ($convert_fields) {
			$select2[] = substr($convert_fields, 2);
		}
	}
	foreach ($select as $key => $val) {
		$field = $fields[idf_unescape($val)];
		if ($field && ($as = convert_field($field))) {
			$select2[$key] = "$as AS $val";
		}
	}
	if (!$is_group && $unselected) {
		foreach ($unselected as $key => $val) {
			$select2[] = idf_escape($key);
			if ($group2) {
				$group2[] = idf_escape($key);
			}
		}
	}
	$result = driver()->select($TABLE, $select2, $where, $group2, $order, $limit, $page, true);

	if (!$result) {
		echo "<p class='error'>" . error() . "\n";
	} else {
		if (JUSH == "mssql" && $page) {
			$result->seek($limit * $page);
		}
		$email_fields = array();
		echo "<form action='' method='post' enctype='multipart/form-data'>\n";
		$rows = array();
		while ($row = $result->fetch_assoc()) {
			if ($page && JUSH == "oracle") {
				unset($row["RNUM"]);
			}
			$rows[] = $row;
		}

		// use count($rows) without LIMIT, COUNT(*) without grouping, FOUND_ROWS otherwise (slowest)
		if ($_GET["page"] != "last" && $limit && $group && $is_group && JUSH == "sql") {
			$found_rows = get_val(" SELECT FOUND_ROWS()"); // space to allow mysql.trace_mode
		}

		if (!$rows) {
			echo "<p class='message'>" . 'No rows.' . "\n";
		} else {
			$backward_keys = adminer()->backwardKeys($TABLE, $table_name);

			echo "<div class='scrollable'>";
			echo "<table id='table' class='nowrap checkable odds'>";
			echo script("mixin(qs('#table'), {onclick: tableClick, ondblclick: partialArg(tableClick, true), onkeydown: editingKeydown});");
			echo "<thead><tr>" . (!$group && $select
				? ""
				: "<td><input type='checkbox' id='all-page' class='jsonly'>" . script("qs('#all-page').onclick = partial(formCheck, /check/);", "")
					. " <a href='" . h($_GET["modify"] ? remove_from_uri("modify") : $_SERVER["REQUEST_URI"] . "&modify=1") . "'>" . 'Modify' . "</a>");
			$names = array();
			$functions = array();
			reset($select);
			$rank = 1;
			foreach ($rows[0] as $key => $val) {
				if (!isset($unselected[$key])) {
					/** @var array{fun?:string, col?:string} */
					$val = idx($_GET["columns"], key($select)) ?: array();
					$field = $fields[$select ? ($val ? $val["col"] : current($select)) : $key];
					$name = ($field ? adminer()->fieldName($field, $rank) : ($val["fun"] ? "*" : h($key)));
					if ($name != "") {
						$rank++;
						$names[$key] = $name;
						$column = idf_escape($key);
						$href = remove_from_uri('(order|desc)[^=]*|page') . '&order%5B0%5D=' . urlencode($key);
						$desc = "&desc%5B0%5D=1";
						echo "<th id='th[" . h(bracket_escape($key)) . "]'>" . script("mixin(qsl('th'), {onmouseover: partial(columnMouse), onmouseout: partial(columnMouse, ' hidden')});", "");
						$fun = apply_sql_function($val["fun"], $name); //! columns looking like functions
						$sortable = isset($field["privileges"]["order"]) || $fun;
						echo ($sortable ? "<a href='" . h($href . ($order[0] == $column || $order[0] == $key ? $desc : '')) . "'>$fun</a>" : $fun); // $order[0] == $key - COUNT(*)
						echo "<span class='column hidden'>";
						if ($sortable) {
							echo "<a href='" . h($href . $desc) . "' title='" . 'descending' . "' class='text'> ↓</a>";
						}
						if (!$val["fun"] && isset($field["privileges"]["where"])) {
							echo '<a href="#fieldset-search" title="' . 'Search' . '" class="text jsonly"> =</a>';
							echo script("qsl('a').onclick = partial(selectSearch, '" . js_escape($key) . "');");
						}
						echo "</span>";
					}
					$functions[$key] = $val["fun"];
					next($select);
				}
			}

			$lengths = array();
			if ($_GET["modify"]) {
				foreach ($rows as $row) {
					foreach ($row as $key => $val) {
						$lengths[$key] = max($lengths[$key], min(40, strlen(utf8_decode($val))));
					}
				}
			}

			echo ($backward_keys ? "<th>" . 'Relations' : "") . "</thead>\n";

			if (is_ajax()) {
				ob_end_clean();
			}

			foreach (adminer()->rowDescriptions($rows, $foreign_keys) as $n => $row) {
				$unique_array = unique_array($rows[$n], $indexes);
				if (!$unique_array) {
					$unique_array = array();
					reset($select);
					foreach ($rows[$n] as $key => $val) {
						if (!preg_match('~^(COUNT|AVG|GROUP_CONCAT|MAX|MIN|SUM)\(~', current($select))) {
							$unique_array[$key] = $val;
						}
						next($select);
					}
				}
				$unique_idf = "";
				foreach ($unique_array as $key => $val) {
					$field = (array) $fields[$key];
					if ((JUSH == "sql" || JUSH == "pgsql") && preg_match('~char|text|enum|set~', $field["type"]) && strlen($val) > 64) {
						$key = (strpos($key, '(') ? $key : idf_escape($key)); //! columns looking like functions
						$key = "MD5(" . (JUSH != 'sql' || preg_match("~^utf8~", $field["collation"]) ? $key : "CONVERT($key USING " . charset(connection()) . ")") . ")";
						$val = md5($val);
					}
					$unique_idf .= "&" . ($val !== null ? urlencode("where[" . bracket_escape($key) . "]") . "=" . urlencode($val === false ? "f" : $val) : "null%5B%5D=" . urlencode($key));
				}
				echo "<tr>" . (!$group && $select ? "" : "<td>"
					. checkbox("check[]", substr($unique_idf, 1), in_array(substr($unique_idf, 1), (array) $_POST["check"]))
					. ($is_group || information_schema(DB) ? "" : " <a href='" . h(ME . "edit=" . urlencode($TABLE) . $unique_idf) . "' class='edit'>" . 'edit' . "</a>")
				);

				reset($select);
				foreach ($row as $key => $val) {
					if (isset($names[$key])) {
						$column = current($select);
						$field = (array) $fields[$key];
						$val = driver()->value($val, $field);
						if ($val != "" && (!isset($email_fields[$key]) || $email_fields[$key] != "")) {
							$email_fields[$key] = (is_mail($val) ? $names[$key] : ""); //! filled e-mails can be contained on other pages
						}

						$link = "";
						if (is_blob($field) && $val != "") {
							$link = ME . 'download=' . urlencode($TABLE) . '&field=' . urlencode($key) . $unique_idf;
						}
						if (!$link && $val !== null) { // link related items
							foreach ((array) $foreign_keys[$key] as $foreign_key) {
								if (count($foreign_keys[$key]) == 1 || end($foreign_key["source"]) == $key) {
									$link = "";
									foreach ($foreign_key["source"] as $i => $source) {
										$link .= where_link($i, $foreign_key["target"][$i], $rows[$n][$source]);
									}
									$link = ($foreign_key["db"] != "" ? preg_replace('~([?&]db=)[^&]+~', '\1' . urlencode($foreign_key["db"]), ME) : ME) . 'select=' . urlencode($foreign_key["table"]) . $link; // InnoDB supports non-UNIQUE keys
									if ($foreign_key["ns"]) {
										$link = preg_replace('~([?&]ns=)[^&]+~', '\1' . urlencode($foreign_key["ns"]), $link);
									}
									if (count($foreign_key["source"]) == 1) {
										break;
									}
								}
							}
						}
						if ($column == "COUNT(*)") {
							$link = ME . "select=" . urlencode($TABLE);
							$i = 0;
							foreach ((array) $_GET["where"] as $v) {
								if (!array_key_exists($v["col"], $unique_array)) {
									$link .= where_link($i++, $v["col"], $v["val"], $v["op"]);
								}
							}
							foreach ($unique_array as $k => $v) {
								$link .= where_link($i++, $k, $v);
							}
						}

						$html = select_value($val, $link, $field, $text_length);
						$id = h("val[$unique_idf][" . bracket_escape($key) . "]");
						$posted = idx(idx($_POST["val"], $unique_idf), bracket_escape($key));
						$editable = !is_array($row[$key]) && is_utf8($html) && $rows[$n][$key] == $row[$key] && !$functions[$key] && !$field["generated"];
						$type = (preg_match('~^(AVG|MIN|MAX)\((.+)\)~', $column, $match) ? $fields[idf_unescape($match[2])]["type"] : $field["type"]);
						$text = preg_match('~text|json|lob~', $type);
						$is_number = preg_match(number_type(), $type) || preg_match('~^(CHAR_LENGTH|ROUND|FLOOR|CEIL|TIME_TO_SEC|COUNT|SUM)\(~', $column);
						echo "<td id='$id'" . ($is_number && ($val === null || is_numeric(strip_tags($html)) || $type == "money") ? " class='number'" : "");
						if (($_GET["modify"] && $editable && $val !== null) || $posted !== null) {
							$h_value = h($posted !== null ? $posted : $row[$key]);
							echo ">" . ($text ? "<textarea name='$id' cols='30' rows='" . (substr_count($row[$key], "\n") + 1) . "'>$h_value</textarea>" : "<input name='$id' value='$h_value' size='$lengths[$key]'>");
						} else {
							$long = strpos($html, "<i>…</i>");
							echo " data-text='" . ($long ? 2 : ($text ? 1 : 0)) . "'"
								. ($editable ? "" : " data-warning='" . h('Use edit link to modify this value.') . "'")
								. ">$html"
							;
						}
					}
					next($select);
				}

				if ($backward_keys) {
					echo "<td>";
				}
				adminer()->backwardKeysPrint($backward_keys, $rows[$n]);
				echo "</tr>\n"; // close to allow white-space: pre
			}

			if (is_ajax()) {
				exit;
			}
			echo "</table>\n";
			echo "</div>\n";
		}

		if (!is_ajax()) {
			if ($rows || $page) {
				$exact_count = true;
				if ($_GET["page"] != "last") {
					if (!$limit || (count($rows) < $limit && ($rows || !$page))) {
						$found_rows = ($page ? $page * $limit : 0) + count($rows);
					} elseif (JUSH != "sql" || !$is_group) {
						$found_rows = ($is_group ? false : found_rows($table_status, $where));
						if (intval($found_rows) < max(1e4, 2 * ($page + 1) * $limit)) {
							// slow with big tables
							$found_rows = first(slow_query(count_rows($TABLE, $where, $is_group, $group)));
						} elseif (JUSH == 'sql' || JUSH == 'pgsql') {
							$exact_count = false;
						}
					}
				}

				$pagination = ($limit && ($found_rows === false || $found_rows > $limit || $page));
				if ($pagination) {
					echo (($found_rows === false ? count($rows) + 1 : $found_rows - $page * $limit) > $limit
						? '<p><a href="' . h(remove_from_uri("page") . "&page=" . ($page + 1)) . '" class="loadmore">' . 'Load more data' . '</a>'
							. script("qsl('a').onclick = partial(selectLoadMore, $limit, '" . 'Loading' . "…');", "")
						: ''
					);
					echo "\n";
				}

				echo "<div class='footer'><div>\n";
				if ($pagination) {
					// display first, previous 4, next 4 and last page
					$max_page = ($found_rows === false
						? $page + (count($rows) >= $limit ? 2 : 1)
						: floor(($found_rows - 1) / $limit)
					);
					echo "<fieldset>";
					if (JUSH != "simpledb") {
						echo "<legend><a href='" . h(remove_from_uri("page")) . "'>" . 'Page' . "</a></legend>";
						echo script("qsl('a').onclick = function () { pageClick(this.href, +prompt('" . 'Page' . "', '" . ($page + 1) . "')); return false; };");
						echo pagination(0, $page) . ($page > 5 ? " …" : "");
						for ($i = max(1, $page - 4); $i < min($max_page, $page + 5); $i++) {
							echo pagination($i, $page);
						}
						if ($max_page > 0) {
							echo ($page + 5 < $max_page ? " …" : "");
							echo ($exact_count && $found_rows !== false
								? pagination($max_page, $page)
								: " <a href='" . h(remove_from_uri("page") . "&page=last") . "' title='~$max_page'>" . 'last' . "</a>"
							);
						}
					} else {
						echo "<legend>" . 'Page' . "</legend>";
						echo pagination(0, $page) . ($page > 1 ? " …" : "");
						echo ($page ? pagination($page, $page) : "");
						echo ($max_page > $page ? pagination($page + 1, $page) . ($max_page > $page + 1 ? " …" : "") : "");
					}
					echo "</fieldset>\n";
				}

				echo "<fieldset>";
				echo "<legend>" . 'Whole result' . "</legend>";
				$display_rows = ($exact_count ? "" : "~ ") . $found_rows;
				$onclick = "const checked = formChecked(this, /check/); selectCount('selected', this.checked ? '$display_rows' : checked); selectCount('selected2', this.checked || !checked ? '$display_rows' : checked);";
				echo checkbox("all", 1, 0, ($found_rows !== false ? ($exact_count ? "" : "~ ") . lang_format(array('%d row', '%d rows'), $found_rows) : ""), $onclick) . "\n";
				echo "</fieldset>\n";

				if (adminer()->selectCommandPrint()) {
					?>
<fieldset<?php echo ($_GET["modify"] ? '' : ' class="jsonly"'); ?>><legend>Modify</legend><div>
<input type="submit" value="Save"<?php echo ($_GET["modify"] ? '' : ' title="' . 'Ctrl+click on a value to modify it.' . '"'); ?>>
</div></fieldset>
<fieldset><legend>Selected <span id="selected"></span></legend><div>
<input type="submit" name="edit" value="Edit">
<input type="submit" name="clone" value="Clone">
<input type="submit" name="delete" value="Delete"><?php echo confirm(); ?>
</div></fieldset>
<?php
				}

				$format = adminer()->dumpFormat();
				foreach ((array) $_GET["columns"] as $column) {
					if ($column["fun"]) {
						unset($format['sql']);
						break;
					}
				}
				if ($format) {
					print_fieldset("export", 'Export' . " <span id='selected2'></span>");
					$output = adminer()->dumpOutput();
					echo ($output ? html_select("output", $output, $adminer_import["output"]) . " " : "");
					echo html_select("format", $format, $adminer_import["format"]);
					echo " <input type='submit' name='export' value='" . 'Export' . "'>\n";
					echo "</div></fieldset>\n";
				}

				adminer()->selectEmailPrint(array_filter($email_fields, 'strlen'), $columns);
				echo "</div></div>\n";
			}

			if (adminer()->selectImportPrint()) {
				echo "<p>";
				echo "<a href='#import'>" . 'Import' . "</a>";
				echo script("qsl('a').onclick = partial(toggle, 'import');", "");
				echo "<span id='import'" . ($_POST["import"] ? "" : " class='hidden'") . ">: ";
				echo file_input("<input type='file' name='csv_file'> "
					. html_select("separator", array("csv" => "CSV,", "csv;" => "CSV;", "tsv" => "TSV"), $adminer_import["format"])
					. " <input type='submit' name='import' value='" . 'Import' . "'>")
				;
				echo "</span>";
			}

			echo input_token();
			echo "</form>\n";
			echo (!$group && $select ? "" : script("tableCheck();"));
		}
	}
}

if (is_ajax()) {
	ob_end_clean();
	exit;
}

} elseif (isset($_GET["variables"])) {
	?>
<?php
$status = isset($_GET["status"]);
page_header($status ? 'Status' : 'Variables');

$variables = ($status ? adminer()->showStatus() : adminer()->showVariables());
if (!$variables) {
	echo "<p class='message'>" . 'No rows.' . "\n";
} else {
	echo "<table>\n";
	foreach ($variables as $row) {
		echo "<tr>";
		$key = array_shift($row);
		echo "<th><code class='jush-" . JUSH . ($status ? "status" : "set") . "'>" . h($key) . "</code>";
		foreach ($row as $val) {
			echo "<td>" . nl_br(h($val));
		}
	}
	echo "</table>\n";
}

} elseif (isset($_GET["script"])) {
	?>
<?php
header("Content-Type: text/javascript; charset=utf-8");

if ($_GET["script"] == "db") {
	$sums = array("Data_length" => 0, "Index_length" => 0, "Data_free" => 0);
	foreach (table_status() as $name => $table_status) {
		json_row("Comment-$name", h($table_status["Comment"]));
		if (!is_view($table_status) || preg_match('~materialized~i', $table_status["Engine"])) {
			foreach (array("Engine", "Collation") as $key) {
				json_row("$key-$name", h($table_status[$key]));
			}
			foreach ($sums + array("Auto_increment" => 0, "Rows" => 0) as $key => $val) {
				if ($table_status[$key] != "") {
					$val = format_number($table_status[$key]);
					if ($val >= 0) {
						json_row("$key-$name", ($key == "Rows" && $val && $table_status["Engine"] == (JUSH == "pgsql" ? "table" : "InnoDB")
							? "~ $val"
							: $val
						));
					}
					if (isset($sums[$key])) {
						// ignore innodb_file_per_table because it is not active for tables created before it was enabled
						$sums[$key] += ($table_status["Engine"] != "InnoDB" || $key != "Data_free" ? $table_status[$key] : 0);
					}
				} elseif (array_key_exists($key, $table_status)) {
					json_row("$key-$name", "?");
				}
			}
		}
	}
	foreach ($sums as $key => $val) {
		json_row("sum-$key", format_number($val));
	}
	json_row("");

} elseif ($_GET["script"] == "kill") {
	connection()->query("KILL " . number($_POST["kill"]));

} else { // connect
	foreach (count_tables(adminer()->databases()) as $db => $val) {
		json_row("tables-$db", $val);
		json_row("size-$db", db_size($db));
	}
	json_row("");
}

exit; // don't print footer

} else {
	?>
<?php
$tables_views = array_merge((array) $_POST["tables"], (array) $_POST["views"]);

if ($tables_views && !$error && !$_POST["search"]) {
	$result = true;
	$message = "";
	if (JUSH == "sql" && $_POST["tables"] && count($_POST["tables"]) > 1 && ($_POST["drop"] || $_POST["truncate"] || $_POST["copy"])) {
		queries("SET foreign_key_checks = 0"); // allows to truncate or drop several tables at once
	}

	if ($_POST["truncate"]) {
		if ($_POST["tables"]) {
			$result = truncate_tables($_POST["tables"]);
		}
		$message = 'Tables have been truncated.';
	} elseif ($_POST["move"]) {
		$result = move_tables((array) $_POST["tables"], (array) $_POST["views"], $_POST["target"]);
		$message = 'Tables have been moved.';
	} elseif ($_POST["copy"]) {
		$result = copy_tables((array) $_POST["tables"], (array) $_POST["views"], $_POST["target"]);
		$message = 'Tables have been copied.';
	} elseif ($_POST["drop"]) {
		if ($_POST["views"]) {
			$result = drop_views($_POST["views"]);
		}
		if ($result && $_POST["tables"]) {
			$result = drop_tables($_POST["tables"]);
		}
		$message = 'Tables have been dropped.';
	} elseif (JUSH == "sqlite" && $_POST["check"]) {
		foreach ((array) $_POST["tables"] as $table) {
			foreach (get_rows("PRAGMA integrity_check(" . q($table) . ")") as $row) {
				$message .= "<b>" . h($table) . "</b>: " . h($row["integrity_check"]) . "<br>";
			}
		}
	} elseif (JUSH != "sql") {
		$result = (JUSH == "sqlite"
			? queries("VACUUM")
			: apply_queries("VACUUM" . ($_POST["optimize"] ? "" : " ANALYZE"), $_POST["tables"])
		);
		$message = 'Tables have been optimized.';
	} elseif (!$_POST["tables"]) {
		$message = 'No tables.';
	} elseif ($result = queries(($_POST["optimize"] ? "OPTIMIZE" : ($_POST["check"] ? "CHECK" : ($_POST["repair"] ? "REPAIR" : "ANALYZE"))) . " TABLE " . implode(", ", array_map('Adminer\idf_escape', $_POST["tables"])))) {
		while ($row = $result->fetch_assoc()) {
			$message .= "<b>" . h($row["Table"]) . "</b>: " . h($row["Msg_text"]) . "<br>";
		}
	}

	queries_redirect(substr(ME, 0, -1), $message, $result);
}

page_header(($_GET["ns"] == "" ? 'Database' . ": " . h(DB) : 'Schema' . ": " . h($_GET["ns"])), $error, true);

if (adminer()->homepage()) {
	if ($_GET["ns"] !== "") {
		echo "<h3 id='tables-views'>" . 'Tables and views' . "</h3>\n";
		$tables_list = tables_list();
		if (!$tables_list) {
			echo "<p class='message'>" . 'No tables.' . "\n";
		} else {
			echo "<form action='' method='post'>\n";
			if (support("table")) {
				echo "<fieldset><legend>" . 'Search data in tables' . " <span id='selected2'></span></legend><div>";
				echo html_select("op", adminer()->operators(), idx($_POST, "op", JUSH == "elastic" ? "should" : "LIKE %%"));
				echo " <input type='search' name='query' value='" . h($_POST["query"]) . "'>";
				echo script("qsl('input').onkeydown = partialArg(bodyKeydown, 'search');", "");
				echo " <input type='submit' name='search' value='" . 'Search' . "'>\n";
				echo "</div></fieldset>\n";
				if ($_POST["search"] && $_POST["query"] != "") {
					$_GET["where"][0]["op"] = $_POST["op"];
					search_tables();
				}
			}
			echo "<div class='scrollable'>\n";
			echo "<table class='nowrap checkable odds'>\n";
			echo script("mixin(qsl('table'), {onclick: tableClick, ondblclick: partialArg(tableClick, true)});");
			echo '<thead><tr class="wrap">';
			echo '<td><input id="check-all" type="checkbox" class="jsonly">' . script("qs('#check-all').onclick = partial(formCheck, /^(tables|views)\[/);", "");
			echo '<th>' . 'Table';
			$columns = array("Engine" => array('Engine' . doc_link(array('sql' => 'storage-engines.html'))));
			if (collations()) {
				$columns["Collation"] = array('Collation' . doc_link(array('sql' => 'charset-charsets.html', 'mariadb' => 'supported-character-sets-and-collations/')));
			}
			if (function_exists('Adminer\alter_table')) {
				$columns["Data_length"] = array('Data Length' . doc_link(array('sql' => 'show-table-status.html', 'pgsql' => 'functions-admin.html#FUNCTIONS-ADMIN-DBOBJECT', 'oracle' => 'REFRN20286')), "create", 'Alter table');
			}
			if (support('indexes')) {
				$columns["Index_length"] = array('Index Length' . doc_link(array('sql' => 'show-table-status.html', 'pgsql' => 'functions-admin.html#FUNCTIONS-ADMIN-DBOBJECT')), "indexes", 'Alter indexes');
			}
			$columns["Data_free"] = array('Data Free' . doc_link(array('sql' => 'show-table-status.html')), "edit", 'New item');
			if (function_exists('Adminer\alter_table')) {
				$columns["Auto_increment"] = array('Auto Increment' . doc_link(array('sql' => 'example-auto-increment.html', 'mariadb' => 'auto_increment/')), "auto_increment=1&create", 'Alter table');
			}
			$columns["Rows"] = array('Rows' . doc_link(array('sql' => 'show-table-status.html', 'pgsql' => 'catalog-pg-class.html#CATALOG-PG-CLASS', 'oracle' => 'REFRN20286')), "select", 'Select data');
			if (support("comment")) {
				$columns["Comment"] = array('Comment' . doc_link(array('sql' => 'show-table-status.html', 'pgsql' => 'functions-info.html#FUNCTIONS-INFO-COMMENT-TABLE')));
			}
			foreach ($columns as $column) {
				echo "<td>$column[0]";
			}
			echo "</thead>\n";

			$tables = 0;
			foreach ($tables_list as $name => $type) {
				$view = ($type !== null && !preg_match('~table|sequence~i', $type));
				$id = h("Table-" . $name);
				echo '<tr><td>' . checkbox(($view ? "views[]" : "tables[]"), $name, in_array("$name", $tables_views, true), "", "", "", $id); // "$name" to check numeric table names
				echo '<th>' . (support("table") || support("indexes") ? "<a href='" . h(ME) . "table=" . urlencode($name) . "' title='" . 'Show structure' . "' id='$id'>" . h($name) . '</a>' : h($name));
				if ($view && !preg_match('~materialized~i', $type)) {
					$title = 'View';
					echo '<td colspan="6">' . (support("view") ? "<a href='" . h(ME) . "view=" . urlencode($name) . "' title='" . 'Alter view' . "'>$title</a>" : $title);
					echo '<td align="right"><a href="' . h(ME) . "select=" . urlencode($name) . '" title="' . 'Select data' . '">?</a>';
				} else {
					foreach ($columns as $key => $column) {
						$id = " id='$key-" . h($name) . "'";
						echo ($column[1]
							? "<td align='right'><a href='" . h(ME . "$column[1]=") . urlencode($name) . "'$id title='$column[2]'>?</a>"
							: "<td id='$key-" . h($name) . "'>"
						);
					}
					$tables++;
				}
				echo "\n";
			}

			echo "<tr><td><th>" . sprintf('%d in total', count($tables_list));
			echo "<td>" . h(JUSH == "sql" ? get_val("SELECT @@default_storage_engine") : "");
			echo "<td>" . h(db_collation(DB, collations()));
			foreach (array("Data_length", "Index_length", "Data_free") as $key) {
				echo ($columns[$key] ? "<td align='right' id='sum-$key'>" : "");
			}
			echo "\n";

			echo "</table>\n";
			echo script("ajaxSetHtml('" . js_escape(ME) . "script=db');");
			echo "</div>\n";
			if (!information_schema(DB)) {
				echo "<div class='footer'><div>\n";
				$vacuum = "<input type='submit' value='" . 'Vacuum' . "'> " . on_help("'VACUUM'");
				$optimize = "<input type='submit' name='optimize' value='" . 'Optimize' . "'> " . on_help(JUSH == "sql" ? "'OPTIMIZE TABLE'" : "'VACUUM OPTIMIZE'");
				echo "<fieldset><legend>" . 'Selected' . " <span id='selected'></span></legend><div>"
				. (JUSH == "sqlite" ? $vacuum . "<input type='submit' name='check' value='" . 'Check' . "'> " . on_help("'PRAGMA integrity_check'")
				: (JUSH == "pgsql" ? $vacuum . $optimize
				: (JUSH == "sql" ? "<input type='submit' value='" . 'Analyze' . "'> " . on_help("'ANALYZE TABLE'")
					. $optimize
					. "<input type='submit' name='check' value='" . 'Check' . "'> " . on_help("'CHECK TABLE'")
					. "<input type='submit' name='repair' value='" . 'Repair' . "'> " . on_help("'REPAIR TABLE'")
				: "")))
				. "<input type='submit' name='truncate' value='" . 'Truncate' . "'> " . on_help(JUSH == "sqlite" ? "'DELETE'" : "'TRUNCATE" . (JUSH == "pgsql" ? "'" : " TABLE'")) . confirm()
				. "<input type='submit' name='drop' value='" . 'Drop' . "'>" . on_help("'DROP TABLE'") . confirm() . "\n";
				$databases = (support("scheme") ? adminer()->schemas() : adminer()->databases());
				echo "</div></fieldset>\n";
				$script = "";
				if (count($databases) != 1 && JUSH != "sqlite") {
					echo "<fieldset><legend>" . 'Move to other database' . " <span id='selected3'></span></legend><div>";
					$db = (isset($_POST["target"]) ? $_POST["target"] : (support("scheme") ? $_GET["ns"] : DB));
					echo ($databases ? html_select("target", $databases, $db) : '<input name="target" value="' . h($db) . '" autocapitalize="off">');
					echo "</label> <input type='submit' name='move' value='" . 'Move' . "'>";
					echo (support("copy") ? " <input type='submit' name='copy' value='" . 'Copy' . "'> " . checkbox("overwrite", 1, $_POST["overwrite"], 'overwrite') : "");
					echo "</div></fieldset>\n";
					$script = " selectCount('selected3', formChecked(this, /^(tables|views)\[/));";
				}
				echo "<input type='hidden' name='all' value=''>"; // used by trCheck()
				echo script("qsl('input').onclick = function () { selectCount('selected', formChecked(this, /^(tables|views)\[/));"
					. (support("table") ? " selectCount('selected2', formChecked(this, /^tables\[/) || $tables);" : "")
					. "$script }")
				;
				echo input_token();
				echo "</div></div>\n";
			}
			echo "</form>\n";
			echo script("tableCheck();");
		}

		echo (function_exists('Adminer\alter_table') ? "<p class='links'><a href='" . h(ME) . "create='>" . 'Create table' . "</a>\n" : '');
		echo (support("view") ? "<a href='" . h(ME) . "view='>" . 'Create view' . "</a>\n" : "");

		if (support("routine")) {
			echo "<h3 id='routines'>" . 'Routines' . "</h3>\n";
			$routines = routines();
			if ($routines) {
				echo "<table class='odds'>\n";
				echo '<thead><tr><th>' . 'Name' . '<td>' . 'Type' . '<td>' . 'Return type' . "<td></thead>\n";
				foreach ($routines as $row) {
					$name = ($row["SPECIFIC_NAME"] == $row["ROUTINE_NAME"] ? "" : "&name=" . urlencode($row["ROUTINE_NAME"])); // not computed on the pages to be able to print the header first
					echo '<tr>';
					echo '<th><a href="' . h(ME . ($row["ROUTINE_TYPE"] != "PROCEDURE" ? 'callf=' : 'call=') . urlencode($row["SPECIFIC_NAME"]) . $name) . '">' . h($row["ROUTINE_NAME"]) . '</a>';
					echo '<td>' . h($row["ROUTINE_TYPE"]);
					echo '<td>' . h($row["DTD_IDENTIFIER"]);
					echo '<td><a href="' . h(ME . ($row["ROUTINE_TYPE"] != "PROCEDURE" ? 'function=' : 'procedure=') . urlencode($row["SPECIFIC_NAME"]) . $name) . '">' . 'Alter' . "</a>";
				}
				echo "</table>\n";
			}
			echo '<p class="links">'
				. (support("procedure") ? '<a href="' . h(ME) . 'procedure=">' . 'Create procedure' . '</a>' : '')
				. '<a href="' . h(ME) . 'function=">' . 'Create function' . "</a>\n"
			;
		}

		if (support("sequence")) {
			echo "<h3 id='sequences'>" . 'Sequences' . "</h3>\n";
			$sequences = get_vals("SELECT sequence_name FROM information_schema.sequences WHERE sequence_schema = current_schema() ORDER BY sequence_name");
			if ($sequences) {
				echo "<table class='odds'>\n";
				echo "<thead><tr><th>" . 'Name' . "</thead>\n";
				foreach ($sequences as $val) {
					echo "<tr><th><a href='" . h(ME) . "sequence=" . urlencode($val) . "'>" . h($val) . "</a>\n";
				}
				echo "</table>\n";
			}
			echo "<p class='links'><a href='" . h(ME) . "sequence='>" . 'Create sequence' . "</a>\n";
		}

		if (support("type")) {
			echo "<h3 id='user-types'>" . 'User types' . "</h3>\n";
			$user_types = types();
			if ($user_types) {
				echo "<table class='odds'>\n";
				echo "<thead><tr><th>" . 'Name' . "</thead>\n";
				foreach ($user_types as $val) {
					echo "<tr><th><a href='" . h(ME) . "type=" . urlencode($val) . "'>" . h($val) . "</a>\n";
				}
				echo "</table>\n";
			}
			echo "<p class='links'><a href='" . h(ME) . "type='>" . 'Create type' . "</a>\n";
		}

		if (support("event")) {
			echo "<h3 id='events'>" . 'Events' . "</h3>\n";
			$rows = get_rows("SHOW EVENTS");
			if ($rows) {
				echo "<table>\n";
				echo "<thead><tr><th>" . 'Name' . "<td>" . 'Schedule' . "<td>" . 'Start' . "<td>" . 'End' . "<td></thead>\n";
				foreach ($rows as $row) {
					echo "<tr>";
					echo "<th>" . h($row["Name"]);
					echo "<td>" . ($row["Execute at"] ? 'At given time' . "<td>" . $row["Execute at"] : 'Every' . " " . $row["Interval value"] . " " . $row["Interval field"] . "<td>$row[Starts]");
					echo "<td>$row[Ends]";
					echo '<td><a href="' . h(ME) . 'event=' . urlencode($row["Name"]) . '">' . 'Alter' . '</a>';
				}
				echo "</table>\n";
				$event_scheduler = get_val("SELECT @@event_scheduler");
				if ($event_scheduler && $event_scheduler != "ON") {
					echo "<p class='error'><code class='jush-sqlset'>event_scheduler</code>: " . h($event_scheduler) . "\n";
				}
			}
			echo '<p class="links"><a href="' . h(ME) . 'event=">' . 'Create event' . "</a>\n";
		}
	}
}

}

// each page calls its own page_header(), if the footer should not be called then the page exits
page_footer();

<?php
	
	require_once(EXTENSIONS . '/cdi/lib/class.cdiutil.php');
	require_once(EXTENSIONS . '/cdi/lib/class.cdimaster.php');
	require_once(EXTENSIONS . '/cdi/lib/class.cdidbsync.php');

	class CdiLogQuery {
		private static $isUpdating;
	
		public static function isUpdating($status) {
			self::$isUpdating = $status;
		}
		
		public static function persistQueries() {
			// Prevent execution on the frontend and check configuration conditions
			// Do not log the query when CDI is disabled, in SLAVE mode or busy executing queries.
			// Additionally if the logger is not installed, you should not be able to call this function
			if((!class_exists('Administration')) || !CdiUtil::isEnabled() || self::$isUpdating || !CdiUtil::isLoggerInstalled()) {
				return true;
			}
			
			$queryLog = Symphony::Database()->fetch("SELECT * FROM `tbl_query_log`");
			foreach($queryLog as $entry) {
				$ts = $entry["time"];
				$query = base64_decode($entry["query_base64"]);
				self::log($query,$ts);
			}
		}
		
		/**
		 * The CdiQueryLog::log() function is called from the Database implementation responsible for executing Symphony SQL queries
		 * If in MASTER mode, CDI will save the query to disk allowing it to be committed to the VCS. From there it will be available
		 * for automatic query exection by CDI slave instances (see also CdiLogQuery::executeQueries()).
		 * @param String $query
		 */
		public static function log($query,$timestamp) {
			// Prevent execution on the frontend and check configuration conditions
			// Do not log the query when CDI is disabled, in SLAVE mode or busy executing queries.
			// Additionally if the logger is not installed, you should not be able to call this function
			if((!class_exists('Administration')) || !CdiUtil::isEnabled() || self::$isUpdating || !CdiUtil::isLoggerInstalled()) {
				return true;
			}
			
			$query = trim($query);
			$tbl_prefix = Symphony::Configuration()->get('tbl_prefix', 'database');
	
			/* FILTERS */
			// do not register changes to tbl_cdi_log
			if (preg_match("/{$tbl_prefix}cdi_log/i", $query)) return true;
			// only structural changes, no SELECT statements
			if (!preg_match('/^(insert|update|delete|create|drop|alter|rename)/i', $query)) return true;
			// un-tracked tables (sessions, cache, authors)
			if (preg_match("/{$tbl_prefix}(authors|cache|forgotpass|sessions)/i", $query)) return true;
			// content updates in tbl_entries (includes tbl_entries_fields_*)
			if (preg_match('/^(insert|delete|update)/i', $query) && preg_match("/({$config->tbl_prefix}entries)/i", $query)) return true;
			// append query delimeter if it doesn't exist
			if (!preg_match('/;$/', $query)) $query .= ";";

			// Replace the table prefix in the query
			// This allows query execution on slave instances with different table prefix.
			$query = str_replace($tbl_prefix,'tbl_',$query);
			
			// We've come far enough... let's try to save it to disk!
			if(CdiUtil::isCdiMaster()) {
				return CdiMaster::persistQuery($query,$timestamp);
			} else if(CdiUtil::isCdiDBSyncMaster()) {
				return CdiDBSync::persistQuery($query,$timestamp);
			} else {
				//TODO: error handling for the unusual event that we are dealing with here.
				return true;
			}
		}
	
		/**
		 * The rollback() function removes the query either from file (MASTER) or from the CDI log (SLAVE)
		 * @param String $hash The MD5 hash created using the SQL statement, the timestamp and the execution order
		 * @param Timestamp $timestamp The UNIX timestamp on which the query was originally logged
		 * @param Integer $order The execution order of the query (in case of multiple executions with the same timestamp)
		 */
		public static function rollback($hash,$timestamp,$order) {
			// do not rollback erronous changes to tbl_cdi_log
			$tbl_prefix = Symphony::Configuration()->get('tbl_prefix', 'database');
			if (preg_match("/{$tbl_prefix}cdi_log/i", $query)) return true;
			
			try {
				if(CdiUtil::isCdiSlave()) {
					// On the SLAVE instance we need to remove the execution log entry from the database
					Symphony::Database()->query("DELETE FROM `tbl_cdi_log` WHERE `query_hash` LIKE '" . $hash . "'");
				} else if(CdiUtil::isCdiMaster()) {
					// On the MASTER instance we need to remove the persisted SQL Statement from disk (if it exists)
					$entries = self::getCdiLogEntries();
					unset($entries[$hash]);
					file_put_contents(CDI_FILE, json_encode($entries));
				} else {
					throw new Exception("Invalid value for the CDI 'mode' configuration option, 'CdiMaster' or 'CdiSlave' expected.");
				}
			} catch(Exception $e) {
				//TODO: think of some smart way of dealing with errors, perhaps through the preference screen or a CDI Status content page?
				//In this case it is perhaps better to simply throw the exception because the rollback failed.
				throw $e;
			}
		}
		
		/**
		 * Returns the SQL statements files from the Manifest folder
		 */
		public static function getCdiLogEntries() {
			$entries = array();
			if(file_exists(CDI_FILE)) {
				$contents = file_get_contents(CDI_FILE);
				$entries = json_decode($contents, true);
				sort($entries);
			}
			return $entries;
		}
	}
		
?>
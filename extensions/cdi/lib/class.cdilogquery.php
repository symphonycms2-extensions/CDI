<?php

	define('CDIROOT',MANIFEST . '/cdi',false);
	define('CDI_FILE', CDIROOT . '/cdi.sql');
	define('DB_SYNC_FILE', CDIROOT . '/db_sync.sql');
	
	class CdiLogQuery {
		
		private static $isUpdating;
		private static $lastEntryTS;
		private static $lastEntryOrder;
		private static $meta_written = FALSE;
	
		/**
		 * Return the current author.
		 * Only available if the author is logged into Symphony backend.
		 */
		private static function getAuthor() {
			$author = Administration::instance()->Author;
			if (isset($author)) { 
				return $author->getFullName(); 
			} else { 
				return ""; 
			}
		}
		
		/**
		 * Return the current URL from which the query is executed.
		 * Only available if the query is executed from the Symphony backend.
		 */
		private static function getURL() {
			$url = Administration::instance()->getCurrentPageURL();
			if (is_null($url)) { $url = ""; }
			return $url;
		}
		
		/**
		 * The CdiQueryLog::log() function is called from the Database implementation responsible for executing Symphony SQL queries
		 * If in MASTER mode, CDI will save the query to disk allowing it to be committed to the VCS. From there it will be available
		 * for automatic query exection by CDI slave instances (see also CdiLogQuery::executeQueries()).
		 * @param String $query
		 */
		public static function log($query) {
			// Prevent execution on the frontend and check configuration conditions
			// Do not log the query when CDI is disabled, in SLAVE mode or busy executing queries.
			if((!class_exists('Administration')) ||
			   (Symphony::Configuration()->get('enabled', 'cdi') == 'no') ||
			   (Symphony::Configuration()->get('is-slave', 'cdi') == 'yes') ||
			   (CdiLogQuery::$isUpdating)) { return true; }
			
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
	
			// We've come far enough... let's try to save it to disk!
			if(Symphony::Configuration()->get('cdi-mode', 'cdi') == 'db_sync') {
				return CdiLogQuery::persistQueryDBSync($query);
			} else {
				return CdiLogQuery::persistQueryCDI($query);
			}
		}
	
		private static function persistQueryDBSync($query) {
			$line = '';
	
			if(self::$meta_written == FALSE) {
	
				$line .= "\n" . '-- ' . date('Y-m-d H:i:s', time());
	
				$author = Administration::instance()->Author;
				if (isset($author)) $line .= ', ' . $author->getFullName();
	
				$url = Administration::instance()->getCurrentPageURL();
				if (!is_null($url)) $line .= ', ' . $url;
	
				$line .= ";\n";
	
				self::$meta_written = TRUE;
	
			}

			$line .= $query . "\n";
			
			$logfile = DB_SYNC_FILE;
			$handle = @fopen($logfile, 'a');
			fwrite($handle, $line);
			fclose($handle);			
		}		
		
		/**
		 * If it is proven to be a valid SQL Statement worthy of logging, the persistQuery() function will
		 * write the statement to file and log it
		 * @param string $query The SQL Statement that will be saved to file and CDI log
		 */
		private static function persistQueryCDI($query) {
			try {
				$ts = time();
		
				if(CdiLogQuery::$lastEntryTS != $ts) {
					CdiLogQuery::$lastEntryTS = $ts;
					CdiLogQuery::$lastEntryOrder = 0;
				} else {
					CdiLogQuery::$lastEntryOrder++;
				}

				// Replace the table prefix in the query
				// This allows query execution on slave instances with different table prefix.
				$tbl_prefix = Symphony::Configuration()->get('tbl_prefix', 'database');
				$query = str_replace($tbl_prefix,'tbl_',$query);
				
				$id = $ts . '-' . CdiLogQuery::$lastEntryOrder;
				$hash = md5($id . $query);
				$date = date('Y-m-d H:i:s', $ts);
				
				try{
					//We are only logging this to file because we do not execute CDI queries on the MASTER instance
					//The database table `tbl_cdi_log` is removed from the database on the MASTER instance.
					//It is the repsonsibility of the user to ensure that they only have a single MASTER instance 
					//and that they protect the integrity of the Symphony database
					$entries = CdiLogQuery::getCdiLogEntries();
					$entries[$id] = array(0 => $ts, 1 => CdiLogQuery::$lastEntryOrder, 2 => $hash, 3 => $query);
					file_put_contents(CDI_FILE, json_encode($entries));
					return true;
				} catch(Exception $e) {
					//TODO: think of some smart way of dealing with errors, perhaps through the preference screen or a CDI Status content page?
					CdiLogQuery::rollback($hash,$ts,$order);
					return false;
				}
			} catch(Exception $e) {
				//TODO: think of some smart way of dealing with errors, perhaps through the preference screen or a CDI Status content page?
				return false;
			}
		}
	
		/**
		 * The rollback() function removes the query either from file (MASTER) or from the CDI log (SLAVE)
		 * @param String $hash The MD5 hash created using the SQL statement, the timestamp and the execution order
		 * @param Timestamp $timestamp The UNIX timestamp on which the query was originally logged
		 * @param Integer $order The execution order of the query (in case of multiple executions with the same timestamp)
		 */
		private static function rollback($hash,$timestamp,$order) {
			// do not rollback erronous changes to tbl_cdi_log
			$tbl_prefix = Symphony::Configuration()->get('tbl_prefix', 'database');
			if (preg_match("/{$tbl_prefix}cdi_log/i", $query)) return true;
			
			try {
				if(Symphony::Configuration()->get('is-slave', 'cdi') == 'yes') {
					// On the SLAVE instance we need to remove the execution log entry from the database
					Symphony::Database()->query("DELETE FROM `tbl_cdi_log` WHERE `query_hash` LIKE '" . $hash . "'");
				} else if(Symphony::Configuration()->get('is-slave', 'cdi') == 'no') {
					// On the MASTER instance we need to remove the persisted SQL Statement from disk (if it exists)
					$entries = CdiLogQuery::getCdiLogEntries();
					unset($entries[$hash]);
					file_put_contents(CDI_FILE, json_encode($entries));
				} else {
					throw new Exception("Invalid value for the CDI is-slave configuration option, 'yes' or 'no' expected.");
				}
			} catch(Exception $e) {
				//TODO: think of some smart way of dealing with errors, perhaps through the preference screen or a CDI Status content page?
				//In this case it is perhaps better to simply throw the exception because the rollback failed.
				throw $e;
			}
		}
		
		/**
		 * The executeQueries() function will try to execute all SQL statements that are available in the manifest folder.
		 * It will check the CDI log to see if the statement has already been executed based on the MD5 hash.
		 * This function can only be called by SLAVE instances
		 */
		public static function executeQueries() {
			// We should not be processing any queries when the extension is disabled or when we are the Master instance
			// Check also exists on content page, but just to be sure!
			if((!class_exists('Administration')) ||
			   (Symphony::Configuration()->get('enabled', 'cdi') == 'no') ||
			   (Symphony::Configuration()->get('is-slave', 'cdi') == 'no')) {
				echo "WARNING: CDI is disabled or you are running the queryies on the Master instance. No queries have been executed.";
				return;
			}
	
			// Prevent the CdiLogQuery::log() from persisting queries that are executed by CDI itself
			// Technically this should not be possible because it will not log queries on a SLAVE instance
			// and you can only run the executeQueries when in SLAVE mode. This is just to be sure.
			CdiLogQuery::$isUpdating = true;
			
			try {
				$skipped = 0;
				$executed = 0;
	
				$entries = CdiLogQuery::getCdiLogEntries();
				foreach($entries as $entry) {
					$ts = $entry[0];
					$order = $entry[1];
					$hash = $entry[2];
					$query = $entry[3];
					$date = date('Y-m-d H:i:s', $ts);
	
					// Replace the table prefix in the query
					// Rename the generic table prefix to the prefix used by this instance
					$tbl_prefix = Symphony::Configuration()->get('tbl_prefix', 'database');
					$query = str_replace('tbl_', $tbl_prefix, $query);
					
					try {
						// Look for available CDI log entries based on the provided MD5 hash
						$cdiLogEntry = Symphony::Database()->fetchRow(0,"SELECT * FROM tbl_cdi_log WHERE `query_hash` LIKE '" . $hash . "'");
						
						if(empty($cdiLogEntry)) {
							// The query has not been found in the log, thus it has not been executed
							// So let's execute the query and add it to the log!
							Symphony::Database()->query("INSERT INTO `tbl_cdi_log` (`query_hash`,`author`,`url`,`date`,`order`)
														 VALUES ('" . $hash . "','" . CdiLogQuery::getAuthor() . "','" . CdiLogQuery::getURL() . "','" . $date . "'," . $order . ")");
							Symphony::Database()->query($query);
							$executed++;
						} else {
							// The query has already been executed, let's do nothing;
							$skipped++;
						}
					} catch (Exception $e) {
						//TODO: think of some smart way of dealing with errors, perhaps through the preference screen or a CDI Status content page?
						//Due to the error we need to perform a rollback to allow this query to be executed at a later stage.
						CdiLogQuery::rollback($hash,$ts,$order);
						echo "ERROR: " . $e->getMessage() , ". Rollback has been executed.";
						die();
					}
				}
				
				echo "OK: " . $executed . " queries executed, " . $skipped . " skipped.";
			} catch (Exception $e) {
				//TODO: think of some smart way of dealing with errors, perhaps through the preference screen or a CDI Status content page?
				echo "ERROR: " . $e->getMessage();
			}

			// Save the last update date to configuration
			Symphony::Configuration()->set('last-update', 'cdi', time());
			Administration::instance()->saveConfig();
			
			// Re-enable CdiLogQuery::log() to persist queries
			CdiLogQuery::$isUpdating = false;
		}
	
		public static function importSyncFile() {
			// We should not be processing any queries when the extension is disabled or when it is in 'Continuous Database Integration' mode
			if((!class_exists('Administration'))) { //||
			   //(Symphony::Configuration()->get('enabled', 'cdi') == 'no') ||
			   //(Symphony::Configuration()->get('cdi-mode', 'cdi') == 'db_sync')) {
			   	throw new Exception("You can only import the Database Synchroniser file from the Preferences page");
			}
			
			// Prevent the CdiLogQuery::log() from persisting queries that are executed by CDI itself
			CdiLogQuery::$isUpdating = true;

			// Handle file upload
			$syncFile = DB_SYNC_FILE;
			if(isset($_FILES['cdi_import_file'])) {
				$syncFile = $_FILES['cdi_import_file']['tmp_name'];
			}
			
			//Execute the queries from file
			try {
				if(file_exists($syncFile)) {
					$contents = file_get_contents($syncFile);
					$queries = split(';',$contents);
					foreach($queries as $query) {
						$query = trim($query);
						// ommit comments and empty statements
						if(!preg_match('/^--/i', $query) && !$query=='') {
							Symphony::Database()->query($query);
						}
					}
				}
			} catch (Exception $e) {
				// Re-enable CdiLogQuery::log() to persist queries
				CdiLogQuery::$isUpdating = false;
				throw $e;
			}

			// Save the last update date to configuration
			Symphony::Configuration()->set('last-update', 'cdi', time());
			Administration::instance()->saveConfig();
			
			// Re-enable CdiLogQuery::log() to persist queries
			CdiLogQuery::$isUpdating = false;
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
		
		/**
		 * Removes all the stored log files from disk
		 * This function can only be called by MASTER instances
		 */
		public static function cleanLogs($completely) {
			// We should not be removing any files from SLAVE instances
			if((!class_exists('Administration')) || !defined('CDIROOT') ||
			   (Symphony::Configuration()->get('enabled', 'cdi') == 'no') ||
			   (Symphony::Configuration()->get('is-slave', 'cdi') == 'yes')) {
			   	throw new Exception("Can not remove CDI log files from disk, this action is only available for MASTER instances and from within the Symphony Backend");
			}
	
			if(file_exists(CDIROOT)) {
				if(file_exists(CDI_FILE)) { unlink(CDI_FILE); }
				if($completely) {
					if(file_exists(DB_SYNC_FILE)) { unlink(DB_SYNC_FILE); }
					rmdir(CDIROOT);
				}
			}
		}
	}

?>
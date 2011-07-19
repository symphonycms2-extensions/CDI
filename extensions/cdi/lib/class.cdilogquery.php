<?php

	define("CDIROOT",MANIFEST . '/cdi',false);
	
	class CdiLogQuery {
		
		private static $isUpdating;
		private static $lastEntryTS;
		private static $lastEntryOrder;
	
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
			return CdiLogQuery::persistQuery($query);
		}
	
		/**
		 * If it is proven to be a valid SQL Statement worthy of logging, the persistQuery() function will
		 * write the statement to file and log it
		 * @param string $query The SQL Statement that will be saved to file and CDI log
		 */
		private static function persistQuery($query) {
			try {
				$ts = time();
		
				if(CdiLogQuery::$lastEntryTS != $ts) {
					CdiLogQuery::$lastEntryTS = $ts;
					CdiLogQuery::$lastEntryOrder = 0;
				} else {
					CdiLogQuery::$lastEntryOrder++;
				}
		
				$hash = md5($query . $ts . CdiLogQuery::$lastEntryOrder);
				$date = date('Y-m-d H:i:s', $ts);
				
				try{
					//We are only logging this to file because we do not execute CDI queries on the MASTER instance
					//The database table `tbl_cdi_log` is removed from the database on the MASTER instance.
					//It is the repsonsibility of the user to ensure that they only have a single MASTER instance 
					//and that they protect the integrity of the Symphony database
					$logfile = MANIFEST . '/cdi/' . $ts . '-' . CdiLogQuery::$lastEntryOrder . '-' . $hash . '.sql';
					file_put_contents($logfile,$query);
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
			try {
				if(Symphony::Configuration()->get('is-slave', 'cdi') == 'yes') {
					// On the SLAVE instance we need to remove the execution log entry from the database
					Symphony::Database()->query("DELETE FROM `tbl_cdi_log` WHERE `query_hash` LIKE '" . $hash . "'");
				} else if(Symphony::Configuration()->get('is-slave', 'cdi') == 'no') {
					// On the MASTER instance we need to remove the persisted SQL Statement from disk (if it exists)
					$logfile = MANIFEST . '/cdi/' . $ts . '-' . CdiLogQuery::$lastEntryOrder . '-' . $hash . '.sql';
					if(file_exists($logfile)) {
						unlink($logfile);
					}
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
	
				$files = CdiLogQuery::getCdiLogFiles();
				foreach($files as $file) {
					$parts = split('-',$file);
					$ts = $parts[0];
					$order = $parts[1];
					$hash = $parts[2];
					$date = date('Y-m-d H:i:s', $ts);
	
					try {
						// Look for available CDI log entries based on the provided MD5 hash
						$cdiLogEntry = Symphony::Database()->fetchRow(0,"SELECT * FROM tbl_cdi_log WHERE `query_hash` LIKE '" . $hash . "'");
						
						if(empty($cdiLogEntry)) {
							// The query has not been found in the log, thus it has not been executed
							// So let's execute the query and add it to the log!
							$query = file_get_contents(MANIFEST . '/cdi/' . $file . '.sql');
							Symphony::Database()->query("INSERT INTO `tbl_cdi_log` (`query_hash`,`author`,`url`,`date`,`order`)
														 VALUES ('" . $parts[2] . "','" . CdiLogQuery::getAuthor() . "','" . CdiLogQuery::getURL() . "','" . $date . "'," . $order . ")");
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
					}
				}
				
				echo "OK: " . $executed . " queries executed, " . $skipped . " skipped.";
			} catch (Exception $e) {
				//TODO: think of some smart way of dealing with errors, perhaps through the preference screen or a CDI Status content page?
				echo "ERROR: " . $e->getMessage();
			}
	
			// Enable CdiLogQuery::log() from persisting queries
			CdiLogQuery::$isUpdating = false;
		}
	
		/**
		 * Returns the SQL statements files from the Manifest folder
		 */
		public static function getCdiLogFiles() {
			$files = array();
			if(file_exists(MANIFEST . '/cdi/')) {
				if($handle = opendir(MANIFEST . '/cdi/')) {
				    while (false !== ($file = readdir($handle))) {
				    	if($file != '.' && $file != '..') {
				    		if(strpos($file,".sql") !== false) {
					        	$files[] = str_replace('.sql', '', $file);
				    		}
				    	}
				    }
				    closedir($handle);
				}
				sort($files);
			}
			return $files;
		}
		
		/**
		 * Removes all the stored log files from disk
		 * This function can only be called by MASTER instances
		 */
		public static function cleanLogs() {
			// We should not be removing any files from SLAVE instances
			if((!class_exists('Administration')) || !defined('CDIROOT') ||
			   (Symphony::Configuration()->get('enabled', 'cdi') == 'no') ||
			   (Symphony::Configuration()->get('is-slave', 'cdi') == 'yes')) {
			   	throw new Exception("Can not remove CDI log files from disk, this action is only available for MASTER instances and from within the Symphony Backend");
			}
	
			if(file_exists(CDIROOT)) {
				$files = CdiLogQuery::getCdiLogFiles();
				foreach($files as $file) { unlink(CDIROOT . '/' . $file . '.sql'); }
				rmdir(CDIROOT);
			}
		}
	}

?>
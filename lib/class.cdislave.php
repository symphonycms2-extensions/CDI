<?php

	require_once(EXTENSIONS . '/cdi/lib/class.cdiutil.php');
	require_once(EXTENSIONS . '/cdi/lib/class.cdilogquery.php');
	
	class CdiSlave {

		public static function install() {
			self::uninstall();
			Symphony::Database()->query("CREATE TABLE IF NOT EXISTS `tbl_cdi_log` (
				  `date` DATETIME NOT NULL,
				  `order` int(4),
				  `author` VARCHAR(255) NOT NULL,
				  `url` VARCHAR(255) NOT NULL,
				  `query_hash` VARCHAR(255) NOT NULL)");
			if (!file_exists(CDIROOT)) { mkdir(CDIROOT); }
		}
		
		public static function uninstall() {
			Symphony::Database()->query("DROP TABLE IF EXISTS `tbl_cdi_log`");
			if(file_exists(CDI_FILE)) { unlink(CDI_FILE); }
		}
		
		/**
		 * The executeQueries() function will try to execute all SQL statements that are available in the manifest folder.
		 * It will check the CDI log to see if the statement has already been executed based on the MD5 hash.
		 * This function can only be called by SLAVE instances
		 */
		public static function update() {
			// We should not be processing any queries when the extension is disabled or when we are the Master instance
			// Check also exists on content page, but just to be sure!
			if((!class_exists('Administration')) || !CdiUtil::isEnabled() || !CdiUtil::isCdiSlave()) {
				echo "WARNING: CDI is disabled or you are running the queryies on the Master instance. No queries have been executed.";
				return;
			}
	
			// Prevent the CdiLogQuery::log() from persisting queries that are executed by CDI itself
			// Technically this should not be possible because it will not log queries on a SLAVE instance
			// and you can only run the executeQueries when in SLAVE mode. This is just to be sure.
			CdiLogQuery::isUpdating(true);
			
			// Switch to maintenance mode
			if(Symphony::Configuration()->get('maintenance-enabled', 'cdi') == 'yes') {
				Symphony::Configuration()->set('enabled', 'yes', 'maintenance_mode');
				Administration::instance()->saveConfig();			
			}
			
			// Implement automatic backup before processing structural changes
			if(Symphony::Configuration()->get('backup-enabled', 'cdi') == 'yes') {
				try {
					if(CdiUtil::hasRequiredDumpDBVersion()) {
						require_once(EXTENSIONS . '/cdi/lib/class.cdidumpdb.php');
						$currentBackup = CdiDumpDB::backup("automatic");
					} else {
						throw new Exception('You can only enable automatic backup files when the "Dump DB" extension (version 1.08) is installed and enabled.');
					}
				}catch (Exception $e) {
					echo "ERROR: " . $e->getMessage() , ". Failed to backup database before update, aborting.";
					die();
				}
			}
			
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
														 VALUES ('" . $hash . "','" . CdiUtil::getAuthor() . "','" . CdiUtil::getURL() . "','" . $date . "'," . $order . ")");
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
						
						// Implement automatic restore on error
						if((Symphony::Configuration()->get('backup-enabled', 'cdi') == 'yes') &&
						   (Symphony::Configuration()->get('restore-enabled', 'cdi') == 'yes')) {
							try {
								CdiDumpDB::restore($currentBackup);
							}catch (Exception $r) {
								echo "ERROR: " . $r->getMessage() , ". Failed to restore latest database backup. This instance needs immediate attention!";
								die();
							}

							// Restore was succesful, at least we can jump out of maintenance mode
							if(Symphony::Configuration()->get('maintenance-enabled', 'cdi') == 'yes') {
								Symphony::Configuration()->set('enabled', 'no', 'maintenance_mode');
								Administration::instance()->saveConfig();
							}
							
							echo "ERROR: " . $e->getMessage() , ". Rollback & Restore have been executed.";
						} else {
							echo "ERROR: " . $e->getMessage() , ". Rollback has been executed.";
						}
						
						// Stop processing
						die();
					}
				}
				
				echo "OK: " . $executed . " queries executed, " . $skipped . " skipped.";
			} catch (Exception $e) {
				//TODO: think of some smart way of dealing with errors, perhaps through the preference screen or a CDI Status content page?
				echo "ERROR: " . $e->getMessage();
			}

			// Save the last update date to configuration
			Symphony::Configuration()->set('last-update', time(), 'cdi');
			// No more maintenance mode
			if(Symphony::Configuration()->get('maintenance-enabled', 'cdi') == 'yes') {
				Symphony::Configuration()->set('enabled', 'no', 'maintenance_mode');
			}
			
			Administration::instance()->saveConfig();
			
			// Re-enable CdiLogQuery::log() to persist queries
			CdiLogQuery::isUpdating(false);
		}
	}
?>
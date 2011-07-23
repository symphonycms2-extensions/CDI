<?php

	class CdiDBSync {
		
		public static function install() {
			self::uninstall();
			if (!file_exists(CDIROOT)) { mkdir(CDIROOT); }
		}
		
		public static function uninstall() {
			if(file_exists(DB_SYNC_FILE)) { unlink(DB_SYNC_FILE); }
		}
		
		public static function persistQuery($query) {
			$line = '';
	
			if(self::$meta_written == FALSE) {
				$line .= "\n" . CdiLogQuery::getMetaData();			
				self::$meta_written = TRUE;
			}

			$line .= $query . "\n";
			
			$logfile = DB_SYNC_FILE;
			$handle = @fopen($logfile, 'a');
			fwrite($handle, $line);
			fclose($handle);			
		}		
		
		/**
		 * Imports the SQL statements from the db_sync.sql file, or from the file provided through file upload.
		 * This function can only be called from the Symphony backend, the CDI extension must be enabled and running in Database Synchronisation mode.
		 */
		public static function import() {
			// We should not be processing any queries when the extension is disabled or when it is in 'Continuous Database Integration' mode
			if((!class_exists('Administration'))  || !CdiUtil::isEnabled || !CdiUtil::isCdiDBSync) {
			   	throw new Exception("You can only import the Database Synchroniser file from the Preferences page. The CDI extension must be enabled and in 'Database Synchronisation' mode.");
			}
			
			// Prevent the CdiLogQuery::log() from persisting queries that are executed by CDI itself
			CdiLogQuery::isUpdating(true);

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
			Symphony::Configuration()->set('last-update', time(), 'cdi');
			Administration::instance()->saveConfig();
			
			// Re-enable CdiLogQuery::log() to persist queries
			CdiLogQuery::isUpdating(false);
		}
		
	}	
?>
<?php

	require_once(EXTENSIONS . '/cdi/lib/class.cdiutil.php');

	class CdiDBSync {
		private static $meta_written;
		
		public static function install() {
			self::uninstall();
			if (!file_exists(CDIROOT)) { mkdir(CDIROOT); }
		}
		
		public static function uninstall() {
			if(file_exists(CDI_DB_SYNC_FILE)) { unlink(CDI_DB_SYNC_FILE); }
		}
		
		public static function persistQuery($query) {
			$line = '';
	
			if(self::$meta_written == FALSE) {
				$line .= "\n" . CdiUtil::getMetaData();			
				self::$meta_written = TRUE;
			}

			$line .= $query . "\n";
			
			$handle = @fopen(CDI_DB_SYNC_FILE, 'a');
			fwrite($handle, $line);
			fclose($handle);			
		}		
		
		/**
		 * Imports the SQL statements from the db_sync.sql file, or from the file provided through file upload.
		 * This function can only be called from the Symphony backend, the CDI extension must be enabled and running in Database Synchronisation mode.
		 */
		public static function import() {
			// We should not be processing any queries when the extension is disabled or when it is in 'Continuous Database Integration' mode
			if((!class_exists('Administration'))  || !CdiUtil::isEnabled() || !CdiUtil::isCdiDBSyncSlave()) {
			   	throw new Exception("You can only import the Database Synchroniser file from the Preferences page. The CDI extension must be enabled and should be a 'Slave' instance in 'Database Synchronisation' mode.");
			}
			
			// Prevent the CdiLogQuery::log() from persisting queries that are executed by CDI itself
			// This should not be possible anyway because we can only import in "Slave" mode, but just to be sure!
			CdiLogQuery::isUpdating(true);

			// Handle file upload
			$syncFile = CDI_DB_SYNC_FILE;
			if(!empty($_FILES['cdi_import_file']['tmp_name'])) {
				$syncFile = $_FILES['cdi_import_file']['tmp_name'];
			}
			
			//Execute the queries from file
			try {
				if(file_exists($syncFile)) {
					$contents = file_get_contents($syncFile);
					$queries = explode(';',$contents);
					foreach($queries as $query) {
						$query = trim($query);
						// ommit comments and empty statements
						if(!preg_match('/^--/i', $query) && !$query=='') {
							Symphony::Database()->query($query);
						}
					}
					
					if(isset($_POST['settings']['cdi']['deleteSyncFile'])) {
						unlink($syncFile);
					}
				}
			} catch (Exception $e) {
				// Re-enable CdiLogQuery::log() to persist queries
				CdiLogQuery::isUpdating(false);
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
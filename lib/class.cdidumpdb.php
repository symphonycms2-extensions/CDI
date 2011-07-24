<?php

	class CdiDumpDB {

		public static function install() {
			self::uninstall();
			if (!file_exists(CDIROOT)) { mkdir(CDIROOT); }
			if (!file_exists(CDI_BACKUP_ROOT)) { mkdir(CDI_BACKUP_ROOT); }
		}
		
		public static function uninstall() {
			foreach(self::getBackupFiles() as $file) {
				unlink(CDI_BACKUP_ROOT . '/' . $file);
			}
		}
		
		/**
		/**
		 * Backup the current state of the Symphony database
		 * This function can only be called from the Symphony backend, the CDI extension must be enabled and running in Database Synchronisation mode.
		 * @param $mode The mode of backup: 'manual' or 'automatic'. This is used to determine which configuration setting should apply.
		 */
		public static function backup($mode) {
			// We should only backup the database when the extension is enabled and version 1.08 of the Dump_DB extension is installed.
			if((!class_exists('Administration'))  || !CdiUtil::isEnabled()) {
			   	throw new Exception("You can only import the Database Synchroniser file from the Preferences page");
			}

			if(!CdiUtil::hasRequiredDumpDBVersion()) {
				throw new Exception('Your current version of <a href="http://symphony-cms.com/download/extensions/view/40986/">Dump DB</a> is not supported. Please switch to version 1.08.');
			} else {
				require_once(EXTENSIONS . '/dump_db/extension.driver.php');
				require_once(EXTENSIONS . '/dump_db/lib/class.mysqldump.php');
				
				// Prevent the CdiLogQuery::log() from persisting queries that are executed by CDI itself
				CdiLogQuery::isUpdating(true);
				
				// COPIED FROM Dump_DB version 1.08
				// Adjust to only support FULL database dump
				$sql = CdiUtil::getMetaData();
				
				$dump = new MySQLDump(Symphony::Database());
				$rows = Symphony::Database()->fetch("SHOW TABLES LIKE 'tbl_%';");
				$rows = array_map (create_function ('$x', 'return array_values ($x);'), $rows);
				$tables = array_map (create_function ('$x', 'return $x[0];'), $rows);

				// Get DATA from all tables
				foreach ($tables as $table){
					$table = str_replace(Symphony::Configuration()->get('tbl_prefix', 'database'), 'tbl_', $table);
					$sql .= $dump->export($table, MySQLDump::ALL);
					$sql = str_replace(Symphony::Configuration()->get('tbl_prefix', 'database'), 'tbl_', $sql);
				}
				
				// Persist SQL data to file
				if(($mode == 'automatic' && Symphony::Configuration()->get('backup-overwrite', 'cdi') == 'yes') ||
				   ($mode == 'manual' && Symphony::Configuration()->get('manual-backup-overwrite', 'cdi') == 'yes')) {
					self::uninstall();
				}
				file_put_contents(sprintf(CDI_BACKUP_FILE, time() . '-'),$sql);
				
				// Re-enable CdiLogQuery::log() to persist queries
				CdiLogQuery::isUpdating(false);
			}
		}
		
		public static function restore() {
			// We should only backup the database when the extension is enabled and version 1.08 of the Dump_DB extension is installed.
			if((!class_exists('Administration'))  || !CdiUtil::isEnabled()) {
			   	throw new Exception("You can only restore the Symphony database from the Preferences page");
			}
			
			if(!CdiUtil::hasRequiredDumpDBVersion()) {
				throw new Exception('Your current version of <a href="http://symphony-cms.com/download/extensions/view/40986/">Dump DB</a> is not supported. Please switch to version 1.08.');
			} else {
				require_once(EXTENSIONS . '/dump_db/extension.driver.php');
				require_once(EXTENSIONS . '/dump_db/lib/class.mysqlrestore.php');
				
				// Prevent the CdiLogQuery::log() from persisting queries that are executed by CDI itself
				CdiLogQuery::isUpdating(true);
				
				// COPIED FROM Dump_DB version 1.08
				// Adjust to only support FULL database dump
				$restore = new MySQLRestore(Symphony::Database());
				
				$filename = $_POST["ref"];
				if(file_exists(CDI_BACKUP_ROOT . '/' . $filename)) {
					$restore->import(file_get_contents(CDI_BACKUP_ROOT . '/' . $filename));
				} else {
					throw new Exception("The provided restore file '" . $filename . "' could not be found.");
				}
				
				// Re-enable CdiLogQuery::log() to persist queries
				CdiLogQuery::isUpdating(false);
			}
		}
		
		/**
		 * Returns the database backup files from the Manifest folder
		 */
		public static function getBackupFiles() {
			$files = array();
			if(file_exists(CDI_BACKUP_ROOT)) {
				if($handle = opendir(CDI_BACKUP_ROOT)) {
				    while (false !== ($file = readdir($handle))) {
						if (preg_match("/cdi-db-backup.sql/i", $file)) {
							$files[] = $file;
						}
				    }
				    closedir($handle);
				}
				sort($files);
			}
			return $files;
		}
	}
?>
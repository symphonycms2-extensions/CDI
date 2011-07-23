<?php

	class CdiDumpDB {

		public static function install() {
			self::uninstall();
			if (!file_exists(CDIROOT)) { mkdir(CDIROOT); }
		}
		
		public static function uninstall() {
			foreach(self::getBackupFiles() as $file) {
				unlink(CDIROOT . '/' . $file);
			}
		}
		
		/**
		 * Backup the current state of the Symphony database
		 * This function can only be called from the Symphony backend, the CDI extension must be enabled and running in Database Synchronisation mode.
		 */
		public static function backup() {
			// We should only backup the database when the extension is enabled and version 1.08 of the Dump_DB extension is installed.
			if((!class_exists('Administration'))  ||
			   (Symphony::Configuration()->get('enabled', 'cdi') == 'no')) {
			   	throw new Exception("You can only import the Database Synchroniser file from the Preferences page");
			}
			
			require_once(EXTENSIONS . '/dump_db/extension.driver.php');
			require_once(EXTENSIONS . '/dump_db/lib/class.mysqldump.php');
			$about = extension_dump_db::about();
			$version = $about["version"];
			if($version != "1.08") {
				throw new Exception('Your current version of <a href="http://symphony-cms.com/download/extensions/view/40986/">Dump DB</a> (' . $version . ') is not supported. Please switch to version 1.08.');
			} else {
				// Prevent the CdiLogQuery::log() from persisting queries that are executed by CDI itself
				CdiLogQuery::$isUpdating = true;
				
				// COPIED FROM Dump_DB version 1.08
				// Adjust to only support FULL database dump
				$sql = CdiLogQuery::getMetaData();
				
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
				if(Symphony::Configuration()->get('backup-overwrite', 'cdi') == 'yes') {
					file_put_contents(sprintf(CDI_BACKUP_FILE,''),$sql);
				} else {
					$ts = time() . '-';
					file_put_contents(sprintf(CDI_BACKUP_FILE,$ts),$sql);
				}
				
				// Re-enable CdiLogQuery::log() to persist queries
				CdiLogQuery::$isUpdating = false;
			}
		}
		
		public static function restore() {
			
		}
		
		/**
		 * Returns the database backup files from the Manifest folder
		 */
		public static function getBackupFiles() {
			$files = array();
			if(file_exists(CDIROOT)) {
				if($handle = opendir(CDIROOT)) {
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
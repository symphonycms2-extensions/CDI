<?php

	define('CDIROOT',MANIFEST . '/cdi',false);
	define('CDI_FILE', CDIROOT . '/cdi.sql');
	define('DB_SYNC_FILE', CDIROOT . '/db_sync.sql');
	define('CDI_BACKUP_FILE', CDIROOT . '/%scdi-db-backup.sql');

	class CdiUtil {
		
		public static function isEnabled() {
			return (Symphony::Configuration()->get('enabled', 'cdi') == 'yes');
		}
		
		public static function isCdiSlave() {
			return (Symphony::Configuration()->get('mode', 'cdi') == 'CdiSlave');
		}

		public static function isCdiMaster() {
			return (Symphony::Configuration()->get('mode', 'cdi') == 'CdiMaster');
		}
		
		public static function isCdiDBSync() {
			return (Symphony::Configuration()->get('mode', 'cdi') == 'CdiDBSync');
		}

		public static function canBeMasterInstance() {
			if(!file_exists(CDIROOT) && is_writable(MANIFEST)) { return true; }
			if(is_writable(CDIROOT)) { return true; }
			else { return false; }
		}

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
		 * Return a line of Meta information to append to the query
		 */
		private static function getMetaData() {
			$meta = '-- ' . date('Y-m-d H:i:s', time());
			$meta .= ', ' . CdiLogQuery::getAuthor();
			$meta .= ', ' . CdiLogQuery::getURL();
			$meta .= ";\n";
			return $meta;
		}
	}
	
?>
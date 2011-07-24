<?php

	class CdiMaster {

		private static $lastEntryTS;
		private static $lastEntryOrder;
		private static $meta_written = FALSE;
		
		public static function install() {
			self::uninstall();
			Symphony::Database()->query("DROP TABLE IF EXISTS `tbl_cdi_log`");
			if (!file_exists(CDIROOT)) { mkdir(CDIROOT); }
		}
		
		public static function uninstall() {
			if(file_exists(CDI_FILE)) { unlink(CDI_FILE); }
		}
		
		/**
		 * If it is proven to be a valid SQL Statement worthy of logging, the persistQuery() function will
		 * write the statement to file and log it
		 * @param string $query The SQL Statement that will be saved to file and CDI log
		 */
		public static function persistQuery($query) {
			try {
				$ts = time();
		
				if(self::$lastEntryTS != $ts) {
					self::$lastEntryTS = $ts;
					self::$lastEntryOrder = 0;
				} else {
					self::$lastEntryOrder++;
				}
				
				$id = $ts . '-' . self::$lastEntryOrder;
				$hash = md5($id . $query);
				$date = date('Y-m-d H:i:s', $ts);
				
				try{
					//We are only logging this to file because we do not execute CDI queries on the MASTER instance
					//The database table `tbl_cdi_log` is removed from the database on the MASTER instance.
					//It is the repsonsibility of the user to ensure that they only have a single MASTER instance 
					//and that they protect the integrity of the Symphony database
					$entries = CdiLogQuery::getCdiLogEntries();
					$entries[$id] = array(0 => $ts, 1 => self::$lastEntryOrder, 2 => $hash, 3 => $query);
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
	}
?>
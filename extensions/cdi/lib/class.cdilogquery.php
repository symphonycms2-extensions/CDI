<?php

class CdiLogQuery {
	
	private static $isUpdating;
	private static $lastEntryTS;
	private static $lastEntryOrder;
	
	static function getAuthor() {
		$author = Administration::instance()->Author;
		if (isset($author)) { 
			return $author->getFullName(); 
		} else { 
			return ""; 
		}
	}
	
	static function getURL() {
		$url = Administration::instance()->getCurrentPageURL();
		if (is_null($url)) { $url = ""; }
		return $url;
	}
	
	static function log($query) {
		if(Symphony::Configuration()->get('enabled', 'cdi') == 'no') return;
		if(CdiLogQuery::$isUpdating || strpos($query,"cdi_log") !== false) return;
		
		$tbl_prefix = Symphony::Configuration()->get('tbl_prefix', 'database');

		/* FILTERS */
		// only structural changes, no SELECT statements
		if (!preg_match('/^(insert|update|delete|create|drop|alter|rename)/i', $query)) return;
		// un-tracked tables (sessions, cache, authors)
		if (preg_match("/{$tbl_prefix}(authors|cache|forgotpass|sessions)/i", $query)) return;
		// content updates in tbl_entries (includes tbl_entries_fields_*)
		if (preg_match('/^(insert|delete|update)/i', $query) && preg_match("/({$config->tbl_prefix}entries)/i", $query)) return;
		
		$query = trim($query);
		
		// append query delimeter if it doesn't exist
		if (!preg_match('/;$/', $query)) $query .= ";";
		CdiLogQuery::persistQuery($query);
	}
	
	static function persistQuery($query) {
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
			Symphony::Database()->query("INSERT INTO `tbl_cdi_log` (`query_hash`,`author`,`url`,`date`,`order`)
										 VALUES ('" . $hash . "','" . CdiLogQuery::getAuthor() . "','" . CdiLogQuery::getURL() . "','" . $date . "'," . CdiLogQuery::$lastEntryOrder . ")");
			$logfile = MANIFEST . '/cdi/' . $ts . '-' . CdiLogQuery::$lastEntryOrder . '-' . $hash . '.sql';
			file_put_contents($logfile,$query);
			return true;
		} catch(Exception $e) {
			//TODO: think of some smart way of dealing with errors
			return false;
		}
	}

	static function rollback($query) {
		//TODO: revert the datbase and file persistance
	}
	
	static function executeQueries() {
		//TODO: Performance Measurement to see if it is faster to fetch all rows or to iterate over available scripts 
		//and query database to see if they have been executed.
		CdiLogQuery::$isUpdating = true;

		try {
			$files = array();
			if($handle = opendir(MANIFEST . '/cdi/')) {
			    while (false !== ($file = readdir($handle))) {
			    	if($file != '.' && $file != '..') {
			    		if(strpos($file,".sql") !== false)
				        	$files[] = str_replace('.sql', '', $file);
			    	}
			    }
			    closedir($handle);
			}
			sort($files);
			
			$skipped = 0;
			$executed = 0;
			
			foreach($files as $file) {
				$parts = split('-',$file);
				$hash = $parts[2];
				$date = date('Y-m-d H:i:s', $parts[0]);
				$order = $parts[1];
				
				$cdiLogEntry = Symphony::Database()->fetchRow(0,"SELECT * FROM tbl_cdi_log WHERE `query_hash` LIKE '" . $hash . "'");
				if(empty($cdiLogEntry)) {
					$query = file_get_contents(MANIFEST . '/cdi/' . $file . '.sql');
					Symphony::Database()->query($query);
					Symphony::Database()->query("INSERT INTO `tbl_cdi_log` (`query_hash`,`author`,`url`,`date`,`order`)
												 VALUES ('" . $parts[2] . "','" . CdiLogQuery::getAuthor() . "','" . CdiLogQuery::getURL() . "','" . $date . "'," . $order . ")");
					$executed++;
				} else {
					$skipped++;
				}
			}
			
			echo "OK: " . $executed . " queries executed, " . $skipped . " skipped.";
		} catch (Exception $e) {
			//TODO: something with error handling;
			echo "ERROR: " . $e->getMessage();
		}

		CdiLogQuery::$isUpdating = false;
	}
	

}
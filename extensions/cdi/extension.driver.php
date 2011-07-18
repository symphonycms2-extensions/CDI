<?php

	require_once(EXTENSIONS . '/cdi/lib/class.cdilogquery.php');
	
	Class extension_cdi extends Extension {

		public function about() {
			return array(
				'name'			=> 'Continuous Database Integration',
				'version'		=> '0.1.0',
				'release-date'	=> '2011-07-17',
				'author'		=> array(
					'name'			=> 'Remie Bolte',
					'email'			=> 'r.bolte@gmail.com'
				),
				'description'	=> 'Continuous Database Integration is designed to save and log structural changes to the database, allowing queries to be savely executed on other instances'
			);
		}
		
		public function fetchNavigation() {
			return array(
				array(
					'location'	=> 'System',
					'name'	=> 'CDI Update',
					'link'	=> '/update/'
				)
			);
		}
		
		public function install() {
			try{
				Symphony::Database()->query("CREATE TABLE IF NOT EXISTS `tbl_cdi_log` (
					  `date` DATETIME NOT NULL,
					  `order` int(4),
					  `author` VARCHAR(255) NOT NULL,
					  `url` VARCHAR(255) NOT NULL,
					  `query_hash` VARCHAR(255) NOT NULL)");
			}
			catch(Exception $e){
				return false;
			}

			if (!file_exists(MANIFEST . '/cdi/')) { mkdir(MANIFEST . '/cdi/'); }
			Symphony::Configuration()->set('enabled', 'yes', 'cdi');
			Administration::instance()->saveConfig();
			return true;
		}
		
		public function uninstall() {
			try {
				Symphony::Database()->query("DROP TABLE IF EXISTS `tbl_cdi_log`");
			}
			catch(Exception $e){
				return false;
			}
			
			Symphony::Configuration()->remove('cdi');
			Administration::instance()->saveConfig();
		}

	}
	
?>
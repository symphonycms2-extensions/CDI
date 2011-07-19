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
		
		public function install() {
			if($this->__createTable()) {
				if (!file_exists(CDIROOT)) { mkdir(CDIROOT); }
				Symphony::Configuration()->set('enabled', 'yes', 'cdi');
				Symphony::Configuration()->set('is-slave', 'yes', 'cdi');
				Administration::instance()->saveConfig();
				return true;
			} else {
				return false;
				}
		}
		
		public function uninstall() {
			if($this->__dropTable()) {
				Symphony::Configuration()->remove('cdi');
				Administration::instance()->saveConfig();
				return true;
			} else {
				return false;
			}
		}
		
		private function __createTable() {
			try{
				Symphony::Database()->query("CREATE TABLE IF NOT EXISTS `tbl_cdi_log` (
					  `date` DATETIME NOT NULL,
					  `order` int(4),
					  `author` VARCHAR(255) NOT NULL,
					  `url` VARCHAR(255) NOT NULL,
					  `query_hash` VARCHAR(255) NOT NULL)");
				return true;
			}
			catch(Exception $e){
				return false;
			}
		}
		
		private function __dropTable() {
			try {
				Symphony::Database()->query("DROP TABLE IF EXISTS `tbl_cdi_log`");
				return true;
			}
			catch(Exception $e){
				return false;
			}
		}
		
		private function __canBeMasterInstance() {
			if(!file_exists(CDIROOT) && is_writable(MANIFEST)) { return true; }
			if(is_writable(CDIROOT)) { return true; }
			else { return false; }
		}

		/*-------------------------------------------------------------------------
			Delegate
		-------------------------------------------------------------------------*/

		public function getSubscribedDelegates()
		{
			return array(
				array(
					'page' => '/system/preferences/',
					'delegate' => 'AddCustomPreferenceFieldsets',
					'callback' => 'appendPreferences'
				),
				array(
					'page' => '/system/preferences/',
					'delegate' => 'Save',
					'callback' => 'savePreferences'
				)
			);
		}
		
		/*-------------------------------------------------------------------------
			Delegated functions
		-------------------------------------------------------------------------*/	

		public function fetchNavigation() {
			if((Symphony::Configuration()->get('enabled', 'cdi') == 'yes') &&
			   (Symphony::Configuration()->get('is-slave', 'cdi') == 'yes')) {
				return array(
					array(
						'location'	=> 'System',
						'name'	=> 'CDI Update',
						'link'	=> '/update/'
					)
				);
			}
		}
		
		public function appendPreferences($context){

			// Clean the database or log files if the cd_clear action was called
			if(isset($_POST["action"]["cdi_clear"])) {
				if(Symphony::Configuration()->get('is-slave', 'cdi') == 'yes') {
					Symphony::Database()->query('DELETE FROM `tbl_cdi_log`');
				} else { 
					CdiLogQuery::cleanLogs();
				}
			}
			
			// Create the Preferences user-interface for the CDI extension
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', 'Continuous Database Integration'));
			$group->appendChild(new XMLElement('h3','Instance Mode',array('style' => 'margin-bottom: 5px;')));
			
			$div = new XMLElement('div', NULL, array('class' => 'group'));

			//CDI mode
			$label = Widget::Label();
			$input = Widget::Input('settings[cdi][is-slave]', 'yes', 'checkbox');
			if($this->__canBeMasterInstance()) {
				if($this->_Parent->Configuration->get('is-slave', 'cdi') == 'yes') { $input->setAttribute('checked', 'checked'); }
				$label->setValue($input->generate() . ' This is a "Slave" instance (no structural changes will be registered)');
			} else {
				$input->setAttribute('checked', 'checked');
				$input->setAttribute('disabled', 'disabled');
				$label->setValue($input->generate() . ' This can only be a "Slave" instance due to insufficient write permissions.');
			}
			$div->appendChild($label);
			
			$group->appendChild($div);
			$group->appendChild(new XMLElement('p', 'The Continuous Database Integration (CDI) extension aims on enabling the automation of propagating structural changes between environments in a DTAP setup.
													 It is imperitive that you have a single "Master" instance (usually the development environment from which the changes are captured). This is important because the autonumbers need to be exactly the same on each database. 
													 Be carefull about the database integrity and only switch instance mode after you have ensured that you have restored all databases from the same source.', array('class' => 'help')));
			
			// Clear CDI logs
			$div = new XMLElement('div', NULL);//, array('class' => 'group'));
			
			$entries = new XMLElement('div',NULL);

			$entryCount = 0;
			if(Symphony::Configuration()->get('is-slave', 'cdi') == 'yes') {
				$entries->appendChild(new XMLElement('h3','The last 5 queries executed',array('style' => 'margin-bottom: 5px;')));
				$table = new XMLElement('table', NULL, array('cellpadding' => '0', 'cellspacing' => '0', 'border' => '0'));
				$cdiLogEntries = Symphony::Database()->fetch("SELECT * FROM tbl_cdi_log ORDER BY `date` DESC LIMIT 0,5");
				if(count($cdiLogEntries) > 0) {
					foreach($cdiLogEntries as $entry) {
						$tr = new XMLElement('tr',null);
						$tr->appendChild(new XMLElement('td',$entry['date'],array('width' => '150')));
						$tr->appendChild(new XMLElement('td',$entry['author']),array('width' => '200'));
						$tr->appendChild(new XMLElement('td',$entry['query_hash']));
						$table->appendChild($tr);
						$entryCount++;
					}
				} else {
					$tr = new XMLElement('tr',null);
					$tr->appendChild(new XMLElement('td','No CDI queries have been executed'));
					$table->appendChild($tr);
				}
				$entries->appendChild($table);
			} else {
				$entries->appendChild(new XMLElement('h3','The last 5 queries logged',array('style' => 'margin-bottom: 5px;')));
				$table = new XMLElement('table', NULL, array('cellpadding' => '0', 'cellspacing' => '0', 'border' => '0'));
				$cdiLogEntries = CdiLogQuery::getCdiLogFiles();
				if(count($cdiLogEntries) > 0) {
					rsort($cdiLogEntries);
					foreach($cdiLogEntries as $entry) {
						if($entryCount == 5) { break; }
						$tr = new XMLElement('tr',null);
						$tr->appendChild(new XMLElement('td',$entry . '.sql'));
						$table->appendChild($tr);
						$entryCount++;
					}
				} else {
					$tr = new XMLElement('tr',null);
					$tr->appendChild(new XMLElement('td','There are no entries in the CDI log'));
					$table->appendChild($tr);
				}
				$entries->appendChild($table);
			}
			$div->appendChild($entries);
			$group->appendChild($div);
			
			// CLEAR Button
			if($entryCount != 0) {
				$div = new XMLElement('div',NULL,array('style' => 'margin-top: 10px'));//, array('style' => 'text-align:center;'));
				$div->appendChild(new XMLElement('button', 'Clear', array('name' => 'action[cdi_clear]', 'type' => 'submit')));
				$div->appendChild(new XMLElement('span','&nbsp;Press "Clear" to remove all CDI log entries from disk and/or Symphony Database'));
				$div->appendChild(new XMLElement('br',NULL));
				$div->appendChild(new XMLElement('br',NULL));
				$group->appendChild($div);
				$group->appendChild(new XMLElement('p', 'You can use the "Clear" button to clean up old CDI logs. 
														 Ensure that all your Symphony have been updated either by CDI (check the last executed queries list above) or by manually restoring the same database backup on all instances.
														 Make sure that you clear the log files on every instance (including the "Master" instance). It is important that the database schemas are synchronized before starting with a clean sheet.', array('class' => 'help')));
			}
			
			// Append preferences
			$context['wrapper']->appendChild($group);
		}

		public function savePreferences($context){
			if(isset($_POST['settings']['cdi']['is-slave'])){
				if($this->__createTable()) {
					CdiLogQuery::cleanLogs();
					Symphony::Configuration()->set('is-slave', 'yes', 'cdi');
				} else {
					throw new DatabaseException("Could not create CDI database table");
				}
			} else if($this->__canBeMasterInstance()) {
				if($this->__dropTable()) {
					if(!file_exists(CDIROOT)) { mkdir(CDIROOT); }
					Symphony::Configuration()->set('is-slave', 'no', 'cdi');
				} else {
					throw new DatabaseException("Could not remove CDI database table");
				}
			} else {
				throw new Exception("This Symphony installation is not permitted to act as MASTER instance for the CDI extension");
			}
			Administration::instance()->saveConfig();
		}
	}
?>
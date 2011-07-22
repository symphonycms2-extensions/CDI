<?php

	require_once(EXTENSIONS . '/cdi/lib/class.cdilogquery.php');
	
	Class extension_cdi extends Extension {
		
		public function about() {
			return array(
				'name'			=> 'Continuous Database Integration',
				'version'		=> '0.3.0',
				'release-date'	=> '2011-07-22',
				'author'		=> array(
					'name'			=> 'Remie Bolte, Nick Dunn, Richard Warrender',
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
				CdiLogQuery::cleanLogs(true);
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
			// Clean the database and log files when the cd_clear action is called
			if(isset($_POST["action"]["cdi_clear"])) {
				CdiLogQuery::cleanLogs(false);
				if((Symphony::Configuration()->get('cdi-mode', 'cdi') == 'cdi') && 
				   (Symphony::Configuration()->get('is-slave', 'cdi') == 'yes')) {
					Symphony::Database()->query('DELETE FROM `tbl_cdi_log`');
				}
			}
			
			// Import the db_sync.sql file when the cdi_import action is called
			if(isset($_POST["action"]["cdi_import"])) {
				CdiLogQuery::importSyncFile();
			}
			
			// Capture CDI Mode change event
			$cdiMode = Symphony::Configuration()->get('cdi-mode', 'cdi');
			if(empty($cdiMode)) { $cdiMode = 'cdi'; }
			if(isset($_POST['settings']['cdi']['cdi-mode'])){
				$cdiMode = $_POST['settings']['cdi']['cdi-mode'];
				// Although it is not the right place, it is important to persist the selected CDI mode
				// This can be removed once the post-back has been replaced by client-side switching of CDI mode
				Symphony::Configuration()->set('cdi-mode', $cdiMode, 'cdi');
				Administration::instance()->saveConfig();
			}
			
			// Create the Preferences user-interface for the CDI extension
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', 'Continuous Database Integration'));
			
			// CDI Mode
			// TODO: There is no need to do a post-back for switching CDI mode!
			// A javascript library needs to be implemented to make this go more smooth
			$div = new XMLElement('div', NULL);
			$div->appendChild(new XMLElement('h3','Continuous Integration Mode',array('style' => 'margin-bottom: 5px;')));
			$options = array();
			$options[] = array('cdi', ($cdiMode == 'cdi'), 'Continuous Database Integration');
			$options[] = array('db_sync', ($cdiMode == 'db_sync'), 'Database Synchroniser');
			$div->appendChild(Widget::Select('settings[cdi][cdi-mode]', $options, array('style' => 'width: 250px;margin-bottom: 12px;', 'onchange' => 'submit();')));
			if($cdiMode == 'cdi') {
				$div->appendChild(new XMLElement('p', 'Each individual query is stored to disk in order of execution and can be automatically executed on a slave instance. The CDI extension will register which queries have been executed to prevent duplicate execution.', array('class' => 'help', 'style' => 'margin-bottom: 10px;')));
			} else {
				$div->appendChild(new XMLElement('p', 'All queries are stored to disk in a single file. The generated SQL file needs to be manually executed on each slave instance and flushed after upgrading to prevent duplicate execution.', array('class' => 'help', 'style' => 'margin-bottom: 10px;')));
			}
			$group->appendChild($div);

			if($cdiMode == 'cdi') {
				//Instance mode
				$div = new XMLElement('div', NULL);
				$div->appendChild(new XMLElement('h3','Instance Mode',array('style' => 'margin: 5px 0;')));
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
				$div->appendChild(new XMLElement('p', 'The Continuous Database Integration (CDI) extension aims on enabling the automation of propagating structural changes between environments in a DTAP setup.
														 It is imperitive that you have a single "Master" instance (usually the development environment from which the changes are captured). This is important because the autonumbers need to be exactly the same on each database. 
														 Be carefull about the database integrity and only switch instance mode after you have ensured that you have restored all databases from the same source.', array('class' => 'help')));
				$group->appendChild($div);
				
				// Clear CDI logs
				$div = new XMLElement('div', NULL);
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
					$cdiLogEntries = CdiLogQuery::getCdiLogEntries();
					if(count($cdiLogEntries) > 0) {
						rsort($cdiLogEntries);
						foreach($cdiLogEntries as $entry) {
							if($entryCount == 5) { break; }
							$tr = new XMLElement('tr',null);
							$tr->appendChild(new XMLElement('td',date('d-m-Y h:m:s', $entry[0]),array('width' => '150')));
							$tr->appendChild(new XMLElement('td',htmlspecialchars($entry[3])));
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
			} else {
				$div = new XMLElement('div', NULL);
				$div->appendChild(new XMLElement('h3','Import SQL Statements',array('style' => 'margin-bottom: 5px;')));
				$span = new XMLElement('span',NULL,array('class' => 'frame'));
				if(file_exists(CDIROOT . '/db_sync.sql')) {
					$span->appendChild(new XMLElement('button','Import',array('name' => 'action[cdi_import]', 'type' => 'submit')));
				} else {
					$context["parent"]->Page->Form->setAttribute('enctype', 'multipart/form-data');
					$span->appendChild(new XMLElement('input',NULL,array('name' => 'cdi_import_file', 'type' => 'file')));
					$span->appendChild(new XMLElement('button','Import',array('name' => 'action[cdi_import]', 'type' => 'submit')));
				}
				$div->appendChild($span);
				$div->appendChild(new XMLElement('p', 'All SQL statements in the Database Synchroniser file will be execute on this Symphony instance. When all statements have been succesfully imported the file will be deleted.', array('class' => 'help')));
				$group->appendChild($div);
			}
			
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
			// CDI Mode
			if(isset($_POST['settings']['cdi']['cdi-mode'])){
				Symphony::Configuration()->set('cdi-mode', $_POST['settings']['cdi']['cdi-mode'], 'cdi');
			} else {
				Symphony::Configuration()->set('cdi-mode', 'cdi', 'cdi');
			}
			
			// Instance Mode
			if(isset($_POST['settings']['cdi']['is-slave'])){
				if($this->__createTable()) {
					CdiLogQuery::cleanLogs(false);
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
<?php 

	require_once(EXTENSIONS . '/cdi/lib/class.cdiutil.php');
	require_once(EXTENSIONS . '/cdi/lib/class.cdidbsync.php');
	require_once(EXTENSIONS . '/cdi/lib/class.cdidumpdb.php');
	require_once(EXTENSIONS . '/cdi/lib/class.cdilogquery.php');
	require_once(EXTENSIONS . '/cdi/lib/class.cdimaster.php');
	require_once(EXTENSIONS . '/cdi/lib/class.cdislave.php');

	class CdiPreferences {
		
		/*-------------------------------------------------------------------------
			Public static functions
		-------------------------------------------------------------------------*/	
		
		public static function appendCdiMode() {
			$div = new XMLElement('div', NULL);
			$div->appendChild(new XMLElement('h3','Continuous Integration Mode',array('style' => 'margin-bottom: 5px;')));
			$options = array();
			$options[] = array('cdi', (CdiUtil::isCdiMaster() || CdiUtil::isCdiSlave()), 'Continuous Database Integration');
			$options[] = array('db_sync', (CdiUtil::isCdiDBSync()), 'Database Synchroniser');
			$div->appendChild(Widget::Select('settings[cdi][cdi-mode]', $options, array('class' => 'cdi-mode', 'style' => 'width: 250px;margin-bottom: 12px;')));
			if(CdiUtil::isCdiMaster() || CdiUtil::isCdiSlave()) {
				$div->appendChild(new XMLElement('p', 'Each individual query is stored to disk in order of execution and can be automatically executed on a slave instance. The CDI extension will register which queries have been executed to prevent duplicate execution.', array('class' => 'help', 'style' => 'margin-bottom: 10px;')));
			} else if(CdiUtil::isCdiDBSync()) {
				$div->appendChild(new XMLElement('p', 'All queries are stored to disk in a single file. The generated SQL file needs to be manually executed on each slave instance and flushed after upgrading to prevent duplicate execution.', array('class' => 'help', 'style' => 'margin-bottom: 10px;')));
			}
			$div->appendChild(new XMLElement('p', 'You need to save your changes before you can configure this mode, or reload the page to cancel. <br />Be advised: changing CDI mode will reset any mode specific configuration settings.', array('class' => 'cdiModeRestart', 'style' => 'display:none;')));
			return $div;
		}

		
		public static function appendCdiPreferences() {
			$header = new XMLElement('div',null);
			$main = new XMLElement('div',null,array('class' => 'group'));
			$leftColumn = new XMLElement('div',null);
			$rightColumn = new XMLElement('div',null);
			$footer = new XMLElement('div',null);
			
			//Instance mode & Backup and restore
			if(CdiUtil::isCdiMaster()) {
				$header->appendChild(self::appendInstanceMode());
			} else {
				$leftColumn->appendChild(self::appendInstanceMode());
				$rightColumn->appendChild(self::appendDumpDB());
				$rightColumn->appendChild(self::appendRestore());
			}
			
			// CDI logs
			if(CdiUtil::isCdiSlave()) {
				if(CdiUtil::hasRequiredDumpDBVersion()) {
					$leftColumn->appendChild(self::appendCdiSlaveQueries());
					$leftColumn->appendChild(self::appendClearLog());
				} else {
					$footer->appendChild(self::appendCdiSlaveQueries());
					$footer->appendChild(self::appendClearLog());
				}
			} else {
				$footer->appendChild(self::appendCdiMasterQueries());
				$footer->appendChild(self::appendClearLog());
			}
				
			// Add sections to preference group
			$section = new XMLElement('div',null,array('class' => 'cdi'));
			if(!CdiUtil::isCdiMaster() && !CdiUtil::isCdiSlave()) { $section->setAttribute('style','display: none;'); }
				
			$section->appendChild($header);
			$main->appendChild($leftColumn);
			$main->appendChild($rightColumn);
			$section->appendChild($main);			
			$section->appendChild($footer);
			return $section;
		}

		
		public static function appendDBSyncPreferences() {
			$header = new XMLElement('div',null);
			$main = new XMLElement('div',null,array('class' => 'group'));
			$leftColumn = new XMLElement('div',null);
			$rightColumn = new XMLElement('div',null);
			$footer = new XMLElement('div',null);
			
				$leftColumn->appendChild(self::appendDBSyncImport());
				$leftColumn->appendChild(self::appendDBExport());
				$leftColumn->appendChild(self::appendClearLog());
				
				$rightColumn->appendChild(self::appendDumpDB());
				$rightColumn->appendChild(self::appendRestore());

			// Add sections to preference group
			$section = new XMLElement('div',null,array('class' => 'db_sync'));
			if(!CdiUtil::isCdiDBSync()) { $section->setAttribute('style','display: none;'); }
				
			$section->appendChild($header);
			$main->appendChild($leftColumn);
			$main->appendChild($rightColumn);
			$section->appendChild($main);			
			$section->appendChild($footer);
			return $section;
		}
		
		public static function save() {
			// CDI & Instance Mode
			if(isset($_POST['settings']['cdi']['cdi-mode'])){
				switch($_POST['settings']['cdi']['cdi-mode']) {
					case "cdi":
						//TODO: when switching between CDI modes, if you go from DBSync to CDI
						//it will default to CdiMaster because the 'is-slave' variable is not set
						//this is an unwanted side-effect of this generic implementation because the default should be CdiSlave.
						
						// Instance Mode
						if(isset($_POST['settings']['cdi']['is-slave'])){
							Symphony::Configuration()->set('mode', 'CdiSlave', 'cdi');
							CdiSlave::install();
						} else {
							Symphony::Configuration()->set('mode', 'CdiMaster', 'cdi');
							CdiMaster::install();
						}			
						break;
						
					case "db_sync":
						Symphony::Configuration()->set('mode', 'CdiDBSync', 'cdi');
						CdiDBSync::install();
						break;
				}
			} else {
				Symphony::Configuration()->set('mode', 'CdiSlave', 'cdi');
				CdiSlave::install();
			}

			// backup-enabled
			if(isset($_POST['settings']['cdi']['backup-enabled'])) {
				Symphony::Configuration()->set('backup-enabled', 'yes', 'cdi');
			} else {
				Symphony::Configuration()->set('backup-enabled', 'no', 'cdi');
			}

			// backup-overwrite
			if(isset($_POST['settings']['cdi']['backup-overwrite'])) {
				Symphony::Configuration()->set('backup-overwrite', 'yes', 'cdi');
			} else {
				Symphony::Configuration()->set('backup-overwrite', 'no', 'cdi');
			}
			
			// restore-enabled
			if(isset($_POST['settings']['cdi']['restore-enabled'])) {
				Symphony::Configuration()->set('restore-enabled', 'yes', 'cdi');
			} else {
				Symphony::Configuration()->set('restore-enabled', 'no', 'cdi');
			}

			// maintenance-enabled
			if(isset($_POST['settings']['cdi']['maintenance-enabled'])) {
				Symphony::Configuration()->set('maintenance-enabled', 'yes', 'cdi');
			} else {
				Symphony::Configuration()->set('maintenance-enabled', 'no', 'cdi');
			}
			
			// save configuration
			Administration::instance()->saveConfig();			
		}
		
		/*-------------------------------------------------------------------------
			Private static static functions
		-------------------------------------------------------------------------*/	
				
		public static function appendInstanceMode() {
			$div = new XMLElement('div', NULL);
			$div->appendChild(new XMLElement('h3','Instance Mode',array('style' => 'margin: 5px 0;')));
			$label = Widget::Label();
			$label->setAttribute('style','position:relative;padding-left:18px;');
			$input = Widget::Input('settings[cdi][is-slave]', 'yes', 'checkbox');
			$input->setAttribute('style','position:absolute;left:0px;');
			$input->setAttribute('class','instance-mode');
			if(CdiUtil::canBeMasterInstance()) {
				if(CdiUtil::isCdiSlave()) { $input->setAttribute('checked', 'checked'); }
				$label->setValue($input->generate() . ' This is a "Slave" instance (no structural changes will be registered)');
			} else {
				$input->setAttribute('checked', 'checked');
				$input->setAttribute('disabled', 'disabled');
				$label->setValue($input->generate() . ' This can only be a "Slave" instance due to insufficient write permissions.');
			}
			$div->appendChild($label);
			$div->appendChild(new XMLElement('p', 'The extension is designed to allow automatic propagation of structural changes between environments in a DTAP setup.
												   It is imperitive that you have a single "Master" instance (usually your development environment). This is important because the auto-increment values need to be exactly the same on each database table in every environment. 
												   Switching between modes is therefore not recommended. If needed, make sure you only switch instance mode after you have ensured that you have restored all databases from the same source and cleared the CDI logs on all instances.', array('class' => 'help')));
			$div->appendChild(new XMLElement('p', 'You need to save your changes before you can configure this instance, or reload the page to cancel.<br />Be advised: changing instances mode will reset any instance specific configuration settings', array('class' => 'cdiInstanceRestart', 'style' => 'display:none;')));
			return $div;
		}
		
		public static function appendDumpDB() {
			$div = new XMLElement('div', NULL);
			$div->appendChild(new XMLElement('h3','Backup &amp; Restore',array('style' => 'margin: 5px 0;')));

			if(!CdiUtil::hasDumpDBInstalled()) {
				$div->appendChild(new XMLElement('p', 'To enable backup and restore you need to install the <a href="http://symphony-cms.com/download/extensions/view/40986/">Dump DB</a> extension (version 1.08)'));
			} else if(!CdiUtil::hasRequiredDumpDBVersion()) {
				$div->appendChild(new XMLElement('p', 'Your current version of <a href="http://symphony-cms.com/download/extensions/view/40986/">Dump DB</a> (' . $version . ') is not supported. Please switch to version 1.08.'));
			} else {
				// Enable automatic backups
				$label = Widget::Label();
				$label->setAttribute('style','margin-bottom: 4px;position:relative;padding-left:18px;');
				$input = Widget::Input('settings[cdi][backup-enabled]', 'yes', 'checkbox');
				$input->setAttribute('style','position:absolute;left:0px;');
				$input->setAttribute('class','backup-enabled');
				if(Symphony::Configuration()->get('backup-enabled', 'cdi') == 'yes') { $input->setAttribute('checked', 'checked'); }
				$label->setValue($input->generate() . ' Create an automatic backup prior to executing structural updates');
				$div->appendChild($label);

				// Overwrite existing backup
				$label = Widget::Label();
				$label->setAttribute('style','margin-bottom: 4px;position:relative;padding-left:18px;');
				$input = Widget::Input('settings[cdi][backup-overwrite]', 'yes', 'checkbox');
				$input->setAttribute('style','position:absolute;left:0px;');
				$input->setAttribute('class','backup-overwrite');
				if(Symphony::Configuration()->get('backup-enabled', 'cdi') != 'yes') { 
					$input->setAttribute('disabled', 'disabled'); 
				} else if(Symphony::Configuration()->get('backup-overwrite', 'cdi') == 'yes') { 
					$input->setAttribute('checked', 'checked'); 
				}
				$label->setValue($input->generate() . ' Overwrite any existing backup file (if unchecked a new backup file is created on each update)');
				$div->appendChild($label);
				
				// Restore backup on failure
				$label = Widget::Label();
				$label->setAttribute('style','margin-bottom: 4px;position:relative;padding-left:18px;');
				$input = Widget::Input('settings[cdi][restore-enabled]', 'yes', 'checkbox');
				$input->setAttribute('style','position:absolute;left:0px;');
				$input->setAttribute('class','restore-enabled');
				if(Symphony::Configuration()->get('backup-enabled', 'cdi') != 'yes') { 
					$input->setAttribute('disabled', 'disabled'); 
				} else if(Symphony::Configuration()->get('restore-enabled', 'cdi') == 'yes') { 
					$input->setAttribute('checked', 'checked'); 
				}
				$label->setValue($input->generate() . ' Automatically restore the created backup when experiencing failures during update');
				$div->appendChild($label);

				// Backup & Restore in maintenance mode
				$label = Widget::Label();
				$label->setAttribute('style','position:relative;padding-left:18px;');
				$input = Widget::Input('settings[cdi][maintenance-enabled]', 'yes', 'checkbox');
				$input->setAttribute('style','position:absolute;left:0px;');
				$input->setAttribute('class','maintenance-enabled');
				if(Symphony::Configuration()->get('backup-enabled', 'cdi') != 'yes') { 
					$input->setAttribute('disabled', 'disabled'); 
				} else if(Symphony::Configuration()->get('maintenance-enabled', 'cdi') == 'yes') { 
					$input->setAttribute('checked', 'checked'); 
				}
				$label->setValue($input->generate() . ' Switch to "Maintenance" mode when performing database updates');
				$div->appendChild($label);
			}
			$div->appendChild(new XMLElement('p', 'It is recommended to enable automatic backup of your Symphony database prior to updating it. 
												   In case of execution errors or data corruption this allows you to quickly revert to a working configuration.', array('class' => 'help')));
			return $div;
		}
		
		public static function appendRestore() {
			$div = new XMLElement('div', NULL,array('style'=>'margin-bottom: 1.5em;','class' => 'cdiRestore'));
			if(CdiUtil::hasRequiredDumpDBVersion()) {
				$div->appendChild(new XMLElement('h3','Restore Symphony Database',array('style' => 'margin: 5px 0;')));
				$table = new XMLElement('table', NULL, array('cellpadding' => '0', 'cellspacing' => '0', 'border' => '0'));
				$files = CdiDumpDB::getBackupFiles();
				if(count($files) > 0) {
					rsort($files);
					foreach($files as $file) {
						$filename = split('-',$file);
						if($entryCount == 5) { break; }
						$tr = new XMLElement('tr',null);
						$tr->appendChild(new XMLElement('td',date('d-m-Y h:m:s', (int)$filename[0]),array('style' => 'vertical-align:middle;')));
						$td = new XMLElement('td',null,array('width' => '75'));
						$button = new XMLElement('input',null, array('value' => 'Restore', 'name' => 'action[cdi_restore]', 'type' => 'button', 'class' => 'restore_action', 'ref' => $file));
						$td->appendChild($button);
						$tr->appendChild($td);
						$table->appendChild($tr);
						$entryCount++;
					}
				} else {
					$tr = new XMLElement('tr',null);
					$tr->appendChild(new XMLElement('td','There are no entries in the CDI log'));
					$table->appendChild($tr);
				}
				$div->appendChild($table);
			}
			return $div;
		}
		
		public static function appendCdiMasterQueries() {
			$div = new XMLElement('div', NULL,array('style'=>'margin-bottom: 1.5em;', 'class' => 'cdiLastQueries'));
			$div->appendChild(new XMLElement('h3','The last 5 queries logged',array('style' => 'margin-bottom: 5px;')));
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
			}
			$tr = new XMLElement('tr',null,array('class' => 'cdiNoLastQueriesCell'));
			if($entryCount != 0) { $tr->setAttribute('style','display:none;'); }
			$tr->appendChild(new XMLElement('td','There are no entries in the CDI log'));
			$table->appendChild($tr);
			
			$div->appendChild($table);
			return $div;
		}
		
		public static function appendCdiSlaveQueries() {
			$div = new XMLElement('div', NULL,array('style'=>'margin-bottom: 1.5em;','class' => 'cdiLastQueries'));
			$div->appendChild(new XMLElement('h3','The last 5 queries executed',array('style' => 'margin-bottom: 5px;')));
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
			}

			$tr = new XMLElement('tr',null,array('class' => 'cdiNoLastQueriesCell'));
			if($entryCount != 0) { $tr->setAttribute('style','display:none;'); }
			$tr->appendChild(new XMLElement('td','No CDI queries have been executed on this instance'));
			$table->appendChild($tr);

			$div->appendChild($table);
			return $div;
		}
		
		public static function appendDBSyncImport() {
			$div = new XMLElement('div', NULL);
			$div->appendChild(new XMLElement('h3','Import SQL Statements',array('style' => 'margin-bottom: 5px;')));
			if(!file_exists(CDIROOT . '/db_sync.sql')) {
				Administration::instance()->Page->Form->setAttribute('enctype', 'multipart/form-data');
				$span = new XMLElement('span',NULL,array('class' => 'frame'));
				$span->appendChild(new XMLElement('input',NULL,array('name' => 'cdi_import_file', 'type' => 'file')));
				$div->appendChild($span);
			}
			
			$button = new XMLElement('div',NULL,array('style' => 'margin: 10px 0;'));
			$button->appendChild(new XMLElement('input',null,array('value' => 'Import', 'name' => 'action[cdi_import]', 'type' => 'button', 'class' => 'cdi_import_action')));
			$button->appendChild(new XMLElement('span','&nbsp;Press "Import" to synchronise the Symphony Database.'));
			$div->appendChild($button);
			$div->appendChild(new XMLElement('p', 'All SQL statements in the Database Synchroniser file will be executed on this Symphony instance. When all statements have been succesfully imported the file will be deleted.', array('class' => 'help')));
			return $div;
		}
		
		public static function appendDBExport() {
			$div = new XMLElement('div', NULL);
			$div->appendChild(new XMLElement('h3','Export current Symphony database',array('style' => 'margin-bottom: 5px;')));
			$button = new XMLElement('div',NULL,array('style' => 'margin: 10px 0;'));
			$button->appendChild(new XMLElement('input',null,array('value' => 'Export', 'name' => 'action[cdi_export]', 'type' => 'button', 'class' => 'cdi_export_action')));
			$button->appendChild(new XMLElement('span','&nbsp;Press "Export" to create a full backup of the Symphony Database.'));
			$div->appendChild($button);
			$div->appendChild(new XMLElement('p', 'You can use the export to synchronise your databases between environments. Be advised: this will also copy all data. If your production environment has user-generated content you need to be carefull for data loss.', array('class' => 'help')));
			return $div;
		}

		public static function appendClearLog() {
			$div = new XMLElement('div',NULL);
			$div->appendChild(new XMLElement('h3','Clear Log Entries',array('style' => 'margin-bottom: 5px;')));
			$button = new XMLElement('div',NULL,array('style' => 'margin: 10px 0;'));
			$button->appendChild(new XMLElement('input', null, array('value' => 'Clear', 'name' => 'action[cdi_clear]', 'type' => 'button', 'class' => 'cdi_clear_action')));
			$button->appendChild(new XMLElement('span','&nbsp;Press "Clear" to remove all CDI log entries from disk and/or Symphony Database'));
			$div->appendChild($button);
			$div->appendChild(new XMLElement('p', 'You can use the "Clear" button to clean up old CDI logs. 
													 Ensure that all your Symphony have been updated either by CDI (check the last executed queries list above) or by manually restoring the same database backup on all instances.
													 Make sure that you clear the log files on every instance (including the "Master" instance). It is important that the database schemas are synchronized before starting with a clean sheet.', array('class' => 'help')));
			return $div;
		}		
		
	}

?>
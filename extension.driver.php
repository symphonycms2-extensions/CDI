<?php

	require_once(EXTENSIONS . '/cdi/lib/class.cdipreferences.php');
	require_once(EXTENSIONS . '/cdi/lib/class.cdilogquery.php');
	
	Class extension_cdi extends Extension {

		public function about() {
			return array(
				'name'			=> 'Continuous Database Integration',
				'version'		=> '0.4.0',
				'release-date'	=> '2011-07-24',
				'author'		=> array(
					'name'			=> 'Remie Bolte, Nick Dunn, Richard Warrender',
					'email'			=> 'r.bolte@gmail.com'
				),
				'description'	=> 'Continuous Database Integration is designed to save and log structural changes to the database, allowing queries to be savely executed on other instances'
			);
		}
		
		public function install() {
			if(!CdiUtil::isLoggerInstalled()) {
       			Administration::instance()->Page->pageAlert("You need to add 'CdiLogQuery::log()' to <em>class.mysql.php</em> to enable the CDI extension. See README for more information.");
				return false;
			} else {
				CdiSlave::install();
				if(CdiUtil::hasRequiredDumpDBVersion()) {
					CdiDumpDB::install();
				}
	
				Symphony::Configuration()->set('enabled', 'yes', 'cdi');
				Symphony::Configuration()->set('mode', 'CdiSlave', 'cdi');
				Administration::instance()->saveConfig();
				return true;
			}
		}
		
		public function uninstall() {
			Symphony::Configuration()->remove('cdi');
			Administration::instance()->saveConfig();
			
			CdiMaster::uninstall();
			CdiSlave::uninstall();
			CdiDBSync::uninstall();
			CdiDumpDB::uninstall();
			return true;
		}

		/*-------------------------------------------------------------------------
			Delegate
		-------------------------------------------------------------------------*/

		public function getSubscribedDelegates()
		{
			return array(
				array(
					'page'		=> '/backend/',
					'delegate'	=> 'InitaliseAdminPageHead',
					'callback'	=> 'initaliseAdminPageHead'
				),
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
			return array(
				array('location' => 'System', 'link' => '/actions/'),
				array('location' => 'System', 'link' => '/update/')
			);
		}
		
		public function initaliseAdminPageHead($context) {
			Administration::instance()->Page->addScriptToHead(URL . '/extensions/cdi/assets/cdi.preferences.js',4598); // I like random numbers
		}

		public function appendPreferences($context){
			// Import the db_sync.sql file when the cdi_import action is called
			// The import action is the only left to require a post-back because AJAX file upload is cumbersome
			if(isset($_POST["action"]["cdi_import"])) {
				CdiDBSync::import();
			} else if(isset($_POST["action"]["dumpdb_restore"])) {
				CdiDumpDB::restore();
			}
			
			
			// Create the Preferences user-interface for the CDI extension
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'cdi settings');
			$group->appendChild(new XMLElement('legend', 'Continuous Database Integration'));

			if(CdiUtil::isLoggerInstalled()) {
				$group->appendChild(CdiPreferences::appendCdiMode());
				if(CdiUtil::isCdi()) {
					$group->appendChild(CdiPreferences::appendCdiPreferences());
				} else if(CdiUtil::isCdiDBSync()) {
					$group->appendChild(CdiPreferences::appendDBSyncPreferences());
				}
			} else {
       			Administration::instance()->Page->pageAlert("You need to add 'CdiLogQuery::log()' to <em>class.mysql.php</em> to enable the CDI extension. See README for more information.");
				$group->appendChild(new XMLElement('p', 'The CDI extension is currently disabled because it seems that you have not added a reference to the "CdiLogQuery::log()" function in your Symphony MySQL class.
														 Installation instructions can be found in the <em>README</em> and <em>class.mysql.php.txt</em> file that can be found in the extension directory.'));
			}

			// Append preferences
			$context['wrapper']->appendChild($group);
		}
		
		public function savePreferences($context){
			CdiPreferences::save();
		}
	}
?>
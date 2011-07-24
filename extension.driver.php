<?php

	require_once(EXTENSIONS . '/cdi/lib/class.cdipreferences.php');
	require_once(EXTENSIONS . '/cdi/lib/class.cdilogquery.php');
	
	Class extension_cdi extends Extension {

		public function about() {
			return array(
				'name'			=> 'Continuous Database Integration',
				'version'		=> '0.4.0',
				'release-date'	=> '2011-07-23',
				'author'		=> array(
					'name'			=> 'Remie Bolte, Nick Dunn, Richard Warrender',
					'email'			=> 'r.bolte@gmail.com'
				),
				'description'	=> 'Continuous Database Integration is designed to save and log structural changes to the database, allowing queries to be savely executed on other instances'
			);
		}
		
		public function install() {
			CdiSlave::install();
			Symphony::Configuration()->set('enabled', 'yes', 'cdi');
			Symphony::Configuration()->set('mode', 'CdiSlave', 'cdi');
			
			Administration::instance()->saveConfig();
			return true;
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
				array('location' => 'System', 'link' => '/save/'),
				array('location' => 'System', 'link' => '/update/'),
				array('location' => 'System', 'link' => '/restore/')
			);
		}
		
		public function initaliseAdminPageHead($context) {
			Administration::instance()->Page->addScriptToHead(URL . '/extensions/cdi/assets/cdi.preferences.js');
		}

		public function appendPreferences($context){
			// Import the db_sync.sql file when the cdi_import action is called
			// The import action is the only left to require a post-back because AJAX file upload is cumbersome
			if(isset($_POST["action"]["cdi_import"])) {
				CdiLogQuery::importSyncFile();
			}
			
			// Create the Preferences user-interface for the CDI extension
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'cdi settings');
			$group->appendChild(new XMLElement('legend', 'Continuous Database Integration'));

			$group->appendChild(CdiPreferences::appendCdiMode());
			if(CdiUtil::isCdi()) {
				$group->appendChild(CdiPreferences::appendCdiPreferences());
			} else if(CdiUtil::isCdiDBSync()) {
				$group->appendChild(CdiPreferences::appendDBSyncPreferences());
			}

			// Append preferences
			$context['wrapper']->appendChild($group);
		}
		
		public function savePreferences($context){
			CdiPreferences::save();
		}
	}
?>
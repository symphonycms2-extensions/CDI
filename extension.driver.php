<?php

	require_once(EXTENSIONS . '/cdi/lib/class.cdiutil.php');
	require_once(EXTENSIONS . '/cdi/lib/class.cdipreferences.php');
	require_once(EXTENSIONS . '/cdi/lib/class.cdilogquery.php');
	
	Class extension_cdi extends Extension {

		public function install() {
			if(CdiSlave::install()) {
				if(CdiUtil::hasRequiredDumpDBVersion()) {
					CdiDumpDB::install();
				}
			} else {
				return false;
			}

			Symphony::Configuration()->set('enabled', 'yes', 'cdi');
			Symphony::Configuration()->set('mode', 'CdiSlave', 'cdi');
			Symphony::Configuration()->write();
			return true;
		}
		
		public function uninstall() {
			Symphony::Configuration()->remove('cdi');
			Symphony::Configuration()->write();
			
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
					'page'		=> '/backend/',
					'delegate'	=> 'NavigationPreRender',
					'callback'	=> 'NavigationPreRender'
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

		public function initaliseAdminPageHead($context) {
			Administration::instance()->Page->addStylesheetToHead(URL . '/extensions/cdi/assets/cdi.css',null,10);
			Administration::instance()->Page->addScriptToHead(URL . '/extensions/cdi/assets/cdi.preferences.js',4598); // I like random numbers
		}
		
		public function NavigationPreRender($context) {
			if(CdiUtil::hasDisabledBlueprints()) {
				unset($context["navigation"][BLUEPRINTS_INDEX]);
			}
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
			$group->appendChild(new XMLElement('div', '<span class="image">&#160;</span><span>Processing... please wait.</span>', array('class' => 'help cdiLoading cdiHidden')));
			Administration::instance()->Page->Form->setAttribute('enctype', 'multipart/form-data');
			
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
			if(CdiPreferences::save()) {
				// apply config changes
				if(CdiUtil::isCdiSlave()) { CdiSlave::install(); } 
				else if (CdiUtil::isCdiMaster()) { CdiMaster::install(); }
				else { CdiDBSync::install(); }
			} else {
				Administration::instance()->Page->pageAlert(_('An unknown error occurred while saving preferences for CDI. Your changes have not been saved.'));
				return false;
			}
		}
	}
?>
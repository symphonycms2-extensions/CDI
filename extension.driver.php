<?php

	require_once(EXTENSIONS . '/cdi/lib/class.cdiutil.php');
	require_once(EXTENSIONS . '/cdi/lib/class.cdipreferences.php');
	require_once(EXTENSIONS . '/cdi/lib/class.cdilogquery.php');
	
	Class extension_cdi extends Extension {

		public function about() {
			return array(
				'name'			=> 'Continuous Database Integration',
				'version'		=> '1.0.1',
				'release-date'	=> '2011-08-07',
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
				Symphony::Configuration()->set('api_key', $this->generateKey(), 'cdi');
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

		public function fetchNavigation() {
			return array(
				array('location' => 'System', 'link' => '/actions/'),
				array('location' => 'System', 'link' => '/update/')
			);
		}
		
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

			if(null == Symphony::Configuration()->get('api_key', 'cdi')){
				Symphony::Configuration()->set('api_key', $this->generateKey(), 'cdi');
				Administration::instance()->saveConfig();
			}
			
			// Create the Preferences user-interface for the CDI extension
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'cdi settings');
			$group->appendChild(new XMLElement('legend', 'Continuous Database Integration'));
			$group->appendChild(new XMLElement('div', '<span class="image">&#160;</span><span>Processing... please wait.</span>', array('class' => 'help cdiLoading cdiHidden')));
			Administration::instance()->Page->Form->setAttribute('enctype', 'multipart/form-data');
			
			if(CdiUtil::isLoggerInstalled()) {
				$group->appendChild(CdiPreferences::appendCdiMode());
				if(CdiUtil::isCdiSlave() || CdiUtil::isCdiDBSyncSlave())
				{
					$div = new XMLElement('div');
					$heading = new XMLElement('h3', 'Update URL');
					$heading->setAttribute('style','margin: 5px 0;');
					$div->appendChild($heading);
					$link = new XMLElement('span', URL . '/symphony/extension/cdi/update/' . Symphony::Configuration()->get('api_key','cdi'));
					$link->setAttribute('class','frame');
					$div->appendChild($link);
					$help = new XMLElement('p','To get this installation in sync with your master installation, the above URL will trigger the update process. There is no extra configuration needed, so it is possible to automate the update process.');
					$help->setAttribute('class','help');
					$div->appendChild($help);
					$group->appendChild($div);
				}
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

		protected function generateKey(){
			return substr(sha1(uniqid()), 0, 10);
		}
	}
?>
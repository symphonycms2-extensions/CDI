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
			Symphony::Configuration()->set('is-slave', 'yes', 'cdi');
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
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', 'Continuous Database Integration'));
			
			//CDI mode
			$div = new XMLElement('div', NULL, array('class' => 'group'));
	
			$label = Widget::Label();
			$input = Widget::Input('settings[cdi][is-slave]', 'yes', 'checkbox');
			if($this->_Parent->Configuration->get('is-slave', 'cdi') == 'yes') $input->setAttribute('checked', 'checked');
			$label->setValue($input->generate() . ' This is a "Slave" instance, no structural changes will be registered.');
			$div->appendChild($label);
			$group->appendChild($div);

			$group->appendChild(new XMLElement('p', 'The Continuous Database Integration (CDI) extension aims on enabling the automation of propagating structural changes between environments in a DTAP setup.<br />
													 You should have a single "master" instance (usually the development environment on which the changes are captured). You can use the REST interface to automatically update the "Slave" instances.<br />
													 The CDI extension will log which updates have already been executed and skip those during the update run. This will prevent updates from being executed twice, thus safeguarding the integrity of the database.', array('class' => 'help')));
			
			
			$context['wrapper']->appendChild($group);
		}		
	
		public function savePreferences($context){
			if(!isset($_POST['settings']['cdi']['is-slave'])){
				Symphony::Configuration()->set('is-slave', 'no', 'cdi');
			} else {
				Symphony::Configuration()->set('is-slave', 'yes', 'cdi');
			}
			Administration::instance()->saveConfig();
		}
	}
?>
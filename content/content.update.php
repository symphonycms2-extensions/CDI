<?php 
	require_once(EXTENSIONS . '/cdi/lib/class.cdiutil.php');
	require_once(EXTENSIONS . '/cdi/lib/class.cdislave.php');

	// We should not be processing any queries when the extension is disabled or when we are the Master instance
	if((!class_exists('Administration')) || !CdiUtil::isEnabled() || (CdiUtil::isCdiMaster() || CdiUtil::isCdiDBSync())) {
		echo "WARNING: You are not calling this page from Symphony, the CDI extension is disabled or you are running the queryies on the Master instance. No queries have been executed.";
	} else {
		$callback = Administration::getPageCallback();
		if(Symphony::Configuration()->get('api_key','cdi') !== $callback['context'][0]){
			echo "WARNING: Invalid API key. The correct key can be found in the configuration page.";
			die();
		}
		else{
			CdiSlave::update();
		}
	}
	
	die();
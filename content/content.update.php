<?php 
	// We should not be processing any queries when the extension is disabled or when we are the Master instance
	if((!class_exists('Administration')) || !CdiUtil::isEnabled || (CdiUtil::isCdiMaster || CdiUtil::isCdiDBSync())) {
		echo "WARNING: You are not calling this page from Symphony, the CDI extension is disabled or you are running the queryies on the Master instance. No queries have been executed.";
	} else {
		require_once(EXTENSIONS . '/cdi/lib/class.cdilogquery.php');
		CdiSlave::update();
	}
	
	die();
?>
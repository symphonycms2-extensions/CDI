<?php 

	// We should not be processing any queries when the extension is disabled or when we are the Master instance
	if((!class_exists('Administration')) ||
	   (Symphony::Configuration()->get('enabled', 'cdi') == 'no') ||
	   (Symphony::Configuration()->get('is-slave', 'cdi') == 'no')) {
		echo "WARNING: You are not calling this page from Symphony, the CDI extension is disabled or you are running the queryies on the Master instance. No queries have been executed.";
	} else {
		require_once(EXTENSIONS . '/cdi/lib/class.cdilogquery.php');
		CdiLogQuery::executeQueries();
	}
	
	//TODO: create a nice user-interface within the admin console
	//Or maybe not, because this page is meant to be used as REST interface during build
	//But that might be problematic because of the authentication...
	//This needs some figuring out. In the meantime, just kill te execution.
	die();

?>
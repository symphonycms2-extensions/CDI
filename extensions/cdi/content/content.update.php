<?php 

	require_once(EXTENSIONS . '/cdi/lib/class.cdilogquery.php');
	CdiLogQuery::executeQueries();
	
	//TODO: create a nice user-interface within the admin console
	//Or maybe not, because this page is meant to be used as REST interface during build
	//But that might be problematic because of the authentication...
	//This needs some figuring out. In the meantime, just kill te execution.
	die();

?>
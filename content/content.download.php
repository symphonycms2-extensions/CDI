<?php


	// We should only allow download of the database from the administration interface when the extension is enabled.
	if((!class_exists('Administration'))  || !Administration::instance()->isLoggedIn() || !CdiUtil::isEnabled()) {
	   	throw new Exception("You can only download CDI content from the Preferences page");
	}

	require_once(EXTENSIONS . '/cdi/lib/class.cdiutil.php');
	
	$filename = $_REQUEST["ref"];
	if($filename == CDI_FILENAME || $filename == CDI_DB_SYNC_FILENAME) {
		$file = CDIROOT . '/' . $filename;
	} else {
		$file = CDI_BACKUP_ROOT . '/' . $filename;
	}
	
	if(file_exists($file)) {
		$data = file_get_contents($file);

		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");

		header("Content-Type: application/octet-stream");
		header("Content-Transfer-Encoding: binary");
		header("Content-Disposition: attachment; filename=" . $filename);
		echo $data;
		die();
	} else {
		throw new Exception("The provided backup file '" . $file . "' could not be found.");
	}

?>
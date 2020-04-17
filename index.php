<?php
if(!defined('ROOT')) exit('No direct script access allowed');

include_once __DIR__."/api.php";

$slug = _slug("a/src/mode/refid");

if(isset($slug['src']) && !isset($_REQUEST['src'])) {
	$_REQUEST['src']=$slug['src'];
}


if(isset($_REQUEST['src']) && strlen($_REQUEST['src'])>0) {
	$dcConfig = findDCPage($slug['src']);

	if($dcConfig) {
		printDCPage($dcConfig);
	} else {
		echo "<h1 class='errormsg'>Sorry, dcpage '{$_REQUEST['src']}' not found.</h1>";
	}
} else {
	echo "<h1 class='errormsg'>Sorry, DCPage not defined.</h1>";
}
?>
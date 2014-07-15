<?php
	include('../../../config.php');
	$language = ereg_replace( '[\\|/]', '', getLanguageName());
	$url = './'.$language.'_help.html';
	if(file_exists($url)){
		$message=file($url);
	}
	else{
		$message=file('./default_help.html');
	}
	$linenumber=sizeof($message);
	$i=0;
	while($i<$linenumber){
		$message[$i] = trim($message[$i], "\n\0\r");
		$message[$i] = mb_ereg_replace("'", "\\'", $message[$i]);
		$message[$i] = mb_ereg_replace('&', '\\&', $message[$i]);
		$message[$i] = mb_ereg_replace('"', '\\"', $message[$i]);
		$message[$i] = mb_ereg_replace('/', '\\/', $message[$i]);
		$message[$i] = mb_ereg_replace('    ', '\\&nbsp;\\&nbsp;\\&nbsp;\\&nbsp;', $message[$i]);
		print ("document.write('{$message[$i]}\\n');");
		$i++;
	}
?>
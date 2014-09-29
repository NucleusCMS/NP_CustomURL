<?php
	include('../../../config.php');
	$language = str_replace( array('\\','/'), '', getLanguageName());
	$url = './'.$language.'_help.html';
	if(is_file($url)){
		$message=file($url);
	}
	else{
		$message=file('./default_help.html');
	}
	$linenumber=sizeof($message);
	$i=0;
	while($i<$linenumber){
		$message[$i] = trim($message[$i], "\n\0\r");
		$message[$i] = str_replace("'", "\\'", $message[$i]);
		$message[$i] = str_replace('&', '\\&', $message[$i]);
		$message[$i] = str_replace('"', '\\"', $message[$i]);
		$message[$i] = str_replace('/', '\\/', $message[$i]);
		$message[$i] = str_replace('    ', '\\&nbsp;\\&nbsp;\\&nbsp;\\&nbsp;', $message[$i]);
		echo ("document.write('{$message[$i]}\\n');");
		$i++;
	}

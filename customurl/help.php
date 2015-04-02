<?php
	$language = str_replace( array('\\','/'), '', getLanguageName());
	$plugin_path = str_replace('\\','/',dirname(__FILE__)) . '/';
	$help_path = "{$plugin_path}{$language}_help.html";
	if(is_file($help_path)) echo file_get_contents($help_path);
	else              echo file_get_contents('default_help.html');

<?php

global $CONF;
$CONF['Self']='';

if (!function_exists('htmlspecialchars_decode')) {
	function htmlspecialchars_decode($text)
	{
		return strtr($text, array_flip(get_html_translation_table(HTML_SPECIALCHARS)));
	}
}

if (!defined('_CUSTOMURL_TABLE_DEFINED')) {
	define('_CUSTOMURL_TABLE_DEFINED', 1);
	define('_CUSTOMURL_TABLE',         sql_table('plug_customurl'));
	define('_C_SUBCAT_TABLE',          sql_table('plug_multiple_categories_sub'));
}

class NP_CustomURL extends NucleusPlugin
{

	function getMinNucleusVersion() { return '364';}
	function getName()              { return 'Customized URL';}
	function getAuthor()            { return 'shizuki + nekonosippo + Cacher + Reine';}
	function getURL()               { return 'http://japan.nucleuscms.org/wiki/plugins:customurl';}
	function getVersion()           { return '0.3.8';}
	function getDescription()       { return _DESCRIPTION;}
	function hasAdminArea()         { return 1;}
	function getTableList()         { return array(_CUSTOMURL_TABLE);}
	function supportsFeature($what)
	{
		switch ($what) {
			case 'SqlTablePrefix':
			case 'HelpPage':
			case 'SqlApi':
				return 1;
			default:
				return 0;
		}
	}


	function event_QuickMenu(&$data)
	{
		global $member;
		$quickLink   = ($this->getOption( 'customurl_quicklink') == 'yes');
		$memberCheck = ($member->isLoggedIn() && $member->isAdmin());
		if (!$quickLink || !$memberCheck) {
			return;
		}
		array_push(
			$data['options'],
			array(
				'title'   => _ADMIN_TITLE,
				'url'     => $this->getAdminURL(),
				'tooltip' => _QUICK_TIPS
			)
		);
	}

	function getEventList()
	{
		return	array(
					  'QuickMenu',
					  'ParseURL',
					  'GenerateURL',
					  'PostAddBlog',
					  'PostAddItem',
					  'PostUpdateItem',
					  'PostRegister',
					  'PostAddCategory',
					  'PostDeleteBlog',
					  'PostDeleteItem',
					  'PostDeleteMember',
					  'PostDeleteCategory',
					  'PrePluginOptionsUpdate',
					  'PreItem',
					  'PostItem',
					  'PreSkinParse',
					  'AddItemFormExtras',
					  'EditItemFormExtras',
					  'PostMoveCategory',
					  'PostMoveItem',
					  'InitSkinParse',
					 );
	}

	function install()
	{

// Can't install when faster requier Nucleus Core Version
		$ver_min = (getNucleusVersion() < $this->getMinNucleusVersion());
		$pat_min = ((getNucleusVersion() == $this->getMinNucleusVersion()) &&
				   (getNucleusPatchLevel() < $this->getMinNucleusPatchLevel()));
		if ($ver_min || $pat_min) {
			global $DIR_LIBS;
			// uninstall plugin again...
			include_once($DIR_LIBS . 'ADMIN.php');
			$admin = new ADMIN();
			$admin->deleteOnePlugin($this->getID());
		
			// ...and show error
			$admin->error(_ERROR_NUCLEUSVERSIONREQ .
			$this->getMinNucleusVersion() . ' patch ' .
			$this->getMinNucleusPatchLevel());
		}

		global $manager, $CONF;
// Keys initialize
		if (empty($CONF['ArchiveKey'])) {
			$CONF['ArchiveKey'] = 'archive';
		}
		if (empty($CONF['ArchivesKey'])) {
			$CONF['ArchivesKey'] = 'archives';
		}
		if (empty($CONF['MemberKey'])) {
			$CONF['MemberKey'] = 'member';
		}
		if (empty($CONF['ItemKey'])) {
			$CONF['ItemKey'] = 'item';
		}
		if (empty($CONF['CategoryKey'])) {
			$CONF['CategoryKey'] = 'category';
		}

//Plugins sort
		$plugTable = sql_table('plugin');
		$myid      = intval($this->getID());
		$res       = sql_query('SELECT pid, porder FROM ' . $plugTable);
		while ($p = sql_fetch_array($res)) {
			$updateQuery = 'UPDATE %s '
						 . 'SET    porder = %d '
						 . 'WHERE  pid    = %d';
			if (($pid = intval($p['pid'])) == $myid) {
				$q      = sprintf($updateQuery, $plugTable, 1, $myid);
				sql_query($q);
			} else {
				$porder = intval($p['porder']);
				$q      = sprintf($updateQuery, $plugTable, $porder + 1, $pid);
				sql_query($q);
			}
		}

//create plugin's options and set default value
		$this->createOption('customurl_archive',   _OP_ARCHIVE_DIR_NAME,
							'text', $CONF['ArchiveKey']);
		$this->createOption('customurl_archives',  _OP_ARCHIVES_DIR_NAME,
							'text', $CONF['ArchivesKey']);
		$this->createOption('customurl_member',    _OP_MEMBER_DIR_NAME,
							'text', $CONF['MemberKey']);
		$this->createOption('customurl_dfitem',    _OP_DEF_ITEM_KEY,
							'text', $CONF['ItemKey']);
		$this->createOption('customurl_dfcat',     _OP_DEF_CAT_KEY,
							'text', $CONF['CategoryKey']);
		$this->createOption('customurl_dfscat',    _OP_DEF_SCAT_KEY,
							'text', 'subcategory');
		$this->createOption('customurl_incbname',  _OP_INCLUDE_CBNAME,
							'yesno', 'no');
		$this->createOption('customurl_tabledel',  _OP_TABLE_DELETE,
							'yesno', 'no');
		$this->createOption('customurl_quicklink', _OP_QUICK_LINK,
							'yesno', 'yes');
		$this->createOption('customurl_notfound',  _OP_NOT_FOUND,
							'select', '404',
							'404 Not Found|404|303 See Other|303');
		$this->createBlogOption(    'use_customurl',   _OP_USE_CURL,
									'yesno', 'yes');
		$this->createBlogOption(    'redirect_normal', _OP_RED_NORM,
									'yesno', 'yes');
		$this->createBlogOption(    'redirect_search', _OP_RED_SEARCH,
									'yesno', 'yes');
		$this->createBlogOption(    'customurl_bname', _OP_BLOG_PATH,
									'text');
//		$this->createItemOption(    'customurl_iname', _OP_ITEM_PATH,
//									'text',  $CONF['ItemKey']);
		$this->createMemberOption(  'customurl_mname', _OP_MEMBER_PATH,
									'text');
		$this->createCategoryOption('customurl_cname', _OP_CATEGORY_PATH,
									'text');

		//default archive directory name
		$this->setOption('customurl_archive',  $CONF['ArchiveKey']);
		//default archives directory name
		$this->setOption('customurl_archives', $CONF['ArchivesKey']);
		//default member directory name
		$this->setOption('customurl_member',   $CONF['MemberKey']);
		//default itemkey_template
		$this->setOption('customurl_dfitem',   $CONF['ItemKey']);
		//default categorykey_template
		$this->setOption('customurl_dfcat',    $CONF['CategoryKey']);
		//default subcategorykey_template
		$this->setOption('customurl_dfscat',   'subcategory');

//create data table
		$sql = 'CREATE TABLE IF NOT EXISTS ' . _CUSTOMURL_TABLE . ' ('
			 . ' `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, '
			 . ' `obj_param` VARCHAR(15) NOT NULL, '
			 . ' `obj_name` VARCHAR(128) NOT NULL, '
			 . ' `obj_id` INT(11) NOT NULL, '
			 . ' `obj_bid` INT(11) NOT NULL,'
			 . ' INDEX (`obj_name`)'
			 . ' )';
		sql_query($sql);

//setting default aliases
		$this->_createNewPath('blog',     'blog',     'bnumber', 'bshortname');
		$this->_createNewPath('item',     'item',     'inumber', 'iblog');
		$this->_createNewPath('category', 'category', 'catid',   'cblog');
		$this->_createNewPath('member',   'member',   'mnumber', 'mname');

		if ($this->pluginCheck('MultipleCategories')) {
			$scatTableName = 'plug_multiple_categories_sub';
			$this->_createNewPath('subcategory', $scatTableName, 'scatid', 'catid');
		}

	}

	function _createNewPath($type, $n_table, $id, $bids)
	{
		$tmpTable    = sql_table('plug_customurl_temp');
		$createQuery = 'CREATE TABLE %s '
					 . 'SELECT       obj_id, obj_param '
					 . 'FROM         %s '
					 . 'WHERE        obj_param = "%s"';
		sql_query(sprintf($createQuery, $tmpTable, _CUSTOMURL_TABLE, $type));
		$TmpQuery    = 'SELECT    %s, %s '
					 . 'FROM      %s as ttb '
					 . 'LEFT JOIN %s as tcu '
					 . 'ON        ttb.%s = tcu.obj_id '
					 . 'WHERE     tcu.obj_id is null';
		$table       = sql_table($n_table);
		$TmpQuery    = sprintf($TmpQuery, $id, $bids, $table, $tmpTable, $id);
		$temp        = sql_query($TmpQuery);
		if ($temp) {
			while ($row = sql_fetch_array($temp)) {
				switch ($type) {
					case 'blog':
						//set access by BlogshortName/
						$newPath = $row[$bids];
						$blgid   = 0;
					break;
					case 'item':
						//set access by (itemkey_template)_itemid.html
						$tque    = 'SELECT '
								 . 'itime as result '
								 . 'FROM %s '
								 . 'WHERE inumber = %d';
						$tque    = sprintf($tque, $table, intval($row[$id]));
						$itime   = quickQuery($tque);
						list($y, $m, $d, $trush) = sscanf($itime, '%d-%d-%d %s');
						$param['year']           = sprintf('%04d', $y);
						$param['month']          = sprintf('%02d', $m);
						$param['day']            = sprintf('%02d', $d);
						$itplt   = $this->getOption('customurl_dfitem');
						$ikey    = TEMPLATE::fill($itplt, $param);
						$newPath = $ikey . '_' . $row[$id] . '.html';
						$blgid   = $row[$bids];
					break;
					case 'category':
						//set access by (categorykey_template)_categoryid/
						$newPath = $this->getOption('customurl_dfcat') . '_' . $row[$id];
						$blgid   = $row[$bids];
					break;
					case 'member':
						//set access by loginName.html
						$newPath = $row[$bids] . '.html';
						$blgid   = 0;
					break;
					case 'subcategory':
						//set access by (subcategorykey_template)_subcategoryid/
						$newPath = $this->getOption('customurl_dfscat') . '_' . $row[$id];
						$blgid   = $row[$bids];
					break;
					default:
					break;
				}
				$insertQuery = 'INSERT INTO %s '
							 . '(obj_param, obj_id, obj_name, obj_bid) '
							 . 'VALUES ("%s", %d, "%s", %d)';
				$row[$id]    = intval($row[$id]);
				$blgid       = intval($blgid);
				sql_query(sprintf($insertQuery, _CUSTOMURL_TABLE, $type, $row[$id], $newPath, $blgid));
			}
		}
		$query = 'SELECT obj_id, obj_name '
			   . 'FROM %s '
			   . 'WHERE obj_param = "%s"';
		$temp  = sql_query(sprintf($query, _CUSTOMURL_TABLE, $type));
		while ($row = sql_fetch_array($temp)) {
			$name = $row['obj_name'];
			$id   = intval($row['obj_id']);
			switch ($type) {
				case 'blog':
					$this->setBlogOption($id, 'customurl_bname', $name);
				break;
				case 'category':
					$this->setCategoryOption($id, 'customurl_cname', $name);
				break;
				case 'member':
					$obj_name = substr($name, 0, -5);
					$this->setMemberOption($id, 'customurl_mname', $obj_name);
				break;
				default:
				break;
			}
		}

		sql_query('DROP TABLE IF EXISTS ' . $tmpTable);
	}

	function init()
	{
		global $admin;
		$language = str_replace(array('\\','/'), '', getLanguageName());
		$plugin_path = $this->getDirectory();
		if (!is_file("{$plugin_path}language/{$language}.php"))
			$language = 'english';
		include_once("{$plugin_path}language/{$language}.php");
	}

	function pluginCheck($pluginName)
	{
		global $manager;
		if (!$manager->pluginInstalled('NP_' . $pluginName)) {
			return;
		}
		$plugin =& $manager->getPlugin('NP_' . $pluginName);
		return $plugin;
	}

	function unInstall()
	{
		if ($this->getOption('customurl_tabledel') == 'yes') {
			sql_query("DROP TABLE "._CUSTOMURL_TABLE);
		}
		$this->deleteOption('customurl_archive');
		$this->deleteOption('customurl_archives');
		$this->deleteOption('customurl_member');
		$this->deleteOption('customurl_dfitem');
		$this->deleteOption('customurl_dfcat');
		$this->deleteOption('customurl_dfscat');
		$this->deleteOption('customurl_notfound');
		$this->deleteOption('customurl_tabledel');
		$this->deleteOption('customurl_quicklink');
		$this->deleteBlogOption('use_customurl');
		$this->deleteBlogOption('redirect_normal');
		$this->deleteBlogOption('redirect_search');
		$this->deleteBlogOption('customurl_bname');
//		$this->deleteItemOption('customurl_iname');
		$this->deleteMemberOption('customurl_mname');
		$this->deleteCategoryOption('customurl_cname');
	}

	function event_ParseURL($data)
	{
		global $CONF, $manager, $curl_blogid, $blogid, $itemid, $catid;
		global $memberid, $archivelist, $archive, $query;
// initialize
		$info     =  $data['info'];
		$complete =& $data['complete'];
		if ($complete) {
			return;
		}
		$useCustomURL = $this->getAllBlogOptions('use_customurl');
// Use NP_MultipleCategories ?
		$mcategories  = $this->pluginCheck('MultipleCategories');
		if ($mcategories) {
			$param = array();
			$mcategories->event_PreSkinParse($param);
			global $subcatid;
			if (method_exists($mcategories, 'getRequestName')) {
				$subrequest = $mcategories->getRequestName();
			} else {
				$subrequest = 'subcatid';
			}
		}

// initialize and sanitize '$blogid'
		if (!$blogid) {
			if ( getVar('blogid') ) {
				if ( is_numeric(getVar('blogid')) ) {
					$blogid = intval(getVar('blogid'));
				} else {
					$blogid = intval(getBlogIDFromName(getVar('blogid')));
				}
			} elseif ($curl_blogid) {
				$blogid = intval($curl_blogid);
			} elseif ($itemid>0) {//2008-09-19 Cacher
				$blogid = getBlogIDFromItemID($itemid);
			} else {
				$blogid = $CONF['DefaultBlog'];
			}//2008-09-19 Cacher
		} else {
			if (is_numeric($blogid)) {
				$blogid = intval($blogid);
			} else {
				$blogid = intval(getBlogIDFromName($blogid));
			}
		}

		if (!$info) {
			if (serverVar('PATH_INFO')) {
				$info = serverVar('PATH_INFO');
			} elseif (getNucleusVersion() < 330) {
				if (getVar('virtualpath')) {
					$info = getVar('virtualpath');
				}
			} else {
				if(getVar('query')) { 
					$info = serverVar('REQUEST_URI'); 
				} else { 
					return; 
				} //by nekonosippo 2008-04-06 http://japan.nucleuscms.org/bb/viewtopic.php?p=22351#22351
			}
		}

// Sanitize 'PATH_INFO'
		$info   = trim($info, '/');
		$v_path = explode("/", $info);
		foreach($v_path as $key => $value) {
			$value = urlencode($value);
			$value = preg_replace('|[^a-zA-Z0-9-~+_.?#=&;,/:@%]|i', '', $value);
			$v_path[$key] = $value;
		}
		if (phpversion() >= '4.1.0') {
			$_SERVER['PATH_INFO'] = implode('/', $v_path);
		}
		global $HTTP_SERVER_VARS;
		$HTTP_SERVER_VARS['PATH_INFO'] = implode('/', $v_path);

// Admin area check
		$tmpURL       = sprintf("%s%s%s", "http://", serverVar("HTTP_HOST"), serverVar("SCRIPT_NAME"));
		$uri          = str_replace('/', '\/', $tmpURL);
		$plug_url     = str_replace('/', '\/', $CONF['PluginURL']);
		$u_plugAction = (getVar('action') == 'plugin' && getVar('name'));
		$UsingPlugAdmin = FALSE;
		if (strpos($uri, $plug_url) === 0 || $u_plugAction) {
			$UsingPlugAdmin = TRUE;
		}

// get real blogid
		$blink = FALSE;
		if (empty($info)) {
			$bLink = TRUE;
		}
		$linkObj = array (
						  'bid'       => 0,
						  'name'      => reset($v_path),
						  'linkparam' => 'blog'
						 );
		$blog_id = $this->getRequestPathInfo($linkObj);
		if ($blog_id) {
			$blogid = $blog_id;
			$trush  = array_shift($v_path);
			$bLink  = TURE;
		}
		if ($useCustomURL[$blogid] == 'no') {
			return;
		}

// redirect to other URL style
		$useCustomURLyes = ($useCustomURL[$blogid] == 'yes');
		if ($useCustomURLyes && !$UsingPlugAdmin && !$CONF['UsingAdminArea']) {
// Search query redirection
// 301 permanent ? or 302 temporary ?
			$queryURL = (strpos(serverVar('REQUEST_URI'), 'query=') !== FALSE);
			$search_q = (getVar('query') || $queryURL);
			$redirectSerch = ($this->getBlogOption($blogid, 'redirect_search') == 'yes');
			if ($redirectSerch) {
				if ($search_q) {
					$que_str     = getVar('query');
					$que_str     = $this->hsc($que_str);
					if (extension_loaded('mbstring')) {
						$que_str = str_replace('/', md5('/'), $que_str);
						$que_str = str_replace("'", md5("'"), $que_str);
						$que_str = str_replace('&', md5('&'), $que_str);
					} else {
						$que_str = str_replace('/', md5('/'), $que_str);
						$que_str = str_replace("'", md5("'"), $que_str);
						$que_str = str_replace('&', md5('&'), $que_str);
					}
					$que_str     = urlencode($que_str);
					$search_path = 'search/' . $que_str;
					$b_url       = createBlogidLink($blogid);
					$redurl      = sprintf("%s%s", $b_url, $search_path);
					redirect($redurl); // 302 Moved temporary
					exit;
				}
			}
			$exLink = FALSE;
			if (!$redirectSerch && $search_q) {
				$exLink = TRUE;
			}

// redirection nomal URL to FancyURL
			$temp_req       = explode('?', serverVar('REQUEST_URI'));
			$reqPath        = trim(end($temp_req), '/');
			$indexrdf       = ($reqPath == 'xml-rss1.php');
			$atomfeed       = ($reqPath == 'atom.php');
			$rss2feed       = ($reqPath == 'xml-rss2.php');
			$feeds          = ($indexrdf || $atomfeed || $rss2feed);
			$redirectNormal = ($this->getBlogOption($blogid, 'redirect_normal') == 'yes');
			if ($redirectNormal && serverVar('QUERY_STRING') && !$feeds && !$exLink) {
				$temp = explode('&', serverVar('QUERY_STRING'));
				foreach ($temp as $k => $val) {
					if (preg_match('/^virtualpath/', $val)) {
						unset($temp[$k]);
					}
				}
				if (!empty($temp)) {
					$p_arr = array();
					foreach ($temp as $key => $value) {
						$p_key = explode('=', $value);
						switch (reset($p_key)) {
							case 'blogid';
								$p_arr[] = $CONF['BlogKey'] . '/'
										 . intGetVar('blogid');
								unset($temp[$key]);
								break;
							case 'catid';
								$p_arr[] = $CONF['CategoryKey'] . '/'
										 . intGetVar('catid');
								unset($temp[$key]);
								break;
							case $subrequest;
								$p_arr[] = $subrequest . '/'
										 . intGetVar($subrequest);
								unset($temp[$key]);
								break;
							case 'itemid';
								$p_arr[] = $CONF['ItemKey'] . '/'
										 . intGetVar('itemid');
								unset($temp[$key]);
								break;
							case 'memberid';
								$p_arr[] = $CONF['MemberKey'] . '/'
										 . intGetVar('memberid');
								unset($temp[$key]);
								break;
							case 'archivelist';
								$p_arr[] = $CONF['ArchivesKey'] . '/'
										 . $blogid;
								unset($temp[$key]);
								break;
							case 'archive';
								$p_arr[] = $CONF['ArchiveKey'] . '/'
										 . $blogid . '/' . getVar('archive');
								unset($temp[$key]);
								break;
							default:
								break;
						}
					}
					if (!empty($temp)) {
						$queryTemp = '/?' . implode('&', $temp);
					}
					if (reset($p_arr)) {
						$b_url    = createBlogidLink($blogid);
						$red_path = '/' . implode('/', $p_arr);
						if (substr($b_url, -1) == '/') {
							$b_url = rtrim($b_url, '/');
						}
						$redurl = sprintf("%s%s", $b_url, $red_path) . $queryTemp;
						// HTTP status 301 "Moved Permanentry"
						header('HTTP/1.1 301 Moved Permanently');
						header('Location: ' . $redurl);
						exit;
					}
				}
			} elseif ($redirectNormal && $feeds) {
				$b_url = rtrim(createBlogidLink($blogid), '/');
				switch ($reqPath) {
					case 'xml-rss1.php':
						$feed_code = '/index.rdf';
						break;
					case 'xml-rss2.php':
						$feed_code = '/rss2.xml';
						break;
					case 'atom.php':
						$feed_code = '/atom.xml';
						break;
					default:
						break;
				}
				// HTTP status 301 "Moved Permanentry"
				header('HTTP/1.1 301 Moved Permanently');
				header('Location: ' . $b_url . $feed_code);
				exit;
			}
		}
// decode path_info

// decode unofficial Page switch '/page_2.html'
/*		foreach($v_path as $pathName) {
			if (preg_match('/^page_/', $pathName)) {
				$temp_info    = explode('page_', $pathName);
				$_GET['page'] = intval($temp_info[1]);
				$page         = array_pop($v_path);
			}
		}*/

// decode TrackBack URL shorten ver.
		$tail = end($v_path);
		if (substr($tail, -10, 10) == '.trackback') {
			$v_pathName = substr($tail, 0, -10);
			if (is_numeric($v_pathName) || substr($v_pathName, -5) == '.html') {
				$this->_trackback($blogid, $v_pathName);
			} else {
				$this->_trackback($blogid, $v_pathName . '.html');
			}
			return;
		}

// decode other type URL
//		$bLink = $cLink = $iLink = $exLink = FALSE;
		$cLink = $iLink = $exLink = FALSE;

		$i = 1;
		$NP_ExtraSkinJPFlag = FALSE;
		$redURI = NULL;
		$sc = NULL;
		foreach($v_path as $pathName) {
			switch ($pathName) {
// decode FancyURLs and redirection to Customized URL
				// for blogsgetAllBlogOptions($name)
				case $CONF['BlogKey']:
					if (isset($v_path[$i]) && is_numeric($v_path[$i])) {
						if ($useCustomURL[intval($v_path[$i])] != 'yes') {
							$blogid = intval($v_path[$i]);
							$bLink  = TRUE;
						} else {
							$redURI = createBlogidLink(intval($v_path[$i]));
						}
					}
				break;
				// for items
				case $CONF['ItemKey']:
					if (isset($v_path[$i]) && is_numeric($v_path[$i])) {
						if ($useCustomURL[$blogid] != 'yes') {
							$itemid = intval($v_path[$i]);
							$iLink  = TRUE;
						} else {
							$redURI = createItemLink(intval($v_path[$i]));
						}
					}
				break;
				// for categories
				case $CONF['CategoryKey']:
				case 'catid':
					if (isset($v_path[$i]) && is_numeric($v_path[$i])) {
						if ($useCustomURL[$blogid] != 'yes') {
							$catid  = intval($v_path[$i]);
							$cLink  = TRUE;
						} else {
							$redURI = createCategoryLink(intval($v_path[$i]));
						}
					}
				break;
				// for subcategories
				case $subrequest:
					$c = $i - 2;
					$subCat = (isset($v_path[$i]) && is_numeric($v_path[$i]));
					if ($mcategories && $subCat && $i >= 3 && is_numeric($v_path[$c])) {
						if ($useCustomURL[$blogid] != 'yes') {
							$subcatid  = intval($v_path[$i]);
							$catid     = intval($v_path[$c]);
							$cLink     = TRUE;
						} else {
							$subcat_id = intval($v_path[$i]);
							$catid     = intval($v_path[$c]);
							$linkParam = array($subrequest => $subcat_id);
							$redURI    = createCategoryLink($catid, $linkParam);
						}
					}
				break;
				// for archives
				case $CONF['ArchivesKey']:
				case $this->getOption('customurl_archives'):
				// FancyURL
					if (isset($v_path[$i]) && is_numeric($v_path[$i])) {
						if ($useCustomURL[intval($v_path[$i])] != 'yes') {
							$archivelist = intval($v_path[$i]);
							$blogid      = $archivelist;
							$exLink      = TRUE;
						} else {
							$redURI      = createArchiveListLink(intval($v_path[$i]));
						}
				// Customized URL
					} elseif (isset($v_path[$i]) && strpos($v_path[$i], 'page') === FALSE) {
						$archivelist = $blogid;
						$redURI      = createArchiveListLink($archivelist);
					} else {
						$archivelist = $blogid;
						$exLink      = TRUE;
					}
				break;
				// for archive
				case $CONF['ArchiveKey']:
				case $this->getOption('customurl_archive'):
					$y = $m = $d = '';
					$ar = $i + 1;
					if (isset($v_path[$i])) {
						$darc  = (preg_match('/([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})/', $v_path[$i]));
						$marc  = (preg_match('/([0-9]{4})-([0-9]{1,2})/', $v_path[$i]));
						$yarc  = (preg_match('/([0-9]{4})/', $v_path[$i]));
						if (isset($v_path[$ar])) {
							$adarc = (preg_match('/([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})/', $v_path[$ar]));
							$amarc = (preg_match('/([0-9]{4})-([0-9]{1,2})/', $v_path[$ar]));
							$ayarc = (preg_match('/([0-9]{4})/', $v_path[$ar]));
						} else {
							$adarc = $amarc = $ayarc = NULL;
						}
						$arc   = (!$darc && !$marc && !$yarc);
						$aarc  = ($adarc || $amarc || $ayarc);
						$carc  = ($darc || $marc || $yarc);
				// FancyURL
						if (is_numeric($v_path[$i]) && $arc && isset($v_path[$ar]) && $aarc) {
								sscanf($v_path[$ar], '%d-%d-%d', $y, $m, $d);
							if (!empty($d)) {
								$archive = sprintf('%04d-%02d-%02d', $y, $m, $d);
							} elseif (!empty($m)) {
								$archive = sprintf('%04d-%02d',      $y, $m);
							} else {
								$archive = sprintf('%04d',           $y);
							}
							if ($useCustomURL[intval($v_path[$i])] != 'yes') {
								$blogid = intval($v_path[$i]);
								$exLink = TRUE;
							} else {
								$blogid = intval($v_path[$i]);
								$redURI = createArchiveLink($blogid, $archive);
							}
				// Customized URL
						} elseif ($carc) {
							sscanf($v_path[$i], '%d-%d-%d', $y, $m, $d);
							if (!empty($d)) {
								$archive = sprintf('%04d-%02d-%02d', $y, $m, $d);
							} elseif (!empty($m)) {
								$archive = sprintf('%04d-%02d',      $y, $m);
							} else {
								$archive = sprintf('%04d',           $y);
							}
							$exLink = TRUE;
						} else {
							$redURI = createArchiveListLink($blogid);
						}
					} else {
						$redURI = createArchiveListLink($blogid);
					}
				break;
				// for member
				case $CONF['MemberKey']:
				case $this->getOption('customurl_member'):
				// Customized URL
					$customMemberURL = (substr($v_path[$i], -5, 5) == '.html');
					if (isset($v_path[$i]) && $customMemberURL) {
						$memberInfo = array(
											'linkparam' => 'member',
											'bid'       => 0,
											'name'      => $v_path[$i]
										   );
						$member_id  = $this->getRequestPathInfo($memberInfo);
						$memberid   = intval($member_id);
						$exLink     = TRUE;
				// FancyURL
					} elseif (isset($v_path[$i]) && is_numeric($v_path[$i])) {
						if ($useCustomURL[$blogid] != 'yes') {
							$memberid = intval($v_path[$i]);
							$exLink   = TRUE;
						} else {
							$redURI = createMemberLink(intval($v_path[$i]));
						}
					} else {
						$redURI = createBlogidLink($blogid);
					}
				break;
				// for tag
				// for pageswitch
				case 'tag':
//					if ($this->pluginCheck(TagEx)) $exLink = true;
					if ($this->pluginCheck(TagEX)) $exLink = true;
					break;//2008-07-28 http://japan.nucleuscms.org/bb/viewtopic.php?p=23175#23175
				case 'page':
						$exLink = TRUE;
				break;
				// for ExtraSkinJP
				case 'extra':
					$ExtraSkinJP = $this->pluginCheck('ExtraSkinJP');
					if ($ExtraSkinJP) {
						$NP_ExtraSkinJPFlag = TRUE;
					}
				break;
				// for search query
				case 'search':
					$redirectSerch = ($this->getBlogOption($blogid, 'redirect_search') == 'yes');
					if ($redirectSerch) {
						$que_str = urldecode($v_path[$i]);
						if (extension_loaded('mbstring')) {
							$que_str = str_ireplace(md5('/'), '/', $que_str);
							$que_str = str_ireplace(md5("'"), "'", $que_str);
							$que_str = str_ireplace(md5('&'), '&', $que_str);
						} else {
							$que_str = str_ireplace(md5('/'), '/', $que_str);
							$que_str = str_ireplace(md5("'"), "'", $que_str);
							$que_str = str_ireplace(md5('&'), '&', $que_str);
						}
						$que_str       = htmlspecialchars_decode($que_str);
						$_GET['query'] = $que_str;
						$query         = $que_str;
						$exLink        = TRUE;
					}
				break;
				// for tDiarySkin
				case 'tdiarydate':
				case 'categorylist':
				case 'monthlimit':
					$tDiaryPlugin = $this->pluginCheck('tDiarySkin');
					if ($tDiaryPlugin && isset($v_path[$i])) {
						$_GET[$pathName] = $v_path[$i];
						$exLink          = TRUE;
					}
				break;
				case 'special':
				case $CONF['SpecialskinKey']:
					if (isset($v_path[$i]) && is_string($v_path[$i])) {
						$_REQUEST['special'] = $v_path[$i];
						$exLink          = TRUE;
					}
				break;
				// for trackback
				case 'trackback':
					if (isset($v_path[$i]) && is_string($v_path[$i])) {
						$this->_trackback($blogid, $v_path[$i]);
					}
					return;
				break;

// decode Customized URL
				default:
				// initialyze
					$linkObj = array (
									  'bid'  => $blogid,
									  'name' => $pathName
									 );
					$comp   = FALSE;
					$isItem = (substr($pathName, -5) == '.html');
				// category ?
					if (!$comp && !$cLink && !$iLink && !$isItem && !$exLink) {//2007-10-06 http://japan.nucleuscms.org/bb/viewtopic.php?p=20641#20641
						$linkObj['linkparam'] = 'category';
						$cat_id               = $this->getRequestPathInfo($linkObj);
						if (!empty($cat_id)) {
							$catid = intval($cat_id);
							$cLink = TRUE;
							$comp  = TRUE;
						}
					}
				// subcategory ?
					if (!$comp && $cLink && !$iLink && $mcategories && !$isItem && !$exLink) {//2007-10-06 http://japan.nucleuscms.org/bb/viewtopic.php?p=20641#20641
						$linkObj['linkparam'] = 'subcategory';
						$linkObj['bid']       = $catid;
						$subcat_id            = $this->getRequestPathInfo($linkObj);
						if (!empty($subcat_id)) {
							$_REQUEST[$subrequest] = intval($subcat_id);
							$subcatid              = intval($subcat_id);
							$sc                    = $i;
							$comp                  = TRUE;
						}
					}
				// item ?
					if ($isItem) {
						$linkObj['linkparam'] = 'item';
						$item_id              = $this->getRequestPathInfo($linkObj);
						if (!empty($item_id)) {
							$itemid = intval($item_id);
							$iLink  = TRUE;
						}
						if (preg_match('/^page_/', $pathName)) {
							$iLink  = TRUE;
						}
//var_dump($linkObj);
					}
				break;
			}
			if (preg_match('/^[0-9page]$/', $pathName)) {
				$exLink = $pathName;
			}
			$i++;
		}

		if ($NP_ExtraSkinJPFlag) {
			$this->goNP_ExtraSkinJP();
		}

// FancyURL redirect to Customized URL if use it
// HTTP status 301 "Moved Permanentry"
		if ($redURI) {
			if (strpos(serverVar('REQUEST_URI'), '?') !== FALSE) {
				list($trush, $tempQueryString) = explode('?', serverVar('REQUEST_URI'), 2);
			}
			if ($tempQueryString) {
				$temp = explode('&', $tempQueryString);
				foreach ($temp as $k => $val) {
					if (preg_match('/^virtualpath/', $val)) {
						unset($temp[$k]);
					}
				}
				if (!empty($temp)) {
					$tempQueryString = '?' . implode('&', $temp);
				}
			}
			header('HTTP/1.1 301 Moved Permanently');
			header('Location: ' . $redURI . $tempQueryString);
			exit;
		}
		$feedurl = array(
						 'rss1.xml',
						 'index.rdf',
						 'rss2.xml',
						 'atom.xml',
						);
		$siteMapPlugin = $this->pluginCheck('GoogleSitemap');
		if (!$siteMapPlugin) {
			$siteMapPlugin = $this->pluginCheck('SEOSitemaps');
		}
		if ($siteMapPlugin) {
			$pcSitemaps = $siteMapPlugin->getAllBlogOptions('PcSitemap');
			foreach ($pcSitemaps as $pCsitemap) {
				if ($pCsitemap) {
					$feedurl[] = $pCsitemap;
				}
			}
			$mobSitemaps = $siteMapPlugin->getAllBlogOptions('MobileSitemap');
			foreach ($mobSitemaps as $mobSitemap) {
				if ($mobSitemap) {
					$feedurl[] = $mobSitemap;
				}
			}
		}
		$feedurl      = array_unique($feedurl);
		$request_path = end($v_path);
		$feeds        = in_array($request_path, $feedurl, true);

// finish decode
		if (!$exLink && !$feeds) {
// URL Not Found
			if (substr(end($v_path), -5) == '.html' && !$iLink) {
				$notFound = TRUE;
				if (!empty($subcatid)) {
					$linkParam = array(
									   $subrequest => $subcatid
									  );
					$uri       = createCategoryLink($catid, $linkParam);
				} elseif (!empty($catid)) {
					$uri = createCategoryLink($catid);
				} else {
					$uri = createBlogidLink($blogid);
				}
			} elseif (count($v_path) > $sc && !empty($subcatid) && !$iLink) {
				$notFound  = TRUE;
				$linkParam = array(
								   $subrequest => $subcatid
								  );
				$uri       = createCategoryLink($catid, $linkParam);
			} elseif (count($v_path) >= 2 && !$subcatid && !$iLink) {
				$notFound = TRUE;
				if (isset($catid)) {
					$uri = createCategoryLink($catid);
				} else {
					$uri = createBlogidLink($blogid);
				}
			} elseif (reset($v_path) && !$catid && !$subcatid && !$iLink) {
				$notFound = TRUE;
				$uri      = createBlogidLink($blogid);
			} else {
// Found
// setting $CONF['Self'] for other plugins
				$uri                    = createBlogidLink($blogid);
				$CONF['Self']           = rtrim($uri, '/');
				$CONF['BlogURL']        = rtrim($uri, '/');
				$CONF['ItemURL']        = rtrim($uri, '/');
				$CONF['CategoryURL']    = rtrim($uri, '/');
				$CONF['ArchiveURL']     = rtrim($uri, '/');
				$CONF['ArchiveListURL'] = rtrim($uri, '/');
				$complete               = TRUE;
				return ;
			}
		} else {
			$uri                    = createBlogidLink($blogid);
			$CONF['Self']           = rtrim($uri, '/');
			$CONF['BlogURL']        = rtrim($uri, '/');
			$CONF['ItemURL']        = rtrim($uri, '/');
			$CONF['CategoryURL']    = rtrim($uri, '/');
			$CONF['ArchiveURL']     = rtrim($uri, '/');
			$CONF['ArchiveListURL'] = rtrim($uri, '/');
			$complete               = TRUE;
			return ;
		}
// Behavior Not Found
		if ($notFound) {
			if (substr($uri, -1) != '/') {
				$uri .= '/';
			}
			if ($this->getOption('customurl_notfound') == '404') {
				header('HTTP/1.1 404 Not Found');
				doError(_NO_SUCH_URI);
				exit;
			} else {
				header('HTTP/1.1 303 See Other');
				header('Location: ' . $uri);
				exit;
			}
		}
	}

	function goNP_ExtraSkinJP()
	{
		$ExtraSkinJP = $this->pluginCheck('ExtraSkinJP');
		// under v3.2 needs this
		if ($CONF['DisableSite'] && !$member->isAdmin()) {
			header('Location: ' . $CONF['DisableSiteURL']);
			exit;
		}
		$extraParams = explode("/", serverVar('PATH_INFO'));
		array_shift ($extraParams);

		if (isset($extraParams[1]) && preg_match("/^([1-9]+[0-9]*)(\?.*)?$/", $extraParams[1], $matches)) {
			$extraParams[1] = $matches[1];
		}

		$ExtraSkinJP->extra_selector($extraParams);
		exit;
	}

// decode 'path name' to 'id'
	function getRequestPathInfo($linkObj)
	{
		$query     = 'SELECT obj_id as result'
				   . ' FROM %s'
				   . ' WHERE obj_name  = "%s"'
				   . ' AND   obj_bid   = %d'
				   . ' AND   obj_param = "%s"';
		$name      = $this->quote_smart($linkObj['name']);
		$bid       = $this->quote_smart($linkObj['bid']);
		$linkparam = $this->quote_smart($linkObj['linkparam']);
		$query     = sprintf($query, _CUSTOMURL_TABLE, $name, $bid, $linkparam);
		$ObjID     = quickQuery($query);
		if (!$ObjID) {
			return;
		} else {
			return intval($ObjID);
		}
	}

// Receive TrackBack ping
	function _trackback($bid, $path)
	{
		$blog_id   = intval($bid);
		$TrackBack = $this->pluginCheck('TrackBack');
		if ($TrackBack) {
			if (substr($path, -5, 5) == '.html') {
				$linkObj = array (
								  'linkparam' => 'item',
								  'bid'       => $blog_id,
								  'name'      => $path
								 );
				$item_id = $this->getRequestPathInfo($linkObj);
				if ($item_id) {
					$tb_id = intval($item_id);
				} else {
					doError(_NO_SUCH_URI);
				}
			} else {
				$tb_id = intval($path);
			}

			$errorMsg = $TrackBack->handlePing($tb_id);
			if ($errorMsg != '') {
				$TrackBack->xmlResponse($errorMsg);
			} else {
				$TrackBack->xmlResponse();
			}
		}
		exit;
	}

	function event_GenerateURL($data)
	{
		global $CONF, $manager, $blogid;
		if ($data['completed']) {
			return;
		}
		if (is_numeric($blogid)) {
			$blogid = intval($blogid);
		} else {
			$blogid = intval(getBlogIDFromName($blogid));
		}
		$mcategories = $this->pluginCheck('MultipleCategories');
		if ($mcategories) {
			if (method_exists($mcategories, 'getRequestName')) {
				$param = array();
				$mcategories->event_PreSkinParse($param);
				global $subcatid;
				$subrequest = $mcategories->getRequestName();
			}
		}
		if ($subcatid) {
			$subcatid = intval($subcatid);
		}
		$OP_ArchiveKey	= $this->getOption('customurl_archive');
		$OP_ArchivesKey	= $this->getOption('customurl_archives');
		$OP_MemberKey	= $this->getOption('customurl_member');
		$params         = $data['params'];
//		$catParam       = $params['extra']['catid'];
//		$subcatParam    = $params['extra'][$subrequest];
		$useCustomURL   = $this->getAllBlogOptions('use_customurl');
		$objPath        = NULL;
		$burl           = NULL;
		switch ($data['type']) {
			case 'item':
				if (!is_numeric($params['itemid'])) {
					return;
				}
				$item_id = intval($params['itemid']);
				if ($item_id) {
					$bid     = intval(getBlogIDFromItemID($item_id));
					if ($useCustomURL[$bid] == 'no') {
						return;
					}
					$query = 'SELECT obj_name as result '
						   . 'FROM  %s '
						   . 'WHERE obj_param = "item" '
						   . 'AND   obj_id    = %d';
					$path  = quickQuery(sprintf($query, _CUSTOMURL_TABLE, $item_id));
					if ($path) {
						$objPath = $path;
					} else {
						if (!$this->_isValid(array('item', 'inumber', $item_id))) {
							$objPath = _NOT_VALID_ITEM;
						} else {
							$y = $m = $d = $temp = '';
							$table  =  sql_table('item');
							$tque   = 'SELECT itime as result '
									. 'FROM   %s '
									. 'WHERE  inumber = %d';
							$itime  = quickQuery(sprintf($tque ,$table, $item_id));
							sscanf($itime,'%d-%d-%d %s', $y, $m, $d, $temp);
							$defItem   = $this->getOption('customurl_dfitem');
							$tempParam = array(
											   'year'  => $Y,
											   'month' => $m,
											   'day'   => $d
											  );
							$ikey      = TEMPLATE::fill($defItem, $tempParam);
							$ipath     = $ikey . '_' . $item_id;
							$query     = 'SELECT ititle as result '
									   . 'FROM  %s '
									   . 'WHERE inumber = %d';
							$query     = sprintf($query, $table, $item_id);
							$iname     = quickQuery($query);
							$this->RegistPath($item_id, $ipath, $bid, 'item', $iname, TRUE);
							$objPath   = $ipath . '.html';
						}
					}
					if ($bid != $blogid) {
						$burl = $this->_generateBlogLink($bid);
					} else {
						$burl = $this->_generateBlogLink($blogid);
					}
				} else {
					$objPath = '';
				}
			break;
			case 'member':
				if (!is_numeric($params['memberid']) || $useCustomURL[$blogid] =='no') {
					return;
				}
				$memberID = intval($params['memberid']);
				if ($memberID) {
					$path = $this->getMemberOption($memberID, 'customurl_mname');
					if ($path) {
						$data['url'] = $this->_generateBlogLink($blogid) . '/'
									 . $OP_MemberKey . '/' . $path . '.html';
						$data['completed'] = TRUE;
						return;
					} else {
						if (!$this->_isValid(array('member', 'mnumber', $memberID))) {
							$data['url'] = $this->_generateBlogLink($blogid) . '/'
										 . _NOT_VALID_MEMBER;
							$data['completed'] = TRUE;
							return;
						} else {
							$query = 'SELECT mname as result FROM %s'
								   . ' WHERE mnumber = %d';
							$table = sql_table('member');
							$mname = quickQuery(sprintf($query, $table, $memberID));
							$this->RegistPath($memberID, $mname, 0, 'member', $mname, TRUE);
							$data['url'] = $this->_generateBlogLink($blogid) . '/'
										 . $OP_MemberKey . '/' . $mname . '.html';
							$data['completed'] = TRUE;
							return;
						}
					}
				} else {
					$objPath = '';
				}
			break;
			case 'category':
				if (!is_numeric($params['catid'])) {
					return;
				}
				$cat_id = intval($params['catid']);
				$bid = intval(getBlogidFromCatID($cat_id));
				if ($useCustomURL[$bid] == 'no') {
					return;
				}
				if (!$cat_id) {
					$objPath = '';
					$bid = $blogid;
				} else {
					$objPath = $this->_generateCategoryLink($cat_id);
				}
				if ($bid != $blogid) {
					$burl = $this->_generateBlogLink($bid);
				}
			break;
			case 'archivelist':
				if ($useCustomURL[$blogid] == 'no') {
					return;
				}
				$objPath = $OP_ArchivesKey . '/';
				$bid     = intval($params['blogid']);
				$burl    = $this->_generateBlogLink($bid);
			break;
			case 'archive':
				if ($useCustomURL[$blogid] == 'no') {
					return;
				}
				sscanf($params['archive'], '%d-%d-%d', $y, $m, $d);
				if ($d) {
					$arc = sprintf('%04d-%02d-%02d', $y, $m, $d);
				} elseif ($m) {
					$arc = sprintf('%04d-%02d',      $y, $m);
				} else {
					$arc = sprintf('%04d',           $y);
				}
				$objPath = $OP_ArchiveKey . '/' . $arc . '/';
				$bid     = intval($params['blogid']);
				$burl = $this->_generateBlogLink($bid);
			break;
			case 'blog':
				if (!is_numeric($params['blogid'])) {
					return;
				}
				$bid  = intval($params['blogid']);
				$burl = $this->_generateBlogLink($bid);
			break;
			default:
				return;
		}
		if (!$burl) {
			$burl = $this->_generateBlogLink($blogid);
		}

		$denyPlugins = array(
							 'np_analyze',
							 'np_googlesitemap',
							);
		$tempdeb=debug_backtrace();
		$denyPlugin = FALSE;
		foreach($tempdeb as $k => $v){
			if (array_key_exists('class', $v)) {
				$analyzePlugin = (strtolower($v['class']) == 'np_analyze');
				$sitemapPlugin = (strtolower($v['class']) == 'np_googlesitemap' || 
								  strtolower($v['class']) == 'np_seositemaps');
				if ($analyzePlugin || $sitemapPlugin) {
					$denyPlugin = TRUE;
				}
			}
		}

		if (!$denyPlugin && $bid != $blogid) {
			$params['extra'] = array();
		}
		if ($objPath || $data['type'] == 'blog') {
			$LinkURI = $this->_addLinkParams($objPath, (array_key_exists('extra', $params) ? $params['extra'] : ''));
			if ($LinkURI) {
				$data['url'] = $burl . '/' . $LinkURI;
			} else {
				$data['url'] = $burl;
			}
			$arcTmp      = (preg_match('/' . $OP_ArchivesKey . '/', $data['url']));
			$arcsTmp     = (preg_match('/' . $OP_ArchiveKey . '/', $data['url']));
			$isArchives  = ($arcTmp || $arcsTmp);
			$isItem      = (substr($data['url'], -5, 5) == '.html');
			$isDirectory = (substr($data['url'], -1) == '/');
			$puri        = parse_url($data['url']);
			if (!$isItem && !$isDirectory && !array_key_exists('query', $puri)) {
				$data['url'] .= '/';
			}
			$data['completed'] = TRUE;
			if (strstr ($data['url'], '//')) {
				$link = preg_replace("/([^:])\/\//", "$1/", $data['url']);
			}
			//$tempdeb=debug_backtrace();
			$tb = 0;
			foreach($tempdeb as $k => $v){
				if (array_key_exists('class', $v) && array_key_exists('function', $v)
					&& strtolower($v['class']) == 'np_trackback' 
					&& strtolower($v['function']) == 'gettrackbackurl') {
					$tb = 1;
				}
			}
			if ($tb == 1 && $data['type'] == 'item' && $isItem) {
				$data['url'] = substr($data['url'], 0, -5);
			}
		} else {
			$data['url'] = $this->_generateBlogLink($blogid) . '/';
			$data['completed'] = TRUE;
		}
		return $data;
	}

	function _createSubCategoryLink($scid)
	{
		$scids     = $this->getParents(intval($scid));
		$subcatids = explode('/', $scids);
		$eachPath  = array();
		foreach ($subcatids as $sid) {
			$subcat_id = intval($sid);
			$query     = 'SELECT obj_name as result'
					   . ' FROM         %s'
					   . ' WHERE    obj_id = %d'
					   . ' AND   obj_param = "%s"';
			$query     = sprintf($query, _CUSTOMURL_TABLE, $subcat_id, 'subcategory');
			$path      = quickQuery($query);
			if ($path) {
				$eachPath[] = $path;
			} else {
				$tempParam = array(
								   'plug_multiple_categories_sub',
								   'scatid',
								   $subcat_id
								  );
				if (!$this->_isValid($tempParam)) {
					return $url = _NOT_VALID_SUBCAT;
				} else {
					$scpath = $this->getOption('customurl_dfscat') . '_' . $subcat_id;
					$query  = 'SELECT catid as result FROM %s WHERE scatid = %d';
					$query  = sprintf($query, _C_SUBCAT_TABLE, $subcat_id);
					$cid    = quickQuery($query);
					if (!$cid) {
						return 'no_such_subcat=' . $subcat_id . '/';
					}
					$this->RegistPath($subcat_id, $scpath, $cid, 'subcategory', 'subcat_' . $subcat_id, TRUE);
					$eachPath[] = $scpath;
				}
			}
		}
		$subcatPath = @implode('/', $eachPath);
		return $subcatPath . '/';
	}

	function getParents($subid)
	{
		$mcatPlugin  = $this->pluginCheck('MultipleCategories');
		$mcatVarsion = $mcatPlugin->getVersion() * 100;
		if (intval($mcatVarsion) < 40) {
			return intval($subid);
		}
		$subcat_id          = intval($subid);
		$query              = 'SELECT '
							. 'scatid, '
							. 'parentid '
							. 'FROM %s '
							. 'WHERE scatid = %d';
		$query              = sprintf($query, _C_SUBCAT_TABLE, $subcat_id);
		$res                = sql_query($query);
		list($sid, $parent) = sql_fetch_row($res);
		if ($parent != 0) {
			$r = $this->getParents($parent) . '/' . $sid;
		} else {
			$r = $sid;
		}
		return $r;
	}

	function _generateCategoryLink($cid)
	{
		global $CONF;
		$cat_id = intval($cid);
		$path   = $this->getCategoryOption($cat_id, 'customurl_cname');
		if ($path) {
			return $path . '/';
		} else {
			$catData = array(
							 'category',
							 'catid',
							 $cat_id
							);
			if (!$this->_isValid($catData)) {
				return $url = _NOT_VALID_CAT;
			} else {
				$cpath   = $this->getOption('customurl_dfcat') . '_' . $cat_id;
				$blog_id = intval(getBlogIDFromCatID($cat_id));
				$catname = 'catid_' . $cat_id;
				$this->RegistPath($cat_id, $cpath, $blog_id, 'category', $catname, TRUE);
				return $cpath . '/';
			}
		}
	}

	function _generateBlogLink($bid)
	{
		global $manager, $CONF;
		$blog_id = intval($bid);
		$param   = array(
						 'blog',
						 'bnumber',
						 $blog_id
						);
		if (!$this->_isValid($param)) {
			return _NOT_VALID_BLOG;
		}
		$b    =& $manager->getBlog($blog_id);
		$burl = $b->getURL();
		if ($this->getBlogOption($blog_id, 'use_customurl') == 'yes') {
			if ($blog_id == $CONF['DefaultBlog'] && $this->getOption('customurl_incbname') == 'no') {
				if (empty($burl)) {
					$this->_updateBlogURL($CONF['IndexURL'], $blog_id);
				}
				$burl = $CONF['IndexURL'];
			} else {
				if (empty($burl)) {
					$burl = $CONF['IndexURL'];
				}
				if (substr($burl, -4) == '.php' || $burl == $CONF['IndexURL']) {
					$path = $this->getBlogOption($blog_id, 'customurl_bname');
					if ($path) {
						$burl = $CONF['IndexURL'] . $path;
					} else {
						$query = 'SELECT bshortname as result'
							   . ' FROM %s'
							   . ' WHERE bnumber = %d';
						$query = sprintf($query, sql_table('blog'), $blog_id);
						$bpath = quickQuery($query);
						$this->RegistPath($blog_id, $bpath, 0, 'blog', $bpath, TRUE);
						$burl  = $CONF['IndexURL'] . $bpath . '/';
					}
					$this->_updateBlogURL($burl, $blog_id);
				}
			}
		}

		return trim($burl, '/');
	}

	function _updateBlogURL($burl, $blogid)
	{
		$blogid      = intval($blogid);
		$burl_update = 'UPDATE %s '
					 . 'SET    burl = "%s" '
					 . 'WHERE  bnumber = %d';
		$burl        = $this->quote_smart($burl);
		$bTable      = sql_table('blog');
		sql_query(sprintf($burl_update, $bTable, $burl, $blogid));
	}

	function _addLinkParams($link, $params)
	{
		global $CONF, $manager, $catid;
		$arcTmp      = (preg_match('/' . $this->getOption('customurl_archives') . '/', $link));
		$arcsTmp     = (preg_match('/' . $this->getOption('customurl_archive') . '/', $link));
		$isArchives  = ($arcTmp || $arcsTmp);
		$mcategories = $this->pluginCheck('MultipleCategories');
		if ($mcategories) {
			$param = array();
			$mcategories->event_PreSkinParse($param);
			global $subcatid;
			if (method_exists($mcategories, 'getRequestName')) {
				$subrequest = $mcategories->getRequestName();
			} else {
				$subrequest = 'subcatid';
			}
		}
		$linkExtra = '';
		if (is_array($params)) {
			if (array_key_exists('archives', $params)) {
				$linkExtra = $this->getOption('customurl_archives') . '/';
				unset($params['archives']);
			} elseif (array_key_exists('archivelist', $params)) {
				$linkExtra = $this->getOption('customurl_archives') . '/';
				unset($params['archivelist']);
			} elseif (array_key_exists('archive', $params)) {
				sscanf($params['archive'], '%d-%d-%d', $y, $m, $d);
				if ($d) {
					$arc = sprintf('%04d-%02d-%02d', $y, $m, $d);
				} elseif ($m) {
					$arc = sprintf('%04d-%02d',      $y, $m);
				} else {
					$arc = sprintf('%04d',           $y);
				}
				$linkExtra = $this->getOption('customurl_archive') . '/' . $arc;
				unset($params['archive']);
			}
			if (array_key_exists('blogid', $params)) {
				unset($params['blogid']);
			}
			$paramlink = array();
			foreach ($params as $param => $value) {
				switch ($param) {
					case 'catid':
					case $CONF['CategoryKey']:
						$cid         = intval($value);
						$paramlink[] = $this->_generateCategoryLink($cid);
					break;
					case $subrequest:
						if ($mcategories) {
							$sid         = intval($value);
							$paramlink[] = $this->_createSubCategoryLink($sid);
						}
					break;
					default:
						$paramlink[] = $param . '/' . $value . '/';
					break;
				}
			}
			if (substr($link, -5, 5) == '.html' || $isArchives) {
				$link = implode('', $paramlink) . $link;
			} else {
				$link .= implode('', $paramlink);
			}
		}
		if ($linkExtra) {
			$link .= $linkExtra;
		}
		if (requestVar('skinid')) {
			$skinid = htmlspecialchars(requestVar('skinid'), ENT_QUOTES, _CHARSET);
			if (!$link) {
				$link = '?skinid=' . $skinid;
			} elseif (strpos('?', $link)) {
				$link .= '&amp;skinid=' . $skinid;
			} else {
				if (substr($link, -1) != '/' && !empty($link)) {
					$link .= '/?skinid=' . $skinid;
				} else {
					$link .= '?skinid=' . $skinid;
				}
			}
		}
		if (strstr ($link, '//')) {
			$link = preg_replace("/([^:])\/\//", "$1/", $link);
		}
		return $link;
	}

	function doSkinVar($skinType, $link_type = '', $target = '', $title = '')
	{
		global $blogid;
		if ($skinType == 'item' && $link_type == 'trackback') {
			global $itemid, $CONF;
			if ($this->getBlogOption($blogid, 'use_customurl') == 'yes') {
				$que      = 'SELECT obj_name as result '
						  . 'FROM %s '
						  . 'WHERE obj_param = "item" '
						  . 'AND      obj_id = %d';
				$itempath = quickQuery(sprintf($que, _CUSTOMURL_TABLE, $itemid));
				if ($target != 'ext') {
					$uri = $CONF['BlogURL'] . '/trackback/' . $itempath;
				} elseif ($target == 'ext') {
// /item_123.trackback
					$itempath = substr($itempath, 0, -5) . '.trackback';
					$uri      = $CONF['BlogURL'] . '/' . $itempath;
				}
			} else {
				$uri = $CONF['ActionURL']
					 . '?action=plugin&amp;name=TrackBack&amp;tb_id=' . $itemid;
			}
			echo $uri;
			return;
		}
		// $data == type / id || name / 'i'd || 'n'ame
		// ex. =>	[(b)log / blogid [|| shortname / 'i'd || 'n'ame]]
		//			(c)at  / catid [|| cname / 'i'd || 'n'ame]
		//			(s)cat / subcatid [|| sname / 'i'd || 'n'ame]
		//			[(i)tem /] itemid [/ 'path']
		//			(m)ember / mnumber [|| mname / 'i'd || 'n'ame]
		//
		// if second param is null, third param is id
		// if param is null, generate blog link
		if (!$link_type) {
			$link_params = array(0, 'b/' . intval($blogid) . '/i,'
						 . $target . ',' . $title);
		} else {
			$l_params = explode("/", $link_type);
			if (count($l_params) == 1) {
				$link_params = array(0, 'b/' . intval($link_type) . '/i,'
							 . $target . ',' . $title);
			} else {
				$link_params = array(0,
									 $link_type . ',' . $target . ',' . $title);
			}
		}
		echo $this->URL_Callback($link_params);
	}

	function doItemVar(&$item, $link_type = '', $target = '', $title = '')
	{
		if (getNucleusVersion() < '330') {
			return;
		}
		$item_id = intval($item->itemid);
		if (!$link_type || $link_type == 'subcategory') {
			$link_params = array(0,
								 'i/' . $item_id . '/i,' . $target . ',' . $title);
		} elseif ($link_type == 'path') {
			$link_params = array(0,
								 'i/' . $item_id . '/path,' . $target . ',' . $title);
		} else {
			$link_params = array(0,
								 $link_type . ',' . $target . ',' . $title);
		}
		if ($link_type == 'subcategory') {
			echo $this->URL_Callback($link_params, 'scat');
		} else {
			echo $this->URL_Callback($link_params);
		}
	}

	function doTemplateVar(&$item, $link_type = '', $target = '', $title = '')
	{
		$item_id = intval($item->itemid);
		if ($link_type == 'trackback') {
			global $CONF;
			$blog_id = intval(getBlogIDFromItemID($item_id));
			if ($this->getBlogOption($blog_id, 'use_customurl') == 'yes') {
				$que      = 'SELECT obj_name as result '
						  . 'FROM %s '
						  . 'WHERE obj_param = "item" '
						  . 'AND      obj_id = %d';
				$itempath = quickQuery(sprintf($que, _CUSTOMURL_TABLE, $item_id));
				if ($target != 'ext') {
					$uri = $CONF['BlogURL'] . '/trackback/' . $itempath;
				} elseif ($target == 'ext') {
// /item_123.trackback
					$itempath = substr($itempath, 0, -5) . '.trackback';
					$uri = $CONF['BlogURL'] . '/' . $itempath;
				}
			} else {
				$uri = $CONF['ActionURL']
					 . '?action=plugin&amp;name=TrackBack&amp;tb_id=' . $item_id;
			}
			echo $uri;
			return;
		}
		if (!$link_type || $link_type == 'subcategory') {
			$link_params = array(0,
								 'i/' . $item_id . '/i,' . $target . ',' . $title);
		} elseif ($link_type == 'path') {
			$link_params = array(0,
								 'i/' . $item_id . '/path,' . $target . ',' . $title);
		} else {
			$link_params = array(0,
								 $link_type . ',' . $target . ',' . $title);
		}
		if ($link_type == 'subcategory') {
			echo $this->URL_Callback($link_params, 'scat');
		} else {
			echo $this->URL_Callback($link_params);
		}
	}

	function URL_Callback($data, $scatFlag = '')
	{
		$l_data  = explode(",", $data[1]);
		$l_type  = $l_data[0];
		$target  = $l_data[1];
		$title   = $l_data[2];
		$item_id = intval($this->currentItem->itemid);
		if (!$l_type) {
			$link_params = array (
								  'i',
								  $item_id,
								  'i'
								 );
		} else {
			$link_data = explode("/", $l_type);
			if (count($link_data) == 1) {
				$link_params = array (
									  'i',
									  intval($l_type),
									  'i'
									 );
			} elseif (count($link_data) == 2) {
				if ($link_data[1] == 'path') {
					$link_params = array (
										  'i',
										  $link_data[0],
										  'path'
										 );
				} else {
					$link_params = array (
										  $link_data[0],
										  intval($link_data[1]),
										  'i'
										 );
				}
			} else {
				$link_params = array (
									  $link_data[0],
									  $link_data[1],
									  $link_data[2]
									 );
			}
		}
		$url = $this->_genarateObjectLink($link_params, $scatFlag);
		if ($target) {
			if ($title) {
				$ObjLink = '<a href="' . $this->hsc($url) . '" '
						 . 'title="' . $this->hsc($title) . '">'
						 . $this->hsc($target) . '</a>';
			} else {
				$ObjLink = '<a href="' . $this->hsc($url) . '" '
						 . 'title="' . $this->hsc($target) . '">'
						 . $this->hsc($target) . '</a>';
			}
		} else {
			$ObjLink = $this->hsc($url);
		}
		return $ObjLink;
	}

	function _isValid($data)
	{
		$query   = 'SELECT * FROM %s WHERE %s = %d';
		$data[2] = $this->quote_smart($data[2]);
		$query   = sprintf($query, sql_table($data[0]), $data[1], $data[2]);
		$res     = sql_query($query);
		return (sql_num_rows($res) != 0);
	}

	function _genarateObjectLink($data, $scatFlag = '')
	{
		global $CONF, $manager, $blog;
		$ext = substr(serverVar('REQUEST_URI'), -4);
		if ($ext == '.rdf' || $ext == '.xml') {
			$CONF['URLMode']  = 'pathinfo';
		}
		if ($CONF['URLMode'] != 'pathinfo') {
			return;
		}
		$query = 'SELECT %s as result FROM %s WHERE %s = "%s"';
		switch ($data[0]) {
			case 'b':
				if ($data[2] == 'n') {
					$bid = getBlogIDFromName($data[1]);
				} else {
					$bid = $data[1];
				}
				$blog_id = intval($bid);
				$param   = array(
								 'blog',
								 'bnumber',
								 $blog_id
								);
				if (!$this->_isValid($param)) {
					$url = _NOT_VALID_BLOG;
				} else {
					$url = $this->_generateBlogLink($blog_id) . '/';
				}
			break;
			case 'c':
				if ($data[2] == 'n') {
					$cid = getCatIDFromName($data[1]);
				} else {
					$cid = $data[1];
				}
				$cat_id = intval($cid);
				$param = array(
							   'category',
							   'catid',
							   $cat_id
							  );
				if (!$this->_isValid($param)) {
					$url = _NOT_VALID_CAT;
				} else {
					$url = createCategoryLink($cat_id);
				}
			break;
			case 's':
				$mcategories = $this->pluginCheck('MultipleCategories');
				if ($mcategories) {
					if ($data[2] == 'n') {
						$temp = $this->quote_smart($data[1]);
						$sque = sprintf($query, 'scatid', _C_SUBCAT_TABLE, 'sname', $temp);
						$scid = quickQuery($sque);
					} else {
						$scid = $data[1];
					}
					$sub_id = intval($scid);
					$param  = array(
									'plug_multiple_categories_sub',
									'scatid',
									$sub_id
								   );
					if (!$this->_isValid($param)) {
						$url = _NOT_VALID_SUBCAT;
					} else {
						$cqe        = sprintf($query, 'catid', _C_SUBCAT_TABLE, 'scatid', $sub_id);
						$cid        = quickQuery($cqe);
						$cid        = intval($cid);
						if (method_exists($mcategories, "getRequestName")) {
							$subrequest = $mcategories->getRequestName();
						}
						if (!$subrequest) {
							$subrequest = 'subcatid';
						}
						$linkParam = array(
										   $subrequest => $sub_id
										  );
						$url       = createCategoryLink($cid, $linkParam);
					}
				}
			break;
			case 'i':
				$param = array(
							   'item',
							   'inumber',
							   intval($data[1])
							  );
				if (!$this->_isValid($param)) {
					$url = _NOT_VALID_ITEM;
				} else {
					if ($scatFlag) {
						global $catid, $subcatid;
						if (!empty($catid)) {
							$linkparams['catid'] = intval($catid);
						}
						if (!empty($subcatid)) {
							$mcategories = $this->pluginCheck('MultipleCategories');
							if ($mcategories) {
								if (method_exists($mcategories, 'getRequestName')) {
									$subrequest = $mcategories->getRequestName();
								} else {
									$subrequest = 'subcatid';
								}
							}
							$linkparams[$subrequest] = intval($subcatid);
						}
						$url = createItemLink(intval($data[1]), $linkparams);
					} else {
						$blink = $this->_generateBlogLink(getBlogIDFromItemID(intval($data[1])));
						$i_query = 'SELECT obj_name as result '
								 . 'FROM %s '
								 . 'WHERE obj_param = "item" '
								 . 'AND      obj_id = %d';
						$i_query = sprintf($i_query, _CUSTOMURL_TABLE, intval($data[1]));
						$path    = quickQuery($i_query);
						if ($path) {
							if ($data[2] == 'path') {
								$url = $path;
							} else {
								$url = $blink . '/' . $path;
							}
						} else {
							if ($data[2] == 'path') {
								$url = $CONF['ItemKey'] . '/'
									 . intval($data[1]);
							} else {
								$url = $blink . '/' . $CONF['ItemKey'] . '/'
									 . intval($data[1]);
							}
						}
					}
				}
			break;
			case 'm':
				if ($data[2] == 'n') {
					$data[1] = $this->quote_smart($data[1]);
					$mque    = sprintf($query, 'mnumber', sql_table('member'), 'mname', $data[1]);
					$mid     = quickQuery($mque);
				} else {
					$mid = $data[1];
				}
				$member_id = intval($mid);
				$param = array(
							   'member',
							   'mnumber',
							   $member_id
							  );
				if (!$this->_isValid($param)) {
					$url = _NOT_VALID_MEMBER;
				} else {
					$url = createMemberLink($member_id);
				}
			break;
		}
		return $url;
	}

	function event_InitSkinParse($data)
	{
		global $blogid, $CONF, $manager;
		$feedurl = array(
						 'rss1.xml',
						 'index.rdf',
						 'rss2.xml',
						 'atom.xml',
						);
		$reqPaths = explode('/', serverVar('PATH_INFO'));
		$reqPath  = end($reqPaths);
		$feeds    = in_array($reqPath, $feedurl, true);
		if (!$feeds) {
			return;
		} else {
			$p_info = trim(serverVar('PATH_INFO'), '/');
			$path_arr = explode('/', $p_info);
			switch (end($path_arr)) {
				case 'rss1.xml':
				case 'index.rdf':
					$skinName = 'feeds/rss10';
					break;
				case 'rss2.xml':
					$skinName = 'feeds/rss20';
					break;
				case 'atom.xml':
					$skinName = 'feeds/atom';
					break;
			}
			if (SKIN::exists($skinName)) {
				$skin =& SKIN::createFromName($skinName);
				$data['skin']->SKIN($skin->getID());
				$skinData =& $data['skin'];
				$pageType =  $data['type'];
				if (!$CONF['DisableSite']) {
					ob_start();

					$skinID    = $skinData->id;
					$contents  = $this->getSkinContent($pageType, $skinID);
					$actions   = SKIN::getAllowedActionsForType($pageType);
					$dataArray = array(
									   'skin'     => &$skinData,
									   'type'     =>  $pageType,
									   'contents' => &$contents
									  );
					$manager->notify('PreSkinParse', $dataArray);
					PARSER::setProperty('IncludeMode',   SKIN::getIncludeMode());
					PARSER::setProperty('IncludePrefix', SKIN::getIncludePrefix());
					$handler = new ACTIONS($pageType, $skinData);
					$parser  = new PARSER($actions, $handler);
					$handler->setParser($parser);
					$handler->setSkin($skinData);
					$parser->parse($contents);
					$dataArray = array(
									   'skin' => &$skinData,
									   'type' =>  $pageType
									  );
					$manager->notify('PostSkinParse', $dataArray);

					$feed = ob_get_contents();

					ob_end_clean();
					$eTag = '"' . md5($feed) . '"';
					header('Etag: ' . $eTag);
					if ($eTag == serverVar('HTTP_IF_NONE_MATCH')) {	
						header('HTTP/1.0 304 Not Modified');
						header('Content-Length: 0');
					} else {
						if (extension_loaded('mbstring')) {
							$feed = mb_convert_encoding($feed, 'UTF-8', _CHARSET);
							$charset = 'UTF-8';
						} else {
							$charset = _CHARSET;
						}
						header('Content-Type: application/xml; charset=' . $charset);
						header('Generator: Nucleus CMS ' . $nucleus['version']);
						// dump feed
						echo $feed;
					}
				} else {
					echo '<' . '?xml version="1.0" encoding="ISO-8859-1"?' . '>';
?>
<rss version="2.0">
  <channel>
    <title><?php echo $this->hsc($CONF['SiteName'], ENT_QUOTES)?></title>
    <link><?php echo $this->hsc($CONF['IndexURL'], ENT_QUOTES)?></link>
    <description></description>
    <docs>http://backend.userland.com/rss</docs>
  </channel>
</rss>	
<?php
				}
			}
		exit;
		}
	}

	function getSkinContent($pageType, $skinID)
	{
		$skinID   = intval($skinID);
		$pageType = addslashes($pageType);
		$query    = 'SELECT scontent '
				  . 'FROM %s '
				  . 'WHERE sdesc = %d '
				  . 'AND   stype = %d';
		$query    = sprintf($query, sql_table('skin'), $skinID, $pageType);
		$res      = sql_query($query);

		if (sql_num_rows($res) == 0) {
			return '';
		} else {
			return sql_result($res, 0, 0);
		}
	}


// merge NP_RightURL
	function event_PreSkinParse($data)
	{
		global $CONF, $manager, $blog, $catid, $itemid, $subcatid;
		global $memberid;
		if (!$blog) {
			$b =& $manager->getBlog($CONF['DefaultBlog']);
		} else {
			$b =& $blog;
		}
		$blogurl = $b->getURL();
		
		if (!$blogurl) {
			if($blog) {
				$b_tmp   =& $manager->getBlog($CONF['DefaultBlog']);
				$blogurl =  $b_tmp->getURL();
			}
			if (!$blogurl) {
				$blogurl = $CONF['IndexURL'];
				if ($CONF['URLMode'] != 'pathinfo'){
					if ($data['type'] == 'pageparser') {
						$blogurl .= 'index.php';
					} else {
						$blogurl  = $CONF['Self'];
					}
				}
			}
		}
		if ($CONF['URLMode'] == 'pathinfo'){
			if (substr($blogurl, -1) == '/') {
				$blogurl = substr($blogurl, 0, -1);
			}
		}
		$CONF['BlogURL']        = $blogurl;
		$CONF['ItemURL']        = $blogurl;
		$CONF['CategoryURL']    = $blogurl;
		$CONF['ArchiveURL']     = $blogurl;
		$CONF['ArchiveListURL'] = $blogurl;
		$CONF['SearchURL']      = $blogurl;
//		$CONF['MemberURL']      = $blogurl;
	}

	function event_PreItem($data)
	{
		global $CONF, $manager;

		if (getNucleusVersion() < '330') {
			$this->currentItem = &$data['item']; 
			$pattern = '/<%CustomURL\((.*)\)%>/';
			$data['item']->body = preg_replace_callback($pattern, array(&$this, 'URL_Callback'), $data['item']->body);
			if ($data['item']->more) {
				$data['item']->more = preg_replace_callback($pattern, array(&$this, 'URL_Callback'), $data['item']->more);
			}
		}

		$itemid   =  intval($data['item']->itemid);
		$itemblog =& $manager->getBlog(getBlogIDFromItemID($itemid));
		$blogurl  =  $itemblog->getURL();
		if (!$blogurl) {
			$b =& $manager->getBlog($CONF['DefaultBlog']);
			if (!($blogurl = $b->getURL())) {
				$blogurl = $CONF['IndexURL'];
				if ($CONF['URLMode'] != 'pathinfo'){
					if ($data['type'] == 'pageparser') {
						$blogurl .= 'index.php';
					} else {
						$blogurl  = $CONF['Self'];
					}
				}
			}
		}
		if ($CONF['URLMode'] == 'pathinfo'){
			if (substr($blogurl, -1) == '/') {
				$blogurl = substr($blogurl, 0, -1);
			}
		}
		$CONF['BlogURL']        = $blogurl;
		$CONF['ItemURL']        = $blogurl;
		$CONF['CategoryURL']    = $blogurl;
		$CONF['ArchiveURL']     = $blogurl;
		$CONF['ArchiveListURL'] = $blogurl;
//		$CONF['MemberURL']      = $blogurl;
	}

	function event_PostItem($data)
	{
		global $CONF, $manager, $blog;
		if (!$blog) {
			$b =& $manager->getBlog($CONF['DefaultBlog']);
		} else {
			$b =& $blog;
		}
		$blogurl = $b->getURL();
		if (!$blogurl) {
			if($blog) {
				$b_tmp   =& $manager->getBlog($CONF['DefaultBlog']);
				$blogurl =  $b_tmp->getURL();
			}
			if (!$blogurl) {
				$blogurl = $CONF['IndexURL'];
				if ($CONF['URLMode'] != 'pathinfo'){
					if ($data['type'] == 'pageparser') {
						$blogurl .= 'index.php';
					} else {
						$blogurl  = $CONF['Self'];
					}
				}
			}
		}
		if ($CONF['URLMode'] == 'pathinfo'){
			if (substr($blogurl, -1) == '/') {
				$blogurl = substr($blogurl, 0, -1);
			}
		}
		$CONF['BlogURL']        = $blogurl;
		$CONF['ItemURL']        = $blogurl;
		$CONF['CategoryURL']    = $blogurl;
		$CONF['ArchiveURL']     = $blogurl;
		$CONF['ArchiveListURL'] = $blogurl;
//		$CONF['MemberURL']      = $CONF['Self'];
	}
// merge NP_RightURL end

	function event_PostDeleteBlog ($data)
	{
		$query    = 'DELETE FROM %s WHERE obj_id = %d AND obj_param = "%s"';
		$pquery   = 'DELETE FROM %s WHERE obj_bid = %d AND obj_param= "%s"';
		$blogid   = intval($data['blogid']);
		sql_query(sprintf($query, _CUSTOMURL_TABLE, $blogid, 'blog'));
		sql_query(sprintf($pquery, _CUSTOMURL_TABLE, $blogid, 'item'));
		$cnmquery = 'SELECT catid FROM %s WHERE cblog = %d';
		$table    = sql_table('category');
		$cnm      = sql_query(sprintf($cnmquery, $table, $blogid));
		while ($c = sql_fetch_object($cnm)) {
			$catid = intval($c->catid);
			sql_query(sprintf($pquery, _CUSTOMURL_TABLE, $catid, 'subcategory'));
			sql_query(sprintf($query, _CUSTOMURL_TABLE, $catid, 'category'));
		}
	}

	function event_PostDeleteCategory ($data)
	{
		$query  = 'DELETE FROM %s WHERE obj_id = %d AND obj_param = "%s"';
		$squery = 'DELETE FROM %s WHERE obj_bid = %d AND obj_param = "%s"';
		$catid  = intval($data['catid']);
		sql_query(sprintf($query, _CUSTOMURL_TABLE, $catid, 'category'));
		sql_query(sprintf($squery, _CUSTOMURL_TABLE, $catid, 'subcategory'));
	}

	function event_PostDeleteItem ($data)
	{
		$query  = 'DELETE FROM %s WHERE obj_id = %d AND obj_param = "%s"';
		$itemid = intval($data['itemid']);
		sql_query(sprintf($query, _CUSTOMURL_TABLE, $itemid, 'item'));
	}

	function event_PostDeleteMember ($data)
	{
		$query    = 'DELETE FROM %s WHERE obj_id = %d AND obj_param = "%s"';
		$memberid = intval($data['member']->id);
		sql_query(sprintf($query, _CUSTOMURL_TABLE, $memberid, 'member'));
	}

	function event_PostAddBlog ($data)
	{
		$blog_id    = intval($data['blog']->blogid);
		$bshortname = $data['blog']->settings['bshortname'];
		$this->RegistPath($blog_id, $bshortname, 0, 'blog', $bshortname, TRUE);
		$this->setBlogOption($blog_id, 'customurl_bname', $bshortname);
	}

	function event_PostAddCategory ($data)
	{
		global $CONF;
		$cat_id = intval($data['catid']);
		if (!$data['blog']->blogid) {
			$query = 'SELECT cblog as result FROM %s WHERE catid = %d';
			$bid   = quickQuery(sprintf($query, sql_table('category'), $cat_id));
		} else {
			$bid = $data['blog']->blogid;
		}
		if (!$data['name']) {
			$query = 'SELECT cname as result FROM %s WHERE catid = %d';
			$name  = quickQuery(sprintf($query, sql_table('category'), $cat_id));
		} else {
			$name = $data['name'];
		}
		$bid     = intval($bid);
		$dfcat   = $this->getOption('customurl_dfcat');
		$catpsth = $dfcat . '_' . $cat_id;
		$this->RegistPath($cat_id, $catpsth, $bid, 'category', $name, TRUE);
		$this->setCategoryOption($cat_id, 'customurl_cname', $catpsth);
	}

	function event_PostAddItem ($data)
	{
		$item_id = intval($data['itemid']);
		$tpath   = requestVar('plug_custom_url_path');
		$tque    = 'SELECT itime as result FROM %s WHERE inumber = %d';
		$itime   = quickQuery(sprintf($tque, sql_table('item'), $item_id));
		list($y, $m, $d, $trush) = sscanf($itime, '%d-%d-%d %s');
		$param['year']           = sprintf('%04d', $y);
		$param['month']          = sprintf('%02d', $m);
		$param['day']            = sprintf('%02d', $d);
		$ipath   = TEMPLATE::fill($tpath, $param);
		$query   = 'SELECT ititle as result FROM %s WHERE inumber = %d';
		$iname   = quickQuery(sprintf($query, sql_table('item'), $item_id));
		$blog_id = intval(getBlogIDFromItemID($item_id));
		$this->RegistPath($item_id, $ipath, $blog_id, 'item', $iname, TRUE);
		if ($this->pluginCheck('TrackBack')) {
				$this->convertLocalTrackbackURL($data);
		}
	}

	function event_PostRegister ($data)
	{
		$memberID = intval($data['member']->id);
		$dispName = $data['member']->displayname;
		$this->RegistPath($memberID, $dispName, 0, 'member', $dispName, TRUE);
		$this->setMemberOption($memberID, 'customurl_mname', $dispName);
	}

	function event_AddItemFormExtras(&$data)
	{
		$this->createItemForm();
	}

	function event_EditItemFormExtras(&$data)
	{
		$this->createItemForm(intval($data['itemid']));
	}

	function event_PostUpdateItem($data)
	{
		$tpath   = requestVar('plug_custom_url_path');
		$item_id = intval($data['itemid']);
		$tque    = 'SELECT itime as result FROM %s WHERE inumber = %d';
		$itime   = quickQuery(sprintf($tque ,sql_table('item'), $item_id));
		list($y, $m, $d, $trush) = sscanf($itime, '%d-%d-%d %s');
		$param['year']           = sprintf('%04d', $y);
		$param['month']          = sprintf('%02d', $m);
		$param['day']            = sprintf('%02d', $d);
		$ipath   = TEMPLATE::fill($tpath, $param);
		$query   = 'SELECT ititle as result FROM %s WHERE inumber = %d';
		$iname   = quickQuery(sprintf($query, sql_table('item'), $item_id));
		$blog_id = intval(getBlogIDFromItemID($item_id));
		$this->RegistPath($item_id, $ipath, $blog_id, 'item', $iname);
		if ($this->pluginCheck('TrackBack')) {
				$this->convertLocalTrackbackURL($data);
		}
	}

	function createItemForm($item_id = 0)
	{
		global $CONF;
		if ($item_id) {
			$query   = 'SELECT obj_name as result'
				     . ' FROM         %s'
				     . ' WHERE obj_param = "item"'
				     . ' AND      obj_id = %d';
			$item_id = intval($item_id);
			$res     = quickQuery(sprintf($query, _CUSTOMURL_TABLE, $item_id));
			$ipath   = substr($res, 0, strlen($res)-5);
		} else {
			$ipath   = $this->getOption('customurl_dfitem');
		}
		echo <<<OUTPUT
<h3>Custom URL</h3>
<p>
<label for="plug_custom_url">Custom Path:</label>
<input id="plug_custom_url" name="plug_custom_url_path" value="{$ipath}" />
</p>
OUTPUT;
	}

	function event_PrePluginOptionsUpdate($data)
	{
		$blog_option = ($data['optionname'] == 'customurl_bname');
		$cate_option = ($data['optionname'] == 'customurl_cname');
		$memb_option = ($data['optionname'] == 'customurl_mname');
		$arch_option = ($data['optionname'] == 'customurl_archive');
		$arvs_option = ($data['optionname'] == 'customurl_archives');
		$memd_option = ($data['optionname'] == 'customurl_member');
		$contextid   = intval($data['contextid']);
		$context     = $data['context'];
		if ($blog_option || $cate_option || $memb_option) {
			if ($context == 'member' ) {
				$blogid = 0;
				$query  = 'SELECT mname as result FROM %s WHERE mnumber = %d';
				$table  = sql_table('member');
				$name   = quickQuery(sprintf($query, $table, $contextid));
			} elseif (context == 'category') {
				$blogid = getBlogIDFromCatID($contextid);
				$query  = 'SELECT cname as result FROM %s WHERE catid = %d';
				$table  = sql_table('category');
				$name   = quickQuery(sprintf($query, $table, $contextid));
			} else {
				$blogid = 0;
				$query  = 'SELECT bname as result FROM %s WHERE bnumber = %d';
				$table  = sql_table('blog');
				$name   = quickQuery(sprintf($query, $table, $contextid));
			}
			$blogid = intval($blogid);
			$msg = $this->RegistPath($contextid, $data['value'], $blogid, $context, $name);
			if ($msg) {
				$this->error($msg);
				exit;
			}
		} elseif ($arch_option || $arvs_option || $memd_option) {
			if (!preg_match('/^[-_a-zA-Z0-9]+$/', $data['value'])) {
				$name = substr($data['optionname'], 8);
				$msg  = array (1, _INVALID_ERROR, $name, _INVALID_MSG);
				$this->error($msg);
				exit;
			} else {
				return;
			}
		}
		return;
	}

	function event_PostMoveItem($data)
	{
		$query      = 'UPDATE %s SET obj_bid = %d'
					. ' WHERE obj_param = "%s" AND obj_id = %d';
		$destblogid = intval($data['destblogid']);
		$item_id    = intval($data['itemid']);
		sql_query(sprintf($query, _CUSTOMURL_TABLE, $destblogid, 'item', $item_id));
	}

	function event_PostMoveCategory($data)
	{
		$query      = 'UPDATE %s SET obj_bid = %d'
					. ' WHERE obj_param = "%s" AND obj_id = %d';
		$destblogid = intval($data['destblog']->blogid);
		$cat_id     = intval($data['catid']);
		sql_query(sprintf($query, _CUSTOMURL_TABLE, $destblogid, 'category', $cat_id));
		$query      = 'SELECT inumber FROM %s WHERE icat = %d';
		$query      = sprintf($query, sql_table('item'), $cat_id);
		$items      = sql_query($query);
		while ($oItem = sql_fetch_object($items)) {
			$odata = array(
						   'destblogid' => $destblogid,
						   'itemid'     => $oItem->inumber
						  );
			$this->event_PostMoveItem($odata);
		}
	}

	function RegistPath($objID, $path, $bid, $oParam, $name, $new = FALSE )
	{
		global $CONF;
		switch($oParam) {
			case 'item':
			case 'member':
				if (preg_match('/.html$/', $path))
					$path = substr($path, 0, -5);
			break;
			case 'blog':
			case 'category':
			case 'subcategory':
				break;
			default :
				return;
				break;
		}
		$bid   = intval($bid);
		$objID = intval($objID);
		$name  = rawurlencode($name);

		if ($new && $oParam == 'item') {
			$tque  = 'SELECT itime as result FROM %s WHERE inumber = %d';
			$itime = quickQuery(sprintf($tque ,sql_table('item'), $objID));
			list($y, $m, $d, $trush) = sscanf($itime, '%d-%d-%d %s');
			$param['year']           = sprintf('%04d', $y);
			$param['month']          = sprintf('%02d', $m);
			$param['day']            = sprintf('%02d', $d);
			$ikey = TEMPLATE::fill($template, $param); 
				if ($path == $ikey) {
					$path = $ikey . '_' . $objID;
				}
		} elseif (!$new && strlen($path) == 0) {
			$del_que = 'DELETE FROM %s WHERE obj_id = %d AND obj_param = "%s"';
			sql_query(sprintf($del_que, _CUSTOMURL_TABLE, $objID, $oParam));
			$msg = array (0, _DELETE_PATH, $name, _DELETE_MSG);
			return $msg;
			exit;
		}

		$dotslash = array ('.', '/');
		$path     = str_replace ($dotslash, '_', $path);
		if (!preg_match('/^[-_a-zA-Z0-9]+$/', $path)) {
			$msg = array (1, _INVALID_ERROR, $name, _INVALID_MSG);
			return $msg;
			exit;
		}

		$tempPath = $path;
		if ($oParam == 'item' || $oParam == 'member') $tempPath .= '.html';
		$conf_que = 'SELECT obj_id FROM %s'
				  . ' WHERE obj_name = "%s"'
				  . ' AND    obj_bid = %d'
				  . ' AND  obj_param = "%s"'
				  . ' AND    obj_id != %d';
		$res = sql_query(sprintf($conf_que, _CUSTOMURL_TABLE, $tempPath, $bid, $oParam, $objID));
		if ($res && sql_num_rows($res)) {
			$msg   = array (0, _CONFLICT_ERROR, $name, _CONFLICT_MSG);
			$path .= '_'.$objID;
		}
		if ($oParam == 'category' && !$msg) {
			$conf_cat = 'SELECT obj_id FROM %s WHERE obj_name = "%s"'
					  . ' AND obj_param = "blog"';
			$res = sql_query(sprintf($conf_cat, _CUSTOMURL_TABLE, $tempPath));
			if ($res && sql_num_rows($res)) {
				$msg   = array (0, _CONFLICT_ERROR, $name, _CONFLICT_MSG);
				$path .= '_'.$objID;
			}
		}
		if ($oParam == 'blog' && !$msg) {
			$conf_blg = 'SELECT obj_id FROM %s WHERE obj_name = "%s"'
					  . ' AND obj_param = "category"';
			$res = sql_query(sprintf($conf_blg, _CUSTOMURL_TABLE, $tempPath));
			if ($res && sql_num_rows($res)) {
				$msg   = array (0, _CONFLICT_ERROR, $name, _CONFLICT_MSG);
				$path .= '_'.$objID;
			}
		}

		$newPath = $path;
		if ($oParam == 'item' || $oParam == 'member') $newPath .= '.html';
		$query = 'SELECT * FROM %s WHERE obj_id = %d AND obj_param = "%s"';
		$res = sql_query(sprintf($query, _CUSTOMURL_TABLE, $objID, $oParam));
		$row = sql_fetch_object($res);
		$pathID = $row->id;
		if ($pathID) {
			$query = 'UPDATE %s SET obj_name = "%s" WHERE id = %d';
			sql_query(sprintf($query, _CUSTOMURL_TABLE, $newPath, $pathID));
		} else {
			$query = 'INSERT INTO %s (obj_param, obj_name, obj_id, obj_bid)'
				   . ' VALUES ("%s", "%s", %d, %d)';
			sql_query(sprintf($query, _CUSTOMURL_TABLE, $oParam, $newPath, $objID, $bid));
		}
		switch($oParam) {
			case 'blog':
				$this->setBlogOption($objID, 'customurl_bname', $path);
			break;
			case 'category':
				$this->setCategoryOption($objID, 'customurl_cname', $path);
			break;
			case 'member':
				$this->setMemberOption($objID, 'customurl_mname', $path);
			break;
			default :
			break;
		}
		return $msg;
	}

	function error($msg = '')
	{
		global $admin;

		$admin->pagehead();
		echo $msg[1].' : '.$msg[2].'<br />';
		echo $msg[3].'<br />';
		echo '<a href="index.php" onclick="history.back()">'._BACK.'</a>';
		$admin->pagefoot();
		return;
	}

	function quote_smart($value)
	{
		if (get_magic_quotes_gpc()) $value = stripslashes($value);
		if (!is_numeric($value)) {
			$value = sql_real_escape_string($value);
		} elseif (is_numeric($value)) {
			$value = intval($value);
		}
		return $value;
	}

	function convertLocalTrackbackURL($data)
	{
		global $manager, $CONF;
		$ping_urls_count = 0;
		$ping_urls       = array();
		$localflag       = array();
		$ping_url        = requestVar('trackback_ping_url');
		if (trim($ping_url)) {
			$ping_urlsTemp = array();
			$ping_urlsTemp = preg_split("/[\s,]+/", trim($ping_url));
			for ($i = 0; $i < count($ping_urlsTemp); $i++) {
				$ping_urls[] = trim($ping_urlsTemp[$i]);
				$ping_urls_count++;
			}
		}
		$tb_url_amount = intRequestVar('tb_url_amount');
		for ($i=0; $i < $tb_url_amount; $i++) {
			$tb_temp_url = requestVar('tb_url_' . $i);
			if ($tb_temp_url) {
				$ping_urls[$ping_urls_count] = $tb_temp_url;
				$localflag[$ping_urls_count] = (requestVar('tb_url_' . $i . '_local') == 'on') ? 1 : 0;
				$ping_urls_count++;
			}
		}
		if ($ping_urls_count <= 0) {
			return;
		}
		$blog_id = getBlogidFromItemID(intval($data['itemid']));
		for ($i=0; $i < count($ping_urls); $i++) {
			if($localflag[$i]) {
				$tmp_url         = parse_url($ping_urls[$i]);
				$tmp_url['path'] = trim($tmp_url['path'], '/');
				$path_arr        = explode("/", $tmp_url['path']);
				$tail            = end($path_arr);
				$linkObj         = array (
										  'linkparam' => 'item',
										  'bid'       => $blog_id,
										 );
				if (substr($tail, -10) == '.trackback') {
					$pathName = substr($tail, 0, -10);
					if (substr($pathName, -5) == '.html') {
						$linkObj['name'] = $pathName;
					} else {
						$linkObj['name'] = $pathName . '.html';
					}
				} else {
					$linkObj['name'] = $tail;
				}
				$item_id = $this->getRequestPathInfo($linkObj);
				if ($item_id) {
					$ping_urls[$i] = $CONF['ActionURL']
								   . '?action=plugin&name=TrackBack&tb_id=' . $item_id;
				}
			}
		}
		$_REQUEST['trackback_ping_url'] = implode ("\n", $ping_urls);
	}
	
	function hsc($string, $flags=ENT_QUOTES, $encoding='')
	{
		if($encoding==='')
		{
			if(defined('_CHARSET')) $encoding = _CHARSET;
			else                    $encoding = 'utf8';
		}
		if(version_compare(PHP_VERSION, '5.2.3', '>='))
			return htmlspecialchars($string, $flags, $encoding, false);
		else
		{
			if(function_exists('htmlspecialchars_decode'))
				$string = htmlspecialchars_decode($string, $flags);
			else
				$string = strtr($string, array_flip(get_html_translation_table(HTML_SPECIALCHARS)));
			
			return htmlspecialchars($string, $flags, $encoding);
		}
	}
}

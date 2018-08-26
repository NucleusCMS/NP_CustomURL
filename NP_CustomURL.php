<?php

global $CONF;
$CONF['Self']='';

class NP_CustomURL extends NucleusPlugin
{
	public function getMinNucleusVersion() { return '372';}
	public function getName()              { return 'Customized URL';}
	public function getAuthor()            { return 'shizuki + nekonosippo + Cacher + Reine + yamamoto';}
	public function getURL()               { return 'http://japan.nucleuscms.org/wiki/plugins:customurl';}
	public function getVersion()           { return '0.4.1';}
	public function getDescription()       { return _DESCRIPTION;}
	public function hasAdminArea()         { return 1;}
	public function getTableList()         { return array(parseQuery('[@prefix@]plug_customurl'));}
	public function supportsFeature($what)
	{
		switch ($what) {
			case 'SqlTablePrefix':
			case 'HelpPage':
			case 'SqlApi':
			case 'SqlApi_sqlite':
				return 1;
			default:
				return 0;
		}
	}


	public function event_QuickMenu(&$data)
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

	public function getEventList()
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
					  'PrePluginOptionsEdit',
					  'PostUpdatePlugin',
					 );
	}

	public function install()
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
		$ph = array('pid'=>(int)$this->getID());
		$myorder   = (int)parseQuickQuery('SELECT porder as result FROM [@prefix@]plugin WHERE pid=[@pid@]', $ph);
		$minorder  = (int)parseQuickQuery('SELECT porder as result FROM [@prefix@]plugin ORDER BY porder ASC LIMIT 1');
		if ($myorder != $minorder || $myorder >1)
		{
			if ($minorder <= 1)
			{
				$inc = (($minorder < 0) ? abs($minorder) : 1);
				$ph = array('add'=>$inc, 'porder'=>$myorder+$inc-1);
				sql_query(parseQuery('UPDATE [@prefix@]plugin SET porder=porder+[@add@] WHERE porder < [@porder@]'));
			}
			sql_query(parseQuery('UPDATE [@prefix@]plugin SET porder=1 WHERE pid=[@pid@]', $ph));
		}

//create plugin's options and set default value
		$this->createOption('customurl_archive',   _OP_ARCHIVE_DIR_NAME,  'text', $CONF['ArchiveKey']);
		$this->createOption('customurl_archives',  _OP_ARCHIVES_DIR_NAME, 'text', $CONF['ArchivesKey']);
		$this->createOption('customurl_member',    _OP_MEMBER_DIR_NAME,   'text', $CONF['MemberKey']);
		$this->createOption('customurl_dfitem',    _OP_DEF_ITEM_KEY,      'text', $CONF['ItemKey']);
		$this->createOption('customurl_dfcat',     _OP_DEF_CAT_KEY,       'text', $CONF['CategoryKey']);
		$this->createOption('customurl_dfscat',    _OP_DEF_SCAT_KEY,      'text', 'subcategory');
		$this->createOption('customurl_incbname',  _OP_INCLUDE_CBNAME,    'yesno', 'no');
		$this->createOption('customurl_tabledel',  _OP_TABLE_DELETE,      'yesno', 'no');
		$this->createOption('customurl_quicklink', _OP_QUICK_LINK,        'yesno', 'yes');
		$this->createOption('customurl_notfound',  _OP_NOT_FOUND,         'select', '404', '404 Not Found|404|303 See Other|303');
		$this->createOption('customurl_allow_edit_member_uri', _OP_ALLOW_EDIT_MEMBER_URI, 'yesno', 'no');
		
		$this->createBlogOption('use_customurl',   _OP_USE_CURL,   'yesno', 'yes');
		$this->createBlogOption('redirect_normal', _OP_RED_NORM,   'yesno', 'yes');
		$this->createBlogOption('redirect_search', _OP_RED_SEARCH, 'yesno', 'yes');
		$this->createBlogOption('customurl_bname', _OP_BLOG_PATH,  'text');
		
//		$this->createItemOption('customurl_iname', _OP_ITEM_PATH, 'text',  $CONF['ItemKey']);
		
		$this->createMemberOption('customurl_mname', _OP_MEMBER_PATH, 'text');
		
		$this->createCategoryOption('customurl_cname', _OP_CATEGORY_PATH, 'text');

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
		$sql = 'CREATE TABLE IF NOT EXISTS [@prefix@]plug_customurl ('
			 . ' `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, '
			 . ' `obj_param` VARCHAR(15) NOT NULL, '
			 . ' `obj_name` VARCHAR(128) NOT NULL, '
			 . ' `obj_id` INT(11) NOT NULL, '
			 . ' `obj_bid` INT(11) NOT NULL,'
			 . ' INDEX (`obj_name`)'
			 . ' )';
		
		global $MYSQL_HANDLER;
		
		if ((isset($this->is_db_sqlite) && $this->is_db_sqlite) || in_array('sqlite', $MYSQL_HANDLER))
		{
			$sql = str_replace('INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY', 'INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL', $sql);
			$sql = preg_replace('#,\s+INDEX .+$#ims', ');', $sql);
			if (sql_query($sql) === false) {
				addToLog (ERROR, 'NP_CustomURL : failed to create the table [@prefix@]plug_customurl');
			}
			$sql = 'CREATE INDEX IF NOT EXISTS `[@prefix@]plug_customurl_idx_obj_name` on `[@prefix@]plug_customurl` (`obj_name`);';
		}
		sql_query(parseQuery($sql));

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

	private function _createNewPath($type, $table_name, $field_name1, $field_name2)
	{
		$ph['type']        = $type;
		$ph['table_name']  = $table_name;
		$ph['field_name1'] = $field_name1;
		$ph['field_name2'] = $field_name2;
		
		$query = "CREATE TABLE [@prefix@]plug_customurl_temp SELECT obj_id, obj_param FROM [@prefix@]plug_customurl WHERE obj_param='[@type@]'";
		sql_query(parseQuery($query, $ph));
		
		$query = 'SELECT [@field_name1@], [@field_name1@] FROM [@prefix@][@table_name@] as ttb LEFT JOIN [@prefix@]plug_customurl_temp as tcu ON ttb.[@field_name1@]=tcu.obj_id WHERE tcu.obj_id is null';
		$rs = sql_query(parseQuery($query, $ph));
		while ($row = sql_fetch_array($rs)) {
			switch ($type) {
				case 'blog':
					//set access by BlogshortName/
					$ph['name'] = $row[$field_name2];
					$ph['bid'] = 0;
					break;
				case 'item':
					//set access by (itemkey_template)_itemid.html
					$ph['id'] = (int)$row[$field_name1];
					$query = 'SELECT itime as result FROM [@prefix@]item WHERE inumber=[@id@]';
					$itime = parseQuickQuery($query, $ph);
					list($y, $m, $d, $null) = sscanf($itime, '%d-%d-%d %s');
					$param['year']  = sprintf('%04d', $y);
					$param['month'] = sprintf('%02d', $m);
					$param['day']   = sprintf('%02d', $d);
					$ikey    = TEMPLATE::fill($this->getOption('customurl_dfitem'), $param);
					$ph['name'] = $ikey . '_' . $row[$field_name1] . '.html';
					$ph['bid'] = (int)$row[$field_name2];
					break;
				case 'category':
					//set access by (categorykey_template)_categoryid/
					$ph['name'] = $this->getOption('customurl_dfcat') . '_' . $row[$field_name1];
					$ph['bid'] = (int)$row[$field_name2];
					break;
				case 'member':
					//set access by loginName.html
					$ph['name'] = $row[$field_name2] . '.html';
					$ph['bid'] = 0;
					break;
				case 'subcategory':
					//set access by (subcategorykey_template)_subcategoryid/
					$ph['name'] = $this->getOption('customurl_dfscat') . '_' . $row[$field_name1];
					$ph['bid'] = $row[$field_name2];
					break;
			}
			$query = "INSERT INTO [@prefix@]plug_customurl (obj_param, obj_id, obj_name, obj_bid) VALUES ('[@type@]', [@id@], '[@name@]', [@bid@])";
			$ph['id']   = (int)$row[$field_name1];
			sql_query(parseQuery($query, $ph));
		}
		$query = "SELECT obj_id, obj_name FROM [@prefix@]plug_customurl WHERE obj_param='[@type@]'";
		$rs  = sql_query(parseQuery($query));
		while ($row = sql_fetch_array($rs)) {
			$name = $row['obj_name'];
			$id   = (int)$row['obj_id'];
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
			}
		}
		
		sql_query(parseQuery('DROP TABLE IF EXISTS [@prefix@]plug_customurl_temp'));
	}

	public function init()
	{
		$language = str_replace(array('\\','/'), '', getLanguageName());
		$plugin_path = $this->getDirectory();
		if (!is_file("{$plugin_path}language/{$language}.php"))
			$language = 'english';
		include_once("{$plugin_path}language/{$language}.php");
	}

	private function pluginCheck($pluginName)
	{
		global $manager;
		if (!$manager->pluginInstalled('NP_' . $pluginName)) {
			return;
		}
		$plugin =& $manager->getPlugin('NP_' . $pluginName);
		return $plugin;
	}

	public function unInstall()
	{
		if ($this->getOption('customurl_tabledel') == 'yes') {
			sql_query(parseQuery('DROP TABLE [@prefix@]plug_customurl'));
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
		$this->deleteOption('customurl_allow_edit_member_uri');
		$this->deleteBlogOption('use_customurl');
		$this->deleteBlogOption('redirect_normal');
		$this->deleteBlogOption('redirect_search');
		$this->deleteBlogOption('customurl_bname');
//		$this->deleteItemOption('customurl_iname');
		$this->deleteMemberOption('customurl_mname');
		$this->deleteCategoryOption('customurl_cname');
	}

	public function event_ParseURL($data)
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
		$NP_MultipleCategories = $this->pluginCheck('MultipleCategories');
		if ($NP_MultipleCategories) {
			$param = array();
			$NP_MultipleCategories->event_PreSkinParse($param);
			
			global $subcatid;
			if (method_exists($NP_MultipleCategories, 'getRequestName')) {
				$subrequest = $NP_MultipleCategories->getRequestName();
			} else {
				$subrequest = 'subcatid';
			}
		}
		
		// initialize and sanitize '$blogid'
		if (!$blogid) {
			if ( getVar('blogid') ) {
				if ( preg_match('@^[1-9][0-9]*$@',getVar('blogid')) ) {
					$blogid = (int)getVar('blogid');
				} else {
					$blogid = (int)getBlogIDFromName(getVar('blogid'));
				}
			} elseif ($curl_blogid) {
				$blogid = (int)$curl_blogid;
			} elseif ($itemid>0) {//2008-09-19 Cacher
				$blogid = getBlogIDFromItemID($itemid);
			} else {
				$blogid = $CONF['DefaultBlog'];
			}//2008-09-19 Cacher
		} else {
			if (is_numeric($blogid)) {
				$blogid = (int)$blogid;
			} else {
				$blogid = (int)getBlogIDFromName($blogid);
			}
		}

		if (!$info) {
			if (serverVar('PATH_INFO')) {
				$info = serverVar('PATH_INFO');
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
		$v_path = explode('/', $info);
		foreach($v_path as $key => $value) {
			$value = urlencode($value);
			$value = preg_replace('|[^a-zA-Z0-9-~+_.?#=&;,/:@%]|i', '', $value);
			$v_path[$key] = $value;
		}
		
		$_SERVER['PATH_INFO'] = join('/', $v_path);

// Admin area check
		$tmpURL       = sprintf('http://%s%s', serverVar('HTTP_HOST'), serverVar('SCRIPT_NAME'));
		$uri          = str_replace('/', '\/', $tmpURL);
		$plug_url     = str_replace('/', '\/', $CONF['PluginURL']);
		$u_plugAction = (getVar('action') == 'plugin' && getVar('name'));
		$UsingPlugAdmin = false;
		if (strpos($uri, $plug_url) === 0 || $u_plugAction) {
			$UsingPlugAdmin = true;
		}

// get real blogid
		$blink = false;
		if (empty($info)) {
			$bLink = true;
		}
		$linkObj = array (
						  'bid'       => 0,
						  'name'      => reset($v_path),
						  'linkparam' => 'blog'
						 );
		$blog_id = $this->getRequestPathInfo($linkObj);
		if ($blog_id) {
			$blogid = $blog_id;
			array_shift($v_path);
			$bLink  = true;
		}
		
		if ($useCustomURL[$blogid] == 'no') {
			return;
		}

// redirect to other URL style
		$useCustomURLyes = ($useCustomURL[$blogid] == 'yes');
		if ($useCustomURLyes && !$UsingPlugAdmin && !$CONF['UsingAdminArea']) {
// Search query redirection
// 301 permanent ? or 302 temporary ?
			$queryURL = (strpos(serverVar('REQUEST_URI'), 'query=') !== false);
			$search_q = (getVar('query') || $queryURL);
			$redirectSearch = ($this->getBlogOption($blogid, 'redirect_search') == 'yes');
			if ($redirectSearch && $search_q) {
				$que_str     = hsc(getVar('query'));
				$que_str = str_replace('/', md5('/'), $que_str);
				$que_str = str_replace("'", md5("'"), $que_str);
				$que_str = str_replace('&', md5('&'), $que_str);
				$que_str     = urlencode($que_str);
				$search_path = 'search/' . $que_str;
				$b_url       = createBlogidLink($blogid);
				$redurl      = sprintf('%s%s', $b_url, $search_path);
				redirect($redurl); // 302 Moved temporary
				exit;
			} elseif (!$redirectSearch ) {
				$isExtra = true;
			} else {
				$isExtra = false;
			}
			
			// redirection nomal URL to FancyURL
			$temp_req = explode('?', serverVar('REQUEST_URI'));
			$reqPath  = trim(end($temp_req), '/');
			$isFeed   = in_array($reqPath, array('xml-rss1.php','atom.php','xml-rss2.php'));
			
			if ($this->getBlogOption($blogid, 'redirect_normal') == 'yes') {
				if (serverVar('QUERY_STRING') && !$isFeed && !$isExtra) {
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
									$p_arr[] = $CONF['BlogKey'] . '/' . intGetVar('blogid');
									unset($temp[$key]);
									break;
								case 'catid';
									$p_arr[] = $CONF['CategoryKey'] . '/' . intGetVar('catid');
									unset($temp[$key]);
									break;
								case 'itemid';
									$p_arr[] = $CONF['ItemKey'] . '/' . intGetVar('itemid');
									unset($temp[$key]);
									break;
								case 'memberid';
									$p_arr[] = $CONF['MemberKey'] . '/' . intGetVar('memberid');
									unset($temp[$key]);
									break;
								case 'archivelist';
									$p_arr[] = $CONF['ArchivesKey'] . '/' . $blogid;
									unset($temp[$key]);
									break;
								case 'archive';
									$p_arr[] = $CONF['ArchiveKey'] . '/' . $blogid . '/' . getVar('archive');
									unset($temp[$key]);
									break;
								default:
									if(isset($subrequest) && $subrequest)
									{
										$p_arr[] = $subrequest . '/' . intGetVar($subrequest);
										unset($temp[$key]);
									}
							}
						}
						if (!empty($temp)) {
							$queryTemp = '/?' . join('&', $temp);
						}
						if (reset($p_arr)) {
							$b_url    = createBlogidLink($blogid);
							$red_path = '/' . join('/', $p_arr);
							if (substr($b_url, -1) == '/') {
								$b_url = rtrim($b_url, '/');
							}
							$redurl = sprintf('%s%s', $b_url, $red_path) . $queryTemp;
							header('Location: ' . $redurl, true, 301);
							exit;
						}
					}
				} elseif ($isFeed) {
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
					}
					// HTTP status 301 "Moved Permanentry"
					header('Location: ' . $b_url . $feed_code, true, 301);
					exit;
				}
			}
		}
// decode path_info

// decode TrackBack URL shorten ver.
		$tail = end($v_path);
		if (substr($tail, -10, 10) == '.trackback') {
			$v_pathName = substr($tail, 0, -10);
			if (preg_match('@^[1-9][0-9]*$@',$v_pathName) || substr($v_pathName, -5) == '.html') {
				$this->_trackback($blogid, $v_pathName);
			} else {
				$this->_trackback($blogid, $v_pathName . '.html');
			}
			return;
		}

// decode other type URL
		$isCategory = false;
		$isItem = false;
		$isExtra = false;

		$i = 1;
		$sc = null;
		foreach($v_path as $pathName) {
			if(!isset($subrequest)) $subrequest = null;
			switch ($pathName) {
				case $CONF['BlogKey']:
					// decode FancyURLs and redirection to Customized URL
					// for blogsgetAllBlogOptions($name)
					if (isset($v_path[$i]) && preg_match('@^[1-9][0-9]*$@',$v_path[$i])) {
						if ($useCustomURL[(int)$v_path[$i]] != 'yes') {
							$blogid = (int)$v_path[$i];
						} else {
							$this->redirectFancyURLtoCustomURL(createBlogidLink((int)$v_path[$i]));
							exit;
						}
					}
					break;
				case $CONF['ItemKey']:
					// for items
					if (isset($v_path[$i]) && preg_match('@^[1-9][0-9]*$@',$v_path[$i])) {
						if ($useCustomURL[$blogid] != 'yes') {
							$itemid = (int)$v_path[$i];
							$isItem  = true;
						} else {
							$this->redirectFancyURLtoCustomURL(createItemLink((int)$v_path[$i]));
							exit;
						}
					}
					break;
				// for categories
				case $CONF['CategoryKey']:
				case 'catid':
					if (isset($v_path[$i]) && preg_match('@^[1-9][0-9]*$@',$v_path[$i])) {
						if ($useCustomURL[$blogid] != 'yes') {
							$catid  = (int)$v_path[$i];
							$isCategory  = true;
						} else {
							$this->redirectFancyURLtoCustomURL(createCategoryLink((int)$v_path[$i]));
							exit;
						}
					}
					break;
				// for subcategories
				case $subrequest:
					$c = $i - 2;
					$subCat = (isset($v_path[$i]) && preg_match('@^[1-9][0-9]*$@',$v_path[$i]));
					if ($NP_MultipleCategories && $subCat && $i >= 3 && preg_match('@^[1-9][0-9]*$@',$v_path[$c])) {
						if ($useCustomURL[$blogid] != 'yes') {
							$subcatid  = (int)$v_path[$i];
							$catid     = (int)$v_path[$c];
							$isCategory     = true;
						} else {
							$subcat_id = (int)$v_path[$i];
							$catid     = (int)$v_path[$c];
							$linkParam = array($subrequest => $subcat_id);
							$this->redirectFancyURLtoCustomURL(createCategoryLink($catid, $linkParam));
							exit;
						}
					}
					break;
				// for archives
				case $CONF['ArchivesKey']:
				case $this->getOption('customurl_archives'):
				// FancyURL
					if (isset($v_path[$i]) && preg_match('@^[1-9][0-9]*$@',$v_path[$i])) {
						if ($useCustomURL[(int)$v_path[$i]] != 'yes') {
							$archivelist = (int)$v_path[$i];
							$blogid      = $archivelist;
							$isExtra     = true;
						} else {
							$this->redirectFancyURLtoCustomURL(createArchiveListLink((int)$v_path[$i]));
							exit;
						}
				// Customized URL
					} elseif (isset($v_path[$i]) && strpos($v_path[$i], 'page') === false) {
						$archivelist = $blogid;
						$this->redirectFancyURLtoCustomURL(createArchiveListLink($archivelist));
						exit;
					} else {
						$archivelist = $blogid;
						$isExtra     = true;
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
							$adarc = $amarc = $ayarc = null;
						}
						$arc   = (!$darc && !$marc && !$yarc);
						$aarc  = ($adarc || $amarc || $ayarc);
						$carc  = ($darc || $marc || $yarc);
						// FancyURL
						if (preg_match('@^[1-9][0-9]*$@',$v_path[$i]) && $arc && isset($v_path[$ar]) && $aarc) {
								sscanf($v_path[$ar], '%d-%d-%d', $y, $m, $d);
							if (!empty($d)) {
								$archive = sprintf('%04d-%02d-%02d', $y, $m, $d);
							} elseif (!empty($m)) {
								$archive = sprintf('%04d-%02d',      $y, $m);
							} else {
								$archive = sprintf('%04d',           $y);
							}
							if ($useCustomURL[(int)$v_path[$i]] != 'yes') {
								$blogid  = (int)$v_path[$i];
								$isExtra = true;
							} else {
								$blogid = (int)$v_path[$i];
								$this->redirectFancyURLtoCustomURL(createArchiveLink($blogid, $archive));
								exit;
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
							$isExtra = true;
						} else {
							$this->redirectFancyURLtoCustomURL(createArchiveListLink($blogid));
							exit;
						}
					} else {
						$this->redirectFancyURLtoCustomURL(createArchiveListLink($blogid));
						exit;
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
						$memberid   = (int)$member_id;
						$isExtra    = true;
				// FancyURL
					} elseif (isset($v_path[$i]) && preg_match('@^[1-9][0-9]*$@',$v_path[$i])) {
						if ($useCustomURL[$blogid] != 'yes') {
							$memberid = (int)$v_path[$i];
							$isExtra  = true;
						} else {
							$this->redirectFancyURLtoCustomURL(createMemberLink((int)$v_path[$i]));
							exit;
						}
					} else {
						$this->redirectFancyURLtoCustomURL(createBlogidLink($blogid));
						exit;
					}
					break;
				// for tag
				// for pageswitch
				case 'tag':
					if ($this->pluginCheck(TagEX)) $isExtra = true;
					break;//2008-07-28 http://japan.nucleuscms.org/bb/viewtopic.php?p=23175#23175
				case 'page':
						$isExtra = true;
					break;
				// for ExtraSkinJP
				case 'extra':
					if ($manager->pluginInstalled('NP_ExtraSkinJP')) {
						$this->goNP_ExtraSkinJP();
						exit;
					}
					break;
				// for search query
				case 'search':
					$redirectSearch = ($this->getBlogOption($blogid, 'redirect_search') == 'yes');
					if ($redirectSearch) {
						$que_str = urldecode($v_path[$i]);
						$que_str = str_ireplace(md5('/'), '/', $que_str);
						$que_str = str_ireplace(md5("'"), "'", $que_str);
						$que_str = str_ireplace(md5('&'), '&', $que_str);
						$que_str       = htmlspecialchars_decode($que_str);
						$_GET['query'] = $que_str;
						$query         = $que_str;
						$isExtra       = true;
					}
					break;
				// for tDiarySkin
				case 'tdiarydate':
				case 'categorylist':
				case 'monthlimit':
					if ($this->pluginCheck('tDiarySkin') && isset($v_path[$i])) {
						$_GET[$pathName] = $v_path[$i];
						$isExtra         = true;
					}
					break;
				case 'special':
				case $CONF['SpecialskinKey']:
					if (isset($v_path[$i]) && is_string($v_path[$i])) {
						global $special;
						$special = $v_path[$i];
						$isExtra = true;
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
					$comp   = false;
					$isItem = (substr($pathName, -5) == '.html');
					
					// category ?
					if (!$isCategory && !$isItem && !$isExtra) {
						//2007-10-06 http://japan.nucleuscms.org/bb/viewtopic.php?p=20641#20641
						$linkObj['linkparam'] = 'category';
						$catid               = $this->getRequestPathInfo($linkObj);
						if (!empty($catid)) {
							$catid = (int)$catid;
							$isCategory = true;
							$comp  = true;
						}
					}
					// subcategory ?
					if (!$comp && $isCategory && !$isItem && $NP_MultipleCategories && !$isExtra) {//2007-10-06 http://japan.nucleuscms.org/bb/viewtopic.php?p=20641#20641
						$linkObj['linkparam'] = 'subcategory';
						$linkObj['bid']       = $catid;
						$subcat_id            = $this->getRequestPathInfo($linkObj);
						if (!empty($subcat_id)) {
							$_REQUEST[$subrequest] = (int)$subcat_id;
							$subcatid              = (int)$subcat_id;
							$sc                    = $i;
							$comp                  = true;
						}
					}
				// item ?
					if ($isItem) {
						$linkObj['linkparam'] = 'item';
						$item_id              = $this->getRequestPathInfo($linkObj);
						if (!empty($item_id)) {
							$itemid = (int)$item_id;
							$isItem  = true;
						}
						if (preg_match('/^page_/', $pathName)) {
							$isItem  = true;
						}
//var_dump($linkObj);
					}
					break;
			}
			if (preg_match('/^[0-9page]$/', $pathName)) {
				$isExtra = true;
			}
			$i++;
		}

		$feedurls = array(
						 'rss1.xml',
						 'index.rdf',
						 'rss2.xml',
						 'atom.xml',
						);
		$NP_GoogleSitemap = $this->pluginCheck('GoogleSitemap');
		if (!$NP_GoogleSitemap) {
			$NP_GoogleSitemap = $this->pluginCheck('SEOSitemaps');
		}
		if ($NP_GoogleSitemap) {
			$pcSitemaps = $NP_GoogleSitemap->getAllBlogOptions('PcSitemap');
			foreach ($pcSitemaps as $pCsitemap) {
				if (!$pCsitemap) {
					continue;
				}
				$feedurls[] = $pCsitemap;
			}
			$mobSitemaps = $NP_GoogleSitemap->getAllBlogOptions('MobileSitemap');
			foreach ($mobSitemaps as $mobSitemap) {
				if (!$mobSitemap) {
					continue;
				}
				$feedurls[] = $mobSitemap;
			}
		}
		$feedurls     = array_unique($feedurls);
		$request_path = end($v_path);
		$isFeed       = in_array($request_path, $feedurls, true);

// finish decode
		if (!$isExtra && !$isFeed) {
// URL Not Found
			if (substr(end($v_path), -5) == '.html' && !$isItem) {
				$notFound = true;
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
			} elseif (count($v_path) > $sc && !empty($subcatid) && !$isItem) {
				$notFound  = true;
				$linkParam = array(
								   $subrequest => $subcatid
								  );
				$uri       = createCategoryLink($catid, $linkParam);
			} elseif (count($v_path) >= 2 && (!isset($subcatid)||!$subcatid) && !$isItem) {
				$notFound = true;
				if (isset($catid)) {
					$uri = createCategoryLink($catid);
				} else {
					$uri = createBlogidLink($blogid);
				}
			} elseif (reset($v_path) && !$catid && (!isset($subcatid)||!$subcatid) && !$isItem) {
				$notFound = true;
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
				$complete               = true;
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
			$complete               = true;
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
			}
			header('Location: ' . $uri, true, 303);
			exit;
		}
	}

	private function redirectFancyURLtoCustomURL($customurl)
	{
		// FancyURL redirect to Customized URL if use it
		// HTTP status 301 "Moved Permanentry"
		if (strpos(serverVar('REQUEST_URI'), '?') !== false) {
			list($null, $tempQueryString) = explode('?', serverVar('REQUEST_URI'), 2);
			if ($tempQueryString) {
				$temp = explode('&', $tempQueryString);
				foreach ($temp as $k => $val) {
					if (preg_match('/^virtualpath/', $val)) {
						unset($temp[$k]);
					}
				}
				if (!empty($temp)) {
					$tempQueryString = '?' . join('&', $temp);
				}
			}
		}
		header('Location: ' . $customurl . $tempQueryString, true, 301);
		exit;
	}
	
	private function goNP_ExtraSkinJP()
	{
        global $CONF;
		// under v3.2 needs this
		if ($CONF['DisableSite'] && !$member->isAdmin()) {
			header('Location: ' . $CONF['DisableSiteURL'], true, 302);
			exit;
		}
		$extraParams = explode('/', serverVar('PATH_INFO'));
		array_shift ($extraParams);

		if (isset($extraParams[1]) && preg_match('/^([1-9]+[0-9]*)(\?.*)?$/', $extraParams[1], $matches)) {
			$extraParams[1] = $matches[1];
		}

		$NP_ExtraSkinJP = $this->pluginCheck('ExtraSkinJP');
		$NP_ExtraSkinJP->extra_selector($extraParams);
		exit;
	}

// decode 'path name' to 'id'
	private function getRequestPathInfo($linkObj)
	{
		$query = "SELECT obj_id as result FROM [@prefix@]plug_customurl WHERE obj_name='[@obj_name@]' AND obj_bid=[@bid@] AND obj_param='[@linkparam@]'";
		$ph['obj_name']  = sql_real_escape_string($linkObj['name']);
		$ph['bid']       = sql_real_escape_string($linkObj['bid']);
		$ph['linkparam'] = sql_real_escape_string($linkObj['linkparam']);
		$ObjID = quickQuery(parseQuery($query,$ph));
		
		if (!$ObjID) {
			return;
		}
		
		return (int)$ObjID;
	}

// Receive TrackBack ping
	private function _trackback($bid, $path)
	{
		$blog_id   = (int)$bid;
		$NP_TrackBack = $this->pluginCheck('TrackBack');
		if ($NP_TrackBack) {
			if (substr($path, -5, 5) == '.html') {
				$linkObj = array (
								  'linkparam' => 'item',
								  'bid'       => $blog_id,
								  'name'      => $path
				 );
				$item_id = $this->getRequestPathInfo($linkObj);
				if ($item_id) {
					$tb_id = (int)$item_id;
				} else {
					doError(_NO_SUCH_URI);
				}
			} else {
				$tb_id = (int)$path;
			}

			$errorMsg = $NP_TrackBack->handlePing($tb_id);
			if ($errorMsg != '') {
				$NP_TrackBack->xmlResponse($errorMsg);
			} else {
				$NP_TrackBack->xmlResponse();
			}
		}
		exit;
	}

	public function event_GenerateURL($data)
	{
		global $CONF, $manager, $blogid;
		
		if ($data['completed']) {
			return;
		}
		$ref_data =& $data;
		unset($data);
		$data = array_merge($ref_data); // copy data to avoid contamination of the variable
		if (preg_match('@^[1-9][0-9]*$@',$blogid)) {
			$blogid = (int)$blogid;
		} else {
			$blogid = (int)getBlogIDFromName($blogid);
		}
		$NP_MultipleCategories = $this->pluginCheck('MultipleCategories');
		if ($NP_MultipleCategories) {
			if (method_exists($NP_MultipleCategories, 'getRequestName')) {
				$param = array();
				$NP_MultipleCategories->event_PreSkinParse($param);
				global $subcatid;
				$subrequest = $NP_MultipleCategories->getRequestName();
			}
		}
		if (isset($subcatid) && $subcatid) {
			$subcatid = (int)$subcatid;
		}
		$OP_ArchiveKey	= $this->getOption('customurl_archive');
		$OP_ArchivesKey	= $this->getOption('customurl_archives');
		$OP_MemberKey	= $this->getOption('customurl_member');
		$params         = $data['params'];
//		$catParam       = $params['extra']['catid'];
//		$subcatParam    = $params['extra'][$subrequest];
		$useCustomURL   = $this->getAllBlogOptions('use_customurl');
		$objPath        = null;
		$burl           = null;
		switch ($data['type']) {
			case 'item':
				if (!preg_match('@^[1-9][0-9]*$@',$params['itemid'])) {
					return;
				}
				$itemid = (int)$params['itemid'];
				if ($itemid) {
					
					$ph['itemid'] = $itemid;
					
					$bid     = (int)getBlogIDFromItemID($itemid);
					if ($useCustomURL[$bid] == 'no') {
						return;
					}
					$query = "SELECT obj_name as result FROM [@prefix@]plug_customurl WHERE obj_param='item' AND obj_id=[@itemid@]";
					$path  = parseQuickQuery($query, $ph);
					if ($path) {
						$objPath = $path;
					} else {
						if (!$this->_isValid(array('item', 'inumber', $itemid))) {
							$objPath = _NOT_VALID_ITEM;
						} else {
							$y = $m = $d = $temp = '';
							$tque   = 'SELECT itime as result FROM [@prefix@]item WHERE inumber=[@itemid@]';
							$itime  = parseQuickQuery($tque ,$ph);
							sscanf($itime,'%d-%d-%d %s', $y, $m, $d, $temp);
							$defItem   = $this->getOption('customurl_dfitem');
							$tempParam = array(
											   'year'  => $y,
											   'month' => $m,
											   'day'   => $d
											  );
							$ikey      = TEMPLATE::fill($defItem, $tempParam);
							$ipath     = $ikey . '_' . $itemid;
							$query     = 'SELECT ititle as result FROM [@prefix@]item WHERE inumber=[@itemid@]';
							$iname     = parseQuickQuery($query,$ph);
							$this->RegistPath($itemid, $ipath, $bid, 'item', $iname, true);
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
				if (!preg_match('@^[1-9][0-9]*$@',$params['memberid']) || $useCustomURL[$blogid] =='no') {
					return;
				}
				$memberID = (int)$params['memberid'];
				if ($memberID) {
					$path = $this->getMemberOption($memberID, 'customurl_mname');
					if ($path) {
						$data['url'] = $this->_generateBlogLink($blogid) . '/'
									 . $OP_MemberKey . '/' . $path . '.html';
						$data['completed'] = true;
						$ref_data = array_merge($data);
						return;
					} else {
						if (!$this->_isValid(array('member', 'mnumber', $memberID))) {
							$data['url'] = $this->_generateBlogLink($blogid) . '/' . _NOT_VALID_MEMBER;
							$data['completed'] = true;
							$ref_data = array_merge($data);
							return;
						} else {
							$ph['memberID'] = $memberID;
							$mname = parseQuickQuery('SELECT mname AS result FROM [@prefix@]member WHERE mnumber=[@memberID@]', $ph);
							$this->RegistPath($memberID, $mname, 0, 'member', $mname, true);
							$data['url'] = $this->_generateBlogLink($blogid) . '/'
										 . $OP_MemberKey . '/' . $mname . '.html';
							$data['completed'] = true;
							$ref_data = array_merge($data);
							return;
						}
					}
				} else {
					$objPath = '';
				}
				break;
			case 'category':
				if (!preg_match('@^[1-9][0-9]*$@',$params['catid'])) {
					return;
				}
				$cat_id = (int)$params['catid'];
				$bid = (int)getBlogidFromCatID($cat_id);
				if (!$bid || $useCustomURL[$bid] == 'no') {
					return;
				}
				if (!$cat_id) {
					$objPath = '';
					$bid = $blogid;
				} else {
					$objPath = $this->_generateCategoryLink($cat_id);
				}
				if ($bid && $bid != $blogid) {
					$burl = $this->_generateBlogLink($bid);
				}
				break;
			case 'archivelist':
				if ($useCustomURL[$blogid] == 'no') {
					return;
				}
				$objPath = $OP_ArchivesKey . '/';
				$bid     = (int)$params['blogid'];
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
				$bid     = (int)$params['blogid'];
				$burl = $this->_generateBlogLink($bid);
			break;
			case 'blog':
				if (!preg_match('@^[1-9][0-9]*$@',$params['blogid'])) {
					return;
				}
				$bid  = (int)$params['blogid'];
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
		$denyPlugin = false;
		foreach($tempdeb as $k => $v){
			if (!isset($v['class'])) {
				continue;
			}
			$analyzePlugin = (strtolower($v['class']) == 'np_analyze');
			$sitemapPlugin = (strtolower($v['class']) == 'np_googlesitemap' || 
							  strtolower($v['class']) == 'np_seositemaps');
			if ($analyzePlugin || $sitemapPlugin) {
				$denyPlugin = true;
			}
		}

		if (!$denyPlugin && $bid != $blogid) {
			$params['extra'] = array();
		}
		if ($objPath || $data['type'] == 'blog') {
			$LinkURI = $this->_addLinkParams($objPath, (isset($params['extra']) ? $params['extra'] : ''));
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
			if (!$isItem && !$isDirectory && !isset($puri['query'])) {
				$data['url'] .= '/';
			}
			$data['completed'] = true;
			if (strstr ($data['url'], '//')) {
				$link = preg_replace('/([^:])\/\//', "$1/", $data['url']);
			}
			//$tempdeb=debug_backtrace();
			$tb = 0;
			foreach($tempdeb as $k => $v){
				if (isset($v['class']) && isset($v['function'])
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
			$data['completed'] = true;
		}
		if ($data['completed'])
			$ref_data = array_merge($data);
	}

	private function _createSubCategoryLink($scid)
	{
		$scids     = $this->getParents((int)$scid);
		$subcat_ids = explode('/', $scids);
		$eachPath  = array();
		$ph = array();
		foreach ($subcat_ids as $subcat_id) {
			$subcat_id = (int)$subcat_id;
			$ph['subcat_id'] = $subcat_id;
			$query = "SELECT obj_name AS result FROM [@prefix@]plug_customurl WHERE obj_id=[@subcat_id@] AND obj_param='subcategory'";
			$path = parseQuickQuery($query, $ph);
			if ($path) {
				$eachPath[] = $path;
			} else {
				$tempParam = array('plug_multiple_categories_sub', 'scatid', $subcat_id);
				if (!$this->_isValid($tempParam)) {
					return $url = _NOT_VALID_SUBCAT;
				} else {
					$scpath = $this->getOption('customurl_dfscat') . '_' . $subcat_id;
					$query  = 'SELECT catid as result FROM [@prefix@]plug_multiple_categories_sub WHERE scatid=[@subcat_id@]';
					$cid    = parseQuickQuery($query, $ph);
					if (!$cid) {
						return 'no_such_subcat=' . $subcat_id . '/';
					}
					$this->RegistPath($subcat_id, $scpath, $cid, 'subcategory', 'subcat_' . $subcat_id, true);
					$eachPath[] = $scpath;
				}
			}
		}
		$subcatPath = join('/', $eachPath);
		return $subcatPath . '/';
	}

	private function getParents($subid)
	{
		$subid = (int)$subid;
		$NP_MultipleCategories  = $this->pluginCheck('MultipleCategories');
		$mcatVarsion = $NP_MultipleCategories->getVersion() * 100;
		if ((int)$mcatVarsion < 40) {
			return $subid;
		}
		$query = 'SELECT scatid, parentid FROM [@prefix@]plug_multiple_categories_sub WHERE scatid=[@subcat_id@]';
		$ph['subcat_id'] = $subid;
		$res = sql_query(parseQuery($query,$ph));
		list($sid, $parent) = sql_fetch_row($res);
		if ($parent != 0) {
			$r = $this->getParents($parent) . '/' . $sid;
		} else {
			$r = $sid;
		}
		return $r;
	}

	private function _generateCategoryLink($cid)
	{
		global $CONF;
		$cat_id = (int)$cid;
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
				$blog_id = (int)getBlogIDFromCatID($cat_id);
				$catname = 'catid_' . $cat_id;
				$this->RegistPath($cat_id, $cpath, $blog_id, 'category', $catname, true);
				return $cpath . '/';
			}
		}
	}

	private function _generateBlogLink($blog_id)
	{
		global $manager, $CONF;
		static $url = null;
		
		if(isset($url[$blog_id]))
		{
			return $url[$blog_id];
		}
		
		$blog_id = (int)$blog_id;
		$param   = array(
						 'blog',
						 'bnumber',
						 $blog_id
						);
		if (!$this->_isValid($param)) {
			$url[$blog_id] = _NOT_VALID_BLOG;
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
						$query = 'SELECT bshortname as result FROM [@prefix@]blog WHERE bnumber=[@blog_id@]';
						$ph['blog_id'] = $blog_id;
						$bpath = parseQuickQuery($query);
						$this->RegistPath($blog_id, $bpath, 0, 'blog', $bpath, true);
						$burl  = $CONF['IndexURL'] . $bpath . '/';
					}
					$this->_updateBlogURL($burl, $blog_id);
				}
			}
		}
		else
		{
			if (strlen($burl)==0) {
				$usePathInfo = ($CONF['URLMode'] == 'pathinfo');
				if ($usePathInfo) {
					$burl = $CONF['BlogURL'] . '/' . $CONF['BlogKey'] . '/' . $blog_id;
				} else {
					$burl = $CONF['BlogURL'] . '?blogid=' . $blog_id;
				}
			}
		}
		
		$url[$blog_id] = trim($burl, '/');
		return $url[$blog_id];
	}

	private function _updateBlogURL($blog_url, $blog_id)
	{
		return;
		
		$ph = array();
		$ph['blog_id']  = (int)$blog_id;
		$ph['blog_url'] = sql_real_escape_string($blog_url);
		sql_query(parseQuery("UPDATE [@prefix@]blog SET burl='[@blog_url@]' WHERE bnumber=[@blog_id@]", $ph));
	}

	private function _addLinkParams($link, $params)
	{
		global $CONF, $manager, $catid;
		$arcTmp      = (preg_match('/' . $this->getOption('customurl_archives') . '/', $link));
		$arcsTmp     = (preg_match('/' . $this->getOption('customurl_archive') . '/', $link));
		$isArchives  = ($arcTmp || $arcsTmp);
		$NP_MultipleCategories = $this->pluginCheck('MultipleCategories');
		if ($NP_MultipleCategories) {
			$param = array();
			$NP_MultipleCategories->event_PreSkinParse($param);
			global $subcatid;
			if (method_exists($NP_MultipleCategories, 'getRequestName')) {
				$subrequest = $NP_MultipleCategories->getRequestName();
			} else {
				$subrequest = 'subcatid';
			}
		}
		$linkExtra = '';
		if (is_array($params)) {
			if (isset($params['archives'])) {
				$linkExtra = $this->getOption('customurl_archives') . '/';
				unset($params['archives']);
			} elseif (isset($params['archivelist'])) {
				$linkExtra = $this->getOption('customurl_archives') . '/';
				unset($params['archivelist']);
			} elseif (isset($params['archive'])) {
				$y = $m = $d = '';
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
			if (isset($params['blogid'])) {
				unset($params['blogid']);
			}
			$paramlink = array();
			foreach ($params as $param => $value) {
				switch ($param) {
					case 'catid':
					case $CONF['CategoryKey']:
						$cid         = (int)$value;
						$paramlink[] = $this->_generateCategoryLink($cid);
						break;
					case $subrequest:
						if ($NP_MultipleCategories) {
							$sid         = (int)$value;
							$paramlink[] = $this->_createSubCategoryLink($sid);
						}
						break;
					default:
						$paramlink[] = $param . '/' . $value . '/';
						break;
				}
			}
			if (substr($link, -5, 5) == '.html' || $isArchives) {
				$link = join('', $paramlink) . $link;
			} else {
				$link .= join('', $paramlink);
			}
		}
		if ($linkExtra) {
			$link .= $linkExtra;
		}
		if (requestVar('skinid')) {
			$skinid = hsc(requestVar('skinid'));
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
			$link = preg_replace('/([^:])\/\//', "$1/", $link);
		}
		return $link;
	}

	public function doSkinVar($skinType, $link_type = '', $target = '', $title = '')
	{
		global $blogid;
		if ($skinType == 'item' && $link_type == 'trackback') {
			global $itemid, $CONF;
			if ($this->getBlogOption($blogid, 'use_customurl') == 'yes') {
				$query = "SELECT obj_name as result FROM [@prefix@]plug_customurl WHERE obj_param='item' AND obj_id=[@itemid@]";
				$ph['itemid'] = $itemid;
				$itempath = parseQuickQuery($query, $ph);
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
		if (!$link_type) {
			$link_params = array(0, 'b/' . (int)$blogid . '/i,'
						 . $target . ',' . $title);
		} else {
			$l_params = explode('/', $link_type);
			if (count($l_params) == 1) {
				$link_params = array(0, 'b/' . (int)$link_type . '/i,'
							 . $target . ',' . $title);
			} else {
				$link_params = array(0,
									 $link_type . ',' . $target . ',' . $title);
			}
		}
		echo $this->URL_Callback($link_params);
	}

	public function doItemVar(&$item, $link_type = '', $target = '', $title = '')
	{
		if (getNucleusVersion() < '370') {
			return;
		}
		$item_id = (int)$item->itemid;
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

	public function doTemplateVar(&$item, $link_type = '', $target = '', $title = '')
	{
		$item_id = (int)$item->itemid;
		if ($link_type == 'trackback') {
			global $CONF;
			$blog_id = (int)getBlogIDFromItemID($item_id);
			if ($this->getBlogOption($blog_id, 'use_customurl') == 'yes') {
				$query = "SELECT obj_name as result FROM [@prefix@]plug_customurl WHERE obj_param='item' AND obj_id=[@item_id@]";
				$ph['item_id'] = $item_id;
				$itempath = parseQuickQuery($query, $ph);
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

	private function URL_Callback($data, $scatFlag = '')
	{
		global $item_id;
		
		$l_data  = explode(',', $data[1]);
		$l_type  = $l_data[0];
		$target  = $l_data[1];
		$title   = $l_data[2];
		
		$item_id = isset($data['item']->itemid) ? (int)$data['item']->itemid : 0;
		if (!$l_type) {
			$link_params = array (
								  'i',
								  $item_id,
								  'i'
								 );
		} else {
			$link_data = explode('/', $l_type);
			if (count($link_data) == 1) {
				$link_params = array (
									  'i',
									  (int)$l_type,
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
										  (int)$link_data[1],
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
			$ph = array('url'=>hsc($url), 'target'=>hsc($target));
			$ph['title'] = $title ? hsc($title) : hsc($target);
			$ObjLink = parseHtml('<a href="{%url%}" title="{%title%}">{%target%}</a>', $ph);
		} else {
			$ObjLink = hsc($url);
		}
		return $ObjLink;
	}

	private function _isValid($data)
	{
		$query = "SELECT count(*) AS result FROM [@prefix@][@table_name@] WHERE [@key@]='[@value@]'";
		$ph['table_name'] = $data[0];
		$ph['key']        = sql_real_escape_string($data[1]);
		$ph['value']      = sql_real_escape_string($data[2]);
		return ((int)parseQuickQuery($query, $ph) != 0);
	}

	private function _genarateObjectLink($data, $scatFlag = '')
	{
		global $CONF, $manager, $blog;
		$ext = substr(serverVar('REQUEST_URI'), -4);
		if ($ext == '.rdf' || $ext == '.xml') {
			$CONF['URLMode']  = 'pathinfo';
		}
		if ($CONF['URLMode'] != 'pathinfo') {
			return;
		}
		switch ($data[0]) {
			case 'b':
				if ($data[2] == 'n') {
					$bid = getBlogIDFromName($data[1]);
				} else {
					$bid = $data[1];
				}
				$blog_id = (int)$bid;
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
				$cat_id = (int)$cid;
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
				$NP_MultipleCategories = $this->pluginCheck('MultipleCategories');
				if ($NP_MultipleCategories) {
					if ($data[2] == 'n') {
						$query = "SELECT scatid as result FROM [@prefix@]plug_multiple_categories_sub WHERE sname='[@sub_name@]'";
						$ph['sub_name'] = sql_real_escape_string($data[1]);
						$scid = parseQuickQuery($query,$ph);
					} else {
						$scid = $data[1];
					}
					$sub_id = (int)$scid;
					$param  = array(
									'plug_multiple_categories_sub',
									'scatid',
									$sub_id
								   );
					if (!$this->_isValid($param)) {
						$url = _NOT_VALID_SUBCAT;
					} else {
						$query = "SELECT catid as result FROM [@prefix@]plug_multiple_categories_sub WHERE scatid='[@sub_id@]'";
						$cid = (int) parseQuickQuery($query, array('sub_id'=>$sub_id));
						if (method_exists($NP_MultipleCategories, 'getRequestName')) {
							$subrequest = $NP_MultipleCategories->getRequestName();
						}
						if (!$subrequest) {
							$subrequest = 'subcatid';
						}
						$linkParam = array($subrequest => $sub_id);
						$url = createCategoryLink($cid, $linkParam);
					}
				}
				break;
			case 'i':
				$param = array(
							   'item',
							   'inumber',
							   (int)$data[1]
							  );
				if (!$this->_isValid($param)) {
					$url = _NOT_VALID_ITEM;
				} else {
					if ($scatFlag) {
						global $catid, $subcatid;
						if (!empty($catid)) {
							$linkparams['catid'] = (int)$catid;
						}
						if (!empty($subcatid)) {
							$NP_MultipleCategories = $this->pluginCheck('MultipleCategories');
							if ($NP_MultipleCategories) {
								if (method_exists($NP_MultipleCategories, 'getRequestName')) {
									$subrequest = $NP_MultipleCategories->getRequestName();
								} else {
									$subrequest = 'subcatid';
								}
							}
							$linkparams[$subrequest] = (int)$subcatid;
						}
						$url = createItemLink((int)$data[1], $linkparams);
					} else {
						$query = "SELECT obj_name as result FROM [@prefix@]plug_customurl WHERE obj_param='item' AND obj_id=[@item_id@]";
						$item_id = (int) $data[1];
						$path    = parseQuickQuery($query, array('item_id'=>$item_id));
						$blink = $this->_generateBlogLink(getBlogIDFromItemID($item_id));
						if ($path) {
							if ($data[2] == 'path') {
								$url = $path;
							} else {
								$url = $blink . '/' . $path;
							}
						} else {
							if ($data[2] == 'path') {
								$url = $CONF['ItemKey'] . '/'
									 . $item_id;
							} else {
								$url = $blink . '/' . $CONF['ItemKey'] . '/'
									 . $item_id;
							}
						}
					}
				}
				break;
			case 'm':
				if ($data[2] == 'n') {
					$query = "SELECT mnumber as result FROM [@prefix@]member WHERE mname='[@mname@]'";
					$mid = parseQuickQuery($query, array('mname'=>sql_real_escape_string($data[1])));
				} else {
					$mid = $data[1];
				}
				$member_id = (int)$mid;
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

	public function event_InitSkinParse($data)
	{
		global $blogid, $CONF, $manager, $nucleus;
		$reqPaths = explode('/', trim(serverVar('PATH_INFO'), '/'));
		$reqPath  = end($reqPaths);
		
		switch ($reqPath) {
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
			default:
				return;
		}
		
		if (!SKIN::exists($skinName)) {
			exit;
		}
		
		if(method_exists($data['skin'], 'changeSkinByName')) {
			$data['skin']->changeSkinByName($skinName);
		} else {
			$newSkinId = SKIN::getIdFromName($skinName);
			if(method_exists($data['skin'], 'SKIN')) {
				$data['skin']->SKIN($newSkinId);
			} else {
				$data['skin']->__construct($newSkinId);
			}
		}
		
		if ($CONF['DisableSite']) {
			echo '<' . '?xml version="1.0" encoding="ISO-8859-1"?' . '>';
?>
<rss version="2.0">
  <channel>
    <title><?php echo hsc($CONF['SiteName'])?></title>
    <link><?php echo hsc($CONF['IndexURL'])?></link>
    <description></description>
    <docs>http://backend.userland.com/rss</docs>
  </channel>
</rss>
<?php
			exit;
		}
		
		$skinData =& $data['skin'];
		$pageType =  $data['type'];
		
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
		
		global $skinid;
		$skin = new SKIN($skinid);
		PARSER::setProperty('IncludeMode',   $skin->getIncludeMode());
		PARSER::setProperty('IncludePrefix', $skin->getIncludePrefix());
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

		$feed = ob_get_clean();

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
			// dump feed
			echo $feed;
		}
		exit;
	}

	private function getSkinContent($pageType, $skinID)
	{
		$query = "SELECT scontent FROM [@prefix@]skin WHERE sdesc=[@skinID@] AND stype='[@pageType@]'";
		$ph = array();
		$ph['skinID']   = (int)$skinID;
		$ph['pageType'] = sql_real_escape_string($pageType);
		$res = sql_query(parseQuery($query, $ph));

		if ($res && ($obj = sql_fetch_object($res)))
			return $obj->scontent;
		return '';
	}

// merge NP_RightURL
	public function event_PreSkinParse($data)
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

	public function event_PreItem(&$data)
	{
		global $CONF, $manager, $itemid;

		if(!$itemid) $itemid   =  (int)$data['item']->itemid;
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

	public function event_PostItem($data)
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

	public function event_PostDeleteBlog ($data)
	{
		$blogid = (int)$data['blogid'];
		
		if(!$blogid) return;
		
		$query  = "DELETE FROM [@prefix@]plug_customurl WHERE obj_param='blog' AND obj_id=[@obj_id@]";
		sql_query(parseQuery($query, array('obj_id'=>$blogid)));
		
		$query = "DELETE FROM [@prefix@]plug_customurl WHERE obj_param='item' AND obj_bid=[@obj_bid@]";
		sql_query(parseQuery($query, array('obj_bid'=>$blogid)));
		
		$rs = sql_query(parseQuery('SELECT catid FROM [@prefix@]category WHERE cblog=[@blogid@]', array('blogid'=>$blogid)));
		while ($row = sql_fetch_object($rs)) {
			$catid = (int)$row->catid;
			
			$query  = "DELETE FROM [@prefix@]plug_customurl WHERE obj_param='[@key@]' AND obj_id=[@obj_id@]";
			sql_query(parseQuery($query,  array('key'=>'category',    'obj_id'=>$catid)));
			
			$query = "DELETE FROM [@prefix@]plug_customurl WHERE obj_param='[@key@]' AND obj_bid=[@obj_bid@]";
			sql_query(parseQuery($query, array('key'=>'subcategory', 'obj_bid'=>$catid)));
		}
	}

	public function event_PostDeleteCategory ($data)
	{
		$ph['catid'] = (int)$data['catid'];
		
		$query = "DELETE FROM [@prefix@]plug_customurl WHERE obj_param='category' AND obj_id=[@catid@]";
		sql_query(parseQuery($query, $ph));
		
		$query = "DELETE FROM [@prefix@]plug_customurl WHERE obj_param='subcategory' AND obj_bid=[@catid@]";
		sql_query(parseQuery($query, $ph));
	}

	public function event_PostDeleteItem ($data)
	{
		$query = "DELETE FROM [@prefix@]plug_customurl WHERE obj_param='item' AND obj_id=[@itemid@]";
		$ph['itemid'] = (int)$data['itemid'];
		sql_query(parseQuery($query, $ph));
	}

	public function event_PostDeleteMember ($data)
	{
		$query = "DELETE FROM [@prefix@]plug_customurl WHERE obj_param='member' AND obj_id=[@memberid@]";
		$ph['memberid'] = (int)$data['member']->id;
		sql_query(parseQuery($query, $ph));
	}

	public function event_PostAddBlog ($data)
	{
		$blog_id    = (int)$data['blog']->blogid;
		$bshortname = $data['blog']->settings['bshortname'];
		$this->RegistPath($blog_id, $bshortname, 0, 'blog', $bshortname, true);
		$this->setBlogOption($blog_id, 'customurl_bname', $bshortname);
	}

	public function event_PostAddCategory ($data)
	{
		global $CONF;
		$catid = (int)$data['catid'];
		$ph = array('catid'=>$catid);
		if (!$data['blog']->blogid) {
			$query = 'SELECT cblog as result FROM [@prefix@]category WHERE catid=[@catid@]';
			$bid = parseQuickQuery($query, $ph);
		} else {
			$bid = $data['blog']->blogid;
		}
		if (!$data['name']) {
			$query = 'SELECT cname as result FROM [@prefix@]category WHERE catid=[@catid@]';
			$name  = parseQuickQuery($query, $ph);
		} else {
			$name = $data['name'];
		}
		$bid     = (int)$bid;
		$dfcat   = $this->getOption('customurl_dfcat');
		$catpsth = $dfcat . '_' . $catid;
		$this->RegistPath($catid, $catpsth, $bid, 'category', $name, true);
		$this->setCategoryOption($catid, 'customurl_cname', $catpsth);
	}

	public function event_PostAddItem ($data)
	{
		$itemid = (int)$data['itemid'];
		$ph['itemid'] = $itemid;
		$query    = 'SELECT itime as result FROM [@prefix@]item WHERE inumber=[@itemid@]';
		$itime   = parseQuickQuery($query, $ph);
		
		list($y, $m, $d, $null) = sscanf($itime, '%d-%d-%d %s');
		$param['year']  = sprintf('%04d', $y);
		$param['month'] = sprintf('%02d', $m);
		$param['day']   = sprintf('%02d', $d);
		$ipath = TEMPLATE::fill(requestVar('plug_custom_url_path'), $param);
		$query = 'SELECT ititle as result FROM [@prefix@]item WHERE inumber=[@itemid@]';
		$iname = parseQuickQuery($query, $ph);
		$blog_id = (int)getBlogIDFromItemID($itemid);
		$this->RegistPath($itemid, $ipath, $blog_id, 'item', $iname, true);
		if ($this->pluginCheck('TrackBack')) {
			$this->convertLocalTrackbackURL($data);
		}
	}

	public function event_PostRegister ($data)
	{
		$memberID = (int)$data['member']->id;
		$dispName = $data['member']->displayname;
		$this->RegistPath($memberID, $dispName, 0, 'member', $dispName, true);
		$this->setMemberOption($memberID, 'customurl_mname', $dispName);
	}

	public function event_AddItemFormExtras(&$data)
	{
		$this->createItemForm();
	}

	public function event_EditItemFormExtras(&$data)
	{
		$this->createItemForm((int)$data['itemid']);
	}

	public function event_PostUpdateItem($data)
	{
		$itemid = (int)$data['itemid'];
		$ph['itemid'] = $itemid;
		$query = 'SELECT itime as result FROM [@prefix@]item WHERE inumber=[@itemid@]';
		$itime = parseQuickQuery($query ,$ph);
		
		$tpath = requestVar('plug_custom_url_path');
		list($y, $m, $d, $null) = sscanf($itime, '%d-%d-%d %s');
		$param['year']  = sprintf('%04d', $y);
		$param['month'] = sprintf('%02d', $m);
		$param['day']   = sprintf('%02d', $d);
		$ipath = TEMPLATE::fill($tpath, $param);
		
		$query = 'SELECT ititle as result FROM [@prefix@]item WHERE inumber=[@itemid@]';
		$iname = parseQuickQuery($query, $ph);
		
		$blog_id = (int)getBlogIDFromItemID($itemid);
		$this->RegistPath($itemid, $ipath, $blog_id, 'item', $iname);
		if ($this->pluginCheck('TrackBack')) {
			$this->convertLocalTrackbackURL($data);
		}
	}

	private function createItemForm($itemid = 0)
	{
		global $CONF;
		
		$itemid = (int)$itemid;
		
		if ($itemid) {
			$ph['itemid'] = $itemid;
			$query = "SELECT obj_name as result FROM [@prefix@]plug_customurl WHERE obj_param='item' AND obj_id=[@itemid@]";
			$res   = parseQuickQuery($query, $ph);
			$ipath = substr($res, 0, strlen($res)-5);
		} else {
			$ipath = $this->getOption('customurl_dfitem');
		}
		echo <<<OUTPUT
<h3>Custom URL</h3>
<p>
<label for="plug_custom_url">Custom Path:</label>
<input id="plug_custom_url" name="plug_custom_url_path" value="{$ipath}" />
</p>
OUTPUT;
	}

	public function event_PrePluginOptionsUpdate($data)
	{
		$blog_option = ($data['optionname'] == 'customurl_bname');
		$cate_option = ($data['optionname'] == 'customurl_cname');
		$memb_option = ($data['optionname'] == 'customurl_mname');
		$arch_option = ($data['optionname'] == 'customurl_archive');
		$arvs_option = ($data['optionname'] == 'customurl_archives');
		$memd_option = ($data['optionname'] == 'customurl_member');
		$contextid = (int)$data['contextid'];
		$ph['contextid'] = $contextid;
		$context = $data['context'];
		if ($blog_option || $cate_option || $memb_option) {
			if ($context == 'member' ) {
				global $member;
				if (!$member->isAdmin())
				{
					if ($this->getOption('customurl_allow_edit_member_uri') !== 'yes')
					   return;
				}
				$query  = 'SELECT mname as result FROM [@prefix@]member WHERE mnumber=[@contextid@]';
				$name   = parseQuickQuery($query, $ph);
			} elseif ($context == 'category') {
				$blogid = (int)getBlogIDFromCatID($contextid);
				$query  = 'SELECT cname as result FROM [@prefix@]category WHERE catid=[@contextid@]';
				$name   = parseQuickQuery($query, $ph);
			} else {
				$query  = 'SELECT bname as result FROM [@prefix@]blog WHERE bnumber=[@contextid@]';
				$name   = parseQuickQuery($query, $ph);
			}
			
			if(!isset($blogid)) $blogid = 0;
			
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

	public function event_PostMoveItem($data)
	{
		$query = "UPDATE [@prefix@]plug_customurl SET obj_bid=[@destblogid@] WHERE obj_param='item' AND obj_id=[@item_id@]";
		$ph['destblogid'] = (int)$data['destblogid'];
		$ph['item_id']    = (int)$data['itemid'];
		sql_query(parseQuery($query, $ph));
	}

	public function event_PostMoveCategory($data)
	{
		$destblogid = (int)$data['destblog']->blogid;
		$catid      = (int)$data['catid'];
		$ph['destblogid'] = $destblogid;
		$ph['catid']      = $catid;
		$query = "UPDATE [@prefix@]plug_customurl SET obj_bid=[@destblogid@] WHERE obj_param='category' AND obj_id=[@catid@]";
		sql_query(parseQuery($query, $ph));
		$query = 'SELECT inumber FROM [@prefix@]item WHERE icat=[@catid@]';
		$rs = sql_query(parseQuery($query,$ph));
		while ($row = sql_fetch_object($rs)) {
			$odata = array(
						   'destblogid' => $destblogid,
						   'itemid'     => $row->inumber
						  );
			$this->event_PostMoveItem($odata);
		}
	}

	private function RegistPath($objID, $path, $bid, $oParam, $name, $new = false )
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
		}
		$bid   = (int)$bid;
		$objID = (int)$objID;
		$ph['objID'] = $objID;
		$ph['bid']   = $bid;
		$ph['param'] = $oParam;
		$name  = rawurlencode($name);
		$msg = '';

		if ($new && $oParam == 'item') {
			$query = 'SELECT itime as result FROM [@prefix@]item WHERE inumber=[@objID@]';
			$itime = parseQuickQuery($query, $ph);
			list($y, $m, $d, $null) = sscanf($itime, '%d-%d-%d %s');
			$param['year']  = sprintf('%04d', $y);
			$param['month'] = sprintf('%02d', $m);
			$param['day']   = sprintf('%02d', $d);
			$dfItem   = $this->getOption('customurl_dfitem');
			$ikey = TEMPLATE::fill($dfItem, $param); 
				if ($path == $ikey) {
					$path = $ikey . '_' . $objID;
				}
		} elseif (!$new && strlen($path) == 0) {
			$query = "DELETE FROM [@prefix@]plug_customurl WHERE obj_id=[@objID@] AND obj_param='[@param@]'";
			sql_query(parseQuery($query, $ph));
			$msg = array (0, _DELETE_PATH, $name, _DELETE_MSG);
			return $msg;
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
		$ph['path'] = $tempPath;
		$query = "SELECT obj_id FROM [@prefix@]plug_customurl WHERE obj_name='[@path@]' AND obj_bid=[@bid@] AND obj_param='[@param@]' AND obj_id!=[@objID@]";
		$res = sql_query(parseQuery($query,$ph));
		if ($res && ($obj = sql_fetch_object($res))) {
			$msg   = array (0, _CONFLICT_ERROR, $name, _CONFLICT_MSG);
			$path .= '_'.$objID;
		}
		if ($oParam == 'category' && !$msg) {
			$conf_cat = "SELECT obj_id FROM [@prefix@]plug_customurl WHERE obj_name='[@path@]' AND obj_param='blog'";
			$res = sql_query(parseQuery($conf_cat, $ph));
			if ($res && ($obj = sql_fetch_object($res))) {
				$msg   = array (0, _CONFLICT_ERROR, $name, _CONFLICT_MSG);
				$path .= '_'.$objID;
			}
		}
		if ($oParam == 'blog' && !$msg) {
			$conf_blg = "SELECT obj_id FROM [@prefix@]plug_customurl WHERE obj_name='[@path@]' AND obj_param='category'";
			$res = sql_query(parseQuery($conf_blg, $ph));
			if ($res && ($obj = sql_fetch_object($res))) {
				$msg   = array (0, _CONFLICT_ERROR, $name, _CONFLICT_MSG);
				$path .= '_'.$objID;
			}
		}

		$newPath = $path;
		if ($oParam == 'item' || $oParam == 'member') $newPath .= '.html';
		$ph['newPath'] = $newPath;
		
		$query = "SELECT * FROM [@prefix@]plug_customurl WHERE obj_id = [@objID@] AND obj_param='[@param@]'";
		$res = sql_query(parseQuery($query, $ph));
		if ($res && ($row = sql_fetch_object($res)) && !empty($row)) {
			$ph['pathID'] = $row->id;
			$query = "UPDATE [@prefix@]plug_customurl SET obj_name='[@newPath@]' WHERE id=[@pathID@]";
			sql_query(parseQuery($query, $ph));
		} else {
			$query = "INSERT INTO [@prefix@]plug_customurl (obj_param,obj_name,obj_id,obj_bid) VALUES ('[@param@]','[@newPath@]',[@objID@],[@bid@])";
			sql_query(parseQuery($query,$ph));
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
		}
		return $msg;
	}

	private function error($msg = '')
	{
		global $admin;

		$admin->pagehead();
		echo $msg[1].' : '.$msg[2].'<br />';
		echo $msg[3].'<br />';
		echo '<a href="index.php" onclick="history.back()">'._BACK.'</a>';
		$admin->pagefoot();
		return;
	}

	private function convertLocalTrackbackURL($data)
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
		$blog_id = getBlogidFromItemID((int)$data['itemid']);
		$count_ping_urls = count($ping_urls);
		for ($i=0; $i < $count_ping_urls; $i++) {
			
			if(!$localflag[$i]) continue;
			
			$tmp_url         = parse_url($ping_urls[$i]);
			$tmp_url['path'] = trim($tmp_url['path'], '/');
			$path_arr        = explode('/', $tmp_url['path']);
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
			
			if (!$item_id) continue;
			
			$ping_urls[$i] = $CONF['ActionURL'] . '?action=plugin&name=TrackBack&tb_id=' . $item_id;
		}
		$_REQUEST['trackback_ping_url'] = join ("\n", $ping_urls);
	}
	
	public function event_PrePluginOptionsEdit(&$data)
	{
		global $member;
		
		if ($data['context'] !== 'member') return;
		if ($member->isAdmin())            return;
		
		if ($this->getOption('customurl_allow_edit_member_uri') == 'yes') return;
		
		foreach($data['options'] as $k => $v) {
			if ($v['pid'] != $this->getID()) {
				continue;
			}
			unset($data['options'][$k]);
		}
	}

	public function event_PostUpdatePlugin(&$data)
	{
		if ( !method_exists( 'NucleusPlugin' , 'existOptionDesc' ) ) {
			return;
		}
		if ( $this->existOptionDesc( 'customurl_allow_edit_member_uri' ) ) {
			return;
		}
		$this->createOption('customurl_allow_edit_member_uri', _OP_ALLOW_EDIT_MEMBER_URI, 'yesno', 'no');
	}

}

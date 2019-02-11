<?php
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
$myorder   = intval(parseQuickQuery('SELECT porder as result FROM [@prefix@]plugin WHERE pid=[@pid@]', array('pid'=>$this->getID())));
$minorder  = intval(quickQuery('SELECT porder as result FROM [@prefix@]plugin ORDER BY porder ASC LIMIT 1'));
if ($myorder != $minorder || $myorder >1)
{
	if ($minorder <= 1)
	{
		$inc = (($minorder < 0) ? abs($minorder) : 1);
		$updateQuery = sprintf('UPDATE %s SET porder = porder+%d WHERE porder < %d', $plugTable, $inc, $myorder+$inc-1);
		sql_query($updateQuery);
	}
	sql_query(parseQuery('UPDATE [@prefix@]plugin SET porder = 1 WHERE pid=[@pid@]', array('pid'=>$this->getID())));
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
$this->createOption('customurl_allow_edit_member_uri', _OP_ALLOW_EDIT_MEMBER_URI,
					'yesno', 'no');
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
$sql = parseQuery('CREATE TABLE IF NOT EXISTS [@prefix@]plug_customurl ('
	 . ' `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, '
	 . ' `obj_param` VARCHAR(15) NOT NULL, '
	 . ' `obj_name` VARCHAR(128) NOT NULL, '
	 . ' `obj_id` INT(11) NOT NULL, '
	 . ' `obj_bid` INT(11) NOT NULL,'
	 . ' INDEX (`obj_name`)'
	 . ' )');
global $MYSQL_HANDLER;
if ((isset($this->is_db_sqlite) && $this->is_db_sqlite) || in_array('sqlite', $MYSQL_HANDLER))
{
	$sql = str_replace("INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY", "INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL", $sql);
	$sql = preg_replace("#,\s+INDEX .+$#ims", ");", $sql);
	$res = sql_query(parseQuery($sql));
	if ($res === FALSE)
		addToLog (ERROR, parseQuery('NP_CustomURL : failed to create the table [@prefix@]plug_customurl'));
	$sql = parseQuery('CREATE INDEX IF NOT EXISTS `[@prefix@]plug_customurl_idx_obj_name` on `[@prefix@]plug_customurl` (`obj_name`);');
}
sql_query($sql);

// setting default aliases
$this->_createNewPath('blog',     'blog',     'bnumber', 'bshortname');
$this->_createNewPath('item',     'item',     'inumber', 'iblog');
$this->_createNewPath('category', 'category', 'catid',   'cblog');
$this->_createNewPath('member',   'member',   'mnumber', 'mname');

if ($this->pluginCheck('MultipleCategories')) {
	$scatTableName = 'plug_multiple_categories_sub';
	$this->_createNewPath('subcategory', $scatTableName, 'scatid', 'catid');
}

<?php
//
//	URL configuration plugin "NP_CustomURL" ADMIN page
//

	// if your 'plugin' directory is not in the default location,
	// edit this variable to point to your site directory
	// (where config.php is)
	$strRel = '../../../';
    if (!is_file($strRel.'config.php') && is_file($strRel.'../config.php'))
        $strRel .= '../';

	include($strRel . 'config.php');
	include($DIR_LIBS . 'PLUGINADMIN.php');

	// create the admin area page
	$oPluginAdmin = new PluginAdmin('CustomURL');
	$lang_name = str_replace( array('\\','/'), '', getLanguageName());
	$NP_CustomURL_dir = $oPluginAdmin->plugin->getDirectory();
	if (is_file(__DIR__ . "/language/{$lang_name}.php"))
		include_once(__DIR__ . "/language/{$lang_name}.php");
	else
		include_once __DIR__ . '/language/english.php';

	if (!($member->isLoggedIn() && $member->isAdmin())) {
		ACTIONLOG::add(WARNING, _ACTIONLOG_DISALLOWED . serverVar('REQUEST_URI'));
		$myAdmin->error(_CURL_ERROR_DISALLOWED);
	}

class CustomURL_ADMIN
{
	function __construct()
	{
		global $manager, $CONF, $oPluginAdmin;
		$this->plugin   =& $oPluginAdmin->plugin;
		$this->name     =  $this->plugin->getName();
		$this->pluginid =  $this->plugin->getID();
		$this->adminurl =  $this->plugin->getAdminURL();
		$this->editurl  =  $CONF['AdminURL'];
		$this->pediturl =  $CONF['AdminURL']
						. 'index.php?action=pluginoptions&amp;plugid='
						. $this->pluginid;
		$this->table = sql_table('plug_customurl');
		$this->uScat = ($manager->pluginInstalled('NP_MultipleCategories') == TRUE);
		if ($manager->pluginInstalled('NP_MultipleCategories')) {
			$mplugin =& $manager->getPlugin('NP_MultipleCategories');
			if (method_exists($mplugin, 'getRequestName')) {
				$this->mcadmin = $mplugin->getAdminURL();
			}
		}
	}

	function action($action)
	{
		global $manager;
		$methodName         = 'action_' . $action;
		$this->actions      = strtolower($action);
		$aActionsNotToCheck = array(
									'blogview',
									'categoryview',
									'memberview',
									'itemview',
									'pathupdate',
								   );
		if (!in_array($this->actions, $aActionsNotToCheck)) {
			if (!$manager->checkTicket()) {
				$this->error(_ERROR_BADTICKET);
			}
		}

		if (method_exists($this, $methodName)) {
			call_user_func(array(&$this, $methodName));
		} else {
			$this->error(sprintf('%s (%s)', _BADACTION, $action));
		}
	}

	function disallow()
	{

		ACTIONLOG::add(WARNING, _ACTIONLOG_DISALLOWED . serverVar('REQUEST_URI'));
		$msg = array (0, _CURL_ERROR_DISALLOWED, '***', _DISALLOWED_MSG);
		$this->error($msg);
	}

	function error($msg = '')
	{
		global $oPluginAdmin;

		$oPluginAdmin->start();
		$printData = $msg[1] . 'name : ' . $msg[2] . '<br />'
				   . $msg[3] . '<br />'
				   . '<a href="' . $this->adminurl . 'index.php" onclick="history.back()">'
				   . _BACK . '</a>';
		echo $printData;
		$oPluginAdmin->end();
		unset($printData);
		exit;
	}

	function action_blogview($msg = '')
	{
		global $oPluginAdmin;

		$oPluginAdmin->start();
		$printData = '<h2><a id="pagetop">'._ADMIN_AREA_TITLE.'</a></h2>'
				   . '<ul style="list-style:none;">'
				   . '  <li>'
				   . '    <a href="' . $this->pediturl . '">'
				   . _OPTION_SETTING
				   . '    </a>'
				   . '  </li>'
				   . '  <li>'
				   . '    <a href="' . $this->adminurl . 'index.php?action=memberview">'
				   . _FOR_MEMBER_SETTING
				   . '    </a>'
				   . '  </li>'
				   . '</ul>'
				   . '<p>' . hsc($msg);
		echo $printData;
		unset($printData);
		$this->print_tablehead(_BLOG_LIST_TITLE, _LISTS_ACTIONS);
		$query = sprintf('SELECT bname,bnumber,bshortname FROM %s', sql_table('blog'));
		$res   = sql_query($query);
		while ($b = sql_fetch_object($res)) {
			$forCatURI  = sprintf(
			    '%sindex.php?action=categoryview&amp;blogid=%s'
                , $this->adminurl
                , $b->bnumber);
			$forItemURI = sprintf(
			    '%sindex.php?action=itemview&amp;blogid=%s'
                , $this->adminurl
                , $b->bnumber);
			$bPath      = hsc($this->plugin->getBlogOption($b->bnumber, 'customurl_bname'));
			$data = array (
	                       'oid'          => (int)$b->bnumber,
	                       'obd'          => 0,
	                       'opr'          => 'blog',
	                       'name'         => hsc($b->bname),
	                       'ret'          => 'blogview',
	                       'ed_URL'       => $this->editurl . 'index.php?action=blogsettings'
	                       				  .  '&amp;blogid=' . (int)$b->bnumber,
	                       'desc'         => '[<a href="' . $forItemURI . '" style="font-size:x-small;">'
	                                      .  _FOR_ITEMS_SETTING
	                                      .  '</a>]'
	                                      .  '&nbsp;'
	                                      .  '[<a href="' . $forCatURI . '" style="font-size:x-small;">'
	                                      .  _FOR_CATEGORY_SETTING
	                                      .  '</a>]',
	                       'path'         => $bPath,
	                       'setting_text' => _BLOG_SETTING
						  );
			$this->print_tablerow($data);
		}
			echo '</tbody></table>';
		echo '</p>';
		unset($query);
		$oPluginAdmin->end();
	}

	function action_categoryview($bid = '', $msg = '')
	{
		global $CONF, $oPluginAdmin;
		if (empty($bid)) {
			if (getVar('blogid')) {
				$bid = intGetVar('blogid');
			} else {
				$bid = (int)$CONF['DefaultBlog'];
			}
		} else {
			$bid = (int)$bid;
		}
		$bname = hsc(getBlognameFromID($bid));

		$oPluginAdmin->start();
		$printData = '<h2><a id="pagetop">'._ADMIN_AREA_TITLE.'</a></h2>'
				   . '<ul style="list-style:none;">'
				   . '  <li>'
				   . '    <a href="' . $this->pediturl . '">'
				   . _OPTION_SETTING
				   . '    </a>'
				   . '  </li>'
				   . '  <li>'
				   . '    <a href="' . $this->adminurl . 'index.php?action=blogview">'
				   . _FOR_BLOG_SETTING
				   . '    </a>'
				   . '  </li>'
				   . '  <li>'
				   . '    <a href="' . $this->adminurl . 'index.php?action=itemview&amp;blogid=' . $bid . '">'
				   ._FOR_ITEMS_SETTING
				   . '    </a>'
				   . '  </li>'
				   . '  <li>'
				   . '    <a href="' . $this->adminurl . 'index.php?action=memberview">'
				   . _FOR_MEMBER_SETTING
				   . '    </a>'
				   . '  </li>'
				   . '</ul>'
				   . '<p>' . hsc($msg)
				   . '<h3 style="padding-left: 0px">' . $bname . '</h3>';
		echo $printData;
		unset($printData);
		$this->print_tablehead(_LISTS_CAT_NAME, _LISTS_DESC);
		$query = 'SELECT catid, cname, cdesc FROM %s WHERE cblog = %d';
		$query = sprintf($query, sql_table('category'), $bid);
		$cnm   = sql_query($query);
		while ($c = sql_fetch_object($cnm)) {
			$cPath = hsc($this->plugin->getCategoryOption($c->catid, 'customurl_cname'));
			$data  = array (
							'oid'    => (int)$c->catid,
							'obd'    => $bid,
								'opr'    => 'category',
							'name'   => hsc($c->cname),
							'ret'    => 'catoverview',
							'ed_URL' => $this->editurl
									 .  'index.php?action=categoryedit'
									 .  '&amp;blogid=' . $bid
									 .  '&amp;catid=' . (int)$c->catid,
							'desc'   => hsc($c->cdesc),
							'path'   => $cPath
						   );
			$this->print_tablerow($data);
			if ($this->uScat) {
				$query = 'SELECT scatid, sname, sdesc FROM %s WHERE catid = %d';
				$query = sprintf($query, sql_table('plug_multiple_categories_sub'), (int)$c->catid);
				$scnm  = sql_query($query);
				while ($sc = sql_fetch_object($scnm)) {
					$query = 'SELECT obj_name '
						   . 'FROM %s '
						   . 'WHERE obj_param = "subcategory" '
						   . 'AND   obj_bid = %d '
						   . 'AND   obj_id = %d';
					$query = sprintf($query, $this->table, (int)$c->catid, (int)$sc->scatid);
					$scpt  = sql_query($query);
					$scp   = sql_fetch_object($scpt);
					$data  = array (
									'oid'    => (int)$sc->scatid,
									'obd'    => (int)$c->catid,
									'opr'    => 'subcategory',
									'name'   => '&raquo;' . hsc($sc->sname),
									'ret'    => 'catoverview',
									'ed_URL' => $this->mcadmin
											 .  'index.php?action=scatedit'
											 .  '&amp;catid=' . (int)$c->catid
											 .  '&amp;scatid=' . (int)$sc->scatid,
									'desc'   => hsc($sc->sdesc),
									'path'   => hsc($scp->obj_name)
								   );
					$this->print_tablerow($data);
				}
			}
		}
		echo '</tbody></table>';
		echo '<a href="' . $this->adminurl . 'index.php" onclick="history.back()">' . _BACK . '</a>';
		echo '</p>';
		unset($query);
		$oPluginAdmin->end();
	}

	function action_memberview($msg = '')
	{
		global $oPluginAdmin;

		$oPluginAdmin->start();
		$printData = '<h2>' . _ADMIN_AREA_TITLE . '</h2>'
				   . '<ul style="list-style:none;">'
				   . '  <li>'
				   . '    <a href="' . $this->pediturl . '">'
				   . _OPTION_SETTING
				   . '    </a>'
				   . '  </li>'
				   . '  <li>'
				   . '    <a href="' . $this->adminurl . 'index.php?action=blogview">'
				   . _FOR_BLOG_SETTING
				   . '    </a>'
				   . '  </li>'
				   . '</ul>'
				   . '<p>' . hsc($msg);
		echo $printData;
		unset($printData);
		$this->print_tablehead(_LOGIN_NAME, _MEMBERS_REALNAME);
		$query = 'SELECT %s,%s,%s FROM %s';
		$query = sprintf($query, 'mname', 'mnumber', 'mrealname', sql_table('member'));
		$res   = sql_query($query);
		while ($m = sql_fetch_object($res)) {
			$mPath = hsc($this->plugin->getMemberOption($m->mnumber, 'customurl_mname'));
			$data  = array (
						    'oid'    => (int)$m->mnumber,
						    'obd'    => 0,
						    'opr'    => 'member',
						    'name'   => hsc($m->mname),
						    'ret'    => 'memberview',
						    'ed_URL' => sprintf(
						        '%sindex.php?action=memberedit&amp;memberid=%d'
                                , $this->editurl
                                , (int)$m->mnumber
                            ),
						    'desc'   => hsc($m->mrealname),
						    'path'   => $mPath
						   );
			$this->print_tablerow($data);
		}
		echo '</tbody></table></p>';
		unset($query);
		$oPluginAdmin->end();
	}

	function action_itemview($bid = 0, $msg = '') {
		global $CONF, $oPluginAdmin;

		if (empty($bid)) {
			if (getVar('blogid')) {
				$bid = intGetVar('blogid');
			} else {
				$bid = (int)$CONF['DefaultBlog'];
			}
		} else {
			$bid = (int)$bid;
		}
		$oPluginAdmin->start();
		$printData = '<h2>'._ADMIN_AREA_TITLE.'</h2>'
				   . '<ul style="list-style:none;">'
				   . '  <li>'
				   . '    <a href="' . $this->pediturl . '">'
				   . _OPTION_SETTING
				   . '    </a>'
				   . '  </li>'
				   . '  <li>'
				   . '    <a href="' . $this->adminurl . 'index.php?action=blogview">'
				   . _FOR_BLOG_SETTING
				   . '    </a>'
				   . '  </li>'
				   . '  <li>'
				   . '    <a href="' . $this->adminurl . 'index.php?action=categoryview&amp;blogid=' . $bid . '">'
				   . _FOR_CATEGORY_SETTING
				   . '    </a>'
				   . '  </li>'
				   . '  <li>'
				   . '    <a href="' . $this->adminurl . 'index.php?action=memberview">'
				   . _FOR_MEMBER_SETTING
				   . '    </a>'
				   . '  </li>'
				   . '</ul>'
				   . '<p><h3>' . hsc($msg) . '</h3>';
		echo $printData;
		unset($printData);
		$this->print_tablehead(_LISTS_TITLE, _LISTS_ITEM_DESC);
		$query = sprintf(
		    'SELECT ititle,inumber,ibody FROM %s WHERE iblog=%d ORDER BY itime DESC'
            , sql_table('item')
            , $bid
        );
		$res   = sql_query($query);
		while ($i = sql_fetch_object($res)) {
			$query    = sprintf(
			    "SELECT obj_name as result FROM %s WHERE obj_param='item' AND obj_id=%d"
                , sql_table('plug_customurl')
                , (int)$i->inumber
            );
			$temp_res = quickQuery($query);
			$ipath = hsc(substr($temp_res, 0, -5));
			$data = array (
                           'oid'    => (int)$i->inumber,
                           'obd'    => $bid,
                           'opr'    => 'item',
                           'name'   => hsc($i->ititle),
                           'ret'    => 'itemview',
                           'ed_URL' => sprintf(
                               '%sindex.php?action=itemedit&amp;itemid=%d'
                               , $this->editurl
                               , (int)$i->inumber
                           ),
                           'path'   => $ipath
					);
			if (extension_loaded('mbstring')) {
				$data['desc'] = hsc(mb_substr(strip_tags($i->ibody), 0, 80));
			} else {
				hsc(substr(strip_tags($i->ibody), 0, 80));
			}
			$this->print_tablerow($data);
		}
		echo '</tbody></table></p>';
		unset($query);
		$oPluginAdmin->end();
	}

	function print_tablehead($o_name, $o_desc)
	{
		$PATH   = _LISTS_PATH;
		$ACTION = _LISTS_ACTIONS;
echo <<< TABLE_HEAD
	<table>
		<thead>
			<tr>
				<th>{$o_name}</th>
				<th>{$o_desc}</th>
				<th style="width:180px;">{$PATH}</th>
				<th style="width:80px;">{$ACTION}</th>
			</tr>
		</thead>
		<tbody>
TABLE_HEAD;
	}

	function print_tablerow($data)
	{
		global $manager;

		$updateText = _SETTINGS_UPDATE_BTN;
		$edit       = _EDIT;
echo <<< TBODY
			<tr onmouseover="focusRow(this);" onmouseout="blurRow(this);">
				<form method="post" action="{$this->adminurl}index.php" />
				<input type="hidden" name="action" value="pathupdate" />
				<input type="hidden" name="oid" value="{$data['oid']}" />
				<input type="hidden" name="obd" value="{$data['obd']}" />
				<input type="hidden" name="opr" value="{$data['opr']}" />
				<input type="hidden" name="name" value="{$data['name']}" />
				<input type="hidden" name="ret" value="{$data['ret']}" />
TBODY;
		$manager->addTicketHidden();
echo <<< TBODY
				<td>{$data['name']}&nbsp;&nbsp;
					<a href="{$data['ed_URL']}" style="font-size:xx-small;">[{$edit}]</a>
				</td>
				<td>{$data['desc']}</td>
				<td><input type="text" name="path" size="32" value="{$data['path']}"/></td>
				<td><input type="submit" name="update" value="{$updateText}" /></td>
				</form>
			</tr>
TBODY;
	}

	function action_pathupdate() {
		$o_oid   = intRequestVar('oid');
		$o_bid   = intRequestVar('obd');
		$o_param = requestVar('opr');
		$o_name  = requestVar('name');
		$newPath = requestVar('path');
		$action  = requestVar('ret');

		$msg = $this->plugin->RegistPath($o_oid, $newPath, $o_bid, $o_param, $o_name);
		if ($msg) {
			$this->error($msg);
			if ($msg[0] != 0) {
				return;
			}
		}
		switch($action) {
			case 'catoverview':
				if ($o_param === 'subcategory') {
					$bid = getBlogIDFromCatID($o_bid);
				} else {
					$bid = $o_bid;
				}
				$this->action_categoryview($bid, _UPDATE_SUCCESS);
			break;
			case 'memberview':
				$this->action_memberview(_UPDATE_SUCCESS);
			break;
			case 'blogview':
				$this->action_blogview(_UPDATE_SUCCESS);
			break;
			case 'itemview':
				$this->action_itemview($o_bid, _UPDATE_SUCCESS);
			break;
			default:
				echo _UPDATE_SUCCESS;
		}
	}

	function action_goItem() {

		$bid = getVar('blogid');
		$this->action_itemview($bid);
	}

	function action_goCategory() {
		$bid = getVar('blogid');
		$this->action_categoryview($bid);
	}
}

$myAdmin = new CustomURL_ADMIN();

if (requestVar('action')) {
	$myAdmin->action(requestVar('action'));
} else {
	$myAdmin->action('blogview');
}


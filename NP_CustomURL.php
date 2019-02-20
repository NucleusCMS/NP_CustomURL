<?php

global $CONF;
$CONF['Self']='';

class NP_CustomURL extends NucleusPlugin
{

    private $currentItem;

    public function getMinNucleusVersion() { return '380';}
    public function getName()              { return 'Customized URL';}
    public function getAuthor()            { return 'shizuki + nekonosippo + Cacher + Reine + yamamoto';}
    public function getURL()               { return 'http://japan.nucleuscms.org/wiki/plugins:customurl';}
    public function getVersion()           { return '0.5';}
    public function getDescription()       { return _DESCRIPTION;}
    public function hasAdminArea()         { return 1;}
    public function getTableList()         { return array(parseQUery('[@prefix@]plug_customurl'));}
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

    public function init()
    {
        if(strpos(serverVar('REQUEST_URI'),'/nucleus/')===false) {
            return;
        }
        $language = str_replace(array('\\','/'), '', getLanguageName());
        $plugin_path = $this->getDirectory();
        if (!is_file(sprintf("%slanguage/%s.php", $plugin_path, $language))) {
            include_once sprintf("%slanguage/%s.php", $plugin_path, $language);
        }
        else {
            include_once sprintf('%slanguage/english.php', $plugin_path);
        }
    }

    public function doSkinVar($skinType, $link_type = '', $target = '', $title = '')
    {
        global $blogid;
        if ($skinType === 'item' && $link_type === 'trackback') {
            global $itemid, $CONF;
            if ($this->getBlogOption($blogid, 'use_customurl') === 'yes') {
                $itempath = quickQuery(sprintf(
                    "SELECT obj_name as result FROM %s WHERE obj_param='item' AND obj_id=%d"
                    , sql_table('plug_customurl')
                    , $itemid));
                if ($target !== 'ext') {
                    $uri = sprintf('%s/trackback/%s', $CONF['BlogURL'], $itempath);
                } elseif ($target === 'ext') { // /item_123.trackback
                    $uri = sprintf(
                        '%s/%s.trackback'
                        , $CONF['BlogURL']
                        , substr($itempath, 0, -5)
                    );
                }
            } else {
                $uri = $CONF['ActionURL']
                    . '?action=plugin&amp;name=TrackBack&amp;tb_id=' . $itemid;
            }
            echo $uri;
            return;
        }
        if (!$link_type) {
            $link_params = array(0, sprintf(
                'b/%d/i,%s,%s'
                , (int)$blogid
                , $target
                , $title
            ));
        } else {
            if (substr_count($link_type,'/') == 1) {
                $link_params = array(0, sprintf(
                    'b/%d/i,%s,%s'
                    , (int)$link_type
                    , $target, $title
                ));
            } else {
                $link_params = array(0, sprintf(
                    '%s,%s,%s'
                    , $link_type
                    , $target
                    , $title
                ));
            }
        }
        echo $this->URL_Callback($link_params);
    }

    public function doItemVar(&$item, $link_type = '', $target = '', $title = '')
    {
        if (getNucleusVersion() < '330') {
            return;
        }
        $item_id = (int)$item->itemid;
        if (!$link_type || $link_type === 'subcategory') {
            $link_params = array(0, sprintf(
                'i/%d/i,%s,%s'
                , $item_id
                , $target
                , $title
            ));
        } elseif ($link_type === 'path') {
            $link_params = array(0, sprintf(
                'i/%d/path,%s,%s'
                , $item_id
                , $target
                , $title
            ));
        } else {
            $link_params = array(0, sprintf(
                '%s,%s,%s'
                , $link_type
                , $target
                , $title
            ));
        }

        if ($link_type === 'subcategory') {
            echo $this->URL_Callback($link_params, 'scat');
        } else {
            echo $this->URL_Callback($link_params);
        }
    }

    public function doTemplateVar(&$item, $link_type = '', $target = '', $title = '')
    {
        $item_id = (int)$item->itemid;
        if ($link_type === 'trackback') {
            global $CONF;
            $blog_id = (int)getBlogIDFromItemID($item_id);
            if ($this->getBlogOption($blog_id, 'use_customurl') === 'yes') {
                $itempath = quickQuery(sprintf(
                    "SELECT obj_name as result FROM %s WHERE obj_param='item' AND obj_id=%d"
                    , sql_table('plug_customurl')
                    , $item_id
                ));
                if ($target !== 'ext') {
                    $uri = sprintf(
                        '%s/trackback/%s'
                        , $CONF['BlogURL']
                        , $itempath
                    );
                } elseif ($target === 'ext') {
                    $uri = sprintf(
                        '%s/%s.trackback'
                        , $CONF['BlogURL']
                        , substr($itempath, 0, -5)
                    );
                }
            } else {
                $uri = sprintf(
                    '%s?action=plugin&amp;name=TrackBack&amp;tb_id=%d'
                    , $CONF['ActionURL']
                    , $item_id
                );
            }
            echo $uri;
            return;
        }
        if (!$link_type || $link_type === 'subcategory') {
            $link_params = array(0, sprintf(
                'i/%d/i,%s,%s'
                , $item_id
                , $target
                , $title
            ));
        } elseif ($link_type === 'path') {
            $link_params = array(0, sprintf(
                'i/%d/path,%s,%s'
                , $item_id
                , $target
                , $title
            ));
        } else {
            $link_params = array(0, sprintf(
                '%s,%s,%s'
                , $link_type
                , $target
                , $title
            ));
        }
        if ($link_type === 'subcategory') {
            echo $this->URL_Callback($link_params, 'scat');
        } else {
            echo $this->URL_Callback($link_params);
        }
    }

    public function event_ParseURL($data)
    {
        global $CONF, $curl_blogid, $blogid, $itemid, $catid;
        global $memberid, $archivelist, $archive, $query;
        $info     =  $data['info'];
        $complete =& $data['complete'];
        if ($complete) {
            return;
        }
        $useCustomURL = $this->getAllBlogOptions('use_customurl');
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

        if (!$blogid) {
            if ( getVar('blogid') ) {
                if ( is_numeric(getVar('blogid')) ) {
                    $blogid = (int)getVar('blogid');
                } else {
                    $blogid = (int)getBlogIDFromName(getVar('blogid'));
                }
            } elseif ($curl_blogid) {
                $blogid = (int)$curl_blogid;
            } elseif ($itemid>0) {
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
        $_SERVER['PATH_INFO'] = join('/', $v_path);

// Admin area check
        $uri = str_replace('/', '\/', sprintf(
            '%s%s%s'
            , "http://"
            , serverVar('HTTP_HOST')
            , serverVar('SCRIPT_NAME')
        ));
        if (strpos($uri, $CONF['PluginURL'] === 0
            ||
            (getVar('action') === 'plugin' && getVar('name')))
        ) {
            $UsingPlugAdmin = true;
        } else{
            $UsingPlugAdmin = false;
        }

// get real blogid
        $linkObj = array (
            'bid'       => 0,
            'name'      => reset($v_path),
            'linkparam' => 'blog'
        );
        $blog_id = $this->getRequestPathInfo($linkObj);
        if ($blog_id) {
            $blogid = $blog_id;
            array_shift($v_path);
        }
        if ($useCustomURL[$blogid] === 'no') {
            return;
        }

// redirect to other URL style
        $useCustomURLyes = ($useCustomURL[$blogid] === 'yes');
        if ($useCustomURLyes && !$UsingPlugAdmin && !$CONF['UsingAdminArea']) {
// Search query redirection
// 301 permanent ? or 302 temporary ?
            $queryURL = (strpos(serverVar('REQUEST_URI'), 'query=') !== false);
            $search_q = (getVar('query') || $queryURL);
            $redirectSerch = ($this->getBlogOption($blogid, 'redirect_search') === 'yes');
            if ($redirectSerch) {
                if ($search_q) {
                    $que_str = str_replace(
                        array('/', "'", '&')
                        , array(md5('/'), md5("'"), md5('&'))
                        , hsc(getVar('query'))
                    );
                    $que_str = urlencode($que_str);
                    $search_path = sprintf('search/%s', $que_str);
                    $redurl = sprintf('%s%s', createBlogidLink($blogid), $search_path);
                    redirect($redurl); // 302 Moved temporary
                    exit;
                }
            } else {
                if($search_q) {
                    return;
                }
                $exLink = $search_q ? true : false;
            }

// redirection nomal URL to FancyURL
            $temp_req       = explode('?', serverVar('REQUEST_URI'));
            $reqPath        = trim(end($temp_req), '/');
            $indexrdf       = ($reqPath === 'xml-rss1.php');
            $atomfeed       = ($reqPath === 'atom.php');
            $rss2feed       = ($reqPath === 'xml-rss2.php');
            $feeds          = ($indexrdf || $atomfeed || $rss2feed);
            $redirectNormal = ($this->getBlogOption($blogid, 'redirect_normal') === 'yes');
            if ($redirectNormal && serverVar('QUERY_STRING') && !$feeds && !$exLink) {
                $temp = explode('&', serverVar('QUERY_STRING'));
                foreach ($temp as $k => $val) {
                    if (strpos($val, "virtualpath") === 0) {
                        unset($temp[$k]);
                    }
                }
                if (!empty($temp)) {
                    $p_arr = array();
                    foreach ($temp as $key => $value) {
                        if(strpos($value,'=')!==false) {
                            list($k, $null) = explode('=', $value, 2);
                        } else {
                            $k = $value;
                        }
                        switch ($k) {
                            case 'blogid';
                                $p_arr[] = sprintf('%s/%s', $CONF['BlogKey'], intGetVar('blogid'));
                                unset($temp[$key]);
                                break;
                            case 'catid';
                                $p_arr[] = sprintf('%s/%s', $CONF['CategoryKey'], intGetVar('catid'));
                                unset($temp[$key]);
                                break;
                            case 'itemid';
                                $p_arr[] = sprintf('%s/%s', $CONF['ItemKey'], intGetVar('itemid'));
                                unset($temp[$key]);
                                break;
                            case 'memberid';
                                $p_arr[] = sprintf('%s/%s', $CONF['MemberKey'], intGetVar('memberid'));
                                unset($temp[$key]);
                                break;
                            case 'archivelist';
                                $p_arr[] = sprintf('%s/%d', $CONF['ArchivesKey'], $blogid);
                                unset($temp[$key]);
                                break;
                            case 'archive';
                                $p_arr[] = sprintf('%s/%d/%s', $CONF['ArchiveKey'], $blogid, getVar('archive'));
                                unset($temp[$key]);
                                break;
                            default:
                                if ( isset($subrequest) && $subrequest == $k) {
                                    $p_arr[] = $subrequest . '/'
                                        . intGetVar($subrequest);
                                    unset($temp[$key]);
                                }
                                break;
                        }
                    }
                    if (!empty($temp)) {
                        $queryTemp = '/?' . join('&', $temp);
                    }
                    if (reset($p_arr)) {
                        $b_url    = createBlogidLink($blogid);
                        $red_path = '/' . join('/', $p_arr);
                        if (substr($b_url, -1) === '/') {
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
// decode TrackBack URL shorten ver.
        $tail = end($v_path);
        if (substr($tail, -10, 10) === '.trackback') {
            $v_pathName = substr($tail, 0, -10);
            if (is_numeric($v_pathName) || substr($v_pathName, -5) === '.html') {
                $this->_trackback($blogid, $v_pathName);
            } else {
                $this->_trackback($blogid, $v_pathName . '.html');
            }
            return;
        }

        $cLink = $iLink = $exLink = false;

        $i = 1;
        $NP_ExtraSkinJPFlag = false;
        $redURI = NULL;
        $sc = NULL;
        foreach($v_path as $pathName) {
            if(!isset($subrequest)) $subrequest = null;
            switch ($pathName) {
                case $CONF['BlogKey']:
                    if (isset($v_path[$i]) && is_numeric($v_path[$i])) {
                        if ($useCustomURL[(int)$v_path[$i]] !== 'yes') {
                            $blogid = (int)$v_path[$i];
                        } else {
                            $redURI = createBlogidLink((int)$v_path[$i]);
                        }
                    }
                    break;
                // for items
                case $CONF['ItemKey']:
                    if (isset($v_path[$i]) && is_numeric($v_path[$i])) {
                        if ($useCustomURL[$blogid] !== 'yes') {
                            $itemid = (int)$v_path[$i];
                            $iLink  = true;
                        } else {
                            $redURI = createItemLink((int)$v_path[$i]);
                        }
                    }
                    break;
                // for categories
                case $CONF['CategoryKey']:
                case 'catid':
                    if (isset($v_path[$i]) && is_numeric($v_path[$i])) {
                        if ($useCustomURL[$blogid] !== 'yes') {
                            $catid  = (int)$v_path[$i];
                            $cLink  = true;
                        } else {
                            $redURI = createCategoryLink((int)$v_path[$i]);
                        }
                    }
                    break;
                // for subcategories
                case $subrequest:
                    $c = $i - 2;
                    $subCat = (isset($v_path[$i]) && is_numeric($v_path[$i]));
                    if ($mcategories && $subCat && $i >= 3 && is_numeric($v_path[$c])) {
                        if ($useCustomURL[$blogid] !== 'yes') {
                            $subcatid  = (int)$v_path[$i];
                            $catid     = (int)$v_path[$c];
                            $cLink     = true;
                        } else {
                            $subcat_id = (int)$v_path[$i];
                            $catid     = (int)$v_path[$c];
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
                        if ($useCustomURL[(int)$v_path[$i]] !== 'yes') {
                            $archivelist = (int)$v_path[$i];
                            $blogid      = $archivelist;
                            $exLink      = true;
                        } else {
                            $redURI      = createArchiveListLink((int)$v_path[$i]);
                        }
                        // Customized URL
                    } elseif (isset($v_path[$i]) && strpos($v_path[$i], 'page') === false) {
                        $archivelist = $blogid;
                        $redURI      = createArchiveListLink($archivelist);
                    } else {
                        $archivelist = $blogid;
                        $exLink      = true;
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
                            if ($useCustomURL[(int)$v_path[$i]] !== 'yes') {
                                $blogid = (int)$v_path[$i];
                                $exLink = true;
                            } else {
                                $blogid = (int)$v_path[$i];
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
                            $exLink = true;
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
                    $customMemberURL = (substr($v_path[$i], -5, 5) === '.html');
                    if (isset($v_path[$i]) && $customMemberURL) {
                        $memberInfo = array(
                            'linkparam' => 'member',
                            'bid'       => 0,
                            'name'      => $v_path[$i]
                        );
                        $member_id  = $this->getRequestPathInfo($memberInfo);
                        $memberid   = (int)$member_id;
                        $exLink     = true;
                        // FancyURL
                    } elseif (isset($v_path[$i]) && is_numeric($v_path[$i])) {
                        if ($useCustomURL[$blogid] !== 'yes') {
                            $memberid = (int)$v_path[$i];
                            $exLink   = true;
                        } else {
                            $redURI = createMemberLink((int)$v_path[$i]);
                        }
                    } else {
                        $redURI = createBlogidLink($blogid);
                    }
                    break;
                case 'tag':
                    if ($this->pluginCheck('TagEX')) $exLink = true;
                    break;//2008-07-28 http://japan.nucleuscms.org/bb/viewtopic.php?p=23175#23175
                case 'page':
                    $exLink = true;
                    break;
                // for ExtraSkinJP
                case 'extra':
                    $ExtraSkinJP = $this->pluginCheck('ExtraSkinJP');
                    if ($ExtraSkinJP) {
                        $NP_ExtraSkinJPFlag = true;
                    }
                    break;
                // for search query
                case 'search':
                    $redirectSerch = ($this->getBlogOption($blogid, 'redirect_search') === 'yes');
                    if ($redirectSerch) {
                        $que_str = urldecode($v_path[$i]);
                        $que_str = htmlspecialchars_decode(str_replace(
                            array(md5('/'), md5("'"), md5('&'))
                            , array('/', "'", '&')
                            , $que_str
                        ));
                        $_GET['query'] = $que_str;
                        $query         = $que_str;
                        $exLink        = true;
                    }
                    break;
                // for tDiarySkin
                case 'tdiarydate':
                case 'categorylist':
                case 'monthlimit':
                    $tDiaryPlugin = $this->pluginCheck('tDiarySkin');
                    if ($tDiaryPlugin && isset($v_path[$i])) {
                        $_GET[$pathName] = $v_path[$i];
                        $exLink          = true;
                    }
                    break;
                case 'special':
                case $CONF['SpecialskinKey']:
                    if (isset($v_path[$i]) && is_string($v_path[$i])) {
                        global $special;
                        $special = $v_path[$i];
                        $exLink  = true;
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
                    $linkObj = array (
                        'bid'  => $blogid,
                        'name' => $pathName
                    );
                    $comp   = false;
                    $isItem = (substr($pathName, -5) === '.html');
                    // category ?
                    if (!$comp && !$cLink && !$iLink && !$isItem && !$exLink) {
                        //2007-10-06 http://japan.nucleuscms.org/bb/viewtopic.php?p=20641#20641
                        $linkObj['linkparam'] = 'category';
                        $cat_id  = $this->getRequestPathInfo($linkObj);
                        if (!empty($cat_id)) {
                            $catid = (int)$cat_id;
                            $cLink = true;
                            $comp  = true;
                        }
                    }
                    // subcategory ?
                    if (!$comp && $cLink && !$iLink && $mcategories && !$isItem && !$exLink) {
                        //2007-10-06 http://japan.nucleuscms.org/bb/viewtopic.php?p=20641#20641
                        $linkObj['linkparam'] = 'subcategory';
                        $linkObj['bid']       = $catid;
                        $subcat_id            = $this->getRequestPathInfo($linkObj);
                        if (!empty($subcat_id)) {
                            $_REQUEST[$subrequest] = (int)$subcat_id;
                            $subcatid              = (int)$subcat_id;
                            $sc                    = $i;
                        }
                    }
                    // item ?
                    if ($isItem) {
                        $linkObj['linkparam'] = 'item';
                        $item_id              = $this->getRequestPathInfo($linkObj);
                        if (!empty($item_id)) {
                            $itemid = (int)$item_id;
                            $iLink  = true;
                        }
                        if (strpos($pathName, 'page_') === 0) {
                            $iLink  = true;
                        }
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

        if ($redURI) {
            if (strpos(serverVar('REQUEST_URI'), '?') !== false) {
                list($trush, $tempQueryString) = explode('?', serverVar('REQUEST_URI'), 2);
            }
            if ($tempQueryString) {
                $temp = explode('&', $tempQueryString);
                foreach ($temp as $k => $val) {
                    if (strpos($val, "virtualpath") === 0) {
                        unset($temp[$k]);
                    }
                }
                if (!empty($temp)) {
                    $tempQueryString = '?' . join('&', $temp);
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
            if (substr(end($v_path), -5) === '.html' && !$iLink) {
                $notFound = true;
                if (isset($subcatid) && $subcatid) {
                    $linkParam = array($subrequest => $subcatid);
                    $uri = createCategoryLink($catid, $linkParam);
                } elseif (!empty($catid)) {
                    $uri = createCategoryLink($catid);
                } else {
                    $uri = createBlogidLink($blogid);
                }
            } elseif (count($v_path) > $sc && isset($subcatid) && !empty($subcatid) && !$iLink) {
                $notFound  = true;
                $linkParam = array($subrequest => $subcatid);
                $uri = createCategoryLink($catid, $linkParam);
            } elseif (count($v_path) >= 2 && (!isset($subcatid)||!$subcatid) && !$iLink) {
                $notFound = true;
                if (isset($catid)) {
                    $uri = createCategoryLink($catid);
                } else {
                    $uri = createBlogidLink($blogid);
                }
            } elseif (reset($v_path) && !$catid && (!isset($subcatid) || !$subcatid) && !$iLink) {
                $notFound = true;
                $uri = createBlogidLink($blogid);
            } else {
// Found
// setting $CONF['Self'] for other plugins
                $uri = rtrim(createBlogidLink($blogid), '/');
                $CONF['Self']           = $uri;
                $CONF['BlogURL']        = $uri;
                $CONF['ItemURL']        = $uri;
                $CONF['CategoryURL']    = $uri;
                $CONF['ArchiveURL']     = $uri;
                $CONF['ArchiveListURL'] = $uri;
                $complete               = true;
                return ;
            }
        } else {
            $uri = rtrim(createBlogidLink($blogid), '/');
            $CONF['Self']           = $uri;
            $CONF['BlogURL']        = $uri;
            $CONF['ItemURL']        = $uri;
            $CONF['CategoryURL']    = $uri;
            $CONF['ArchiveURL']     = $uri;
            $CONF['ArchiveListURL'] = $uri;
            $complete               = true;
            return ;
        }
// Behavior Not Found
        if ($notFound) {
            if ($this->getOption('customurl_notfound') == '404') {
                header('HTTP/1.1 404 Not Found');
                doError(_NO_SUCH_URI);
                exit;
            }

            header('HTTP/1.1 303 See Other');
            header('Location: ' . rtrim($uri,'/') . '/');
            exit;
        }
    }

    public function event_GenerateURL($data)
    {
        global $blogid;
        if ($data['completed']) {
            return;
        }
        $ref_data =& $data;
        unset($data);
        $data = array_merge($ref_data); // copy data to avoid contamination of the variable
        if (is_numeric($blogid)) {
            $blogid = (int)$blogid;
        } else {
            $blogid = (int)getBlogIDFromName($blogid);
        }
        $mcategories = $this->pluginCheck('MultipleCategories');
        if ($mcategories) {
            if (method_exists($mcategories, 'getRequestName')) {
                $param = array();
                $mcategories->event_PreSkinParse($param);
                global $subcatid;
            }
        }
        if (isset($subcatid) && $subcatid) {
            $subcatid = (int)$subcatid;
        }
        $OP_ArchiveKey	= $this->getOption('customurl_archive');
        $OP_ArchivesKey	= $this->getOption('customurl_archives');
        $OP_MemberKey	= $this->getOption('customurl_member');
        $params         = $data['params'];
        $useCustomURL   = $this->getAllBlogOptions('use_customurl');
        $objPath        = NULL;
        $burl           = NULL;
        switch ($data['type']) {
            case 'item':
                if (!is_numeric($params['itemid'])) {
                    return;
                }
                $item_id = (int)$params['itemid'];
                if ($item_id) {
                    $bid     = (int)getBlogIDFromItemID($item_id);
                    if ($useCustomURL[$bid] === 'no') {
                        return;
                    }
                    $path  = quickQuery(sprintf(
                            "SELECT obj_name as result FROM %s WHERE obj_param='item' AND obj_id=%d"
                            , sql_table('plug_customurl')
                            , $item_id));
                    if ($path) {
                        $objPath = $path;
                    } else {
                        if (!$this->_isValid(array('item', 'inumber', $item_id))) {
                            $objPath = _NOT_VALID_ITEM;
                        } else {
                            $y = $m = $d = $temp = '';$itime = quickQuery(sprintf(
                                    'SELECT itime as result FROM %s WHERE inumber=%d'
                                    ,sql_table('item')
                                    , $item_id
                            ));
                            sscanf($itime,'%d-%d-%d %s', $y, $m, $d, $temp);
                            $defItem   = $this->getOption('customurl_dfitem');
                            $tempParam = array(
                                'year'  => $y,
                                'month' => $m,
                                'day'   => $d
                            );
                            $ipath = sprintf(
                                    '%s_%d'
                                    , TEMPLATE::fill($defItem, $tempParam)
                                    , $item_id
                            );
                            $iname = quickQuery(sprintf(
                                'SELECT ititle as result FROM %s WHERE inumber=%d'
                                , sql_table('item')
                                , $item_id
                            ));
                            $this->RegistPath($item_id, $ipath, $bid, 'item', $iname, true);
                            $objPath = sprintf('%s.html', $ipath);
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
                if (!is_numeric($params['memberid']) || $useCustomURL[$blogid] === 'no') {
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
                            $data['url'] = $this->_generateBlogLink($blogid) . '/'
                                . _NOT_VALID_MEMBER;
                            $data['completed'] = true;
                            $ref_data = array_merge($data);
                            return;
                        } else {
                            $table = sql_table('member');
                            $mname = quickQuery(sprintf(
                                    'SELECT mname as result FROM %s WHERE mnumber=%d'
                                    , $table
                                    , $memberID
                            ));
                            $this->RegistPath($memberID, $mname, 0, 'member', $mname, true);
                            $data['url'] = sprintf(
                                    '%s/%s/%s.html'
                                    , $mname
                                , $this->_generateBlogLink($blogid)
                                , $OP_MemberKey
                            );
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
                if (!is_numeric($params['catid'])) {
                    return;
                }
                $cat_id = (int)$params['catid'];
                $bid = (int)getBlogidFromCatID($cat_id);
                if ($useCustomURL[$bid] === 'no') {
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
                if ($useCustomURL[$blogid] === 'no') {
                    return;
                }
                $objPath = $OP_ArchivesKey . '/';
                $bid = (int)$params['blogid'];
                $burl = $this->_generateBlogLink($bid);
                break;
            case 'archive':
                if ($useCustomURL[$blogid] === 'no') {
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
                $bid = (int)$params['blogid'];
                $burl = $this->_generateBlogLink($bid);
                break;
            case 'blog':
                if (!is_numeric($params['blogid'])) {
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

        $tempdeb = debug_backtrace();
        $denyPlugin = false;
        foreach($tempdeb as $k => $v){
            if (array_key_exists('class', $v)) {
                $analyzePlugin = (strtolower($v['class']) === 'np_analyze');
                $sitemapPlugin = (strtolower($v['class']) === 'np_googlesitemap' ||
                    strtolower($v['class']) === 'np_seositemaps');
                if ($analyzePlugin || $sitemapPlugin) {
                    $denyPlugin = true;
                }
            }
        }

        if (!$denyPlugin && $bid != $blogid) {
            $params['extra'] = array();
        }
        if ($objPath || $data['type'] === 'blog') {
            $LinkURI = $this->_addLinkParams($objPath, (array_key_exists('extra', $params) ? $params['extra'] : ''));
            if ($LinkURI) {
                $data['url'] = $burl . '/' . $LinkURI;
            } else {
                $data['url'] = $burl;
            }
            $isItem      = (substr($data['url'], -5, 5) === '.html');
            $isDirectory = (substr($data['url'], -1) === '/');
            $puri        = parse_url($data['url']);
            if (!$isItem && !$isDirectory && !array_key_exists('query', $puri)) {
                $data['url'] .= '/';
            }
            $data['completed'] = true;
            //$tempdeb=debug_backtrace();
            $tb = 0;
            foreach($tempdeb as $k => $v){
                if (array_key_exists('class', $v) && array_key_exists('function', $v)
                    && strtolower($v['class']) === 'np_trackback'
                    && strtolower($v['function']) === 'gettrackbackurl') {
                    $tb = 1;
                }
            }
            if ($tb == 1 && $data['type'] === 'item' && $isItem) {
                $data['url'] = substr($data['url'], 0, -5);
            }
        } else {
            $data['url'] = $this->_generateBlogLink($blogid) . '/';
            $data['completed'] = true;
        }
        if ($data['completed'])
            $ref_data = array_merge($data);
    }

    public function event_InitSkinParse($data)
    {
        global $CONF, $manager, $nucleus;
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
        }

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
            if(method_exists($data['skin'], "changeSkinByName")) {
                $data['skin']->changeSkinByName($skinName);
            } else {
                $newSkinId = SKIN::getIdFromName($skinName);
                if(method_exists($data['skin'], "SKIN")) {
                    $data['skin']->SKIN($newSkinId);
                } else {
                    $data['skin']->__construct($newSkinId);
                }
            }
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

                $feed = ob_get_contents();

                ob_end_clean();
                $eTag = sprintf('"%s"', md5($feed));
                header(sprintf('Etag: %s', $eTag));
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
                        <title><?php echo hsc($CONF['SiteName'], ENT_QUOTES)?></title>
                        <link><?php echo hsc($CONF['IndexURL'], ENT_QUOTES)?></link>
                        <description></description>
                        <docs>http://backend.userland.com/rss</docs>
                    </channel>
                </rss>
                <?php
            }
        }
        exit;
    }

    public function event_PreItem($data)
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

        $itemid   = (int)$data['item']->itemid;
        $itemblog =& $manager->getBlog(getBlogIDFromItemID($itemid));
        $blogurl  =  $itemblog->getURL();
        if (!$blogurl) {
            $b =& $manager->getBlog($CONF['DefaultBlog']);
            if (!($blogurl = $b->getURL())) {
                $blogurl = $CONF['IndexURL'];
                if ($CONF['URLMode'] !== 'pathinfo'){
                    if ($data['type'] === 'pageparser') {
                        $blogurl .= 'index.php';
                    } else {
                        $blogurl = $CONF['Self'];
                    }
                }
            }
        }
        if ($CONF['URLMode'] === 'pathinfo'){
            if (substr($blogurl, -1) === '/') {
                $blogurl = substr($blogurl, 0, -1);
            }
        }
        $CONF['BlogURL']        = $blogurl;
        $CONF['ItemURL']        = $blogurl;
        $CONF['CategoryURL']    = $blogurl;
        $CONF['ArchiveURL']     = $blogurl;
        $CONF['ArchiveListURL'] = $blogurl;
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
                if ($CONF['URLMode'] !== 'pathinfo'){
                    if ($data['type'] === 'pageparser') {
                        $blogurl .= 'index.php';
                    } else {
                        $blogurl  = $CONF['Self'];
                    }
                }
            }
        }
        if ($CONF['URLMode'] === 'pathinfo'){
            if (substr($blogurl, -1) === '/') {
                $blogurl = substr($blogurl, 0, -1);
            }
        }
        $CONF['BlogURL']        = $blogurl;
        $CONF['ItemURL']        = $blogurl;
        $CONF['CategoryURL']    = $blogurl;
        $CONF['ArchiveURL']     = $blogurl;
        $CONF['ArchiveListURL'] = $blogurl;
    }

    public function event_PostDeleteBlog ($data)
    {
        sql_query(sprintf(
                "DELETE FROM %s WHERE obj_id=%d AND obj_para='blog'"
                , sql_table('plug_customurl')
                , (int)$data['blogid']
        ));
        sql_query(sprintf(
                "DELETE FROM %s WHERE obj_bid=%d AND obj_param='item'"
                , sql_table('plug_customurl')
                , (int)$data['blogid']
        ));
        while (
            $c = sql_fetch_object(
                sql_query(
                    sprintf(
                        'SELECT catid FROM %s WHERE cblog=%d'
                        , sql_table('category')
                        , (int)$data['blogid']
                    )
                )
            )
        ) {
            sql_query(sprintf(
                    "DELETE FROM %s WHERE obj_bid=%d AND obj_param='subcategory'"
                    , sql_table('plug_customurl')
                    , (int)$c->catid
            ));
            sql_query(sprintf(
                    "DELETE FROM %s WHERE obj_id=%d AND obj_para='category'"
                    , sql_table('plug_customurl')
                    , (int)$c->catid
            ));
        }
    }

    public function event_PostDeleteCategory ($data)
    {
        sql_query(sprintf(
                "DELETE FROM %s WHERE obj_id=%d AND obj_param='category'"
                , sql_table('plug_customurl')
                , (int)$data['catid']
        ));
        sql_query(sprintf(
                "DELETE FROM %s WHERE obj_bid=%d AND obj_param='subcategory'"
                , sql_table('plug_customurl')
                , (int)$data['catid']
        ));
    }

    public function event_PostDeleteItem ($data)
    {
        sql_query(sprintf(
                "DELETE FROM %s WHERE obj_id=%d AND obj_param='item'"
                , sql_table('plug_customurl')
                , (int)$data['itemid']
        ));
    }

    public function event_PostDeleteMember ($data)
    {
        sql_query(sprintf(
                "DELETE FROM %s WHERE obj_id = %d AND obj_param = 'member'"
                , sql_table('plug_customurl')
                , (int)$data['member']->id
        ));
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
        $cat_id = (int)$data['catid'];
        if (!$data['blog']->blogid) {
            $bid   = quickQuery(sprintf(
                    'SELECT cblog as result FROM %s WHERE catid = %d'
                    , sql_table('category')
                    , $cat_id
            ));
        } else {
            $bid = $data['blog']->blogid;
        }
        if (!$data['name']) {
            $name  = quickQuery(sprintf(
                    'SELECT cname as result FROM %s WHERE catid = %d'
                    , sql_table('category')
                    , $cat_id
            ));
        } else {
            $name = $data['name'];
        }
        $bid     = (int)$bid;
        $dfcat   = $this->getOption('customurl_dfcat');
        $catpsth = $dfcat . '_' . $cat_id;
        $this->RegistPath($cat_id, $catpsth, $bid, 'category', $name, true);
        $this->setCategoryOption($cat_id, 'customurl_cname', $catpsth);
    }

    public function event_PostAddItem ($data)
    {
        $item_id = (int)$data['itemid'];
        $tpath   = requestVar('plug_custom_url_path');
        $itime   = quickQuery(sprintf(
                'SELECT itime as result FROM %s WHERE inumber = %d'
                , sql_table('item')
                , $item_id
        ));
        list($y, $m, $d, $trush) = sscanf($itime, '%d-%d-%d %s');
        $param['year']  = sprintf('%04d', $y);
        $param['month'] = sprintf('%02d', $m);
        $param['day']   = sprintf('%02d', $d);
        $ipath = TEMPLATE::fill($tpath, $param);
        $iname = quickQuery(sprintf(
                'SELECT ititle as result FROM %s WHERE inumber=%d'
                , sql_table('item')
                , $item_id
        ));
        $blog_id = (int)getBlogIDFromItemID($item_id);
        $this->RegistPath($item_id, $ipath, $blog_id, 'item', $iname, true);
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
        $tpath   = requestVar('plug_custom_url_path');
        $item_id = (int)$data['itemid'];
        $itime   = quickQuery(sprintf(
                'SELECT itime as result FROM %s WHERE inumber = %d'
                ,sql_table('item')
                , $item_id
        ));
        list($y, $m, $d, $trush) = sscanf($itime, '%d-%d-%d %s');
        $param['year']  = sprintf('%04d', $y);
        $param['month'] = sprintf('%02d', $m);
        $param['day']   = sprintf('%02d', $d);
        $ipath   = TEMPLATE::fill($tpath, $param);
        $iname   = quickQuery(sprintf(
                'SELECT ititle as result FROM %s WHERE inumber=%d'
                , sql_table('item')
                , $item_id
        ));
        $blog_id = (int)getBlogIDFromItemID($item_id);
        $this->RegistPath($item_id, $ipath, $blog_id, 'item', $iname);
        if ($this->pluginCheck('TrackBack')) {
            $this->convertLocalTrackbackURL($data);
        }
    }

    public function event_PostMoveItem($data)
    {
        sql_query(sprintf(
                "UPDATE %s SET obj_bi=%d WHERE obj_param='item' AND obj_id=%d"
                , sql_table('plug_customurl')
                , (int)$data['destblogid']
                , (int)$data['itemid']
        ));
    }

    public function event_PostMoveCategory($data)
    {
        sql_query(sprintf(
            "UPDATE %s SET obj_bid=%d WHERE obj_param='category' AND obj_id=%d"
            , sql_table('plug_customurl')
            , (int)$data['destblog']->blogid
            , (int)$data['catid']
        ));
        $items = sql_query(sprintf(
            'SELECT inumber FROM %s WHERE icat=%d'
            , sql_table('item')
            , (int)$data['catid']
        ));
        while ($oItem = sql_fetch_object($items)) {
            $odata = array(
                'destblogid' => (int)$data['destblog']->blogid,
                'itemid'     => $oItem->inumber
            );
            $this->event_PostMoveItem($odata);
        }
    }

    public function event_PrePluginOptionsUpdate($data)
    {
        $blog_option = ($data['optionname'] === 'customurl_bname');
        $cate_option = ($data['optionname'] === 'customurl_cname');
        $memb_option = ($data['optionname'] === 'customurl_mname');
        $arch_option = ($data['optionname'] === 'customurl_archive');
        $arvs_option = ($data['optionname'] === 'customurl_archives');
        $memd_option = ($data['optionname'] === 'customurl_member');
        $contextid   = (int)$data['contextid'];
        $context     = $data['context'];
        if ($blog_option || $cate_option || $memb_option) {
            $blogid = 0;
            if ($context === 'member' ) {
                global $member;
                if (!$member->isAdmin()
                    && $this->getOption('customurl_allow_edit_member_uri') !== 'yes') {
                    return;
                }
                $name   = quickQuery(sprintf(
                    'SELECT mname as result FROM %s WHERE mnumber=%d'
                    , sql_table('member')
                    , $contextid
                ));
            } elseif ($context === 'category') {
                $blogid = getBlogIDFromCatID($contextid);
                $name = quickQuery(sprintf(
                    'SELECT cname as result FROM %s WHERE catid=%d'
                    , sql_table('category')
                    , $contextid
                ));
            } else {
                $name = quickQuery(sprintf(
                    'SELECT bname as result FROM %s WHERE bnumber=%d'
                    , sql_table('blog')
                    , $contextid
                ));
            }
            $blogid = (int)$blogid;
            $msg = $this->RegistPath($contextid, $data['value'], $blogid, $context, $name);
            if ($msg) {
                $this->error($msg);
                exit;
            }
        } elseif ($arch_option || $arvs_option || $memd_option) {
            if (preg_match('/^[-_a-zA-Z0-9]+$/', $data['value'])) {
                return;
            }
            $name = substr($data['optionname'], 8);
            $msg  = array (1, _INVALID_ERROR, $name, _INVALID_MSG);
            $this->error($msg);
            exit;
        }
    }

    public function event_PrePluginOptionsEdit(&$data)
    {
        global $member;

        if ($data['context'] !== 'member' || $member->isAdmin()) {
            return;
        }
        if ($this->getOption('customurl_allow_edit_member_uri') === 'yes') {
            return;
        }

        $myid = $this->getID();
        foreach($data['options'] as $k => $v) {
            if ($v['pid'] == $myid) {
                unset($data['options'][$k]);
            }
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

// merge NP_RightURL
    public function event_PreSkinParse($data)
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
                if ($CONF['URLMode'] !== 'pathinfo'){
                    if ($data['type'] === 'pageparser') {
                        $blogurl .= 'index.php';
                    } else {
                        $blogurl  = $CONF['Self'];
                    }
                }
            }
        }
        if ($CONF['URLMode'] === 'pathinfo'){
            if (substr($blogurl, -1) === '/') {
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

    public function event_QuickMenu(&$data)
    {
        global $member;
        $quickLink   = ($this->getOption('customurl_quicklink') === 'yes');
        $memberCheck = ($member->isLoggedIn() && $member->isAdmin());
        if (!$quickLink || !$memberCheck) {
            return;
        }
        $data['options'][] = array(
            'title'   => _ADMIN_TITLE,
            'url'     => $this->getAdminURL(),
            'tooltip' => _QUICK_TIPS
        );
    }

    public function install()
    {
        include_once($this->getDirectory().'inc/install.inc.php');
    }

    public function unInstall()
    {
        if ($this->getOption('customurl_tabledel') === 'yes') {
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
        $this->deleteMemberOption('customurl_mname');
        $this->deleteCategoryOption('customurl_cname');
    }

    public function _createNewPath($type, $n_table, $id, $bids)
    {
        sql_query(parseQuery(
                "CREATE TABLE [@prefix@]plug_customurl_temp SELECT obj_id, obj_param FROM [@prefix@]plug_customurl WHERE obj_param='[@type@]'"
                , array('type'=>$type))
        );
        $table = sql_table($n_table);
        $TmpQuery = sprintf(
            'SELECT %s, %s FROM %s as ttb LEFT JOIN %s as tcu ON ttb.%s=tcu.obj_id WHERE tcu.obj_id is null'
            , $id
            , $bids
            , $table
            , sql_table('plug_customurl_temp')
            , $id
        );
        $temp = sql_query($TmpQuery);
        if ($temp) {
            while ($row = sql_fetch_array($temp)) {
                switch ($type) {
                    case 'blog':
                        $newPath = $row[$bids];
                        $blgid   = 0;
                        break;
                    case 'item':
                        $itime   = quickQuery(sprintf(
                            'SELECT itime as result FROM %s WHERE inumber=%d'
                            , $table
                            , (int)$row[$id]
                        ));
                        $param['year']  = strftime('%Y', $itime);
                        $param['month'] = strftime('%m', $itime);
                        $param['day']   = strftime('%d', $itime);
                        $newPath = sprintf(
                            '%s_%s.html'
                            , TEMPLATE::fill(
                            $this->getOption('customurl_dfitem')
                            , array($param)
                        )
                            , $row[$id]
                        );
                        $blgid = $row[$bids];
                        break;
                    case 'category':
                        //set access by (categorykey_template)_categoryid/
                        $newPath = sprintf(
                            '%s_%s'
                            , $this->getOption('customurl_dfcat')
                            , $row[$id]
                        );
                        $blgid = $row[$bids];
                        break;
                    case 'member':
                        //set access by loginName.html
                        $newPath = sprintf(
                            '%s.html'
                            , $row[$bids]);
                        $blgid   = 0;
                        break;
                    case 'subcategory':
                        //set access by (subcategorykey_template)_subcategoryid/
                        $newPath = sprintf(
                            '%s_%s'
                            , $this->getOption('customurl_dfscat')
                            , $row[$id]
                        );
                        $blgid   = $row[$bids];
                        break;
                }
                sql_query(sprintf(
                    "INSERT INTO %s (obj_param, obj_id, obj_name, obj_bid) VALUES ('%s', %d, '%s', %d)"
                    , sql_table('plug_customurl')
                    , $type
                    , (int)$row[$id]
                    , $newPath
                    , (int)$blgid
                ));
            }
        }
        $temp  = sql_query(parseQuery(
                "SELECT obj_id, obj_name FROM [@prefix@]plug_customurl WHERE obj_param='[@type@]'"
                , array('type'=>$type)
            )
        );
        while ($row = sql_fetch_array($temp)) {
            $id = (int)$row['obj_id'];
            switch ($type) {
                case 'blog':
                    $this->setBlogOption($id, 'customurl_bname', $row['obj_name']);
                    break;
                case 'category':
                    $this->setCategoryOption($id, 'customurl_cname', $row['obj_name']);
                    break;
                case 'member':
                    $this->setMemberOption(
                        $id
                        , 'customurl_mname'
                        , substr($row['obj_name'], 0, -5)
                    );
                    break;
            }
        }

        sql_query(sprintf(
                'DROP TABLE IF EXISTS %s'
                , sql_table('plug_customurl_temp')
        ));
    }

    private function pluginCheck($pluginName)
    {
        global $manager;
        if (!$manager->pluginInstalled('NP_' . $pluginName)) {
            return false;
        }
        $plugin =& $manager->getPlugin('NP_' . $pluginName);
        return $plugin;
    }

    private function goNP_ExtraSkinJP()
    {
        global $CONF, $member;
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
    private function getRequestPathInfo($linkObj)
    {
        $data = array();
        $data['name']      = sql_real_escape_string($linkObj['name']);
        $data['bid']       = sql_real_escape_string($linkObj['bid']);
        $data['linkparam'] = sql_real_escape_string($linkObj['linkparam']);

        if (!$ObjID = quickQuery(parseQuery(
                "SELECT obj_id as result FROM [@prefix@]plug_customurl WHERE obj_name='[@name@]' AND obj_bid='[@bid@]' AND obj_param='[@linkparam@]'"
                , $data
        ))) {
            return false;
        }

        return (int)$ObjID;
    }

// Receive TrackBack ping
    private function _trackback($bid, $path)
    {
        $blog_id   = (int)$bid;
        $TrackBack = $this->pluginCheck('TrackBack');
        if ($TrackBack) {
            if (substr($path, -5, 5) === '.html') {
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

            $errorMsg = $TrackBack->handlePing($tb_id);
            if ($errorMsg != '') {
                $TrackBack->xmlResponse($errorMsg);
            } else {
                $TrackBack->xmlResponse();
            }
        }
        exit;
    }

    private function _createSubCategoryLink($scid)
    {
        $scids     = $this->getParents((int)$scid);
        $subcatids = explode('/', $scids);
        $eachPath  = array();
        foreach ($subcatids as $sid) {
            $subcat_id = (int)$sid;
            $query = sprintf(
                "SELECT obj_name as result FROM %s WHERE obj_id = %d AND obj_param='subcategory'"
                , sql_table('plug_customurl')
                , $subcat_id
            );
            $path = quickQuery($query);
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
                }

                $cid    = quickQuery(sprintf(
                    'SELECT catid as result FROM %s WHERE scatid=%d'
                    , sql_table('plug_multiple_categories_sub')
                    , $subcat_id
                ));
                if (!$cid) {
                    return sprintf('no_such_subcat=%s/', $subcat_id);
                }
                $scpath = sprintf('%s_%d', $this->getOption('customurl_dfscat'), $subcat_id);
                $this->RegistPath(
                    $subcat_id
                    , $scpath
                    , $cid
                    , 'subcategory'
                    , sprintf('subcat_%d', $subcat_id)
                    , true);
                $eachPath[] = $scpath;
            }
        }
        $subcatPath = join('/', $eachPath);
        return $subcatPath . '/';
    }

    private function getParents($subid)
    {
        $mcatPlugin  = $this->pluginCheck('MultipleCategories');
        $mcatVarsion = $mcatPlugin->getVersion() * 100;
        if ((int)$mcatVarsion < 40) {
            return (int)$subid;
        }
        $subcat_id          = (int)$subid;
        $query              = 'SELECT '
            . 'scatid, '
            . 'parentid '
            . 'FROM %s '
            . 'WHERE scatid = %d';
        $query = sprintf($query, sql_table('plug_multiple_categories_sub'), $subcat_id);
        $res = sql_query($query);
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
        $cat_id = (int)$cid;
        $path = $this->getCategoryOption($cat_id, 'customurl_cname');
        if ($path) {
            return $path . '/';
        }

        $catData = array(
            'category',
            'catid',
            $cat_id
        );
        if (!$this->_isValid($catData)) {
            return $url = _NOT_VALID_CAT;
        }

        $cpath   = $this->getOption('customurl_dfcat') . '_' . $cat_id;
        $blog_id = (int)getBlogIDFromCatID($cat_id);
        $catname = sprintf('catid_%d', $cat_id);
        $this->RegistPath($cat_id, $cpath, $blog_id, 'category', $catname, true);
        return $cpath . '/';
    }

    private function _generateBlogLink($bid)
    {
        global $manager, $CONF;

        static $url = array();

        if(isset($url[$bid]))
        {
            return $url[$bid];
        }

        $blog_id = (int)$bid;
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
        if ($this->getBlogOption($blog_id, 'use_customurl') === 'yes') {
            if ($blog_id == $CONF['DefaultBlog'] && $this->getOption('customurl_incbname') === 'no') {
                if (empty($burl)) {
                    $this->_updateBlogURL($CONF['IndexURL'], $blog_id, __LINE__);
                }
                $burl = $CONF['IndexURL'];
            } else {
                if (empty($burl)) {
                    $burl = $CONF['IndexURL'];
                }
                if (substr($burl, -4) === '.php') {
                    $path = $this->getBlogOption($blog_id, 'customurl_bname');
                    file_put_contents('/var/www/vhosts/bengoshi-blog.com/httpdocs/_log/debuglog.txt', __LINE__.$path."\n", FILE_APPEND);
                    if ($path) {
                        $burl = rtrim($CONF['IndexURL'],'/').'/' . $path.'/';
                    } else {
                        $query = 'SELECT bshortname as result FROM [@prefix@]blog WHERE bnumber = [@bnumber@]';
                        $query = parseQuery($query, array('bnumber'=>$blog_id));
                        $bpath = quickQuery($query);
                        $this->RegistPath($blog_id, $bpath, 0, 'blog', $bpath, true);
                        $burl  = rtrim($CONF['IndexURL'],'/').'/' . $bpath . '/';
                    }
                    $this->_updateBlogURL($burl, $blog_id, __LINE__);
                }
            }
        }
        else
        {
            if (strlen($burl)==0) {
                $usePathInfo = ($CONF['URLMode'] === 'pathinfo');
                if ($usePathInfo) {
                    $burl = rtrim($CONF['BlogURL'],'/') . '/' . $CONF['BlogKey'] . '/' . $blog_id;
                } else {
                    $burl = $CONF['BlogURL'] . '?blogid=' . $blog_id;
                }
            }
        }
        $url[$bid] = trim($burl, '/');
        return $url[$bid];
    }

    private function _updateBlogURL($burl, $blogid, $line)
    {
        static $excuted = array();

        if(isset($excuted[$blogid])) return;

        $blogid      = (int)$blogid;
        $burl_update = 'UPDATE %s '
            . "SET    burl = '%s' "
            . 'WHERE  bnumber = %d';
        $burl        = $this->quote_smart($burl);
        $bTable      = sql_table('blog');
        sql_query(sprintf($burl_update, $bTable, $burl, $blogid));
        $excuted[$blogid] = $burl;
    }

    private function _addLinkParams($link, $params)
    {
        global $CONF;
        $arcTmp      = preg_match(sprintf('/%s/', $this->getOption('customurl_archives')), $link);
        $arcsTmp     = preg_match(sprintf('/%s/', $this->getOption('customurl_archive')), $link);
        $isArchives  = ($arcTmp || $arcsTmp);
        $mcategories = $this->pluginCheck('MultipleCategories');
        if ($mcategories) {
            $param = array();
            $mcategories->event_PreSkinParse($param);
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
            if (array_key_exists('blogid', $params)) {
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
                        if ($mcategories) {
                            $sid         = (int)$value;
                            $paramlink[] = $this->_createSubCategoryLink($sid);
                        }
                        break;
                    default:
                        $paramlink[] = $param . '/' . $value . '/';
                        break;
                }
            }
            if (substr($link, -5, 5) === '.html' || $isArchives) {
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
                if (substr($link, -1) !== '/' && !empty($link)) {
                    $link .= '/?skinid=' . $skinid;
                } else {
                    $link .= '?skinid=' . $skinid;
                }
            }
        }
        if (str_contains($link, '//')) {
            $link = preg_replace("/([^:])\/\//", "$1/", $link);
        }
        return $link;
    }

    private function URL_Callback($data, $scatFlag = '')
    {
        $l_data  = explode(",", $data[1]);
        $l_type  = $l_data[0];
        $target  = $l_data[1];
        $title   = $l_data[2];
        if (!$l_type) {
            if(!isset($this->currentItem->itemid)) return '';
            $item_id = (int)$this->currentItem->itemid;
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
                    (int)$l_type,
                    'i'
                );
            } elseif (count($link_data) == 2) {
                if ($link_data[1] === 'path') {
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
        if ($url && $target) {
            if ($title) {
                $ObjLink = sprintf('<a href="%s" title="%s">%s</a>', hsc($url), hsc($title), hsc($target));
            } else {
                $ObjLink = sprintf('<a href="%s" title="%s">%s</a>', hsc($url), hsc($target), hsc($target));
            }
        } else {
            $ObjLink = hsc($url);
        }
        return $ObjLink;
    }

    private function _isValid($data)
    {
        $data[2] = $this->quote_smart($data[2]);
        $query   = sprintf(
            'SELECT count(*) AS result FROM %s WHERE %s=%d'
            , sql_table($data[0])
            , $data[1]
            , $data[2]
        );
        $ct      = (int)quickQuery($query);
        return ($ct != 0);
    }

    private function _genarateObjectLink($data, $scatFlag = '')
    {
        global $CONF;
        $ext = substr(serverVar('REQUEST_URI'), -4);
        if ($ext === '.rdf' || $ext === '.xml') {
            $CONF['URLMode'] = 'pathinfo';
        }
        if ($CONF['URLMode'] !== 'pathinfo') {
            return '';
        }
        switch ($data[0]) {
            case 'b':
                if ($data[2] === 'n') {
                    $bid = getBlogIDFromName($data[1]);
                } else {
                    $bid = $data[1];
                }
                $blog_id = (int)$bid;
                $param = array('blog', 'bnumber', $blog_id);
                if (!$this->_isValid($param)) {
                    $url = _NOT_VALID_BLOG;
                } else {
                    $url = $this->_generateBlogLink($blog_id) . '/';
                }
                break;
            case 'c':
                if ($data[2] === 'n') {
                    $cid = getCatIDFromName($data[1]);
                } else {
                    $cid = $data[1];
                }
                $cat_id = (int)$cid;
                $param = array('category', 'catid', $cat_id);
                if (!$this->_isValid($param)) {
                    $url = _NOT_VALID_CAT;
                } else {
                    $url = createCategoryLink($cat_id);
                }
                break;
            case 's':
                $mcategories = $this->pluginCheck('MultipleCategories');
                if ($mcategories) {
                    if ($data[2] === 'n') {
                        $sque = sprintf(
                            "SELECT scatid as result FROM %s WHERE sname='%s'"
                            , sql_table('plug_multiple_categories_sub')
                            , sql_real_escape_string($data[1])
                        );
                        $scid = quickQuery($sque);
                    } else {
                        $scid = $data[1];
                    }
                    $sub_id = (int)$scid;
                    $param  = array('plug_multiple_categories_sub', 'scatid', $sub_id);
                    if (!$this->_isValid($param)) {
                        $url = _NOT_VALID_SUBCAT;
                    } else {
                        $cqe = sprintf(
                            "SELECT catid as result FROM %s WHERE scatid='%s'"
                            , sql_table('plug_multiple_categories_sub')
                            , $sub_id
                        );
                        $cid = quickQuery($cqe);
                        $cid = (int)$cid;
                        if (method_exists($mcategories, "getRequestName")) {
                            $subrequest = $mcategories->getRequestName();
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
                $param = array('item', 'inumber', (int)$data[1]);
                if (!$this->_isValid($param)) {
                    $url = _NOT_VALID_ITEM;
                } else {
                    if ($scatFlag) {
                        global $catid, $subcatid;
                        if (!empty($catid)) {
                            $linkparams['catid'] = (int)$catid;
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
                            $linkparams[$subrequest] = (int)$subcatid;
                        }
                        $url = createItemLink((int)$data[1], $linkparams);
                    } else {
                        $blink = $this->_generateBlogLink(getBlogIDFromItemID((int)$data[1]));
                        $i_query = sprintf(
                            "SELECT obj_name as result FROM %s WHERE obj_param='item' AND obj_id=%d"
                            , sql_table('plug_customurl')
                            , (int)$data[1]
                        );
                        $path = quickQuery($i_query);
                        if ($path) {
                            if ($data[2] === 'path') {
                                $url = $path;
                            } else {
                                $url = $blink . '/' . $path;
                            }
                        } else {
                            if ($data[2] === 'path') {
                                $url = $CONF['ItemKey'] . '/'
                                    . (int)$data[1];
                            } else {
                                $url = $blink . '/' . $CONF['ItemKey'] . '/'
                                    . (int)$data[1];
                            }
                        }
                    }
                }
                break;
            case 'm':
                if ($data[2] === 'n') {
                    $mque = sprintf(
                        "SELECT mnumber as result FROM %s WHERE mname='%s'"
                        , sql_table('member')
                        , sql_real_escape_string($data[1])
                    );
                    $mid = quickQuery($mque);
                } else {
                    $mid = $data[1];
                }
                $member_id = (int)$mid;
                $param = array('member', 'mnumber', $member_id);
                if (!$this->_isValid($param)) {
                    $url = _NOT_VALID_MEMBER;
                } else {
                    $url = createMemberLink($member_id);
                }
                break;
        }
        return $url;
    }

    private function getSkinContent($pageType, $skinID)
    {
        $res = sql_query(sprintf(
            'SELECT scontent FROM %s WHERE sdesc=%d AND stype=%d'
            , sql_table('skin')
            , (int)$skinID
            , sql_real_escape_string($pageType)
        ));

        if ($res && ($obj = sql_fetch_object($res)))
            return $obj->scontent;
        return '';
    }

    private function createItemForm($item_id = 0)
    {
        if ($item_id) {
            $item_id = (int)$item_id;
            $res = quickQuery(sprintf(
                "SELECT obj_name as result FROM %s WHERE obj_param='item' AND obj_id=%d"
                , sql_table('plug_customurl')
                , $item_id
            ));
            $ipath = substr($res, 0, -5);
        } else {
            $ipath = $this->getOption('customurl_dfitem');
        }
        echo <<< OUTPUT
<h3>Custom URL</h3>
<p>
<label for="plug_custom_url">Custom Path:</label>
<input id="plug_custom_url" name="plug_custom_url_path" value="{$ipath}" />
</p>
OUTPUT;
    }

    private function RegistPath($objID, $path, $bid, $oParam, $name, $new = false )
    {
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
                return '';
        }
        $bid   = (int)$bid;
        $objID = (int)$objID;
        $name  = rawurlencode($name);
        $msg = '';

        if ($new && $oParam === 'item') {
            $itime = quickQuery(sprintf(
                'SELECT itime as result FROM %s WHERE inumber = %d'
                ,sql_table('item')
                , $objID
            ));
            $param['year']  = strftime('%Y', $itime);
            $param['month'] = strftime('%m', $itime);
            $param['day']   = strftime('%d', $itime);
            $ikey = TEMPLATE::fill($this->getOption('customurl_dfitem'), $param);
            if ($path == $ikey) {
                $path = sprintf('%s_%d', $ikey, $objID);
            }
        } elseif (!$new && $path == '') {
            sql_query(sprintf(
                "DELETE FROM %s WHERE obj_id=%d AND obj_param='%s'"
                , sql_table('plug_customurl')
                , $objID
                , $oParam
            ));
            $msg = array (0, _DELETE_PATH, $name, _DELETE_MSG);
            return $msg;
        }

        $dotslash = array ('.', '/');
        $path     = str_replace ($dotslash, '_', $path);
        if (!preg_match('/^[-_a-zA-Z0-9]+$/', $path)) {
            $msg = array (1, _INVALID_ERROR, $name, _INVALID_MSG);
            return $msg;
        }

        $tempPath = $path;
        if (in_array($oParam,  array('item','member'))) {
            $tempPath .= '.html';
        }
        $res = sql_query(sprintf(
                "SELECT obj_id FROM %s WHERE obj_name='%s' AND obj_bid=%d AND obj_param='%s' AND    obj_id!=%d"
                , sql_table('plug_customurl')
                , $tempPath
                , $bid
                , $oParam
                , $objID
        ));
        if ($res && ($obj = sql_fetch_object($res))) {
            $msg   = array (0, _CONFLICT_ERROR, $name, _CONFLICT_MSG);
            $path .= '_'.$objID;
        }
        if ($oParam === 'category' && !$msg) {
            $res = sql_query(sprintf(
                    "SELECT obj_id FROM %s WHERE obj_name='%s' AND obj_param='blog'"
                    , sql_table('plug_customurl')
                    , $tempPath
            ));
            if ($res && ($obj = sql_fetch_object($res))) {
                $msg   = array (0, _CONFLICT_ERROR, $name, _CONFLICT_MSG);
                $path .= '_'.$objID;
            }
        }
        if ($oParam === 'blog' && !$msg) {
            $res = sql_query(sprintf(
                    "SELECT obj_id FROM %s WHERE obj_name='%s' AND obj_param='category'"
                    , sql_table('plug_customurl')
                    , $tempPath
            ));
            if ($res && ($obj = sql_fetch_object($res))) {
                $msg   = array (0, _CONFLICT_ERROR, $name, _CONFLICT_MSG);
                $path .= '_'.$objID;
            }
        }

        $newPath = $path;
        if (in_array($oParam, array('item', 'member'))) {
            $newPath .= '.html';
        }
        $res = sql_query(sprintf("SELECT * FROM %s WHERE obj_id=%d AND obj_param='%s'"
            , sql_table('plug_customurl')
            , $objID
            , $oParam
        ));
        if ($res && ($row = sql_fetch_object($res)) && !empty($row)) {
            $pathID = $row->id;
            sql_free_result($res);
            sql_query(sprintf(
                    "UPDATE %s SET obj_name='%s' WHERE id=%d"
                    , sql_table('plug_customurl')
                    , $newPath
                    , $pathID
            ));
        } else {
            sql_query(sprintf(
                    "INSERT INTO %s (obj_param, obj_name,obj_id,obj_bid) VALUES ('%s','%s',%d,%d)"
                    , sql_table('plug_customurl')
                    , $oParam
                    , $newPath
                    , $objID
                    , $bid
            ));
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

    private function quote_smart($value)
    {
        if (get_magic_quotes_gpc()) $value = stripslashes($value);
        if (!is_numeric($value)) {
            $value = sql_real_escape_string($value);
        } else {
            $value = (int)$value;
        }
        return $value;
    }

    private function convertLocalTrackbackURL($data)
    {
        global $CONF;
        $ping_urls_count = 0;
        $ping_urls       = array();
        $localflag       = array();
        $ping_url        = requestVar('trackback_ping_url');
        if (trim($ping_url)) {
            $ping_urlsTemp = preg_split("/[\s,]+/", trim($ping_url));
            foreach ($ping_urlsTemp as $iValue) {
                $ping_urls[] = trim($iValue);
                $ping_urls_count++;
            }
        }
        $iMax = intRequestVar('tb_url_amount');
        for ($i=0; $i < $iMax; $i++) {
            if (requestVar(sprintf('tb_url_%d', $i))) {
                $ping_urls[$ping_urls_count] = requestVar(sprintf('tb_url_%d', $i));
                if (requestVar('tb_url_' . $i . '_local') === 'on') {
                    $localflag[$ping_urls_count] = 1;
                } else {
                    $localflag[$ping_urls_count] = 0;
                }
                $ping_urls_count++;
            }
        }
        if ($ping_urls_count <= 0) {
            return;
        }
        $blog_id = getBlogIDFromItemID((int)$data['itemid']);
        for ($i=0, $iMax = count($ping_urls); $i < $iMax; $i++) {
            if($localflag[$i]) {
                $tmp_url         = parse_url($ping_urls[$i]);
                $tmp_url['path'] = trim($tmp_url['path'], '/');
                $path_arr        = explode("/", $tmp_url['path']);
                $tail            = end($path_arr);
                $linkObj         = array (
                    'linkparam' => 'item',
                    'bid'       => $blog_id,
                );
                if (substr($tail, -10) === '.trackback') {
                    $pathName = substr($tail, 0, -10);
                    if (substr($pathName, -5) === '.html') {
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
        $_REQUEST['trackback_ping_url'] = join ("\n", $ping_urls);
    }
}

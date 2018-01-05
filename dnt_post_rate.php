<?php

/**
 * Post Rates
 * Plugin for MyBB 1.8.x series
 * contact: neogeoman@gmail.com
 * Website: https://soportemybb.es
 * Author:  Whiteneo
 */
 
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'dnt_post_rate.php');
require_once "./global.php";

if($mybb->settings['dnt_post_rate_active'] == 0)
{
	return false;
}

$templatelist .= "dnt_prt_page,dnt_prt_content,dnt_prt_list,dnt_prt_list_none,dnt_prt_list_rate1,dnt_prt_list_rate2,dnt_prt_list_rate3,dnt_prt_list_rate4,dnt_prt_list_rate5,dnt_prt_list_rate6";

if(!empty($mybb->settings['dnt_post_rate_groups']) && $mybb->settings['dnt_post_rate_groups'] != "-1")
{
	$gid = (int)$mybb->user['usergroup'];
	if($mybb->user['additionalgroups'])
		$gids = "{$gid}, {$mybb->user['additionalgroups']}";
	
	$dnt_prt_gids = explode(",",$mybb->settings['dnt_post_rate_groups']);
	
	if(!empty($gids))
	{
		$gids = explode(",",$gids);
		foreach($gids as $gid)
		{
			if(!in_array($gid, $dnt_prt_gids))
				return false;
		}
	}
	else
	{
		if(!in_array($gid, $dnt_prt_gids))
			return false;		
	}		
}

$lang->load('dnt_post_rate',false,true);
$plugins->run_hooks("dnt_post_rate_start");

//add_breadcrumb($lang->dnt_prt_rates_page_title, THIS_SCRIPT);

if($mybb->input['action'] == "get_thread_rates")
{
	if(!isset($mybb->input['lid']) && !isset($mybb->input['tid']))
	{
		error($lang->dnt_prt_not_received, $lang->dnt_prt_error_title);
	}	
	// get forums user cannot view
	$unviewable = get_unviewable_forums(true);
	$uwp = "";
	if(isset($fids) && !empty($fids) && !empty($unviewable)){
	$unviewable .= "," . $fids;
	}	
	elseif(isset($fids) && empty($unviewable)){
	$unviewable .= $fids;
	}
	if($unviewable)
	{
		$unviewwhere = " AND t.fid NOT IN ($unviewable)";
	}	
	if(!$mybb->user['ismoderator'])
	{		
		$unviewwhere .= " AND t.visible='1'";
	}
	
	if($mybb->input['lid'] == "all")
	{
		$sql_req = "dnt_prt_type>0";
	}
	else
	{
		$mybb->input['lid'] = (int)$mybb->input['lid'];
		$mybb->input['lid'] = $db->escape_string($mybb->input['lid']);
		$sql_req = "dnt_prt_type=".$mybb->input['lid'];
	}
	$mybb->input['tid'] = (int)$mybb->input['tid'];
	$mybb->input['tid'] = $db->escape_string($mybb->input['tid']);
	$sql_req .= " AND dnt_prt_tid=".$mybb->input['tid'];
		
	if(isset($mybb->input['pid']))
	{
		$mybb->input['pid'] = (int)$mybb->input['pid'];
		$mybb->input['pid'] = $db->escape_string($mybb->input['pid']);
		$sql_req .= " AND dnt_prt_pid=".$mybb->input['pid'];
	}
	$limit_search = (int)$mybb->settings['dnt_post_rate_limit'];	
	$dnt_prt_date_limit = time() - ($limit_search * 60 * 60 * 24);
	if($limit_search > 0)
		$dnt_prt_date = " AND dnt_prt_date>='{$dnt_prt_date_limit}'";
	else
		$dnt_prt_date = "";
	if($mybb->settings['dnt_post_rate_only_firspost'] == 1)
		$uwp = " AND t.firstpost=dp.dnt_prt_pid";
	
	$query = $db->simple_select("dnt_post_rate", "*", "{$sql_req}{$dnt_prt_date}", array("limit" => 1));
		
	$dnt_prt_rows = $db->fetch_array($query);
	$db->free_result($query);

	if(!$dnt_prt_rows)
	{
		$lang->dnt_prt_not_received = $lang->dnt_prt_thread_dont_received;
		eval("\$dnt_prt_list = \"".$templates->get("dnt_prt_list_none")."\";");
	}
	
	$lid = (int)$mybb->input['lid'];
	$tid = (int)$mybb->input['tid'];
	$pid = (int)$mybb->input['pid'];
	if(isset($mybb->input['pid']))	
		add_breadcrumb($lang->dnt_prt_rates_page_title, THIS_SCRIPT."?action=get_thread_rates&amp;lid={$lid}&amp;dnt_prt_tid={$tid}&amp;dnt_prt_pid={$pid}");
	else
		add_breadcrumb($lang->dnt_prt_rates_page_title, THIS_SCRIPT."?action=get_thread_rates&amp;lid={$lid}&amp;dnt_prt_tid={$tid}");
		
	$page = (int)$mybb->input['page'];
	if($page < 1) $page = 1;
	//$numtot = $db->fetch_field($db->simple_select('dnt_post_rate', 'COUNT(*) AS numtot', $sql_req.$dnt_prt_date), 'numtot');
	$numtot = $db->query("SELECT COUNT(*) AS numtot, dp.*, t.fid, t.tid, t.visible, t.firstpost FROM ".TABLE_PREFIX."dnt_post_rate dp
		LEFT JOIN ".TABLE_PREFIX."threads t
		ON (dp.dnt_prt_tid=t.tid)
		WHERE {$sql_req}{$dnt_prt_date}{$unviewwhere}{$uwp}");
	$numtot = $db->fetch_field($numtot,'numtot');
	$perpage = (int)$mybb->settings['dnt_post_rate_limit_page'];
	if($perpage == 0)
		$perpage = 20;
	$items_founded = (int)$numtot;
	if($mybb->settings['dnt_post_rate_showthread_all'] == 1)
		$lang->dnt_prt_rates = $lang->sprintf($lang->dnt_prt_rates_thread, $items_founded);
	else
		$lang->dnt_prt_rates = $lang->sprintf($lang->dnt_prt_rates_post, $items_founded);		
	
	if(isset($mybb->input['pid']))
		$multipage = multipage($numtot, $perpage, $page, $_SERVER['PHP_SELF']."?action=get_thread_rates&amp;lid={$lid}&amp;dnt_prt_tid={$tid}&amp;dnt_prt_pid={$pid}");
	else
		$multipage = multipage($numtot, $perpage, $page, $_SERVER['PHP_SELF']."?action=get_thread_rates&amp;lid={$lid}&amp;dnt_prt_tid={$tid}");
		
	$query = $db->query("SELECT dp.*, u.*, ru.uid as ruid, ru.username as runame, ru.usergroup as rug, ru.displaygroup as rudg, t.fid, t.tid, t.visible, t.firstpost, p.subject, p.pid FROM ".TABLE_PREFIX."dnt_post_rate dp
		LEFT JOIN ".TABLE_PREFIX."users u
		ON (dp.dnt_prt_sender=u.uid)
		LEFT JOIN ".TABLE_PREFIX."users ru
		ON (dp.dnt_prt_user=ru.uid)
		LEFT JOIN ".TABLE_PREFIX."threads t
		ON (dp.dnt_prt_tid=t.tid)			
		LEFT JOIN ".TABLE_PREFIX."posts p
		ON (dp.dnt_prt_pid=p.pid)	
		WHERE {$sql_req}{$dnt_prt_date}{$unviewwhere}{$uwp}
		ORDER BY dnt_prt_date DESC
		LIMIT ".(($page-1)*$perpage).", {$perpage}		
	");

	$dnt_prt_list = '';
	$itemno = 0;
	
	while($dnt_prt_rows = $db->fetch_array($query))
	{
		$trow = alt_trow();
		$dnt_prt_rows['tid'] = (int)$dnt_prt_rows['tid'];
        $dnt_prt_rows['pid'] = (int)$dnt_prt_rows['pid'];
		
		if($dnt_prt_rows['avatar'] != "")
			$dnt_prt_rows['avatar'] = "<img src=".htmlspecialchars_uni($dnt_prt_rows['avatar'])." class=\"dnt_prt_list_avatar\" alt=\"avatar\" />";
		else
			$dnt_prt_rows['avatar'] = '<img src="images/default_avatar.png" class="dnt_prt_list_avatar" alt="no avatar" />';
		
		if($dnt_prt_rows['dnt_prt_sender'] > 0)
		{
			if($dnt_prt_rows['dnt_prt_sender'] == $mybb->user['uid'])
			{
				$dnt_prt_rows['username'] = $lang->dnt_prt_you;
				$dnt_prt_rows['username'] = format_name($dnt_prt_rows['username'], $mybb->user['usergroup'], $mybb->user['displaygroup']);
				$dnt_prt_rows['username'] = build_profile_link($dnt_prt_rows['username'], $mybb->user['uid']);
			}
			else
			{
				$dnt_prt_rows['username'] = htmlspecialchars_uni($dnt_prt_rows['username']);
				$dnt_prt_rows['username'] = format_name($dnt_prt_rows['username'], $dnt_prt_rows['usergroup'], $dnt_prt_rows['displaygroup']);
				$dnt_prt_rows['username'] = build_profile_link($dnt_prt_rows['username'], $dnt_prt_rows['uid']);
			}
		}
		else
			$dnt_prt_rows['username'] = $lang->guest;		

		$dnt_prt_rows['date'] = my_date($mybb->settings['dateformat'], $dnt_prt_rows['dnt_prt_date']);
		$dnt_prt_rows['time'] = my_date($mybb->settings['timeformat'], $dnt_prt_rows['dnt_prt_date']);
		
		$dnt_prt_rows['dnt_prt_type'] = (int)$dnt_prt_rows['dnt_prt_type'];
		if($dnt_prt_rows['dnt_prt_type'] == 1)
			eval("\$dnt_prt_rows['rate'] = \"".$templates->get("dnt_prt_list_rate1")."\";");
		if($dnt_prt_rows['dnt_prt_type'] == 2)
			eval("\$dnt_prt_rows['rate'] = \"".$templates->get("dnt_prt_list_rate2")."\";");			
		if($dnt_prt_rows['dnt_prt_type'] == 3)
			eval("\$dnt_prt_rows['rate'] = \"".$templates->get("dnt_prt_list_rate3")."\";");			
		if($dnt_prt_rows['dnt_prt_type'] == 4)
			eval("\$dnt_prt_rows['rate'] = \"".$templates->get("dnt_prt_list_rate4")."\";");			
		if($dnt_prt_rows['dnt_prt_type'] == 5)
			eval("\$dnt_prt_rows['rate'] = \"".$templates->get("dnt_prt_list_rate5")."\";");			
		if($dnt_prt_rows['dnt_prt_type'] == 6)
			eval("\$dnt_prt_rows['rate'] = \"".$templates->get("dnt_prt_list_rate6")."\";");			
			
		$dnt_prt_rows['subject'] = htmlspecialchars_uni($dnt_prt_rows['subject']);
		/*if(my_strlen($dnt_prt_rows['subject']) > 25)
		{
			$dnt_prt_rows['subject'] = my_substr($dnt_prt_rows['subject'], 0, 25)."...";
		}*/
		if($mybb->settings['dnt_post_rate_showthread_all'] == 1)
			$dnt_prt_rows['url'] = $mybb->settings['bburl'] ."/". get_thread_link($dnt_prt_rows['tid']);			
		else	
			$dnt_prt_rows['url'] = $mybb->settings['bburl'] ."/". get_post_link($dnt_prt_rows['pid'], $dnt_prt_rows['tid']) . "#pid" . $dnt_prt_rows['pid'];
		if($dnt_prt_rows['dnt_prt_user'] > 0)
		{
			if($dnt_prt_rows['dnt_prt_user'] == $mybb->user['uid'])
			{
				$dnt_prt_rows['runame'] = $lang->dnt_prt_you;
				$dnt_prt_rows['runame'] = format_name($dnt_prt_rows['runame'], $mybb->user['usergroup'], $mybb->user['displaygroup']);
				$dnt_prt_rows['runame'] = build_profile_link($dnt_prt_rows['runame'], $mybb->user['uid']);
			}			
			else
			{
				$dnt_prt_rows['runame'] = htmlspecialchars_uni($dnt_prt_rows['runame']);
				$dnt_prt_rows['runame'] = format_name($dnt_prt_rows['runame'], $dnt_prt_rows['rug'], $dnt_prt_rows['rdg']);
				$dnt_prt_rows['runame'] = build_profile_link($dnt_prt_rows['runame'], $dnt_prt_rows['ruid']);
			}
		}
		else 		
			$dnt_prt_rows['runame'] = $lang->guest;			

		$dnt_prt_rows['rate_to'] = $lang->sprintf($lang->dnt_prt_has_rated_you, $dnt_prt_rows['runame'], $dnt_prt_rows['rate'], $dnt_prt_rows['url'], $dnt_prt_rows['subject'], $dnt_prt_rows['date'], $dnt_prt_rows['time']);
			
		eval("\$dnt_prt_list .= \"".$templates->get("dnt_prt_list")."\";");
	}

	$db->free_result($query);

	if(!$dnt_prt_list)
	{
		$lang->dnt_prt_not_received = $lang->dnt_prt_thread_dont_received;
		eval("\$dnt_prt_list = \"".$templates->get("dnt_prt_list_none")."\";");
	}

	eval("\$content = \"".$templates->get("dnt_prt_content")."\";");
	eval("\$page = \"".$templates->get("dnt_prt_page")."\";");
	$plugins->run_hooks("dnt_post_rate_end");
	output_page($page);
	exit;
}
else if($mybb->input['action'] == "get_received_rates")
{
	if(!isset($mybb->input['uid']))
	{
		error($lang->dnt_prt_not_received, $lang->dnt_prt_error_title);
	}
	// get forums user cannot view
	$unviewable = get_unviewable_forums(true);	
	$uwp = "";	
	if(isset($fids) && !empty($fids) && !empty($unviewable)){
	$unviewable .= "," . $fids;
	}	
	elseif(isset($fids) && empty($unviewable)){
	$unviewable .= $fids;
	}
	if($unviewable)
	{
		$unviewwhere = " AND t.fid NOT IN ($unviewable)";
	}	
	if(!$mybb->user['ismoderator'])
	{
		$unviewwhere .= " AND t.visible='1'";
	}
	
	$mybb->input['uid'] = (int)$mybb->input['uid'];
	$mybb->input['uid'] = $db->escape_string($mybb->input['uid']);
	$sql_req = "dnt_prt_user=".$mybb->input['uid'];

	$limit_search = (int)$mybb->settings['dnt_post_rate_limit'];	
	$dnt_prt_date_limit = time() - ($limit_search * 60 * 60 * 24);
	if($limit_search > 0)
		$dnt_prt_date = " AND dnt_prt_date>='{$dnt_prt_date_limit}'";
	else
		$dnt_prt_date = "";
	if($mybb->settings['dnt_post_rate_only_firspost'] == 1)
		$uwp = " AND t.firstpost=dp.dnt_prt_pid";
		
	$query = $db->simple_select("dnt_post_rate", "*", "{$sql_req}{$dnt_prt_date}", array("limit" => 1));
		
	$dnt_prt_rows = $db->fetch_array($query);
	$db->free_result($query);

	if(!$dnt_prt_rows)
	{
		$lang->dnt_prt_not_received = $lang->dnt_prt_dont_received;
		eval("\$dnt_prt_list = \"".$templates->get("dnt_prt_list_none")."\";");
	}
	
	$lid = (int)$mybb->input['lid'];
	$tid = (int)$mybb->input['tid'];
	$pid = (int)$mybb->input['pid'];

	add_breadcrumb($lang->dnt_prt_rates_page_title, THIS_SCRIPT."?action=get_received_rates&amp;uid={$mybb->input['uid']}");
		
	$page = (int)$mybb->input['page'];
	if($page < 1) $page = 1;
	//$numtot = $db->fetch_field($db->simple_select('dnt_post_rate', 'COUNT(*) AS numtot', $sql_req.$dnt_prt_date), 'numtot');
	$numtot = $db->query("SELECT COUNT(*) AS numtot, dp.*, t.fid, t.tid, t.visible FROM ".TABLE_PREFIX."dnt_post_rate dp
		LEFT JOIN ".TABLE_PREFIX."threads t
		ON (dp.dnt_prt_tid=t.tid)			
		WHERE {$sql_req}{$dnt_prt_date}{$unviewwhere}{$uwp}");
	$numtot = $db->fetch_field($numtot,'numtot');	
	$perpage = (int)$mybb->settings['dnt_post_rate_limit_page'];
	if($perpage == 0)
		$perpage = 20;	
	$items_founded = (int)$numtot;
	$lang->dnt_prt_rates = $lang->sprintf($lang->dnt_prt_rates_received, $items_founded);
	
	$multipage = multipage($numtot, $perpage, $page, $_SERVER['PHP_SELF']."?action=get_received_rates&amp;uid={$mybb->input['uid']}");
		
	$query = $db->query("SELECT dp.*, u.*, ru.username as runame, ru.uid as ruid, ru.usergroup as rug, ru.displaygroup as rudg, t.fid, t.tid, t.visible, p.subject, p.pid FROM ".TABLE_PREFIX."dnt_post_rate dp
		LEFT JOIN ".TABLE_PREFIX."users u
		ON (dp.dnt_prt_sender=u.uid)
		LEFT JOIN ".TABLE_PREFIX."users ru
		ON (dp.dnt_prt_user=ru.uid)		
		LEFT JOIN ".TABLE_PREFIX."threads t
		ON (dp.dnt_prt_tid=t.tid)			
		LEFT JOIN ".TABLE_PREFIX."posts p
		ON (dp.dnt_prt_pid=p.pid)	
		WHERE {$sql_req}{$dnt_prt_date}{$unviewwhere}{$uwp}
		ORDER BY dnt_prt_date DESC
		LIMIT ".(($page-1)*$perpage).", {$perpage}		
	");

	$dnt_prt_list = '';

	while($dnt_prt_rows = $db->fetch_array($query))
	{
		$trow = alt_trow();
		$dnt_prt_rows['tid'] = (int)$dnt_prt_rows['tid'];
        $dnt_prt_rows['pid'] = (int)$dnt_prt_rows['pid'];
		
		if($dnt_prt_rows['avatar'] != "")
			$dnt_prt_rows['avatar'] = "<img src=".htmlspecialchars_uni($dnt_prt_rows['avatar'])." class=\"dnt_prt_list_avatar\" alt=\"avatar\" />";
		else
			$dnt_prt_rows['avatar'] = '<img src="images/default_avatar.png" class="dnt_prt_list_avatar" alt="no avatar" />';
		
		if($dnt_prt_rows['dnt_prt_sender'] > 0)
		{
			if($dnt_prt_rows['dnt_prt_sender'] == $mybb->user['uid'])
			{
				$dnt_prt_rows['username'] = $lang->dnt_prt_you;
				$dnt_prt_rows['username'] = format_name($dnt_prt_rows['username'], $mybb->user['usergroup'], $mybb->user['displaygroup']);
				$dnt_prt_rows['username'] = build_profile_link($dnt_prt_rows['username'], $mybb->user['uid']);
			}
			else
			{
				$dnt_prt_rows['username'] = htmlspecialchars_uni($dnt_prt_rows['username']);
				$dnt_prt_rows['username'] = format_name($dnt_prt_rows['username'], $dnt_prt_rows['usergroup'], $dnt_prt_rows['displaygroup']);
				$dnt_prt_rows['username'] = build_profile_link($dnt_prt_rows['username'], $dnt_prt_rows['uid']);
			}
		}
		else
			$dnt_prt_rows['username'] = $lang->guest;		

		$dnt_prt_rows['date'] = my_date($mybb->settings['dateformat'], $dnt_prt_rows['dnt_prt_date']);
		$dnt_prt_rows['time'] = my_date($mybb->settings['timeformat'], $dnt_prt_rows['dnt_prt_date']);
		
		$dnt_prt_rows['dnt_prt_type'] = (int)$dnt_prt_rows['dnt_prt_type'];
		if($dnt_prt_rows['dnt_prt_type'] == 1)
			eval("\$dnt_prt_rows['rate'] = \"".$templates->get("dnt_prt_list_rate1")."\";");
		if($dnt_prt_rows['dnt_prt_type'] == 2)
			eval("\$dnt_prt_rows['rate'] = \"".$templates->get("dnt_prt_list_rate2")."\";");			
		if($dnt_prt_rows['dnt_prt_type'] == 3)
			eval("\$dnt_prt_rows['rate'] = \"".$templates->get("dnt_prt_list_rate3")."\";");			
		if($dnt_prt_rows['dnt_prt_type'] == 4)
			eval("\$dnt_prt_rows['rate'] = \"".$templates->get("dnt_prt_list_rate4")."\";");			
		if($dnt_prt_rows['dnt_prt_type'] == 5)
			eval("\$dnt_prt_rows['rate'] = \"".$templates->get("dnt_prt_list_rate5")."\";");			
		if($dnt_prt_rows['dnt_prt_type'] == 6)
			eval("\$dnt_prt_rows['rate'] = \"".$templates->get("dnt_prt_list_rate6")."\";");			
			
		$dnt_prt_rows['subject'] = htmlspecialchars_uni($dnt_prt_rows['subject']);
		/*if(my_strlen($dnt_prt_rows['subject']) > 25)
		{
			$dnt_prt_rows['subject'] = my_substr($dnt_prt_rows['subject'], 0, 25)."...";
		}*/
		$dnt_prt_rows['url'] = $mybb->settings['bburl'] ."/". get_post_link($dnt_prt_rows['pid'], $dnt_prt_rows['tid']) . "#pid" . $dnt_prt_rows['pid'];
		if($dnt_prt_rows['dnt_prt_user'] > 0)
		{		
			if($dnt_prt_rows['ruid'] == $mybb->user['uid'])
			{
				$dnt_prt_rows['runame'] = $lang->dnt_prt_you;
				$dnt_prt_rows['runame'] = format_name($dnt_prt_rows['runame'], $mybb->user['usergroup'], $mybb->user['displaygroup']);
				$dnt_prt_rows['runame'] = build_profile_link($dnt_prt_rows['runame'], $mybb->user['uid']);
			}			
			else
			{
				$dnt_prt_rows['runame'] = htmlspecialchars_uni($dnt_prt_rows['runame']);
				$dnt_prt_rows['runame'] = format_name($dnt_prt_rows['runame'], $dnt_prt_rows['rug'], $dnt_prt_rows['rdg']);
				$dnt_prt_rows['runame'] = build_profile_link($dnt_prt_rows['runame'], $dnt_prt_rows['ruid']);
			}
		}
		else		
			$dnt_prt_rows['runame'] = $lang->guest;

		$dnt_prt_rows['rate_to'] = $lang->sprintf($lang->dnt_prt_has_rated_you, $dnt_prt_rows['runame'], $dnt_prt_rows['rate'], $dnt_prt_rows['url'], $dnt_prt_rows['subject'], $dnt_prt_rows['date'], $dnt_prt_rows['time']);

		eval("\$dnt_prt_list .= \"".$templates->get("dnt_prt_list")."\";");
	}

	$db->free_result($query);

	if(!$dnt_prt_list)
	{
		$lang->dnt_prt_not_received = $lang->dnt_prt_dont_received;
		eval("\$dnt_prt_list = \"".$templates->get("dnt_prt_list_none")."\";");
	}

	eval("\$content = \"".$templates->get("dnt_prt_content")."\";");
	eval("\$page = \"".$templates->get("dnt_prt_page")."\";");
	$plugins->run_hooks("dnt_post_rate_end");
	output_page($page);
	exit;
}
else if($mybb->input['action'] == "get_given_rates")
{
	if(!isset($mybb->input['uid']))
	{
		error($lang->dnt_prt_not_received, $lang->dnt_prt_error_title);
	}
	// get forums user cannot view
	$unviewable = get_unviewable_forums(true);	
	$uwp = "";	
	if(isset($fids) && !empty($fids) && !empty($unviewable)){
	$unviewable .= "," . $fids;
	}	
	elseif(isset($fids) && empty($unviewable)){
	$unviewable .= $fids;
	}
	if($unviewable)
	{
		$unviewwhere = " AND t.fid NOT IN ($unviewable)";
	}	
	if(!$mybb->user['ismoderator'])
	{
		$unviewwhere .= " AND t.visible='1'";
	}
	
	$mybb->input['uid'] = (int)$mybb->input['uid'];
	$mybb->input['uid'] = $db->escape_string($mybb->input['uid']);
	$sql_req = "dnt_prt_sender=".$mybb->input['uid'];

	$limit_search = (int)$mybb->settings['dnt_post_rate_limit'];	
	$dnt_prt_date_limit = time() - ($limit_search * 60 * 60 * 24);
	if($limit_search > 0)
		$dnt_prt_date = " AND dnt_prt_date>='{$dnt_prt_date_limit}'";
	else
		$dnt_prt_date = "";
	if($mybb->settings['dnt_post_rate_only_firspost'] == 1)
		$uwp = " AND t.firstpost=dp.dnt_prt_pid";
	
	$query = $db->simple_select("dnt_post_rate", "*", "{$sql_req}{$dnt_prt_date}", array("limit" => 1));
		
	$dnt_prt_rows = $db->fetch_array($query);
	$db->free_result($query);

	if(!$dnt_prt_rows)
	{
		$lang->dnt_prt_not_received = $lang->dnt_prt_dont_given;		
		eval("\$dnt_prt_list = \"".$templates->get("dnt_prt_list_none")."\";");
	}
	
	$lid = (int)$mybb->input['lid'];
	$tid = (int)$mybb->input['tid'];
	$pid = (int)$mybb->input['pid'];

	add_breadcrumb($lang->dnt_prt_rates_page_title, THIS_SCRIPT."?action=get_given_rates&amp;uid={$mybb->input['uid']}");
		
	$page = (int)$mybb->input['page'];
	if($page < 1) $page = 1;
	//$numtot = $db->fetch_field($db->simple_select('dnt_post_rate', 'COUNT(*) AS numtot', $sql_req.$dnt_prt_date), 'numtot');
	$numtot = $db->query("SELECT COUNT(*) AS numtot, dp.*, t.fid, t.tid, t.visible,t.firstpost FROM ".TABLE_PREFIX."dnt_post_rate dp
		LEFT JOIN ".TABLE_PREFIX."threads t
		ON (dp.dnt_prt_tid=t.tid)
		WHERE {$sql_req}{$dnt_prt_date}{$unviewwhere}{$uwp}");
	$numtot = $db->fetch_field($numtot,'numtot');	
	$perpage = (int)$mybb->settings['dnt_post_rate_limit_page'];
	if($perpage == 0)
		$perpage = 20;	
	$items_founded = (int)$numtot;
	$lang->dnt_prt_rates = $lang->sprintf($lang->dnt_prt_rates_given, $items_founded);
	
	$multipage = multipage($numtot, $perpage, $page, $_SERVER['PHP_SELF']."?action=get_given_rates&amp;uid={$mybb->input['uid']}");
		
	$query = $db->query("SELECT dp.*, u.*, ru.username as runame, ru.usergroup as rug, ru.displaygroup as rudg, ru.uid as ruid, t.fid, t.tid, t.visible, t.firstpost, p.subject, p.pid FROM ".TABLE_PREFIX."dnt_post_rate dp
		LEFT JOIN ".TABLE_PREFIX."users u
		ON (dp.dnt_prt_sender=u.uid)
		LEFT JOIN ".TABLE_PREFIX."users ru
		ON (dp.dnt_prt_user=ru.uid)
		LEFT JOIN ".TABLE_PREFIX."threads t
		ON (dp.dnt_prt_tid=t.tid)			
		LEFT JOIN ".TABLE_PREFIX."posts p
		ON (dp.dnt_prt_pid=p.pid)	
		WHERE {$sql_req}{$dnt_prt_date}{$unviewwhere}{$uwp}
		ORDER BY dnt_prt_date DESC
		LIMIT ".(($page-1)*$perpage).", {$perpage}		
	");

	$dnt_prt_list = '';
		
	while($dnt_prt_rows = $db->fetch_array($query))
	{
		$trow = alt_trow();
		$dnt_prt_rows['tid'] = (int)$dnt_prt_rows['tid'];
        $dnt_prt_rows['pid'] = (int)$dnt_prt_rows['pid'];
		
		if($dnt_prt_rows['avatar'] != "")
			$dnt_prt_rows['avatar'] = "<img src=".htmlspecialchars_uni($dnt_prt_rows['avatar'])." class=\"dnt_prt_list_avatar\" alt=\"avatar\" />";
		else
			$dnt_prt_rows['avatar'] = '<img src="images/default_avatar.png" class="dnt_prt_list_avatar" alt="no avatar" />';
		
		if($dnt_prt_rows['dnt_prt_sender'] > 0)
		{
			if($dnt_prt_rows['dnt_prt_sender'] == $mybb->user['uid'])
			{
				$dnt_prt_rows['username'] = $lang->dnt_prt_you;
				$dnt_prt_rows['username'] = format_name($dnt_prt_rows['username'], $mybb->user['usergroup'], $mybb->user['displaygroup']);
				$dnt_prt_rows['username'] = build_profile_link($dnt_prt_rows['username'], $mybb->user['uid']);
			}
			else
			{
				$dnt_prt_rows['username'] = htmlspecialchars_uni($dnt_prt_rows['username']);
				$dnt_prt_rows['username'] = format_name($dnt_prt_rows['username'], $dnt_prt_rows['usergroup'], $dnt_prt_rows['displaygroup']);
				$dnt_prt_rows['username'] = build_profile_link($dnt_prt_rows['username'], $dnt_prt_rows['uid']);
			}
		}
		else
			$dnt_prt_rows['username'] = $lang->guest;		

		$dnt_prt_rows['date'] = my_date($mybb->settings['dateformat'], $dnt_prt_rows['dnt_prt_date']);
		$dnt_prt_rows['time'] = my_date($mybb->settings['timeformat'], $dnt_prt_rows['dnt_prt_date']);
		
		$dnt_prt_rows['dnt_prt_type'] = (int)$dnt_prt_rows['dnt_prt_type'];
		if($dnt_prt_rows['dnt_prt_type'] == 1)
			eval("\$dnt_prt_rows['rate'] = \"".$templates->get("dnt_prt_list_rate1")."\";");
		if($dnt_prt_rows['dnt_prt_type'] == 2)
			eval("\$dnt_prt_rows['rate'] = \"".$templates->get("dnt_prt_list_rate2")."\";");			
		if($dnt_prt_rows['dnt_prt_type'] == 3)
			eval("\$dnt_prt_rows['rate'] = \"".$templates->get("dnt_prt_list_rate3")."\";");			
		if($dnt_prt_rows['dnt_prt_type'] == 4)
			eval("\$dnt_prt_rows['rate'] = \"".$templates->get("dnt_prt_list_rate4")."\";");			
		if($dnt_prt_rows['dnt_prt_type'] == 5)
			eval("\$dnt_prt_rows['rate'] = \"".$templates->get("dnt_prt_list_rate5")."\";");			
		if($dnt_prt_rows['dnt_prt_type'] == 6)
			eval("\$dnt_prt_rows['rate'] = \"".$templates->get("dnt_prt_list_rate6")."\";");			
			
		$dnt_prt_rows['subject'] = htmlspecialchars_uni($dnt_prt_rows['subject']);
		/*if(my_strlen($dnt_prt_rows['subject']) > 25)
		{
			$dnt_prt_rows['subject'] = my_substr($dnt_prt_rows['subject'], 0, 25)."...";
		}*/
		$dnt_prt_rows['url'] = $mybb->settings['bburl'] ."/". get_post_link($dnt_prt_rows['pid'], $dnt_prt_rows['tid']) . "#pid" . $dnt_prt_rows['pid'];
		if($dnt_prt_rows['dnt_prt_user'] > 0)
		{
			if($dnt_prt_rows['ruid'] == $mybb->user['uid'])
			{
				$dnt_prt_rows['runame'] = $lang->dnt_prt_you;
				$dnt_prt_rows['runame'] = format_name($dnt_prt_rows['runame'], $mybb->user['usergroup'], $mybb->user['displaygroup']);
				$dnt_prt_rows['runame'] = build_profile_link($dnt_prt_rows['runame'], $mybb->user['uid']);
			}			
			else
			{
				$dnt_prt_rows['runame'] = htmlspecialchars_uni($dnt_prt_rows['runame']);
				$dnt_prt_rows['runame'] = format_name($dnt_prt_rows['runame'], $dnt_prt_rows['rug'], $dnt_prt_rows['rdg']);
				$dnt_prt_rows['runame'] = build_profile_link($dnt_prt_rows['runame'], $dnt_prt_rows['ruid']);
			}
		}
		else		
			$dnt_prt_rows['runame'] = $lang->guest;
		
		$dnt_prt_rows['rate_to'] = $lang->sprintf($lang->dnt_prt_has_rated_you, $dnt_prt_rows['runame'], $dnt_prt_rows['rate'], $dnt_prt_rows['url'], $dnt_prt_rows['subject'], $dnt_prt_rows['date'], $dnt_prt_rows['time']);

		eval("\$dnt_prt_list .= \"".$templates->get("dnt_prt_list")."\";");
	}

	$db->free_result($query);

	if(!$dnt_prt_list)
	{
		$lang->dnt_prt_not_received = $lang->dnt_prt_dont_given;		
		eval("\$dnt_prt_list = \"".$templates->get("dnt_prt_list_none")."\";");
	}

	eval("\$content = \"".$templates->get("dnt_prt_content")."\";");
	eval("\$page = \"".$templates->get("dnt_prt_page")."\";");
	$plugins->run_hooks("dnt_post_rate_end");
	output_page($page);
	exit;
}
else
{
	
	$dnt_prt_list = "<div class=\"dnt_prt_list trow1\">{$lang->dnt_prt_error_title}</div>";
	eval("\$content = \"".$templates->get("dnt_prt_content")."\";");
	eval("\$page = \"".$templates->get("dnt_prt_page")."\";");	
	output_page($page);
	exit;
}
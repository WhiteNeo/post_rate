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

if(!empty($mybb->settings['dnt_post_rate_groups']) && $mybb->settings['dnt_post_rate_groups'] != "-1")
{
	$gid = (int)$mybb->user['usergroup'];
	if($mybb->user['additionalgroups'])
		$gids = "{$gid}, {$mybb->user['additionalgroups']}";
	
	$pcl_gids = explode(",",$mybb->settings['dnt_post_rate_groups']);
	
	if(!empty($gids))
	{
		$gids = explode(",",$gids);
		foreach($gids as $gid)
		{
			if(!in_array($gid, $pcl_gids))
				return false;
		}
	}
	else
	{
		if(!in_array($gid, $pcl_gids))
			return false;		
	}		
}

$templatelist = "dnt_prt_page,dnt_prt_content,dnt_prt_list,dnt_prt_list_none";
$lang->load('dnt_post_rate',false,true);
$plugins->run_hooks("dnt_post_rate_start");

add_breadcrumb($lang->pcl_rates_page_title, THIS_SCRIPT);

if($mybb->input['action'] == "get_thread_rates")
{
	if(!isset($mybb->input['lid']) && !isset($mybb->input['tid']))
	{
		error($lang->pcl_not_received, $lang->pcl_error_title);
	}	
	// get forums user cannot view
	$unviewable = get_unviewable_forums(true);	
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
		$sql_req = "pcl_type>0";
	}
	else
	{
		$mybb->input['lid'] = (int)$mybb->input['lid'];
		$mybb->input['lid'] = $db->escape_string($mybb->input['lid']);
		$sql_req = "pcl_type=".$mybb->input['lid'];
	}
	$mybb->input['tid'] = (int)$mybb->input['tid'];
	$mybb->input['tid'] = $db->escape_string($mybb->input['tid']);
	$sql_req .= " AND pcl_tid=".$mybb->input['tid'];
		
	if(isset($mybb->input['pid']))
	{
		$mybb->input['pid'] = (int)$mybb->input['pid'];
		$mybb->input['pid'] = $db->escape_string($mybb->input['pid']);
		$sql_req .= " AND pcl_pid=".$mybb->input['pid'];
	}

	$limit_search = (int)$mybb->settings['dnt_post_rate_limit'];	
	$pcl_date_limit = time() - ($limit_search * 60 * 60 * 24);
	if($limit_search > 0)
		$pcl_date = " AND pcl_date>='{$pcl_date_limit}'";
	else
		$pcl_date = "";
	
	$query = $db->simple_select("dnt_post_rate", "*", "{$sql_req}{$pcl_date}", array("limit" => 1));
		
	$pcl_rows = $db->fetch_array($query);
	$db->free_result($query);

	if(!$pcl_rows)
	{
		error($lang->pcl_not_received, $lang->pcl_error_title);
	}
	
	$lid = (int)$mybb->input['lid'];
	$tid = (int)$mybb->input['tid'];
	$pid = (int)$mybb->input['pid'];

	if(isset($mybb->input['pid']))	
		add_breadcrumb($lang->pcl_rates_page_title, THIS_SCRIPT."?action=get_thread_rates&amp;lid={$lid}&amp;pcl_tid={$tid}&amp;pcl_pid={$pid}");
	else
		add_breadcrumb($lang->pcl_rates_page_title, THIS_SCRIPT."?action=get_thread_rates&amp;lid={$lid}&amp;pcl_tid={$tid}");
		
	$page = (int)$mybb->input['page'];
	if($page < 1) $page = 1;
	$numtot = $db->fetch_field($db->simple_select('dnt_post_rate', 'COUNT(*) AS numtot', $sql_req.$pcl_date), 'numtot');
	$perpage = (int)$mybb->settings['dnt_post_rate_limit_page'];
	if($perpage == 0)
		$perpage = 20;
	$items_founded = (int)$numtot;
	if($mybb->input['lid'] == "all")
		$lang->pcl_rates = $lang->sprintf($lang->pcl_rates_thread, $items_founded);
	else
		$lang->pcl_rates = $lang->sprintf($lang->pcl_rates_post, $items_founded);		
	
	if(isset($mybb->input['pid']))
		$multipage = multipage($numtot, $perpage, $page, $_SERVER['PHP_SELF']."?action=get_thread_rates&amp;lid={$lid}&amp;pcl_tid={$tid}&amp;pcl_pid={$pid}");
	else
		$multipage = multipage($numtot, $perpage, $page, $_SERVER['PHP_SELF']."?action=get_thread_rates&amp;lid={$lid}&amp;pcl_tid={$tid}");
		
	$query = $db->query("SELECT dp.*, u.*, ru.uid as ruid, ru.username as runame, ru.usergroup as rug, ru.displaygroup as rudg, t.fid, t.tid, t.visible, p.subject, p.pid FROM ".TABLE_PREFIX."dnt_post_rate dp
		LEFT JOIN ".TABLE_PREFIX."users u
		ON (dp.pcl_sender=u.uid)
		LEFT JOIN ".TABLE_PREFIX."users ru
		ON (dp.pcl_user=ru.uid)
		LEFT JOIN ".TABLE_PREFIX."threads t
		ON (dp.pcl_tid=t.tid)			
		LEFT JOIN ".TABLE_PREFIX."posts p
		ON (dp.pcl_pid=p.pid)	
		WHERE {$sql_req}{$pcl_date}{$unviewwhere}
		ORDER BY pcl_date DESC
		LIMIT ".(($page-1)*$perpage).", {$perpage}		
	");

	$dnt_prt_list = '';
	
	while($pcl_rows = $db->fetch_array($query))
	{
		$trow = alt_trow();
		$pcl_rows['tid'] = (int)$pcl_rows['tid'];
        $pcl_rows['pid'] = (int)$pcl_rows['pid'];
		
		if($pcl_rows['avatar'] != "")
			$pcl_rows['avatar'] = "<img src=".htmlspecialchars_uni($pcl_rows['avatar'])." class=\"pcl_list_avatar\" alt=\"avatar\" />";
		else
			$pcl_rows['avatar'] = '<img src="images/default_avatar.png" class="pcl_list_avatar" alt="no avatar" />';
		
		if($pcl_rows['pcl_sender'] > 0)
		{
			if($pcl_rows['pcl_sender'] == $mybb->user['uid'])
			{
				$pcl_rows['username'] = $lang->pcl_you;
				$pcl_rows['username'] = format_name($pcl_rows['username'], $mybb->user['usergroup'], $mybb->user['displaygroup']);
				$pcl_rows['username'] = build_profile_link($pcl_rows['username'], $mybb->user['uid']);
			}
			else
			{
				$pcl_rows['username'] = htmlspecialchars_uni($pcl_rows['username']);
				$pcl_rows['username'] = format_name($pcl_rows['username'], $pcl_rows['usergroup'], $pcl_rows['displaygroup']);
				$pcl_rows['username'] = build_profile_link($pcl_rows['username'], $pcl_rows['uid']);
			}
		}
		else
			$pcl_rows['username'] = $lang->guest;		

		$pcl_rows['date'] = my_date($mybb->settings['dateformat'], $pcl_rows['pcl_date']);
		$pcl_rows['time'] = my_date($mybb->settings['timeformat'], $pcl_rows['pcl_date']);
		
		$pcl_rows['pcl_type'] = (int)$pcl_rows['pcl_type'];
		if($pcl_rows['pcl_type'] == 1)
			$pcl_rows['rate'] = '<div class="pcl_div_rate"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/like.png" alt="'.$lang->pcl_like.'" title="'.$lang->pcl_like.'" /><span style="color:#1b43b6;">'.$lang->pcl_like.'</span></div>';
		if($pcl_rows['pcl_type'] == 2)
			$pcl_rows['rate'] = '<div class="pcl_div_rate"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/love.png" alt="'.$lang->pcl_love.'" title="'.$lang->pcl_love.'" /><span style="color: #e61b3f;">'.$lang->pcl_love.'</span></div>';
		if($pcl_rows['pcl_type'] == 3)
			$pcl_rows['rate'] = '<div class="pcl_div_rate"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/wow.png" alt="'.$lang->pcl_wow.'" title="'.$lang->pcl_wow.'" /><span style="color:#cfcd35;">'.$lang->pcl_wow.'</span></div>';
		if($pcl_rows['pcl_type'] == 4)
			$pcl_rows['rate'] = '<div class="pcl_div_rate"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/smile.png" alt="'.$lang->pcl_smile.'" title="'.$lang->pcl_smile.'" /><span style="color:#cfcd35;">'.$lang->pcl_smile.'</span></div>';
		if($pcl_rows['pcl_type'] == 5)				
			$pcl_rows['rate'] = '<div class="pcl_div_rate"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/cry.png" alt="'.$lang->pcl_cry.'" title="'.$lang->pcl_cry.'" /><span style="color:#1b43b6;">'.$lang->pcl_cry.'</span></div>';				
		if($pcl_rows['pcl_type'] == 6)
			$pcl_rows['rate'] = '<div class="pcl_div_rate"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/angry.png" alt="'.$lang->pcl_angry.'" title="'.$lang->pcl_angry.'" /><span style="color:#c22e0f;">'.$lang->pcl_angry.'</span></div>';				
			
		$pcl_rows['subject'] = htmlspecialchars_uni($pcl_rows['subject']);
		/*if(my_strlen($pcl_rows['subject']) > 25)
		{
			$pcl_rows['subject'] = my_substr($pcl_rows['subject'], 0, 25)."...";
		}*/
		$pcl_rows['url'] = $mybb->settings['bburl'] ."/". get_post_link($pcl_rows['pid'], $pcl_rows['tid']) . "#pid" . $pcl_rows['pid'];
		if($pcl_rows['pcl_user'] > 0)
		{
			if($pcl_rows['pcl_user'] == $mybb->user['uid'])
			{
				$pcl_rows['runame'] = $lang->pcl_you;
				$pcl_rows['runame'] = format_name($pcl_rows['runame'], $mybb->user['usergroup'], $mybb->user['displaygroup']);
				$pcl_rows['runame'] = build_profile_link($pcl_rows['runame'], $mybb->user['uid']);
			}			
			else
			{
				$pcl_rows['runame'] = htmlspecialchars_uni($pcl_rows['runame']);
				$pcl_rows['runame'] = format_name($pcl_rows['runame'], $pcl_rows['rug'], $pcl_rows['rdg']);
				$pcl_rows['runame'] = build_profile_link($pcl_rows['runame'], $pcl_rows['ruid']);
			}
		}
		else 		
			$pcl_rows['runame'] = $lang->guest;			
			
		eval("\$dnt_prt_list .= \"".$templates->get("dnt_prt_list")."\";");
	}

	$db->free_result($query);

	if(!$dnt_prt_list)
	{
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
		error($lang->pcl_not_received, $lang->pcl_error_title);
	}
	// get forums user cannot view
	$unviewable = get_unviewable_forums(true);	
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
	$sql_req = "pcl_user=".$mybb->input['uid'];

	$limit_search = (int)$mybb->settings['dnt_post_rate_limit'];	
	$pcl_date_limit = time() - ($limit_search * 60 * 60 * 24);
	if($limit_search > 0)
		$pcl_date = " AND pcl_date>='{$pcl_date_limit}'";
	else
		$pcl_date = "";
	
	$query = $db->simple_select("dnt_post_rate", "*", "{$sql_req}{$pcl_date}", array("limit" => 1));
		
	$pcl_rows = $db->fetch_array($query);
	$db->free_result($query);

	if(!$pcl_rows)
	{
		error($lang->pcl_not_received, $lang->pcl_error_title);
	}
	
	$lid = (int)$mybb->input['lid'];
	$tid = (int)$mybb->input['tid'];
	$pid = (int)$mybb->input['pid'];

	if(isset($mybb->input['pid']))	
		add_breadcrumb($lang->pcl_rates_page_title, THIS_SCRIPT."?action=get_thread_rates&amp;lid={$lid}&amp;pcl_tid={$tid}&amp;pcl_pid={$pid}");
	else
		add_breadcrumb($lang->pcl_rates_page_title, THIS_SCRIPT."?action=get_thread_rates&amp;lid={$lid}&amp;pcl_tid={$tid}");
		
	$page = (int)$mybb->input['page'];
	if($page < 1) $page = 1;
	$numtot = $db->fetch_field($db->simple_select('dnt_post_rate', 'COUNT(*) AS numtot', $sql_req.$pcl_date), 'numtot');
	$perpage = (int)$mybb->settings['dnt_post_rate_limit_page'];
	if($perpage == 0)
		$perpage = 20;	
	$items_founded = (int)$numtot;
	$lang->pcl_rates = $lang->sprintf($lang->pcl_rates_received, $items_founded);
	
	if(isset($mybb->input['pid']))
		$multipage = multipage($numtot, $perpage, $page, $_SERVER['PHP_SELF']."?action=get_thread_rates&amp;lid={$lid}&amp;pcl_tid={$tid}&amp;pcl_pid={$pid}");
	else
		$multipage = multipage($numtot, $perpage, $page, $_SERVER['PHP_SELF']."?action=get_thread_rates&amp;lid={$lid}&amp;pcl_tid={$tid}");
		
	$query = $db->query("SELECT dp.*, u.*, ru.username as runame, ru.uid as ruid, ru.usergroup as rug, ru.displaygroup as rudg, t.fid, t.tid, t.visible, p.subject, p.pid FROM ".TABLE_PREFIX."dnt_post_rate dp
		LEFT JOIN ".TABLE_PREFIX."users u
		ON (dp.pcl_sender=u.uid)
		LEFT JOIN ".TABLE_PREFIX."users ru
		ON (dp.pcl_user=ru.uid)		
		LEFT JOIN ".TABLE_PREFIX."threads t
		ON (dp.pcl_tid=t.tid)			
		LEFT JOIN ".TABLE_PREFIX."posts p
		ON (dp.pcl_pid=p.pid)	
		WHERE {$sql_req}{$pcl_date}{$unviewwhere}
		ORDER BY pcl_date DESC
		LIMIT ".(($page-1)*$perpage).", {$perpage}		
	");

	$dnt_prt_list = '';
	
	while($pcl_rows = $db->fetch_array($query))
	{
		$trow = alt_trow();
		$pcl_rows['tid'] = (int)$pcl_rows['tid'];
        $pcl_rows['pid'] = (int)$pcl_rows['pid'];
		
		if($pcl_rows['avatar'] != "")
			$pcl_rows['avatar'] = "<img src=".htmlspecialchars_uni($pcl_rows['avatar'])." class=\"pcl_list_avatar\" alt=\"avatar\" />";
		else
			$pcl_rows['avatar'] = '<img src="images/default_avatar.png" class="pcl_list_avatar" alt="no avatar" />';
		
		if($pcl_rows['pcl_sender'] > 0)
		{
			if($pcl_rows['pcl_sender'] == $mybb->user['uid'])
			{
				$pcl_rows['username'] = $lang->pcl_you;
				$pcl_rows['username'] = format_name($pcl_rows['username'], $mybb->user['usergroup'], $mybb->user['displaygroup']);
				$pcl_rows['username'] = build_profile_link($pcl_rows['username'], $mybb->user['uid']);
			}
			else
			{
				$pcl_rows['username'] = htmlspecialchars_uni($pcl_rows['username']);
				$pcl_rows['username'] = format_name($pcl_rows['username'], $pcl_rows['usergroup'], $pcl_rows['displaygroup']);
				$pcl_rows['username'] = build_profile_link($pcl_rows['username'], $pcl_rows['uid']);
			}
		}
		else
			$pcl_rows['username'] = $lang->guest;		

		$pcl_rows['date'] = my_date($mybb->settings['dateformat'], $pcl_rows['pcl_date']);
		$pcl_rows['time'] = my_date($mybb->settings['timeformat'], $pcl_rows['pcl_date']);
		
		$pcl_rows['pcl_type'] = (int)$pcl_rows['pcl_type'];
		if($pcl_rows['pcl_type'] == 1)
			$pcl_rows['rate'] = '<div class="pcl_div_rate"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/like.png" alt="'.$lang->pcl_like.'" title="'.$lang->pcl_like.'" /><span style="color:#1b43b6;">'.$lang->pcl_like.'</span></div>';
		if($pcl_rows['pcl_type'] == 2)
			$pcl_rows['rate'] = '<div class="pcl_div_rate"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/love.png" alt="'.$lang->pcl_love.'" title="'.$lang->pcl_love.'" /><span style="color: #e61b3f;">'.$lang->pcl_love.'</span></div>';
		if($pcl_rows['pcl_type'] == 3)
			$pcl_rows['rate'] = '<div class="pcl_div_rate"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/wow.png" alt="'.$lang->pcl_wow.'" title="'.$lang->pcl_wow.'" /><span style="color:#cfcd35;">'.$lang->pcl_wow.'</span></div>';
		if($pcl_rows['pcl_type'] == 4)
			$pcl_rows['rate'] = '<div class="pcl_div_rate"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/smile.png" alt="'.$lang->pcl_smile.'" title="'.$lang->pcl_smile.'" /><span style="color:#cfcd35;">'.$lang->pcl_smile.'</span></div>';
		if($pcl_rows['pcl_type'] == 5)				
			$pcl_rows['rate'] = '<div class="pcl_div_rate"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/cry.png" alt="'.$lang->pcl_cry.'" title="'.$lang->pcl_cry.'" /><span style="color:#1b43b6;">'.$lang->pcl_cry.'</span></div>';				
		if($pcl_rows['pcl_type'] == 6)
			$pcl_rows['rate'] = '<div class="pcl_div_rate"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/angry.png" alt="'.$lang->pcl_angry.'" title="'.$lang->pcl_angry.'" /><span style="color:#c22e0f;">'.$lang->pcl_angry.'</span></div>';				
			
		$pcl_rows['subject'] = htmlspecialchars_uni($pcl_rows['subject']);
		/*if(my_strlen($pcl_rows['subject']) > 25)
		{
			$pcl_rows['subject'] = my_substr($pcl_rows['subject'], 0, 25)."...";
		}*/
		$pcl_rows['url'] = $mybb->settings['bburl'] ."/". get_post_link($pcl_rows['pid'], $pcl_rows['tid']) . "#pid" . $pcl_rows['pid'];

		if($pcl_rows['pcl_user'] > 0)
		{		
			if($pcl_rows['ruid'] == $mybb->user['uid'])
			{
				$pcl_rows['runame'] = $lang->pcl_you;
				$pcl_rows['runame'] = format_name($pcl_rows['runame'], $mybb->user['usergroup'], $mybb->user['displaygroup']);
				$pcl_rows['runame'] = build_profile_link($pcl_rows['runame'], $mybb->user['uid']);
			}			
			else
			{
				$pcl_rows['runame'] = htmlspecialchars_uni($pcl_rows['runame']);
				$pcl_rows['runame'] = format_name($pcl_rows['runame'], $pcl_rows['rug'], $pcl_rows['rdg']);
				$pcl_rows['runame'] = build_profile_link($pcl_rows['runame'], $pcl_rows['ruid']);
			}
		}
		else		
			$pcl_rows['runame'] = $lang->guest;

		eval("\$dnt_prt_list .= \"".$templates->get("dnt_prt_list")."\";");
	}

	$db->free_result($query);

	if(!$dnt_prt_list)
	{
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
		error($lang->pcl_not_received, $lang->pcl_error_title);
	}
	// get forums user cannot view
	$unviewable = get_unviewable_forums(true);	
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
	$sql_req = "pcl_sender=".$mybb->input['uid'];

	$limit_search = (int)$mybb->settings['dnt_post_rate_limit'];	
	$pcl_date_limit = time() - ($limit_search * 60 * 60 * 24);
	if($limit_search > 0)
		$pcl_date = " AND pcl_date>='{$pcl_date_limit}'";
	else
		$pcl_date = "";
	
	$query = $db->simple_select("dnt_post_rate", "*", "{$sql_req}{$pcl_date}", array("limit" => 1));
		
	$pcl_rows = $db->fetch_array($query);
	$db->free_result($query);

	if(!$pcl_rows)
	{
		error($lang->pcl_not_received, $lang->pcl_error_title);
	}
	
	$lid = (int)$mybb->input['lid'];
	$tid = (int)$mybb->input['tid'];
	$pid = (int)$mybb->input['pid'];

	if(isset($mybb->input['pid']))	
		add_breadcrumb($lang->pcl_rates_page_title, THIS_SCRIPT."?action=get_thread_rates&amp;lid={$lid}&amp;pcl_tid={$tid}&amp;pcl_pid={$pid}");
	else
		add_breadcrumb($lang->pcl_rates_page_title, THIS_SCRIPT."?action=get_thread_rates&amp;lid={$lid}&amp;pcl_tid={$tid}");
		
	$page = (int)$mybb->input['page'];
	if($page < 1) $page = 1;
	$numtot = $db->fetch_field($db->simple_select('dnt_post_rate', 'COUNT(*) AS numtot', $sql_req.$pcl_date), 'numtot');
	$perpage = (int)$mybb->settings['dnt_post_rate_limit_page'];
	if($perpage == 0)
		$perpage = 20;	
	$items_founded = (int)$numtot;
	$lang->pcl_rates = $lang->sprintf($lang->pcl_rates_given, $items_founded);
	
	if(isset($mybb->input['pid']))
		$multipage = multipage($numtot, $perpage, $page, $_SERVER['PHP_SELF']."?action=get_thread_rates&amp;lid={$lid}&amp;pcl_tid={$tid}&amp;pcl_pid={$pid}");
	else
		$multipage = multipage($numtot, $perpage, $page, $_SERVER['PHP_SELF']."?action=get_thread_rates&amp;lid={$lid}&amp;pcl_tid={$tid}");
		
	$query = $db->query("SELECT dp.*, u.*, ru.username as runame, ru.usergroup as rug, ru.displaygroup as rudg, ru.uid as ruid, t.fid, t.tid, t.visible, p.subject, p.pid FROM ".TABLE_PREFIX."dnt_post_rate dp
		LEFT JOIN ".TABLE_PREFIX."users u
		ON (dp.pcl_sender=u.uid)
		LEFT JOIN ".TABLE_PREFIX."users ru
		ON (dp.pcl_user=ru.uid)
		LEFT JOIN ".TABLE_PREFIX."threads t
		ON (dp.pcl_tid=t.tid)			
		LEFT JOIN ".TABLE_PREFIX."posts p
		ON (dp.pcl_pid=p.pid)	
		WHERE {$sql_req}{$pcl_date}{$unviewwhere}
		ORDER BY pcl_date DESC
		LIMIT ".(($page-1)*$perpage).", {$perpage}		
	");

	$dnt_prt_list = '';
	
	while($pcl_rows = $db->fetch_array($query))
	{
		$trow = alt_trow();
		$pcl_rows['tid'] = (int)$pcl_rows['tid'];
        $pcl_rows['pid'] = (int)$pcl_rows['pid'];
		
		if($pcl_rows['avatar'] != "")
			$pcl_rows['avatar'] = "<img src=".htmlspecialchars_uni($pcl_rows['avatar'])." class=\"pcl_list_avatar\" alt=\"avatar\" />";
		else
			$pcl_rows['avatar'] = '<img src="images/default_avatar.png" class="pcl_list_avatar" alt="no avatar" />';
		
		if($pcl_rows['pcl_sender'] > 0)
		{
			if($pcl_rows['pcl_sender'] == $mybb->user['uid'])
			{
				$pcl_rows['username'] = $lang->pcl_you;
				$pcl_rows['username'] = format_name($pcl_rows['username'], $mybb->user['usergroup'], $mybb->user['displaygroup']);
				$pcl_rows['username'] = build_profile_link($pcl_rows['username'], $mybb->user['uid']);
			}
			else
			{
				$pcl_rows['username'] = htmlspecialchars_uni($pcl_rows['username']);
				$pcl_rows['username'] = format_name($pcl_rows['username'], $pcl_rows['usergroup'], $pcl_rows['displaygroup']);
				$pcl_rows['username'] = build_profile_link($pcl_rows['username'], $pcl_rows['uid']);
			}
		}
		else
			$pcl_rows['username'] = $lang->guest;		

		$pcl_rows['date'] = my_date($mybb->settings['dateformat'], $pcl_rows['pcl_date']);
		$pcl_rows['time'] = my_date($mybb->settings['timeformat'], $pcl_rows['pcl_date']);
		
		$pcl_rows['pcl_type'] = (int)$pcl_rows['pcl_type'];
		if($pcl_rows['pcl_type'] == 1)
			$pcl_rows['rate'] = '<div class="pcl_div_rate"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/like.png" alt="'.$lang->pcl_like.'" title="'.$lang->pcl_like.'" /><span style="color:#1b43b6;">'.$lang->pcl_like.'</span></div>';
		if($pcl_rows['pcl_type'] == 2)
			$pcl_rows['rate'] = '<div class="pcl_div_rate"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/love.png" alt="'.$lang->pcl_love.'" title="'.$lang->pcl_love.'" /><span style="color: #e61b3f;">'.$lang->pcl_love.'</span></div>';
		if($pcl_rows['pcl_type'] == 3)
			$pcl_rows['rate'] = '<div class="pcl_div_rate"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/wow.png" alt="'.$lang->pcl_wow.'" title="'.$lang->pcl_wow.'" /><span style="color:#cfcd35;">'.$lang->pcl_wow.'</span></div>';
		if($pcl_rows['pcl_type'] == 4)
			$pcl_rows['rate'] = '<div class="pcl_div_rate"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/smile.png" alt="'.$lang->pcl_smile.'" title="'.$lang->pcl_smile.'" /><span style="color:#cfcd35;">'.$lang->pcl_smile.'</span></div>';
		if($pcl_rows['pcl_type'] == 5)				
			$pcl_rows['rate'] = '<div class="pcl_div_rate"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/cry.png" alt="'.$lang->pcl_cry.'" title="'.$lang->pcl_cry.'" /><span style="color:#1b43b6;">'.$lang->pcl_cry.'</span></div>';				
		if($pcl_rows['pcl_type'] == 6)
			$pcl_rows['rate'] = '<div class="pcl_div_rate"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/angry.png" alt="'.$lang->pcl_angry.'" title="'.$lang->pcl_angry.'" /><span style="color:#c22e0f;">'.$lang->pcl_angry.'</span></div>';				
			
		$pcl_rows['subject'] = htmlspecialchars_uni($pcl_rows['subject']);
		/*if(my_strlen($pcl_rows['subject']) > 25)
		{
			$pcl_rows['subject'] = my_substr($pcl_rows['subject'], 0, 25)."...";
		}*/
		$pcl_rows['url'] = $mybb->settings['bburl'] ."/". get_post_link($pcl_rows['pid'], $pcl_rows['tid']) . "#pid" . $pcl_rows['pid'];

		if($pcl_rows['pcl_user'] > 0)
		{			
			if($pcl_rows['ruid'] == $mybb->user['uid'])
			{
				$pcl_rows['runame'] = $lang->pcl_you;
				$pcl_rows['runame'] = format_name($pcl_rows['runame'], $mybb->user['usergroup'], $mybb->user['displaygroup']);
				$pcl_rows['runame'] = build_profile_link($pcl_rows['runame'], $mybb->user['uid']);
			}			
			else
			{
				$pcl_rows['runame'] = htmlspecialchars_uni($pcl_rows['runame']);
				$pcl_rows['runame'] = format_name($pcl_rows['runame'], $pcl_rows['rug'], $pcl_rows['rdg']);
				$pcl_rows['runame'] = build_profile_link($pcl_rows['runame'], $pcl_rows['ruid']);
			}
		}
		else		
			$pcl_rows['runame'] = $lang->guest;
		
		//$lang->pcl_has_rated_you = $lang->sprintf($lang->pcl_has_rated_you, $pcl_rows['runame'], $pcl_rows['rate'], $pcl_rows['url'], $pcl_rows['subject'], $pcl_rows['date'], $pcl_rows['time']);

		eval("\$dnt_prt_list .= \"".$templates->get("dnt_prt_list")."\";");
	}

	$db->free_result($query);

	if(!$dnt_prt_list)
	{
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
	
	$dnt_prt_list = "<div class=\"dnt_prt_list trow1\">{$lang->pcl_error_title}</div>";
	eval("\$content = \"".$templates->get("dnt_prt_content")."\";");
	eval("\$page = \"".$templates->get("dnt_prt_page")."\";");	
	output_page($page);
	exit;
}

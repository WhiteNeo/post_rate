<?php
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook('postbit', 'dnt_post_rate_post_rates');
$plugins->add_hook('xmlhttp', 'dnt_post_rate_xmlhttp');

function dnt_post_rate_info()
{
	return array(
		"name" => "Post Rate",
		"description" => "Clasify your post by users rate",
		"website" => "",
		"author" => "Whiteneo",
		"authorsite" => "https://soportemybb.es",
		"version" => "1.0",
		"codename" => "dnt_post_rate_",
		"compatibility" => "18*"
	);
}

function dnt_post_rate_install()
{
	global $db, $cache, $charset;

	if(empty($charset))
	{
		$charset = "utf8";		
	}
	// Creates the table where comments are saved
	$tables = "CREATE TABLE IF NOT EXISTS `".TABLE_PREFIX."dnt_post_rate` (
  `pcl_id` int(10) NOT NULL auto_increment,
  `pcl_type` int(5) NOT NULL default '0',	  
  `pcl_user` int(10) NOT NULL default '0',
  `pcl_sender` int(10) NOT NULL default '0',
  `pcl_tid` int(10) NOT NULL default '0',
  `pcl_count` int(10) NOT NULL default '0', 
  `pcl_date` int(10) NOT NULL default '0',
  PRIMARY KEY  (`pcl_id`)
) DEFAULT CHARSET={$charset};";
	$db->write_query($tables);

	$db->write_query("ALTER TABLE `".TABLE_PREFIX."threads` ADD `pcl_total` int(10) NOT NULL DEFAULT '0';");
}

function dnt_post_rate_uninstall()
{
	global $mybb, $db, $cache;
	if($mybb->request_method != 'post')
	{
		global $page, $lang;
		$page->output_confirm_action('index.php?module=config-plugins&action=deactivate&uninstall=1&plugin=dnt_post_rate', "Do you want to remove record data ?", "If you use NO then all data must keep it into database, otherwise set YES to remove all data from database.");
	}
	if(!isset($mybb->input['no']))
	{
		// Delete table where comments are saved
		$db->write_query('DROP TABLE `'.TABLE_PREFIX.'dnt_post_rate`');
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."threads` DROP `pcl_total`;");		
	}
}

function dnt_post_rate_is_installed()
{
	global $mybb;

	if(isset($mybb->settings['dnt_post_rate_active']))
	{
		return true;
	}
	else
	{
		return false;		
	}
}

function dnt_post_rate_activate()
{
	global $db, $lang, $cache;

	// Create the config group for MyBB Settings
	$query = $db->simple_select("settinggroups", "COUNT(*) as rows");
	$rows = $db->fetch_field($query, "rows");

	$new_groupconfig = array(
		'name' => 'dnt_post_rate', 
		'title' => 'Post rate plugin',
		'description' => 'Clasify your posts by users rate plugin settings',
		'disporder' => $rows+1,
		'isdefault' => 0
	);
	
	$group['gid'] = $db->insert_query("settinggroups", $new_groupconfig);

	// Insert dnt_post_rate options
	$new_config = array();

	$new_config[] = array(
		'name' => 'dnt_post_rate_active',
		'title' => 'Enable / Disable plugin',
		'description' => 'Here you can set if plugin is enabled or disabled on your boards',
		'optionscode' => 'yesno',
		'value' => 1,
		'disporder' => 1,
		'gid' => $group['gid']
	);
	
	$new_config[] = array(
		'name' => 'dnt_post_rate_forums',
		'title' => 'Select forums where this mod applies',
		'description' => 'Select from the list your forums where this mod take effect',
		'optionscode' => 'forumselect',
		'value' => "-1",
		'disporder' => 2,
		'gid' => $group['gid']
	);

	$new_config[] = array(
		'name' => 'dnt_post_rate_groups',
		'title' => 'Select usergroups who can rate posts',
		'description' => 'Select usergroups from the list for the users who can use rate posts',
		'optionscode' => 'groupselect',
		'value' => "-1",
		'disporder' => 3,
		'gid' => $group['gid']
	);
	
	foreach($new_config as $array => $content)
	{
		$db->insert_query("settings", $content);
	}

	// Creating stylesheet...
	$stylesheet_css = '.post_rate_list{position: absolute;z-index: 9999;border: 2px solid #0F5579;background:#fff;margin-left: -120px;margin-top: -86px;border-radius: 10px;}
.post_rate_button{color: #fff;text-shadow: 1px 1px 1px #000;height: 26px;line-height: 26px;padding: 0 10px;text-decoration: none;margin-left: 4px;display: inline-block;cursor:pointer;background: #202020;border-radius: 4px;font-size: 13px;background: #0F5579 !important}
.post_rate_btn img{cursor:pointer;}
.post_rate_btn img:hover{width:60px;height:60px;margin-top:-96px;transition:all ease 0.5s;}
.pcl_list{text-shadow: 1px 1px 1px #000;padding: 10px;border-radius: 2px;-moz-border-radius: 2px;-webkit-border-radius: 2px;color: #fff;text-align:center;font-size: 13px;display: inline-block;}
.clasify_post_rates_msg{background-color: rgba(83,168,65,0.33);float:right;margin:5px}
.clasify_post_rates_msg > span{font-size: 10px;font-weight: bold;position: absolute;background: #ce5757;padding: 1px 3px;color: #f0f0f0;border-radius: 4px;}';

	$stylesheet = array(
		"name"			=> "pcl.css",
		"tid"			=> 1,
		"attachedto"	=> 0,		
		"stylesheet"	=> $db->escape_string($stylesheet_css),
		"cachefile"		=> "pcl.css",
		"lastmodified"	=> TIME_NOW
	);

	// Insert stylesheet to db...
	$sid = $db->insert_query('themestylesheets', $stylesheet);
	$db->update_query('themestylesheets', array('cachefile' => "psc.css"), "sid='{$sid}'", 1);
	$query = $db->simple_select('themes', 'tid');
	while($theme = $db->fetch_array($query))
	{
		require_once MYBB_ADMIN_DIR.'inc/functions_themes.php';
		cache_stylesheet($style['tid'], $stylesheet['cachefile'], $stylesheet['stylesheet']);
		update_theme_stylesheet_list($theme['tid'], false, true);			
	}	

	// Edit some existing templates
	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("postbit", '#'.preg_quote('{$post[\'button_edit\']}').'#', '{$post[\'clasify_post_rates\']}{$post[\'button_edit\']}');
	find_replace_templatesets("postbit_classic", '#'.preg_quote('{$post[\'button_edit\']}').'#', '{$post[\'clasify_post_rates\']}{$post[\'button_edit\']}');	
	rebuild_settings();
}

/**
 * Deactivate function
 *
 */
function dnt_post_rate_deactivate()
{
	global $db,$cache;

	// Delete config groups
	$db->delete_query("settings", "name IN ('dnt_post_rate_active','dnt_post_rate_forums','dnt_post_rate_groups')");
	$db->delete_query("settinggroups", "name='dnt_post_rate'");
   // Delete stylesheet
   	$db->delete_query('themestylesheets', "name='pcl.css'");
	$query = $db->simple_select('themes', 'tid');
	while($theme = $db->fetch_array($query))
	{
		require_once MYBB_ADMIN_DIR.'inc/functions_themes.php';
		update_theme_stylesheet_list($theme['tid']);
	}	

	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("postbit", '#'.preg_quote('{$post[\'clasify_post_rates\']}').'#', '', 0);
	find_replace_templatesets("postbit_classic", '#'.preg_quote('{$post[\'clasify_post_rates\']}').'#', '', 0);	
	rebuild_settings();
}


function dnt_post_rate_post_rates(&$post)
{
	global $db, $mybb, $lang, $thread;

	if($mybb->settings['dnt_post_rate_active'] == 0)
	{
		return false;
	}
	$lang->load('dnt_post_rate',false,true);

	if($thread['firstpost'] != $post['pid'])
		return false;	
	
	$tid = (int)$thread['tid'];
	$fid = (int)$thread['fid'];
	$pcl_sender = (int)$mybb->user['uid'];
	$pcl_senderc = "-1";
	$pcl_date = time() - (30 * 60 * 60 * 24);
	
	if($pcl_fids != "")
		return false;
	else if($pcl_fids != "-1" && !empty($pcl_fids))
	{
		$pcl_fids = explode(",",$mybb->settings['dnt_post_rate_forums']);
		if(!in_array($fid, $pcl_fids))
			return false;		
	}
	
	if(!empty($mybb->settings['dnt_post_rate_groups']) && $mybb->settings['dnt_post_rate_groups'] != "-1")
	{
		$gid = (int)$mybb->user['usergroup'];
		if($mybb->user['additionalgroups'])
			$gids = "{$gid}, {$mybb->user['additionalgroups']}";
		
		$pcl_gids = explode(",",$mybb->settings['dnt_post_rate_groups']);
		
		if(!in_array($fid, $pcl_fids))
			return false;
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
		$pcl_see_me = false;		
	}
	else if($mybb->settings['dnt_post_rate_groups'] == "-1")
		$pcl_see_me = true;
	
	if($thread['uid'] == $mybb->user['uid'])
	{
		$pcl_see_me = false;
	}
	else
	{
		if($mybb->user['uid'] != $thread['uid'])
		{
			$query = $db->simple_select('dnt_post_rate','*',"pcl_sender='{$pcl_sender}' AND pcl_tid='{$tid}' AND pcl_date>='{$pcl_date}'", array("limit"=>1));		
			if ($db->num_rows($query) > 0)
			{
				$pcl_see_me = false;
			}
			else
			{
				$pcl_see_me = true;
			}		
		}
	}
	$tid = (int)$thread['tid'];
	$pcl_user = (int)$thread['uid'];
	$likes = $loves = $surprises = $smiles = $crys = $hungrys = 0;
	$pcl_query = $db->simple_select('dnt_post_rate','*',"pcl_tid='{$tid}' AND pcl_date>='{$pcl_date}'");
	while($pcl_rows = $db->fetch_array($pcl_query))
	{
		$pcl_senderc = (int)$pcl_rows['pcl_sender'];
		$pcl_type = (int)$pcl_rows['pcl_type'];
		if($pcl_type == 1)
		{
			$likes++;
		}
		if($pcl_type == 2)
		{
			$loves++;
		}
		if($pcl_type == 3)
		{
			$surprises++;
		}			
		if($pcl_type == 4)
		{
			$smiles++;
		}
		if($pcl_type == 5)
		{
			$crys++;
		}
		if($pcl_type == 6)
		{
			$hungrys++;				
		}
	}

	if($pcl_see_me === true)
	{
$post['clasify_post_rates'] = '<div class="post_rate_button" id="post_rates_btn">'.$lang->pcl_rate.'</div>
<div id="post_rates" class="post_rate_list" style="display:none;">
	<span onclick="javascript:DNTPostRate(1, '.$tid.')" class="post_rate_btn"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/like.png" alt="Like" /></span>
	<span onclick="javascript:DNTPostRate(2, '.$tid.')" class="post_rate_btn"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/love.png" alt="Love" /></span>
	<span onclick="javascript:DNTPostRate(3, '.$tid.')" class="post_rate_btn"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/surprise.png" alt="Surprise" /></span>
	<span onclick="javascript:DNTPostRate(4, '.$tid.')" class="post_rate_btn"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/smile.png" alt="Smile" /></span>
	<span onclick="javascript:DNTPostRate(5, '.$tid.')" class="post_rate_btn"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/cry.png" alt="Cry" /></span>
	<span onclick="javascript:DNTPostRate(6, '.$tid.')" class="post_rate_btn"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/hungry.png" alt="Hungry" /></span>	
</div>
<script type="text/javascript">
function DNTPostRate(lid,tid)
{
	$.get("xmlhttp.php?action=clasify_post_rate&lid="+lid+"&tid="+tid,function(request){
		$("#post_rates_btn").remove();
		$("#post_rates").remove();
		$("#clasify_post_rates_msgs_list").html(request.templates);
	});
}
$(document).on("ready", function(){
	$("#post_rates_btn").on("click", function(){
		$("#post_rates").slideToggle();
	});
});
</script>';
	}

	$clasify_post_rates_total = (int)$thread['pcl_total'];

	if($clasify_post_rates_total > 0)
	{
		$pcl_results1 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/like.png" alt="Like" />';				
		$pcl_results2 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/love.png" alt="Love" />';		
		$pcl_results3 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/surprise.png" alt="Surprise" />';			
		$pcl_results4 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/smile.png" alt="Smile" />';				
		$pcl_results5 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/cry.png" alt="Cry" />';				
		$pcl_results6 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/hungry.png" alt="Hungry" />';
		
		if($likes > 0)
			$clasify_post_rates_msg .= "<span>".$likes."</span>".$pcl_results1;
		if($loves > 0)
			$clasify_post_rates_msg .= "<span>".$loves."</span>".$pcl_results2;		
		if($surprises > 0)
			$clasify_post_rates_msg .= "<span>".$surprises."</span>".$pcl_results3;
		if($smiles > 0)
			$clasify_post_rates_msg .= "<span>".$smiles."</span>".$pcl_results4;
		if($crys > 0)
			$clasify_post_rates_msg .= "<span>".$crys."</span>".$pcl_results5;
		if($hungrys > 0)
			$clasify_post_rates_msg .= "<span>".$hungrys."</span>".$pcl_results6;
		
		$post['clasify_post_rates_msg'] = '<div id="clasify_post_rates_msgs_list"><div class="clasify_post_rates_msg">'.$clasify_post_rates_msg.'</div></div>';
		$post['message'] .= $post['clasify_post_rates_msg'];		
	}
	else
	{
		$post['message'] .= '<div id="clasify_post_rates_msgs_list">'.$post['clasify_post_rates_msg'].'</div>';		
	}
}

function dnt_post_rate_xmlhttp()
{
	global $db, $lang, $thread, $mybb, $charset;
	
	if($mybb->get_input('action') == "clasify_post_rate")
	{
		header("Content-type: application/json; charset={$charset}");     
		$lang->load('dnt_post_rate',false,true);		
		$lid = (int)$mybb->input['lid'];
		$tid = (int)$mybb->input['tid'];
		$thread = get_thread($tid);
		$templates = "";
		$touid = (int)$thread['uid'];
		$uid = (int)$mybb->user['uid'];
		$pcl_total = (int)$thread['pcl_total'];
		$pcl_tot = (int)$thread['pcl_total']+1;
		$pcl_date = time() - (30 * 60 * 60 * 24);
		
		if($pcl_tot > 0)
		{
			$likes = $loves = $surprises = $smiles = $crys = $hungrys = 0;	
			$query = $db->simple_select('dnt_post_rate','*',"pcl_sender={$uid} AND pcl_tid='{$tid}' AND pcl_date>='{$pcl_date}'", array("limit"=>1));		
			if($db->num_rows($pcl_query) > 0)
			{
				$pcl_dataiu = "update";				
			}
			else
			{
				$pcl_dataiu = "insert";
				$pcl_count = 1;
			}
		}

		$insert_data = array(
			'pcl_type' => $db->escape_string($lid),
			'pcl_user' => $db->escape_string($touid),
			'pcl_sender' => $db->escape_string($uid),
			'pcl_count' => $db->escape_string($pcl_count),			
			'pcl_tid' => $db->escape_string($tid),
			'pcl_date' => time()
		);
		
		$update_data = array(
			'pcl_type' => $db->escape_string($lid),
			'pcl_count' => $db->escape_string($pcl_count),
			'pcl_date' => time()
		);
		
		if($pcl_dataiu == "insert")
			$db->insert_query("dnt_post_rate",$insert_data);
		else if($pcl_dataiu == "update")
			$db->update_query("dnt_post_rate",$update_data,"pcl_tid='{$tid}' AND pcl_sender='{$uid}'");
		$update_data = array(
			'pcl_total' => (int)$pcl_total+1,
		);
		if(isset($update_data))
		$db->update_query("threads",$update_data,"tid={$tid}");
	
		$pcl_query = $db->simple_select('dnt_post_rate','*',"pcl_tid='{$tid}' AND pcl_date>='{$pcl_date}'");			
		while($pcl_rows = $db->fetch_array($pcl_query))
		{
			$pcl_senderc = (int)$pcl_rows['pcl_sender'];
			$pcl_count = (int)$pcl_rows['pcl_count']+1;
			$pcl_type = (int)$pcl_rows['pcl_type'];
			if($pcl_type == 1)
			{
				$likes++;
			}
			if($pcl_type == 2)
			{
				$loves++;
			}
			if($pcl_type == 3)
			{
				$surprises++;
			}			
			if($pcl_type == 4)
			{
				$smiles++;
			}
			if($pcl_type == 5)
			{
				$crys++;
			}
			if($pcl_type == 6)
			{
				$hungrys++;				
			}
		}
		
		$pcl_results1 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/like.png" alt="Like" />';				
		$pcl_results2 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/love.png" alt="Love" />';		
		$pcl_results3 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/surprise.png" alt="Surprise" />';			
		$pcl_results4 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/smile.png" alt="Smile" />';				
		$pcl_results5 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/cry.png" alt="Cry" />';				
		$pcl_results6 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/hungry.png" alt="Hungry" />';
			
		if($likes > 0)
			$clasify_post_rates_msg .= "<span>".$likes."</span>".$pcl_results1;
		if($loves > 0)
			$clasify_post_rates_msg .= "<span>".$loves."</span>".$pcl_results2;		
		if($surprises > 0)
			$clasify_post_rates_msg .= "<span>".$surprises."</span>".$pcl_results3;
		if($smiles > 0)
			$clasify_post_rates_msg .= "<span>".$smiles."</span>".$pcl_results4;
		if($crys > 0)
			$clasify_post_rates_msg .= "<span>".$crys."</span>".$pcl_results5;
		if($hungrys > 0)
			$clasify_post_rates_msg .= "<span>".$hungrys."</span>".$pcl_results6;

		$templates = '<div class="clasify_post_rates_msg">'.$clasify_post_rates_msg.'</div>';

		$pcl_data = array('receive' => $pcl_dataiu,
			'post_rate_id' => (int)$lid,
			'post_rate_tid' => (int)$tid,
			'pcl_user' => (int)$touid,
			'pcl_sender' => (int)$uid,			
			'pcl_total' => (int)$pcl_total+1,
			'templates' => $templates
		);
		
		echo json_encode($pcl_data);
		exit;
	}
}
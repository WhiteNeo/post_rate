<?php
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook('postbit', 'dnt_post_rate_post_rates');
$plugins->add_hook('xmlhttp', 'dnt_post_rate_xmlhttp');
$plugins->add_hook('global_start', 'dnt_post_rate_script');
$plugins->add_hook('member_profile_end', 'dnt_post_rate_member');
$plugins->add_hook('forumdisplay_thread', 'dnt_post_rates');
$plugins->add_hook('admin_load', 'dnt_post_rate_admin_load');	
	
function dnt_post_rate_info()
{
	global $mybb, $dpr_config, $dpr_integrate;
	
	$dpr_verify = "";
	$dpr_integrate = "";
	$dpr_config = "";
	
	if(function_exists("myalerts_info") && $mybb->settings['dnt_post_rate_active'] == 1){
		$my_alerts_info = myalerts_info();
		$dpr_verify = $my_alerts_info['version'];	
		if(myalerts_is_activated() && !dnt_post_rate_myalerts_status() && $dpr_verify >= 2.0)
			$dpr_integrate = '<br /><a href="index.php?module=config-plugins&amp;action=dnt_post_rate_myalerts_integrate" style="float: right;">Integrate with MyAlerts</a>';			
	}
		
	if(isset($mybb->settings['dnt_post_rate_active']))
		$dpr_config = '<div style="float: right;"><a href="index.php?module=config&action=change&search=dnt_post_rate" style="color:#035488; background: url(../images/icons/brick.png) no-repeat 0px 18px; padding: 21px; text-decoration: none;">Configure</a></div>';
	
	return array(
		"name" => "Post Rate",
		"description" => "Clasify your post by users rate".$dpr_config.$dpr_integrate,
		"website" => "",
		"author" => "Whiteneo",
		"authorsite" => "https://soportemybb.es",
		"version" => "1.3",
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
	if(!$db->field_exists("pcl_total", "threads"))
	{
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."threads` ADD `pcl_total` int(10) NOT NULL DEFAULT '0'");
	}
	
	if(!$db->field_exists("pcl_rates", "threads"))
	{
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."threads` ADD `pcl_rates` text NOT NULL");
	}	
	
	if(function_exists("myalerts_info")){
		$my_alerts_info = myalerts_info();
		$verify = $my_alerts_info['version'];
		if($verify >= "2.0.0" && !dnt_post_rate_myalerts_status()){
			$myalerts_plugins = $cache->read('mybbstuff_myalerts_alert_types');
			if($myalerts_plugins['dntprt']['code'] != 'dntprt'){
			$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
				$alertType = new MybbStuff_MyAlerts_Entity_AlertType();
				$alertType->setCode('dntprt');
				$alertType->setEnabled(true);
			$alertTypeManager->add($alertType);
			}
		}	
	}	
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
		if($db->table_exists("pcl_rates", "dnt_post_rate"))
			$db->write_query('DROP TABLE `'.TABLE_PREFIX.'dnt_post_rate`');
		if($db->field_exists("pcl_total", "threads"))
			$db->write_query("ALTER TABLE `".TABLE_PREFIX."threads` DROP `pcl_total`");
		if($db->field_exists("pcl_rates", "threads"))
			$db->write_query("ALTER TABLE `".TABLE_PREFIX."threads` DROP `pcl_rates`");		
	}
	if(function_exists("myalerts_info")){
		$my_alerts_info = myalerts_info();
		$verify = $my_alerts_info['version'];
		if($verify >= "2.0.0" && dnt_post_rate_myalerts_status()){	
			if($db->table_exists("alert_types")){
				$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();
				$alertTypeManager->deleteByCode('dntprt');
			}
		}
	}	
}

function dnt_post_rate_is_installed()
{
	global $db, $mybb;

	if($db->field_exists("pcl_total", "threads"))
	{
		return true;
	}
	return false;		
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

	$new_config[] = array(
		'name' => 'dnt_post_rate_highlight',
		'title' => 'Highlight post with this ammount of rates',
		'description' => 'Set the ammount of rates given into a month to highlight a post',
		'optionscode' => 'numeric',
		'value' => 10,
		'disporder' => 4,
		'gid' => $group['gid']
	);

	$new_config[] = array(
		'name' => 'dnt_post_rate_limit',
		'title' => 'Limit to search data',
		'description' => 'Set limit in days to search into database, by default 30 days',
		'optionscode' => 'numeric',
		'value' => 30,
		'disporder' => 5,
		'gid' => $group['gid']
	);

	$new_config[] = array(
		'name' => 'dnt_post_rate_limit_users',
		'title' => 'Limit to search userlist',
		'description' => 'Set limit of max number of users to show into modal hover, by default 10',
		'optionscode' => 'numeric',
		'value' => 10,
		'disporder' => 6,
		'gid' => $group['gid']
	);

	$new_config[] = array(
		'name' => 'dnt_post_rate_only_firspost',
		'title' => 'Use only in first post',
		'description' => 'Use this mod only for the first post or all posts. Set to No if you wish to show all posts (It requires more querys)',
		'optionscode' => 'yesno',
		'value' => 1,
		'disporder' => 6,
		'gid' => $group['gid']
	);
	
	foreach($new_config as $array => $content)
	{
		$db->insert_query("settings", $content);
	}

	// Creating stylesheet...
	$stylesheet_css = '.post_rate_list{position: absolute;z-index: 9999;border: 2px solid #0F5579;background:#fff;margin-left: -230px;margin-top: -95px;border-radius: 10px;}
.post_rate_button{color: #fff;text-shadow: 1px 1px 1px #000;height: 26px;line-height: 26px;padding: 0 10px;text-decoration: none;margin-left: 4px;display: inline-block;cursor:pointer;background: #202020;border-radius: 4px;font-size: 13px;background: #0F5579 !important}
.post_rate_btn img{cursor:pointer;}
.post_rate_btn img:hover{width:70px;height:70px;margin-top:-95px;transition:all ease 0.5s;}
.ptr_list{display: none;position: absolute;background: #0b0a0a;color: #e4dada;padding: 6px;border-radius: 3px;font-size: 10px;}
.dnt_prt_ulist > span{display:block}
.pcl_list{text-shadow: 1px 1px 1px #000;padding: 10px;border-radius: 2px;-moz-border-radius: 2px;-webkit-border-radius: 2px;color: #fff;text-align:center;font-size: 13px;display: inline-block;}
.dnt_post_hl{background-color: rgba(83,168,65,0.33)}
.clasify_post_norates_msg{background-color: rgba(185, 65, 25, 0.3);float: right;margin: 5px;color: #6f2f16;font-weight: bold;font-size: 11px;padding: 10px;border-radius: 3px;}
.clasify_post_rates_msg{background-color: rgba(83,168,65,0.33);float: right;margin: 5px;color: #166f16;font-weight: bold;font-size: 11px;padding: 10px;border-radius: 3px;}
.clasify_post_rates_msg_span{font-size: 8px;font-weight: bold;position: absolute;background: #ce5757;padding: 1px 3px;color: #f0f0f0;border-radius: 4px;border-radius: 3px;margin-top: -5px;}';

	$stylesheet = array(
		"name"			=> "pcl.css",
		"tid"			=> 1,
		"attachedto"	=> "",		
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
	find_replace_templatesets("showthread", '#'.preg_quote('{$headerinclude}').'#', '{$headerinclude}{$dnt_prt_script}');
	find_replace_templatesets("forumdisplay_thread", '#'.preg_quote('{$attachment_count}').'#', '{$attachment_count}{$dnt_prt_rates}');		
	find_replace_templatesets("member_profile", '#'.preg_quote('{$headerinclude}').'#', '{$headerinclude}{$dnt_prt_script}');		
	find_replace_templatesets("member_profile", '#'.preg_quote('{$profilefields}').'#', '{$profilefields}{$memprofile[\'dnt_prt\']}');		
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
	$db->delete_query("settings", "name IN ('dnt_post_rate_active','dnt_post_rate_forums','dnt_post_rate_groups','dnt_post_rate_highlight')");
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
	find_replace_templatesets("showthread", '#'.preg_quote('{$dnt_prt_script}').'#', '', 0);
	find_replace_templatesets("forumdisplay_thread", '#'.preg_quote('{$dnt_prt_rates}').'#', '', 0);	
	find_replace_templatesets("member_profile", '#'.preg_quote('{$memprofile[\'dnt_prt\']}').'#', '', 0);		
	find_replace_templatesets("member_profile", '#'.preg_quote('{$dnt_prt_script}').'#', '', 0);		
	
	rebuild_settings();
}

function dnt_post_rate_myalerts_status()
{
	global $db, $cache;
    $myalerts_plugins = $cache->read('mybbstuff_myalerts_alert_types');
	if($myalerts_plugins['dntprt']['code'] == 'dntprt' && $myalerts_plugins['dntprt']['enabled'] == 1){
		return true;
	}
	return false;
}

function dnt_post_rate_admin_load()
{
	global $page, $mybb;
	if($mybb->input['action'] == 'dnt_post_rate_myalerts_integrate')
	{
		dnt_post_rate_myalerts_integrate();
		exit;
	}
}

function dnt_post_rate_myalerts_integrate(){
	global $db, $cache;
	if(function_exists("myalerts_info")){
		$my_alerts_info = myalerts_info();
		$verify = $my_alerts_info['version'];
		if($verify >= "2.0.0")
		{
			$myalerts_plugins = $cache->read('mybbstuff_myalerts_alert_types');
			if($myalerts_plugins['dntprt']['code'] != 'dntprt')
			{
				$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
				$alertType = new MybbStuff_MyAlerts_Entity_AlertType();
				$alertType->setCode('dntprt');
				$alertType->setEnabled(true);
			$alertTypeManager->add($alertType);
			flash_message("MyAlerts and Post Rate System were integrated succesfully", 'success');
			admin_redirect('index.php?module=config-plugins');			
			}
			else
			{
				flash_message("MyAlerts version is wrong and can not integrate with post rate system or already integrated", 'error');
				admin_redirect('index.php?module=config-plugins');			
			}
		}
		else
		{
			flash_message("MyAlerts is not working yet on your board, verify this and try again latter", 'error');
			admin_redirect('index.php?module=config-plugins');			
		}
	}	
}

function dnt_post_rate_script()
{
	global $mybb, $lang, $dnt_prt_script;
	if($mybb->settings['dnt_post_rate_active'] == 0)
	{
		return false;
	}
	$lang->load('dnt_post_rate',false,true);
	if(THIS_SCRIPT == "showthread.php" || THIS_SCRIPT == "member.php")
	{
	$dnt_prt_script = '<script type="text/javascript" src="'.$mybb->asset_url.'/jscripts/dnt_prt.js?ver=110"></script>
<script type="text/javascript">
	var dnt_prt_success = "'.$lang->pcl_rated.'";
</script>';
	}
	if((function_exists('myalerts_is_activated') && myalerts_is_activated()) && $mybb->user['uid']){
		global $cache, $formatterManager;
		$myalerts_plugins = $cache->read('mybbstuff_myalerts_alert_types');
		if($myalerts_plugins['dntprt']['code'] == 'dntprt' && $myalerts_plugins['dntprt']['enabled'] == 1){
			dnt_prt_alerts_formatter_load();	
			if (class_exists('MybbStuff_MyAlerts_AlertFormatterManager') && class_exists('PrtAlertFormatter')) {
				$code = 'dntprt';
				$formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();
				$formatterManager->registerFormatter(new PrtAlertFormatter($mybb, $lang, $code));
			}
		}
	}
}

function dnt_prt_alerts_formatter_load()
{
	global $mybb;
	if($mybb->settings['dnt_post_rate_active'] == 0)
	{
		return false;
	}
	class PrtAlertFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
	{
		public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
		{
		$alertContent = $alert->getExtraDetails();
		$postLink = $this->buildShowLink($alert);
		
			return $this->lang->sprintf(
				$this->lang->dnt_prt_alert,
				$outputAlert['from_user'],
				$alertContent['t_subject']
			);
		}
		public function init()
		{
			if (!$this->lang->dnt_post_rate) 
			{
				$this->lang->load('dnt_post_rate');
			}
		}
		public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
		{
			$alertContent = $alert->getExtraDetails();
			$threadLink = $this->mybb->settings['bburl'] . '/' . get_thread_link((int)$alertContent['tid']);              
				return $threadLink;
		}
	}
}

function recordAlertRpt($tid)
{
	global $db, $mybb, $alert, $thread;
	if($mybb->settings['dnt_post_rate_active'] == 0)
	{
		return false;
	}
	$thread = get_thread($tid);
	$uid = (int)$thread['uid'];
	$tid = (int)$thread['tid'];
	$subject = htmlspecialchars_uni($thread['subject']);
	$fid = (int)$thread['fid'];
	if(function_exists('myalerts_is_activated') && myalerts_is_activated())
	{
		myalerts_create_instances();
		$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();
		$alertType = $alertTypeManager->getByCode('dntprt');
		
		if(isset($alertType) && $alertType->getEnabled())
		{
			//check if already alerted
			$query = $db->simple_select(
				'alerts',
				'id',
				'object_id = ' .$tid . ' AND uid = ' . $uid . ' AND unread = 1 AND alert_type_id = ' . $alertType->getId() . ''
			);

			if ($db->num_rows($query) == 0) 
			{
				$alert = new MybbStuff_MyAlerts_Entity_Alert($uid, $alertType, $tid, $mybb->user['uid']);
				$alert->setExtraDetails(
					array(
						'tid' 		=> $tid,
						't_subject' => $subject,
						'fid'		=> $fid
					)); 
				MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
			}
		}
	}
}

function dnt_post_rate_post_rates(&$post)
{
	global $db, $mybb, $lang, $thread;

	if($mybb->settings['dnt_post_rate_active'] == 0)
	{
		return false;
	}
	$lang->load('dnt_post_rate',false,true);
	$pcl_firstpost = (int)$mybb->settings['dnt_post_rate_only_firspost'];	
	
	if($pcl_firstpost == 1 && $thread['firstpost'] != $post['pid'])
		return false;	
	
	$tid = (int)$thread['tid'];
	$fid = (int)$thread['fid'];
	$pcl_sender = (int)$mybb->user['uid'];
	$pcl_senderc = "-1";
	$limit_search = (int)$mybb->settings['dnt_post_rate_limit_users'];	
	$pcl_date_limit = time() - ($limit_search * 60 * 60 * 24);
	$pcl_date = " AND pcl_date>='{$pcl_date_limit}";
		
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
			$query = $db->simple_select('dnt_post_rate','*',"pcl_sender='{$pcl_sender}' AND pcl_tid='{$tid}'{$pcl_date}", array("limit"=>1));		
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
	$likes = $loves = $wow = $smiles = $crys = $angrys = 0;
	$pcl_query = $db->simple_select('dnt_post_rate','*',"pcl_tid='{$tid}'{$pcl_date}");
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
			$wow++;
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
			$angrys++;				
		}
	}

	if($pcl_see_me === true)
	{		
$post['clasify_post_rates'] = '<div class="post_rate_button" id="post_rates_btn">'.$lang->pcl_rate.'</div>
<div id="post_rates" class="post_rate_list" style="display:none;">
	<span onclick="javascript:DNTPostRate(1, '.$tid.')" class="post_rate_btn"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/like.gif" alt="'.$lang->pcl_like.'" title="'.$lang->pcl_like.'" /></span>
	<span onclick="javascript:DNTPostRate(2, '.$tid.')" class="post_rate_btn"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/love.gif" alt="'.$lang->pcl_love.'" title="'.$lang->pcl_love.'" /></span>
	<span onclick="javascript:DNTPostRate(3, '.$tid.')" class="post_rate_btn"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/wow.gif" alt="'.$lang->pcl_wow.'" title="'.$lang->pcl_wow.'" /></span>
	<span onclick="javascript:DNTPostRate(4, '.$tid.')" class="post_rate_btn"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/smile.gif" alt="'.$lang->pcl_smile.'" title="'.$lang->pcl_smile.'" /></span>
	<span onclick="javascript:DNTPostRate(5, '.$tid.')" class="post_rate_btn"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/cry.gif" alt="'.$lang->pcl_cry.'" title="'.$lang->pcl_cry.'" /></span>
	<span onclick="javascript:DNTPostRate(6, '.$tid.')" class="post_rate_btn"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/angry.gif" alt="'.$lang->pcl_angry.'" title="'.$lang->pcl_angry.'" /></span>	
</div>';
	}

	$clasify_post_rates_total = (int)$thread['pcl_total'];

	if($clasify_post_rates_total > 0)
	{
		$pcl_results1 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/like.png" alt="'.$lang->pcl_like.'" title="'.$lang->pcl_like.'" onmouseover="javascript:DNTPostRates(1, '.$tid.')" onmouseout="javascript:DNTPostRatesRemove(1, '.$tid.')" /><span id="prt_list1" class="ptr_list"></span>';
		$pcl_results2 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/love.png" alt="'.$lang->pcl_love.'" title="'.$lang->pcl_love.'" onmouseover="javascript:DNTPostRates(2, '.$tid.')" onmouseout="javascript:DNTPostRatesRemove(2, '.$tid.')" /><span id="prt_list2" class="ptr_list"></span>';
		$pcl_results3 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/wow.png" alt="'.$lang->pcl_wow.'" title="'.$lang->pcl_wow.'" onmouseover="javascript:DNTPostRates(3, '.$tid.')" onmouseout="javascript:DNTPostRatesRemove(3, '.$tid.')" /><span id="prt_list3" class="ptr_list"></span>';			
		$pcl_results4 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/smile.png" alt="'.$lang->pcl_smile.'" title="'.$lang->pcl_smile.'" onmouseover="javascript:DNTPostRates(4, '.$tid.')" onmouseout="javascript:DNTPostRatesRemove(4, '.$tid.')" /><span id="prt_list4" class="ptr_list"></span>';				
		$pcl_results5 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/cry.png" alt="'.$lang->pcl_cry.'" title="'.$lang->pcl_cry.'" onmouseover="javascript:DNTPostRates(5, '.$tid.')" onmouseout="javascript:DNTPostRatesRemove(5, '.$tid.')" /><span id="prt_list5" class="ptr_list"></span>';				
		$pcl_results6 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/angry.png" alt="'.$lang->pcl_angry.'" title="'.$lang->pcl_angry.'" onmouseover="javascript:DNTPostRates(6, '.$tid.')" onmouseout="javascript:DNTPostRatesRemove(6, '.$tid.')" /><span id="prt_list6" class="ptr_list"></span>';
		
		if($likes > 0)
			$post['dnt_likes'] = "<span class=\"clasify_post_rates_msg_span\">".$likes."</span>".$pcl_results1;
		if($loves > 0)
			$post['dnt_loves'] = "<span class=\"clasify_post_rates_msg_span\">".$loves."</span>".$pcl_results2;		
		if($wow > 0)
			$post['dnt_surprises'] = "<span class=\"clasify_post_rates_msg_span\">".$wow."</span>".$pcl_results3;
		if($smiles > 0)
			$post['dnt_smiles'] = "<span class=\"clasify_post_rates_msg_span\">".$smiles."</span>".$pcl_results4;
		if($crys > 0)
			$post['dnt_crys'] = "<span class=\"clasify_post_rates_msg_span\">".$crys."</span>".$pcl_results5;
		if($angrys > 0)
			$post['dnt_hungrys'] = "<span class=\"clasify_post_rates_msg_span\">".$angrys."</span>".$pcl_results6;
		if($mybb->settings['dnt_post_rate_highlight'] > 0)
		{
			$dnt_to_highlight = (int)$likes + (int)$loves + (int)$wow + (int)$smiles + (int)$crys + (int)$angrys;
			$dnt_to_compare = (int)$mybb->settings['dnt_post_rate_highlight'];			
			if($dnt_to_highlight >= $dnt_to_compare)
				$post['message'] = "<div class=\"dnt_post_hl\">{$post['message']}</div>";
		}
		
		$clasify_post_rates_msg = $post['dnt_likes'].$post['dnt_loves'].$post['dnt_surprises'].$post['dnt_smiles'].$post['dnt_crys'].$post['dnt_hungrys'];
		$lang->pcl_total = $lang->sprintf($lang->pcl_total, $clasify_post_rates_total);
		$post['clasify_post_rates_msg'] = '<div id="clasify_post_rates_msgs_list"><div class="clasify_post_rates_msg">'.$lang->pcl_total.$lang->pcl_rates."<br />".$clasify_post_rates_msg.'</div></div>';
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

	if($mybb->settings['dnt_post_rate_active'] == 0)
		return false;
  
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
		$pid = (int)$thread['pid'];
		$pcl_total = (int)$thread['pcl_total'];
		$pcl_tot = (int)$thread['pcl_total']+1;
		$limit_search = (int)$mybb->settings['dnt_post_rate_limit_users'];	
		$pcl_date_limit = time() - ($limit_search * 60 * 60 * 24);
		$pcl_date = " AND pcl_date>='{$pcl_date_limit}";
		$likes = $loves = $wow = $smiles = $crys = $angrys = 0;	
		$pcl_query = $db->simple_select('dnt_post_rate','*',"pcl_sender={$uid} AND pcl_tid='{$tid}'{$pcl_date}", array("limit"=>1));		
		if($db->num_rows($pcl_query) > 0)
		{
			$pcl_dataiu = "update";	
			$pcl_count = $pcl_query+1;
		}
		else
		{
			$pcl_dataiu = "insert";
			$pcl_count = 1;
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
		recordAlertRpt($tid);	
		$pcl_query = $db->simple_select('dnt_post_rate','*',"pcl_tid='{$tid}'{$pcl_date}");			
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
				$wow++;
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
				$angrys++;				
			}
		}
		
		$pcl_results1 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/like.png" alt="'.$lang->pcl_like.'" title="'.$lang->pcl_like.'" onmouseover="javascript:DNTPostRates(1, '.$tid.')" onmouseout="javascript:DNTPostRatesRemove(1, '.$tid.')" /><span id="prt_list1" class="ptr_list"></span>';
		$pcl_results2 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/love.png" alt="'.$lang->pcl_love.'" title="'.$lang->pcl_love.'" onmouseover="javascript:DNTPostRates(2, '.$tid.')" onmouseout="javascript:DNTPostRatesRemove(2, '.$tid.')" /><span id="prt_list2" class="ptr_list"></span>';
		$pcl_results3 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/wow.png" alt="'.$lang->pcl_wow.'" title="'.$lang->pcl_wow.'" onmouseover="javascript:DNTPostRates(3, '.$tid.')" onmouseout="javascript:DNTPostRatesRemove(3, '.$tid.')" /><span id="prt_list3" class="ptr_list"></span>';			
		$pcl_results4 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/smile.png" alt="'.$lang->pcl_smile.'" title="'.$lang->pcl_smile.'" onmouseover="javascript:DNTPostRates(4, '.$tid.')" onmouseout="javascript:DNTPostRatesRemove(4, '.$tid.')" /><span id="prt_list4" class="ptr_list"></span>';				
		$pcl_results5 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/cry.png" alt="'.$lang->pcl_cry.'" title="'.$lang->pcl_cry.'" onmouseover="javascript:DNTPostRates(5, '.$tid.')" onmouseout="javascript:DNTPostRatesRemove(5, '.$tid.')" /><span id="prt_list5" class="ptr_list"></span>';				
		$pcl_results6 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/angry.png" alt="'.$lang->pcl_angry.'" title="'.$lang->pcl_angry.'" onmouseover="javascript:DNTPostRates(6, '.$tid.')" onmouseout="javascript:DNTPostRatesRemove(6, '.$tid.')" /><span id="prt_list6" class="ptr_list"></span>';
			
		if($likes > 0)
			$clasify_post_rates_msg .= "<span class=\"clasify_post_rates_msg_span\">".$likes."</span>".$pcl_results1;
		if($loves > 0)
			$clasify_post_rates_msg .= "<span class=\"clasify_post_rates_msg_span\">".$loves."</span>".$pcl_results2;		
		if($wow > 0)
			$clasify_post_rates_msg .= "<span class=\"clasify_post_rates_msg_span\">".$wow."</span>".$pcl_results3;
		if($smiles > 0)
			$clasify_post_rates_msg .= "<span class=\"clasify_post_rates_msg_span\">".$smiles."</span>".$pcl_results4;
		if($crys > 0)
			$clasify_post_rates_msg .= "<span class=\"clasify_post_rates_msg_span\">".$crys."</span>".$pcl_results5;
		if($angrys > 0)
			$clasify_post_rates_msg .= "<span class=\"clasify_post_rates_msg_span\">".$angrys."</span>".$pcl_results6;

		$clasify_post_rates_total = (int)$thread['pcl_total'] + 1;		
		$lang->pcl_total = $lang->sprintf($lang->pcl_total, $clasify_post_rates_total);

		$templates = '<div class="clasify_post_rates_msg">'.$lang->pcl_total.$lang->pcl_rates.'<br />'.$clasify_post_rates_msg.'</div>';

		$thread_rates = array(
			'likes' => (int)$likes,
			'loves' => (int)$loves,
			'wow' => (int)$wow,
			'smiles' => (int)$smiles,
			'crys' => (int)$crys,
			'angry' => (int)$angrys	
		);

		$thread_rates = serialize($thread_rates);

		$update_data = array(
			'pcl_total' => (int)$pcl_total+1,
			"pcl_rates" => $thread_rates
		);
		if(isset($update_data))
		$db->update_query("threads",$update_data,"tid={$tid}");
		
		$pcl_data = array(
			'receive' => $pcl_dataiu,
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
	else if($mybb->get_input('action') == "get_post_rates")
	{
		header("Content-type: application/json; charset={$charset}");     
		$lang->load('dnt_post_rate',false,true);		
		$lid = (int)$mybb->input['lid'];
		$tid = (int)$mybb->input['tid'];
		$thread = get_thread($tid);
		$limit_users = (int)$mybb->settings['dnt_post_rate_limit_users'];
		$limit_search = (int)$mybb->settings['dnt_post_rate_limit_users'];	
		$pcl_date_limit = time() - ($limit_search * 60 * 60 * 24);
		$pcl_date = " AND pcl_date>='{$pcl_date_limit}";		
		$templates = "";
		$pcl_query = $db->query("SELECT dp.*, u.username FROM ".TABLE_PREFIX."dnt_post_rate dp
		LEFT JOIN ".TABLE_PREFIX."users u
		ON (dp.pcl_sender=u.uid)
		WHERE pcl_tid='{$tid}' AND pcl_type='{$lid}'{$pcl_date}'
		ORDER BY pcl_date DESC LIMIT {$limit_users}");
		while($pcl_rows = $db->fetch_array($pcl_query))
		{
			$uname = htmlspecialchars_uni($pcl_rows['username']);
			if(empty($uname))
				$uname = $lang->guest;
			$dnt_pcl_uname .= "<span>".$uname."</span>";			
		}
		$templates = "<div class=\"dnt_prt_ulist\">{$dnt_pcl_uname}</div>";		
		echo json_encode($templates);
		exit;		
	}
}

function dnt_post_rate_member()
{
	global $db, $lang, $mybb, $memprofile;
	$lang->load('dnt_post_rate',false,true);		
	$templates = "";
	$limit_search = (int)$mybb->settings['dnt_post_rate_limit_users'];	
	$pcl_date_limit = time() - ($limit_search * 60 * 60 * 24);
	$pcl_date = " AND pcl_date>='{$pcl_date_limit}";
	$pcl_query = $db->simple_select('threads','*',"uid='{$memprofile['uid']}' AND pcl_total>0 ORDER BY pcl_total DESC LIMIT 1");
	while($thread = $db->fetch_array($pcl_query))
	{
		$tid = (int)$thread['tid'];
		$subject = htmlspecialchars_uni($thread['subject']);
		$subject_link = get_thread_link($tid);
		$subject = "<a href=\"{$subject_link}\">{$subject}</a>";
		$total = (int)$thread['pcl_total'];
	}
	if(isset($tid))
	{
		$pcl_query = $db->simple_select('dnt_post_rate','*',"pcl_tid='{$tid}'{$pcl_date}");			
		while($pcl_rows = $db->fetch_array($pcl_query))
		{
			$pcl_senderc = (int)$pcl_rows['pcl_sender'];
			$pcl_count = (int)$pcl_rows['pcl_count'];
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
				$wow++;
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
				$angrys++;				
			}
		}
		
		$pcl_results1 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/like.png" alt="'.$lang->pcl_like.'" title="'.$lang->pcl_like.'" onmouseover="javascript:DNTPostRates(1, '.$tid.')" onmouseout="javascript:DNTPostRatesRemove(1, '.$tid.')" /><span id="prt_list1" class="ptr_list"></span>';
		$pcl_results2 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/love.png" alt="'.$lang->pcl_love.'" title="'.$lang->pcl_love.'" onmouseover="javascript:DNTPostRates(2, '.$tid.')" onmouseout="javascript:DNTPostRatesRemove(2, '.$tid.')" /><span id="prt_list2" class="ptr_list"></span>';
		$pcl_results3 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/wow.png" alt="'.$lang->pcl_wow.'" title="'.$lang->pcl_wow.'" onmouseover="javascript:DNTPostRates(3, '.$tid.')" onmouseout="javascript:DNTPostRatesRemove(3, '.$tid.')" /><span id="prt_list3" class="ptr_list"></span>';			
		$pcl_results4 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/smile.png" alt="'.$lang->pcl_smile.'" title="'.$lang->pcl_smile.'" onmouseover="javascript:DNTPostRates(4, '.$tid.')" onmouseout="javascript:DNTPostRatesRemove(4, '.$tid.')" /><span id="prt_list4" class="ptr_list"></span>';				
		$pcl_results5 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/cry.png" alt="'.$lang->pcl_cry.'" title="'.$lang->pcl_cry.'" onmouseover="javascript:DNTPostRates(5, '.$tid.')" onmouseout="javascript:DNTPostRatesRemove(5, '.$tid.')" /><span id="prt_list5" class="ptr_list"></span>';				
		$pcl_results6 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/angry.png" alt="'.$lang->pcl_angry.'" title="'.$lang->pcl_angry.'" onmouseover="javascript:DNTPostRates(6, '.$tid.')" onmouseout="javascript:DNTPostRatesRemove(6, '.$tid.')" /><span id="prt_list6" class="ptr_list"></span>';
			
		if($likes > 0)
			$clasify_post_rates_msg .= "<span class=\"clasify_post_rates_msg_span\">".$likes."</span>".$pcl_results1;
		if($loves > 0)
			$clasify_post_rates_msg .= "<span class=\"clasify_post_rates_msg_span\">".$loves."</span>".$pcl_results2;		
		if($wow > 0)
			$clasify_post_rates_msg .= "<span class=\"clasify_post_rates_msg_span\">".$wow."</span>".$pcl_results3;
		if($smiles > 0)
			$clasify_post_rates_msg .= "<span class=\"clasify_post_rates_msg_span\">".$smiles."</span>".$pcl_results4;
		if($crys > 0)
			$clasify_post_rates_msg .= "<span class=\"clasify_post_rates_msg_span\">".$crys."</span>".$pcl_results5;
		if($angrys > 0)
			$clasify_post_rates_msg .= "<span class=\"clasify_post_rates_msg_span\">".$angrys."</span>".$pcl_results6;
		$lang->pcl_total_best = $lang->sprintf($lang->pcl_total_best, $total);
			$templates = '<div class="clasify_post_rates_msg">'.$lang->pcl_total_best.'<BR />'.$subject.'<br />'.$clasify_post_rates_msg.'</div>';
	}
	else
		$templates = '<div class="clasify_post_norates_msg">'.$lang->pcl_dont_rates.'</div>';
	
	$memprofile['dnt_prt'] = $templates;
}

function dnt_post_rates()
{
	global $mybb, $thread, $dnt_prt_rates;
	$dnt_prt_rates = "";
	$thread['pcl_rates'] = unserialize($thread['pcl_rates']);
	$pcl_results1 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/like.png" alt="'.$lang->pcl_like.'" title="'.$lang->pcl_like.'" />';
	$pcl_results2 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/love.png" alt="'.$lang->pcl_love.'" title="'.$lang->pcl_love.'" />';
	$pcl_results3 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/wow.png" alt="'.$lang->pcl_wow.'" title="'.$lang->pcl_wow.'" />';			
	$pcl_results4 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/smile.png" alt="'.$lang->pcl_smile.'" title="'.$lang->pcl_smile.'" />';				
	$pcl_results5 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/cry.png" alt="'.$lang->pcl_cry.'" title="'.$lang->pcl_cry.'" />';				
	$pcl_results6 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/angry.png" alt="'.$lang->pcl_angry.'" title="'.$lang->pcl_angry.'" />';
	$likes = (int)$thread['pcl_rates']['likes'];
	$loves = (int)$thread['pcl_rates']['loves'];
	$wow = (int)$thread['pcl_rates']['wow'];
	$smiles = (int)$thread['pcl_rates']['smiles'];
	$crys = (int)$thread['pcl_rates']['crys'];
	$angrys = (int)$thread['pcl_rates']['angrys'];
	$dnt_prt_rates = '<div style="float:right">';
	if($likes > 0)
		$dnt_prt_rates .= "<span class=\"clasify_post_rates_msg_span\">".$likes."</span>".$pcl_results1;
	if($loves > 0)
		$dnt_prt_rates .= "<span class=\"clasify_post_rates_msg_span\">".$loves."</span>".$pcl_results2;		
	if($wow > 0)
		$dnt_prt_rates .= "<span class=\"clasify_post_rates_msg_span\">".$wow."</span>".$pcl_results3;
	if($smiles > 0)
		$dnt_prt_rates .= "<span class=\"clasify_post_rates_msg_span\">".$smiles."</span>".$pcl_results4;
	if($crys > 0)
		$dnt_prt_rates .= "<span class=\"clasify_post_rates_msg_span\">".$crys."</span>".$pcl_results5;
	if($angrys > 0)
		$dnt_prt_rates .= "<span class=\"clasify_post_rates_msg_span\">".$angrys."</span>".$pcl_results6;
	$dnt_prt_rates .= '</div>';
	unset($thread['pcl_rates']);
}

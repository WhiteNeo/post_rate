<?php
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}
if(defined("IN_ADMINCP"))
{
	$plugins->add_hook('admin_tools_action_handler', 'dnt_post_rate_admin_action');
	$plugins->add_hook('admin_tools_menu', 'dnt_post_rate_admin_menu');
	$plugins->add_hook('admin_tools_permissions', 'dnt_post_rate_admin_permissions');
	$plugins->add_hook('admin_load', 'dnt_post_rate_admin');
	$plugins->add_hook('admin_load', 'dnt_post_rate_admin_load');	
}
else
{
	$plugins->add_hook('postbit', 'dnt_post_rate_post_rates');
	$plugins->add_hook('xmlhttp', 'dnt_post_rate_xmlhttp');
	$plugins->add_hook('global_start', 'dnt_post_rate_script');
	$plugins->add_hook('member_profile_end', 'dnt_post_rate_member');
	$plugins->add_hook('forumdisplay_thread', 'dnt_post_rates');
	$plugins->add_hook('datahandler_post_insert_thread','dnt_post_rate_insert_thread');
	$plugins->add_hook('datahandler_post_insert_post','dnt_post_rate_insert_post');	
}

function dnt_post_rate_info()
{
	global $mybb, $lang, $dpr_config, $dpr_integrate;
	$lang->load('dnt_post_rate',false,true);	
	
	$dpr_verify = "";
	$dpr_integrate = "";
	$dpr_config = "";
	
	if(function_exists("myalerts_info") && $mybb->settings['dnt_post_rate_active'] == 1){
		$my_alerts_info = myalerts_info();
		$dpr_verify = $my_alerts_info['version'];	
		if(myalerts_is_activated() && !dnt_post_rate_myalerts_status() && $dpr_verify >= 2.0)
			$dpr_integrate = '<br /><a href="index.php?module=config-plugins&amp;action=dnt_post_rate_myalerts_integrate" style="float: right;">'.htmlspecialchars_uni($lang->pcl_integrate_alerts).'</a>';			
	}
		
	if(isset($mybb->settings['dnt_post_rate_active']))
		$dpr_config = '<div style="float: right;"><a href="index.php?module=config&amp;action=change&amp;search=dnt_post_rate" style="color:#035488; background: url(../images/icons/brick.png) no-repeat 0px 18px; padding: 21px; text-decoration: none;">'.htmlspecialchars_uni($lang->pcl_configure).'</a></div>';

	if(!isset($mybb->settings['dnt_post_rate_version']))
		$dpr_config .= '<div style="float: right;"><a href="index.php?module=config-plugins&amp;action=dnt_post_rate_verify_update" style="color:#035488; padding: 21px; text-decoration: none;">'.htmlspecialchars_uni($lang->pcl_update_to_15).'</a></div>';
	else if($mybb->settings['dnt_post_rate_version'] > 140)
		$dpr_config .= "";
	
	return array(
		"name" => "Post Rate",
		"description" => "Clasify your post by users rate".$dpr_config.$dpr_integrate,
		"website" => "",
		"author" => "Whiteneo",
		"authorsite" => "https://soportemybb.es",
		"version" => "1.5",
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
  `pcl_pid` int(10) NOT NULL default '0', 
  `pcl_count` int(10) NOT NULL default '0', 
  `pcl_date` int(10) NOT NULL default '0',
  PRIMARY KEY  (`pcl_id`)
) DEFAULT CHARSET={$charset};";
	$db->write_query($tables);
	if(!$db->field_exists("pcl_total", "threads"))
	{
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."threads` ADD `pcl_total` int(10) NOT NULL DEFAULT '0'");
	}
	
	if(!$db->field_exists("pcl_rates_threads", "threads"))
	{
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."threads` ADD `pcl_rates_threads` text NOT NULL");
	}	

	if(!$db->field_exists("pcl_rates_posts", "posts"))
	{
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."posts` ADD `pcl_rates_posts` text NOT NULL");
	}

	if(!$db->field_exists("pcl_rates_given", "users"))
	{
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."users` ADD `pcl_rates_given` int(10) NOT NULL DEFAULT '0'");
	}

	if(!$db->field_exists("pcl_rates_received", "users"))
	{
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."users` ADD `pcl_rates_received` int(10) NOT NULL DEFAULT '0'");
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
		$lang->load('dnt_post_rate', false, true);
		$page->output_confirm_action('index.php?module=config-plugins&action=deactivate&uninstall=1&plugin=dnt_post_rate', $lang->pcl_remove_data, $lang->pcl_remove_data_desc);
	}
	if(!isset($mybb->input['no']))
	{
		if($db->table_exists("dnt_post_rate"))
			$db->write_query('DROP TABLE `'.TABLE_PREFIX.'dnt_post_rate`');
		if($db->field_exists("pcl_rates_posts", "posts"))
			$db->write_query("ALTER TABLE `".TABLE_PREFIX."posts` DROP `pcl_rates_posts`");
		if($db->field_exists("pcl_rates_threads", "threads"))
			$db->write_query("ALTER TABLE `".TABLE_PREFIX."threads` DROP `pcl_rates_threads`");
		if($db->field_exists("pcl_total", "threads"))
			$db->write_query("ALTER TABLE `".TABLE_PREFIX."threads` DROP `pcl_total`");
		if($db->field_exists("pcl_rates_given", "users"))
			$db->write_query("ALTER TABLE `".TABLE_PREFIX."users` DROP `pcl_rates_given`");
		if($db->field_exists("pcl_rates_received", "posts"))
			$db->write_query("ALTER TABLE `".TABLE_PREFIX."users` DROP `pcl_rates_received`");		
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

	if($db->table_exists("dnt_post_rate"))
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
	$stylesheet_css = '.post_rate_list{position:absolute;z-index:9999;border:2px solid #0F5579;background:#fff;right:80px;margin-top:-95px;border-radius:10px}
.post_rate_button{color:#fff;text-shadow:1px 1px 1px #000;height:26px;line-height:26px;padding:0 10px;text-decoration:none;margin-left:4px;display:inline-block;cursor:pointer;background:#202020;border-radius:4px;font-size:13px;background:#0F5579 !important}
.post_rate_btn img{cursor:pointer}
.post_rate_btn img:hover{border: 1px dotted #202020}
.ptr_list{display:none;position:absolute;background:#0b0a0a;color:#e4dada;padding:6px;border-radius:3px;font-size:10px}
.dnt_prt_list{height: 40px;position: relative}
.dnt_prt_list_span {padding: 0 40px}
.dnt_prt_ulist > span{display:block}
.pcl_list{text-shadow:1px 1px 1px #000;padding:10px;border-radius:2px;-moz-border-radius:2px;-webkit-border-radius:2px;color:#fff;text-align:center;font-size:13px;display:inline-block}
.dnt_post_hl{background-color:rgba(25,119,150,0.3);margin:5px;border-radius:3px;border-left:2px solid #4d5e77}
.pcl_div_rate{display:inline-block !important;cursor:pointer}
.pcl_div_rate img{width:19px;height:19px;position:absolute}
.pcl_div_rate span{margin-left:20px;font-weight:bold}
.pcl_list_avatar{padding: 3px;border: 1px solid #D8DFEA;width: 30px;height: 30px;border-radius: 50%;margin: 0px 5px -15px 2px;position: relative}
.clasify_post_norates_msg{background-color:rgba(185,65,25,0.3);float:right;margin:5px;color:#6f2f16;font-weight:bold;font-size:11px;padding:10px;border-radius:3px}
.clasify_post_rates_msg{background-color:rgba(102,189,218,0.3);margin:5px;color:#315284;font-weight:bold;font-size:11px;padding:10px;border-radius:3px}
.clasify_post_rates_msg_span{font-size:8px;font-weight:bold;position:absolute;background:#ce5757;padding:1px 3px;color:#f0f0f0;border-radius:4px;border-radius:3px;margin-top:-5px}';

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
	// Add templates:
	dnt_prt_templates_make();
	// Edit some existing templates
	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("postbit", '#'.preg_quote('{$post[\'button_edit\']}').'#', '{$post[\'clasify_post_rates\']}{$post[\'button_edit\']}');
	find_replace_templatesets("postbit_classic", '#'.preg_quote('{$post[\'button_edit\']}').'#', '{$post[\'clasify_post_rates\']}{$post[\'button_edit\']}');	
	find_replace_templatesets("headerinclude", '#'.preg_quote('{$stylesheets}').'#', '{$stylesheets}{$dnt_prt_script}');
	find_replace_templatesets("forumdisplay_thread", '#'.preg_quote('{$attachment_count}').'#', '{$attachment_count}{$dnt_prt_rates}');		
	find_replace_templatesets("member_profile", '#'.preg_quote('{$profilefields}').'#', '{$profilefields}{$memprofile[\'dnt_prt\']}{$memprofile[\'pcl_rates_given\']}{$memprofile[\'pcl_rates_received\']}');		
	find_replace_templatesets("postbit", '#'.preg_quote('{$post[\'user_details\']}').'#', '{$post[\'user_details\']}{$post[\'pcl_rates_given\']}{$post[\'pcl_rates_received\']}', 0);
	find_replace_templatesets("postbit_classic", '#'.preg_quote('{$post[\'user_details\']}').'#', '{$post[\'user_details\']}{$post[\'pcl_rates_given\']}{$post[\'pcl_rates_received\']}', 0);	
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
	$db->delete_query("settings", "name IN ('dnt_post_rate_active','dnt_post_rate_forums','dnt_post_rate_groups','dnt_post_rate_highlight','dnt_post_rate_limit','dnt_post_rate_limit_users','dnt_post_rate_only_firspost')");
	$db->delete_query("settinggroups", "name='dnt_post_rate'");
   // Delete stylesheet
   	$db->delete_query('themestylesheets', "name='pcl.css'");
	$query = $db->simple_select('themes', 'tid');
	while($theme = $db->fetch_array($query))
	{
		require_once MYBB_ADMIN_DIR.'inc/functions_themes.php';
		update_theme_stylesheet_list($theme['tid']);
	}	
	//Remove templates:
	dnt_prt_templates_remove();
	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("postbit", '#'.preg_quote('{$post[\'clasify_post_rates\']}').'#', '', 0);
	find_replace_templatesets("postbit_classic", '#'.preg_quote('{$post[\'clasify_post_rates\']}').'#', '', 0);	
	find_replace_templatesets("showthread", '#'.preg_quote('{$dnt_prt_script}').'#', '', 0);
	find_replace_templatesets("forumdisplay_thread", '#'.preg_quote('{$dnt_prt_rates}').'#', '', 0);	
	find_replace_templatesets("member_profile", '#'.preg_quote('{$memprofile[\'dnt_prt\']}').'#', '', 0);		
	find_replace_templatesets("member_profile", '#'.preg_quote('{$dnt_prt_script}').'#', '', 0);		
	find_replace_templatesets("headerinclude", '#'.preg_quote('{$dnt_prt_script}').'#', '', 0);
	find_replace_templatesets("postbit", '#'.preg_quote('{$post[\'pcl_rates_given\']}').'#', '', 0);
	find_replace_templatesets("postbit", '#'.preg_quote('{$post[\'pcl_rates_received\']}').'#', '', 0);
	find_replace_templatesets("postbit_classic", '#'.preg_quote('{$post[\'pcl_rates_given\']}').'#', '', 0);
	find_replace_templatesets("postbit_classic", '#'.preg_quote('{$post[\'pcl_rates_received\']}').'#', '', 0);
	find_replace_templatesets("memprofile", '#'.preg_quote('{$memprofile[\'pcl_rates_given\']}').'#', '', 0);
	find_replace_templatesets("memprofile", '#'.preg_quote('{$memprofile[\'pcl_rates_received\']}').'#', '', 0);
	
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
	if($mybb->input['action'] == 'dnt_post_rate_verify_update')
	{
		dnt_post_rate_verify_update();
		exit;
	}	
}

function dnt_prt_templates_make()
{
	global $db;
	// Adding new group of templates for this plugin...  
	$templategrouparray = array(
		'prefix' => 'dnt_prt',
		'title'  => 'Post Rate'
	);
	$db->insert_query("templategroups", $templategrouparray);

	$templatearray = array(
		'title' => 'dnt_prt_page',
		'template' => "<html>
	<head>
		<title>{\$mybb->settings[\'bbname\']} - {\$lang->pcl_rates_page_title}</title>
		{\$headerinclude}
	</head>
	<body>
		{\$header}
		{\$content}
		{\$multipage}
		{\$footer}
	</body>
</html>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
		);
	$db->insert_query("templates", $templatearray);	

		$templatearray = array(
		'title' => 'dnt_prt_content',
		'template' => "<div class=\"tborder\">
		<div class=\"thead\" colspan=\"5\">
			<strong>{\$lang->pcl_rates}</strong>
		</div>
	</div>
	{\$dnt_prt_list}
</table>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
		);
	$db->insert_query("templates", $templatearray);	
	
	// Adding every template needed ...
	$templatearray = array(
		'title' => 'dnt_prt_list',
		'template' => "<div class=\"dnt_prt_list {\$trow}\">
{\$pcl_rows[\'avatar\']}<span class=\"dnt_prt_list\">{\$pcl_rows[\'username\']}{\$lang->pcl_has_rated}{\$pcl_rows[\'runame\']}{\$lang->pcl_with}{\$pcl_rows[\'rate\']}{\$lang->pcl_in_this_post}<a href=\"{\$pcl_rows[\'url\']}\">{\$pcl_rows[\'subject\']}</a><span class=\"smalltext\">{\$pcl_rows[\'date\']} ({\$pcl_rows[\'time\']})</span></span>
</div>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
		);	
	$db->insert_query("templates", $templatearray);	
	
	$templatearray = array(
		'title' => 'dnt_prt_list_none',
		'template' => "<div class=\"dnt_prt_ulist {\$trow}\">
	<span>{\$lang->pcl_not_received}</span>
</div>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
		);	
	$db->insert_query("templates", $templatearray);	
}

function dnt_prt_templates_remove()
{
	global $db;
	
	$db->delete_query("templategroups", "prefix='dnt_prt'");
	$db->delete_query("templates", "title LIKE('dnt_prt_%')");		
}

function dnt_post_rate_myalerts_integrate(){
	global $db, $lang, $cache;
	$lang->load('dnt_post_rate', false, true);	
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
			flash_message($lang->pcl_alerts_integrated_success, 'success');
			admin_redirect('index.php?module=config-plugins');			
			}
			else
			{
				flash_message($lang->pcl_alerts_integrated_wrong, 'error');
				admin_redirect('index.php?module=config-plugins');			
			}
		}
		else
		{
			flash_message($lang->pcl_alerts_integrated_none, 'error');
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
	$dnt_prt_script = "";
	if(THIS_SCRIPT == "showthread.php" || THIS_SCRIPT == "member.php" || THIS_SCRIPT == "forumdisplay.php")
	{
	$dnt_prt_script = '<script type="text/javascript" src="'.$mybb->asset_url.'/jscripts/dnt_prt.js?ver=140"></script>
<script type="text/javascript">
	var dnt_prt_success = "'.$lang->pcl_rated.'";
	var dnt_prt_remove_question = "'.$lang->pcl_remove_rate_question.'";
	var dnt_prt_remove_success = "'.$lang->pcl_remove_rate.'";
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
	global $mybb, $alertContent;
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
			if (!$this->lang->thx) 
			{
				$this->lang->load('thx');
			}
		}
		public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
		{
			$alertContent = $alert->getExtraDetails();
			$postLink = $this->mybb->settings['bburl'] . '/' . get_post_link((int)$alertContent['pid'], (int)$alertContent['tid']).'#pid'.(int)$alertContent['pid'];              
			return $postLink;
		}		
	}
}

function recordAlertRpt($tid, $pid)
{
	global $db, $mybb, $alert, $post;
	if($mybb->settings['dnt_post_rate_active'] == 0 || !$mybb->user['uid'])
	{
		return false;
	}
	$tid = (int)$mybb->input['tid'];
	$pid = (int)$mybb->input['pid'];
	$post = get_post($pid);
	$uid = (int)$post['uid'];
	$subject = htmlspecialchars_uni($post['subject']);
	$fid = (int)$post['fid'];
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
				'object_id = ' .$pid . ' AND uid = ' . $uid . ' AND unread = 1 AND alert_type_id = ' . $alertType->getId() . ''
			);

			if ($db->num_rows($query) == 0) 
			{
				$alert = new MybbStuff_MyAlerts_Entity_Alert($uid, $alertType, $pid, $mybb->user['uid']);
				$alert->setExtraDetails(
					array(
						'tid' 		=> $tid,
						'pid'		=> $pid,						
						't_subject' => $subject,
						'fid'		=> $fid
					)); 
				MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
			}
		}
	}
}

function removeAlertRpt($tid, $pid)
{
	global $db, $mybb;
	if($mybb->settings['dnt_post_rate_active'] == 0)
	{
		return false;
	}
	$tid = (int)$mybb->input['tid'];
	$pid = (int)$mybb->input['pid'];
	$uid = (int)$mybb->user['uid'];
	$db->delete_query("alerts","from_user_id='{$uid}' AND object_id='{$pid}' AND unread=1 LIMIT 1");
}

function dnt_post_rate_post_rates(&$post)
{
	global $db, $mybb, $lang, $thread;

	if($mybb->settings['dnt_post_rate_active'] == 0 || THIS_SCRIPT != "showthread.php")
	{
		return false;
	}
	$lang->load('dnt_post_rate',false,true);
	$pcl_firstpost = (int)$mybb->settings['dnt_post_rate_only_firspost'];	
	
	if($pcl_firstpost == 1 && $thread['firstpost'] != $post['pid'])
		return false;	

	$tid = (int)$post['tid'];
	$fid = (int)$post['fid'];
	$pid = (int)$post['pid'];
	$pcl_sender = (int)$mybb->user['uid'];
	$pcl_senderc = "-1";
	$limit_search = (int)$mybb->settings['dnt_post_rate_limit_users'];
	$pcl_date_limit = time() - ($limit_search * 60 * 60 * 24);
	$pcl_date = " AND pcl_date>='{$pcl_date_limit}'";
	$post['pcl_see_me'] = false;
	if($pcl_firstpost == 0)
	{
		$url_given = $mybb->settings['bburl'].'/dnt_post_rate.php?action=get_given_rates&amp;uid='.(int)$post['uid'];
		$url_received = $mybb->settings['bburl'].'/dnt_post_rate.php?action=get_received_rates&amp;uid='.(int)$post['uid'];	
		$post['pcl_rates_given'] = $lang->sprintf($lang->pcl_rates_given,(int)$post['pcl_rates_given'], $url_given);
		$post['pcl_rates_received'] = $lang->sprintf($lang->pcl_rates_received,(int)$post['pcl_rates_received'], $url_received);		
	}
	else if($pcl_firstpost == 1)
	{
		if($thread['firstpost'] != $post['pid'])
		{
			$post['pcl_rates_given'] = "";
			$post['pcl_rates_received'] = "";			
		}
		else
		{
			$url_given = $mybb->settings['bburl'].'/dnt_post_rate.php?action=get_given_rates&amp;uid='.(int)$post['uid'];
			$url_received = $mybb->settings['bburl'].'/dnt_post_rate.php?action=get_received_rates&amp;uid='.(int)$post['uid'];	
			$post['pcl_rates_given'] = $lang->sprintf($lang->pcl_rates_given,(int)$post['pcl_rates_given'], $url_given);
			$post['pcl_rates_received'] = $lang->sprintf($lang->pcl_rates_received,(int)$post['pcl_rates_received'], $url_received);			
		}		
	}
	
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
		
		$pcl_fids = explode(",",$mybb->settings['dnt_post_rate_forums']);
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
		$post['pcl_see_me'] = false;		
	}
	else if($mybb->settings['dnt_post_rate_groups'] == "-1")
		$post['pcl_see_me'] = true;	
	if($post['uid'] == $mybb->user['uid'])
	{
		$post['pcl_see_me'] = false;
	}
	else
	{
		if($mybb->user['uid'] != $post['uid'])
		{
			$query = $db->simple_select('dnt_post_rate','*',"pcl_sender='{$pcl_sender}' AND pcl_tid='{$tid}' AND pcl_pid='{$pid}'{$pcl_date}", array("limit"=>1));
			if ($db->num_rows($query) > 0)
			{
				$post['pcl_see_me'] = false;
				while($pcl_result = $db->fetch_array($query))
				{
					if($pcl_result['pcl_type'] == 1)
						$pcl_results = '<div onclick="javascript:DNTRemoveRate(1,'.$tid.','.$pid.')" id="post_rates_btn_'.$pid.'" class="pcl_div_rate"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/like.png" alt="'.$lang->pcl_like.'" title="'.$lang->pcl_like.'" /><span style="color:#1b43b6;">'.$lang->pcl_like.'</span></div>';
					if($pcl_result['pcl_type'] == 2)
						$pcl_results = '<div onclick="javascript:DNTRemoveRate(2,'.$tid.','.$pid.')" id="post_rates_btn_'.$pid.'" class="pcl_div_rate"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/love.png" alt="'.$lang->pcl_love.'" title="'.$lang->pcl_love.'" /><span style="color: #e61b3f;">'.$lang->pcl_love.'</span></div>';
					if($pcl_result['pcl_type'] == 3)
						$pcl_results = '<div onclick="javascript:DNTRemoveRate(3,'.$tid.','.$pid.')" id="post_rates_btn_'.$pid.'" class="pcl_div_rate"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/wow.png" alt="'.$lang->pcl_wow.'" title="'.$lang->pcl_wow.'" /><span style="color:#cfcd35;">'.$lang->pcl_wow.'</span></div>';
					if($pcl_result['pcl_type'] == 4)
						$pcl_results = '<div onclick="javascript:DNTRemoveRate(4,'.$tid.','.$pid.')" id="post_rates_btn_'.$pid.'" class="pcl_div_rate"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/smile.png" alt="'.$lang->pcl_smile.'" title="'.$lang->pcl_smile.'" /><span style="color:#cfcd35;">'.$lang->pcl_smile.'</span></div>';
					if($pcl_result['pcl_type'] == 5)				
						$pcl_results = '<div onclick="javascript:DNTRemoveRate(5,'.$tid.','.$pid.')" id="post_rates_btn_'.$pid.'" class="pcl_div_rate"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/cry.png" alt="'.$lang->pcl_cry.'" title="'.$lang->pcl_cry.'" /><span style="color:#1b43b6;">'.$lang->pcl_cry.'</span></div>';				
					if($pcl_result['pcl_type'] == 6)
						$pcl_results = '<div onclick="javascript:DNTRemoveRate(6,'.$tid.','.$pid.')" id="post_rates_btn_'.$pid.'" class="pcl_div_rate"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/angry.png" alt="'.$lang->pcl_angry.'" title="'.$lang->pcl_angry.'" /><span style="color:#c22e0f;">'.$lang->pcl_angry.'</span></div>';				
					$post['clasify_post_rates']	= $pcl_results;
				}				
			}
			else
			{
				$post['pcl_see_me'] = true;
			}		
		}
	}
	
	$pcl_user = (int)$post['uid'];
	$post['pcl_rates_posts'] = unserialize($post['pcl_rates_posts']);
	$likes = (int)$post['pcl_rates_posts']['likes'];
	$loves = (int)$post['pcl_rates_posts']['loves'];
	$wow = (int)$post['pcl_rates_posts']['wow'];
	$smiles = (int)$post['pcl_rates_posts']['smiles'];
	$crys = (int)$post['pcl_rates_posts']['crys'];
	$angrys = (int)$post['pcl_rates_posts']['angrys'];
	$total = (int)(int)$post['pcl_rates_posts']['total'];
	
	if($post['pcl_see_me'] === true)
	{		
$post['clasify_post_rates'] = '<div class="post_rate_button" id="post_rates_btn_'.$pid.'" onclick="DNTShowMenu('.$pid.')">'.$lang->pcl_rate.'</div>
<div id="post_rates_'.$pid.'" class="post_rate_list" style="display:none;">
	<span onclick="javascript:DNTPostRate(1, '.$tid.', '.$pid.')" class="post_rate_btn"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/like.gif" alt="'.$lang->pcl_like.'" title="'.$lang->pcl_like.'" /></span>
	<span onclick="javascript:DNTPostRate(2, '.$tid.', '.$pid.')" class="post_rate_btn"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/love.gif" alt="'.$lang->pcl_love.'" title="'.$lang->pcl_love.'" /></span>
	<span onclick="javascript:DNTPostRate(3, '.$tid.', '.$pid.')" class="post_rate_btn"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/wow.gif" alt="'.$lang->pcl_wow.'" title="'.$lang->pcl_wow.'" /></span>
	<span onclick="javascript:DNTPostRate(4, '.$tid.', '.$pid.')" class="post_rate_btn"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/smile.gif" alt="'.$lang->pcl_smile.'" title="'.$lang->pcl_smile.'" /></span>
	<span onclick="javascript:DNTPostRate(5, '.$tid.', '.$pid.')" class="post_rate_btn"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/cry.gif" alt="'.$lang->pcl_cry.'" title="'.$lang->pcl_cry.'" /></span>
	<span onclick="javascript:DNTPostRate(6, '.$tid.', '.$pid.')" class="post_rate_btn"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/angry.gif" alt="'.$lang->pcl_angry.'" title="'.$lang->pcl_angry.'" /></span>	
</div>';
	}

	$post['psc_rates_total'] = (int)$likes + (int)$loves + (int)$wow + (int)$smiles + (int)$crys + (int)$angrys;

	if($post['psc_rates_total'] > 0)
	{
		$pcl_results1 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/like.png" alt="'.$lang->pcl_like.'" title="'.$lang->pcl_like.'" onmouseover="javascript:DNTPostRates(1, '.$tid.', '.$pid.')" onmouseout="javascript:DNTPostRatesRemove(1, '.$tid.', '.$pid.')" onclick="location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=1&amp;tid='.$tid.'&amp;pid='.$pid.'\'" /><span id="prt_list1_pid'.$pid.'" class="ptr_list"></span>';
		$pcl_results2 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/love.png" alt="'.$lang->pcl_love.'" title="'.$lang->pcl_love.'" onmouseover="javascript:DNTPostRates(2, '.$tid.', '.$pid.')" onmouseout="javascript:DNTPostRatesRemove(2, '.$tid.', '.$pid.')" onclick="location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=2&amp;tid='.$tid.'&amp;pid='.$pid.'\'" /><span id="prt_list2_pid'.$pid.'" class="ptr_list"></span>';
		$pcl_results3 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/wow.png" alt="'.$lang->pcl_wow.'" title="'.$lang->pcl_wow.'" onmouseover="javascript:DNTPostRates(3, '.$tid.', '.$pid.')" onmouseout="javascript:DNTPostRatesRemove(3, '.$tid.', '.$pid.')" onclick="location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=3&amp;tid='.$tid.'&amp;pid='.$pid.'\'" /><span id="prt_list3_pid'.$pid.'" class="ptr_list"></span>';			
		$pcl_results4 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/smile.png" alt="'.$lang->pcl_smile.'" title="'.$lang->pcl_smile.'" onmouseover="javascript:DNTPostRates(4, '.$tid.', '.$pid.')" onmouseout="javascript:DNTPostRatesRemove(4, '.$tid.', '.$pid.')" onclick="location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=4&amp;tid='.$tid.'&amp;pid='.$pid.'\'" /><span id="prt_list4_pid'.$pid.'" class="ptr_list"></span>';				
		$pcl_results5 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/cry.png" alt="'.$lang->pcl_cry.'" title="'.$lang->pcl_cry.'" onmouseover="javascript:DNTPostRates(5, '.$tid.', '.$pid.')" onmouseout="javascript:DNTPostRatesRemove(5, '.$tid.', '.$pid.')" onclick="location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=5&amp;tid='.$tid.'&amp;pid='.$pid.'\'" /><span id="prt_list5_pid'.$pid.'" class="ptr_list"></span>';
		$pcl_results6 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/angry.png" alt="'.$lang->pcl_angry.'" title="'.$lang->pcl_angry.'" onmouseover="javascript:DNTPostRates(6, '.$tid.', '.$pid.')" onmouseout="javascript:DNTPostRatesRemove(6, '.$tid.', '.$pid.')" onclick="location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=6&amp;tid='.$tid.'&amp;pid='.$pid.'\'" /><span id="prt_list6_pid'.$pid.'" class="ptr_list"></span>';
		
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
		$pcl_url = $mybb->settings['bburl']."/dnt_post_rate.php?action=get_thread_rates&lid=all&amp;tid={$post['tid']}&amp;pid={$post['pid']}";
		$lang->pcl_view_all = $lang->sprintf($lang->pcl_view_all, $pcl_url);
		$clasify_post_rates_msg = $post['dnt_likes'].$post['dnt_loves'].$post['dnt_surprises'].$post['dnt_smiles'].$post['dnt_crys'].$post['dnt_hungrys'];
		$lang->pcl_total = $lang->sprintf($lang->pcl_total, $post['psc_rates_total']);
		$post['clasify_post_rates_msg'] = '<div id="clasify_post_rates_msgs_list'.$pid.'"><div class="clasify_post_rates_msg">'.$lang->pcl_total.$lang->pcl_view_all.$lang->pcl_rates."<br />".$clasify_post_rates_msg.'</div></div>';
		$post['message'] .= $post['clasify_post_rates_msg'];		
	}
	else
	{
		$post['message'] .= '<div id="clasify_post_rates_msgs_list'.$pid.'">'.$post['clasify_post_rates_msg'].'</div>';		
	}
}

function dnt_post_rate_xmlhttp()
{
	global $db, $lang, $thread, $mybb, $charset;

	if($mybb->settings['dnt_post_rate_active'] == 0)
		return false;
	if($mybb->get_input('action') == "get_post_rates")
	{
		header("Content-type: application/json; charset={$charset}");     
		$lang->load('dnt_post_rate',false,true);		
		$lid = (int)$mybb->input['lid'];
		$tid = (int)$mybb->input['tid'];
		$pid = (int)$mybb->input['pid'];
		$thread = get_thread($tid);
		$limit_users = (int)$mybb->settings['dnt_post_rate_limit_users'];
		$limit_search = (int)$mybb->settings['dnt_post_rate_limit_users'];	
		$pcl_date_limit = time() - ($limit_search * 60 * 60 * 24);
		if($limit_search > 0)
			$pcl_date = " AND pcl_date>='{$pcl_date_limit}'";
		else
			$pcl_date = "";
		$templates = "";
		$pcl_query = $db->query("SELECT dp.*, u.username FROM ".TABLE_PREFIX."dnt_post_rate dp
		LEFT JOIN ".TABLE_PREFIX."users u
		ON (dp.pcl_sender=u.uid)
		WHERE pcl_tid='{$tid}' AND pcl_pid='{$pid}' AND pcl_type='{$lid}'{$pcl_date}
		ORDER BY pcl_date DESC LIMIT {$limit_users}");
		while($pcl_rows = $db->fetch_array($pcl_query))
		{
			$pcl_date = my_date($mybb->settings['dateformat'], $pcl_rows['pcl_date']);
			$uname = htmlspecialchars_uni($pcl_rows['username']);
			if(empty($uname))
				$uname = $lang->guest;
			$dnt_pcl_uname .= "<span>".$uname." (".$pcl_date.")</span>";			
		}
		$templates = "<div class=\"dnt_prt_ulist\">{$dnt_pcl_uname}</div>";		
		echo json_encode($templates);
		exit;		
	} 
	else if($mybb->get_input('action') == "get_post_rates_member")
	{
		header("Content-type: application/json; charset={$charset}");     
		$lang->load('dnt_post_rate',false,true);		
		$lid = (int)$mybb->input['lid'];
		$tid = (int)$mybb->input['tid'];
		$thread = get_thread($tid);
		$limit_users = (int)$mybb->settings['dnt_post_rate_limit_users'];
		$limit_search = (int)$mybb->settings['dnt_post_rate_limit_users'];	
		$pcl_date_limit = time() - ($limit_search * 60 * 60 * 24);
		if($limit_search > 0)
			$pcl_date = " AND pcl_date>='{$pcl_date_limit}'";
		else
			$pcl_date = "";
		$templates = "";
		$pcl_query = $db->query("SELECT dp.*, u.username FROM ".TABLE_PREFIX."dnt_post_rate dp
		LEFT JOIN ".TABLE_PREFIX."users u
		ON (dp.pcl_sender=u.uid)
		WHERE pcl_tid='{$tid}' AND pcl_type='{$lid}'{$pcl_date}
		ORDER BY pcl_date DESC LIMIT {$limit_users}");
		while($pcl_rows = $db->fetch_array($pcl_query))
		{
			$pcl_date = my_date($mybb->settings['dateformat'], $pcl_rows['pcl_date']);
			$uname = htmlspecialchars_uni($pcl_rows['username']);
			if(empty($uname))
				$uname = $lang->guest;
			$dnt_pcl_uname .= "<span>".$uname." (".$pcl_date.")</span>";			
		}
		$templates = "<div class=\"dnt_prt_ulist\">{$dnt_pcl_uname}</div>";		
		echo json_encode($templates);
		exit;		
	} 	
	else if($mybb->get_input('action') == "clasify_post_rate")
	{
		header("Content-type: application/json; charset={$charset}");     
		$lang->load('dnt_post_rate',false,true);		
		$lid = (int)$mybb->input['lid'];
		$tid = (int)$mybb->input['tid'];
		$thread = get_thread($tid);
		$pid = (int)$mybb->input['pid'];
		$post = get_post($pid);
		$templates = "";
		$button = "";
		$touid = (int)$post['uid'];
		$uid = (int)$mybb->user['uid'];
		$pcl_total = (int)$thread['pcl_total']+1;
		$limit_search = (int)$mybb->settings['dnt_post_rate_limit_users'];	
		$pcl_date_limit = time() - ($limit_search * 60 * 60 * 24);
		if($limit_search > 0)
			$pcl_date = " AND pcl_date>='{$pcl_date_limit}'";
		else
			$pcl_date = "";
		$likes = $loves = $wow = $smiles = $crys = $angrys = 0;	
		$pcl_query = $db->simple_select('dnt_post_rate','*',"pcl_sender={$uid} AND pcl_tid='{$tid}' AND pcl_pid='{$pid}'{$pcl_date}", array("limit"=>1));		
		if($db->num_rows($pcl_query) > 0)
		{
			error("You have rated this post yet, don't blame me lol...");
			return false;
			exit;
			//$pcl_dataiu = "update";	
			//$pcl_count = $pcl_query+1;
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
			'pcl_pid' => $db->escape_string($pid),			
			'pcl_date' => time()
		);
		
		/*$update_data = array(
			'pcl_type' => $db->escape_string($lid),
			'pcl_count' => $db->escape_string($pcl_count),
			'pcl_date' => time()
		);*/

		if($pcl_dataiu == "insert")
			$db->insert_query("dnt_post_rate",$insert_data);
		//else if($pcl_dataiu == "update")
			//$db->update_query("dnt_post_rate",$update_data,"pcl_tid='{$tid}' AND pcl_pid='{$pid}' AND pcl_sender='{$uid}'");

		$thread['pcl_rates_threads'] = unserialize($thread['pcl_rates_threads']);
		
		$likest = (int)$thread['pcl_rates_threads']['likes'];
		$lovest = (int)$thread['pcl_rates_threads']['loves'];
		$wowt = (int)$thread['pcl_rates_threads']['wow'];
		$smilest = (int)$thread['pcl_rates_threads']['smiles'];
		$cryst = (int)$thread['pcl_rates_threads']['crys'];
		$angryst = (int)$thread['pcl_rates_threads']['angrys'];

		if($likest < 1)
			$likest = 0;
		if($lovest < 1)
			$lovest = 0;
		if($wowt < 1)
			$wowt = 0;
		if($smilest < 1)
			$smilest = 0;
		if($cryst < 1)
			$cryst = 0;
		if($angryst < 1)
			$angryst = 0;
		
		if($lid == 1)
			$likest++;
		if($lid == 2)
			$lovest++;
		if($lid == 3)
			$wowt++;
		if($lid == 4)
			$smilest++;
		if($lid == 5)
			$cryst++;
		if($lid == 6)
			$angryst++;		
		
		$clasify_threads_rates_total = (int)$likest + (int)$lovest + (int)$wowt + (int)$smilest + (int)$cryst + (int)$angryst;
		
		$thread_rates = array(
			'likes' => (int)$likest,
			'loves' => (int)$lovest,
			'wow' => (int)$wowt,
			'smiles' => (int)$smilest,
			'crys' => (int)$cryst,
			'angrys' => (int)$angryst,
			'total' => (int)$clasify_thread_rates_total
		);

		$thread_rates = serialize($thread_rates);

		$update_data_thread = array(
			"pcl_total" => $db->escape_string($pcl_total),
			"pcl_rates_threads" => $db->escape_string($thread_rates)
		);
		if(isset($update_data_thread))
			$db->update_query("threads",$update_data_thread,"tid='{$tid}'");
		//$db->update_query("threads",$update_data_thread,"tid='{$tid}' AND firstpost='{$pid}'");

		$post['pcl_rates_posts'] = unserialize($post['pcl_rates_posts']);
		$user['pcl_rates_given'] = (int)$mybb->user['pcl_rates_given'] + 1;
		if($user['pcl_rates_given'] < 1)
			$user['pcl_rates_given'] = 1;
		$post['pcl_rates_received'] = (int)$post['pcl_rates_received'] + 1;
		if($post['pcl_rates_received'] < 1)
			$post['pcl_rates_received'] = 1;

		if($uid != $post['uid'])
		{
			$update_user = array(
				"pcl_rates_given" => $db->escape_string($user['pcl_rates_given'])
			);		
			if(isset($update_user))
				$db->update_query("users",$update_user,"uid='{$uid}'");

			$update_user = array(
				"pcl_rates_received" => $db->escape_string($post['pcl_rates_received'])
			);		
			if(isset($update_user))
				$db->update_query("users",$update_user,"uid='{$post['uid']}'");			
		}
		
		$likesp = (int)$post['pcl_rates_posts']['likes'];
		$lovesp = (int)$post['pcl_rates_posts']['loves'];
		$wowp = (int)$post['pcl_rates_posts']['wow'];
		$smilesp = (int)$post['pcl_rates_posts']['smiles'];
		$crysp = (int)$post['pcl_rates_posts']['crys'];
		$angrysp = (int)$post['pcl_rates_posts']['angrys'];

		if($likesp < 1)
			$likesp = 0;
		if($lovesp < 1)
			$lovesp = 0;
		if($wowp < 1)
			$wowp = 0;
		if($smilesp < 1)
			$smilesp = 0;
		if($crysp < 1)
			$crysp = 0;
		if($angrysp < 1)
			$angrysp = 0;
		
		if($lid == 1)
			$likesp++;
		if($lid == 2)
			$lovesp++;
		if($lid == 3)
			$wowp++;
		if($lid == 4)
			$smilesp++;
		if($lid == 5)
			$crysp++;
		if($lid == 6)
			$angrysp++;
		
		$clasify_post_rates_total = (int)$likesp + (int)$lovesp + (int)$wowp + (int)$smilesp + (int)$crysp + (int)$angrysp;
	
		$post_rates = array(
			'likes' => (int)$likesp,
			'loves' => (int)$lovesp,
			'wow' => (int)$wowp,
			'smiles' => (int)$smilesp,
			'crys' => (int)$crysp,
			'angrys' => (int)$angrysp,
			'total' => (int)$clasify_post_rates_total
		);

		$post_rates = serialize($post_rates);
	
		$update_data_post = array(
			"pcl_rates_posts" => $db->escape_string($post_rates)
		);
		
		if(isset($update_data_post))
			$db->update_query("posts", $update_data_post, "tid='{$tid}' AND pid='{$pid}'");
	
		recordAlertRpt($tid, $pid);	

		$pcl_results1 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/like.png" alt="'.$lang->pcl_like.'" title="'.$lang->pcl_like.'" onmouseover="javascript:DNTPostRates(1, '.$tid.', '.$pid.')" onmouseout="javascript:DNTPostRatesRemove(1, '.$tid.', '.$pid.')" onclick="location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=1&amp;tid='.$tid.'&amp;pid='.$pid.'\'" /><span id="prt_list1_pid'.$pid.'" class="ptr_list"></span>';
		$pcl_results2 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/love.png" alt="'.$lang->pcl_love.'" title="'.$lang->pcl_love.'" onmouseover="javascript:DNTPostRates(2, '.$tid.', '.$pid.')" onmouseout="javascript:DNTPostRatesRemove(2, '.$tid.', '.$pid.')" onclick="location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=2&amp;tid='.$tid.'&amp;pid='.$pid.'\'" /><span id="prt_list2_pid'.$pid.'" class="ptr_list"></span>';
		$pcl_results3 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/wow.png" alt="'.$lang->pcl_wow.'" title="'.$lang->pcl_wow.'" onmouseover="javascript:DNTPostRates(3, '.$tid.', '.$pid.')" onmouseout="javascript:DNTPostRatesRemove(3, '.$tid.', '.$pid.')" onclick="location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=3&amp;tid='.$tid.'&amp;pid='.$pid.'\'" /><span id="prt_list3_pid'.$pid.'" class="ptr_list"></span>';			
		$pcl_results4 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/smile.png" alt="'.$lang->pcl_smile.'" title="'.$lang->pcl_smile.'" onmouseover="javascript:DNTPostRates(4, '.$tid.', '.$pid.')" onmouseout="javascript:DNTPostRatesRemove(4, '.$tid.', '.$pid.')" onclick="location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=4&amp;tid='.$tid.'&amp;pid='.$pid.'\'" /><span id="prt_list4_pid'.$pid.'" class="ptr_list"></span>';				
		$pcl_results5 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/cry.png" alt="'.$lang->pcl_cry.'" title="'.$lang->pcl_cry.'" onmouseover="javascript:DNTPostRates(5, '.$tid.', '.$pid.')" onmouseout="javascript:DNTPostRatesRemove(5, '.$tid.', '.$pid.')" onclick="location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=5&amp;tid='.$tid.'&amp;pid='.$pid.'\'" /><span id="prt_list5_pid'.$pid.'" class="ptr_list"></span>';
		$pcl_results6 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/angry.png" alt="'.$lang->pcl_angry.'" title="'.$lang->pcl_angry.'" onmouseover="javascript:DNTPostRates(6, '.$tid.', '.$pid.')" onmouseout="javascript:DNTPostRatesRemove(6, '.$tid.', '.$pid.')" onclick="location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=6&amp;tid='.$tid.'&amp;pid='.$pid.'\'" /><span id="prt_list6_pid'.$pid.'" class="ptr_list"></span>';
	
		if($likesp > 0)
			$dnt_prt_rates .= "<span class=\"clasify_post_rates_msg_span\">".$likesp."</span>".$pcl_results1;
		if($lovesp > 0)
			$dnt_prt_rates .= "<span class=\"clasify_post_rates_msg_span\">".$lovesp."</span>".$pcl_results2;		
		if($wowp > 0)
			$dnt_prt_rates .= "<span class=\"clasify_post_rates_msg_span\">".$wowp."</span>".$pcl_results3;
		if($smilesp > 0)
			$dnt_prt_rates .= "<span class=\"clasify_post_rates_msg_span\">".$smilesp."</span>".$pcl_results4;
		if($crysp > 0)
			$dnt_prt_rates .= "<span class=\"clasify_post_rates_msg_span\">".$crysp."</span>".$pcl_results5;
		if($angrysp > 0)
			$dnt_prt_rates .= "<span class=\"clasify_post_rates_msg_span\">".$angrysp."</span>".$pcl_results6;
				
		$lang->pcl_total = $lang->sprintf($lang->pcl_total, $clasify_post_rates_total);

		$templates = '<div class="clasify_post_rates_msg">'.$lang->pcl_total.$lang->pcl_rates.'<br />'.$dnt_prt_rates.'</div>';

		if($lid == 1)
			$pcl_results = '<div onclick="javascript:DNTRemoveRate(1,'.$tid.','.$pid.')" class="pcl_div_rate"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/like.png" alt="'.$lang->pcl_like.'" title="'.$lang->pcl_like.'" /><span style="color:#1b43b6;">'.$lang->pcl_like.'</span></div>';
		if($lid == 2)
			$pcl_results = '<div onclick="javascript:DNTRemoveRate(2,'.$tid.','.$pid.')" class="pcl_div_rate"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/love.png" alt="'.$lang->pcl_love.'" title="'.$lang->pcl_love.'" /><span style="color: #e61b3f;">'.$lang->pcl_love.'</span></div>';
		if($lid == 3)
			$pcl_results = '<div onclick="javascript:DNTRemoveRate(3,'.$tid.','.$pid.')" class="pcl_div_rate"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/wow.png" alt="'.$lang->pcl_wow.'" title="'.$lang->pcl_wow.'" /><span style="color:#cfcd35;">'.$lang->pcl_wow.'</span></div>';
		if($lid == 4)
			$pcl_results = '<div onclick="javascript:DNTRemoveRate(4,'.$tid.','.$pid.')" class="pcl_div_rate"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/smile.png" alt="'.$lang->pcl_smile.'" title="'.$lang->pcl_smile.'" /><span style="color:#cfcd35;">'.$lang->pcl_smile.'</span></div>';
		if($lid == 5)				
			$pcl_results = '<div onclick="javascript:DNTRemoveRate(5,'.$tid.','.$pid.')" class="pcl_div_rate"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/cry.png" alt="'.$lang->pcl_cry.'" title="'.$lang->pcl_cry.'" /><span style="color:#1b43b6;">'.$lang->pcl_cry.'</span></div>';				
		if($lid == 6)
			$pcl_results = '<div onclick="javascript:DNTRemoveRate(6,'.$tid.','.$pid.')" class="pcl_div_rate"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/angry.png" alt="'.$lang->pcl_angry.'" title="'.$lang->pcl_angry.'" /><span style="color:#c22e0f;">'.$lang->pcl_angry.'</span></div>';	
	
		$rate = $pcl_results;
		
		$pcl_data = array(
			'receive' => $pcl_dataiu,
			'post_rate_id' => (int)$lid,
			'post_rate_tid' => (int)$tid,
			'pcl_user' => (int)$touid,
			'pcl_sender' => (int)$uid,			
			'pcl_total' => (int)$pcl_total+1,
			'templates' => $templates,
			'rate' => $rate
		);
		
		echo json_encode($pcl_data);
		exit;
	}
	else if($mybb->get_input('action') == "remove_post_rates")
	{
		header("Content-type: application/json; charset={$charset}");     
		$lang->load('dnt_post_rate',false,true);		
		$lid = (int)$mybb->input['lid'];
		$tid = (int)$mybb->input['tid'];
		$pid = (int)$mybb->input['pid'];
		$uid = (int)$mybb->user['uid'];
		$limit_search = (int)$mybb->settings['dnt_post_rate_limit_users'];	
		$pcl_date_limit = time() - ($limit_search * 60 * 60 * 24);		
		if($limit_search > 0)
			$pcl_date = " AND pcl_date>='{$pcl_date_limit}'";
		else
			$pcl_date = "";
		$query = $db->simple_select("dnt_post_rate","*","pcl_tid='{$tid}' AND pcl_pid='{$pid}' AND pcl_sender='{$uid}'{$pcl_date}", array("limit"=>1));
		if($db->num_rows($query) == 0)
		{
			error("You have not rated this post...");
			return false;
			exit;
		}		
		$db->delete_query("dnt_post_rate","pcl_tid='{$tid}' AND pcl_pid='{$pid}' AND pcl_sender='{$uid}'{$pcl_date}");
		$thread = get_thread($tid);
		if($thread['pcl_total'] > 0)
			$pcl_total = (int)$thread['pcl_total']-1;
		else
			$pcl_total = 0;
		
		$thread['pcl_rates_threads'] = unserialize($thread['pcl_rates_threads']);
		
		$likest = (int)$thread['pcl_rates_threads']['likes'];
		$lovest = (int)$thread['pcl_rates_threads']['loves'];
		$wowt = (int)$thread['pcl_rates_threads']['wow'];
		$smilest = (int)$thread['pcl_rates_threads']['smiles'];
		$cryst = (int)$thread['pcl_rates_threads']['crys'];
		$angryst = (int)$thread['pcl_rates_threads']['angrys'];
		
		if($lid == 1)
			$likest--;
		if($lid == 2)
			$lovest--;
		if($lid == 3)
			$wowt--;
		if($lid == 4)
			$smilest--;
		if($lid == 5)
			$cryst--;
		if($lid == 6)
			$angryst--;
		
		if($likest < 1)
			$likest = 0;
		if($lovest < 1)
			$lovest = 0;
		if($wowt < 1)
			$wowt = 0;
		if($smilest < 1)
			$smilest = 0;
		if($cryst < 1)
			$cryst = 0;
		if($angryst < 1)
			$angryst = 0;
		
		$clasify_threads_rates_total = (int)$likest + (int)$lovest + (int)$wowt + (int)$smilest + (int)$cryst + (int)$angryst;
		
		$thread_rates = array(
			'likes' => (int)$likest,
			'loves' => (int)$lovest,
			'wow' => (int)$wowt,
			'smiles' => (int)$smilest,
			'crys' => (int)$cryst,
			'angrys' => (int)$angryst,
			'total' => (int)$clasify_thread_rates_total
		);

		$thread_rates = serialize($thread_rates);

		$update_data_thread = array(
			"pcl_total" => $db->escape_string($pcl_total),
			"pcl_rates_threads" => $db->escape_string($thread_rates)
		);
		if(isset($update_data_thread))
			$db->update_query("threads",$update_data_thread,"tid='{$tid}'");
			//$db->update_query("threads",$update_data_thread,"tid='{$tid}' AND firstpost='{$pid}'");

		$post = get_post($pid);	
		$post['pcl_rates_posts'] = unserialize($post['pcl_rates_posts']);
		$user['pcl_rates_given'] = (int)$mybb->user['pcl_rates_given'] - 1;
		if($user['pcl_rates_given'] < 0)
			$user['pcl_rates_given'] = 0;
		$post['pcl_rates_received'] = (int)$post['pcl_rates_received'] - 1;
		if($post['pcl_rates_received'] < 0)
			$post['pcl_rates_received'] = 0;
		if($uid == $post['uid'])
		{			
			$update_user = array(
				"pcl_rates_given" => $db->escape_string($user['pcl_rates_given'])
			);		
			if(isset($update_user))
				$db->update_query("users",$update_user,"uid='{$uid}'");
		}
		else if($uid != $post['uid'])
		{
			$update_user = array(
				"pcl_rates_received" => $db->escape_string($post['pcl_rates_received'])
			);		
			if(isset($update_user))
				$db->update_query("users",$update_user,"uid='{$post['uid']}'");			
		}
		$likesp = (int)$post['pcl_rates_posts']['likes'];
		$lovesp = (int)$post['pcl_rates_posts']['loves'];
		$wowp = (int)$post['pcl_rates_posts']['wow'];
		$smilesp = (int)$post['pcl_rates_posts']['smiles'];
		$crysp = (int)$post['pcl_rates_posts']['crys'];
		$angrysp = (int)$post['pcl_rates_posts']['angrys'];
		
		if($lid == 1)
			$likesp--;
		if($lid == 2)
			$lovesp--;
		if($lid == 3)
			$wowp--;
		if($lid == 4)
			$smilesp--;
		if($lid == 5)
			$crysp--;
		if($lid == 6)
			$angrysp--;
		
		if($likesp < 1)
			$likesp = 0;
		if($lovesp < 1)
			$lovesp = 0;
		if($wowp < 1)
			$wowp = 0;
		if($smilesp < 1)
			$smilesp = 0;
		if($crysp < 1)
			$crysp = 0;
		if($angrysp < 1)
			$angrysp = 0;
		
		$clasify_post_rates_total = (int)$likesp + (int)$lovesp + (int)$wowp + (int)$smilesp + (int)$crysp + (int)$angrysp;
	
		$post_rates = array(
			'likes' => (int)$likesp,
			'loves' => (int)$lovesp,
			'wow' => (int)$wowp,
			'smiles' => (int)$smilesp,
			'crys' => (int)$crysp,
			'angrys' => (int)$angrysp,
			'total' => (int)$clasify_post_rates_total
		);

		$post_rates = serialize($post_rates);
	
		$update_data_post = array(
			"pcl_rates_posts" => $db->escape_string($post_rates)
		);
		if(isset($update_data_post))
		$db->update_query("posts", $update_data_post, "tid='{$tid}' AND pid='{$pid}'");		

		removeAlertRpt($tid, $pid);	

		$pcl_results1 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/like.png" alt="'.$lang->pcl_like.'" title="'.$lang->pcl_like.'" onmouseover="javascript:DNTPostRates(1, '.$tid.', '.$pid.')" onmouseout="javascript:DNTPostRatesRemove(1, '.$tid.', '.$pid.')" onclick="location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=1&amp;tid='.$tid.'&amp;pid='.$pid.'\'" /><span id="prt_list1_pid'.$pid.'" class="ptr_list"></span>';
		$pcl_results2 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/love.png" alt="'.$lang->pcl_love.'" title="'.$lang->pcl_love.'" onmouseover="javascript:DNTPostRates(2, '.$tid.', '.$pid.')" onmouseout="javascript:DNTPostRatesRemove(2, '.$tid.', '.$pid.')" onclick="location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=2&amp;tid='.$tid.'&amp;pid='.$pid.'\'" /><span id="prt_list2_pid'.$pid.'" class="ptr_list"></span>';
		$pcl_results3 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/wow.png" alt="'.$lang->pcl_wow.'" title="'.$lang->pcl_wow.'" onmouseover="javascript:DNTPostRates(3, '.$tid.', '.$pid.')" onmouseout="javascript:DNTPostRatesRemove(3, '.$tid.', '.$pid.')" onclick="location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=3&amp;tid='.$tid.'&amp;pid='.$pid.'\'" /><span id="prt_list3_pid'.$pid.'" class="ptr_list"></span>';			
		$pcl_results4 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/smile.png" alt="'.$lang->pcl_smile.'" title="'.$lang->pcl_smile.'" onmouseover="javascript:DNTPostRates(4, '.$tid.', '.$pid.')" onmouseout="javascript:DNTPostRatesRemove(4, '.$tid.', '.$pid.')" onclick="location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=4&amp;tid='.$tid.'&amp;pid='.$pid.'\'" /><span id="prt_list4_pid'.$pid.'" class="ptr_list"></span>';				
		$pcl_results5 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/cry.png" alt="'.$lang->pcl_cry.'" title="'.$lang->pcl_cry.'" onmouseover="javascript:DNTPostRates(5, '.$tid.', '.$pid.')" onmouseout="javascript:DNTPostRatesRemove(5, '.$tid.', '.$pid.')" onclick="location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=5&amp;tid='.$tid.'&amp;pid='.$pid.'\'" /><span id="prt_list5_pid'.$pid.'" class="ptr_list"></span>';
		$pcl_results6 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/angry.png" alt="'.$lang->pcl_angry.'" title="'.$lang->pcl_angry.'" onmouseover="javascript:DNTPostRates(6, '.$tid.', '.$pid.')" onmouseout="javascript:DNTPostRatesRemove(6, '.$tid.', '.$pid.')" onclick="location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=6&amp;tid='.$tid.'&amp;pid='.$pid.'\'" /><span id="prt_list6_pid'.$pid.'" class="ptr_list"></span>';
	
		if($likesp > 0)
			$dnt_prt_rates .= "<span class=\"clasify_post_rates_msg_span\">".$likesp."</span>".$pcl_results1;
		if($lovesp > 0)
			$dnt_prt_rates .= "<span class=\"clasify_post_rates_msg_span\">".$lovesp."</span>".$pcl_results2;		
		if($wowp > 0)
			$dnt_prt_rates .= "<span class=\"clasify_post_rates_msg_span\">".$wowp."</span>".$pcl_results3;
		if($smilesp > 0)
			$dnt_prt_rates .= "<span class=\"clasify_post_rates_msg_span\">".$smilesp."</span>".$pcl_results4;
		if($crysp > 0)
			$dnt_prt_rates .= "<span class=\"clasify_post_rates_msg_span\">".$crysp."</span>".$pcl_results5;
		if($angrysp > 0)
			$dnt_prt_rates .= "<span class=\"clasify_post_rates_msg_span\">".$angrysp."</span>".$pcl_results6;
				
		$lang->pcl_total = $lang->sprintf($lang->pcl_total, $clasify_post_rates_total);
		
		if($clasify_post_rates_total > 0)
			$templates = '<div class="clasify_post_rates_msg">'.$lang->pcl_total.$lang->pcl_rates.'<br />'.$dnt_prt_rates.'</div>';
		else
			$templates = '<div class="clasify_post_norates_msg">'.$lang->pcl_rates_removed.'</div>';
		
		$button = '<div class="post_rate_button" onclick="DNTShowMenu('.$pid.')">'.$lang->pcl_rate.'</div>
<div id="post_rates_'.$pid.'" class="post_rate_list" style="display:none;">
	<span onclick="javascript:DNTPostRate(1, '.$tid.', '.$pid.')" class="post_rate_btn"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/like.gif" alt="'.$lang->pcl_like.'" title="'.$lang->pcl_like.'" /></span>
	<span onclick="javascript:DNTPostRate(2, '.$tid.', '.$pid.')" class="post_rate_btn"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/love.gif" alt="'.$lang->pcl_love.'" title="'.$lang->pcl_love.'" /></span>
	<span onclick="javascript:DNTPostRate(3, '.$tid.', '.$pid.')" class="post_rate_btn"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/wow.gif" alt="'.$lang->pcl_wow.'" title="'.$lang->pcl_wow.'" /></span>
	<span onclick="javascript:DNTPostRate(4, '.$tid.', '.$pid.')" class="post_rate_btn"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/smile.gif" alt="'.$lang->pcl_smile.'" title="'.$lang->pcl_smile.'" /></span>
	<span onclick="javascript:DNTPostRate(5, '.$tid.', '.$pid.')" class="post_rate_btn"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/cry.gif" alt="'.$lang->pcl_cry.'" title="'.$lang->pcl_cry.'" /></span>
	<span onclick="javascript:DNTPostRate(6, '.$tid.', '.$pid.')" class="post_rate_btn"><img src="'.$mybb->settings['bburl'].'/images/dnt_rates/angry.gif" alt="'.$lang->pcl_angry.'" title="'.$lang->pcl_angry.'" /></span>	
</div>';		
	
		$pcl_data = array(
			'post_rate_tid' => (int)$tid,
			'pcl_user' => (int)$uid,
			'templates' => $templates,
			'button' => $button
		);
		
		echo json_encode($pcl_data);
		exit;
	}
}

function dnt_post_rate_member()
{
	global $db, $lang, $mybb, $memprofile;
	$lang->load('dnt_post_rate',false,true);		
	$pcl_templates = "";
	$url_given = $mybb->settings['bburl'].'/dnt_post_rate.php?action=get_given_rates&amp;uid='.(int)$memprofile['uid'];
	$url_received = $mybb->settings['bburl'].'/dnt_post_rate.php?action=get_received_rates&amp;uid='.(int)$memprofile['uid'];	
	$memprofile['pcl_rates_given'] = $lang->sprintf($lang->pcl_rates_given,(int)$memprofile['pcl_rates_given'], $url_given);
	$memprofile['pcl_rates_received'] = $lang->sprintf($lang->pcl_rates_received,(int)$memprofile['pcl_rates_received'], $url_received);	
	$pcl_query = $db->simple_select('threads','*',"uid='{$memprofile['uid']}' AND pcl_total>0 ORDER BY pcl_total DESC LIMIT 1");
	while($thread = $db->fetch_array($pcl_query))
	{
		$tid = (int)$thread['tid'];
		//$pid = (int)$thread['firstpost'];
		$subject = htmlspecialchars_uni($thread['subject']);
		$subject_link = get_thread_link($tid);
		$subject = "<a href=\"{$subject_link}\">{$subject}</a>";
		$total = (int)$thread['pcl_total'];
		$memprofile['pcl_rates'] = unserialize($thread['pcl_rates_threads']);
	}
	if(isset($tid))
	{
		$pcl_results1 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/like.png" alt="'.$lang->pcl_like.'" title="'.$lang->pcl_like.'" onmouseover="javascript:DNTPostRatesMember(1, '.$tid.')" onmouseout="javascript:DNTPostRatesMemberRemove(1, '.$tid.')" onclick="location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=1&amp;tid='.$tid.'\'" /><span id="prt_list1_pid'.$tid.'" class="ptr_list"></span>';
		$pcl_results2 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/love.png" alt="'.$lang->pcl_love.'" title="'.$lang->pcl_love.'" onmouseover="javascript:DNTPostRatesMember(2, '.$tid.')" onmouseout="javascript:DNTPostRatesMemberRemove(2, '.$tid.')" onclick="location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=2&amp;tid='.$tid.'\'" /><span id="prt_list2_pid'.$tid.'" class="ptr_list"></span>';
		$pcl_results3 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/wow.png" alt="'.$lang->pcl_wow.'" title="'.$lang->pcl_wow.'" onmouseover="javascript:DNTPostRatesMember(3, '.$tid.')" onmouseout="javascript:DNTPostRatesMemberRemove(3, '.$tid.')" onclick="location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=3&amp;tid='.$tid.'\'" /><span id="prt_list3_pid'.$tid.'" class="ptr_list"></span>';			
		$pcl_results4 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/smile.png" alt="'.$lang->pcl_smile.'" title="'.$lang->pcl_smile.'" onmouseover="javascript:DNTPostRatesMember(4, '.$tid.')" onmouseout="javascript:DNTPostRatesMemberRemove(4, '.$tid.')" onclick="location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=4&amp;tid='.$tid.'\'" /><span id="prt_list4_pid'.$tid.'" class="ptr_list"></span>';				
		$pcl_results5 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/cry.png" alt="'.$lang->pcl_cry.'" title="'.$lang->pcl_cry.'" onmouseover="javascript:DNTPostRatesMember(5, '.$tid.')" onmouseout="javascript:DNTPostRatesMemberRemove(5, '.$tid.')" onclick="location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=5&amp;tid='.$tid.'\'" /><span id="prt_list5_pid'.$tid.'" class="ptr_list"></span>';
		$pcl_results6 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/angry.png" alt="'.$lang->pcl_angry.'" title="'.$lang->pcl_angry.'" onmouseover="javascript:DNTPostRatesMember(6, '.$tid.')" onmouseout="javascript:DNTPostRatesMemberRemove(6, '.$tid.')" onclick="location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=6&amp;tid='.$tid.'\'" /><span id="prt_list6_pid'.$tid.'" class="ptr_list"></span>';
		
		$likes = (int)$memprofile['pcl_rates']['likes'];
		$loves = (int)$memprofile['pcl_rates']['loves'];
		$wow = (int)$memprofile['pcl_rates']['wow'];
		$smiles = (int)$memprofile['pcl_rates']['smiles'];
		$crys = (int)$memprofile['pcl_rates']['crys'];
		$angrys = (int)$memprofile['pcl_rates']['angrys'];

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
			$pcl_templates = '<div class="clasify_post_rates_msg">'.$lang->pcl_total_best.'<br />'.$subject.'<br /><br />'.$clasify_post_rates_msg.'</div>';
	}
	else
		$pcl_templates = '<div class="clasify_post_norates_msg">'.$lang->pcl_dont_rates.'</div>';
	
	$memprofile['dnt_prt'] = $pcl_templates;
}

function dnt_post_rates()
{
	global $mybb, $lang, $thread, $dnt_prt_rates;

	$lang->load('dnt_post_rate',false,true);	
	$dnt_prt_rates = "";
	$tid = $thread['tid'];
	$thread['pcl_rates_threads'] = unserialize($thread['pcl_rates_threads']);
	
	$pcl_results1 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/like.png" alt="'.$lang->pcl_like.'" title="'.$lang->pcl_like.'" onmouseover="javascript:DNTPostRatesMember(1, '.$tid.')" onmouseout="javascript:DNTPostRatesMemberRemove(1, '.$tid.')" onclick="location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=1&amp;tid='.$tid.'\'" /><span id="prt_list1_pid'.$tid.'" class="ptr_list"></span>';
	$pcl_results2 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/love.png" alt="'.$lang->pcl_love.'" title="'.$lang->pcl_love.'" onmouseover="javascript:DNTPostRatesMember(2, '.$tid.')" onmouseout="javascript:DNTPostRatesMemberRemove(2, '.$tid.')" onclick="location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=2&amp;tid='.$tid.'\'" /><span id="prt_list2_pid'.$tid.'" class="ptr_list"></span>';
	$pcl_results3 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/wow.png" alt="'.$lang->pcl_wow.'" title="'.$lang->pcl_wow.'" onmouseover="javascript:DNTPostRatesMember(3, '.$tid.')" onmouseout="javascript:DNTPostRatesMemberRemove(3, '.$tid.')" onclick="location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=3&amp;tid='.$tid.'\'" /><span id="prt_list3_pid'.$tid.'" class="ptr_list"></span>';			
	$pcl_results4 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/smile.png" alt="'.$lang->pcl_smile.'" title="'.$lang->pcl_smile.'" onmouseover="javascript:DNTPostRatesMember(4, '.$tid.')" onmouseout="javascript:DNTPostRatesMemberRemove(4, '.$tid.')" onclick="location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=4&amp;tid='.$tid.'\'" /><span id="prt_list4_pid'.$tid.'" class="ptr_list"></span>';				
	$pcl_results5 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/cry.png" alt="'.$lang->pcl_cry.'" title="'.$lang->pcl_cry.'" onmouseover="javascript:DNTPostRatesMember(5, '.$tid.')" onmouseout="javascript:DNTPostRatesMemberRemove(5, '.$tid.')" onclick="location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=5&amp;tid='.$tid.'\'" /><span id="prt_list5_pid'.$tid.'" class="ptr_list"></span>';
	$pcl_results6 = '<img src="'.$mybb->settings['bburl'].'/images/dnt_rates/angry.png" alt="'.$lang->pcl_angry.'" title="'.$lang->pcl_angry.'" onmouseover="javascript:DNTPostRatesMember(6, '.$tid.')" onmouseout="javascript:DNTPostRatesMemberRemove(6, '.$tid.')" onclick="location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=6&amp;tid='.$tid.'\'" /><span id="prt_list6_pid'.$tid.'" class="ptr_list"></span>';
	
	$likes = (int)$thread['pcl_rates_threads']['likes'];
	$loves = (int)$thread['pcl_rates_threads']['loves'];
	$wow = (int)$thread['pcl_rates_threads']['wow'];
	$smiles = (int)$thread['pcl_rates_threads']['smiles'];
	$crys = (int)$thread['pcl_rates_threads']['crys'];
	$angrys = (int)$thread['pcl_rates_threads']['angrys'];
	
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
	unset($thread['pcl_rates_threads']);
}

function dnt_post_rate_insert_thread(&$data)
{
	global $db, $mybb;	
	$data->thread_insert_data['pcl_rates_threads'] = "";
}

function dnt_post_rate_insert_post(&$data)
{
	global $db, $mybb;	
	$data->post_insert_data['pcl_rates_posts'] = "";
}

function dnt_post_rate_admin_action(&$action)
{
	global $mybb;

	if(!$mybb->settings['dnt_post_rate_active'] || !empty($session->is_spider))
	{
		return false;
	}
	
	$action['recount_pcl_rates'] = array ('active'=>'recount_pcl_rates');
}
function dnt_post_rate_admin_menu(&$sub_menu)
{
    global $mybb, $db, $lang;

	if(!$mybb->settings['dnt_post_rate_active'] || !empty($session->is_spider))
	{
		return false;
	}
	
	$lang->load('dnt_post_rate',false,true);

	$sub_menu['45'] = array	(
		'id'	=> 'recount_pcl_rates',
		'title'	=> $db->escape_string($lang->pcl_recount),
		'link'	=> 'index.php?module=tools-recount_pcl_rates'
	);
}
// Set admin permissions for recount thanks, who can do this task ?
function dnt_post_rate_admin_permissions(&$admin_permissions)
{
    global $mybb, $db,$lang;

	if(!$mybb->settings['dnt_post_rate_active'] || !empty($session->is_spider))
	{
		return false;
	}
	
	$lang->load('dnt_post_rate',false,true);
	
	$admin_permissions['recount_pcl_rates'] = $db->escape_string($lang->pcl_can_recount);
}

function dnt_post_rate_admin()
{
	global $mybb, $page, $db, $lang;

	if(!$mybb->settings['dnt_post_rate_active'] || !empty($session->is_spider))
	{
		return false;
	}	

	require_once MYBB_ROOT.'inc/functions_rebuild.php';
	if($page->active_action != 'recount_pcl_rates')
	{
		return false;
	}

	$lang->load('dnt_post_rate',false,true);	
	
	if($mybb->request_method == "post")
	{
		if(!isset($mybb->input['page']) || (int)$mybb->input['page'] < 1)
		{
			$mybb->input['page'] = 1;
		}
		if(isset($mybb->input['do_recount_rates_threads']))
		{
			if(!(int)$mybb->input['pcl_threads_chunk_size'])
			{
				$mybb->input['pcl_threads_chunk_size'] = 500;
			}

			do_pcl_threads_recount();
		}
		else if(isset($mybb->input['do_recount_rates_users']))
		{
			if(!(int)$mybb->input['pcl_users_chunk_size'])
			{
				$mybb->input['pcl_users_chunk_size'] = 500;
			}

			do_pcl_users_recount();
		}
	}

	$page->add_breadcrumb_item($db->escape_string($lang->pcl_recount), "index.php?module=tools-recount_pcl_rates");
	$page->output_header($db->escape_string($lang->pcl_recount));

	$sub_tabs['thanks_recount'] = array(
		'title'			=> $db->escape_string($lang->pcl_recount_do),
		'link'			=> "index.php?module=tools-recount_pcl_rates",
		'description'	=> $db->escape_string($lang->pcl_upgrade_do)
	);

	$page->output_nav_tabs($sub_tabs, 'pcl_rates_recount');

	$form = new Form("index.php?module=tools-recount_pcl_rates", "post");

	$form_container = new FormContainer($db->escape_string($lang->pcl_recount));
	$form_container->output_row_header($db->escape_string($lang->pcl_recount_desc));
	$form_container->output_row_header($db->escape_string($lang->pcl_recount_send), array('width' => 50));
	$form_container->output_row_header("&nbsp;");

	$form_container->output_cell("<label>".$db->escape_string($lang->pcl_recount_threads)."</label>
	<div class=\"description\">".$db->escape_string($lang->pcl_recount_task_desc)."</div>");
	$form_container->output_cell($form->generate_text_box("pcl_threads_chunk_size", 500, array('style' => 'width: 150px;')));
	$form_container->output_cell($form->generate_submit_button($db->escape_string($lang->pcl_recount_send), array("name" => "do_recount_rates_threads")));
	$form_container->construct_row();

	$form_container->output_cell("<label>".$db->escape_string($lang->pcl_recount_users)."</label>
	<div class=\"description\">".$db->escape_string($lang->pcl_recount_task2_desc).".</div>");
	$form_container->output_cell($form->generate_text_box("pcl_users_chunk_size", 500, array('style' => 'width: 150px;')));
	$form_container->output_cell($form->generate_submit_button($db->escape_string($lang->pcl_recount_send), array("name" => "do_recount_rates_users")));
	$form_container->construct_row();

	$form_container->end();

	$form->end();

	$page->output_footer();

	exit;
}


function do_pcl_threads_recount()
{
	global $db, $mybb, $lang;

	if(!$mybb->settings['dnt_post_rate_active'] || !empty($session->is_spider))
	{
		return false;
	}

	$lang->load('dnt_post_rate',false,true);	
	
	$cur_page = (int)$mybb->input['page'];
	$per_page = (int)$mybb->input['pcl_threads_chunk_size'];
	$start = ($cur_page-1) * $per_page;
	$end = $start + $per_page;

	if ($cur_page == 1)
	{
		$db->write_query("UPDATE ".TABLE_PREFIX."threads SET pcl_rates_threads=''");
		$db->write_query("UPDATE ".TABLE_PREFIX."posts SET pcl_rates_posts=''");		
	}

	$query = $db->simple_select("dnt_post_rate", "COUNT(distinct pcl_tid) AS pcl_count");
	$pcl_count = $db->fetch_field($query, 'pcl_count');
	$likes = $loves = $wow = $smiles = $crys = $angrys = 0;
	$query = $db->simple_select("dnt_post_rate", "*", '', array('order_by' => 'pcl_tid', 'group_by' => 'pcl_tid', 'order_dir' => 'asc', 'limit_start' => $start, 'limit' => $per_page));
	while($pcl = $db->fetch_array($query))
	{
		$type1 = $db->simple_select('dnt_post_rate', 'COUNT(pcl_count) as count1', 'pcl_tid='.(int)$pcl['pcl_tid'].' AND pcl_type=1');
		$likes = $db->fetch_field($type1, 'count1');
		$type2 = $db->simple_select('dnt_post_rate', 'COUNT(pcl_count) as count2', 'pcl_tid='.(int)$pcl['pcl_tid'].' AND pcl_type=2');
		$loves = $db->fetch_field($type2, 'count2');
		$type3 = $db->simple_select('dnt_post_rate', 'COUNT(pcl_count) as count3', 'pcl_tid='.(int)$pcl['pcl_tid'].' AND pcl_type=3');
		$wow = $db->fetch_field($type3, 'count3');
		$type4 = $db->simple_select('dnt_post_rate', 'COUNT(pcl_count) as count4', 'pcl_tid='.(int)$pcl['pcl_tid'].' AND pcl_type=4');
		$smiles = $db->fetch_field($type4, 'count4');
		$type5 = $db->simple_select('dnt_post_rate', 'COUNT(pcl_count) as count5', 'pcl_tid='.(int)$pcl['pcl_tid'].' AND pcl_type=5');
		$crys = $db->fetch_field($type5, 'count5');
		$type6 = $db->simple_select('dnt_post_rate', 'COUNT(pcl_count) as count6', 'pcl_tid='.(int)$pcl['pcl_tid'].' AND pcl_type=6');
		$angrys = $db->fetch_field($type6, 'count6');	
		$total = $likes + $loves + $wow + $smiles + $crys + $angrys;
		$pcl['pcl_rates'] = array(
			'likes' => $likes,
			'loves' => $loves,
			'wow' => $wow,
			'smiles' => $smiles,
			'crys' => $crys,
			'angrys' => $angrys,
			'total' => $total
		);
		$pcl['pcl_rates'] = serialize($pcl['pcl_rates']);
		$db->update_query("threads", array("pcl_rates_threads" => $db->escape_string($pcl['pcl_rates'])), "tid='{$pcl['pcl_tid']}'");		
	}

	$query = $db->simple_select("dnt_post_rate", "*", '', array('order_by' => 'pcl_pid', 'group_by' => 'pcl_pid', 'order_dir' => 'asc', 'limit_start' => $start, 'limit' => $per_page));
	while($pcl = $db->fetch_array($query))
	{
		$type1 = $db->simple_select('dnt_post_rate', 'COUNT(pcl_count) as count1', 'pcl_pid='.(int)$pcl['pcl_pid'].' AND pcl_type=1');
		$likes = $db->fetch_field($type1, 'count1');
		$type2 = $db->simple_select('dnt_post_rate', 'COUNT(pcl_count) as count2', 'pcl_pid='.(int)$pcl['pcl_pid'].' AND pcl_type=2');
		$loves = $db->fetch_field($type2, 'count2');
		$type3 = $db->simple_select('dnt_post_rate', 'COUNT(pcl_count) as count3', 'pcl_pid='.(int)$pcl['pcl_pid'].' AND pcl_type=3');
		$wow = $db->fetch_field($type3, 'count3');
		$type4 = $db->simple_select('dnt_post_rate', 'COUNT(pcl_count) as count4', 'pcl_pid='.(int)$pcl['pcl_pid'].' AND pcl_type=4');
		$smiles = $db->fetch_field($type4, 'count4');
		$type5 = $db->simple_select('dnt_post_rate', 'COUNT(pcl_count) as count5', 'pcl_pid='.(int)$pcl['pcl_pid'].' AND pcl_type=5');
		$crys = $db->fetch_field($type5, 'count5');
		$type6 = $db->simple_select('dnt_post_rate', 'COUNT(pcl_count) as count6', 'pcl_pid='.(int)$pcl['pcl_pid'].' AND pcl_type=6');
		$angrys = $db->fetch_field($type6, 'count6');	
		$total = $likes + $loves + $wow + $smiles + $crys + $angrys;
		$pcl['pcl_rates'] = array(
			'likes' => $likes,
			'loves' => $loves,
			'wow' => $wow,
			'smiles' => $smiles,
			'crys' => $crys,
			'angrys' => $angrys,
			'total' => $total
		);
		$pcl['pcl_rates'] = serialize($pcl['pcl_rates']);
		$db->update_query("posts", array("pcl_rates_posts" => $db->escape_string($pcl['pcl_rates'])), "pid='{$pcl['pcl_pid']}'");		
	}	
	pcl_check_proceed($pcl_count, $end, $cur_page+1, $per_page, "pcl_threads_chunk_size", "do_recount_rates_threads", $db->escape_string($lang->pcl_update_tsuccess));	
}

function do_pcl_users_recount()
{
	global $db, $mybb, $lang;

	if(!$mybb->settings['dnt_post_rate_active'] || !empty($session->is_spider))
	{
		return false;
	}	

	$lang->load('dnt_post_rate',false,true);	
	
	$cur_page = (int)$mybb->input['page'];
	$per_page = (int)$mybb->input['pcl_users_chunk_size'];
	$start = ($cur_page-1) * $per_page;
	$end = $start + $per_page;

	if ($cur_page == 1)
	{
		$db->write_query("UPDATE ".TABLE_PREFIX."users SET pcl_rates_given='0', pcl_rates_received='0'");
		$db->write_query("UPDATE ".TABLE_PREFIX."threads SET pcl_total='0'");		
	}

	$query = $db->simple_select("dnt_post_rate", "COUNT(pcl_id) AS pcl_count");
	$pcl_count = $db->fetch_field($query, 'pcl_count');

	$query = $db->query("SELECT pcl_user, pcl_sender, pcl_tid
		FROM ".TABLE_PREFIX."dnt_post_rate
		ORDER BY pcl_id ASC
		LIMIT {$start}, {$per_page}"
	);

	$user_given = array();
	$user_received = array();
	$pcl_total = array();
	
	while($pcl = $db->fetch_array($query))
	{
		if($user_given[$pcl['pcl_sender']])
		{
			$user_given[$pcl['pcl_sender']]++;
		}
		else
		{
			$user_given[$pcl['pcl_sender']] = 1;
		}
		if($user_received[$pcl['pcl_user']])
		{
			$user_received[$pcl['pcl_user']]++;
		}
		else
		{
			$user_received[$pcl['pcl_user']] = 1;
		}
		if($pcl_total[$pcl['pcl_tid']])
		{
			$pcl_total[$pcl['pcl_tid']]++;
		}
		else
		{
			$pcl_total[$pcl['pcl_tid']] = 1;
		}	
	}

	if(is_array($user_given))
	{
		foreach($user_given as $uid => $change)
		{
			$db->write_query("UPDATE ".TABLE_PREFIX."users SET pcl_rates_given=pcl_rates_given+{$change} WHERE uid='{$uid}'");
		}
	}
	if(is_array($user_received))
	{
		foreach($user_received as $touid => $change)
		{
			$db->write_query("UPDATE ".TABLE_PREFIX."users SET pcl_rates_received=pcl_rates_received+{$change} WHERE uid='{$touid}'");
		}
	}
	if(is_array($pcl_total))
	{
		foreach($pcl_total as $tid => $total)
		{
			$db->write_query("UPDATE ".TABLE_PREFIX."threads SET pcl_total=pcl_total+{$total} WHERE tid='{$tid}'");			
		}
	}

	pcl_check_proceed($pcl_count, $end, $cur_page+1, $per_page, "pcl_users_chunk_size", "do_recount_rates_users", $db->escape_string($lang->pcl_update_usuccess));	
}

function pcl_check_proceed($current, $finish, $next_page, $per_page, $name_chunk, $name_submit, $message)
{
	global $mybb, $db, $page, $lang;

	if(!$mybb->settings['dnt_post_rate_active'] || !empty($session->is_spider))
	{
		return false;
	}	

	$lang->load('dnt_post_rate',false,true);	
	
	if($finish >= $current)
	{
		flash_message($message, 'success');
		admin_redirect("index.php?module=tools-recount_pcl_rates");
	}
	else
	{
		$page->output_header();

		$form = new Form("index.php?module=tools-recount_pcl_rates", 'post');
        $total = $current - $finish;
		echo $form->generate_hidden_field("page", $next_page);
		echo $form->generate_hidden_field($name_chunk, $per_page);
		echo $form->generate_hidden_field($name_submit, $lang->pcl_upgrade_do);
		echo "<div class=\"confirm_action\">\n";
		echo $db->escape_string($lang->pcl_confirm_next);
		echo "<br />\n";
		echo "<br />\n";
		echo "<script type=\"text/javascript\">$(function() { var button = $(\"#submit_button\"); if(button.length > 0) { button.val(\"Loading data...\"); button.attr(\"disabled\", true); button.css(\"color\", \"#aaa\"); button.css(\"borderColor\", \"#aaa\"); document.forms[0].submit(); }})</script>";
		echo "<p class=\"buttons\">\n";
		echo $form->generate_submit_button($db->escape_string($lang->pcl_confirm_button), array('class' => 'button_yes', 'id' => 'submit_button'));
		echo "</p>\n";
		echo "<div style=\"float: right; color: #424242;\">".$db->escape_string($lang->pcl_confirm_page)." {$next_page}\n";
		echo "<br />\n";
		echo $db->escape_string($lang->pcl_confirm_elements)." {$total}</div>";
		echo "<br />\n";
	    echo "<br />\n";
		echo "</div>\n";		
		$form->end();
		$page->output_footer();
		exit;
	}
}

function dnt_post_rate_verify_update()
{
	global $mybb;
	if(!$mybb->settings['dnt_post_rate_active'] || !empty($session->is_spider))
		return false;
	if(!isset($mybb->settings['dnt_post_rate_version']))
		dnt_post_rate_update15();
	else
		dnt_post_rate_updated();
}

function dnt_post_rate_updated()
{
	global $mybb, $lang;
	if(!$mybb->settings['dnt_post_rate_active'] || !empty($session->is_spider))
		return false;
	$lang->load('dnt_post_rate',false,true);	
	if($mybb->settings['dnt_post_rate_version'] > 140)
	{
		flash_message($lang->pcl_update_version_ok, 'success');
		admin_redirect('index.php?module=config-plugins');
	}
	else
	{
		flash_message($lang->pcl_update_version_bad, 'error');
		admin_redirect('index.php?module=config-plugins');
	}
}

function dnt_post_rate_update15()
{
	global $mybb;
	if(!$mybb->settings['dnt_post_rate_active'] || !empty($session->is_spider))
		return false;
	dnt_post_rate_update15_run();	
}

function dnt_post_rate_update15_run()
{
	global $db, $mybb, $lang;
	if(!$mybb->settings['dnt_post_rate_active'] || !empty($session->is_spider))
		return false;
	$lang->load('dnt_post_rate',false,true);	
	if(!$db->field_exists("pcl_pid", "dnt_post_rate"))
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."dnt_post_rate ADD pcl_pid` int(10) NOT NULL DEFAULT '0'");
	if(!$db->field_exists("pcl_rates_threads", "threads") && $db->field_exists("pcl_rates", "threads"))
		$db->query("RENAME TABLE ".TABLE_PREFIX."pcl_rates TO ".TABLE_PREFIX."pcl_rates_threads");
	else if(!$db->field_exists("pcl_rates_threads", "threads"))
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."threads` ADD `pcl_rates_threads` int(10) NOT NULL DEFAULT '0'");		
	if(!$db->field_exists("pcl_total", "threads"))
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."threads` ADD `pcl_total` int(10) NOT NULL DEFAULT '0'");
	if(!$db->field_exists("pcl_rates_posts", "posts"))
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."posts` ADD `pcl_rates_posts` text NOT NULL");
	if(!$db->field_exists("pcl_rates_given", "users"))
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."users` ADD `pcl_rates_given` int(10) NOT NULL DEFAULT '0'");
	if(!$db->field_exists("pcl_rates_received", "users"))
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."users` ADD `pcl_rates_received` int(10) NOT NULL DEFAULT '0'");	
	$content = array(
        'name' => 'dnt_post_rate_version',
        'title' =>  "Post Rate Version",
        'description' => "Plugin version of DNT Post Rate plugin",
        'optionscode' => 'text',
        'value' => 150,
        'disporder' => 0,
        'gid' => 0
    );
	$db->insert_query("settings", $content);
	
	rebuild_settings();	
	flash_message($lang->pcl_update_version, 'success');
	admin_redirect('index.php?module=config-plugins');
}

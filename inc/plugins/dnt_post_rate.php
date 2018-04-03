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
	$plugins->add_hook('global_start', 'dnt_post_rate_templates');
	$plugins->add_hook('global_intermediate', 'dnt_post_rate_script');	
	$plugins->add_hook('member_profile_end', 'dnt_post_rate_member');
	$plugins->add_hook('forumdisplay_thread', 'dnt_post_rates');
	$plugins->add_hook('datahandler_post_insert_thread','dnt_post_rate_insert_thread');
	$plugins->add_hook('datahandler_post_insert_post','dnt_post_rate_insert_post');	
	$plugins->add_hook('datahandler_post_insert_thread_post','dnt_post_rate_insert_post');
	$plugins->add_hook("fetch_wol_activity_end", "dnt_post_rate_wol_activity");
	$plugins->add_hook("build_friendly_wol_location_end", "dnt_post_rate_friendly_wol_activity");	
}

//Fix broken templates...
if(isset($GLOBALS['templatelist']))
{
	if(THIS_SCRIPT == "member.php")
	$GLOBALS['templatelist'] .= "dnt_prt_results1, dnt_prt_results2, dnt_prt_results3, dnt_prt_results4, dnt_prt_results5, dnt_prt_results6, dnt_prt_likes, dnt_prt_clasify_post_rates_msg_memprofile, dnt_prt_stats, dnt_prt_templatesg, dnt_prt_templatesr";
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
			$dpr_integrate = '<br /><a href="index.php?module=config-plugins&amp;action=dnt_post_rate_myalerts_integrate" style="float: right;">'.htmlspecialchars_uni($lang->dnt_prt_integrate_alerts).'</a>';			
	}

	if(isset($mybb->settings['dnt_post_rate_active']))
		$dpr_config = '<div style="float: right;"><a href="index.php?module=config&amp;action=change&amp;search=dnt_post_rate" style="color:#035488; background: url(../images/icons/brick.png) no-repeat 0px 18px; padding: 21px; text-decoration: none;">'.htmlspecialchars_uni($lang->dnt_prt_configure).'</a></div>';

	if(!isset($mybb->settings['dnt_post_rate_version']) && isset($mybb->settings['dnt_post_rate_active']))
		$dpr_config .= '<div style="float: right;"><a href="index.php?module=config-plugins&amp;action=dnt_post_rate_verify_update" style="color:#035488; padding: 21px; text-decoration: none;">'.htmlspecialchars_uni($lang->dnt_prt_update_to_15).'</a></div>';
	else if($mybb->settings['dnt_post_rate_version'] < 163 && $mybb->settings['dnt_post_rate_active'])
		$dpr_config .= '<div style="float: right;"><a href="index.php?module=config-plugins&amp;action=dnt_post_rate_verify_update" style="color:#035488; padding: 21px; text-decoration: none;">'.htmlspecialchars_uni($lang->dnt_prt_update_to_16).'</a></div>';
	else if($mybb->settings['dnt_post_rate_version'] >= 163 && $mybb->settings['dnt_post_rate_active'])
		$dpr_config .= "";
	
	return array(
		"name" => "Post Rate",
		"description" => "Add emoji reactions to posts".$dpr_config.$dpr_integrate,
		"website" => "",
		"author" => "Whiteneo",
		"authorsite" => "https://soportemybb.es",
		"version" => "1.6.4",
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
  `dnt_prt_id` int(10) NOT NULL auto_increment,
  `dnt_prt_type` int(5) NOT NULL default '0',	  
  `dnt_prt_user` int(10) NOT NULL default '0',
  `dnt_prt_sender` int(10) NOT NULL default '0',
  `dnt_prt_tid` int(10) NOT NULL default '0',
  `dnt_prt_pid` int(10) NOT NULL default '0', 
  `dnt_prt_count` int(10) NOT NULL default '0', 
  `dnt_prt_date` int(10) NOT NULL default '0',
  PRIMARY KEY  (`dnt_prt_id`)
) DEFAULT CHARSET={$charset};";
	$db->write_query($tables);
	if(!$db->field_exists("dnt_prt_total", "threads"))
	{
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."threads` ADD `dnt_prt_total` int(10) NOT NULL DEFAULT '0'");
	}
	
	if(!$db->field_exists("dnt_prt_rates_threads", "threads"))
	{
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."threads` ADD `dnt_prt_rates_threads` text NOT NULL");
	}	

	if(!$db->field_exists("dnt_prt_rates_threads_post", "threads"))
	{
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."threads` ADD `dnt_prt_rates_threads_post` text NOT NULL");
	}
	
	if(!$db->field_exists("dnt_prt_rates_posts", "posts"))
	{
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."posts` ADD `dnt_prt_rates_posts` text NOT NULL");
	}

	if(!$db->field_exists("dnt_prt_rates_given", "users"))
	{
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."users` ADD `dnt_prt_rates_given` int(10) NOT NULL DEFAULT '0'");
	}

	if(!$db->field_exists("dnt_prt_rates_received", "users"))
	{
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."users` ADD `dnt_prt_rates_received` int(10) NOT NULL DEFAULT '0'");
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
		$page->output_confirm_action('index.php?module=config-plugins&action=deactivate&uninstall=1&plugin=dnt_post_rate', $lang->dnt_prt_remove_data, $lang->dnt_prt_remove_data_desc);
	}
	if(!isset($mybb->input['no']))
	{
		if($db->table_exists("dnt_post_rate"))
			$db->write_query('DROP TABLE `'.TABLE_PREFIX.'dnt_post_rate`');
		if($db->field_exists("dnt_prt_rates_posts", "posts"))
			$db->write_query("ALTER TABLE `".TABLE_PREFIX."posts` DROP `dnt_prt_rates_posts`");
		if($db->field_exists("dnt_prt_rates_threads", "threads"))
			$db->write_query("ALTER TABLE `".TABLE_PREFIX."threads` DROP `dnt_prt_rates_threads`");
		if($db->field_exists("dnt_prt_rates_threads_post", "threads"))
			$db->write_query("ALTER TABLE `".TABLE_PREFIX."threads` DROP `dnt_prt_rates_threads_post`");		
		if($db->field_exists("dnt_prt_total", "threads"))
			$db->write_query("ALTER TABLE `".TABLE_PREFIX."threads` DROP `dnt_prt_total`");
		if($db->field_exists("dnt_prt_rates_given", "users"))
			$db->write_query("ALTER TABLE `".TABLE_PREFIX."users` DROP `dnt_prt_rates_given`");
		if($db->field_exists("dnt_prt_rates_received", "users"))
			$db->write_query("ALTER TABLE `".TABLE_PREFIX."users` DROP `dnt_prt_rates_received`");		
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
	$query = $db->simple_select("settinggroups", "COUNT(*) as item_rows");
	$item_rows = $db->fetch_field($query, "item_rows");

	$new_groupconfig = array(
		'name' => 'dnt_post_rate', 
		'title' => 'Post rate plugin',
		'description' => 'Add emoji reactions to posts plugin',
		'disporder' => $item_rows+1,
		'isdefault' => 0
	);
	
	$group['gid'] = $db->insert_query("settinggroups", $new_groupconfig);

	// Insert dnt_post_rate options
	$new_config = array();

	$new_config[] = array(
		'name' => 'dnt_post_rate_active',
		'title' => 'Enable / Disable plugin',
		'description' => 'Enabled or disabled emoji reactions rating on your board',
		'optionscode' => 'yesno',
		'value' => 1,
		'disporder' => 1,
		'gid' => $group['gid']
	);
	
	$new_config[] = array(
		'name' => 'dnt_post_rate_forums',
		'title' => 'Select forums where emoji reactions are enabled',
		'description' => 'Select from the list forums where this mod takes effect',
		'optionscode' => 'forumselect',
		'value' => "-1",
		'disporder' => 2,
		'gid' => $group['gid']
	);

	$new_config[] = array(
		'name' => 'dnt_post_rate_groups',
		'title' => 'Select usergroups who can use emojis in posts',
		'description' => 'Select usergroups from the list for the users who can use emojis in posts',
		'optionscode' => 'groupselect',
		'value' => "-1",
		'disporder' => 3,
		'gid' => $group['gid']
	);

	$new_config[] = array(
		'name' => 'dnt_post_rate_highlight',
		'title' => 'Highlight posts with this ammount of emojis',
		'description' => 'Set the ammount of rates given to highlight posts with emojis contents with this ammount or more',
		'optionscode' => 'numeric',
		'value' => 50,
		'disporder' => 4,
		'gid' => $group['gid']
	);

	$new_config[] = array(
		'name' => 'dnt_post_rate_limit',
		'title' => 'Limit to search data',
		'description' => 'Set limit in days to search into database, by default 30 days (Leave empty or set to 0 for no limits)',
		'optionscode' => 'numeric',
		'value' => 0,
		'disporder' => 5,
		'gid' => $group['gid']
	);

	$new_config[] = array(
		'name' => 'dnt_post_rate_limit_users',
		'title' => 'Limit to search userlist',
		'description' => 'Set limit of max number of users to show into modal hover and default counter, by default 10 (Leave empty or set to 0 to load emotion type and saves 1 query, besides loads usernames and date and use 1 query).',
		'optionscode' => 'numeric',
		'value' => 15,
		'disporder' => 6,
		'gid' => $group['gid']
	);

	$new_config[] = array(
		'name' => 'dnt_post_rate_limit_page',
		'title' => 'Items to load per page into emotions page',
		'description' => 'Set limit of max number of items to whos on every new page called by this mod to list emotions by users, by default 20',
		'optionscode' => 'numeric',
		'value' => 20,
		'disporder' => 7,
		'gid' => $group['gid']
	);
	
	$new_config[] = array(
		'name' => 'dnt_post_rate_postbit',
		'title' => 'Post rates received and given in postbit',
		'description' => 'Show this stats on user posts information',
		'optionscode' => 'yesno',
		'value' => 1,
		'disporder' => 8,
		'gid' => $group['gid']
	);

	$new_config[] = array(
		'name' => 'dnt_post_rate_memprofile',
		'title' => 'Post rates received and given in user profile',
		'description' => 'Show emotions given and received and link to it into member profiles',
		'optionscode' => 'yesno',
		'value' => 1,
		'disporder' => 9,
		'gid' => $group['gid']
	);

	$new_config[] = array(
		'name' => 'dnt_post_rate_top5_given_memprofile',
		'title' => 'List emotions given in user profile',
		'description' => 'Show emotions given by users listed in user profiles',
		'optionscode' => 'yesno',
		'value' => 1,
		'disporder' => 10,
		'gid' => $group['gid']
	);

	$new_config[] = array(
		'name' => 'dnt_post_rate_top5_received_memprofile',
		'title' => 'List emotions received in user profile',
		'description' => 'Show emotions received by users listed in user profiles',
		'optionscode' => 'yesno',
		'value' => 1,
		'disporder' => 11,
		'gid' => $group['gid']
	);

	$new_config[] = array(
		'name' => 'dnt_post_rate_showthread',
		'title' => 'Show rates into threalist',
		'description' => 'Set this to No to not show in threalist all rates used into every thread, that contains all posts inside threads too.',
		'optionscode' => 'yesno',
		'value' => 1,
		'disporder' => 12,
		'gid' => $group['gid']
	);

	$new_config[] = array(
		'name' => 'dnt_post_rate_showthread_all',
		'title' => 'Show all rates into threalist',
		'description' => 'Set this to No to not show only the first post count into thread lists(Otherwise all posts must be counted for every thread)',
		'optionscode' => 'yesno',
		'value' => 1,
		'disporder' => 13,
		'gid' => $group['gid']
	);
	
	$new_config[] = array(
		'name' => 'dnt_post_rate_only_firspost',
		'title' => 'Use only in first post',
		'description' => 'Use this mod only for the first post or all posts. Set to No if you wish to show all posts (It requires more querys)',
		'optionscode' => 'yesno',
		'value' => 1,
		'disporder' => 14,
		'gid' => $group['gid']
	);

	$new_config[] = array(
		'name' => 'dnt_post_rate_remove',
		'title' => 'Users can remove emojis given?',
		'description' => 'Enable if you like users can remove own emojis given or not',
		'optionscode' => 'yesno',
		'value' => 1,
		'disporder' => 15,
		'gid' => $group['gid']
	);	
	
	$new_config[] = array(
		'name' => 'dnt_post_rate_antiflood',
		'title' => 'Enable antiflood',
		'description' => 'Applies to only non moderatos a protection with 30 seconds between emojis given, prevent spam.',
		'optionscode' => 'yesno',
		'value' => 1,
		'disporder' => 16,
		'gid' => $group['gid']
	);	

	$new_config[] = array(
		'name' => 'dnt_post_rate_minify',
		'title' => 'Minify javascript',
		'description' => 'Use this to renderize your website besides using external libraries or codes that would broke js. Set to No if you wish to use normal js code',
		'optionscode' => 'yesno',
		'value' => 1,
		'disporder' => 17,
		'gid' => $group['gid']
	);	
	
	$new_config[] = array(
		'name' => 'dnt_post_rate_version',
		'title' => 'Actual version of post rate plugin.',
		'description' => 'This is the actual version of your post rate system installed',
		'optionscode' => 'numeric',
		'value' => 164,
		'disporder' => 0,
		'gid' => 0
	);
	
	foreach($new_config as $array => $content)
	{
		$db->insert_query("settings", $content);
	}
	
	// Creating stylesheet...
	$stylesheet_css = '.post_rate_list {position: absolute; z-index:9999; background: #fff; margin: -95px 0px 0px 225px; border-radius: 40px}
.post_rate_button {color: #fff; text-shadow: 1px 1px 1px #000; height: 26px; line-height: 26px; padding: 0 10px; text-decoration: none; margin-left: 4px; display: inline-block; cursor: pointer; border-radius: 4px; font-size: 13px; background: #0F5579}
.post_rate_btn img {cursor: pointer; margin-top: 2px; transform: scale(1.00); transition: all 0.25s ease-in}
.post_rate_btn img:hover {transform: scale(1.25); transition: all 0.25s ease-in; background: #e6e6e6; border-radius: 40px}
.ptr_list {display: none; position: absolute; background: #0b0a0a; color: #e4dada; padding: 6px; border-radius: 3px; font-size: 10px; margin-top: 20px; margin-left: 2px; z-index: 999999; width: auto; font-weight: normal}
.ptr_list_title {display: none; position: absolute; background: #0b0a0a; color: #fff; padding: 6px; border-radius: 12px; font-size: 9px; font-weight: bold; margin-left: -10px; z-index: 999999; text-align: center; min-width: 55px; width: auto}
.dnt_prt_list_span {padding: 0 40px; height: 40px; position: relative}
.dnt_prt_ulist > span {display: block}
.dnt_prt_list {padding: 10px; font-size: 13px; display: inline-block; width: 98%}
.dnt_prt_list img {margin-top: -10px}
.dnt_post_hl {background-color: rgba(25,119,150,0.3); margin: 5px; border-radius: 3px; border-left: 2px solid #4d5e77}
.dnt_popular_post {border: 1px dotted; border-radius: 2px; border-color: rgba(112,202,47,0.5); background-color: rgba(139,195,74,0.3)}
.dnt_prt_div_rate {display: inline-block !important; cursor: pointer}
.dnt_prt_div_rate img {width: 19px; height: 19px; position: absolute}
.dnt_prt_div_rate span {margin-left: 20px; font-weight: bold}
.dnt_prt_list .dnt_prt_div_rate {cursor: context-menu}
.dnt_prt_list_avatar {padding: 3px; border: 1px solid #D8DFEA; width: 30px; height: 30px; border-radius: 50%; margin-top: -10px; position: absolute}
.clasify_post_norates_msg {background-color: rgba(185,65,25,0.3); margin: 5px; color: #6f2f16; font-weight: bold; font-size: 11px; padding: 10px; border-radius: 3px; display: block; width: 95%}
.clasify_post_rates_msg {background-color: rgba(102,189,218,0.3); margin: 5px; color: #315284; font-weight: bold; font-size: 11px; padding: 10px; border-radius: 3px; display: block; width: 95%}
.clasify_post_rates_msg_span {font-size: 8px; font-weight: bold; position: absolute; background: #ce5757; padding: 1px 3px; color: #f0f0f0; border-radius: 4px; border-radius: 3px; margin-top: -5px}
.clasify_post_rates_msg img {cursor: pointer}
.clasify_post_rates_mp {background-color: rgba(102,189,218,0.3); margin: 5px; color: #315284; font-weight: bold; font-size: 11px; padding: 10px; border-radius: 3px; display: block; width: 95%}
.clasify_post_rates_mp_span {font-size: 8px; font-weight: bold; position: absolute; background: #ce5757; padding: 1px 3px; color: #f0f0f0; border-radius: 4px; border-radius: 3px; margin-top: -5px}
.clasify_post_rates_mp img {cursor: pointer}
.dnt_post_hl_mp {background-color: rgba(25,119,150,0.3); margin: 5px; border-radius: 3px; border-left: 2px solid #4d5e77}
@media screen and (-moz-min-device-pixel-ratio: 0) {
	.dnt_prt_div_rate img {margin-top: -12px;}
}
@media screen and (-webkit-min-device-pixel-ratio: 0) {
	.dnt_prt_div_rate img {margin-top: 0px}
	.ptr_list {margin-top: 5px}
	.dnt_prt_div_rate img {margin-top: -1px}
}
@media only screen and (min-device-width: 320px) and (max-device-width: 550px) {
	.post_rate_list {position: absolute; z-index: 9999; background: #fff; right: 90px; margin-top: -65px; border-radius: 35px}
	.post_rate_button {color: #fff; text-shadow: 1px 1px 1px #000; height: 27px; line-height: 25px; padding: 0 6px; text-decoration: none; display: inline-block; cursor: pointer; border-radius: 4px; font-size: 12px; background: #0F5579 !important; position: absolute; margin: -29px 0px 0px 32px}
	.post_rate_btn img {cursor:pointer; margin-top: 2px; transform: scale(1.00); transition: all 0.25s ease-in; width: 35px; height: 35px}
	.post_rate_btn img:hover {transform: scale(1.25); transition: all 0.25s ease-in; background: #e6e6e6; border-radius: 40px}	
}';

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
	$db->update_query('themestylesheets', array('cachefile' => "pcl.css"), "sid='{$sid}'", 1);
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
	find_replace_templatesets("member_profile", '#'.preg_quote('{$profilefields}').'#', '{$profilefields}{$memprofile[\'dnt_prt\']}{$memprofile[\'top5_given\']}{$memprofile[\'top5_received\']}');
	find_replace_templatesets("postbit", '#'.preg_quote('{$post[\'user_details\']}').'#', '{$post[\'user_details\']}{$post[\'rates_given\']}{$post[\'rates_received\']}', 0);
	find_replace_templatesets("postbit_classic", '#'.preg_quote('{$post[\'user_details\']}').'#', '{$post[\'user_details\']}{$post[\'rates_given\']}{$post[\'rates_received\']}', 0);
	find_replace_templatesets("postbit", '#'.preg_quote('post_content').'#', 'post_content{$post[\'dnt_prt_hl_post\']}', 0);
	find_replace_templatesets("postbit_classic", '#'.preg_quote('post_content').'#', 'post_content{$post[\'dnt_prt_hl_post\']}', 0);
	
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
	$db->delete_query("settings", "name LIKE ('dnt_post_rate_%')");
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
	find_replace_templatesets("headerinclude", '#'.preg_quote('{$dnt_prt_script}').'#', '', 0);	
	find_replace_templatesets("postbit", '#'.preg_quote('{$post[\'clasify_post_rates\']}').'#', '', 0);
	find_replace_templatesets("postbit_classic", '#'.preg_quote('{$post[\'clasify_post_rates\']}').'#', '', 0);	
	find_replace_templatesets("forumdisplay_thread", '#'.preg_quote('{$dnt_prt_rates}').'#', '', 0);	
	find_replace_templatesets("postbit", '#'.preg_quote('{$post[\'dnt_prt_rates_given\']}').'#', '', 0);
	find_replace_templatesets("postbit", '#'.preg_quote('{$post[\'dnt_prt_rates_received\']}').'#', '', 0);
	find_replace_templatesets("postbit_classic", '#'.preg_quote('{$post[\'dnt_prt_rates_given\']}').'#', '', 0);
	find_replace_templatesets("postbit_classic", '#'.preg_quote('{$post[\'dnt_prt_rates_received\']}').'#', '', 0);
	find_replace_templatesets("postbit", '#'.preg_quote('{$post[\'rates_given\']}').'#', '', 0);
	find_replace_templatesets("postbit", '#'.preg_quote('{$post[\'rates_received\']}').'#', '', 0);		
	find_replace_templatesets("postbit_classic", '#'.preg_quote('{$post[\'rates_given\']}').'#', '', 0);
	find_replace_templatesets("postbit_classic", '#'.preg_quote('{$post[\'rates_received\']}').'#', '', 0);
	find_replace_templatesets("postbit", '#'.preg_quote('{$post[\'dnt_prt_hl_post\']}').'#', '', 0);	
	find_replace_templatesets("postbit_classic", '#'.preg_quote('{$post[\'dnt_prt_hl_post\']}').'#', '', 0);	
	find_replace_templatesets("member_profile", '#'.preg_quote('{$memprofile[\'dnt_prt\']}').'#', '', 0);
	find_replace_templatesets("member_profile", '#'.preg_quote('{$memprofile[\'top5_given\']}').'#', '', 0);
	find_replace_templatesets("member_profile", '#'.preg_quote('{$memprofile[\'top5_received\']}').'#', '', 0);
	
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
		'prefix' => 'dnt',
		'title'  => 'Post Rate'
	);
	$db->insert_query("templategroups", $templategrouparray);

	// Adding every template needed ...
	$templatearray = array(
		'title' => 'dnt_prt_page',
		'template' => "<html>
	<head>
		<title>{\$mybb->settings[\'bbname\']} - {\$lang->dnt_prt_rates_page_title}</title>
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
			<strong>{\$lang->dnt_prt_rates}</strong>
		</div>
	</div>
	<div class=\"trow1\">
		{\$dnt_prt_list}
	</div>
</table>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
		);
	$db->insert_query("templates", $templatearray);	
	
	$templatearray = array(
		'title' => 'dnt_prt_list',
		'template' => "<div class=\"dnt_prt_list {\$trow}\">
	{\$dnt_prt_rows[\'avatar\']}
	<span class=\"dnt_prt_list_span\">{\$dnt_prt_rows[\'username\']}{\$dnt_prt_rows[\'rate_to\']}</span>
</div>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
		);	
	$db->insert_query("templates", $templatearray);
	
	$templatearray = array(
		'title' => 'dnt_prt_list_none',
		'template' => "<div class=\"dnt_prt_ulist {\$trow}\">
	<span>{\$lang->dnt_prt_not_received}</span>
</div>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
		);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_list_rate1',
		'template' => "<div class=\"dnt_prt_div_rate\">
	<img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/like.png\" alt=\"{\$lang->dnt_prt_like}\" />
	<span style=\"color:#1b43b6;\">{\$lang->dnt_prt_like}</span>
</div>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
		);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_list_rate2',
		'template' => "<div class=\"dnt_prt_div_rate\">
	<img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/love.png\" alt=\"{\$lang->dnt_prt_love}\" />
	<span style=\"color:#e61b3f;\">{\$lang->dnt_prt_love}</span>
</div>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_list_rate3',
		'template' => "<div class=\"dnt_prt_div_rate\">
	<img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/wow.png\" alt=\"{\$lang->dnt_prt_wow}\" />
	<span style=\"color:#cfcd35;\">{\$lang->dnt_prt_wow}</span>
</div>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_list_rate4',
		'template' => "<div class=\"dnt_prt_div_rate\">
	<img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/smile.png\" alt=\"{\$lang->dnt_prt_smile}\" />
	<span style=\"color:#cfcd35;\">{\$lang->dnt_prt_smile}</span>
</div>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_list_rate5',
		'template' => "<div class=\"dnt_prt_div_rate\">
	<img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/cry.png\" alt=\"{\$lang->dnt_prt_cry}\" />
	<span style=\"color:#1b43b6;\">{\$lang->dnt_prt_cry}</span>
</div>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_list_rate6',
		'template' => "<div class=\"dnt_prt_div_rate\">
	<img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/angry.png\" alt=\"{\$lang->dnt_prt_angry}\" />
	<span style=\"color:#c22e0f;\">{\$lang->dnt_prt_angry}</span>
</div>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);
	
	$templatearray = array(
		'title' => 'dnt_prt_results_1',
		'template' => "<div {\$post[\'dnt_prt_remove\']} class=\"dnt_prt_div_rate\">
	<img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/like.png\" alt=\"{\$lang->dnt_prt_like}\" />
	<span style=\"color:#1b43b6;\">{\$lang->dnt_prt_like}</span>
</div>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_results_2',
		'template' => "<div {\$post[\'dnt_prt_remove\']} class=\"dnt_prt_div_rate\">
	<img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/love.png\" alt=\"{\$lang->dnt_prt_love}\" />
	<span style=\"color:#1b43b6;\">{\$lang->dnt_prt_love}</span>
</div>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_results_3',
		'template' => "<div {\$post[\'dnt_prt_remove\']} class=\"dnt_prt_div_rate\">
	<img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/wow.png\" alt=\"{\$lang->dnt_prt_wow}\" />
	<span style=\"color:#1b43b6;\">{\$lang->dnt_prt_wow}</span>
</div>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_results_4',
		'template' => "<div {\$post[\'dnt_prt_remove\']} class=\"dnt_prt_div_rate\">
	<img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/smile.png\" alt=\"{\$lang->dnt_prt_smile}\" />
	<span style=\"color:#1b43b6;\">{\$lang->dnt_prt_smile}</span>
</div>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_results_5',
		'template' => "<div {\$post[\'dnt_prt_remove\']} class=\"dnt_prt_div_rate\">
	<img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/cry.png\" alt=\"{\$lang->dnt_prt_cry}\" />
	<span style=\"color:#1b43b6;\">{\$lang->dnt_prt_cry}</span>
</div>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_results_6',
		'template' => "<div {\$post[\'dnt_prt_remove\']} class=\"dnt_prt_div_rate\">
	<img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/angry.png\" alt=\"{\$lang->dnt_prt_angry}\" />
	<span style=\"color:#1b43b6;\">{\$lang->dnt_prt_angry}</span>
</div>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	
	
	$templatearray = array(
		'title' => 'dnt_prt_post_clasify_post_rates',
		'template' => "<div class=\"post_rate_button\" id=\"post_rates_btn_{\$pid}\" onclick=\"DNTShowMenu({\$pid})\">{\$lang->dnt_prt_rate}</div>
<div id=\"post_rates_{\$pid}\" class=\"post_rate_list\" style=\"display:none;\">
	<span onclick=\"javascript:DNTPostRate(1, {\$tid}, {\$pid})\" class=\"post_rate_btn\"><img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/like.gif\" alt=\"{\$lang->dnt_prt_like}\" /><span class=\"ptr_list_title\">{\$lang->dnt_prt_like}</span></span>
	<span onclick=\"javascript:DNTPostRate(2, {\$tid}, {\$pid})\" class=\"post_rate_btn\"><img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/love.gif\" alt=\"{\$lang->dnt_prt_love}\" /><span class=\"ptr_list_title\">{\$lang->dnt_prt_love}</span></span>
	<span onclick=\"javascript:DNTPostRate(3, {\$tid}, {\$pid})\" class=\"post_rate_btn\"><img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/wow.gif\" alt=\"{\$lang->dnt_prt_wow}\" /><span class=\"ptr_list_title\">{\$lang->dnt_prt_wow}</span></span>
	<span onclick=\"javascript:DNTPostRate(4, {\$tid}, {\$pid})\" class=\"post_rate_btn\"><img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/smile.gif\" alt=\"{\$lang->dnt_prt_smile}\" /><span class=\"ptr_list_title\">{\$lang->dnt_prt_smile}</span></span>
	<span onclick=\"javascript:DNTPostRate(5, {\$tid}, {\$pid})\" class=\"post_rate_btn\"><img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/cry.gif\" alt=\"{\$lang->dnt_prt_cry}\" /><span class=\"ptr_list_title\">{\$lang->dnt_prt_cry}</span></span>
	<span onclick=\"javascript:DNTPostRate(6, {\$tid}, {\$pid})\" class=\"post_rate_btn\"><img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/angry.gif\" alt=\"{\$lang->dnt_prt_angry}\" /><span class=\"ptr_list_title\">{\$lang->dnt_prt_angry}</span></span>
</div>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_results1',
		'template' => "<img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/like.png\" alt=\"{\$lang->dnt_prt_like}\" onmouseover=\"javascript:DNTPostRates(1, {\$tid}, {\$pid})\" onmouseout=\"javascript:DNTPostRatesRemove(1, {\$tid}, {\$pid})\" onclick=\"location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=1&amp;tid={\$tid}&amp;pid={\$pid}\'\" />
	<span id=\"prt_list1_pid{\$pid}\" class=\"ptr_list\"></span>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_results2',
		'template' => "<img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/love.png\" alt=\"{\$lang->dnt_prt_love}\" onmouseover=\"javascript:DNTPostRates(2, {\$tid}, {\$pid})\" onmouseout=\"javascript:DNTPostRatesRemove(2, {\$tid}, {\$pid})\" onclick=\"location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=2&amp;tid={\$tid}&amp;pid={\$pid}\'\" />
	<span id=\"prt_list2_pid{\$pid}\" class=\"ptr_list\"></span>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_results3',
		'template' => "<img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/wow.png\" alt=\"{\$lang->dnt_prt_wow}\" onmouseover=\"javascript:DNTPostRates(3, {\$tid}, {\$pid})\" onmouseout=\"javascript:DNTPostRatesRemove(3, {\$tid}, {\$pid})\" onclick=\"location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=3&amp;tid={\$tid}&amp;pid={\$pid}\'\" />
	<span id=\"prt_list3_pid{\$pid}\" class=\"ptr_list\"></span>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_results4',
		'template' => "<img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/smile.png\" alt=\"{\$lang->dnt_prt_smile}\" onmouseover=\"javascript:DNTPostRates(4, {\$tid}, {\$pid})\" onmouseout=\"javascript:DNTPostRatesRemove(4, {\$tid}, {\$pid})\" onclick=\"location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=4&amp;tid={\$tid}&amp;pid={\$pid}\'\" />
	<span id=\"prt_list4_pid{\$pid}\" class=\"ptr_list\"></span>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_results5',
		'template' => "<img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/cry.png\" alt=\"{\$lang->dnt_prt_cry}\" onmouseover=\"javascript:DNTPostRates(5, {\$tid}, {\$pid})\" onmouseout=\"javascript:DNTPostRatesRemove(5, {\$tid}, {\$pid})\" onclick=\"location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=5&amp;tid={\$tid}&amp;pid={\$pid}\'\" />
	<span id=\"prt_list5_pid{\$pid}\" class=\"ptr_list\"></span>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_results6',
		'template' => "<img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/angry.png\" alt=\"{\$lang->dnt_prt_angry}\" onmouseover=\"javascript:DNTPostRates(6, {\$tid}, {\$pid})\" onmouseout=\"javascript:DNTPostRatesRemove(6, {\$tid}, {\$pid})\" onclick=\"location.href=\'dnt_post_rate.php?action=get_thread_rates&amp;lid=6&amp;tid={\$tid}&amp;pid={\$pid}\'\" />
	<span id=\"prt_list6_pid{\$pid}\" class=\"ptr_list\"></span>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_likes',
		'template' => "<span class=\"clasify_post_rates_msg_span\">{\$likes}</span>{\$dnt_prt_results1}",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_loves',
		'template' => "<span class=\"clasify_post_rates_msg_span\">{\$loves}</span>{\$dnt_prt_results2}",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	
	
	$templatearray = array(
		'title' => 'dnt_prt_wow',
		'template' => "<span class=\"clasify_post_rates_msg_span\">{\$wow}</span>{\$dnt_prt_results3}",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_smiles',
		'template' => "<span class=\"clasify_post_rates_msg_span\">{\$smiles}</span>{\$dnt_prt_results4}",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_crys',
		'template' => "<span class=\"clasify_post_rates_msg_span\">{\$crys}</span>{\$dnt_prt_results5}",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_angrys',
		'template' => "<span class=\"clasify_post_rates_msg_span\">{\$angrys}</span>{\$dnt_prt_results6}",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	
	
	$templatearray = array(
		'title' => 'dnt_prt_clasify_post_rates_msg',
		'template' => "<div id=\"clasify_post_rates_msgs_list{\$pid}\">
	<div class=\"clasify_post_rates_msg{\$dnt_prt_hl_class}\">
		{\$post[\'dnt_prt_total\']}
		{\$post[\'dnt_prt_view_all\']}<br />
		{\$post[\'clasify_post_rates_msg\']}
	</div>
</div>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);		

	$templatearray = array(
		'title' => 'dnt_prt_clasify_post_rates_msg_memprofile',
		'template' => "<div class=\"clasify_post_rates_mp\">
	{\$memprofile[\'dnt_prt_total\']}
	{\$memprofile[\'dnt_prt_view_all\']}<br />
	{\$memprofile[\'dnt_prt_rates\']}<br />
	{\$memprofile[\'clasify_post_rates_msg\']}
</div>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	
	
	$templatearray = array(
		'title' => 'dnt_prt_clasify_post_no_rates_msg',
		'template' => "<div class=\"clasify_post_norates_msg\">{\$lang->dnt_prt_rates_removed}</div>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);

	$templatearray = array(
		'title' => 'dnt_prt_uname',
		'template' => "<div class=\"dnt_prt_ulist\">
	{\$dnt_prt_name}
	{\$dnt_prt_uname}
	{\$dnt_prt_total}
</div>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_uname_rows',
		'template' => "<span>{\$uname} ({\$dnt_prt_date})</span>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_thread_rates1',
		'template' => "<img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/like.png\" alt=\"{\$lang->dnt_prt_like}\" title=\"{\$lang->dnt_prt_like}\"/>
	<span id=\"prt_list1_pid{\$tid}\" class=\"ptr_list\"></span>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_thread_rates2',
		'template' => "<img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/love.png\" alt=\"{\$lang->dnt_prt_love}\" title=\"{\$lang->dnt_prt_love}\" />
	<span id=\"prt_list2_pid{\$tid}\" class=\"ptr_list\"></span>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_thread_rates3',
		'template' => "<img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/wow.png\" alt=\"{\$lang->dnt_prt_wow}\" title=\"{\$lang->dnt_prt_wow}\" />
	<span id=\"prt_list3_pid{\$tid}\" class=\"ptr_list\"></span>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_thread_rates4',
		'template' => "<img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/smile.png\" alt=\"{\$lang->dnt_prt_smile}\" title=\"{\$lang->dnt_prt_smile}\" />
	<span id=\"prt_list4_pid{\$tid}\" class=\"ptr_list\"></span>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_thread_rates5',
		'template' => "<img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/cry.png\" alt=\"{\$lang->dnt_prt_cry}\" title=\"{\$lang->dnt_prt_cry}\" />
	<span id=\"prt_list5_pid{\$tid}\" class=\"ptr_list\"></span>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_thread_rates6',
		'template' => "<img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/angry.png\" alt=\"{\$lang->dnt_prt_angry}\" title=\"{\$lang->dnt_prt_angry}\" />
	<span id=\"prt_list6_pid{\$tid}\" class=\"ptr_list\"></span>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_memprofile1',
		'template' => "<span class=\"clasify_post_rates_msg_span\">{\$pcl[\'bestid\']}</span>
	<img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/like.png\" alt=\"{\$lang->dnt_prt_like}\" title=\"{\$lang->dnt_prt_like}\" style=\"cursor:context-menu\" />
	<span style=\"color:#1b43b6;\">{\$lang->dnt_prt_like}</span>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_memprofile2',
		'template' => "<span class=\"clasify_post_rates_msg_span\">{\$pcl[\'bestid\']}</span>
	<img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/love.png\" alt=\"{\$lang->dnt_prt_love}\" title=\"{\$lang->dnt_prt_love}\" style=\"cursor:context-menu\" />
	<span style=\"color:#e61b3f;\">{\$lang->dnt_prt_love}</span>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_memprofile3',
		'template' => "<span class=\"clasify_post_rates_msg_span\">{\$pcl[\'bestid\']}</span>
	<img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/wow.png\" alt=\"{\$lang->dnt_prt_wow}\" title=\"{\$lang->dnt_prt_wow}\" style=\"cursor:context-menu\" />
	<span style=\"color:#cfcd35;\">{\$lang->dnt_prt_wow}</span>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_memprofile4',
		'template' => "<span class=\"clasify_post_rates_msg_span\">{\$pcl[\'bestid\']}</span>
	<img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/smile.png\" alt=\"{\$lang->dnt_prt_smile}\" title=\"{\$lang->dnt_prt_smile}\" style=\"cursor:context-menu\" />
	<span style=\"color:#cfcd35;\">{\$lang->dnt_prt_smile}</span>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_memprofile5',
		'template' => "<span class=\"clasify_post_rates_msg_span\">{\$pcl[\'bestid\']}</span>
	<img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/cry.png\" alt=\"{\$lang->dnt_prt_cry}\" title=\"{\$lang->dnt_prt_cry}\" style=\"cursor:context-menu\" />
	<span style=\"color:#1b43b6;\">{\$lang->dnt_prt_cry}</span>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_memprofile6',
		'template' => "<span class=\"clasify_post_rates_msg_span\">{\$pcl[\'bestid\']}</span>
	<img src=\"{\$mybb->settings[\'bburl\']}/images/dnt_rates/angry.png\" alt=\"{\$lang->dnt_prt_angry}\" title=\"{\$lang->dnt_prt_angry}\" style=\"cursor:context-menu\" />
	<span style=\"color:#c22e0f;\">{\$lang->dnt_prt_angry}</span>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);

	$templatearray = array(
		'title' => 'dnt_prt_templatesg',
		'template' => "<div class=\"clasify_post_rates_msg\">{\$lang->dnt_prt_top5_rates_giv}<br />
	{\$dnt_prt_resultsg}
</div>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);
	
	$templatearray = array(
		'title' => 'dnt_prt_templatesr',
		'template' => "<div class=\"clasify_post_rates_msg\">{\$lang->dnt_prt_top5_rates_rec}<br />
	{\$dnt_prt_resultsr}
</div>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);

	$templatearray = array(
		'title' => 'dnt_prt_stats',
		'template' => "<div class=\"clasify_post_rates_msg\">{\$lang->dnt_prt_stats}<br />
	{\$memprofile[\'dnt_prt_rates_given\']}
	{\$memprofile[\'dnt_prt_rates_received\']}
</div>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_rates_given',
		'template' => "<div class=\"dnt_prt_rates_given\">
	<span style=\"display:block\">{\$lang->dnt_prt_rates_given}: <a href=\"{\$url_given}\"><span id=\"dnt_prt_giva{\$post[\'pid\']}\" style=\"font-weight:bold\">{\$post[\'dnt_prt_rates_given\']}</span></a></span>
</div>",
		'sid' => '-2',
		'version' => '1800',
		'dateline' => TIME_NOW
	);	
	$db->insert_query("templates", $templatearray);	

	$templatearray = array(
		'title' => 'dnt_prt_rates_received',
		'template' => "<div class=\"dnt_prt_rates_received\">
	<span style=\"display:block\">{\$lang->dnt_prt_rates_received}: <a href=\"{\$url_received}\"><span id=\"dnt_prt_reca{\$post[\'pid\']}\" style=\"font-weight:bold\">{\$post[\'dnt_prt_rates_received\']}</span></a></span>
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
	
	$db->delete_query("templategroups", "prefix='dnt'");
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
			flash_message($lang->dnt_prt_alerts_integrated_success, 'success');
			admin_redirect('index.php?module=config-plugins');			
			}
			else
			{
				flash_message($lang->dnt_prt_alerts_integrated_wrong, 'error');
				admin_redirect('index.php?module=config-plugins');			
			}
		}
		else
		{
			flash_message($lang->dnt_prt_alerts_integrated_none, 'error');
			admin_redirect('index.php?module=config-plugins');			
		}
	}	
}

function dnt_post_rate_templates()
{
	global $mybb, $templatelist;
	if($mybb->settings['dnt_post_rate_active'] == 0)
	{
		return false;
	}	
	if(THIS_SCRIPT == "dnt_post_rate.php" || THIS_SCRIPT == "showthread.php" || THIS_SCRIPT == "member.php" || THIS_SCRIPT == "forumdisplay.php")
	{
		if(isset($templatelist))
		{
			if(THIS_SCRIPT == "showthread.php")
				$templatelist .= 'dnt_prt_uname,dnt_prt_uname_rows,dnt_prt_results_1,dnt_prt_results_2,dnt_prt_results_3,dnt_prt_results_4,dnt_prt_results_5,dnt_prt_results_6,dnt_prt_post_clasify_post_rates,dnt_prt_results1,dnt_prt_results2,dnt_prt_results3,dnt_prt_results4,dnt_prt_results5,dnt_prt_results6,dnt_prt_likes,dnt_prt_loves,dnt_prt_wow,dnt_prt_smiles,dnt_prt_crys,dnt_prt_angrys,dnt_prt_clasify_post_rates_msg,dnt_prt_clasify_post_no_rates_msg,dnt_prt_rates_given,dnt_prt_rates_received';
			else if(THIS_SCRIPT == "member.php")
				$templatelist .= 'dnt_prt_thread_rates1, dnt_prt_thread_rates2, dnt_prt_thread_rates3, dnt_prt_thread_rates4, dnt_prt_thread_rates5, dnt_prt_thread_rates6, dnt_prt_loves, dnt_prt_wow, dnt_prt_smiles, dnt_prt_crys, dnt_prt_clasify_post_rates_msg_memprofile, dnt_prt_memprofile2, dnt_prt_memprofile1, dnt_prt_memprofile3, dnt_prt_memprofile4, dnt_prt_memprofile5, dnt_prt_memprofile6, dnt_prt_templates';
			else if(THIS_SCRIPT == "forumdisplay.php")
				$templatelist .= 'dnt_prt_thread_rates1, dnt_prt_thread_rates2, dnt_prt_thread_rates3, dnt_prt_thread_rates4, dnt_prt_thread_rates5, dnt_prt_thread_rates6, dnt_prt_loves, dnt_prt_wow, dnt_prt_smiles, dnt_prt_crys, dnt_prt_angrys, dnt_prt_likes';
			else if(THIS_SCRIPT == "dnt_post_rate.php")
				$templatelist .= 'dnt_prt_list, dnt_prt_content, dnt_prt_page,dnt_prt_list_none,dnt_prt_list_rate1,dnt_prt_list_rate2,dnt_prt_list_rate3,dnt_prt_list_rate4,dnt_prt_list_rate5,dnt_prt_list_rate6';
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
		if($mybb->settings['dnt_post_rate_minify'])
			$dnt_prt_min = ".min.js";
		else
			$dnt_prt_min = ".js";
		$dnt_prt_script = "<script type=\"text/javascript\" src=\"{$mybb->asset_url}/jscripts/dnt_prt{$dnt_prt_min}?ver=162\"></script>
<script type=\"text/javascript\">
	var dnt_prt_success = \"{$lang->dnt_prt_rated}\";
	var dnt_prt_remove_question = \"{$lang->dnt_prt_remove_rate_question}\";
	var dnt_prt_remove_success = \"{$lang->dnt_prt_remove_rate}\";
	var dnt_prt_remove_error = \"{$lang->dnt_prt_remove_rate_error}\";		
</script>";
		
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
	if(function_exists('myalerts_is_activated') && myalerts_is_activated() && dnt_post_rate_myalerts_status())
	{
		$tid = (int)$mybb->input['tid'];
		$pid = (int)$mybb->input['pid'];
		$uid = (int)$mybb->user['uid'];
		$db->delete_query("alerts","from_user_id='{$uid}' AND object_id='{$pid}' AND unread=1 LIMIT 1");
	}
}

function dnt_post_rate_post_rates(&$post)
{
	global $db, $mybb, $theme, $templates, $lang, $thread;

	if($mybb->settings['dnt_post_rate_active'] == 0 || THIS_SCRIPT != "showthread.php")
	{
		return false;
	}
	$lang->load('dnt_post_rate',false,true);
	$dnt_prt_firstpost = (int)$mybb->settings['dnt_post_rate_only_firspost'];
	$tid = (int)$post['tid'];
	$fid = (int)$post['fid'];
	$pid = (int)$post['pid'];
	$dnt_prt_sender = (int)$mybb->user['uid'];
	$dnt_prt_senderc = "-1";
	$limit_search = (int)$mybb->settings['dnt_post_rate_limit'];	
	$dnt_prt_date_limit = time() - ($limit_search * 60 * 60 * 24);
	if($limit_search > 0)
		$dnt_prt_date = " AND dnt_prt_date>='{$dnt_prt_date_limit}'";
	else
		$dnt_prt_date = "";
	$dnt_prt_fids = $mybb->settings['dnt_post_rate_forums'];
	if($dnt_prt_fids != "-1" && !empty($dnt_prt_fids))
	{
		$dnt_prt_fids = explode(",",$mybb->settings['dnt_post_rate_forums']);
		if(!in_array($fid, $dnt_prt_fids))
			return false;		
	}
	if(!empty($mybb->settings['dnt_post_rate_groups']) && $mybb->settings['dnt_post_rate_groups'] != "-1")
	{
		$gid = (int)$mybb->user['usergroup'];
		$post['dnt_prt_see_me'] = true;	
		if($mybb->user['additionalgroups'])
			$gids = "{$gid}, {$mybb->user['additionalgroups']}";
		else
			$gids = $gid;
	
		$dnt_prt_gids = explode(",",$mybb->settings['dnt_post_rate_groups']);
		
		if(!empty($gids))
		{
			$gids = explode(",",$gids);
			foreach($gids as $gid)
			{
				if(!in_array($gid, $dnt_prt_gids))
					$post['dnt_prt_see_me'] = false;
			}
		}
		else
		{
			if(!in_array($gid, $dnt_prt_gids))
				$post['dnt_prt_see_me'] = false;	
		}
	}
	else if($mybb->settings['dnt_post_rate_groups'] == "-1")
		$post['dnt_prt_see_me'] = true;	

	if($dnt_prt_firstpost == 0 && $mybb->settings['dnt_post_rate_postbit'])
	{
		$post['dnt_prt_rates_given'] = (int)$post['dnt_prt_rates_given'];
		$post['dnt_prt_rates_received'] = (int)$post['dnt_prt_rates_received'];
		$url_given = $mybb->settings['bburl'].'/dnt_post_rate.php?action=get_given_rates&amp;uid='.(int)$post['uid'];
		$url_received = $mybb->settings['bburl'].'/dnt_post_rate.php?action=get_received_rates&amp;uid='.(int)$post['uid'];	
		eval("\$post['rates_given'] = \"".$templates->get("dnt_prt_rates_given")."\";");
		eval("\$post['rates_received'] = \"".$templates->get("dnt_prt_rates_received")."\";");
	}
	else if($dnt_prt_firstpost == 1 && $mybb->settings['dnt_post_rate_postbit'] == 1)
	{
		if($thread['firstpost'] == $post['pid'])
		{
			$post['dnt_prt_rates_given'] = (int)$post['dnt_prt_rates_given'];
			$post['dnt_prt_rates_received'] = (int)$post['dnt_prt_rates_received'];			
			$url_given = $mybb->settings['bburl'].'/dnt_post_rate.php?action=get_given_rates&amp;uid='.(int)$post['uid'];
			$url_received = $mybb->settings['bburl'].'/dnt_post_rate.php?action=get_received_rates&amp;uid='.(int)$post['uid'];	
			eval("\$post['rates_given'] = \"".$templates->get("dnt_prt_rates_given")."\";");
			eval("\$post['rates_received'] = \"".$templates->get("dnt_prt_rates_received")."\";");
		}
		else
		{
			$post['rates_given'] = '<span id="dnt_prt_giva'.$post['pid'].'" style="display:none"></span>';
			$post['rates_received'] = '<span id="dnt_prt_reca'.$post['pid'].'" style="display:none"></span>';
		}		
	}

	if($dnt_prt_firstpost == 1 && $thread['firstpost'] != $post['pid'])
		return false;	
	
	$dnt_prt_user = (int)$post['uid'];
	$post['dnt_prt_rates_posts'] = unserialize($post['dnt_prt_rates_posts']);
	$likes = (int)$post['dnt_prt_rates_posts']['likes'];
	$loves = (int)$post['dnt_prt_rates_posts']['loves'];
	$wow = (int)$post['dnt_prt_rates_posts']['wow'];
	$smiles = (int)$post['dnt_prt_rates_posts']['smiles'];
	$crys = (int)$post['dnt_prt_rates_posts']['crys'];
	$angrys = (int)$post['dnt_prt_rates_posts']['angrys'];
	$total = (int)$post['dnt_prt_rates_posts']['total'];

	if($post['uid'] == $mybb->user['uid'])
	{
		$post['dnt_prt_see_me'] = false;
	}	
	else
	{
		if($mybb->user['uid'] != $post['uid'])
		{
			$query = $db->simple_select('dnt_post_rate','*',"dnt_prt_sender='{$dnt_prt_sender}' AND dnt_prt_pid='{$pid}'{$dnt_prt_date}", array("limit"=>1));
			if ($db->num_rows($query) > 0)
			{
				$post['dnt_prt_see_me'] = false;
				while($dnt_prt_result = $db->fetch_array($query))
				{
					if($mybb->settings['dnt_post_rate_remove'] == 1)
						$post['dnt_prt_remove'] = "id=\"post_rates_btn_{$pid}\" onclick=\"javascript:DNTRemoveRate({$dnt_prt_result['dnt_prt_type']},{$tid},{$pid})\"";
					else
						$post['dnt_prt_remove'] =  "id=\"post_rates_btn_{$pid}\" onclick=\"javascript:DNTCantRemoveRate({$pid})\"";			
					if($dnt_prt_result['dnt_prt_type'] == 1)
						eval("\$dnt_prt_results = \"".$templates->get("dnt_prt_results_1")."\";");
					if($dnt_prt_result['dnt_prt_type'] == 2)
						eval("\$dnt_prt_results = \"".$templates->get("dnt_prt_results_2")."\";");
					if($dnt_prt_result['dnt_prt_type'] == 3)
						eval("\$dnt_prt_results = \"".$templates->get("dnt_prt_results_3")."\";");
					if($dnt_prt_result['dnt_prt_type'] == 4)
						eval("\$dnt_prt_results = \"".$templates->get("dnt_prt_results_4")."\";");
					if($dnt_prt_result['dnt_prt_type'] == 5)				
						eval("\$dnt_prt_results = \"".$templates->get("dnt_prt_results_5")."\";");
					if($dnt_prt_result['dnt_prt_type'] == 6)
						eval("\$dnt_prt_results = \"".$templates->get("dnt_prt_results_6")."\";");
					$post['clasify_post_rates']	= $dnt_prt_results;
				}				
			}	
		}
	}	
	
	if($post['dnt_prt_see_me'] === true)
	{
		eval("\$post['clasify_post_rates'] = \"".$templates->get("dnt_prt_post_clasify_post_rates")."\";");
	}

	if($total > 0)
	{		
		eval("\$dnt_prt_results1 .= \"".$templates->get("dnt_prt_results1")."\";");
		eval("\$dnt_prt_results2 .= \"".$templates->get("dnt_prt_results2")."\";");
		eval("\$dnt_prt_results3 .= \"".$templates->get("dnt_prt_results3")."\";");
		eval("\$dnt_prt_results4 .= \"".$templates->get("dnt_prt_results4")."\";");
		eval("\$dnt_prt_results5 .= \"".$templates->get("dnt_prt_results5")."\";");
		eval("\$dnt_prt_results6 .= \"".$templates->get("dnt_prt_results6")."\";");
		
		if($likes > 0)
			eval("\$post['dnt_likes'] = \"".$templates->get("dnt_prt_likes")."\";");
		if($loves > 0)
			eval("\$post['dnt_loves'] = \"".$templates->get("dnt_prt_loves")."\";");
		if($wow > 0)
			eval("\$post['dnt_wow'] = \"".$templates->get("dnt_prt_wow")."\";");
		if($smiles > 0)
			eval("\$post['dnt_smiles'] = \"".$templates->get("dnt_prt_smiles")."\";");
		if($crys > 0)
			eval("\$post['dnt_crys'] = \"".$templates->get("dnt_prt_crys")."\";");
		if($angrys > 0)
			eval("\$post['dnt_angrys'] = \"".$templates->get("dnt_prt_angrys")."\";");
		
		if($mybb->settings['dnt_post_rate_highlight'] > 0)
		{
			$dnt_to_highlight = (int)$total;
			$dnt_to_compare = (int)$mybb->settings['dnt_post_rate_highlight'];			
			if($dnt_to_highlight >= $dnt_to_compare)
			{
				$dnt_prt_hl_class = " dnt_post_hl";
				$post['dnt_prt_hl_post'] = " dnt_popular_post";		
			}
		}
		$post['dnt_prt_url'] = $mybb->settings['bburl']."/dnt_post_rate.php?action=get_thread_rates&lid=all&amp;tid={$post['tid']}&amp;pid={$post['pid']}";
		$post['dnt_prt_view_all'] = $lang->sprintf($lang->dnt_prt_view_all, $post['dnt_prt_url']);
		$post['clasify_post_rates_msg'] = $post['dnt_likes'].$post['dnt_loves'].$post['dnt_wow'].$post['dnt_smiles'].$post['dnt_crys'].$post['dnt_angrys'];
		$post['dnt_prt_total'] = $lang->sprintf($lang->dnt_prt_total, $total);
		eval("\$post['clasify_post_rates_msg'] = \"".$templates->get("dnt_prt_clasify_post_rates_msg")."\";");
		$post['message'] .= '<div id="clasify_post_rates_msgs_list'.$pid.'">'.$post['clasify_post_rates_msg']."</div>";
	}
	else
	{
		$post['message'] .= '<div id="clasify_post_rates_msgs_list'.$pid.'">'.$post['clasify_post_rates_msg'].'</div>';		
	}	
}

function dnt_post_rate_xmlhttp()
{
	global $db, $lang, $theme, $templates, $thread, $mybb, $charset;

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
		$limit_search = (int)$mybb->settings['dnt_post_rate_limit'];	
		$dnt_prt_date_limit = time() - ($limit_search * 60 * 60 * 24);
		if($limit_search > 0)
			$dnt_prt_date = " AND dnt_prt_date>='{$dnt_prt_date_limit}'";
		else
			$dnt_prt_date = "";
		$template = "";
		if($limit_users > 0)
		{
			if($mybb->settings['dnt_post_rate_showthread_all'] == 1)
				$sta = "dnt_prt_tid='{$tid}'";
			else
				$sta = "dnt_prt_tid='{$tid}' AND dnt_prt_pid={$pid}";			
			$numtot_query = $db->query("SELECT COUNT(*) as totitems FROM ".TABLE_PREFIX."dnt_post_rate
			WHERE {$sta} AND dnt_prt_type='{$lid}'{$dnt_prt_date}
			ORDER BY dnt_prt_date DESC LIMIT {$limit_users}");
			$dnt_prt_total = $db->fetch_field($numtot_query, 'totitems');			
			$dnt_prt_query = $db->query("SELECT dp.*, u.username FROM ".TABLE_PREFIX."dnt_post_rate dp
			LEFT JOIN ".TABLE_PREFIX."users u
			ON (dp.dnt_prt_sender=u.uid)
			WHERE dnt_prt_tid='{$tid}' AND dnt_prt_pid='{$pid}' AND dnt_prt_type='{$lid}'{$dnt_prt_date}
			ORDER BY dnt_prt_date DESC LIMIT {$limit_users}");
			if($lid == 1)
				$dnt_prt_name = "<b>{$lang->dnt_prt_like}</b><br />";
			if($lid == 2)
				$dnt_prt_name = "<b>{$lang->dnt_prt_love}</b><br />";
			if($lid == 3)
				$dnt_prt_name = "<b>{$lang->dnt_prt_wow}</b><br />";
			if($lid == 4)
				$dnt_prt_name = "<b>{$lang->dnt_prt_smile}</b><br />";
			if($lid == 5)
				$dnt_prt_name = "<b>{$lang->dnt_prt_cry}</b><br />";
			if($lid == 6)
				$dnt_prt_name = "<b>{$lang->dnt_prt_angry}</b><br />";
			while($dnt_prt_rows = $db->fetch_array($dnt_prt_query))
			{
				$dnt_prt_date = my_date($mybb->settings['dateformat'], $dnt_prt_rows['dnt_prt_date']);
				$uname = htmlspecialchars_uni($dnt_prt_rows['username']);
				if(empty($uname))
					$uname = $lang->guest;
				eval("\$dnt_prt_uname .= \"".$templates->get("dnt_prt_uname_rows")."\";"); 		
			}
			if($dnt_prt_total > $limit_users)
			{
				$dnt_prt_total = $dnt_prt_total - $limit_users;
				$dnt_prt_total = $lang->sprintf($lang->dnt_prt_total_items, $dnt_prt_total);
			}
			else			
				$dnt_prt_total = "";
			eval("\$template = \"".$templates->get("dnt_prt_uname")."\";");		
			echo json_encode($template);
			exit;		
		}	
		else
		{
			$dnt_prt_name = "";
			$dnt_prt_total = "";
			if($lid == 1)
				$dnt_prt_uname = "<b>{$lang->dnt_prt_like}</b><br />";
			if($lid == 2)
				$dnt_prt_uname = "<b>{$lang->dnt_prt_love}</b><br />";
			if($lid == 3)
				$dnt_prt_uname = "<b>{$lang->dnt_prt_wow}</b><br />";
			if($lid == 4)
				$dnt_prt_uname = "<b>{$lang->dnt_prt_smile}</b><br />";
			if($lid == 5)
				$dnt_prt_uname = "<b>{$lang->dnt_prt_cry}</b><br />";
			if($lid == 6)
				$dnt_prt_uname = "<b>{$lang->dnt_prt_angry}</b><br />";
			eval("\$template = \"".$templates->get("dnt_prt_uname")."\";");
			echo json_encode($template);
			exit;			
		}		
	}
	else if($mybb->get_input('action') == "get_post_rates_member")
	{
		header("Content-type: application/json; charset={$charset}");     
		$lang->load('dnt_post_rate',false,true);		
		$lid = (int)$mybb->input['lid'];
		$tid = (int)$mybb->input['tid'];
		$thread = get_thread($tid);
		$pid = (int)$thread['firstpost'];
		$limit_users = (int)$mybb->settings['dnt_post_rate_limit_users'];
		$limit_search = (int)$mybb->settings['dnt_post_rate_limit'];	
		$dnt_prt_date_limit = time() - ($limit_search * 60 * 60 * 24);
		if($limit_search > 0)
			$dnt_prt_date = " AND dnt_prt_date>='{$dnt_prt_date_limit}'";
		else
			$dnt_prt_date = "";
		$template = "";
		if($limit_users > 0)
		{
			if($mybb->settings['dnt_post_rate_showthread_all'] == 1)
				$sta = "dnt_prt_tid='{$tid}'";
			else
				$sta = "dnt_prt_tid='{$tid}' AND dnt_prt_pid={$pid}";
			$numtot_query = $db->query("SELECT COUNT(*) as totitems FROM ".TABLE_PREFIX."dnt_post_rate
			WHERE {$sta} AND dnt_prt_type='{$lid}'{$dnt_prt_date}
			ORDER BY dnt_prt_date DESC LIMIT {$limit_users}");
			$dnt_prt_total = $db->fetch_field($numtot_query, 'totitems');				
			$dnt_prt_query = $db->query("SELECT dp.*, u.username FROM ".TABLE_PREFIX."dnt_post_rate dp
			LEFT JOIN ".TABLE_PREFIX."users u
			ON (dp.dnt_prt_sender=u.uid)
			WHERE {$sta} AND dnt_prt_type='{$lid}'{$dnt_prt_date}
			ORDER BY dnt_prt_date DESC LIMIT {$limit_users}");
			if($lid == 1)
				$dnt_prt_name = "<b>{$lang->dnt_prt_like}</b><br />";
			if($lid == 2)
				$dnt_prt_name = "<b>{$lang->dnt_prt_love}</b><br />";
			if($lid == 3)
				$dnt_prt_name = "<b>{$lang->dnt_prt_wow}</b><br />";
			if($lid == 4)
				$dnt_prt_name = "<b>{$lang->dnt_prt_smile}</b><br />";
			if($lid == 5)
				$dnt_prt_name = "<b>{$lang->dnt_prt_cry}</b><br />";
			if($lid == 6)
				$dnt_prt_name = "<b>{$lang->dnt_prt_angry}</b><br />";
			while($dnt_prt_rows = $db->fetch_array($dnt_prt_query))
			{			
				$dnt_prt_date = my_date($mybb->settings['dateformat'], $dnt_prt_rows['dnt_prt_date']);
				$uname = htmlspecialchars_uni($dnt_prt_rows['username']);
				if(empty($uname))
					$uname = $lang->guest;
				eval("\$dnt_prt_uname .= \"".$templates->get("dnt_prt_uname_rows")."\";"); 		
			}
			if($dnt_prt_total > $limit_users)
			{
				$dnt_prt_total = $dnt_prt_total - $limit_users;
				$dnt_prt_total = $lang->sprintf($lang->dnt_prt_total_items, $dnt_prt_total);
			}
			else			
				$dnt_prt_total = "";
			eval("\$template = \"".$templates->get("dnt_prt_uname")."\";");		
			echo json_encode($template);
			exit;
		}
		else
		{
			$dnt_prt_name = "";
			$dnt_prt_total = "";			
			if($lid == 1)
				$dnt_prt_uname = "<b>{$lang->dnt_prt_like}</b><br />";
			if($lid == 2)
				$dnt_prt_uname = "<b>{$lang->dnt_prt_love}</b><br />";
			if($lid == 3)
				$dnt_prt_uname = "<b>{$lang->dnt_prt_wow}</b><br />";
			if($lid == 4)
				$dnt_prt_uname = "<b>{$lang->dnt_prt_smile}</b><br />";
			if($lid == 5)
				$dnt_prt_uname = "<b>{$lang->dnt_prt_cry}</b><br />";
			if($lid == 6)
				$dnt_prt_uname = "<b>{$lang->dnt_prt_angry}</b><br />";
			eval("\$template = \"".$templates->get("dnt_prt_uname")."\";");
			echo json_encode($template);
			exit;			
		}
	} 
	if(!empty($mybb->settings['dnt_post_rate_groups']) && $mybb->settings['dnt_post_rate_groups'] != "-1")
	{
		$gid = (int)$mybb->user['usergroup'];
		if($mybb->user['additionalgroups'])
			$gids = "{$gid}, {$mybb->user['additionalgroups']}";
		else
			$gids = $gid;
	
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
	if($mybb->get_input('action') == "clasify_post_rate")
	{
		header("Content-type: application/json; charset={$charset}");     
		$lang->load('dnt_post_rate',false,true);		
		$lid = (int)$mybb->input['lid'];
		$tid = (int)$mybb->input['tid'];
		$thread = get_thread($tid);
		$pid = (int)$mybb->input['pid'];
		$post = get_post($pid);
		$template = "";
		$button = "";
		$touid = (int)$post['uid'];
		$uid = (int)$mybb->user['uid'];
		$post['uid'] = (int)$post['uid'];
		$dnt_prt_total = (int)$thread['dnt_prt_total']+1;
		$limit_search = (int)$mybb->settings['dnt_post_rate_limit'];	
		$dnt_prt_date_limit = time() - ($limit_search * 60 * 60 * 24);
		if($limit_search > 0)
			$dnt_prt_date = " AND dnt_prt_date>='{$dnt_prt_date_limit}'";
		else
			$dnt_prt_date = "";
		$likes = $loves = $wow = $smiles = $crys = $angrys = 0;	
		if($touid == $uid)
		{
			xmlhttp_error($lang->dnt_prt_cant_rate);
			return false;
			exit;
		}		
		$dnt_prt_query = $db->simple_select('dnt_post_rate','*',"dnt_prt_sender={$uid} AND dnt_prt_tid='{$tid}' AND dnt_prt_pid='{$pid}'{$dnt_prt_date}", array("limit"=>1));
		if($db->num_rows($dnt_prt_query) > 0)
		{
			xmlhttp_error($lang->dnt_prt_rate_rated);
			return false;
			exit;
		}
		else
		{
			if($mybb->settings['dnt_post_rate_antiflood'] == 1)
			{
				$query = $db->simple_select('dnt_post_rate','*',"dnt_prt_sender={$uid}{$dnt_prt_date} ORDER BY dnt_prt_date DESC", array("limit"=>1));			
				$datar = $db->fetch_array($query);			
				$datac = $datar['dnt_prt_date'] + 30;
				$datan = time();
				$timer_txt = $datac - $datan;
				$lang->dnt_prt_antiflood = $lang->sprintf($lang->dnt_prt_antiflood,$timer_txt);
				if($datan < $datac)
				{
					xmlhttp_error($lang->dnt_prt_antiflood);
					return false;
					exit;				
				}				
			}
			$dnt_prt_dataiu = "insert";
			$dnt_prt_count = 1;
		}

		$insert_data = array(
			'dnt_prt_type' => $db->escape_string($lid),
			'dnt_prt_user' => $db->escape_string($touid),
			'dnt_prt_sender' => $db->escape_string($uid),
			'dnt_prt_count' => $db->escape_string($dnt_prt_count),			
			'dnt_prt_tid' => $db->escape_string($tid),
			'dnt_prt_pid' => $db->escape_string($pid),			
			'dnt_prt_date' => time()
		);
		
		if($dnt_prt_dataiu == "insert")
			$db->insert_query("dnt_post_rate",$insert_data);

		$thread['dnt_prt_rates_threads'] = unserialize($thread['dnt_prt_rates_threads']);
		
		$likest = (int)$thread['dnt_prt_rates_threads']['likes'];
		$lovest = (int)$thread['dnt_prt_rates_threads']['loves'];
		$wowt = (int)$thread['dnt_prt_rates_threads']['wow'];
		$smilest = (int)$thread['dnt_prt_rates_threads']['smiles'];
		$cryst = (int)$thread['dnt_prt_rates_threads']['crys'];
		$angryst = (int)$thread['dnt_prt_rates_threads']['angrys'];

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
	
		$thread_rates = array(
			'likes' => (int)$likest,
			'loves' => (int)$lovest,
			'wow' => (int)$wowt,
			'smiles' => (int)$smilest,
			'crys' => (int)$cryst,
			'angrys' => (int)$angryst,
			'total' => (int)$dnt_prt_total
		);

		$thread_rates = serialize($thread_rates);

		$update_data_thread = array(
			"dnt_prt_total" => $db->escape_string($dnt_prt_total),
			"dnt_prt_rates_threads" => $db->escape_string($thread_rates)
		);
		if(isset($update_data_thread))
			$db->update_query("threads",$update_data_thread,"tid='{$tid}'");

		$post['dnt_prt_rates_posts'] = unserialize($post['dnt_prt_rates_posts']);

		if($uid != $post['uid'])
		{
			$db->query("UPDATE ".TABLE_PREFIX."users SET dnt_prt_rates_given=dnt_prt_rates_given+1 WHERE uid='{$uid}' LIMIT 1");
			$db->query("UPDATE ".TABLE_PREFIX."users SET dnt_prt_rates_received=dnt_prt_rates_received+1 WHERE uid='{$post['uid']}' LIMIT 1");			
		}
		
		$likes = (int)$post['dnt_prt_rates_posts']['likes'];
		$loves = (int)$post['dnt_prt_rates_posts']['loves'];
		$wow = (int)$post['dnt_prt_rates_posts']['wow'];
		$smiles = (int)$post['dnt_prt_rates_posts']['smiles'];
		$crys = (int)$post['dnt_prt_rates_posts']['crys'];
		$angrys = (int)$post['dnt_prt_rates_posts']['angrys'];

		if($likes < 1)
			$likes = 0;
		if($loves < 1)
			$loves = 0;
		if($wow < 1)
			$wow = 0;
		if($smiles < 1)
			$smiles = 0;
		if($crys < 1)
			$crys = 0;
		if($angrys < 1)
			$angrys = 0;
		
		if($lid == 1)
			$likes++;
		if($lid == 2)
			$loves++;
		if($lid == 3)
			$wow++;
		if($lid == 4)
			$smiles++;
		if($lid == 5)
			$crys++;
		if($lid == 6)
			$angrys++;
		
		$clasify_post_rates_total = (int)$likes + (int)$loves + (int)$wow + (int)$smiles + (int)$crys + (int)$angrys;
	
		$post_rates = array(
			'likes' => (int)$likes,
			'loves' => (int)$loves,
			'wow' => (int)$wow,
			'smiles' => (int)$smiles,
			'crys' => (int)$crys,
			'angrys' => (int)$angrys,
			'total' => (int)$clasify_post_rates_total
		);

		$post_rates = serialize($post_rates);
	
		$update_data_post = array(
			"dnt_prt_rates_posts" => $db->escape_string($post_rates)
		);

		$update_data_thread = array(
			"dnt_prt_rates_threads_post" => $db->escape_string($post_rates)
		);
			
		if(isset($update_data_post))
		{
			$db->update_query("posts", $update_data_post, "tid='{$tid}' AND pid='{$pid}'");
			if($thread['firstpost'] == $pid)
			$db->update_query("threads", $update_data_thread, "tid='{$tid}'");			
		}
	
		recordAlertRpt($tid, $pid);	

		eval("\$dnt_prt_results1 .= \"".$templates->get("dnt_prt_results1")."\";");
		eval("\$dnt_prt_results2 .= \"".$templates->get("dnt_prt_results2")."\";");
		eval("\$dnt_prt_results3 .= \"".$templates->get("dnt_prt_results3")."\";");
		eval("\$dnt_prt_results4 .= \"".$templates->get("dnt_prt_results4")."\";");
		eval("\$dnt_prt_results5 .= \"".$templates->get("dnt_prt_results5")."\";");
		eval("\$dnt_prt_results6 .= \"".$templates->get("dnt_prt_results6")."\";");
		
		if($likes > 0)
			eval("\$post['dnt_likes'] = \"".$templates->get("dnt_prt_likes")."\";");
		if($loves > 0)
			eval("\$post['dnt_loves'] = \"".$templates->get("dnt_prt_loves")."\";");
		if($wow > 0)
			eval("\$post['dnt_wow'] = \"".$templates->get("dnt_prt_wow")."\";");
		if($smiles > 0)
			eval("\$post['dnt_smiles'] = \"".$templates->get("dnt_prt_smiles")."\";");
		if($crys > 0)
			eval("\$post['dnt_crys'] = \"".$templates->get("dnt_prt_crys")."\";");
		if($angrys > 0)
			eval("\$post['dnt_angrys'] = \"".$templates->get("dnt_prt_angrys")."\";");

		$is_popular = "none";
		if($mybb->settings['dnt_post_rate_highlight'] > 0)
		{
			$dnt_to_highlight = (int)$total;
			$dnt_to_compare = (int)$mybb->settings['dnt_post_rate_highlight'];			
			if($dnt_to_highlight >= $dnt_to_compare)
			{
				$dnt_prt_hl_class = " dnt_post_hl";
				$dnt_prt_hl_post = " dnt_popular_post";		
			}
			if($dnt_to_compare == $dnt_to_highlight)
				$is_popular = 1;
			else if ($dnt_to_compare == $dnt_to_highlight-1)
				$is_popular = 0;			
		}
		$dnt_prt_url = $mybb->settings['bburl']."/dnt_post_rate.php?action=get_thread_rates&lid=all&amp;tid={$tid}&amp;pid={$pid}";
		$post['dnt_prt_view_all'] = $lang->sprintf($lang->dnt_prt_view_all, $dnt_prt_url);
		$post['clasify_post_rates_msg'] = $post['dnt_likes'].$post['dnt_loves'].$post['dnt_wow'].$post['dnt_smiles'].$post['dnt_crys'].$post['dnt_angrys'];	
		$post['dnt_prt_total'] = $lang->sprintf($lang->dnt_prt_total, $clasify_post_rates_total);		
		eval("\$template = \"".$templates->get("dnt_prt_clasify_post_rates_msg")."\";");

		if($mybb->settings['dnt_post_rate_remove'] == 1)
			$post['dnt_prt_remove'] = "onclick=\"javascript:DNTRemoveRate({$lid},{$tid},{$pid})\"";
			else
		$post['dnt_prt_remove'] =  "";			
		if($lid == 1)
			eval("\$dnt_prt_results .= \"".$templates->get("dnt_prt_results_1")."\";");
		if($lid == 2)
			eval("\$dnt_prt_results .= \"".$templates->get("dnt_prt_results_2")."\";");
		if($lid == 3)
			eval("\$dnt_prt_results .= \"".$templates->get("dnt_prt_results_3")."\";");
		if($lid == 4)
			eval("\$dnt_prt_results .= \"".$templates->get("dnt_prt_results_4")."\";");
		if($lid == 5)				
			eval("\$dnt_prt_results .= \"".$templates->get("dnt_prt_results_5")."\";");
		if($lid == 6)
			eval("\$dnt_prt_results .= \"".$templates->get("dnt_prt_results_6")."\";");		
	
		$rate = $dnt_prt_results;
		$dnt_prt_data = array(
			'receive' => $dnt_prt_dataiu,
			'post_rate_id' => (int)$lid,
			'post_rate_tid' => (int)$tid,
			'dnt_prt_user' => (int)$touid,
			'dnt_prt_sender' => (int)$uid,			
			'dnt_prt_total' => (int)$dnt_prt_total+1,
			'templates' => $template,
			'rate' => $rate,
			'is_popular' => (int)$is_popular
		);
		
		echo json_encode($dnt_prt_data);
		exit;
	}
	else if($mybb->get_input('action') == "remove_post_rates")
	{
		header("Content-type: application/json; charset={$charset}");     
		$lang->load('dnt_post_rate',false,true);		
		$lid = (int)$mybb->input['lid'];
		$tid = (int)$mybb->input['tid'];
		$pid = (int)$mybb->input['pid'];
		$post = get_post($pid);		
		$uid = (int)$mybb->user['uid'];
		$post['uid'] = (int)$post['uid'];		
		$limit_search = (int)$mybb->settings['dnt_post_rate_limit'];	
		$dnt_prt_date_limit = time() - ($limit_search * 60 * 60 * 24);		
		if($limit_search > 0)
			$dnt_prt_date = " AND dnt_prt_date>='{$dnt_prt_date_limit}'";
		else
			$dnt_prt_date = "";
		if($mybb->settings['dnt_post_rate_remove'] == 0)
		{
			xmlhttp_error($lang->dnt_prt_cant_unrate);
			return false;
			exit;
		}		
		$query = $db->simple_select("dnt_post_rate","*","dnt_prt_tid='{$tid}' AND dnt_prt_pid='{$pid}' AND dnt_prt_sender='{$uid}'{$dnt_prt_date}", array("limit"=>1));
		if($db->num_rows($query) == 0)
		{
			xmlhttp_error($lang->dnt_prt_rate_dont_rated);
			return false;
			exit;
		}
		$datar = $db->fetch_array($query);
		$resultid = $datar['dnt_prt_user'];
		if($resultid == $uid)
		{
			xmlhttp_error($lang->dnt_prt_cant_unrate);
			return false;
			exit;
		}		
		if($mybb->settings['dnt_post_rate_antiflood'] == 1)
		{
			$datac = $datar['dnt_prt_date'] + 30;
			$datan = time();
			$timer_txt = $datac - $datan;
			$lang->dnt_prt_antiflood = $lang->sprintf($lang->dnt_prt_antiflood,$timer_txt);
			if($datan < $datac)
			{
				xmlhttp_error($lang->dnt_prt_antiflood);
				return false;
				exit;				
			}				
		}		
		$db->delete_query("dnt_post_rate","dnt_prt_tid='{$tid}' AND dnt_prt_pid='{$pid}' AND dnt_prt_sender='{$uid}'{$dnt_prt_date}");
		$thread = get_thread($tid);
		if($thread['dnt_prt_total'] > 0)
			$dnt_prt_total = (int)$thread['dnt_prt_total']-1;
		else
			$dnt_prt_total = 0;
		
		$thread['dnt_prt_rates_threads'] = unserialize($thread['dnt_prt_rates_threads']);
		
		$likest = (int)$thread['dnt_prt_rates_threads']['likes'];
		$lovest = (int)$thread['dnt_prt_rates_threads']['loves'];
		$wowt = (int)$thread['dnt_prt_rates_threads']['wow'];
		$smilest = (int)$thread['dnt_prt_rates_threads']['smiles'];
		$cryst = (int)$thread['dnt_prt_rates_threads']['crys'];
		$angryst = (int)$thread['dnt_prt_rates_threads']['angrys'];
		
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
		
		$thread_rates = array(
			'likes' => (int)$likest,
			'loves' => (int)$lovest,
			'wow' => (int)$wowt,
			'smiles' => (int)$smilest,
			'crys' => (int)$cryst,
			'angrys' => (int)$angryst,
			'total' => (int)$dnt_prt_total
		);

		$thread_rates = serialize($thread_rates);

		$update_data_thread = array(
			"dnt_prt_total" => $db->escape_string($dnt_prt_total),
			"dnt_prt_rates_threads" => $db->escape_string($thread_rates)
		);
		if(isset($update_data_thread))
			$db->update_query("threads",$update_data_thread,"tid='{$tid}'");

		$post['dnt_prt_rates_posts'] = unserialize($post['dnt_prt_rates_posts']);
		
		if($uid != $post['uid'])
		{
			$db->query("UPDATE ".TABLE_PREFIX."users SET dnt_prt_rates_given=dnt_prt_rates_given-1 WHERE uid='{$uid}' LIMIT 1");
			$db->query("UPDATE ".TABLE_PREFIX."users SET dnt_prt_rates_received=dnt_prt_rates_received-1 WHERE uid='{$post['uid']}' LIMIT 1");			
		}
		
		$likes = (int)$post['dnt_prt_rates_posts']['likes'];
		$loves = (int)$post['dnt_prt_rates_posts']['loves'];
		$wow = (int)$post['dnt_prt_rates_posts']['wow'];
		$smiles = (int)$post['dnt_prt_rates_posts']['smiles'];
		$crys = (int)$post['dnt_prt_rates_posts']['crys'];
		$angrys = (int)$post['dnt_prt_rates_posts']['angrys'];
		
		if($lid == 1)
			$likes--;
		if($lid == 2)
			$loves--;
		if($lid == 3)
			$wow--;
		if($lid == 4)
			$smiles--;
		if($lid == 5)
			$crys--;
		if($lid == 6)
			$angrys--;
		
		if($likes < 1)
			$likes = 0;
		if($loves < 1)
			$loves = 0;
		if($wow < 1)
			$wow = 0;
		if($smilesp < 1)
			$smiles = 0;
		if($crysp < 1)
			$crys = 0;
		if($angrys < 1)
			$angrys = 0;
		
		$clasify_post_rates_total = (int)$likes + (int)$loves + (int)$wow + (int)$smiles + (int)$crys + (int)$angrys;
	
		$post_rates = array(
			'likes' => (int)$likes,
			'loves' => (int)$loves,
			'wow' => (int)$wow,
			'smiles' => (int)$smiles,
			'crys' => (int)$crys,
			'angrys' => (int)$angrys,
			'total' => (int)$clasify_post_rates_total
		);

		$post_rates = serialize($post_rates);
	
		$update_data_post = array(
			"dnt_prt_rates_posts" => $db->escape_string($post_rates)
		);		
		$update_data_thread = array(
			"dnt_prt_rates_threads_post" => $db->escape_string($post_rates)
		);		
		if(isset($update_data_post))
		{
			$db->update_query("posts", $update_data_post, "tid='{$tid}' AND pid='{$pid}'");
			if($thread['firstpost'] == $pid)			
			$db->update_query("threads", $update_data_thread, "tid='{$tid}'");
			
		}
		removeAlertRpt($tid, $pid);	

		eval("\$dnt_prt_results1 = \"".$templates->get("dnt_prt_results1")."\";");
		eval("\$dnt_prt_results2 = \"".$templates->get("dnt_prt_results2")."\";");
		eval("\$dnt_prt_results3 = \"".$templates->get("dnt_prt_results3")."\";");
		eval("\$dnt_prt_results4 = \"".$templates->get("dnt_prt_results4")."\";");
		eval("\$dnt_prt_results5 = \"".$templates->get("dnt_prt_results5")."\";");
		eval("\$dnt_prt_results6 = \"".$templates->get("dnt_prt_results6")."\";");
		
		if($likes > 0)
			eval("\$post['dnt_likes'] = \"".$templates->get("dnt_prt_likes")."\";");
		if($loves > 0)
			eval("\$post['dnt_loves'] = \"".$templates->get("dnt_prt_loves")."\";");
		if($wow > 0)
			eval("\$post['dnt_wow'] = \"".$templates->get("dnt_prt_wow")."\";");
		if($smiles > 0)
			eval("\$post['dnt_smiles'] = \"".$templates->get("dnt_prt_smiles")."\";");
		if($crys > 0)
			 eval("\$post['dnt_crys'] = \"".$templates->get("dnt_prt_crys")."\";");
		if($angrys > 0)
			eval("\$post['dnt_angrys'] = \"".$templates->get("dnt_prt_angrys")."\";");

		$is_popular = "none";		
		if($mybb->settings['dnt_post_rate_highlight'] > 0)
		{
			$dnt_to_highlight = (int)$total;
			$dnt_to_compare = (int)$mybb->settings['dnt_post_rate_highlight'];			
			if($dnt_to_highlight >= $dnt_to_compare)
			{
				$dnt_prt_hl_class = " dnt_post_hl";
				$dnt_prt_hl_post = " dnt_popular_post";		
			}
			if($dnt_to_compare == $dnt_to_highlight)
				$is_popular = 1;
			else if ($dnt_to_compare == $dnt_to_highlight-1)
				$is_popular = 0;			
		}
		$dnt_prt_url = $mybb->settings['bburl']."/dnt_post_rate.php?action=get_thread_rates&lid=all&amp;tid={$post['tid']}&amp;pid={$post['pid']}";
		$post['dnt_prt_view_all'] = $lang->sprintf($lang->dnt_prt_view_all, $dnt_prt_url);		
		$post['clasify_post_rates_msg'] = $post['dnt_likes'].$post['dnt_loves'].$post['dnt_wow'].$post['dnt_smiles'].$post['dnt_crys'].$post['dnt_angrys'];	
		$post['dnt_prt_total'] = $lang->sprintf($lang->dnt_prt_total, $clasify_post_rates_total);		
		if($clasify_post_rates_total > 0)		
			eval("\$template = \"".$templates->get("dnt_prt_clasify_post_rates_msg")."\";");
		else
			eval("\$template = \"".$templates->get("dnt_prt_clasify_post_no_rates_msg")."\";");
	
		if($mybb->settings['dnt_post_rate_remove'] == 1)
			$post['dnt_prt_remove'] = "onclick=\"javascript:DNTRemoveRate({$lid},{$tid},{$pid})\"";
			else
		$post['dnt_prt_remove'] =  "";	
		if($lid == 1)
			eval("\$dnt_prt_results .= \"".$templates->get("dnt_prt_results_1")."\";");
		if($lid == 2)
			eval("\$dnt_prt_results .= \"".$templates->get("dnt_prt_results_2")."\";");
		if($lid == 3)
			eval("\$dnt_prt_results .= \"".$templates->get("dnt_prt_results_3")."\";");
		if($lid == 4)
			eval("\$dnt_prt_results .= \"".$templates->get("dnt_prt_results_4")."\";");
		if($lid == 5)				
			eval("\$dnt_prt_results .= \"".$templates->get("dnt_prt_results_5")."\";");
		if($lid == 6)
			eval("\$dnt_prt_results .= \"".$templates->get("dnt_prt_results_6")."\";");		
		
		eval("\$button = \"".$templates->get("dnt_prt_post_clasify_post_rates")."\";");
	
		$dnt_prt_data = array(
			'post_rate_tid' => (int)$tid,
			'dnt_prt_user' => (int)$uid,
			'templates' => $template,
			'button' => $button,
			'is_popular' => (int)$is_popular
		);
		
		echo json_encode($dnt_prt_data);
		exit;
	}
}

function dnt_post_rate_member()
{
	global $db, $theme, $templates, $lang, $mybb, $memprofile;
	$lang->load('dnt_post_rate',false,true);		
	$dnt_prt_stats = "";	
	$dnt_prt_templates = "";
	$dnt_prt_templatesg = "";
	$dnt_prt_templatesr = "";
	$memprofile['uid'] = (int)$memprofile['uid'];
	if ($mybb->settings['dnt_post_rate_memprofile'] == 1)
	{
		$url_given = $mybb->settings['bburl'].'/dnt_post_rate.php?action=get_given_rates&amp;uid='.(int)$memprofile['uid'];
		$url_received = $mybb->settings['bburl'].'/dnt_post_rate.php?action=get_received_rates&amp;uid='.(int)$memprofile['uid'];	
		$memprofile['dnt_prt_rates_given'] = $lang->sprintf($lang->dnt_prt_rates_given_mp,(int)$memprofile['dnt_prt_rates_given'], $url_given);
		$memprofile['dnt_prt_rates_received'] = $lang->sprintf($lang->dnt_prt_rates_received_mp,(int)$memprofile['dnt_prt_rates_received'], $url_received);
		if($mybb->settings['dnt_post_rate_showthread_all'] == 1)
		{
			$dnt_query = $db->simple_select('dnt_post_rate','COUNT(*) AS bestid, dnt_prt_tid',"dnt_prt_user='{$memprofile['uid']}' GROUP BY dnt_prt_tid HAVING bestid > 0 ORDER BY bestid DESC LIMIT 1");
			$tid = $db->fetch_field($dnt_query,'dnt_prt_tid');
			$tid = (int)$tid;
			if($tid > 0)
			{
				$dnt_prt_query = $db->simple_select('threads','*',"tid={$tid}");			
				while($thread = $db->fetch_array($dnt_prt_query))
				{
					$tid = (int)$thread['tid'];
					$pid = (int)$thread['firstpost'];
					$subject = htmlspecialchars_uni($thread['subject']);
					$subject_link = get_thread_link($tid);
					$subject = "<a href=\"{$subject_link}\">{$subject}</a>";
					$total = (int)$thread['dnt_prt_total'];
					$memprofile['dnt_prt_rates'] = unserialize($thread['dnt_prt_rates_threads']);
					$total = (int)$memprofile['dnt_prt_rates']['total'];		
				}
			}
		}
		else
		{
			$dnt_query = $db->simple_select('dnt_post_rate','COUNT(*) AS bestid, dnt_prt_pid',"dnt_prt_user='{$memprofile['uid']}' GROUP BY dnt_prt_pid HAVING bestid > 0 ORDER BY bestid DESC LIMIT 1");
			$pid = $db->fetch_field($dnt_query,'dnt_prt_pid');
			$pid = (int)$pid;
			if($pid > 0)
			{
				$dnt_prt_query = $db->simple_select('posts','*',"pid={$pid}");
				while($post = $db->fetch_array($dnt_prt_query))
				{
					$tid = (int)$post['tid'];
					$pid = (int)$post['pid'];
					$subject = htmlspecialchars_uni($post['subject']);
					$subject_link = get_post_link($pid,$tid)."#pid".$pid;
					$subject = "<a href=\"{$subject_link}\">{$subject}</a>";
					$memprofile['dnt_prt_rates'] = unserialize($post['dnt_prt_rates_posts']);
					$total = (int)$memprofile['dnt_prt_rates']['total'];
				}
			}
		}
		if(isset($tid) && $pid > 0 || isset($tid) && $tid > 0)
		{
			eval("\$dnt_prt_results1 = \"".$templates->get("dnt_prt_results1")."\";");
			eval("\$dnt_prt_results2 = \"".$templates->get("dnt_prt_results2")."\";");
			eval("\$dnt_prt_results3 = \"".$templates->get("dnt_prt_results3")."\";");
			eval("\$dnt_prt_results4 = \"".$templates->get("dnt_prt_results4")."\";");
			eval("\$dnt_prt_results5 = \"".$templates->get("dnt_prt_results5")."\";");
			eval("\$dnt_prt_results6 = \"".$templates->get("dnt_prt_results6")."\";");				
			
			$likes = (int)$memprofile['dnt_prt_rates']['likes'];
			$loves = (int)$memprofile['dnt_prt_rates']['loves'];
			$wow = (int)$memprofile['dnt_prt_rates']['wow'];
			$smiles = (int)$memprofile['dnt_prt_rates']['smiles'];
			$crys = (int)$memprofile['dnt_prt_rates']['crys'];
			$angrys = (int)$memprofile['dnt_prt_rates']['angrys'];

			if($likes > 0)
				eval("\$memprofile['dnt_likes'] = \"".$templates->get("dnt_prt_likes")."\";");
			if($loves > 0)
				eval("\$memprofile['dnt_loves'] = \"".$templates->get("dnt_prt_loves")."\";");
			if($wow > 0)
				eval("\$memprofile['dnt_wow'] = \"".$templates->get("dnt_prt_wow")."\";");
			if($smiles > 0)
				eval("\$memprofile['dnt_smiles'] = \"".$templates->get("dnt_prt_smiles")."\";");
			if($crys > 0)
				eval("\$memprofile['dnt_crys'] = \"".$templates->get("dnt_prt_crys")."\";");
			if($angrys > 0)
				eval("\$memprofile['dnt_angrys'] = \"".$templates->get("dnt_prt_angrys")."\";");
			$dnt_prt_url = $mybb->settings['bburl']."/dnt_post_rate.php?action=get_thread_rates&lid=all&amp;tid={$tid}&amp;pid={$pid}";
			$memprofile['dnt_prt_view_all'] = $lang->sprintf($lang->dnt_prt_view_all_mp, $dnt_prt_url);
			$memprofile['dnt_prt_rates'] = $subject."<br />";
			$memprofile['clasify_post_rates_msg'] = $memprofile['dnt_likes'].$memprofile['dnt_loves'].$memprofile['dnt_wow'].$memprofile['dnt_smiles'].$memprofile['dnt_crys'].$memprofile['dnt_angrys'];				
			$memprofile['dnt_prt_total'] = $lang->sprintf($lang->dnt_prt_total_best_mp, $total);
			eval("\$dnt_prt_templates = \"".$templates->get("dnt_prt_clasify_post_rates_msg_memprofile")."\";");
		}
	}
	
	if($mybb->settings['dnt_post_rate_top5_given_memprofile'] == 1)
	{
		$queryg = $db->simple_select('dnt_post_rate','COUNT(*) AS bestid, dnt_prt_type',"dnt_prt_sender='{$memprofile['uid']}' GROUP BY dnt_prt_type HAVING bestid > 0 ORDER BY dnt_prt_type ASC LIMIT 6");		
		while($pclg = $db->fetch_array($queryg))
		{
			$pcl['bestid'] = (int)$pclg['bestid'];
			if($pclg['dnt_prt_type'] == 1)
				eval("\$dnt_prt_resultsg .= \"".$templates->get("dnt_prt_memprofile1")."\";");
			if($pclg['dnt_prt_type'] == 2)
				eval("\$dnt_prt_resultsg .= \"".$templates->get("dnt_prt_memprofile2")."\";");
			if($pclg['dnt_prt_type'] == 3)
				eval("\$dnt_prt_resultsg .= \"".$templates->get("dnt_prt_memprofile3")."\";");
			if($pclg['dnt_prt_type'] == 4)
				eval("\$dnt_prt_resultsg .= \"".$templates->get("dnt_prt_memprofile4")."\";");
			if($pclg['dnt_prt_type'] == 5)				
				eval("\$dnt_prt_resultsg .= \"".$templates->get("dnt_prt_memprofile5")."\";");
			if($pclg['dnt_prt_type'] == 6)
				eval("\$dnt_prt_resultsg .= \"".$templates->get("dnt_prt_memprofile6")."\";");
		}
	}

	if($mybb->settings['dnt_post_rate_top5_received_memprofile'] == 1)
	{
		$queryr = $db->simple_select('dnt_post_rate','COUNT(*) AS bestid, dnt_prt_type',"dnt_prt_user='{$memprofile['uid']}' GROUP BY dnt_prt_type HAVING bestid > 0 ORDER BY dnt_prt_type ASC LIMIT 6");	
		while($pclr = $db->fetch_array($queryr))
		{
			$pcl['bestid'] = (int)$pclr['bestid'];			
			if($pclr['dnt_prt_type'] == 1)
				eval("\$dnt_prt_resultsr .= \"".$templates->get("dnt_prt_memprofile1")."\";");
			if($pclr['dnt_prt_type'] == 2)
				eval("\$dnt_prt_resultsr .= \"".$templates->get("dnt_prt_memprofile2")."\";");
			if($pclr['dnt_prt_type'] == 3)
				eval("\$dnt_prt_resultsr .= \"".$templates->get("dnt_prt_memprofile3")."\";");
			if($pclr['dnt_prt_type'] == 4)
				eval("\$dnt_prt_resultsr .= \"".$templates->get("dnt_prt_memprofile4")."\";");
			if($pclr['dnt_prt_type'] == 5)				
				eval("\$dnt_prt_resultsr .= \"".$templates->get("dnt_prt_memprofile5")."\";");
			if($pclr['dnt_prt_type'] == 6)
				eval("\$dnt_prt_resultsr .= \"".$templates->get("dnt_prt_memprofile6")."\";");
		}		
	}
	
	if(isset($dnt_prt_stats))
	eval("\$memprofile['dnt_prt'] .= \"".$templates->get("dnt_prt_stats")."\";");
	if(isset($dnt_prt_templates))
	$memprofile['dnt_prt'] .= $dnt_prt_templates;
	if(isset($dnt_prt_resultsg))
	eval("\$memprofile['top5_given'] = \"".$templates->get("dnt_prt_templatesg")."\";");
	if(isset($dnt_prt_resultsr))
	eval("\$memprofile['top5_received'] = \"".$templates->get("dnt_prt_templatesr")."\";");
}

function dnt_post_rates()
{
	global $mybb, $theme, $templates, $lang, $thread, $db, $dnt_prt_rates;

	$lang->load('dnt_post_rate',false,true);	
	$dnt_prt_rates = "";
	if($mybb->settings['dnt_post_rate_showthread'] == 0)
		return false;
	$dnt_prt_fids = $mybb->settings['dnt_post_rate_forums'];
	if($dnt_prt_fids != "-1" && !empty($dnt_prt_fids))
	{
		$dnt_prt_fids = explode(",",$mybb->settings['dnt_post_rate_forums']);
		$fid = (int)$thread['fid'];
		if(!in_array($fid, $dnt_prt_fids))
			return false;		
	}		
	$tid = $thread['tid'];
	if($mybb->settings['dnt_post_rate_showthread_all'] == 1)
	{	
		$thread['dnt_prt_rates_threads'] = unserialize($thread['dnt_prt_rates_threads']);	
		eval("\$dnt_prt_results1 .= \"".$templates->get("dnt_prt_thread_rates1")."\";");
		eval("\$dnt_prt_results2 .= \"".$templates->get("dnt_prt_thread_rates2")."\";");
		eval("\$dnt_prt_results3 .= \"".$templates->get("dnt_prt_thread_rates3")."\";");
		eval("\$dnt_prt_results4 .= \"".$templates->get("dnt_prt_thread_rates4")."\";");
		eval("\$dnt_prt_results5 .= \"".$templates->get("dnt_prt_thread_rates5")."\";");
		eval("\$dnt_prt_results6 .= \"".$templates->get("dnt_prt_thread_rates6")."\";");
		
		$likes = (int)$thread['dnt_prt_rates_threads']['likes'];
		$loves = (int)$thread['dnt_prt_rates_threads']['loves'];
		$wow = (int)$thread['dnt_prt_rates_threads']['wow'];
		$smiles = (int)$thread['dnt_prt_rates_threads']['smiles'];
		$crys = (int)$thread['dnt_prt_rates_threads']['crys'];
		$angrys = (int)$thread['dnt_prt_rates_threads']['angrys'];
		
		$dnt_prt_rates = '<div style="float:right">';	
		if($likes > 0)
			eval("\$dnt_prt_rates .= \"".$templates->get("dnt_prt_likes")."\";");
		if($loves > 0)
			eval("\$dnt_prt_rates .= \"".$templates->get("dnt_prt_loves")."\";");		
		if($wow > 0)
			eval("\$dnt_prt_rates .= \"".$templates->get("dnt_prt_wow")."\";");
		if($smiles > 0)
			eval("\$dnt_prt_rates .= \"".$templates->get("dnt_prt_smiles")."\";");
		if($crys > 0)
			eval("\$dnt_prt_rates .= \"".$templates->get("dnt_prt_crys")."\";");
		if($angrys > 0)
			eval("\$dnt_prt_rates .= \"".$templates->get("dnt_prt_angrys")."\";");
		$dnt_prt_rates .= '</div>';
		unset($thread['dnt_prt_rates_threads']);
	}
	else
	{
		$thread['dnt_prt_rates_threads_post'] = unserialize($thread['dnt_prt_rates_threads_post']);	
		eval("\$dnt_prt_results1 .= \"".$templates->get("dnt_prt_thread_rates1")."\";");
		eval("\$dnt_prt_results2 .= \"".$templates->get("dnt_prt_thread_rates2")."\";");
		eval("\$dnt_prt_results3 .= \"".$templates->get("dnt_prt_thread_rates3")."\";");
		eval("\$dnt_prt_results4 .= \"".$templates->get("dnt_prt_thread_rates4")."\";");
		eval("\$dnt_prt_results5 .= \"".$templates->get("dnt_prt_thread_rates5")."\";");
		eval("\$dnt_prt_results6 .= \"".$templates->get("dnt_prt_thread_rates6")."\";");
		
		$likes = (int)$thread['dnt_prt_rates_threads_post']['likes'];
		$loves = (int)$thread['dnt_prt_rates_threads_post']['loves'];
		$wow = (int)$thread['dnt_prt_rates_threads_post']['wow'];
		$smiles = (int)$thread['dnt_prt_rates_threads_post']['smiles'];
		$crys = (int)$thread['dnt_prt_rates_threads_post']['crys'];
		$angrys = (int)$thread['dnt_prt_rates_threads_post']['angrys'];
		
		$dnt_prt_rates = '<div style="float:right">';	
		if($likes > 0)
			eval("\$dnt_prt_rates .= \"".$templates->get("dnt_prt_likes")."\";");
		if($loves > 0)
			eval("\$dnt_prt_rates .= \"".$templates->get("dnt_prt_loves")."\";");		
		if($wow > 0)
			eval("\$dnt_prt_rates .= \"".$templates->get("dnt_prt_wow")."\";");
		if($smiles > 0)
			eval("\$dnt_prt_rates .= \"".$templates->get("dnt_prt_smiles")."\";");
		if($crys > 0)
			eval("\$dnt_prt_rates .= \"".$templates->get("dnt_prt_crys")."\";");
		if($angrys > 0)
			eval("\$dnt_prt_rates .= \"".$templates->get("dnt_prt_angrys")."\";");
		$dnt_prt_rates .= '</div>';
		unset($thread['dnt_prt_rates_threads_post']);		
	}
}

function dnt_post_rate_wol_activity($user_activity)
{
	global $mybb, $user, $session;

	if(!$mybb->settings['dnt_post_rate_active'] || !empty($session->is_spider))
	{
		return false;
	}
	
	$split_loc = explode(".php", $user_activity['location']);
	if($split_loc[0] == $user['location'])
	{
		$filename = '';
	}
	else
	{
		$filename = my_substr($split_loc[0], -my_strpos(strrev($split_loc[0]), "/"));
	}
	
	if ($filename == "dnt_post_rate")
	{
		$user_activity['activity'] = "dnt_post_rate";
	}
	
	return $user_activity;
}

function dnt_post_rate_friendly_wol_activity($plugin_array)
{
	global $mybb, $lang, $session;

	if(!$mybb->settings['dnt_post_rate_active'] || !empty($session->is_spider))
	{
		return false;
	}
	
	$lang->load('dnt_post_rate', false, true);
	
	if ($plugin_array['user_activity']['activity'] == "dnt_post_rate")
	{
		$uid = (int)$mybb->user['uid'];
		$plugin_array['location_name'] = $lang->sprintf($lang->dnt_prt_wol, "dnt_post_rate.php?action=get_received_rates&uid=".$uid, $lang->dnt_prt_rate);
	}
	
	return $plugin_array;
}

function dnt_post_rate_insert_thread(&$data)
{
	global $db, $mybb;
	if(!$mybb->settings['dnt_post_rate_active'] || !empty($session->is_spider))
	{
		return false;
	}	
	$data->thread_insert_data['dnt_prt_rates_threads'] = "";
	$data->thread_insert_data['dnt_prt_rates_threads_post'] = "";
}

function dnt_post_rate_insert_post(&$data)
{
	global $db, $mybb;	
	if(!$mybb->settings['dnt_post_rate_active'] || !empty($session->is_spider))
	{
		return false;
	}	
	$data->post_insert_data['dnt_prt_rates_posts'] = "";
}

function dnt_post_rate_admin_action(&$action)
{
	global $mybb;

	if(!$mybb->settings['dnt_post_rate_active'] || !empty($session->is_spider))
	{
		return false;
	}
	
	$action['recount_dnt_prt_rates'] = array ('active'=>'recount_dnt_prt_rates');
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
		'id'	=> 'recount_dnt_prt_rates',
		'title'	=> $db->escape_string($lang->dnt_prt_recount),
		'link'	=> 'index.php?module=tools-recount_dnt_prt_rates'
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
	
	$admin_permissions['recount_dnt_prt_rates'] = $db->escape_string($lang->dnt_prt_can_recount);
}

function dnt_post_rate_admin()
{
	global $mybb, $page, $db, $lang;

	if(!$mybb->settings['dnt_post_rate_active'] || !empty($session->is_spider))
	{
		return false;
	}	

	require_once MYBB_ROOT.'inc/functions_rebuild.php';
	if($page->active_action != 'recount_dnt_prt_rates')
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
			if(!(int)$mybb->input['dnt_prt_threads_chunk_size'])
			{
				$mybb->input['dnt_prt_threads_chunk_size'] = 300;
			}

			do_dnt_prt_threads_recount();
		}
		else if(isset($mybb->input['do_recount_rates_users']))
		{
			if(!(int)$mybb->input['dnt_prt_users_chunk_size'])
			{
				$mybb->input['dnt_prt_users_chunk_size'] = 500;
			}

			do_dnt_prt_users_recount();
		}
	}

	$page->add_breadcrumb_item($db->escape_string($lang->dnt_prt_recount), "index.php?module=tools-recount_dnt_prt_rates");
	$page->output_header($db->escape_string($lang->dnt_prt_recount));

	$sub_tabs['thanks_recount'] = array(
		'title'			=> $db->escape_string($lang->dnt_prt_recount_do),
		'link'			=> "index.php?module=tools-recount_dnt_prt_rates",
		'description'	=> $db->escape_string($lang->dnt_prt_upgrade_do)
	);

	$page->output_nav_tabs($sub_tabs, 'dnt_prt_rates_recount');

	$form = new Form("index.php?module=tools-recount_dnt_prt_rates", "post");

	$form_container = new FormContainer($db->escape_string($lang->dnt_prt_recount));
	$form_container->output_row_header($db->escape_string($lang->dnt_prt_recount_desc));
	$form_container->output_row_header($db->escape_string($lang->dnt_prt_recount_send), array('width' => 50));
	$form_container->output_row_header("&nbsp;");

	$form_container->output_cell("<label>".$db->escape_string($lang->dnt_prt_recount_threads)."</label>
	<div class=\"description\">".$db->escape_string($lang->dnt_prt_recount_task_desc)."</div>");
	$form_container->output_cell($form->generate_text_box("dnt_prt_threads_chunk_size", 300, array('style' => 'width: 150px;')));
	$form_container->output_cell($form->generate_submit_button($db->escape_string($lang->dnt_prt_recount_send), array("name" => "do_recount_rates_threads")));
	$form_container->construct_row();

	$form_container->output_cell("<label>".$db->escape_string($lang->dnt_prt_recount_users)."</label>
	<div class=\"description\">".$db->escape_string($lang->dnt_prt_recount_task2_desc).".</div>");
	$form_container->output_cell($form->generate_text_box("dnt_prt_users_chunk_size", 500, array('style' => 'width: 150px;')));
	$form_container->output_cell($form->generate_submit_button($db->escape_string($lang->dnt_prt_recount_send), array("name" => "do_recount_rates_users")));
	$form_container->construct_row();

	$form_container->end();

	$form->end();

	$page->output_footer();

	exit;
}


function do_dnt_prt_threads_recount()
{
	global $db, $mybb, $lang;

	if(!$mybb->settings['dnt_post_rate_active'] || !empty($session->is_spider))
	{
		return false;
	}

	$lang->load('dnt_post_rate',false,true);	
	
	$cur_page = (int)$mybb->input['page'];
	$per_page = (int)$mybb->input['dnt_prt_threads_chunk_size'];
	$start = ($cur_page-1) * $per_page;
	$end = $start + $per_page;

	if ($cur_page == 1)
	{
		$db->write_query("UPDATE ".TABLE_PREFIX."threads SET dnt_prt_rates_threads=''");
		$db->write_query("UPDATE ".TABLE_PREFIX."threads SET dnt_prt_rates_threads_post=''");
		$db->write_query("UPDATE ".TABLE_PREFIX."posts SET dnt_prt_rates_posts=''");
		$db->write_query("UPDATE ".TABLE_PREFIX."threads SET dnt_prt_total='0'");		
	}

	if($mybb->settings['dnt_post_rate_only_firspost'] == 1)
	{
		$query = $db->simple_select("dnt_post_rate", "COUNT(dnt_prt_id) AS dnt_prt_count");
		$dnt_prt_count = $db->fetch_field($query, 'dnt_prt_count');
		$likes = $loves = $wow = $smiles = $crys = $angrys = 0;

		$query = $db->query("SELECT dp.*, t.tid, t.firstpost
			FROM ".TABLE_PREFIX."dnt_post_rate dp
			LEFT JOIN ".TABLE_PREFIX."threads t
			ON (dp.dnt_prt_tid=t.tid)
			WHERE t.firstpost = dp.dnt_prt_pid
			ORDER BY dnt_prt_tid ASC
			LIMIT {$start}, {$per_page}"
		);
		
		while($pcl = $db->fetch_array($query))
		{
			$type1 = $db->simple_select('dnt_post_rate', 'COUNT(dnt_prt_count) as count1', 'dnt_prt_pid='.(int)$pcl['dnt_prt_pid'].' AND dnt_prt_type=1');
			$likes = $db->fetch_field($type1, 'count1');
			$type2 = $db->simple_select('dnt_post_rate', 'COUNT(dnt_prt_count) as count2', 'dnt_prt_pid='.(int)$pcl['dnt_prt_pid'].' AND dnt_prt_type=2');
			$loves = $db->fetch_field($type2, 'count2');
			$type3 = $db->simple_select('dnt_post_rate', 'COUNT(dnt_prt_count) as count3', 'dnt_prt_pid='.(int)$pcl['dnt_prt_pid'].' AND dnt_prt_type=3');
			$wow = $db->fetch_field($type3, 'count3');
			$type4 = $db->simple_select('dnt_post_rate', 'COUNT(dnt_prt_count) as count4', 'dnt_prt_pid='.(int)$pcl['dnt_prt_pid'].' AND dnt_prt_type=4');
			$smiles = $db->fetch_field($type4, 'count4');
			$type5 = $db->simple_select('dnt_post_rate', 'COUNT(dnt_prt_count) as count5', 'dnt_prt_pid='.(int)$pcl['dnt_prt_pid'].' AND dnt_prt_type=5');
			$crys = $db->fetch_field($type5, 'count5');
			$type6 = $db->simple_select('dnt_post_rate', 'COUNT(dnt_prt_count) as count6', 'dnt_prt_pid='.(int)$pcl['dnt_prt_pid'].' AND dnt_prt_type=6');
			$angrys = $db->fetch_field($type6, 'count6');
			$total = (int)$likes + (int)$loves + (int)$wow + (int)$smiles + (int)$crys + (int)$angrys;
			$pcl['dnt_prt_pid'] = (int)$pcl['dnt_prt_pid'];
			$pcl['dnt_prt_rates'] = array(
				'likes' => (int)$likes,
				'loves' => (int)$loves,
				'wow' => (int)$wow,
				'smiles' => (int)$smiles,
				'crys' => (int)$crys,
				'angrys' => (int)$angrys,
				'total' => (int)$total
			);
			$pcl['dnt_prt_rates'] = serialize($pcl['dnt_prt_rates']);
			$db->update_query("threads", array("dnt_prt_rates_threads" => $db->escape_string($pcl['dnt_prt_rates']), "dnt_prt_total" => $db->escape_string($total)), "tid='{$pcl['dnt_prt_tid']}'");
			$db->update_query("posts", array("dnt_prt_rates_posts" => $db->escape_string($pcl['dnt_prt_rates'])), "pid='{$pcl['dnt_prt_pid']}'");
			$db->update_query("threads", array("dnt_prt_rates_threads_post" => $db->escape_string($pcl['dnt_prt_rates'])), "tid='{$pcl['dnt_prt_tid']}' AND firstpost='{$pcl['dnt_prt_pid']}'");			
		}		
	}
	else
	{
		$query = $db->simple_select("dnt_post_rate", "COUNT(dnt_prt_id) AS dnt_prt_count");
		$dnt_prt_count = $db->fetch_field($query, 'dnt_prt_count');
		$likes = $loves = $wow = $smiles = $crys = $angrys = 0;
		
		$query = $db->simple_select("dnt_post_rate", "*", '', array('order_by' => 'dnt_prt_tid', 'group_by' => 'dnt_prt_tid', 'order_dir' => 'asc', 'limit_start' => $start, 'limit' => $per_page));
		while($pcl = $db->fetch_array($query))
		{
			$type1 = $db->simple_select('dnt_post_rate', 'COUNT(dnt_prt_count) as count1', 'dnt_prt_tid='.(int)$pcl['dnt_prt_tid'].' AND dnt_prt_type=1');
			$likes = $db->fetch_field($type1, 'count1');
			$type2 = $db->simple_select('dnt_post_rate', 'COUNT(dnt_prt_count) as count2', 'dnt_prt_tid='.(int)$pcl['dnt_prt_tid'].' AND dnt_prt_type=2');
			$loves = $db->fetch_field($type2, 'count2');
			$type3 = $db->simple_select('dnt_post_rate', 'COUNT(dnt_prt_count) as count3', 'dnt_prt_tid='.(int)$pcl['dnt_prt_tid'].' AND dnt_prt_type=3');
			$wow = $db->fetch_field($type3, 'count3');
			$type4 = $db->simple_select('dnt_post_rate', 'COUNT(dnt_prt_count) as count4', 'dnt_prt_tid='.(int)$pcl['dnt_prt_tid'].' AND dnt_prt_type=4');
			$smiles = $db->fetch_field($type4, 'count4');
			$type5 = $db->simple_select('dnt_post_rate', 'COUNT(dnt_prt_count) as count5', 'dnt_prt_tid='.(int)$pcl['dnt_prt_tid'].' AND dnt_prt_type=5');
			$crys = $db->fetch_field($type5, 'count5');
			$type6 = $db->simple_select('dnt_post_rate', 'COUNT(dnt_prt_count) as count6', 'dnt_prt_tid='.(int)$pcl['dnt_prt_tid'].' AND dnt_prt_type=6');
			$angrys = $db->fetch_field($type6, 'count6');	
			$total = (int)$likes + (int)$loves + (int)$wow + (int)$smiles + (int)$crys + (int)$angrys;
			$pcl['dnt_prt_tid'] = (int)$pcl['dnt_prt_tid'];
			$pcl['dnt_prt_rates'] = array(
				'likes' => (int)$likes,
				'loves' => (int)$loves,
				'wow' => (int)$wow,
				'smiles' => (int)$smiles,
				'crys' => (int)$crys,
				'angrys' => (int)$angrys,
				'total' => (int)$total
			);
			$pcl['dnt_prt_rates'] = serialize($pcl['dnt_prt_rates']);
			$db->update_query("threads", array("dnt_prt_rates_threads" => $db->escape_string($pcl['dnt_prt_rates']), "dnt_prt_total" => $db->escape_string($total)), "tid='{$pcl['dnt_prt_tid']}'");		
		}
		
		$query = $db->simple_select("dnt_post_rate", "*", '', array('order_by' => 'dnt_prt_pid', 'group_by' => 'dnt_prt_pid', 'order_dir' => 'asc', 'limit_start' => $start, 'limit' => $per_page));
		while($pcl = $db->fetch_array($query))
		{
			$type1 = $db->simple_select('dnt_post_rate', 'COUNT(dnt_prt_count) as count1', 'dnt_prt_pid='.(int)$pcl['dnt_prt_pid'].' AND dnt_prt_type=1');
			$likes = $db->fetch_field($type1, 'count1');
			$type2 = $db->simple_select('dnt_post_rate', 'COUNT(dnt_prt_count) as count2', 'dnt_prt_pid='.(int)$pcl['dnt_prt_pid'].' AND dnt_prt_type=2');
			$loves = $db->fetch_field($type2, 'count2');
			$type3 = $db->simple_select('dnt_post_rate', 'COUNT(dnt_prt_count) as count3', 'dnt_prt_pid='.(int)$pcl['dnt_prt_pid'].' AND dnt_prt_type=3');
			$wow = $db->fetch_field($type3, 'count3');
			$type4 = $db->simple_select('dnt_post_rate', 'COUNT(dnt_prt_count) as count4', 'dnt_prt_pid='.(int)$pcl['dnt_prt_pid'].' AND dnt_prt_type=4');
			$smiles = $db->fetch_field($type4, 'count4');
			$type5 = $db->simple_select('dnt_post_rate', 'COUNT(dnt_prt_count) as count5', 'dnt_prt_pid='.(int)$pcl['dnt_prt_pid'].' AND dnt_prt_type=5');
			$crys = $db->fetch_field($type5, 'count5');
			$type6 = $db->simple_select('dnt_post_rate', 'COUNT(dnt_prt_count) as count6', 'dnt_prt_pid='.(int)$pcl['dnt_prt_pid'].' AND dnt_prt_type=6');
			$angrys = $db->fetch_field($type6, 'count6');
			$total = (int)$likes + (int)$loves + (int)$wow + (int)$smiles + (int)$crys + (int)$angrys;
			$pcl['dnt_prt_pid'] = (int)$pcl['dnt_prt_pid'];
			$pcl['dnt_prt_rates'] = array(
				'likes' => (int)$likes,
				'loves' => (int)$loves,
				'wow' => (int)$wow,
				'smiles' => (int)$smiles,
				'crys' => (int)$crys,
				'angrys' => (int)$angrys,
				'total' => (int)$total
			);
			$pcl['dnt_prt_rates'] = serialize($pcl['dnt_prt_rates']);
			$db->update_query("posts", array("dnt_prt_rates_posts" => $db->escape_string($pcl['dnt_prt_rates'])), "pid='{$pcl['dnt_prt_pid']}'");		
			$db->update_query("threads", array("dnt_prt_rates_threads_post" => $db->escape_string($pcl['dnt_prt_rates'])), "tid='{$pcl['dnt_prt_tid']}' AND firstpost='{$pcl['dnt_prt_pid']}'");			
		}		
	}
	
	dnt_prt_check_proceed($dnt_prt_count, $end, $cur_page+1, $per_page, "dnt_prt_threads_chunk_size", "do_recount_rates_threads", $db->escape_string($lang->dnt_prt_update_tsuccess));	
}

function do_dnt_prt_users_recount()
{
	global $db, $mybb, $lang;

	if(!$mybb->settings['dnt_post_rate_active'] || !empty($session->is_spider))
	{
		return false;
	}	

	$lang->load('dnt_post_rate',false,true);	
	
	$cur_page = (int)$mybb->input['page'];
	$per_page = (int)$mybb->input['dnt_prt_users_chunk_size'];
	$start = ($cur_page-1) * $per_page;
	$end = $start + $per_page;

	if ($cur_page == 1)
	{
		$db->write_query("UPDATE ".TABLE_PREFIX."users SET dnt_prt_rates_given='0', dnt_prt_rates_received='0'");	
	}

	if($mybb->settings['dnt_post_rate_only_firspost'] == 1)
	{
		$query = $db->simple_select("dnt_post_rate", "COUNT(dnt_prt_id) AS dnt_prt_count");
		$dnt_prt_count = $db->fetch_field($query, 'dnt_prt_count');

		$query = $db->query("SELECT dp.dnt_prt_user, dp.dnt_prt_sender, t.tid, t.firstpost
			FROM ".TABLE_PREFIX."dnt_post_rate dp
			LEFT JOIN ".TABLE_PREFIX."threads t
			ON (dp.dnt_prt_tid=t.tid)
			WHERE t.firstpost = dp.dnt_prt_pid
			ORDER BY dnt_prt_id ASC
			LIMIT {$start}, {$per_page}"
		);

		$user_given = array();
		$user_received = array();
		
		while($pcl = $db->fetch_array($query))
		{
			if($user_given[$pcl['dnt_prt_sender']])
			{
				$user_given[$pcl['dnt_prt_sender']]++;
			}
			else
			{
				$user_given[$pcl['dnt_prt_sender']] = 1;
			}
			if($user_received[$pcl['dnt_prt_user']])
			{
				$user_received[$pcl['dnt_prt_user']]++;
			}
			else
			{
				$user_received[$pcl['dnt_prt_user']] = 1;
			}
		}

		if(is_array($user_given))
		{
			foreach($user_given as $uid => $change)
			{
				$db->write_query("UPDATE ".TABLE_PREFIX."users SET dnt_prt_rates_given=dnt_prt_rates_given+{$change} WHERE uid='{$uid}'");
			}
		}
		if(is_array($user_received))
		{
			foreach($user_received as $touid => $change)
			{
				$db->write_query("UPDATE ".TABLE_PREFIX."users SET dnt_prt_rates_received=dnt_prt_rates_received+{$change} WHERE uid='{$touid}'");
			}
		}		
	}
	else
	{
		$query = $db->simple_select("dnt_post_rate", "COUNT(dnt_prt_id) AS dnt_prt_count");
		$dnt_prt_count = $db->fetch_field($query, 'dnt_prt_count');

		$query = $db->query("SELECT dnt_prt_user, dnt_prt_sender
			FROM ".TABLE_PREFIX."dnt_post_rate
			ORDER BY dnt_prt_id ASC
			LIMIT {$start}, {$per_page}"
		);

		$user_given = array();
		$user_received = array();
		
		while($pcl = $db->fetch_array($query))
		{
			if($user_given[$pcl['dnt_prt_sender']])
			{
				$user_given[$pcl['dnt_prt_sender']]++;
			}
			else
			{
				$user_given[$pcl['dnt_prt_sender']] = 1;
			}
			if($user_received[$pcl['dnt_prt_user']])
			{
				$user_received[$pcl['dnt_prt_user']]++;
			}
			else
			{
				$user_received[$pcl['dnt_prt_user']] = 1;
			}
		}

		if(is_array($user_given))
		{
			foreach($user_given as $uid => $change)
			{
				$db->write_query("UPDATE ".TABLE_PREFIX."users SET dnt_prt_rates_given=dnt_prt_rates_given+{$change} WHERE uid='{$uid}'");
			}
		}
		if(is_array($user_received))
		{
			foreach($user_received as $touid => $change)
			{
				$db->write_query("UPDATE ".TABLE_PREFIX."users SET dnt_prt_rates_received=dnt_prt_rates_received+{$change} WHERE uid='{$touid}'");
			}
		}
	}
	
	dnt_prt_check_proceed($dnt_prt_count, $end, $cur_page+1, $per_page, "dnt_prt_users_chunk_size", "do_recount_rates_users", $db->escape_string($lang->dnt_prt_update_usuccess));	
}

function dnt_prt_check_proceed($current, $finish, $next_page, $per_page, $name_chunk, $name_submit, $message)
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
		admin_redirect("index.php?module=tools-recount_dnt_prt_rates");
	}
	else
	{		
		$page->output_header();
		
		$form = new Form("index.php?module=tools-recount_dnt_prt_rates", 'post');
        $total = $current - $finish;
		echo $form->generate_hidden_field("page", $next_page);
		echo $form->generate_hidden_field($name_chunk, $per_page);
		echo $form->generate_hidden_field($name_submit, $lang->dnt_prt_upgrade_do);
		echo "<div class=\"confirm_action\">\n";
		echo $db->escape_string($lang->dnt_prt_confirm_next);
		echo "<br />\n";
		echo "<br />\n";
		echo "<script type=\"text/javascript\">$(function() { var button = $(\"#submit_button\"); if(button.length > 0) { button.val(\"".$db->escape_string($lang->dnt_prt_loading)."\"); button.attr(\"disabled\", true); button.css(\"color\", \"#aaa\"); button.css(\"borderColor\", \"#aaa\"); document.forms[0].submit(); }})</script>";
		echo "<p class=\"buttons\">\n";
		echo $form->generate_submit_button($db->escape_string($lang->dnt_prt_confirm_button), array('class' => 'button_yes', 'id' => 'submit_button'));
		echo "</p>\n";
		echo "<div style=\"float: right; color: #424242;\">".$db->escape_string($lang->dnt_prt_confirm_page)." {$next_page}\n";
		echo "<br />\n";
		echo $db->escape_string($lang->dnt_prt_confirm_elements)." {$total}</div>";
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
	else if(isset($mybb->settings['dnt_post_rate_version']) && $mybb->settings['dnt_post_rate_version'] < 160)
		dnt_post_rate_update16();
	else if(isset($mybb->settings['dnt_post_rate_version']) && $mybb->settings['dnt_post_rate_version'] < 161)
		dnt_post_rate_update161();
	else if(isset($mybb->settings['dnt_post_rate_version']) && $mybb->settings['dnt_post_rate_version'] < 163)
		dnt_post_rate_update163();	
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
		flash_message($lang->dnt_prt_update_version_ok, 'success');
		admin_redirect('index.php?module=config-plugins');
	}
	else
	{
		flash_message($lang->dnt_prt_update_version_bad, 'error');
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
	if(!$db->field_exists("dnt_prt_pid", "dnt_post_rate"))
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."dnt_post_rate` ADD `dnt_prt_pid` int(10) NOT NULL DEFAULT '0'");
	if(!$db->field_exists("dnt_prt_rates_threads", "threads") && $db->field_exists("dnt_prt_rates", "threads"))
		$db->query("RENAME TABLE `".TABLE_PREFIX."dnt_prt_rates` TO `".TABLE_PREFIX."dnt_prt_rates_threads`");
	else if(!$db->field_exists("dnt_prt_rates_threads", "threads"))
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."threads` ADD `dnt_prt_rates_threads` int(10) NOT NULL DEFAULT '0'");		
	if(!$db->field_exists("dnt_prt_total", "threads"))
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."threads` ADD `dnt_prt_total` int(10) NOT NULL DEFAULT '0'");
	if(!$db->field_exists("dnt_prt_rates_posts", "posts"))
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."posts` ADD `dnt_prt_rates_posts` text NOT NULL");
	if(!$db->field_exists("dnt_prt_rates_given", "users"))
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."users` ADD `dnt_prt_rates_given` int(10) NOT NULL DEFAULT '0'");
	if(!$db->field_exists("dnt_prt_rates_received", "users"))
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."users` ADD `dnt_prt_rates_received` int(10) NOT NULL DEFAULT '0'");	
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
	flash_message($lang->dnt_prt_update_version, 'success');
	admin_redirect('index.php?module=config-plugins');
}

function dnt_post_rate_update16()
{
	global $db, $mybb, $lang;
	if(!$mybb->settings['dnt_post_rate_active'] || !empty($session->is_spider))
		return false;
	if($db->field_exists("pcl_id", "dnt_post_rate") && !$db->field_exists("dnt_prt_id", "dnt_post_rate"))
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."dnt_post_rate` CHANGE `pcl_id` `dnt_prt_id` INT(10) NOT NULL AUTO_INCREMENT");	
	if($db->field_exists("pcl_type", "dnt_post_rate") && !$db->field_exists("dnt_prt_type", "dnt_post_rate"))
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."dnt_post_rate` CHANGE `pcl_type` `dnt_prt_type` INT(5) NOT NULL DEFAULT '0'");	
	if($db->field_exists("pcl_user", "dnt_post_rate") && !$db->field_exists("dnt_prt_user", "dnt_post_rate"))
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."dnt_post_rate` CHANGE `pcl_user` `dnt_prt_user` INT(10) NOT NULL DEFAULT '0'");	
	if($db->field_exists("pcl_sender", "dnt_post_rate") && !$db->field_exists("dnt_prt_sender", "dnt_post_rate"))
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."dnt_post_rate` CHANGE `pcl_sender` `dnt_prt_sender` INT(10) NOT NULL DEFAULT '0'");	
	if($db->field_exists("pcl_tid", "dnt_post_rate") && !$db->field_exists("dnt_prt_tid", "dnt_post_rate"))
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."dnt_post_rate` CHANGE `pcl_tid` `dnt_prt_tid` INT(10) NOT NULL DEFAULT '0'");	
	if($db->field_exists("pcl_pid", "dnt_post_rate") && !$db->field_exists("dnt_prt_pid", "dnt_post_rate"))
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."dnt_post_rate` CHANGE `pcl_pid` `dnt_prt_pid` INT(10) NOT NULL DEFAULT '0'");	
	if($db->field_exists("pcl_count", "dnt_post_rate") && !$db->field_exists("dnt_prt_count", "dnt_post_rate"))
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."dnt_post_rate` CHANGE `pcl_count` `dnt_prt_count` INT(10) NOT NULL DEFAULT '0'");	
	if($db->field_exists("pcl_date", "dnt_post_rate") && !$db->field_exists("dnt_prt_date", "dnt_post_rate"))
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."dnt_post_rate` CHANGE `pcl_date` `dnt_prt_date` INT(10) NOT NULL DEFAULT '0'");	

	if($db->field_exists("pcl_rates_posts", "posts") && !$db->field_exists("dnt_prt_rates_posts", "posts"))
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."posts` CHANGE `pcl_rates_posts` `dnt_prt_rates_posts` text NOT NULL");
	if($db->field_exists("pcl_rates_threads", "threads") && !$db->field_exists("dnt_prt_rates_threads", "threads"))
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."threads` CHANGE `pcl_rates_threads` `dnt_prt_rates_threads` text NOT NULL");
	if($db->field_exists("pcl_total", "threads") && !$db->field_exists("dnt_prt_total", "threads"))
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."threads` CHANGE `pcl_total` `dnt_prt_total` int(10) NOT NULL DEFAULT '0'");
	if($db->field_exists("pcl_rates_given", "users") && !$db->field_exists("dnt_prt_rates_given", "users"))
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."users` CHANGE `pcl_rates_given` `dnt_prt_rates_given` int(10) NOT NULL DEFAULT '0'");
	if($db->field_exists("pcl_rates_received", "users") && !$db->field_exists("dnt_prt_rates_received", "users"))
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."users` CHANGE `pcl_rates_received` `dnt_prt_rates_received` int(10) NOT NULL DEFAULT '0'");
	$db->update_query('settings',array('value' => 160),"name='dnt_post_rate_version'");
	rebuild_settings();
	flash_message($lang->dnt_prt_update_version, 'success');
	admin_redirect('index.php?module=config-plugins');
}

function dnt_post_rate_update161()
{
	global $db, $mybb, $lang;
	if(!$mybb->settings['dnt_post_rate_active'] || !empty($session->is_spider))
		return false;

	if($db->field_exists("pcl_rates_given", "users") && !$db->field_exists("dnt_prt_rates_given", "users"))
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."users` CHANGE `pcl_rates_given` `dnt_prt_rates_given` int(10) NOT NULL DEFAULT '0'");
	if($db->field_exists("pcl_rates_received", "users") && !$db->field_exists("dnt_prt_rates_received", "users"))
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."users` CHANGE `pcl_rates_received` `dnt_prt_rates_received` int(10) NOT NULL DEFAULT '0'");
	$db->update_query('settings',array('value' => 161),"name='dnt_post_rate_version'");
	rebuild_settings();
	flash_message($lang->dnt_prt_update_version, 'success');
	admin_redirect('index.php?module=config-plugins');
}

function dnt_post_rate_update163()
{
	global $db, $mybb, $lang;
	if(!$mybb->settings['dnt_post_rate_active'] || !empty($session->is_spider))
		return false;
	if(!$db->field_exists("dnt_prt_rates_threads_post", "threads"))
	{
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."threads` ADD `dnt_prt_rates_threads_post` text NOT NULL");
	}
	$db->update_query('settings',array('value' => 163),"name='dnt_post_rate_version'");
	rebuild_settings();
	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("postbit", '#'.preg_quote('post_content').'#', 'post_content{$post[\'dnt_prt_hl_post\']}', 0);
	find_replace_templatesets("postbit_classic", '#'.preg_quote('post_content').'#', 'post_content{$post[\'dnt_prt_hl_post\']}', 0);	
	flash_message($lang->dnt_prt_update_version, 'success');
	admin_redirect('index.php?module=config-plugins');
}

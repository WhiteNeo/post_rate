<?php
/**
 * Autor: Dark Neo
 * Plugin: Post Rate
 * Versión: 1.6
 * Single Converter
 */
define("IN_MYBB", 1);
define("NO_ONLINE", 1);
define('DNTPRT_CONVERSION_SCRIPT','converter.php');
require_once "global.php";
require_once "./inc/init.php";
if(!$mybb->usergroup['cancp'])
    error('You have no permissions to do this task','Post Rate Error');
ini_set('max_execution_time', 300);

if($db->table_exists("reputation") && $db->table_exists('dnt_post_rate'))
{
	$batch = 0;
	$total = 0;
	$thx = array();		
	$db->query("TRUNCATE TABLE `".TABLE_PREFIX."dnt_post_rate`");
	$query = $db->simple_select('reputation', '*', 'pid>0');
	while ($thanks = $db->fetch_array($query)) 
	{
		$pid = (int) $thanks['pid'];
		if($pid != 0)
		{
			if($thanks['reputation'] == 1)
				$thanks['type'] = 1;			
			else if($thanks['reputation'] > 1)
				$thanks['type'] = 2;
			else if($thanks['reputation'] == 0)
				$thanks['type'] = 3;
			else if($thanks['reputation'] == -1)
				$thanks['type'] = 4;
			else if($thanks['reputation'] < -1)
				$thanks['type'] = 6;
			$req = $db->simple_select('posts','tid',"pid={$pid}");
			$result = $db->fetch_array($req);		
			$thx[] = array(
				'dnt_prt_type'		=> (int) $thanks['type'],
				'dnt_prt_tid'		=> (int) $result['tid'],
				'dnt_prt_pid'	 	=> (int) $thanks['pid'],
				'dnt_prt_sender'	=> (int) $thanks['adduid'],
				'dnt_prt_user'		=> (int) $thanks['uid'],
				'dnt_prt_date'		=> (int) $thanks['dateline'],
				'dnt_prt_count'		=> 1
			);
			$batch++;
			$total++;
			if($batch == 1000) 
			{
				$db->insert_query_multiple('dnt_post_rate', $thx);
				$thx = array();
				$batch = 0;
				echo "System has converted {$total} items from MyBB Reputation System to Post Rate<br/>";
			}
		}
	}
	$db->insert_query_multiple('dnt_post_rate', $thx);
	echo "<span style=\"color: green;\">Done!!!</span><br />System has converted {$total} items from MyBB Reputation System to Post Rate<br/>Remember to remove this file (converter.php) from your server...<br />Make a recount of post rates to retrieve all necessary data into your new dnt_post_rate database";
}
else if (!$db->table_exists('dnt_post_rate'))
	echo "<span style=\"color: red;\">Warning!!!</span><br />You must install Post Rate System before run this converter...";
else
	echo "<span style=\"color: red;\">Warning!!!</span><br />There are no items to convert from other compatible systems to Post Rate System...";

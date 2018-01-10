<?php
/**
 * Autor: Dark Neo
 * Plugin: Post Rate
 * Versión: 1.5
 * Single Converter
 */
define("IN_MYBB", 1);
define("NO_ONLINE", 1);
define('DNTPRT_CONVERSION_SCRIPT','converter.php');
require_once "global.php";
require_once "./inc/init.php";
if(!$mybb->usergroup['cancp'])
{
    error('You have no permissions to do this task','Post Rate Error');
}
ini_set('max_execution_time', 300);
$batch = 0;
$total = 0;
$thx = array();
	if($db->table_exists("thx") && $db->table_exists('dnt_post_rate'))
	{
		$db->query("TRUNCATE TABLE `".TABLE_PREFIX."dnt_post_rate`");
		$query = $db->simple_select('thx', '*');
		while ($thanks = $db->fetch_array($query)) {
			$pid = (int) $thanks['pid'];
			$req = $db->simple_select('posts','tid',"pid={$pid}");
			$result = $db->fetch_array($req);			
			$thx[] = array(
				'dnt_prt_type'		=> 1,
				'dnt_prt_tid'		=> (int) $result['tid'],
				'dnt_prt_pid'	 	=> (int) $thanks['pid'],
				'dnt_prt_sender'	=> (int) $thanks['adduid'],
				'dnt_prt_user'		=> (int) $thanks['uid'],
				'dnt_prt_date'		=> (int) $thanks['time'],
				'dnt_prt_count'		=> 1
			);
			$batch++;
			$total++;
			if($batch == 1000) {
				$db->insert_query_multiple('dnt_post_rate', $thx);
				$thx = array();
				$batch = 0;
				echo "System has converted {$total} items from Thanks System to Post Rate<br/>";
			}
		}
		$db->insert_query_multiple('dnt_post_rate', $thx);
		echo "<span style=\"color: green;\">Done!!!</span><br />System has converted {$total} items from Thanks System to Post Rate<br/>Rememer to remove this file (converter.php) from your server...<br />Make a recount of post rates to retrieve all necesary data into your new dnt_post_rate database";
	}
	else if($db->table_exists('g33k_thankyoulike_thankyoulike') && $db->table_exists('dnt_post_rate'))
	{
		$db->query("TRUNCATE TABLE `".TABLE_PREFIX."dnt_post_rate`");		
		$query = $db->simple_select('g33k_thankyoulike_thankyoulike', '*');
		while ($thanks = $db->fetch_array($query)) 
		{
			$pid = (int) $thanks['pid'];
			$req = $db->simple_select('posts','tid',"pid={$pid}");
			$result = $db->fetch_array($req);			
			$thx[] = array(
				'dnt_prt_type'		=> 1,			
				'dnt_prt_tid'		=> (int) $result['tid'],
				'dnt_prt_pid'	 	=> (int) $thanks['pid'],
				'dnt_prt_sender'	=> (int) $thanks['uid'],
				'dnt_prt_user'		=> (int) $thanks['puid'],
				'dnt_prt_date'		=> (int) $thanks['dateline'],
				'dnt_prt_count'		=> 1				
			);
			$batch++;
			$total++;
			if($batch == 1000) {
				$db->insert_query_multiple('dnt_post_rate', $thx);
				$thx = array();
				$batch = 0;
				echo "System has converted {$total} items from TYL to Post Rate<br/>";
			}
		}
	$db->insert_query_multiple('dnt_post_rate', $thx);
	echo "<span style=\"color: green;\">Done!!!</span><br />System has converted {$total} items from TYL to Post Rate<br/>Rememer to remove this file (converter.php) from your server...<br />Make a recount of post rates to retrieve all necesary data into your new dnt_post_rate database";
	}
	
	else if($db->table_exists("post_likes") && $db->table_exists('dnt_post_rate'))
	{
		$db->query("TRUNCATE TABLE `".TABLE_PREFIX."dnt_post_rate`");		
		$query = $db->simple_select('post_likes', '*');
		while ($post_likes = $db->fetch_array($query)) {
			$pid = (int) $post_likes['post_id'];
			$req = $db->simple_select('posts','tid,dateline,uid',"pid={$pid}");
			$result = $db->fetch_array($req);			
			if(function_exists('simplelikes_info'))
				$likes_info = simplelikes_info();
			if($likes_info['version'] >= '2.0.0')
			{
				$date = strtotime($post_likes['created_at']);
				$puid = $post_likes['user_id'];
			}
			else
			{
				$puid = $post_likes['user_uid'];
				$date = $result['dateline'];
			}
			$thx[] = array(
				'dnt_prt_type'		=> 1,			
				'dnt_prt_tid'		=> (int) $result['tid'],
				'dnt_prt_pid'	 	=> (int) $post_likes['post_id'],
				'dnt_prt_user'		=> (int) $result['uid'],				
				'dnt_prt_sender'	=> (int) $puid,
				'dnt_prt_date'		=> (int) $date,
				'dnt_prt_count'		=> 1
			);
			$batch++;
			$total++;
			if($batch == 1000) {
				$db->insert_query_multiple('dnt_post_rate', $thx);
				$thx = array();
				$batch = 0;
				echo "System has converted {$total} items from Simple Likes System to Post Rate<br/>";
			}
		}
		$db->insert_query_multiple('dnt_post_rate', $thx);
		echo  "<span style=\"color: green;\">Done!!!</span><br />System has converted {$total} items from Simple Likes System to Post Rate<br/>Rememer to remove this file (converter.php) from your server...<br />Make a recount of post rates to retrieve all necesary data into your new dnt_post_rate database";
	}
	
	else if (!$db->table_exists('dnt_post_rate'))
	{
		echo "<span style=\"color: red;\">Warning!!!</span><br />You must install Post Rate System before run this converter...";
	}
	
	else
	{
		echo "<span style=\"color: red;\">Warning!!!</span><br />There are no items to convert from other compatible systems to Post Rate System...";
	}

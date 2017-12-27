# Post Rate Plugin fo MyBB 1.8.x Series
´´
Make a funny post rate into your forums like facebook smilies :)
´´
This plugin allows you 6 types of emotions into your posts to rate it in a nice way.
´´
You can see further details of actual 1.5 version in this video
´´
https://www.youtube.com/watch?v=NXy95_cLzj0
´´
´´
Templates where you can use own vars:
´´
member_profile
´´
{$memprofile['dnt_prt']}
$memprofile['stats']	
$memprofile['dnt_prt']
$memprofile['top5_given']
$memprofile['top5_received']
------------------------------------------------- Addittionaly you can use this ones ------------------------------------
{$memprofile['pcl_rates_given']}
{$memprofile['pcl_rates_received']}  
Make sure that vars are into that template.
´´
´´
postbit and postbit_classic
´´
{$post['pcl_rates_given']}
{$post['pcl_rates_received']}
{$post['clasify_post_rates']}
------------------------------------------------- Addittionaly you can use this ones ------------------------------------
{$post['likes']}
{$post['loves']}
{$post['wow']}
{$post['smiles']}
{$post['crys']}
{$post['angrys']}
´´
You can use in order to organize your mod at own needs, but many of them are not ajax capable, only the needed ones and really necesary.

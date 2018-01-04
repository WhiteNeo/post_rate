# Post Rate Plugin fo MyBB 1.8.x Series

Make a funny post rate into your forums like facebook smilies :)

This plugin allows you 6 types of emotions into your posts to rate it in a nice way.

You can see further details of actual 1.5 version in this video

https://www.youtube.com/watch?v=NXy95_cLzj0


Templates where you can use own vars:

member_profile
```HTML
{$memprofile['dnt_prt']}
$memprofile['top5_given']
$memprofile['top5_received']
---------------- Make sure that vars are into that template. ----------------
----------------- Addittionaly you can use this ones ------------------------
{$memprofile['dnt_prt_rates_given']}
{$memprofile['dnt_prt_rates_received']}  
```


postbit and postbit_classic
```HTML
{$post['rates_given']}
{$post['rates_received']}
{$post['clasify_post_rates']}
---------------- Make sure that vars are into that template. ----------------
------------------- Addittionaly you can use this ones ----------------------
{$post['likes']}
{$post['loves']}
{$post['wow']}
{$post['smiles']}
{$post['crys']}
{$post['angrys']}
```

* You can use in order to organize your mod at own needs, but many of them are not ajax capable, only the needed ones and really necesary.

* All changes can make into this posible things:

* Post Rates templates inside your theme (admincp -> styles &templates -> yourtheme)
* Language vars (inside inc/languages/yourlang/dnt_post_rates.lang.php)
* Stylesheet (admincp -> styles &templates -> yourtheme) search for pcl.css (Edit at your owns)

* If you need to convert from some other system (Only ThankYouMyBB System, Thankyoulike system and Simple Likes System are available)

* Open extras folder and upload converter.php file to forum root.

* Install Post Rate System.

* Keep in mind all current data would be deleted, so is is necesary to follow the right stepts to do this

* And then go to that url into your server. (Yourforum/converter.php), make sure you are logued in as admin.

* Once this process have end then remove this file.

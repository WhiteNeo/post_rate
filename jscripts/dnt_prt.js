/**************************************************************
 *                                                            *
 * DNT_PostRate JavaScript File                               *
 *                                                            *
 * DNT_Shoutbox created by DarkNeo For MyBB 1.8 			  *
 * Website: https://soportemybb.es      	                  *
 *                                                            *
 **************************************************************/
function DNTPostRate(lid,tid)
{
	$.get("xmlhttp.php?action=clasify_post_rate&lid="+lid+"&tid="+tid,function(request){
		$("#post_rates_btn").remove();
		$("#post_rates").remove();
		$.jGrowl(dnt_prt_success, {theme:'jgrowl_success'});	
		$("#clasify_post_rates_msgs_list").html(request.templates);
	});
}
function DNTPostRates(lid,tid)
{
	var dntulist = $("span#prt_list"+lid);
	if(dntulist.attr("rel") == 'dntulist')
	{
		dntulist.fadeIn("slow").css({"display":"inline","marginTop":"45px","marginLeft": "-12px"});
	}
	else
	{
		$.get("xmlhttp.php?action=get_post_rates&lid="+lid+"&tid="+tid,function(request){
			dntulist.attr("rel","dntulist");
			dntulist.html(request);
			dntulist.fadeIn("slow").css({"display":"inline","marginTop":"45px","marginLeft": "-12px"});
		});		
	}
}
function DNTPostRatesRemove(lid,tid)
{
	var dntulist = $("span#prt_list"+lid);	
	dntulist.fadeOut("slow");
}
$(document).on("ready", function(){
	$("#post_rates_btn").on("click", function(){
		$("#post_rates").slideToggle();
	});
});
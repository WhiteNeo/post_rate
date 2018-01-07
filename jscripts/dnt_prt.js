/**************************************************************
 *                                                            *
 * DNT_PostRate JavaScript File                               *
 *                                                            *
 * DNT_Shoutbox created by DarkNeo For MyBB 1.8 			  *
 * Website: https://soportemybb.es      	                  *
 *                                                            *
 **************************************************************/
function DNTPostRate(lid,tid,pid)
{
	$.get("xmlhttp.php?action=clasify_post_rate&lid="+lid+"&tid="+tid+"&pid="+pid,function(request,status){
		if(request.errors)
		{
			$.each(request.errors, function(i, error)
			{
				$.jGrowl(error, {theme: 'jgrowl_error'});
			});
			return false;					
		}
		else
		{
			var dreca = $('span#dnt_prt_reca'+pid).text();
			dreca++;
			$('span#dnt_prt_reca'+pid).text(dreca);
			$("#post_rates_btn_"+pid).removeClass("post_rate_button").css("display","inline").removeAttr("onclick").html(request.rate);
			$("#post_rates_"+pid).remove();
			$.jGrowl(dnt_prt_success, {theme:'jgrowl_success'});
			$("#clasify_post_rates_msgs_list"+pid).html(request.templates);
			if(request.is_popular == 1)
			{
				$("div.dnt_prt_post"+pid).addClass('dnt_post_hl');
				$("div#post_"+pid).next("div.post_content").addClass('dnt_popular_post');
			}
		}
	});
}
function DNTPostRates(lid,tid,pid)
{
	var dntulist = $("span#prt_list"+lid+"_pid"+pid);
	if(dntulist.attr("rel") == 'dntulist')
	{
		dntulist.show().css({"display":"inline"});
	}
	else
	{
		$.get("xmlhttp.php?action=get_post_rates&lid="+lid+"&tid="+tid+"&pid="+pid,function(request,status){
			if(request.errors)
			{
				$.each(request.errors, function(i, error)
				{
					$.jGrowl(error, {theme: 'jgrowl_error'});
				});
				return false;					
			}
			else
			{
				dntulist.attr("rel","dntulist");
				dntulist.html(request);
				dntulist.fadeIn("slow").css({"display":"inline"});
			}
		});		
	}
}
function DNTPostRatesRemove(lid,tid,pid)
{
	var dntulist = $("span#prt_list"+lid+"_pid"+pid);	
	dntulist.hide();
}
function DNTPostRatesMember(lid,tid)
{
	var dntulist = $("span#prt_list"+lid+"_pid"+tid);
	if(dntulist.attr("rel") == 'dntulist')
	{
		dntulist.show().css({"display":"inline"});
	}
	else
	{
		$.get("xmlhttp.php?action=get_post_rates_member&lid="+lid+"&tid="+tid,function(request,status){
			if(request.errors)
			{
				$.each(request.errors, function(i, error)
				{
					$.jGrowl(error, {theme: 'jgrowl_error'});
				});
				return false;					
			}
			else
			{
				dntulist.attr("rel","dntulist");
				dntulist.html(request);
				dntulist.fadeIn("slow").css({"display":"inline"});
			}
		});		
	}
}
function DNTPostRatesMemberRemove(lid,tid)
{
	var dntulist = $("span#prt_list"+lid+"_pid"+tid);	
	dntulist.hide();
}
function DNTRemoveRate(lid,tid,pid)
{
	if(!confirm(dnt_prt_remove_question))
	{
		stop();
		return false;
	}
	$.get("xmlhttp.php?action=remove_post_rates&lid="+lid+"&tid="+tid+"&pid="+pid,function(request,status){
		if(request.errors)
		{
			$.each(request.errors, function(i, error)
			{
				$.jGrowl(error, {theme: 'jgrowl_error'});
			});
			return false;					
		}
		else
		{
			var dreca = $('span#dnt_prt_reca'+pid).text();
			dreca--;
			$('span#dnt_prt_reca'+pid).text(dreca);
			$("#post_rates_btn_"+pid).removeClass("dnt_prt_div_rate").css("display","inline").removeAttr("onclick").html(request.button);
			$.jGrowl(dnt_prt_remove_success, {theme:'jgrowl_success'});
			$("#clasify_post_rates_msgs_list"+pid).html(request.templates);
			if(request.is_popular == 0)
			{
				$("div.dnt_prt_post"+pid).removeClass('dnt_post_hl');
				$("div#post_"+pid).next("div.post_content").removeClass('dnt_popular_post');
			}
		}
	});	
}
function DNTCantRemoveRate(pid)
{
	$.jGrowl(dnt_prt_remove_error, {theme:'jgrowl_error'});
}
function DNTShowMenu(pid)
{
	var dntumenu = $("#post_rates_"+pid);
	dntumenu.slideToggle();
}
$(document).on("ready",function(){
	$("span.post_rate_btn img").on("mouseover", function(){
		$(this).next("span.ptr_list_title").css({"display":"inline"});
		$(this).stop();				
	}).on("mouseout", function(){
		$(this).next("span.ptr_list_title").css("display","none");
		$(this).stop();		
	});
});

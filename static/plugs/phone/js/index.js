// JavaScript Document



$(".scroll a").click(function(){
	$(this).addClass('currer').siblings().removeClass('currer');
	})


$(window.document).scroll(function () {
	var t_shu=$(".banner").height();
	var g_shu=$(".hot").height();
	if($(window).scrollTop()>t_shu){
			$(".top").css({background:"#fff",borderBottom:"1px solid #ccc"})
		}else{
			$(".top").css({background:"transparent",borderBottom:"none"})
			};
	if($(window).scrollTop()>t_shu+g_shu){
			$(".nav_s").addClass('pos')
		}else{
			$(".nav_s").removeClass('pos')
			};
})


$(".dao").click(function(){
	$('.dao i,.dao span').css('color','#7b7b7b');
	$(this).find('i,span').css('color','#246fc0');
	})

$(".left_c li").click(function(){
	$('.left_c li a').css('color','#484848');
	$(this).css({background:"#fff"}).siblings().css({background:"transparent"});
	$(this).find('a').css('color','#246fc0');
	})


//商家
$(".titll .col-xs-4,.titll .col-xs-2").click(function(){
	$('.titll .col-xs-2').css('color','#666');
	$(this).css('color','#246fc0').siblings().css('color','#666');
	})

$(".sp_s").click(function(){
	$('.lie').css('display','block').siblings().css('display','none');
	$('.footer').css('display','block');
	})

$(".sp_p").click(function(){
	$('.ping').css('display','block').siblings().css('display','none');
	$('.footer').css('display','none');
	})

$(".sp_j").click(function(){
	$('.shop').css('display','block').siblings().css('display','none');
	$('.footer').css('display','none');
	})


$(".shou").click(function(){
if($(".icon-shoucang1").css("display")=="none"){
$(".icon-shoucang1").show();
$(".icon-shoucang").hide();
}else{
$(".icon-shoucang1").hide();
$(".icon-shoucang").show();
}
});


$(document).ready(function(){
//获得文本框对象
   var t = $("#text_box");
//初始化数量为1,并失效减
$('#min').attr('disabled',true);
    //数量增加操作
    $("#add").click(function(){    
        t.val(parseInt(t.val())+1)
        if (parseInt(t.val())!=1){
            $('#min').attr('disabled',false);
        }
      
    }) 
    //数量减少操作
    $("#min").click(function(){
        t.val(parseInt(t.val())-1);
        if (parseInt(t.val())==1){
            $('#min').attr('disabled',true);
        }
      
    })
   
});


$('.icon-iconfontgouwuche').click(function(){
	$('.window').css('display','block');
	$('.gou').css('display','block');
	})

$('.window').click(function(){
	$('.window').css('display','none');
	$('.gou').css('display','none');
	})


//支付

$(".zhong").click(function(){
if($(".icon-xuanzhong").css("display")=="none"){
$(".icon-xuanzhong").show();
$(".icon-weixuanzhong").hide();
}else{
$(".icon-xuanzhong").hide();
$(".icon-weixuanzhong").show();
}
});


//商家订单

$('.fa').click(function(){
	$('.window').css('display','block');
	$('.tis').css('display','block');
	})

$('.window').click(function(){
	$('.window').css('display','none');
	$('.tis').css('display','none');
	})





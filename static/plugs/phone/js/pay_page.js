// JavaScript Document

    //请求服务器生成 预支付订单
    //请求预支付订单成功后， 调起微信小程序支付界面
    var  mini_send = function(payUrl, proCode, payType){

        var perOrderPara = '';
        var perObj = '';
        var reqData = {type:'payment', icons_type:payType, product_code: proCode};
        $.ajax(payUrl, {
            dataType: 'json', method: 'post', data:{json: reqData},
            success: function (ret) {

                //预订单参数
                var retObj = $.parseJSON(ret)
                perOrderPara = retObj.data;

                if(perOrderPara.code != 0){
                    console.log('payment error')
                    return;
                }
                if( perOrderPara.length <= 0 ){
                    alert('order para is null');
                    perObj = 'order para is null';
                }else{
                    //alert('order =' + perOrderPara);
                    var perObj = perOrderPara; //JSON.parse(perOrderPara);
                }
                console.log('payment info: ' + perOrderPara)
                //异常判断 处理
                /* if( !perObj.code){
                 if(perObj.code == 2){

                 }

                 return;
                 }*/

                require(['phone.jweixin'], function (wx) {
                    console.log(wx)
                    wx.miniProgram.getEnv(function(res) {
                            var isMini =  res.miniprogram;

                            console.log(isMini) // true
                        }
                    );
                    //点击微信支付后，调取统一下单接口生成微信小程序支付需要的支付参数

                    var params = '?prePayId=' + perObj.prepayId + '&order_no=' + perObj.order_no
                        + '&timestamp=' + perObj.timeStamp + '&nonceStr=' + perObj.nonceStr
                        + '&signType=' + perObj.signType + '&paySign=' + perObj.paySign;


                    var path = '/pages/wxpay/wxpay' + params;
                    wx.miniProgram.navigateTo({
                        url: path,
                        success: function(){
                            console.log('navigateTo wxpay success')
                        },
                        fail: function(){
                            console.log('fail');
                        },
                        complete:function(){
                            console.log('navigateTo wxpay complete');
                        }

                    });

                })

            }
        });

    }  //end mini_send



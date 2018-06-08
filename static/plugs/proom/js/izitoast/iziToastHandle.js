
var toastController = {};


toastController = function(iziToast){
    "use strict";
    var that = {};

    iziToast.settings({
        timeout: 5000,
        // position: 'center',
        // imageWidth: 50,
        pauseOnHover: true,
        // resetOnHover: true,
        close: true,
        progressBar: true
        // layout: 1,
        // balloon: true,
        // target: '.target',
        // icon: 'material-icons',
        // iconText: 'face',
        // animateInside: false,
        // transitionIn: 'flipInX',
        // transitionOut: 'flipOutX',
    });

    $(".trigger-info").on('click', function (event) {
        event.preventDefault();

        iziToast.info({
            title: 'Hello',
            message: 'Welcome!',
            // imageWidth: 70,
            position: 'topCenter',
            //position: 'bottomLeft',
            transitionIn: 'bounceInRight',
            // rtl: true,
            // iconText: 'star',
            onOpen: function(){
                console.log('callback abriu! info');
            },
            onClose: function(){
                console.log("callback fechou! info");
            }
        });
    });

    $(".trigger-custom2").on('click', function (event) {
        event.preventDefault();

        iziToast.show({
            class: 'test',
            color: 'dark',
            icon: 'icon-contacts',
            title: 'Hello!',
            message: 'Do you like it?',
            position: 'topCenter',
            transitionIn: 'flipInX',
            transitionOut: 'flipOutX',
            progressBarColor: 'rgb(0, 255, 184)',
            //image: '/img/wawa/test1.jpg',
            imageWidth: 70,
            layout:2,
            onClose: function(){
                console.info('onClose');
            },
            iconColor: 'rgb(0, 255, 184)'
        });
    });


    var _toastCustom = function(position, pic, title, msg){

        if( pic.length <= 0){
            pic = '__STATIC__/plugs/proom/img/adou.png';
        }
        iziToast.show({
            class: 'test',
            color: 'red',
            icon: 'icon-contacts',
            title: title,
            message: msg,
            position: position, //'topCenter',
            transitionIn: 'flipInX',
            transitionOut: 'flipOutX',
            progressBarColor: 'rgb(0, 255, 184)',
            image: pic,
            imageWidth: 70,
            layout:1,
            close: true,
            onClose: function(){
                console.info('onClose');
            },
            iconColor: 'rgb(0, 255, 184)'
        });
    }



    //////////////////////////// interface functions ////////////////////////////////////////
    //position弹窗位置， pic头像路径， title标题， msg消息内容， rootUrl 头像根目录
    that.toastCustom = function(position, pic, title, msg, rootUrl){
        if( pic.length <= 0){
            pic = rootUrl + '/plugs/proom/img/adou.png';
        }
        _toastCustom(position, pic, title, msg);
    }
    return that;

}






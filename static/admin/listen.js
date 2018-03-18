
// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2017 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://think.ctolog.com
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | github开源项目：https://github.com/zoujingli/ThinkAdmin
// +----------------------------------------------------------------------

define(['jquery', 'admin.plugs'], function () {

    /*! 定义当前body对象 */
    this.$body = $('body');

    /*! 注册 data-load 事件行为 */
    this.$body.on('click', '[data-load]', function () {
        var url = $(this).attr('data-load'), tips = $(this).attr('data-tips');
        if ($(this).attr('data-confirm')) {
            return $.msg.confirm($(this).attr('data-confirm'), _goLoad);
        }
        return _goLoad.call(this);
        function _goLoad() {
            $.form.load(url, {}, 'get', null, true, tips);
        }
    });

    /*! 注册 data-serach 表单搜索行为 */
    this.$body.on('submit', 'form.form-search', function () {
        var url = $(this).attr('action').replace(/\&?page\=\d+/g, ''), split = url.indexOf('?') === -1 ? '?' : '&';
        if ((this.method || 'get').toLowerCase() === 'get') {
            return window.location.href = '#' + $.menu.parseUri(url + split + $(this).serialize());
        }
        $.form.load(url, this, 'post');
    });

    /*! 注册 data-modal 事件行为 */
    this.$body.on('click', '[data-modal]', function () {
        return $.form.modal($(this).attr('data-modal'), 'open_type=modal', $(this).attr('data-title') || '编辑');
    });

    /*! 注册 data-open 事件行为 */
    this.$body.on('click', '[data-open]', function () {
        $.form.href($(this).attr('data-open'), this);
    });

    /*! 注册 data-reload 事件行为 */
    this.$body.on('click', '[data-reload]', function () {
        $.form.reload();
    });

    /*! 注册 data-check 事件行为 */
    this.$body.on('click', '[data-check-target]', function () {
        var checked = !!this.checked;
        $($(this).attr('data-check-target')).map(function () {
            this.checked = checked;
        });
    });

    /*! 注册 data-update 事件行为 */
    this.$body.on('click', '[data-update]', function () {
        var id = $(this).attr('data-update') || (function () {
            var data = [];
            return $($(this).attr('data-list-target') || 'input.list-check-box').map(function () {
                (this.checked) && data.push(this.value);
            }), data.join(',');
        }).call(this);
        if (id.length < 1) {
            return $.msg.tips('请选择需要操作的数据！');
        }
        var action = $(this).attr('data-action') || $(this).parents('[data-location]').attr('data-location');
        var value = $(this).attr('data-value') || 0, field = $(this).attr('data-field') || 'status';
        $.msg.confirm('确定要操作这些数据吗？', function () {
            $.form.load(action, {field: field, value: value, id: id}, 'POST');
        });
    });

    /*! 注册 data-href 事件行为 */
    this.$body.on('click', '[data-href]', function () {
        var href = $(this).attr('data-href');
        (href && href.indexOf('#') !== 0) && (window.location.href = href);
    });

    /*! 注册 data-page-href 事件行为 */
    this.$body.on('click', 'a[data-page-href]', function () {
        window.location.href = '#' + $.menu.parseUri(this.href, this);
    });

    /*! 注册 data-file 事件行为 */
    this.$body.on('click', '[data-file]', function () {
        var method = $(this).attr('data-file') === 'one' ? 'one' : 'mtl';
        var type = $(this).attr('data-type') || 'jpg,png', field = $(this).attr('data-field') || 'file';
        var title = $(this).attr('data-title') || '文件上传', uptype = $(this).attr('data-uptype') || '';
        var url = window.ROOT_URL + '/index.php/admin/plugs/upfile.html?mode=' + method + '&uptype=' + uptype + '&type=' + type + '&field=' + field;
        $.form.iframe(url, title || '文件管理');
    });

    /*! 注册 data-iframe 事件行为 */
    this.$body.on('click', '[data-iframe]', function () {
        $.form.iframe($(this).attr('data-iframe'), $(this).attr('data-title') || '窗口');
    });

    /*! 注册 data-icon 事件行为 */
    this.$body.on('click', '[data-icon]', function () {
        var field = $(this).attr('data-icon') || $(this).attr('data-field') || 'icon';
        var url = window.ROOT_URL + '/index.php/admin/plugs/icon.html?field=' + field;
        $.form.iframe(url, '图标选择');
    });

    /*! 注册 data-tips-image 事件行为 */
    this.$body.on('click', '[data-tips-image]', function () {
        var img = new Image(), src = this.getAttribute('data-tips-image') || this.src;
        var imgWidth = this.getAttribute('data-width') || '480px';
        img.onload = function () {
            var $content = $(img).appendTo('body').css({background: '#fff', width: imgWidth, height: 'auto'});
            layer.open({type: 1, area: imgWidth, title: false, closeBtn: 1, skin: 'layui-layer-nobg', shadeClose: true, content: $content, end: function () {
                    $(img).remove();
                }
            });
        };
        img.src = src;
    });

    /*! 注册 data-tips-text 事件行为 */
    this.$body.on('mouseenter', '[data-tips-text]', function () {
        var text = $(this).attr('data-tips-text'), placement = $(this).attr('data-tips-placement') || 'auto';
        $(this).tooltip({title: text, placement: placement}).tooltip('show');
    });

    /*! 注册 data-phone-view 事件行为 */
    this.$body.on('click', '[data-phone-view]', function () {
        var $container = $('<div class="mobile-preview pull-left"><div class="mobile-header">公众号</div><div class="mobile-body"><iframe id="phone-preview" frameborder="0" scrolling="no" marginheight="0" marginwidth="0"></iframe></div></div>').appendTo('body');
        $container.find('iframe').attr('src', this.getAttribute('data-phone-view') || this.href);
        layer.style(layer.open({type: 1, scrollbar: !1, area: ['335px', '600px'], title: !1, closeBtn: 1, skin: 'layui-layer-nobg', shadeClose: !1, content: $container, end: function () {
                $container.remove();
            }
        }), {boxShadow: 'none'});
    });


    /* window.addEventListener('contextmenu', function(e){
        e.preventDefault();
    });
    $('button').bind('contextmenu', function(e) {
     e.preventDefault();
     console.log('禁止长按弹出菜单');
     })*/


    var timeOutEvent=0;
    function longPress(controlUrl, sendCmd){
        clearTimeout(timeOutEvent);
        timeOutEvent = 0;
        console.log("长按事件触发");

        //var sendCmd = $(this).attr('data-down');
        $.ajax(controlUrl, {
            dataType: 'json', method: 'post', data: sendCmd, success: function (ret) {

                console.log('返回 --> ' + ret);
            }
        });
        //alert("长按事件触发发");
    }
    this.$body.on('touchstart', '[data-down]', function (e) {

        //alert( "处理程序touchstart11" );
        var sendCmd = $(this).attr('data-down');
        var controlUrl = $(this).attr('url');
        //if (!timeOutEvent){
            timeOutEvent = setTimeout(longPress(controlUrl, sendCmd),5000);
        //}
        e.preventDefault();
        return false;

    });
    this.$body.on('touchmove', '[data-down]', function () {
        //alert( "处理程序touchmove" );
        clearTimeout(timeOutEvent);
        timeOutEvent = 0;

    });
    this.$body.on('touchend', '[data-up]', function () {
        //alert( "处理程序touchend" );
        clearTimeout(timeOutEvent);

        if(timeOutEvent!=0){
            //alert("你这是点击，不是长按899");
            timeOutEvent = 0;
        }

        var sendCmd = $(this).attr('data-up');
        var controlUrl = $(this).attr('url');

        $.ajax(controlUrl, {
            dataType: 'json', method: 'post', data: sendCmd, success: function (ret) {

                console.log('返回 --> ' + ret);
            }
        });

        return false;
    });


    /*! 注册 data-down 事件行为 */
    this.$body.on('click', '[data-coins]', function () {

        var controlUrl = 'http://120.77.61.179:2100/';
        var sendCmd = $(this).attr('data-coins')
        $.ajax(controlUrl, {
            dataType: 'json', method: 'post', data: sendCmd, success: function (ret) {

                console.log('返回 --> ' + ret);
            }
        });
    });

    /*! 注册 data-down 事件行为 */
    /*   this.$body.on('mousedown', '[data-down]', function () {


     var controlUrl = 'http://120.77.61.179:2100/';
     var sendCmd = $(this).attr('data-down')
     $.ajax(controlUrl, {
     dataType: 'json', method: 'post', data: sendCmd, success: function (ret) {

     console.log('返回 --> ' + ret);
     }
     });
     });*/
    /*! 注册 data-up 事件行为 */
    /*    this.$body.on('mouseup', '[data-up]', function () {

     var controlUrl = 'http://120.77.61.179:2100/';
     var sendCmd = $(this).attr('data-up')
     $.ajax(controlUrl, {
     dataType: 'json', method: 'post', data: sendCmd, success: function (ret) {

     console.log('返回 --> ' + ret);
     }
     });
     });
     */

    /*! 后台菜单控制初始化 */
    $.menu.listen();

    /*! 表单监听初始化 */
    $.validate.listen(this);

});
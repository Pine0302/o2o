$(function() {
//使用title内容作为tooltip提示文字
    $(document).tooltip({
        track: true
    });
    var  winWidth=$(document.body).outerWidth(true);
    if(winWidth<1500){

        $("#foldSidebar > i").addClass('fa-indent').removeClass('fa-outdent');
        $('.sub-menu').removeAttr('style');
        $('.admincp-container').addClass('fold').removeClass('unfold');
        $(".manager,.admincp-name,#foldSidebar").css("display","none")
        $('.sub-menu').find('a').on("click",function(){

            $(".admincp-header-r").css("margin","0");
            openItem($(this).attr('data-param'));
        });
        // 直接在最外层的div调用该函数即可
        changeOrientation($('body'));

        function changeOrientation( $print ){
            var width = document.documentElement.clientWidth;
            var height =  document.documentElement.clientHeight;
            if( width < height ){
                // 竖屏
                $print.width(height);
                // $print.height(width);
                $print.css('top',  (height-width)/2 );
                $print.css('left',  0-(height-width)/2 );
                $print.css('transform' , 'rotate(90deg)');
                $print.css('transform-origin' , '50% 50%');
            }

            var evt = "onorientationchange" in window ? "orientationchange" : "resize";

            window.addEventListener(evt, function() {

                setTimeout( function(){
                    var width = document.documentElement.clientWidth;
                    var height =  document.documentElement.clientHeight;
                    if( width > height ){
                        // 横屏
                        $print.width(width);
                        // $print.height(height);
                        $print.css('top',  0 );
                        $print.css('left',  0 );
                        $print.css('transform' , 'none');
                        $print.css('transform-origin' , '50% 50%');
                    }
                    else{
                        // 竖屏
                        $print.width(height);

                        $print.css('top',  (height-width)/2 );
                        $print.css('left',  0-(height-width)/2 );
                        $print.css('transform' , 'rotate(90deg)');
                        $print.css('transform-origin' , '50% 50%');
                    }

                }  , 300 );

            }, false);
        }


    }
    // 侧边导航二级菜单切换（展开式）
    $('.nav-tabs').each(function(){
        $(this).find('dl > dt > a').each(function(i){
            $(this).parent().next().css('top', "0");
            $(this).click(function(){

                if ($('.admincp-container').hasClass('fold')) {
                    return;
                }
                $('.sub-menu').hide();
                $('.nav-tabs').find('dl').removeClass('active');
                $(this).parents('dl:first').addClass('active');
                $(this).parent().next().show().find('a:first').click();
            });
        });
    });
    
    // 侧边导航展示形式切换
    $('#foldSidebar > i').click(function(){
        if ($('.admincp-container').hasClass('unfold')) {
            $(this).addClass('fa-indent').removeClass('fa-outdent');
            $('.sub-menu').removeAttr('style');
            $('.admincp-container').addClass('fold').removeClass('unfold');
        } else {
            $(this).addClass('fa-outdent').removeClass('fa-indent');
            $('.nav-tabs').each(function(i){
                $(this).find('dl').each(function(i){
                    $(this).find('dd').css('top', (-70)*i+"px");
                    if ($(this).hasClass('active')) {
                        $(this).find('dd').show();
                    }
                });
            });
            $('.admincp-container').addClass('unfold').removeClass('fold');
        }
    });
    // 侧边导航三级级菜单点击
    $('.sub-menu').find('a').click(function(){

        openItem($(this).attr('data-param'));
    });
    
    // 顶部各个模块切换
    $('.nc-module-menu').find('a').click(function(){
        if ($('.admincp-container').hasClass('unfold')) {
            $('.sub-menu').hide();
        }
        $('.nc-module-menu').find('li').removeClass('active');
        _modules = $(this).parent().addClass('active').attr('data-param');
        $('div[id^="admincpNavTabs_"]').hide();
        $('#admincpNavTabs_' + _modules).show().find('dl').removeClass('active').first().addClass('active').find('dd').find('li > a:first').click();
    });
    
    if ($.cookie('workspaceParam') == null) {

        // 默认选择第一个菜单
        //$('.nc-module-menu').find('li:first > a').click();
        openItem('welcome|Index');
    } else {

        openItem($.cookie('workspaceParam'));
    }
    // 导航菜单  显示
    $('a[tptype="map_on"],a[class="add-menu"]').click(function(){
        $('div[tptype="map_nav"]').show();
    });
    // 导航菜单 隐藏
    $('a[tptype="map_off"]').click(function(){
        $('div[tptype="map_nav"]').hide();
    });
    // 导航菜单切换
    $('a[data-param^="map-"]').click(function(){
        $(this).parent().addClass('selected').siblings().removeClass('selected');
        $('div[data-param^="map-"]').hide();
        $('div[data-param="' + $(this).attr('data-param') + '"]').show();
    });

    // 导航菜单默认值显示第一组菜单
    $('div[data-param^="map-"]:first').nextAll().hide();
    $('A[data-param^="map-"]:first').parent().addClass('selected');
    
});

// 点击菜单，iframe页面跳转
function openItem(param) {	

    $('.sub-menu').find('li').removeClass('active');
    data_str = param.split('|');
    $this = $('div[id^="admincpNavTabs_"]').find('a[data-param="' + param + '"]');
    if ($('.admincp-container').hasClass('unfold')) {
        $('.sub-menu').hide();
        $this.parents('dd:first').show();
    }
    $('div[id^="admincpNavTabs_"]').hide().find('dl').removeClass('active');
    $('li[data-param="' + data_str[1] + '"]').addClass('active');
    //$('li[data-param="' + data_str[0] + '"]').addClass('active');
    $this.parent().addClass('active').parents('dl:first').addClass('active').parents('div:first').show();

    console.log('/index.php?m=Seller&c=' + data_str[1] + '&a=' + data_str[0],"???????1111");

    $('#workspace').attr('src','/index.php?m=Seller&c=' + data_str[1] + '&a=' + data_str[0]);
    $.cookie('workspaceParam', data_str[0] + '|' + data_str[1], { expires: 1 ,path:"/"});
}

/* 显示Ajax表单 */
function ajax_form(id, title, url, width, model)
{
    if (!width)	width = 480;
    if (!model) model = 1;
    var d = DialogManager.create(id);
    d.setTitle(title);
    d.setContents('ajax', url);
    d.setWidth(width);
    d.show('center',model);
    return d;
}
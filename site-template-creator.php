<?php
/**
 * Plugin Name: Site Template Creator
 * Description: 允许登录用户点击模板按钮，一键在当前 Network 下新建属于自己的子站。
 * Version: 0.2
 * Author:  Ziyang Song
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// 1) 前端按钮短代码
function stc_buttons_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<p>请先 <a href="' . wp_login_url() . '">登录</a> 后再创建站点。</p>';
    }

    // 模板 slug => 主题文件夹名称
    $templates = array(
        'up-home'   => 'theme-uphome',
        'portfolio' => 'theme-portfolio',
        'resume'    => 'theme-resume',
    );

    ob_start(); ?>
    <div id="stc-button-wrap">
        <?php foreach ( $templates as $slug => $theme ) : ?>
            <button class="stc-btn" data-template="<?php echo esc_attr( $slug ); ?>">
                <?php echo esc_html( $slug ); ?>
            </button>
        <?php endforeach; ?>
    </div>
    <div id="stc-msg"></div>

    <script>
    (function($){
        $('#stc-button-wrap').on('click', '.stc-btn', function(e){
            e.preventDefault();
            var tpl = $(this).data('template');
            $('#stc-msg').text('正在创建，请稍候…');
            $.post( '<?php echo admin_url('admin-ajax.php'); ?>',
                {
                    action: 'stc_create_site',
                    template: tpl,
                    _ajax_nonce: '<?php echo wp_create_nonce('stc_nonce'); ?>'
                },
                function(res){
                    if ( res.success ) {
                        $('#stc-msg').html('站点创建成功：<a href="'+res.data.url+'" target="_blank">'+res.data.url+'</a>');
                    } else {
                        $('#stc-msg').text('创建失败：' + res.data);
                    }
                }
            );
        });
    })(jQuery);
    </script>

    <style>
    .stc-btn{margin:5px;padding:8px 16px;background:#0073aa;color:#fff;border:none;border-radius:3px;cursor:pointer;}
    .stc-btn:hover{background:#005177;}
    </style>
    <?php
    return ob_get_clean();
}
add_shortcode( 'site_template_buttons', 'stc_buttons_shortcode' );

// 2) AJAX 处理函数
function stc_ajax_create_site() {
    // 验证 nonce & 登录状态
    check_ajax_referer( 'stc_nonce' );
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( '请先登录' );
    }

    $user_id  = get_current_user_id();
    $template = sanitize_key( $_POST['template'] ?? '' );

    // 模板与对应主题映射
    $map = array(
        'up-home'   => 'theme-uphome',
        'portfolio' => 'theme-portfolio',
        'resume'    => 'theme-resume',
    );

    if ( ! isset( $map[ $template ] ) ) {
        wp_send_json_error( '无效的模板' );
    }

    // 生成站点路径：例如 /up-home-用户名/
    $user     = wp_get_current_user();
    $slug     = $template . '-' . $user->user_nicename;
    $slug     = sanitize_title_with_dashes( $slug );
    $domain   = parse_url( network_home_url(), PHP_URL_HOST );
    $path     = '/' . $slug . '/';
    $sitename = $user->display_name . ' 的站点';

    if ( domain_exists( $domain, $path ) ) {
        wp_send_json_error( '你已经创建过相同地址的站点：' . $path );
    }

    // 创建子站
    $blog_id = wpmu_create_blog( $domain, $path, $sitename, $user_id );
    if ( is_wp_error( $blog_id ) ) {
        wp_send_json_error( $blog_id->get_error_message() );
    }

    // 切换到新站，启用主题
    switch_to_blog( $blog_id );
    switch_theme( $map[ $template ] );
    restore_current_blog();

    // 返回新站 URL
    $new_site_url = network_home_url( $path );
    wp_send_json_success( array( 'url' => $new_site_url ) );
}
add_action( 'wp_ajax_stc_create_site', 'stc_ajax_create_site' );

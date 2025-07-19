<?php
/**
 * Plugin Name:  Creator Header (with Fallback Avatar)
 * Description:  显示自媒体个人信息并允许前端更换头像；若无头像则用默认占位图。
 * Version:      1.1.0
 * Author:       Ziyang Song
 * License:      GPL-2.0-or-later
 * Text Domain:  creator-header
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ======================================================
 *  1. 仅在含短代码页面加载 Media Library + JS
 * ==================================================== */
function ch_enqueue_assets() {
	global $post;
	if ( isset( $post->post_content ) && has_shortcode( $post->post_content, 'creator_header' ) ) {
		if ( is_user_logged_in() ) {
			wp_enqueue_media();
			wp_register_script(
				'ch-uploader',
				'',
				array( 'jquery', 'media-upload', 'media-views' ),
				'1.1',
				true
			);
			wp_enqueue_script( 'ch-uploader' );

			wp_localize_script(
				'ch-uploader',
				'chAvatar',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'ch_avatar_nonce' ),
					'success'  => __( '头像更新成功！', 'creator-header' ),
					'error'    => __( '上传失败，请重试', 'creator-header' ),
				)
			);

			wp_add_inline_script(
				'ch-uploader',
				<<<JS
jQuery(function($){
  $(document).on('click','.ch-change-avatar',function(e){
    e.preventDefault();
    var \$btn = $(this);
    var \$img = \$btn.closest('.ch-header').find('.ch-avatar');
    var frame = wp.media({
        title: '选择新头像',
        button: { text: '使用此头像' },
        multiple:false
    });
    frame.on('select',function(){
      var url = frame.state().get('selection').first().get('url');
      $.post(chAvatar.ajax_url,{
          action: 'ch_save_avatar',
          nonce:  chAvatar.nonce,
          avatar_url: url
      },function(resp){
          if(resp.success){
              \$img.attr('src',url);
              alert(chAvatar.success);
          }else{
              alert(resp.data || chAvatar.error);
          }
      });
    });
    frame.open();
  });
});
JS
			);
		}
	}
}
add_action( 'wp_enqueue_scripts', 'ch_enqueue_assets' );

/* ======================================================
 *  2. AJAX 保存头像 URL
 * ==================================================== */
function ch_save_avatar_ajax() {
	check_ajax_referer( 'ch_avatar_nonce', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( __( '请先登录', 'creator-header' ) );
	}

	$url = isset( $_POST['avatar_url'] ) ? esc_url_raw( $_POST['avatar_url'] ) : '';
	if ( empty( $url ) ) {
		wp_send_json_error( __( 'URL 为空', 'creator-header' ) );
	}

	update_user_meta( get_current_user_id(), 'ch_avatar_url', $url );
	wp_send_json_success();
}
add_action( 'wp_ajax_ch_save_avatar', 'ch_save_avatar_ajax' );

/* ======================================================
 *  3. [creator_header] 短代码
 * ==================================================== */
function ch_creator_header_shortcode( $atts ) {

    $atts = shortcode_atts(
        array(
            'avatar' => '',
            'name'   => '',
            'fans'   => 0,
            'bio'    => '',
        ),
        $atts,
        'creator_header'
    );

    /* ---------- 尝试获取用户已上传头像 ---------- */
    if ( empty( $atts['avatar'] ) && is_user_logged_in() ) {
        $atts['avatar'] = get_user_meta( get_current_user_id(), 'ch_avatar_url', true );
    }

    /* ---------- 若仍为空，则使用插件内置占位图 ---------- */
    if ( empty( $atts['avatar'] ) ) {
        $atts['avatar'] = plugin_dir_url( __FILE__ ) . 'default-avatar.png';
    }

    if ( empty( $atts['name'] ) ) {
        return '';
    }

    $editable = is_user_logged_in();

    ob_start(); ?>
    <div class="ch-header" style="position:relative;display:flex;align-items:center;gap:16px;
        padding:20px 24px;background:#151515;color:#fff;border-radius:8px;
        font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;width:100%;">
        <img src="<?php echo esc_url( $atts['avatar'] ); ?>" alt="<?php echo esc_attr( $atts['name'] ); ?>"
             class="ch-avatar" style="width:72px;height:72px;border-radius:50%;object-fit:cover">
        <?php if ( $editable ) : ?>
            <a href="#" class="ch-change-avatar"
               style="position:absolute;top:0;left:0;width:72px;height:72px;line-height:72px;text-align:center;
               color:#fff;background:rgba(0,0,0,.5);opacity:0;transition:.2s;border-radius:50%;text-decoration:none;">✎</a>
        <?php endif; ?>

        <div class="ch-info" style="flex:1">
            <h2 style="margin:0;font-size:20px;font-weight:700;"><?php echo esc_html( $atts['name'] ); ?></h2>
            <?php if ( $atts['bio'] ) : ?>
                <p style="margin:4px 0 0;font-size:14px;color:#ccc;"><?php echo esc_html( $atts['bio'] ); ?></p>
            <?php endif; ?>
        </div>

        <div class="ch-fans" style="text-align:right;">
            <span style="display:block;font-size:12px;color:#999;">粉丝数</span>
            <strong style="font-size:24px;font-weight:700;"><?php echo number_format_i18n( (int) $atts['fans'] ); ?></strong>
        </div>
    </div>

    <style>
        /* ① 让整块头像栏在所有屏宽下都全幅 */
        .ch-wrap{width:100%;max-width:none;}

        /* ② 悬停出现 ✎ 按钮 */
        .ch-header:hover .ch-change-avatar{opacity:1;}

        /* ③ 手机端改为纵向排布 */
        @media(max-width:600px){
            .ch-header{
                flex-direction:column;
                align-items:flex-start;
                padding:16px;
            }
            .ch-fans{margin-top:8px;width:100%;}
        }
    </style>
    <?php
    /* 用 ch-wrap 把输出包起来，实现全宽效果 */
    return '<div class="ch-wrap">'.ob_get_clean().'</div>';
}
add_shortcode( 'creator_header', 'ch_creator_header_shortcode' );

/* ======================================================
 * 4. [creator_navbar] 顶部导航栏
 * ==================================================== */
function ch_creator_navbar_shortcode( $atts ) {

    $atts = shortcode_atts( array(
        'links' => '首页:/,作品:/works,商品:/products,粉丝社区:/community',
        'bg'    => '#ffffff',   // 背景色
        'color' => '#000000',   // 文字色
    ), $atts, 'creator_navbar' );

    /* 解析 links 属性： 文字:URL,文字:URL */
    $items = array_filter( array_map( 'trim', explode( ',', $atts['links'] ) ) );
    $html_items = '';
    foreach ( $items as $item ) {
        [$text,$url] = array_map( 'trim', explode( ':', $item, 2 ) );
        $html_items .= '<a href="'. esc_url( $url ?: '#' ) .'" class="nav-link">'. esc_html( $text ) .'</a>';
    }

    ob_start(); ?>
    <nav class="creator-navbar" style="background:<?php echo esc_attr( $atts['bg'] ); ?>;
        color:<?php echo esc_attr( $atts['color'] ); ?>;">
        <button class="nav-burger" aria-label="Menu">☰</button>
        <div class="nav-list"><?php echo $html_items; ?></div>
    </nav>

    <style>
        .creator-navbar{
            position:fixed;
			top:0;
			left:50%;               /* 先定位到屏幕中心 */
			width:100vw;            /* 占满整个可视宽度 */
			transform:translateX(-50%); /* 再左移半宽，达到左右全贴边 */
			max-width:none;         /* 打破主题 max-width */
			z-index:9999;
        }
        .nav-burger{
            background:none;border:none;font-size:22px;cursor:pointer;
            padding:4px 8px;line-height:1;
            color:inherit;
        }
        .nav-list{display:flex;gap:12px;flex-wrap:wrap}
        .nav-link{
            text-decoration:none;font-size:14px;color:inherit;
            padding:4px 6px;border-radius:3px;transition:.2s;
        }
        .nav-link:hover{background:rgba(0,0,0,.06);}
        /* 给正文推一个高度，避免被导航遮挡 */
		/* --- 已登录时避让 WP Admin Bar (32px) --- */
		body.admin-bar .creator-navbar{ top:32px; }
		
		/* --- 根据导航高度推正文；管理员多推 32 像素 --- */
		body{ margin-top:48px; }
		body.admin-bar{ margin-top:80px; }    /* 48 + 32 */

        body{margin-top:48px;}
        @media(max-width:600px){
            .nav-list{display:none;}        /* 以后可做点击汉堡展开 */
        }
    </style>
    <?php
    return ob_get_clean();
}
add_shortcode( 'creator_navbar', 'ch_creator_navbar_shortcode' );


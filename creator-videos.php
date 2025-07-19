<?php
/**
 * Plugin Name:  Creator Links (Ultra Lite)
 * Description:  作者前端提交链接，站点展示纯链接列表/按钮，无缩略图、零外部请求。
 * Version:      0.1
 * Author:       Ziyang Song
 * License:      GPL-2.0-or-later
 * Text Domain:  creator-links
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ======================================================
 * 1. 表单短代码 [video_submit_form]
 * ==================================================== */
function cl_submit_form_shortcode() {

	if ( ! is_user_logged_in() ) {
		return '<p>' . esc_html__( '请先登录后再添加链接。', 'creator-links' ) . '</p>';
	}

	ob_start(); ?>
	<form class="cl-form" method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
		<?php wp_nonce_field( 'cl_add_link', 'cl_nonce' ); ?>
		<input type="hidden" name="action" value="cl_add_link">

		<p>
			<label>链接地址<br>
				<input type="url" name="link_url" required placeholder="https://example.com/..." style="width:100%;padding:6px;">
			</label>
		</p>

		<?php submit_button( '添加到我的作品集', 'primary', 'cl_submit', false ); ?>
	</form>

	<style>.cl-form{max-width:420px;margin:20px 0;border:1px solid #444;padding:16px;border-radius:6px}</style>
	<?php
	return ob_get_clean();
}
add_shortcode( 'video_submit_form', 'cl_submit_form_shortcode' );

/* ---------- Ajax 处理 ---------- */
add_action( 'wp_ajax_cl_add_link', function () {

	check_ajax_referer( 'cl_add_link', 'cl_nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( 'not_login' );
	}

	$url = isset( $_POST['link_url'] ) ? esc_url_raw( $_POST['link_url'] ) : '';
	if ( empty( $url ) ) {
		wp_send_json_error( 'empty_url' );
	}

	$user_id = get_current_user_id();
	$list    = get_user_meta( $user_id, 'cv_links', true );
	$list    = $list ? json_decode( $list, true ) : array();

	$list[]  = $url;
	update_user_meta( $user_id, 'cv_links', wp_json_encode( $list ) );

	wp_send_json_success();
} );

/* ======================================================
 * 2. 展示短代码 [creator_links]
 * ==================================================== */
function cl_links_shortcode( $atts ) {

	$a = shortcode_atts( array(
		'type' => 'list',   // list | buttons
		'blank'=> 1,
	), $atts, 'creator_links' );

	$user_id = get_current_user_id();
	$list    = get_user_meta( $user_id, 'cv_links', true );
	$list    = $list ? json_decode( $list, true ) : array();

	if ( ! $list ) {
		return '<p style="color:#777;">还没有添加任何链接。</p>';
	}

	$blank = $a['blank'] ? ' target="_blank" rel="noopener"' : '';

	if ( $a['type'] === 'buttons' ) {
		$out = '<div class="cl-buttons">';
		foreach ( $list as $url ) {
			$out .= '<a class="cl-btn" href="'. esc_url( $url ) .'"'.$blank.'>前往</a>';
		}
		$out .= '</div>
		<style>
			.cl-buttons{display:flex;gap:12px;flex-wrap:wrap}
			.cl-btn{padding:8px 16px;background:#0073aa;color:#fff;border-radius:4px;text-decoration:none}
			.cl-btn:hover{background:#006198}
		</style>';
		return $out;
	}

	/* 默认 ul 列表 */
	$out = '<ul class="cl-list">';
	foreach ( $list as $url ) {
		$out .= '<li><a href="'. esc_url( $url ) .'"'.$blank.'>'. esc_html( $url ) .'</a></li>';
	}
	$out .= '</ul>';
	return $out;
}
add_shortcode( 'creator_links', 'cl_links_shortcode' );

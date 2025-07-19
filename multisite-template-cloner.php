<?php
/**
 * Plugin Name: Multisite Template Cloner
 * Description: 在 Multisite 下，一键克隆主站上一页（up网站首页）及指定插件设置到新子站。
 * Version:     1.1
 * Author:      Ziyang Song
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ——— 配置 ———
// 模板所在站点 ID，主站通常是 1
if ( ! defined( 'STC_TEMPLATE_SITE_ID' ) ) {
	define( 'STC_TEMPLATE_SITE_ID', 1 );
}
// 模板页面的 slug（不带斜杠）
if ( ! defined( 'STC_TEMPLATE_PAGE_SLUG' ) ) {
	define( 'STC_TEMPLATE_PAGE_SLUG', 'up网站首页' );
}
// 要复制的插件 option keys
if ( ! defined( 'STC_OPTION_KEYS' ) ) {
	define( 'STC_OPTION_KEYS', serialize( [
		'myplugin_form_fields',
		'myplugin_color_scheme',
		// 如有更多请继续添加
	] ) );
}

// ——— 前端脚本加载 ———
function stc_enqueue_assets() {
	if ( ! is_user_logged_in() ) {
		return;
	}
	wp_enqueue_script( 'jquery' );
	wp_enqueue_script(
		'stc-main',
		plugin_dir_url( __FILE__ ) . 'js/stc-main.js',
		[ 'jquery' ],
		'1.0',
		true
	);
	wp_localize_script( 'stc-main', 'stc_ajax', [
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'stc_clone_nonce' ),
	] );
}
add_action( 'wp_enqueue_scripts', 'stc_enqueue_assets' );

// ——— 短代码：渲染按钮 ———
function stc_template_cloner_shortcode() {
	if ( ! is_user_logged_in() ) {
		return '<p>请先 <a href="' . esc_url( wp_login_url() ) . '">登录</a>，然后再创建站点。</p>';
	}

	return '<button id="stc-create-site">Create My Site</button>'
	     . '<div id="stc-result" style="margin-top:1em;"></div>';
}
add_shortcode( 'site_template_cloner', 'stc_template_cloner_shortcode' );

// ——— Ajax 处理：新建空站并克隆 ———
function stc_ajax_clone_template() {
	check_ajax_referer( 'stc_clone_nonce', 'nonce' );

	if ( ! is_user_logged_in() || ! current_user_can( 'manage_sites' ) ) {
		wp_send_json_error( '权限不足，无法创建站点。' );
	}

	$user      = wp_get_current_user();
	$user_slug = sanitize_title_with_dashes( $user->user_nicename );
	$domain    = parse_url( network_home_url(), PHP_URL_HOST );
	$path      = '/' . $user_slug . '/';
	$title     = $user->display_name . ' 的站点';

	if ( domain_exists( $domain, $path ) ) {
		wp_send_json_error( '您已创建过相同地址的站点：' . $path );
	}

	// 1) 新建空子站
	$new_blog_id = wpmu_create_blog( $domain, $path, $title, $user->ID );
	if ( is_wp_error( $new_blog_id ) ) {
		wp_send_json_error( $new_blog_id->get_error_message() );
	}

	// 2) 克隆模板那一页
	$ok = clone_template_page( $new_blog_id );
	if ( ! $ok ) {
		wp_send_json_error( '克隆模板页面失败，请联系管理员。' );
	}

	// 3) 克隆插件设置
	$option_keys = maybe_unserialize( STC_OPTION_KEYS );
	clone_plugin_options( STC_TEMPLATE_SITE_ID, $new_blog_id, $option_keys );

	// 返回新站首页链接
	$site_url = network_home_url( $path );
	wp_send_json_success( [ 'url' => $site_url ] );
}
add_action( 'wp_ajax_stc_clone_template', 'stc_ajax_clone_template' );

// ——— 辅助函数：只克隆一个页面及其 meta ———
function clone_template_page( $new_blog_id ) {
	// 从模板站拉取页面对象
	switch_to_blog( STC_TEMPLATE_SITE_ID );
	$page = get_page_by_path( STC_TEMPLATE_PAGE_SLUG );
	restore_current_blog();

	if ( ! $page ) {
		error_log( "找不到模板页面：" . STC_TEMPLATE_PAGE_SLUG );
		return false;
	}

	// 在新站插入这页
	switch_to_blog( $new_blog_id );
	$data = [
		'post_author'       => $page->post_author,
		'post_date'         => $page->post_date,
		'post_date_gmt'     => $page->post_date_gmt,
		'post_content'      => $page->post_content,
		'post_title'        => $page->post_title,
		'post_excerpt'      => $page->post_excerpt,
		'post_status'       => $page->post_status,
		'post_name'         => $page->post_name,
		'post_parent'       => $page->post_parent,
		'menu_order'        => $page->menu_order,
		'post_type'         => 'page',
		'comment_status'    => $page->comment_status,
		'ping_status'       => $page->ping_status,
		'post_password'     => $page->post_password,
		'post_modified'     => $page->post_modified,
		'post_modified_gmt' => $page->post_modified_gmt,
	];
	$new_id = wp_insert_post( wp_slash( $data ) );
	if ( is_wp_error( $new_id ) ) {
		error_log( "插入模板页面失败：" . $new_id->get_error_message() );
		restore_current_blog();
		return false;
	}

	// 复制该页面的所有 postmeta
	global $wpdb;
	$meta_table = $wpdb->base_prefix . 'postmeta';
	$rows       = $wpdb->get_results( $wpdb->prepare(
		"SELECT meta_key,meta_value FROM {$meta_table} WHERE post_id=%d",
		$page->ID
	) );
	foreach ( $rows as $m ) {
		add_post_meta( $new_id, $m->meta_key, maybe_unserialize( $m->meta_value ) );
	}
	restore_current_blog();

	return true;
}

// ——— 辅助函数：克隆插件设置 ———
function clone_plugin_options( $template_id, $new_blog_id, array $option_keys ) {
	foreach ( $option_keys as $opt ) {
		$val = get_blog_option( $template_id, $opt );
		update_blog_option( $new_blog_id, $opt, $val );
	}
}

<?php
/**
 * Plugin Name:  Creator Gallery (Super Lite)
 * Description:  用同一张默认封面展示一组链接，短代码一次搞定。
 * Version:      0.1
 * Author:       Ziyang Song
 * License:      GPL-2.0-or-later
 * Text Domain:  creator-gallery
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* 路径常量 */
define( 'CG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CG_DEFAULT_COVER', CG_PLUGIN_URL . 'default-cover.png' );

/**
 * [creator_gallery]  —— 纯默认封面的链接卡片
 *
 * 用法示例：
 * [creator_gallery
 *    items="https://youtu.be/dQw4w9WgXcQ,https://www.bilibili.com/video/BV1LS421N7hK"
 *    cols="3"
 *    blank="1"]
 *
 * 参数：
 * - items  逗号分隔的 URL 列表（必填）
 * - cols   每行列数 1~6（默认 4）
 * - blank  1=新标签打开，0=当前页（默认 1）
 */
function cg_gallery_shortcode( $atts ) {

	$a = shortcode_atts( array(
		'items' => '',
		'cols'  => 4,
		'blank' => 1,
	), $atts, 'creator_gallery' );

	$links = array_filter( array_map( 'trim', explode( ',', $a['items'] ) ) );
	if ( empty( $links ) ) {
		return '<p style="color:#999;">请在 items 参数里填写链接，用逗号分隔。</p>';
	}

	$cols  = max( 1, min( 6, intval( $a['cols'] ) ) );
	$blank = $a['blank'] ? ' target="_blank" rel="noopener"' : '';

	$cards = '';
	foreach ( $links as $url ) {
		$url_esc = esc_url( $url );
		$cards  .= "<a class='cg-card' href='{$url_esc}'{$blank}>
						<img src='". esc_url( CG_DEFAULT_COVER ) ."' alt='cover' loading='lazy'>
						<span class='cg-link'>". esc_html( $url ) ."</span>
					</a>";
	}

	/* inline 样式 */
	$style = "
	<style>
	.cg-grid{
    display:grid;
    gap:16px;
    /* 每张卡片最小 280px，再大的宽度自动平分 */
    grid-template-columns:repeat(auto-fill,minmax(280px,1fr));
    width:100%;          /* 让它占满父容器 */
    max-width:none;      /* 万一主题给 .entry-content 设了 max-width，也强行打破 */
	}

	.cg-card{display:flex;flex-direction:column;text-decoration:none;border-radius:6px;overflow:hidden;background:#202020}
	.cg-card img{width:100%;height:auto;display:block}
	.cg-link{padding:8px 10px;font-size:14px;color:#fff;word-break:break-all;background:#2b2b2b}
	.cg-card:hover .cg-link{background:#0073aa}
	@media(max-width:768px){.cg-grid{grid-template-columns:repeat(2,1fr)}}
	</style>";

	return $style . "<div class='cg-grid'>{$cards}</div>";
}
add_shortcode( 'creator_gallery', 'cg_gallery_shortcode' );

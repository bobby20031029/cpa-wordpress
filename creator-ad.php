<?php
/**
 * Plugin Name:  Creator Ad Block (Size by Class)
 * Description:  横幅广告短代码；size 参数 small/medium/large 控制宽高，移动端自适应。
 * Version:      1.4
 * Author:       Ziyang Song
 * License:      GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function cabanner_sc( $atts ){

    $atts = shortcode_atts( array(
        'ad'      => '',
        'creator' => '',
        'img'     => '',
        'href'    => '',
        'alt'     => '',
        'size'    => 'large',   // ⚠ 新增：small | medium | large
        'blank'   => 1
    ), $atts, 'creator_ad_banner' );

    $ad_id      = (int) $atts['ad'];
    $creator_id = (int) $atts['creator'];
    if ( ! $ad_id || ! $creator_id ) return '';

    /* ——素材—— */
    $img    = $atts['img'] ?: get_the_post_thumbnail_url( $ad_id, 'full' );
    $target = $atts['href'] ?: get_post_meta( $ad_id, '_cas_target', true );
    if ( ! $img || ! $target ) return '';

    $go    = esc_url( site_url( "/go/{$ad_id}?ref={$creator_id}" ) );
    $blank = $atts['blank'] ? ' target="_blank" rel="noopener noreferrer nofollow"' : '';

    /* ——合法尺寸—— */
    $size = in_array( $atts['size'], array( 'small','medium','large' ), true )
          ? $atts['size'] : 'large';

    ob_start(); ?>
    <div class="creator-ad size-<?= esc_attr( $size ); ?>">
        <a href="<?= $go; ?>" <?= $blank; ?>>
            <img src="<?= esc_url( $img ); ?>" alt="<?= esc_attr( $atts['alt'] ); ?>">
        </a>
        <span class="creator-ad-badge">推广</span>
    </div>

    <?php /* ——全局一次性样式—— */ ?>
    <?php static $printed = false; if ( ! $printed ) : $printed = true; ?>
    <style>
        /* 基础外观 */
        .creator-ad{position:relative;margin:32px auto;overflow:hidden;transition:.2s;}
        .creator-ad img{display:block;width:100%;height:100%;object-fit:cover;border-radius:10px;box-shadow:0 4px 10px rgba(0,0,0,.35);}
        .creator-ad-badge{position:absolute;top:8px;left:8px;padding:0 6px;font-size:12px;background:#ff5c5c;color:#fff;border-radius:3px;}
        /* ⚠ 三档尺寸 */
        .creator-ad.size-small  {width:500px;height:120px;max-width:500px;}
        .creator-ad.size-medium {width:700px;height:160px;max-width:700px;}
        .creator-ad.size-large  {width:900px;height:200px;max-width:900px;}
        /* 平板 / 手机自适应 */
        @media(max-width:991px){ .creator-ad{max-width:90vw!important;width:90vw!important;} }
        @media(max-width:600px){ .creator-ad{max-width:calc(100vw - 24px)!important;width:calc(100vw - 24px)!important;} }
    </style>
    <?php endif;

    return ob_get_clean();
}
add_shortcode( 'creator_ad_banner', 'cabanner_sc' );

<?php
/**
 * Plugin Name:       Operator Voting & Tier Display
 * Plugin URI:        https://worthbuy.com.au
 * Description:       提供干员投票和根据投票结果以 Tier 形式展示的短代码插件。
 * Version:           1.0.0
 * Author:            Ziyang Song
 * Author URI:        mailto:nickysong1029@gmail.com
 * License:           GPLv2 or later
 * Text Domain:       operator-voting-tier-display
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 1. 注册自定义文章类型：operator
 */
add_action( 'init', 'ovtd_register_operator_cpt' );
function ovtd_register_operator_cpt() {
    $labels = array(
        'name'               => '干员',
        'singular_name'      => '干员',
        'menu_name'          => '干员管理',
        'add_new_item'       => '添加新干员',
        'edit_item'          => '编辑干员',
        'new_item'           => '新干员',
        'view_item'          => '查看干员',
    );
    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'has_archive'        => false,
        'show_in_rest'       => true,
        'supports'           => array( 'title', 'thumbnail', 'custom-fields' ),
        'menu_position'      => 20,
        'menu_icon'          => 'dashicons-groups',
    );
    register_post_type( 'operator', $args );
}

/**
 * 2. 注册 REST API 路由：/wp-json/ovtd/v1/vote
 */
add_action( 'rest_api_init', 'ovtd_register_rest_routes' );
function ovtd_register_rest_routes() {
    register_rest_route( 'ovtd/v1', '/vote', array(
        'methods'             => 'POST',
        'callback'            => 'ovtd_handle_vote',
        'permission_callback' => '__return_true',
    ));
}

/**
 * 3. 处理投票请求
 */
function ovtd_handle_vote( WP_REST_Request $req ) {
    $id   = (int) $req->get_param( 'id' );
    $type = $req->get_param( 'type' );

    if ( $type !== 'operator' ) {
        return new WP_Error( 'invalid_type', '类型错误', array( 'status' => 400 ) );
    }
    if ( ! get_post( $id ) ) {
        return new WP_Error( 'invalid_id', '无效的文章 ID', array( 'status' => 404 ) );
    }

    $meta_key = 'op_votes_count';
    $votes    = (int) get_post_meta( $id, $meta_key, true );
    $votes++;
    update_post_meta( $id, $meta_key, $votes );

    return array(
        'thisVotes'  => $votes,
        'totalVotes' => $votes,
    );
}

/**
 * 4. 注册并加载前端脚本与样式
 */
add_action( 'wp_enqueue_scripts', 'ovtd_enqueue_assets' );
function ovtd_enqueue_assets() {
    // CSS
    wp_register_style(
        'ovtd-style',
        plugins_url( 'assets/css/style.css', __FILE__ ),
        array(),
        '1.0.0'
    );
    wp_enqueue_style( 'ovtd-style' );

    // JS
    wp_register_script(
        'ovtd-script',
        plugins_url( 'assets/js/vote.js', __FILE__ ),
        array( 'jquery' ),
        '1.0.0',
        true
    );
    wp_localize_script( 'ovtd-script', 'ovtdData', array(
        'rest_url' => esc_url_raw( rest_url( 'ovtd/v1/vote' ) )
    ));
    wp_enqueue_script( 'ovtd-script' );
}

/**
 * 5. 短代码 [operator_tiers]：根据投票数分段渲染 Tier 排行
 */
add_shortcode( 'operator_tiers', 'ovtd_render_operator_tiers' );
function ovtd_render_operator_tiers( $atts ) {
    // 拉取所有 operator，按投票数倒序
    $ops = get_posts( array(
        'post_type'      => 'operator',
        'posts_per_page' => -1,
        'meta_key'       => 'op_votes_count',
        'orderby'        => 'meta_value_num',
        'order'          => 'DESC',
    ));

    // 定义各 Tier 行要显示的干员数量
    $tiers = array(
        '巅峰'  => 1,
        'T0'    => 6,
        'T0.5'  => 8,
        'T1'    => 12,
        'T1.5'  => 12,
        'T2'    => 10,
        'T2.5'  => 8,
        'T3'    => 8,
        'T3.5'  => 6,
        'T4'    => 6,
        'T5'    => 6,
    );

    $html   = '<div class="ovtd-tier-list">';
    $offset = 0;

    foreach ( $tiers as $label => $count ) {
        $slice = array_slice( $ops, $offset, $count );
        if ( empty( $slice ) ) break;
        $offset += $count;

        $cls = sanitize_title( $label );
        $html .= "<div class='ovtd-tier-row ovtd-tier-{$cls}'>";
        $html .=   "<div class='ovtd-tier-label'>{$label}</div>";
        $html .=   "<div class='ovtd-ops'>";
        foreach ( $slice as $post ) {
            $thumb = get_the_post_thumbnail_url( $post->ID, 'thumbnail' );
            if ( ! $thumb ) {
                $thumb = plugins_url( 'assets/img/default.png', __FILE__ );
            }
            $title = esc_attr( $post->post_title );
            $html .= "<div class='ovtd-op-card'>";
            $html .=   "<img src='{$thumb}' alt='{$title}' title='{$title}'>";
            $html .= "</div>";
        }
        $html .=   "</div>";
        $html .= "</div>";
    }

    $html .= '</div>';
    return $html;
}

<?php
/**
 * Plugin Name:  Fan Suggestions (REST + Shortcode)
 * Description:  粉丝建议 + 投票条形图，可用 width/height 参数控制整体大小。
 * Version:      1.2
 * Author:       Ziyang Song
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ---------- 1. CPT fan_suggestion ---------- */
add_action( 'init', function () {
    register_post_type( 'fan_suggestion', array(
        'labels'    => array( 'name' => '粉丝建议' ),
        'public'    => false,
        'show_ui'   => true,
        'menu_icon' => 'dashicons-megaphone',
        'supports'  => array( 'title', 'editor' ),
    ) );
} );

/* ---------- 2. REST API ---------- */
add_action( 'rest_api_init', function () {

    /* POST /suggest */
    register_rest_route( 'fs/v1', '/suggest', array(
        'methods'  => 'POST',
        'callback' => function ( WP_REST_Request $req ) {

            $content = sanitize_text_field( $req['content'] ?? '' );
            if ( ! $content ) return new WP_Error( 'empty', '内容不能为空', [ 'status'=>400 ] );

            $nickname = sanitize_text_field( $req['nickname'] ?? '' );
            $id = wp_insert_post( array(
                'post_type'   => 'fan_suggestion',
                'post_status' => 'publish',
                'post_title'  => $nickname ? "{$nickname} 的建议" : '匿名建议',
                'post_content'=> $content,
            ), true );

            if ( is_wp_error( $id ) ) return $id;

            update_post_meta( $id, '_votes', 0 );
            return array( 'id' => $id );
        },
        'permission_callback' => '__return_true',
    ) );

    /* GET /suggest */
    register_rest_route( 'fs/v1', '/suggest', array(
        'methods'  => 'GET',
        'callback' => function () {

            $posts = get_posts( array(
                'post_type'      => 'fan_suggestion',
                'posts_per_page' => -1,
                'orderby'        => 'meta_value_num',
                'meta_key'       => '_votes',
                'order'          => 'DESC',
            ) );

            return array_map( function( $p ){
                return array(
                    'id'      => $p->ID,
                    'content' => array( 'rendered' => $p->post_content ),
                    'meta'    => array( '_votes' => (int) get_post_meta( $p->ID, '_votes', true ) ),
                );
            }, $posts );
        },
        'permission_callback' => '__return_true',
    ) );

    /* POST /vote/{id} */
    register_rest_route( 'fs/v1', '/vote/(?P<id>\d+)', array(
        'methods'  => 'POST',
        'callback' => function ( WP_REST_Request $req ) {

            $id = (int) $req['id'];
            if ( get_post_type( $id ) !== 'fan_suggestion' )
                return new WP_Error( 'invalid_id', '非法 ID', [ 'status'=>404 ] );

            if ( isset( $_COOKIE[ 'fs_voted_'.$id ] ) )
                return new WP_Error( 'voted', '您已投票', [ 'status'=>409 ] );

            $votes = (int) get_post_meta( $id, '_votes', true ) + 1;
            update_post_meta( $id, '_votes', $votes );
            setcookie( 'fs_voted_'.$id, 1, time()+YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );

            return array( 'votes' => $votes );
        },
        'permission_callback' => '__return_true',
    ) );
} );

/* ---------- 3. 仅在含短代码页面加载前端资源 ---------- */
add_action( 'wp_enqueue_scripts', function () {
    if ( ! is_singular() ) return;
    global $post;
    if ( has_shortcode( $post->post_content, 'fan_suggestions' ) ) {

        wp_register_script(
            'fs-script',
            plugins_url( 'fs-script.js', __FILE__ ),
            array(), '1.2', true
        );
        wp_localize_script( 'fs-script', 'FS_DATA', array(
            'root'  => esc_url_raw( rest_url( 'fs/v1' ) ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
        ) );
        wp_enqueue_script( 'fs-script' );

        wp_add_inline_style( 'wp-block-library', <<<CSS
.fs-wall{display:flex;flex-direction:column;gap:12px;margin:24px auto;width:100%;}
.fs-item{cursor:pointer}
.fs-bar{background:#444;color:#eee;border-radius:20px;padding:6px 14px;transition:width .3s;white-space:pre-line}
.fs-item:hover .fs-bar{background:#555}
.fs-count{float:right}
#fs-add{position:fixed;right:24px;bottom:24px;width:52px;height:52px;border-radius:50%;background:#0073aa;color:#fff;font-size:30px;border:none;cursor:pointer}
@media(max-width:600px){
    .fs-wall{max-width:calc(100vw - 24px);}
}
CSS );
    }
} );

/* ---------- 4. 短代码 [fan_suggestions width="800" height=""] ---------- */
function fs_shortcode( $atts = array() ) {

    /* 新增：宽高可调 */
    $atts = shortcode_atts( array(
        'width'  => '',      // 800 | 80% | 90vw
        'height' => ''       // 300 | 25vh
    ), $atts, 'fan_suggestions' );

    $style = '';
    if ( $atts['width'] ){
        $w = is_numeric($atts['width']) ? intval($atts['width']).'px' : esc_attr($atts['width']);
        $style .= "max-width:{$w};width:{$w};";
    }
    if ( $atts['height'] ){
        $h = is_numeric($atts['height']) ? intval($atts['height']).'px' : esc_attr($atts['height']);
        $style .= "height:{$h};overflow-y:auto;";
    }

    ob_start(); ?>
<div class="fs-wall" style="<?= $style; ?>"><!-- JS 渲染 --></div>

<button id="fs-add">＋</button>

<div id="fs-popup" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);
     align-items:center;justify-content:center;z-index:9999;">
  <div style="background:#fff;border-radius:8px;padding:24px;max-width:480px;width:90%;position:relative;">
      <button id="fs-close" style="position:absolute;top:10px;right:10px;border:none;font-size:20px;cursor:pointer;">×</button>

      <form id="fs-form">
          <p><label>昵称（可留空）<br><input name="nickname" style="width:100%;padding:6px;"></label></p>
          <p><label>你的建议 <span style="color:red">*</span><br>
              <textarea name="content" rows="4" required style="width:100%;padding:6px;"></textarea></label></p>
          <button type="submit" class="button button-primary">提交</button>
      </form>
  </div>
</div>
<?php
    return ob_get_clean();
}
add_shortcode( 'fan_suggestions', 'fs_shortcode' );

<?php
/**
 * Plugin Name: Creator Affiliate Stats
 * Description: 自媒体横幅推广：点击跳转、订单回传、后台统计。
 * Version:     0.9
 * Author:      Ziyang Song
 */

if ( !defined( 'ABSPATH' ) ) exit;

/* ------------------------------------------------------------------
 * 0. 激活时建两张表：clicks & orders
 * ----------------------------------------------------------------*/
register_activation_hook( __FILE__, function () {
	global $wpdb;
	$charset = $wpdb->get_charset_collate();

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$sql_clicks = "CREATE TABLE {$wpdb->prefix}cas_clicks (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		ad_id BIGINT UNSIGNED NOT NULL,
		creator_id BIGINT UNSIGNED NOT NULL,
		ip VARCHAR(45) DEFAULT '',
		clicked_at DATETIME NOT NULL,
		PRIMARY KEY (id),
		KEY idx_ad (ad_id),
		KEY idx_creator (creator_id)
	) $charset;";

	$sql_orders = "CREATE TABLE {$wpdb->prefix}cas_orders (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		order_sn VARCHAR(60) NOT NULL,
		ad_id BIGINT UNSIGNED NOT NULL,
		creator_id BIGINT UNSIGNED NOT NULL,
		amount DECIMAL(10,2) NOT NULL DEFAULT 0,
		commission DECIMAL(10,2) NOT NULL DEFAULT 0,
		recorded_at DATETIME NOT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY u_sn (order_sn),
		KEY idx_creator (creator_id)
	) $charset;";

	dbDelta( $sql_clicks );
	dbDelta( $sql_orders );
} );

/* ------------------------------------------------------------------
 * 1. CPT creator_ad   (素材)
 * ----------------------------------------------------------------*/
add_action( 'init', function () {
	register_post_type( 'creator_ad', array(
		'label'       => 'Creator Ads',
		'public'      => false,
		'show_ui'     => true,
		'menu_icon'   => 'dashicons-megaphone',
		'supports'    => array( 'title', 'thumbnail' ),
	) );
} );

/* 保存目标链接 & 佣金率 */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'cas_ad_meta', '广告设置', function ( $post ) {
		$target = get_post_meta( $post->ID, '_cas_target', true );
		$rate   = get_post_meta( $post->ID, '_cas_rate',   true ) ?: 0.10;
		?>
		<p><label>目标链接（商品页）<br>
			<input type="url" name="cas_target" value="<?= esc_attr( $target ); ?>" style="width:100%;">
		</label></p>
		<p><label>佣金率（小数，如 0.12 = 12%）<br>
			<input type="number" step="0.01" min="0" name="cas_rate" value="<?= esc_attr( $rate ); ?>">
		</label></p>
		<?php
	}, 'creator_ad', 'normal', 'high' );
} );

add_action( 'save_post_creator_ad', function ( $post_id ) {
	if ( isset( $_POST['cas_target'] ) )
		update_post_meta( $post_id, '_cas_target', esc_url_raw( $_POST['cas_target'] ) );
	if ( isset( $_POST['cas_rate'] ) )
		update_post_meta( $post_id, '_cas_rate', (float) $_POST['cas_rate'] );
} );

/* ------------------------------------------------------------------
 * 2. Shortcode  [creator_ad_banner]
 * ----------------------------------------------------------------*/
function cas_banner_sc( $atts ) {
	$a = shortcode_atts(
		array( 'ad' => '', 'creator' => '' ),
		$atts, 'creator_ad_banner'
	);

	$ad_id = (int) $a['ad'];
	$creator_id = (int) $a['creator'];
	if ( ! $ad_id || ! $creator_id ) return '';

	$img = get_the_post_thumbnail_url( $ad_id, 'full' );
	$target = site_url( "/go/$ad_id?ref=$creator_id" );

	return $img ? "<a href='$target' target='_blank' rel='nofollow'>
	     <img src='$img' alt='' style='width:100%;border-radius:10px;box-shadow:0 4px 10px rgba(0,0,0,.35);'></a>"
	     : '';
}
add_shortcode( 'creator_ad_banner', 'cas_banner_sc' );

/* ------------------------------------------------------------------
 * 3. 路由 /go/{ad_id}?ref={creator_id}
 * ----------------------------------------------------------------*/
add_action( 'init', function () {
	add_rewrite_rule( '^go/([0-9]+)/?', 'index.php?cas_go=$matches[1]', 'top' );
} );
add_filter( 'query_vars', fn( $q ) => array_merge( $q, array( 'cas_go' ) ) );

add_action( 'template_redirect', function () {
	$ad_id = get_query_var( 'cas_go' );
	if ( ! $ad_id ) return;

	$creator = (int) $_GET['ref'] ?? 0;
	$target  = get_post_meta( $ad_id, '_cas_target', true );

	// 记录点击
	global $wpdb;
	$wpdb->insert( $wpdb->prefix . 'cas_clicks', array(
		'ad_id'      => $ad_id,
		'creator_id' => $creator,
		'ip'         => $_SERVER['REMOTE_ADDR'],
		'clicked_at' => current_time( 'mysql' ),
	), array( '%d','%d','%s','%s' ) );

	// 跳转
	wp_redirect( $target ?: home_url() );
	exit;
} );

/* ------------------------------------------------------------------
 * 4. REST 订单回传  POST /wp-json/creator/v1/order
 * ----------------------------------------------------------------*/
add_action( 'rest_api_init', function () {
	register_rest_route( 'creator/v1', '/order', array(
		'methods'  => 'POST',
		'callback' => function ( WP_REST_Request $r ) {

			$data = $r->get_json_params();
			$sn   = sanitize_text_field( $data['sn'] ?? '' );
			$ad   = (int) ( $data['ad'] ?? 0 );
			$ref  = (int) ( $data['ref'] ?? 0 );
			$amt  = (float) ( $data['amount'] ?? 0 );

			if ( ! $sn || ! $ad || ! $ref || ! $amt )
				return new WP_Error( 'bad_param', '缺少参数', array( 'status'=>400 ) );

			$rate = (float) get_post_meta( $ad, '_cas_rate', true ); // 默认已存
			$comm = round( $amt * $rate, 2 );

			global $wpdb;
			$wpdb->insert( $wpdb->prefix . 'cas_orders', array(
				'order_sn'   => $sn,
				'ad_id'      => $ad,
				'creator_id' => $ref,
				'amount'     => $amt,
				'commission' => $comm,
				'recorded_at'=> current_time( 'mysql' ),
			), array( '%s','%d','%d','%f','%f','%s' ) );

			return array( 'stored'=>1 );
		},
		'permission_callback' => '__return_true',
	) );
} );

/* ------------------------------------------------------------------
 * 5. 后台统计页
 * ----------------------------------------------------------------*/
add_action( 'admin_menu', function () {
	add_submenu_page(
		'edit.php?post_type=creator_ad',
		'Creator 统计',
		'Creator 统计',
		'manage_options',
		'creator-stat',
		'cas_stat_page'
	);
} );

function cas_stat_page() {
	global $wpdb;
	$clicks_tbl  = $wpdb->prefix . 'cas_clicks';
	$orders_tbl  = $wpdb->prefix . 'cas_orders';

	// 聚合
	$sql = "SELECT c.creator_id,
	        COUNT(DISTINCT c.id)        AS click_cnt,
	        COUNT(o.id)                 AS order_cnt,
	        COALESCE(SUM(o.amount),0)   AS sum_amount,
	        COALESCE(SUM(o.commission),0) AS sum_comm
	        FROM $clicks_tbl c
	        LEFT JOIN $orders_tbl o ON o.creator_id=c.creator_id
	        GROUP BY c.creator_id
	        ORDER BY sum_comm DESC";

	$list = $wpdb->get_results( $sql );

	echo '<div class="wrap"><h1 class="wp-heading-inline">Creator 统计</h1><table class="widefat"><thead>
	      <tr><th>Creator ID</th><th>点击</th><th>订单</th><th>成交额</th><th>佣金</th></tr></thead><tbody>';

	foreach ( $list as $row ) {
		printf('<tr><td>%d</td><td>%d</td><td>%d</td><td>%.2f</td><td>%.2f</td></tr>',
			$row->creator_id, $row->click_cnt, $row->order_cnt, $row->sum_amount, $row->sum_comm );
	}

	echo '</tbody></table></div>';
}

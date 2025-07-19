<?php
/**
 * Plugin Name: Site Template Buttons
 * Description: 短代码输出可点击的个人网站模板按钮（纯前端 UI）
 * Version:     0.1
 * Author:      Ziyang Song
 * Text Domain: site-template-buttons
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 需要展示的模板清单
 * slug => 显示名称
 */
function stb_get_templates() {
	return array(
		'up-home'   => 'UP 主主页',
		'portfolio' => '作品集',
		'resume'    => '个人简历',
	);
}

/**
 * [site_template_buttons] —— 输出按钮组
 */
function stb_buttons_shortcode() {

	$tpls = stb_get_templates();
	if ( empty( $tpls ) ) {
		return '<p>' . esc_html__( '暂无模板。', 'site-template-buttons' ) . '</p>';
	}

	ob_start(); ?>
	<div class="stb-button-wrap">
		<?php foreach ( $tpls as $slug => $name ) : ?>
			<a href="#"
			   class="stb-btn"
			   data-template="<?php echo esc_attr( $slug ); ?>">
				<?php echo esc_html( $name ); ?>
			</a>
		<?php endforeach; ?>
	</div>

	<style>
		.stb-button-wrap{display:flex;gap:12px;flex-wrap:wrap;margin:16px 0}
		.stb-btn{
			display:inline-block;padding:10px 20px;border-radius:4px;
			background:#0073aa;color:#fff;text-decoration:none;font-weight:600;
			transition:.2s}
		.stb-btn:hover{background:#005d8c}
	</style>
	<?php
	return ob_get_clean();
}
add_shortcode( 'site_template_buttons', 'stb_buttons_shortcode' );

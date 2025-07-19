<?php
/**
 * Plugin Name: WooCommerce Simple Group Buy (Enhanced)
 * Description: 在商品页面“加入购物车”按钮下方支持创建／加入／退出／重置团购，可在后台设置拼团人数和折扣比例，带拼团倒计时（24 小时格式：时分秒）。
 * Version:     1.12
 * Author:      Your Name
 * Text Domain: wc-simple-groupbuy
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Simple_Group_Buy {

    // 拼团有效期：1440 分钟 = 24 小时
    const DURATION = 1440;

    public function __construct() {
        register_activation_hook( __FILE__, [ $this, 'create_table' ] );

        // 前端显示
        add_action( 'woocommerce_after_add_to_cart_button', [ $this, 'output_groupbuy_ui' ], 10 );

        // AJAX 接口
        add_action( 'wp_ajax_create_groupbuy',      [ $this, 'ajax_create_groupbuy' ] );
        add_action( 'wp_ajax_join_groupbuy',        [ $this, 'ajax_join_groupbuy' ] );
        add_action( 'wp_ajax_cancel_groupbuy',      [ $this, 'ajax_cancel_groupbuy' ] );
        add_action( 'wp_ajax_reset_groupbuy',       [ $this, 'ajax_reset_groupbuy' ] );
        add_action( 'wp_ajax_nopriv_join_groupbuy', [ $this, 'ajax_join_groupbuy' ] );

        // 计算折扣
        add_action( 'woocommerce_before_calculate_totals', [ $this, 'apply_groupbuy_discount' ], 20 );

        // 后台设置页面
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    /** 创建拼团记录表 */
    public function create_table() {
        global $wpdb;
        $table   = $wpdb->prefix . 'wc_groupbuys';
        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE IF NOT EXISTS {$table} (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            user_id    BIGINT UNSIGNED NOT NULL,
            joined_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            KEY product_id (product_id),
            KEY user_id    (user_id)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /** 添加后台“拼团设置”子菜单 */
    public function add_settings_page() {
        add_submenu_page(
            'woocommerce',
            '拼团设置',
            '拼团设置',
            'manage_woocommerce',
            'wc-groupbuy-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    /** 注册设置项 */
    public function register_settings() {
        register_setting( 'wc_groupbuy_settings', 'wc_groupbuy_group_size', [
            'type' => 'integer', 'default' => 2,
        ] );
        register_setting( 'wc_groupbuy_settings', 'wc_groupbuy_discount', [
            'type' => 'integer', 'default' => 50,
        ] );
        add_settings_section( 'wc_groupbuy_main', '基本设置', null, 'wc-groupbuy-settings' );
        add_settings_field( 'wc_groupbuy_group_size', '拼团人数', [ $this, 'field_group_size' ], 'wc-groupbuy-settings', 'wc_groupbuy_main' );
        add_settings_field( 'wc_groupbuy_discount',   '折扣比例（%）', [ $this, 'field_discount' ],   'wc-groupbuy-settings', 'wc_groupbuy_main' );
    }

    public function field_group_size() {
        $val = intval( get_option( 'wc_groupbuy_group_size', 2 ) );
        echo "<input type='number' name='wc_groupbuy_group_size' value='{$val}' min='2' />";
    }

    public function field_discount() {
        $val = intval( get_option( 'wc_groupbuy_discount', 50 ) );
        echo "<input type='number' name='wc_groupbuy_discount' value='{$val}' min='1' max='100' /> （50 即五折）";
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>WooCommerce 拼团功能设置</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'wc_groupbuy_settings' );
                do_settings_sections( 'wc-groupbuy-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /** 前端：渲染拼团 UI */
    public function output_groupbuy_ui() {
        if ( ! is_user_logged_in() ) {
            echo '<p>请先 <a href="'.esc_url(wp_login_url()).'">登录</a> 才能参与拼团。</p>';
            return;
        }

        global $product, $wpdb;
        $pid   = $product->get_id();
        $table = $wpdb->prefix . 'wc_groupbuys';
        $uid   = get_current_user_id();

        // 后台设置读取
        $group_size = intval( get_option( 'wc_groupbuy_group_size', 2 ) );
        $discount   = intval( get_option( 'wc_groupbuy_discount',   50 ) );

        // 查询参与者
        $users  = array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
            "SELECT user_id FROM {$table} WHERE product_id=%d",
            $pid
        ) ) );
        $count  = count( $users );
        $joined = in_array( $uid, $users, true );

        // 自动清理过期
        $first_joined = $wpdb->get_var( $wpdb->prepare(
            "SELECT joined_at FROM {$table} WHERE product_id=%d ORDER BY joined_at ASC LIMIT 1",
            $pid
        ) );
        if ( $first_joined && strtotime($first_joined) < ( time() - self::DURATION * 60 ) ) {
            $wpdb->delete( $table, [ 'product_id' => $pid ], [ '%d' ] );
            $users = []; $count = 0; $joined = false; $first_joined = null;
        }

        // 输出 HTML
        echo '<div class="groupbuy-box" style="margin-top:15px;padding:15px;border:1px solid #ddd;border-radius:4px;">';
        echo '<strong>拼团状态：</strong>' . ( $count >= $group_size ? '已成团 ✅' : '未成团' )
             . "（{$count}/{$group_size}）<br>";

        if ( $count > 0 && $first_joined ) {
            $expire_ts = strtotime( $first_joined ) + self::DURATION * 60;
            echo '<p>剩余拼团时间：<span id="groupbuy-countdown" data-expire="'.esc_attr($expire_ts).'"></span></p>';
        }

        if ( $count > 0 ) {
            echo '<div style="margin:8px 0;">';
            foreach ( $users as $uid_item ) {
                $user   = get_user_by( 'id', $uid_item );
                $avatar = get_avatar( $uid_item, 32 );
                $name   = $user ? esc_html( $user->display_name ) : "UID{$uid_item}";
                echo "<span style='margin-right:8px;vertical-align:middle;'>{$avatar} <strong>{$name}</strong></span>";
            }
            echo '</div>';
        }

        // 按钮逻辑
        if ( $joined ) {
            echo '<button id="cancel-groupbuy-btn" data-pid="'.esc_attr($pid).'" class="button">退出拼团</button>';
        }
        elseif ( $count >= $group_size ) {
            echo "<p>成团成功！结算时自动 {$discount}% 折 🎉</p>";
            echo '<button id="reset-groupbuy-btn" data-pid="'.esc_attr($pid).'" class="button">再次发起拼团</button>';
        }
        elseif ( $count > 0 ) {
            $left = $group_size - $count;
            echo '<button id="join-groupbuy-btn" data-pid="'.esc_attr($pid).'" class="button">加入拼团（剩余'.$left.'人）</button>';
        }
        else {
            echo '<button id="create-groupbuy-btn" data-pid="'.esc_attr($pid).'" class="button">创建拼团（'
                 . $group_size . '人成团，' . $discount . '% 折）</button>';
        }

        echo '</div>';

        // 前端脚本：倒计时＆AJAX（同原版）
        ?>
        <script>
        jQuery(function($){
            const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
            const nonce   = '<?php echo wp_create_nonce('groupbuy-nonce'); ?>';

            // 倒计时
            var cd = $('#groupbuy-countdown');
            if ( cd.length ) {
                var expire = parseInt(cd.data('expire'),10);
                function updateCountdown(){
                    var now  = Math.floor(Date.now()/1000),
                        diff = expire - now;
                    if ( diff <= 0 ) return location.reload();
                    var h = Math.floor(diff/3600),
                        m = Math.floor((diff%3600)/60),
                        s = diff % 60;
                    cd.text( h+'小时'+(m<10?'0'+m:m)+'分'+(s<10?'0'+s:s)+'秒' );
                }
                updateCountdown();
                setInterval(updateCountdown, 1000);
            }

            // AJAX：创建
            $('#create-groupbuy-btn').on('click', function(){
                $.post(ajaxUrl, {
                    action:      'create_groupbuy',
                    product_id:  $(this).data('pid'),
                    _ajax_nonce: nonce
                }, res => res.success ? location.reload() : alert(res.data||'创建失败'));
            });

            // AJAX：加入
            $('#join-groupbuy-btn').on('click', function(){
                $.post(ajaxUrl, {
                    action:      'join_groupbuy',
                    product_id:  $(this).data('pid'),
                    _ajax_nonce: nonce
                }, res => res.success ? location.reload() : alert(res.data||'加入失败'));
            });

            // AJAX：退出
            $('#cancel-groupbuy-btn').on('click', function(){
                if (!confirm('确定退出拼团？')) return;
                $.post(ajaxUrl, {
                    action:      'cancel_groupbuy',
                    product_id:  $(this).data('pid'),
                    _ajax_nonce: nonce
                }, res => res.success ? location.reload() : alert(res.data||'退出失败'));
            });

            // AJAX：重置
            $('#reset-groupbuy-btn').on('click', function(){
                if (!confirm('确定清空并重新发起拼团？')) return;
                $.post(ajaxUrl, {
                    action:      'reset_groupbuy',
                    product_id:  $(this).data('pid'),
                    _ajax_nonce: nonce
                }, res => res.success ? location.reload() : alert(res.data||'重置失败'));
            });
        });
        </script>
        <?php
    }

    /** AJAX：创建拼团 */
    public function ajax_create_groupbuy() {
        check_ajax_referer('groupbuy-nonce');
        if (!is_user_logged_in()) wp_send_json_error('请先登录');
        global $wpdb;
        $pid   = intval($_POST['product_id']);
        $uid   = get_current_user_id();
        $table = $wpdb->prefix.'wc_groupbuys';
        if ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE product_id=%d",$pid))) {
            wp_send_json_error('已有进行中的拼团，请加入或重置');
        }
        $wpdb->insert($table,['product_id'=>$pid,'user_id'=>$uid],['%d','%d']);
        wp_send_json_success();
    }

    /** AJAX：加入拼团 */
    public function ajax_join_groupbuy() {
        check_ajax_referer('groupbuy-nonce');
        if (!is_user_logged_in()) wp_send_json_error('请先登录');
        global $wpdb;
        $pid   = intval($_POST['product_id']);
        $uid   = get_current_user_id();
        $table = $wpdb->prefix.'wc_groupbuys';
        $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE product_id=%d",$pid));
        if ($count>= intval(get_option('wc_groupbuy_group_size',2))) {
            wp_send_json_error('拼团已满，请重置再发起');
        }
        if ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE product_id=%d AND user_id=%d",$pid,$uid))) {
            wp_send_json_error('你已在拼团中');
        }
        $wpdb->insert($table,['product_id'=>$pid,'user_id'=>$uid],['%d','%d']);
        wp_send_json_success();
    }

    /** AJAX：退出拼团 */
    public function ajax_cancel_groupbuy() {
        check_ajax_referer('groupbuy-nonce');
        if (!is_user_logged_in()) wp_send_json_error('请先登录');
        global $wpdb;
        $pid   = intval($_POST['product_id']);
        $uid   = get_current_user_id();
        $table = $wpdb->prefix.'wc_groupbuys';
        $del   = $wpdb->delete($table,['product_id'=>$pid,'user_id'=>$uid],['%d','%d']);
        $del ? wp_send_json_success() : wp_send_json_error('退出失败');
    }

    /** AJAX：重置拼团 */
    public function ajax_reset_groupbuy() {
        check_ajax_referer('groupbuy-nonce');
        if (!is_user_logged_in()) wp_send_json_error('请先登录');
        global $wpdb;
        $pid   = intval($_POST['product_id']);
        $table = $wpdb->prefix.'wc_groupbuys';
        $del   = $wpdb->delete($table,['product_id'=>$pid],['%d']);
        $del !== false ? wp_send_json_success() : wp_send_json_error('重置失败');
    }

    /** 结算时应用后台设置的折扣 */
    public function apply_groupbuy_discount( $cart ) {
        if ( is_admin() && ! defined('DOING_AJAX') ) return;
        global $wpdb;
        $table       = $wpdb->prefix.'wc_groupbuys';
        $group_size  = intval( get_option( 'wc_groupbuy_group_size', 2 ) );
        $discount    = intval( get_option( 'wc_groupbuy_discount', 50 ) );

        $success_ids = array_map('intval', $wpdb->get_col(
            "SELECT product_id FROM {$table} GROUP BY product_id HAVING COUNT(*)>={$group_size}"
        ));
        if ( empty($success_ids) ) return;

        foreach ( $cart->cart_contents as $key => $item ) {
            if ( in_array($item['product_id'], $success_ids, true) ) {
                $orig = floatval($item['data']->get_price());
                $new  = round($orig * $discount / 100, wc_get_price_decimals());
                $item['data']->set_price($new);
            }
        }
    }
}

new WC_Simple_Group_Buy();

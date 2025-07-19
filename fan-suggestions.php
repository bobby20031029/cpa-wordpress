<?php
/**
 * Plugin Name:  Fan Suggestions & Voting
 * Description:  粉丝提交建议并投票的前端模块，短代码即插即用。
 * Version:      0.1
 * Author:       Ziyang Song
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ======================================================
 * 0. 注册自定义文章类型 fan_suggestion
 * ==================================================== */
add_action( 'init', function () {
    register_post_type( 'fan_suggestion', array(
        'labels' => array( 'name' => '粉丝建议' ),
        'public' => false,
        'show_ui'=> true,
        'supports' => array( 'title', 'editor', 'author' ),
    ) );
} );

/* ======================================================
 * 1. 前端表单短代码 [suggestion_form]
 * ==================================================== */
function fs_form_shortcode() {
    ob_start(); ?>
    <form id="fs-form" style="max-width:480px;margin:24px 0;">
        <p>
            <label>昵称（可留空）<br>
                <input type="text" name="nickname" style="width:100%;padding:6px;">
            </label>
        </p>
        <p>
            <label>你的建议 / 想法 <span style="color:red">*</span><br>
                <textarea name="content" rows="4" required style="width:100%;padding:6px;"></textarea>
            </label>
        </p>
        <button type="submit" class="button button-primary">提交建议</button>
    </form>
    <script>
    (function(){
      const f=document.getElementById('fs-form');
      f&&f.addEventListener('submit',async e=>{
        e.preventDefault();
        const fd=new FormData(f);
        fd.append('action','fs_submit');
        fd.append('nonce','<?php echo wp_create_nonce('fs_nonce'); ?>');
        const r=await fetch('<?php echo admin_url('admin-ajax.php'); ?>',{method:'POST',body:fd});
        const j=await r.json();
        alert(j.success? '已提交，感谢！' : j.data);
        if(j.success) f.reset();
      });
    })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'suggestion_form', 'fs_form_shortcode' );

/* Ajax 保存 */
add_action( 'wp_ajax_nopriv_fs_submit', 'fs_submit_ajax' );
function fs_submit_ajax() {
    check_ajax_referer( 'fs_nonce', 'nonce' );
    $content = sanitize_text_field( $_POST['content'] ?? '' );
    if ( ! $content ) wp_send_json_error( '内容不能为空' );

    $nickname = sanitize_text_field( $_POST['nickname'] ?? '' );
    $post_id  = wp_insert_post( array(
        'post_type'   => 'fan_suggestion',
        'post_status' => 'publish',
        'post_title'  => $nickname ? $nickname.' 的建议' : '匿名建议',
        'post_content'=> $content,
    ) );
    if ( is_wp_error( $post_id ) ) wp_send_json_error( '保存失败' );

    update_post_meta( $post_id, '_votes', 0 );
    wp_send_json_success();
}

/* ======================================================
 * 2. 建议墙短代码 [suggestion_board] —— 条形+浮动投稿
 * ==================================================== */
function fs_board_shortcode() {

    $posts = get_posts( array(
        'post_type'      => 'fan_suggestion',
        'posts_per_page' => -1,
        'orderby'        => 'meta_value_num',
        'meta_key'       => '_votes',
        'order'          => 'DESC',
    ) );

    if ( ! $posts ) {
        $empty = '<p style="color:#777;">还没有建议，点右下角“＋”写第一条吧！</p>';
    }

    /* 计算最大票数，用于百分比宽度 */
    $max = 0;
    foreach ( $posts as $p ) {
        $v = (int) get_post_meta( $p->ID, '_votes', true );
        if ( $v > $max ) $max = $v;
    }
    $max = max( 1, $max ); // 避免除 0

    ob_start(); ?>

    <div class="fs-wrapper" style="position:relative;margin:24px 0;">
        <?php echo $empty ?? ''; ?>

        <ul class="fs-board" style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:12px;">
            <?php foreach ( $posts as $p ) :
                $votes   = (int) get_post_meta( $p->ID, '_votes', true );
                $percent = max( 10, round( $votes / $max * 100 ) ); // 最小 10%
            ?>
            <li data-id="<?php echo $p->ID; ?>"
                class="fs-item"
                style="cursor:pointer;position:relative;">
                <div class="fs-bar"
                     style="width:<?php echo $percent; ?>%;background:#444;border-radius:20px;padding:6px 14px;color:#eee;">
                    <?php echo esc_html( wp_trim_words( $p->post_content, 20 ) ); ?>
                    <span class="fs-count" style="float:right;"><?php echo $votes; ?></span>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>

        <!-- 浮动 “+” -->
        <button class="fs-add"
                style="position:absolute;bottom:-16px;right:-16px;width:48px;height:48px;
                       border-radius:50%;background:#0073aa;color:#fff;font-size:30px;border:none;
                       box-shadow:0 2px 6px rgba(0,0,0,.3);cursor:pointer;">＋</button>

        <!-- 弹出层表单（复用早期表单 HTML） -->
        <div class="fs-popup" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);
             align-items:center;justify-content:center;z-index:10000;">
            <div style="background:#fff;border-radius:8px;padding:24px;max-width:480px;width:90%;position:relative;">
                <button class="fs-close" style="position:absolute;top:10px;right:10px;border:none;background:none;font-size:20px;">×</button>
                <?php echo fs_form_shortcode(); /* 直接调用之前的表单函数 */ ?>
            </div>
        </div>
    </div>

    <style>
        .fs-item:hover .fs-bar{background:#555;}
    </style>

    <script>
    (function(){
      /* ---- 投票 ---- */
      document.querySelectorAll('.fs-item').forEach(function(li){
        li.addEventListener('click',function(){
          var id=this.dataset.id;
          if(localStorage.getItem('fs_voted_'+id)){ alert('您已投票'); return; }
          var fd=new FormData();
          fd.append('action','fs_vote');
          fd.append('nonce','<?php echo wp_create_nonce('fs_nonce'); ?>');
          fd.append('id',id);
          fetch('<?php echo admin_url('admin-ajax.php'); ?>',{method:'POST',body:fd})
          .then(r=>r.json()).then(j=>{
            if(j.success){
              this.querySelector('.fs-count').textContent=j.data;
              /* 重新计算条形长度 */
              var max=parseInt(document.querySelector('.fs-board .fs-count').textContent,10);
              document.querySelectorAll('.fs-item').forEach(function(li2){
                 var v=parseInt(li2.querySelector('.fs-count').textContent,10);
                 var w=Math.max(10,Math.round(v/max*100));
                 li2.querySelector('.fs-bar').style.width=w+'%';
              });
              localStorage.setItem('fs_voted_'+id,1);
            }else alert(j.data||'出错了');
          });
        });
      });

      /* ---- 弹出层 ---- */
      const addBtn=document.querySelector('.fs-add'),
            pop=document.querySelector('.fs-popup');
      if(addBtn) addBtn.onclick=()=>pop.style.display='flex';
      if(pop){
        pop.querySelector('.fs-close').onclick=()=>pop.style.display='none';
        /* 表单提交成功后自动关闭并刷新页面 */
        pop.querySelector('#fs-form')?.addEventListener('submit',()=>setTimeout(()=>location.reload(),500));
      }
    })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'suggestion_board', 'fs_board_shortcode' );

(function($){
  $(document).ready(function(){
    // 为每个 .btn-vote 按钮绑定点击事件
    $('.btn-vote').on('click', function(){
      var $btn = $(this);
      var $card = $btn.closest('.op-card');
      var opId = $card.data('id');

      // 防刷：如果 localStorage 已存在投票记录，就不重复提交
      if ( localStorage.getItem('ovtd_voted_' + opId) ) {
        return;
      }

      // 发起投票请求
      fetch(ovtdData.rest_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          type: 'operator',
          id: opId
        })
      })
      .then(function(res){ return res.json(); })
      .then(function(data){
        if ( data && ! data.code ) {
          // 标记已投
          localStorage.setItem('ovtd_voted_' + opId, '1');
          $btn.prop('disabled', true).text('已投票');

          // 更新结果展示
          var $result = $card.find('.vote-result');
          var percent = Math.round( data.thisVotes / data.totalVotes * 100 );
          $result.find('.percent').text(percent + '%');
          $result.find('.fill').css('width', percent + '%');
          $result.show();
        }
      })
      .catch(function(err){
        console.error('投票失败', err);
      });
    });

    // 页面加载时，如果投过，就直接显示结果
    $('.op-card').each(function(){
      var $card = $(this);
      var opId = $card.data('id');
      if ( localStorage.getItem('ovtd_voted_' + opId) ) {
        var $btn = $card.find('.btn-vote');
        $btn.prop('disabled', true).text('已投票');
        $card.find('.vote-result').show();
      }
    });
  });
})(jQuery);

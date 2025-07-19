jQuery(document).ready(function($){
    $('#stc-create-site').on('click', function(e){
        e.preventDefault();
        var $msg = $('#stc-result');
        $msg.text('Creating your site, please wait…');

        $.post(stc_ajax.ajax_url, {
            action:  'stc_clone_template',
            nonce:   stc_ajax.nonce
        }, function(res){
            if ( res.success ) {
                $msg.html('Site created: <a href="' + res.data.url +
                          '" target="_blank">' + res.data.url + '</a>');
            } else {
                $msg.text('Error: ' + res.data);
            }
        });
    });
});

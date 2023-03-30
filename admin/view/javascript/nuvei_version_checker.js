window.onload = (event) => {
    console.log('nuvei version checker');
    
    // be sure those consts are same as in php NUVEI_CLASS
    let date    = new Date();
    let today   = date.getDate();
    
    if(today % 7 != 0) {
        return;
    }
    
    let ajaxUrl         = 'index.php?route=extension/nuvei/payment/nuvei|check_for_update&user_token=';
    let nuveiGetParams  = window.location.toString().split('&');
    let nuveiCookieMsg  = '';
    
    for(var i in nuveiGetParams) {
        // get user token
        if(nuveiGetParams[i].search('user_token') == 0) {
            ajaxUrl += nuveiGetParams[i].replace('user_token=', '');
            break;
        }
    }
    
    if('' != document.cookie) {
        let cookies         = document.cookie;
        let cookiesParts    = cookies.split(';');
        
        for(var i in cookiesParts) {
            if(cookiesParts[i].search('nuvei_plugin_msg') == 0) {
                nuveiCookieMsg = cookiesParts[i].replace('nuvei_plugin_msg=', '');
                break;
            }
        }
    }
    
    if('' != nuveiCookieMsg) {
        $('#content .container-fluid:first')
            .append('<div class="alert alert-info"><i class="fa fa-info-circle"></i> '+ nuveiCookieMsg +'</div>');
        return;
    }
    
    $.ajax({
        url: ajaxUrl,
        type: 'post',
        dataType: 'json',
    })
    .done(function(resp) {
        if(resp.hasOwnProperty('status')) {
            if(resp.status == 1 && resp.hasOwnProperty('msg')) {
                $('#content .container-fluid:first')
                    .append('<div class="alert alert-info"><i class="fa fa-info-circle"></i> '+ resp.msg +'</div>');
                 
                // set cookie life to 6 days
                const d = new Date();
                d.setTime(d.getTime() + (6*24*60*60*1000));

                document.cookie = 'nuvei_plugin_msg='+ resp.msg +'; expires=' + d.toUTCString() + '; path=/admin';
                return;
            }

            if(resp.hasOwnProperty('msg')) {
                console.log(resp.msg);
                return;
            }
        }
        else {
            console.log('Nuvei Ajax response has no status.');
        }
    })
    .fail(function(resp) {
        console.error('Ajax response error:', resp);
    });
};
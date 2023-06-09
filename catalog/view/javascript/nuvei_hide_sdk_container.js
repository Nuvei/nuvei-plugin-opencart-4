$( document ).ajaxComplete(function(event, xhr, setting) {
    // try to hide Nuvei checkout container
    if (typeof nuveiPluginCode != 'undefined'
        && setting.hasOwnProperty('type')
        && 'POST' == setting.type
        && setting.hasOwnProperty('url')
        && setting.url.search('checkout/payment_method.save') > -1
        && $('#input-payment-code').val().search(nuveiPluginCode) == -1
    ) {
        $('#nuvei_checkout').hide();
    }
    
    
    
    // try to catch privacy and policy change
    if (typeof nuveiPluginTitle != 'undefined'
        && setting.hasOwnProperty('type')
        && 'GET' == setting.type
        && setting.hasOwnProperty('url')
        && setting.url.search('checkout/confirm.confirm') > -1
    ) {
        if ('' == nuveiPluginTitle) {
            $('#nuvei_checkout').hide();
        }
        else if ($('#input-payment-method').val() != nuveiPluginTitle) {
            $('#nuvei_checkout').hide();
        }
        
    }
});
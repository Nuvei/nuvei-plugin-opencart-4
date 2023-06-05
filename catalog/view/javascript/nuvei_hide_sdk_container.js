$( document ).ajaxComplete(function(event, xhr, setting) {
    // try to hide Nuvei checkout container
    if (setting.hasOwnProperty('type')
        && 'POST' == setting.type
        && setting.hasOwnProperty('url')
        && setting.url.search('checkout/payment_method.save') > -1
        && typeof nuveiPluginCode != 'undefined'
        && $('#input-payment-code').val().search(nuveiPluginCode) == -1
    ) {
        $('#nuvei_checkout').hide();
    }
});
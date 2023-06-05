$(function() {
    $('input[name="payment_method"]').change(function() {
        console.log( $(':selected', this).val() );
    });
    
    $('#button-payment-method').click(function() {
        console.log( $('input[name="payment_method"]:selected').val() );
    });
    
    
    if (typeof nuveiPluginCode != 'undefined'
        && $('#input-payment-code').val() != nuveiPluginCode + '.' + nuveiPluginCode
    ) {
        $('#nuvei_checkout').hide();
    }
        
});



// show Nuvei Add Product Error
$( document ).ajaxComplete(function(event, xhr, setting) {
    if (xhr.hasOwnProperty('responseJSON') 
        && typeof xhr.responseJSON != 'undefined'
        && xhr.responseJSON.hasOwnProperty('nuvei_add_product_error')
    ) {
        $('#alert').prepend(
            '<div class="alert alert-danger alert-dismissible">'
                + '<i class="fa-solid fa-circle-check"></i> ' 
                + xhr.responseJSON.nuvei_add_product_error 
                + ' <button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
            + '</div>'
        );
    }
});
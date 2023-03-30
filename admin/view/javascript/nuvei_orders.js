var nuveiVars = {
    ajaxUrl: 'index.php?route=extension/nuvei/payment/nuvei'
};

var nuveiGetParams = window.location.toString().split('&');

for(var i in nuveiGetParams) {
    // get user token
    if(nuveiGetParams[i].search('user_token') == 0) {
        nuveiVars.userToken = nuveiGetParams[i].replace('user_token=', '');
    }
    // get order id
    if(nuveiGetParams[i].search('order_id') == 0) {
        nuveiVars.orderId = nuveiGetParams[i].replace('order_id=', '');
    }
}

function scOrderActions(confirmQusetion, action, orderId) {
    console.log(action);

    if('refund' == action) {
        var refAm   = $('#refund_amount').val().replace(',', '.');
        var reg     = new RegExp(/^\d+(\.\d{1,2})?$/); // match integers and decimals

        if(!reg.test(refAm) || isNaN(refAm) || refAm <= 0) {
            alert(nuveiVars.nuveiRefundAmountError);
            return;
        }
    }
    
    var question = confirmQusetion + ' #' + orderId + '?';
    
    if('cancelSubscription' == action) {
        question = confirmQusetion;
    }
    
    if(!confirm(question)) {
        return;
    }
    
    $('#nuvei_btn_'+ action +' .fa-spin').removeClass('d-none');

    // disable sc custom buttons
    $('.sc_order_btns').each(function(){
        $(this).attr('disabled', true);
    });

    console.log('before ajax');

    $.ajax({
        url: nuveiVars.ajaxUrl + '|'+ action +'&user_token=' + nuveiVars.userToken,
        type: 'post',
        dataType: 'json',
        data: {
            orderId: orderId
            ,action: action
            ,amount: $('#refund_amount').val()
        }
    })
    .done(function(resp) {
        console.log('done', resp)

        if(resp.hasOwnProperty('status')) {
            if(resp.status == 1) {
                window.location.href = window.location.toString().replace('/info', '');
                return;
            }

            if(resp.status == 0) {
                if(resp.hasOwnProperty('msg')) {
                    alert(resp.msg);
                }
                else {
                    alert(nuveiVars.nuveiUnexpectedError);
                }

                $('#nuvei_btn_'+ action +' .fa-spin').addClass('d-none');

                // enable sc custom buttons
                $('.sc_order_btns').each(function(){
                    $(this).attr('disabled', false);
                });

                return;
            }
        }
        else {
            $('#nuvei_btn_'+ action +' .fa-spin').addClass('d-none');

            alert(nuveiVars.nuveiUnexpectedError);
        }
    })
    .fail(function(resp) {
        $('#nuvei_btn_'+ action +' .fa-spin').addClass('d-none');

        // enable sc custom buttons
        $('.sc_order_btns').each(function(){
            $(this).attr('disabled', false);
        });

        console.error('ajax response error:', resp);
    });
}

function loadNuveiExtras() {
    // 1.set the changes in Options table
    var buttonsHtml = '';
    var scPlaceOne  = $('#content .container-fluid .card.mb-3 .card-body > .row:nth-child(1)');
    
    var voidBtnTpl = '<button class="btn btn-danger sc_order_btns" id="nuvei_btn_void" style="margin-left: 2px;" onclick="scOrderActions(\''+ nuveiVars.nuveiOrderConfirmCancel +'\', \'void\', '+ nuveiVars.orderId +')"><i class="fas fa-circle-notch fa-spin d-none me-1"></i>'+ nuveiVars.nuveiBtnVoid +'</button>';
    
    var cancelSubsBtnTpl = '<button class="btn btn-danger sc_order_btns" id="nuvei_btn_cancelSubscription" style="margin-left: 2px;" onclick="scOrderActions(\''+ nuveiVars.nuveiConfirmCancelSubs +'\', \'cancelSubscription\', '+ nuveiVars.orderId +')"><i class="fas fa-circle-notch fa-spin d-none me-1"></i>'+ nuveiVars.nuveiBtnCancelSubs +'</button>';
    
    if(scPlaceOne.length > 0) {
        // 1.1.place Refund, Void and Cancel Subscription buttons
        if(1 == nuveiVars.nuveiAllowRefundBtn) {
            buttonsHtml = 
                '<div class="input-group" style="margin-bottom: 2px; margin-top: 2px;">'
                    + '<input type="text" class="form-control" id="refund_amount" value="">'
                    + '<div class="input-group-append">'
                        + '<button class="btn btn-danger sc_order_btns" id="nuvei_btn_refund" type="button" onclick="scOrderActions(\''+ nuveiVars.nuveiOrderConfirmRefund +'\', \'refund\', '+ nuveiVars.orderId +')"><i class="fas fa-circle-notch fa-spin d-none me-1"></i>'+ nuveiVars.nuveiBtnRefund +'</button>';

            // add Void button
            if(1 == nuveiVars.nuveiAllowVoidBtn) {
                buttonsHtml += voidBtnTpl;
            }
            
            // add Cancel Subscription button
            if(1 == nuveiVars.nuveiAllowCancelSubsBtn) {
                buttonsHtml += cancelSubsBtnTpl;
            }

            buttonsHtml +=
                    '</div>'
                + '</div>';
        }
        
        // 1.2.set Void button
        if(1 != nuveiVars.nuveiAllowRefundBtn && 1 == nuveiVars.nuveiAllowVoidBtn) {
            buttonsHtml += voidBtnTpl;
        }

        // 1.3.set Settle btn
        if(1 == nuveiVars.nuveiAllowSettleBtn) {
                buttonsHtml += '<button class="btn btn-success sc_order_btns" id="nuvei_btn_settle" style="margin-left: 2px;" onclick="scOrderActions(\''+ nuveiVars.nuveiOrderConfirmSettle +'\', \'settle\', '+ nuveiVars.orderId +')"><i class="fas fa-circle-notch fa-spin d-none me-1"></i>'+ nuveiVars.nuveiBtnSettle +'</button>';
        }
        
        // 1.4 set Cancel Subscription Button only
        if (1 != nuveiVars.nuveiAllowRefundBtn && 1 == nuveiVars.nuveiAllowCancelSubsBtn) {
            buttonsHtml += cancelSubsBtnTpl;
        }
        
        // 1.4.place buttons
        scPlaceOne.after(
            '<div class="row mb-3">'
                + '<div class="col">'
                    + '<div class="form-control border rounded-start">'
                        + '<div class="row">'
                            + '<div class="col">'
                                + '<div class="lead"><strong>'+ nuveiVars.nuveiMoreActions +'</strong></div>'
                            + '</div>'

                            + '<div class="col" style="text-align: right;">' + buttonsHtml + '</div>'
                        + '</div>'
                    + '</div>'
                + '</div>'
            + '</div>'
        );
    }
    // set the changes in Options table END

    // 2.add SC Refunds
    var scPlaceTwo = $('#order-products');
    
    if(scPlaceTwo.length <= 0) {
        return;
    }
    
    if(nuveiVars.nuveiRefunds == 'undefined' || nuveiVars.nuveiRefunds.length == 0) {
        return;
    }

    // 2.1 collect Refunds
    var scRefundsRows = '';

    for(var i in nuveiVars.nuveiRefunds) {
        scRefundsRows += 
            '<tr id="sc_refund_'+ nuveiVars.nuveiRefunds[i].clientUniqueId +'">'
                + '<td class="text-start">' + nuveiVars.nuveiRefunds[i].clientUniqueId +'</td>'
                + '<td class="text-start">'+ nuveiVars.nuveiRefunds[i].transactionId +'</td>'
                + '<td class="text-start">'+ nuveiVars.nuveiRefunds[i].responseTimeStamp +'</td>'
                + '<td class="text-end" colspan="2">'+ nuveiVars.nuveiRefunds[i].amount_curr +'</td>'
                + '<td>&nbsp;</td>'
            + '</tr>';
        }
    // /2.1 collect Refunds

    // 2.2.place Refunds
    scPlaceTwo.append(
        '<tr><td colspan="5"></tr>'
        + '<tr>'
            + '<td class="text-start"><strong>'+ nuveiVars.nuveiRefundId +'</strong></td>'
            + '<td class="text-start"><strong>Transaction ID</strong></td>'
            + '<td class="text-start"><strong>'+ nuveiVars.nuveiDate +'</strong></td>'
            + '<td class="text-end" colspan="2"><strong>Refund Amount</strong></td>'
            + '<td>&nbsp;</td>'
        + '</tr>'

        + scRefundsRows

        + '<tr>'
            + '<td class="text-end" colspan="4"><strong>'+ nuveiVars.nuveiRemainingTotal +'</strong></td>'
            + '<td class="text-end"><strong id="nuveiRemainigTotal">'+ nuveiVars.remainingTotalCurr +'</strong></td>'
            + '<td>&nbsp;</td>'
        + '</tr>'
    );
    // /2.add SC Refunds
}

$(function(){
    console.log('nuvei_orders loaded');
    
    $.ajax({
        url: nuveiVars.ajaxUrl + '|get_nuvei_vars&user_token=' + nuveiVars.userToken,
        type: 'post',
        dataType: 'json',
        data: { orderId: nuveiVars.orderId }
    })
    .done(function(resp) {
        console.log('ajax call response', resp)

        // set nuvei variables
        if(typeof resp != 'undefined') {
            if(!resp.hasOwnProperty('isNuveiOrder') || 0 == resp.isNuveiOrder) {
                console.log('This is not a Nuvei order');
                return;
            }
            
            nuveiVars               = {...nuveiVars, ...resp};
            nuveiVars.nuveiRefunds  = JSON.parse(nuveiVars.nuveiRefunds);

            loadNuveiExtras();
            return;
        }

        alert('Unexpected error');
        return;
    })
    .fail(function(resp) {
        console.error('Nuvei Ajax response error:', resp);

        alert('Unexpected error');
        return;
    });
});
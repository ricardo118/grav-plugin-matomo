
$(document).ready(function() {

    window.matomo.popup.onAccept = function(){
        $.fn.ihavecookies.cookie();
    };

    $('body').ihavecookies(window.matomo.popup);

    if ($.fn.ihavecookies.preference('analytics') === true || $.fn.ihavecookies.preference('matomo') === true) {
        _paq.push(['setConsentGiven']);
    }

    // you can extend to other cookies if you want here by doing the same method as above.
    // This plugin is only adapted to use matomo and if you require other more advanced configs then I suggest to disable this popup
    // and use a full blown popup plugin.

});

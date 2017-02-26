function reloadLater(handle) {
    clearTimeout(window.timeoutHandle);
    window.timeoutHandle = setTimeout(function() {
        if ($(blind).is(':visible')) return;
        if ($('#_YY_' + handle).is(':visible')) {
            go();
        }
    }, 1000);
}

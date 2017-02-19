/**
 * Created by epoxa on 12.02.17.
 */

(function () {

    window.yy_translate_agent = this;

    var $translatorIndicator;
    var translatorHandle;

    var createHandle = function () {
        $translatorIndicator = $('<div style="display: none; position: absolute; z-index: 9999; background-color: red; width: 3mm; height: 3mm; border-radius: 3mm; cursor: crosshair"></div>');
        $translatorIndicator.appendTo('body').hover(
            function () {
                $translatorIndicator.stop(true, true).show();
            },
            function () {
                $translatorIndicator.fadeOut(1200);
            }
        ).click(
            function () {
                go(translatorHandle, 'showTranslatePrompt', {s_slug: $($translatorIndicator.element).attr('data-translate-slug')});
            }
        );
    };

    this.setTranslatorHandle = function (handle) {
        translatorHandle = handle;
    };

    this.registerTranslatable = function (md5slug) {
        if (!$translatorIndicator) {
            createHandle();
        }
        console.info('registerTranslatable: ');
        $('*[data-translate-slug="' + md5slug + '"]').hover(
            function () {
                var p = $(this).offset();
                $translatorIndicator.css('right', $('body').width() - p.left - $(this).outerWidth() - 4).css('top', p.top - 4).stop(true, true).show();
                $translatorIndicator.element = this;
            },
            function (event) {
                if ($(event.relatedTarget)[0] != $translatorIndicator[0]) $translatorIndicator.fadeOut(1200);
            }
        );
    };

    this.showTranslatePrompt = function (slug, originalValue, translatedValue) {
        trans = prompt(originalValue, translatedValue);
        if (trans === null) return;
        go(translatorHandle, 'setTranslation', {s_slug: slug, s_translation: trans});
    }

})();


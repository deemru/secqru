<?php

    define( 'SECQRU_ROOT', '/secqru/' );
    define( 'SECQRU_SITE', 'secq.ru' );
    define( 'SECQRU_PASS', 'password' );

    define( 'SECQRU_ADDR', 'http' .
    ( ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] == 'on' ) ? 's' : '' ) .
    '://' . $_SERVER['HTTP_HOST'] . SECQRU_ROOT );

    date_default_timezone_set( 'Europe/Moscow' );
    mb_internal_encoding( 'UTF-8' );

    define( 'SECQRU_DEBUG', 1 );
    define( 'SECQRU_ERRORLOG', './var/log/_error.log' );
    define( 'SECQRU_ACCESSLOG', './var/log/access.log' );
    define( 'SECQRU_APPLOG', './var/log/%s.log' );
    define( 'SECQRU_LOCKIP', './var/lock/' );

    define( 'SECQRU_GITHEAD', 1 );
    define( 'SECQRU_INFORMER', '<!-- Yandex.Metrika informer -->
<a href="https://metrika.yandex.ru/stat/?id=32274279&amp;from=informer"
target="_blank" rel="nofollow"><img src="https://informer.yandex.ru/informer/32274279/3_0_%sFF_%sFF_%s_pageviews"
style="width:88px; height:31px; border:0;" onclick="try{Ya.Metrika.informer({i:this,id:32274279,lang:\'ru\'});return false}catch(e){}" /></a>
<!-- /Yandex.Metrika informer -->

<!-- Yandex.Metrika counter -->
<script type="text/javascript">
    (function (d, w, c) {
        (w[c] = w[c] || []).push(function() {
            try {
                w.yaCounter32274279 = new Ya.Metrika({
                    id:32274279,
                    clickmap:true,
                    trackLinks:true,
                    accurateTrackBounce:true,
                    webvisor:true,
                    ut:"noindex"
                });
            } catch(e) { }
        });

        var n = d.getElementsByTagName("script")[0],
            s = d.createElement("script"),
            f = function () { n.parentNode.insertBefore(s, n); };
        s.type = "text/javascript";
        s.async = true;
        s.src = "https://mc.yandex.ru/metrika/watch.js";

        if (w.opera == "[object Opera]") {
            d.addEventListener("DOMContentLoaded", f, false);
        } else { f(); }
    })(document, window, "yandex_metrika_callbacks");
</script>
<noscript><div><img src="https://mc.yandex.ru/watch/32274279?ut=noindex" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
<!-- /Yandex.Metrika counter -->' );

?>
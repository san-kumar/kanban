<?php

if (preg_match('/favicon\.ico/', $_SERVER['REQUEST_URI'])) {
    header("Location: https://i.imgur.com/eCDPzLa.png");
    exit(0);
}

if (preg_match('/emoji\.woff/', $_SERVER['REQUEST_URI'])) {
    header('Content-Type: font/woff2');
    readfile(__DIR__ . '/emoji.woff');
    exit(0);
}

ob_start();

register_shutdown_function(function () {
    global $title;
    $body = ob_get_contents();
    ob_end_clean();
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <title><?= ucfirst(basename($title)) ?>: Todo</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
        <meta name="viewport" content="width=device-width,initial-scale=1.0">
        <style>
            label.task a.ln {
                visibility: hidden
            }

            label.task:hover a.ln {
                visibility: visible
            }

            .heading {
                text-transform: capitalize;
            }

            .panel {
                margin-top: 15px;
                height: calc(100vh - 170px);
                overflow: auto;
            }
        </style>
    </head>

    <body style="margin: 0; padding: 0;">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.5.0/css/bootstrap.min.css" />

    <script src="https://cdnjs.cloudflare.com/ajax/libs/vue/2.6.11/vue.min.js" crossorigin="anonymous"></script>
    <script src="https://unpkg.com/sortablejs@1.4.2"></script>
    <script src="//cdnjs.cloudflare.com/ajax/libs/Vue.Draggable/2.20.0/vuedraggable.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/timeago.js/4.0.2/timeago.min.js"></script>

    <?= $body ?>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Raleway&display=swap');

        @font-face {
            font-family: "EmojiSymbols";
            src: url(/emoji.woff) format("woff");
            text-decoration: none;
            font-style: normal
        }

        body, html, input {
            font-family: 'Raleway', sans-serif, 'EmojiSymbols';
        }
    </style>
    </body>
    </html>
    <?php
});
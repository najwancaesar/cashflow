<?php

if (!function_exists('show_sweetalert_and_redirect')) {
    function show_sweetalert_and_redirect($title, $text, $icon = 'info', $redirect = '')
    {
        $titleJson = json_encode($title, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $textJson = json_encode($text, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $iconJson = json_encode($icon, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $redirectJson = json_encode($redirect, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head><body>';
        echo "<script>
            (function () {
                var title = {$titleJson};
                var text = {$textJson};
                var icon = {$iconJson};
                var redirectTo = {$redirectJson};

                function continueAction() {
                    if (redirectTo) {
                        window.location.href = redirectTo;
                    }
                }

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: title,
                        text: text,
                        icon: icon,
                        confirmButtonColor: '#0ea5e9'
                    }).then(function () {
                        continueAction();
                    });
                } else {
                    alert(title + '\\n' + text);
                    continueAction();
                }
            })();
        </script>";
        echo '</body></html>';
        exit;
    }
}

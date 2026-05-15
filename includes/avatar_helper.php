<?php

if (!function_exists('default_avatar_filename')) {
    function default_avatar_filename()
    {
        return 'team-4.jpg';
    }
}

if (!function_exists('resolve_profile_photo')) {
    function resolve_profile_photo($filename)
    {
        $filename = trim((string) $filename);
        $safeFallback = default_avatar_filename();

        if ($filename === '' || $filename === 'default.png') {
            return $safeFallback;
        }

        $candidatePath = __DIR__ . '/../assets/img/profil/' . basename($filename);

        if (!is_file($candidatePath)) {
            return $safeFallback;
        }

        return basename($filename);
    }
}

if (!function_exists('profile_photo_src')) {
    function profile_photo_src($filename)
    {
        return 'assets/img/profil/' . resolve_profile_photo($filename);
    }
}

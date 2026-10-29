<?php

return [
    /*
     * Whether new accounts can be created. The very first account can always be created, so a fresh
     * install can be claimed; after that, set ALLOW_REGISTRATION=false to keep strangers out.
     */
    'registration' => (bool) env('ALLOW_REGISTRATION', true),
];

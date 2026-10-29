<?php

return [
    /*
     * Whether new accounts can be created. The very first account can always be created, so a fresh
     * install can be claimed; after that, set ALLOW_REGISTRATION=false to keep strangers out.
     */
    'registration' => (bool) env('ALLOW_REGISTRATION', true),

    /*
     * The most campaigns one account may keep, so a single account can't fill the server's disk.
     */
    'max_campaigns' => (int) env('MAX_CAMPAIGNS', 25),

    /*
     * Sign-up forms submitted faster than this (in seconds) after the page loaded are treated as bots.
     */
    'signup_min_seconds' => (int) env('SIGNUP_MIN_SECONDS', 2),
];

<?php

return [
    // General
    'install' => 'Install Ci4ms',
    'next' => 'Next',
    'previous' => 'Previous',
    'submit' => 'Submit',

    // Steps
    'superUserInformation' => 'Super User Information',
    'databaseInformation' => 'Database Information',
    'siteInformation' => 'Site Information',

    // Super User Information
    'yourName' => 'Your Name',
    'surname' => 'Surname',
    'email' => 'E-mail',
    'passwordMinLength' => 'Password (min 8 characters)',
    'generatePassword' => 'Generate Password',

    // Database Information
    'databaseHost' => 'Database Host',
    'databaseName' => 'Database Name',
    'databaseUsername' => 'Database Username',
    'databasePassword' => 'Database Password',
    'databaseDriver' => 'Database Driver',
    'databasePrefix' => 'Database Prefix',
    'databasePort' => 'Database Port',

    // Site Information
    'siteUrl' => 'Site URL',
    'siteSlogan' => 'Site Slogan',

    // Placeholders
    'siteNamePlaceholder' => 'Your site name here',
    'siteSloganPlaceholder' => 'Your slogan here',
    'routeFileError' => 'Routes file could not be created.',
    'baseUrl' => 'Base Url',
    'firstName' => 'First Name',
    'lastName' => 'Last Name',
    'password' => 'Password',
    'foldersWithSameNameListItem' => 'Folders With Same Name List Item',
    'invalidNonce' => 'The install session is missing or expired. Reload this page and submit the form again.',
    'geoLookup' => 'Enable session location tracking (local GeoIP database)',
    'geoLookupHint' => 'Derives approximate city/country for login sessions locally — no data is sent to third parties. Requires downloading the free DB-IP database after install: php spark ci4ms:geoip-update. Uses "IP Geolocation by DB-IP" (CC BY 4.0); attribution required if enabled.',

    // DevGate one-time credential disclosure
    'devGateCredentialsTitle' => 'DevGate Access Credentials',
    'devGateCredentialsWarning' => 'This password is shown once and cannot be recovered. Save it now.',
    'devGateCredentialsNote' => 'DevGate is a separate development Basic-Auth gate and is unrelated to your admin account password.',
    'devGateCredentialsUsername' => 'Username',
    'devGateCredentialsPassword' => 'Password',
    'devGateCredentialsContinue' => 'Continue to site',
];

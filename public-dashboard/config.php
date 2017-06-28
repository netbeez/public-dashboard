<?php
/**
 *
 * Configuration variables for your NetBeez network status dashboard
 *
 */

//Optional error display for debugging (set to false for production)
ini_set( "display_errors", false );

//Company/organization name (used for the site title)
define("COMPANY_NAME", "Acme Company");

//Site description (keep it short!)
define("SITE_DESCRIPTION", "This description is declared in the config file!");

//The URL of this site
define("SITE_URL", "http://172.30.0.49/public-dashboard-github/public-dashboard/");

//The host address of the NetBeez API (this is usually your NB dashboard's hostname)
define("API_HOST", "https://172.30.0.7:3001");

//The NetBeez API version
define("API_VERSION", "v1");

//Your authentication key for accessing the API
define("API_AUTH_KEY", "dc37ae6ff9d7fb875f131d0832a990310a8ec769");

//Boolean setting for cURL option to verify the SSL host
define("SSL_VERIFY_HOST", false);

//Boolean setting for cURL option to verify the SSL peer.
//Default is false due to issue with certificate configuration on internal NetBeez instances
define("SSL_VERIFY_PEER", false);


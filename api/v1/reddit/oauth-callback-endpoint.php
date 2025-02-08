<?php

namespace Radle\API\v1\Reddit;

use WP_REST_Controller;
use WP_Error;
use Radle\Modules\Reddit\Reddit_API;

/**
 * Handles REST API endpoints for Reddit OAuth callback processing.
 * 
 * This class manages the OAuth2 callback from Reddit, processing the
 * authorization code and storing the resulting access tokens. It supports
 * different redirect paths based on the authorization context (welcome
 * wizard or settings page).
 * 
 * @since 1.0.0
 */
class OAuth_Callback_Endpoint extends WP_REST_Controller {

    /**
     * The namespace for the REST API endpoints.
     * @var string
     */
    protected $namespace;

    /**
     * The base path for the REST API endpoints.
     * @var string
     */
    protected $rest_base;

    /**
     * Initialize the endpoint and register routes.
     * 
     * Sets up the REST API endpoint for handling OAuth callbacks.
     * This endpoint must be publicly accessible to receive Reddit's
     * authorization response.
     */
    public function __construct() {
        $this->namespace = 'radle/v1';
        $this->rest_base = 'reddit/oauth-callback';

        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'handle_callback'],
                'permission_callback' => function($request) {
                    // Verify required OAuth parameters are present
                    if (!isset($_GET['code']) || !isset($_GET['state'])) {
                        return false;
                    }

                    // Verify state parameter matches our stored state
                    $saved_state = get_transient('radle_oauth_state');
                    
                    // State parameter is our primary security check
                    return !empty($saved_state) && $_GET['state'] === $saved_state;
                }
            ],
        ]);
    }

    /**
     * Process the OAuth callback from Reddit.
     * 
     * Handles the authorization response from Reddit, including:
     * 1. Processing the authorization code
     * 2. Obtaining access tokens
     * 3. Redirecting to appropriate page based on context
     * 
     * The 'state' parameter determines the redirect destination:
     * - 'welcome': Redirects to welcome wizard step 7
     * - other: Redirects to Reddit settings tab
     * 
     * @param \WP_REST_Request $request Request object containing authorization code and state.
     * @return \WP_Error|void Error on failure, redirects on success.
     */
    public function handle_callback($request) {
        global $radleLogs;

        $radleLogs->log("Handling Reddit OAuth callback", 'api');

        // Process authorization response
        $redditAPI = Reddit_API::getInstance();
        $result = $redditAPI->handle_authorization_response();

        // Handle authorization errors
        if (is_wp_error($result)) {
            $error_message = $result->get_error_message();
            $radleLogs->log("Reddit OAuth error: $error_message", 'api');
            return new WP_Error(
                'oauth_error',
                $error_message,
                ['status' => 400]
            );
        }

        // Determine redirect destination
        $redirect_to = $request->get_param('state');
        if ($redirect_to === 'welcome') {
            $redirect_url = admin_url('admin.php?page=radle-welcome&step=7');
            $radleLogs->log("Redirecting to welcome page after successful OAuth", 'api');
        } else {
            $redirect_url = admin_url('options-general.php?page=radle-settings&tab=reddit');
            $radleLogs->log("Redirecting to settings page after successful OAuth", 'api');
        }

        wp_redirect($redirect_url);
        exit;
    }
}
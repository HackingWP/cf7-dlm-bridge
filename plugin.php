<?php
/**
 * Plugin Name: Contact Form 7 to Download Monitor Bridge
 * Plugin URI: https://github.com/attitude/dlm-cf7-bridge
 * Description: Sends tokens to allow downloads of member only files
 * Version:  v0.1.0
 * Author: @martin_adamko
 * Author URI: http://twitter.com/martin_adamko
 * License: MIT
 */

class DLM_CF7_Bridge
{
    private static $instance;

    private $tokenAPI;

    /**
     * Returns instance of this class
     *
     * @param void
     * @return object Instance
     *
     */
    public static function getInstance()
    {
        if (!isset(self::$instance) && !(self::$instance instanceof DLM_CF7_Bridge)) {
            self::checkDependencies();
            self::$instance = new DLM_CF7_Bridge;
        }

        return self::$instance;
    }

    /**
     * Tries to load this plugin but does not halt system on fatal error/exception
     *
     * Plugin activation check does not check plugin dependencies on during use, hence
     * this method.
     *
     * @param void
     * @return void
     *
     */
    public static function maybeLoad()
    {
        try {
            self::getInstance();
        } catch (Exception $e) {
            trigger_error('DLM_CF7_Bridge: Failed to load. Reason: '.$e->getMessage());
        }
    }

    /**
     * Run check on activation
     *
     * @param void
     * @return void
     *
     */
    public static function checkDependencies()
    {
        if (!class_exists('WP_DLM')) {
            throw new Exception('DLM_CF7_Bridge: Download Monitor is required');
        }

        if (!class_exists('WPCF7_ContactForm')) {
            throw new Exception('DLM_CF7_Bridge: Contact Form 7 is required');
        }

        if (!class_exists('WP_AccessTokenAPI')) {
            throw new Exception('DLM_CF7_Bridge: Access Token API is required');
        }
    }

    /**
     * Object constructor (init)
     *
     * @param void
     * @return void
     *
     */
    private function __construct()
    {
        $this->tokenAPI = WP_AccessTokenAPI::getInstance();

        add_filter('wpcf7_additional_mail', array($this, 'filterAdditionalMail'), 10, 1);
        add_filter('dlm_can_download', array($this, 'canDownload'), 10, 3);

        add_shortcode( 'cf7_dlm_download', array($this, 'addEnclosingShortCode'));
    }

    /**
     * Filters the 2nd CF7 mail for download shortcode
     *
     * @param array $additional_mail Mail parameters to compose new message
     * @return array                 Modified mail parameters.
     *
     */
    public function filterAdditionalMail($additional_mail)
    {
        if (isset($additional_mail) && isset($additional_mail['mail_2']) && isset($additional_mail['mail_2']['body'])) {
            $additional_mail['mail_2']['body'] = do_shortcode(
                wpcf7_mail_replace_tags(
                    $additional_mail['mail_2']['body'],
                    array( 'html' => !!$additional_mail['mail_2']['use_html'])
                )
            );
        }

        return $additional_mail;
    }

    /**
     * Create action string required for token API
     */
    private function getActionString($id, $versionId)
    {
        return 'access-download{id:'.$id.',versionid:'.$versionId.'}';
    }

    /**
     * Allows access to download using token passed in URL
     *
     * @param bool $can        Original result.
     * @param object $download Parent download object.
     * @param object $version  Current version selected to download.
     *
     */
    public function canDownload( $can, $download, $version )
    {
        $token  = isset($_GET['token']) && is_string($_GET['token']) && strlen(trim($_GET['token'])) > 0 ? trim($_GET['token']) : null;
        $versions = $download->get_file_versions();
        $vid = $version->id;

        if (isset($_GET['version']) && is_string($_GET['version']) && strlen(trim($_GET['version'])) > 0) {
            // Force the version against its ID
            foreach($versions as &$v) {
                if (str_replace(' ', '', $v->version) === str_replace(' ', '', $_GET['version'])) {
                    $vid = $v->id;
                }
            }
        }

        if($token && $download->is_members_only()) {
            $action = $this->getActionString($download->id, $vid);

            // trigger_error(json_encode(array($download, $version, $token, $this->getActionString($download->id, $vid))));

            return $this->tokenAPI->validate($action, $token);
        }

        return $can;
    }

    /**
     * Shortcode to get list of downloads
     *
     * @param array $args     Shortcode parameters
     * @param string $content Enclosed shortcode `$content`. Use checkboxes or radio buttons with download version names to fill the `$content`.
     * @returns string        Generated list of download versions enhanced with tokens
     *
     * Example: [cf7_dlm_download id="199" ttl="30"][checkbox-download-versions][/cf7_dlm_download]
     */
    public function addEnclosingShortCode($args, $content = null)
    {
        global $download_monitor;

        $id      = (int) $args['id'];
        $ttl     = (int) @$args['ttl'];
        $retries = (int) @$args['retries'];
        $html    = !! @$args['html'];

        // Change defaults
        if ($ttl <= 0) {
            $ttl = apply_filters('DLM_CF7_Bridge_ttl', 15);
        }

        // Change defaults
        if ($retries <= 0) {
            $retries = apply_filters('DLM_CF7_Bridge_retries', 1);
        }

        // Setup Downlaod Monitor download...
        $download = new DLM_Download($id);

        // ... and versions
        $versions = $download->get_file_versions();

        $links = '';

        $content = str_replace(' ', '', mb_strtolower($content));

        foreach ($versions as $v) {
            if (empty($content) && empty($v->version) || strstr($content, str_replace(' ', '', $v->version))) {
                // Try and silently (?) fail
                try {
                    // Generate token
                    $token = $this->tokenAPI->set($this->getActionString($id, $v->id), $ttl, $retries);

                    // trigger_error(json_encode(array($token, $this->getActionString($id, $v->id))));

                    // Iterate current version data
                    $download->set_version($v->id);
                    $link = $download->get_the_download_link();

                    // Add token for members only downloads
                    if ($download->is_members_only()) {
                        $link = strstr($link, '?') ? $link.'&token='.$token : $link.'?token='.$token;
                    }

                    if ($html) {
                        $links .= '<li><a href="' . $link .'">' . (!empty($v->version) ? mb_strtoupper($v->version) : $download->post->post_title) . '</a>';
                    } else {
                        $links .= '- '.(!empty($v->version) ? mb_strtoupper($v->version) : $download->post->post_title).":\r\n  ";
                        $links .= $link."\r\n";
                    }

                    $links .= "\r\n";
                } catch (Exception $e) {
                    trigger_error('DLM_CF7_Bridge: Failed to get token for `'.$this->getActionString($id, $v->id).'` action. Reason: '.$e->getMessage());
                }
            }
        }

        return trim($links);
    }
}

register_activation_hook( __FILE__, array('DLM_CF7_Bridge', 'checkDependencies'));
add_action('init', array('DLM_CF7_Bridge', 'maybeLoad'));

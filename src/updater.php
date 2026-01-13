<?php

class WPN_Updater {
    private $file;
    private $plugin;
    private $basename;
    private $active;
    private $username;
    private $repository;
    private $github_response;

    public function __construct($file) {
        $this->file = $file;
        $this->plugin = plugin_basename($this->file);
        $this->basename = plugin_basename($this->file);
        $this->active = is_plugin_active($this->plugin);
        $this->username = 'ann61c';
        $this->repository = 'wp-neodb';

        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);
        add_filter('plugins_api', [$this, 'plugin_popup'], 10, 3);
        add_filter('upgrader_source_selection', [$this, 'upgrader_source_selection'], 10, 4);
    }

    private function get_repository_info() {
        if (!is_null($this->github_response)) {
            return $this->github_response;
        }

        // Check transient cache first
        $cache_key = 'wpn_github_release_info';
        $cached = get_transient($cache_key);
        
        if ($cached) {
            $this->github_response = $cached;
            return $this->github_response;
        }

        $request_uri = sprintf('https://api.github.com/repos/%s/%s/releases', $this->username, $this->repository);

        $response = wp_remote_get($request_uri);

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            return false;
        }

        $response_body = wp_remote_retrieve_body($response);
        $releases = json_decode($response_body);

        if (is_array($releases) && !empty($releases)) {
            // Get the latest release (first item is usually latest)
            $this->github_response = $releases[0];
            
            // Cache the response for 12 hours
            set_transient($cache_key, $this->github_response, 12 * HOUR_IN_SECONDS);
            
            return $this->github_response;
        }

        return false;
    }

    public function check_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $release = $this->get_repository_info();

        if ($release) {
            $new_version = $release->tag_name;
            // Remove 'v' prefix if present
            $new_version = ltrim($new_version, 'v');
            $current_version = isset($transient->checked[$this->plugin]) ? $transient->checked[$this->plugin] : '0';

            if (version_compare($new_version, $current_version, '>')) {
                $obj = new stdClass();
                $obj->slug = $this->basename;
                $obj->new_version = $new_version;
                $obj->url = $release->html_url;
                $obj->package = $release->zipball_url;
                
                // If there's an asset named 'wp-neodb.zip', use that instead of source code
                if (!empty($release->assets)) {
                    foreach ($release->assets as $asset) {
                        if ($asset->name === 'wp-neodb.zip') {
                            $obj->package = $asset->browser_download_url;
                            break;
                        }
                    }
                }

                $transient->response[$this->plugin] = $obj;
            }
        }

        return $transient;
    }

    public function plugin_popup($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if ($args->slug !== $this->basename) {
            return $result;
        }

        $release = $this->get_repository_info();

        if ($release) {
            $plugin_data = get_plugin_data($this->file);
            $new_version = ltrim($release->tag_name, 'v');
            
            $res = new stdClass();
            $res->name = $plugin_data['Name'];
            $res->slug = $this->basename;
            $res->version = $new_version;
            $res->author = $plugin_data['AuthorName'];
            $res->author_profile = $plugin_data['AuthorURI'];
            $res->homepage = $plugin_data['PluginURI'];
            $res->last_updated = $release->published_at;
            
            // GitHub API doesn't provide tested/requires fields in the standard release object
            // We could parse readme.txt from the repo, but for simplicity we'll omit them or use current
            // $res->tested = ...; 
            // $res->requires = ...;

            $description = $release->body;
            $parsedown = new Parsedown();
            $changelog = $parsedown->text($description);

            $res->sections = [
                'description' => $plugin_data['Description'], // Use local description
                'changelog' => $changelog
            ];
            
            // Try to set download link if available
             $res->download_link = $release->zipball_url;
            if (!empty($release->assets)) {
                foreach ($release->assets as $asset) {
                    if ($asset->name === 'wp-neodb.zip') {
                        $res->download_link = $asset->browser_download_url;
                        break;
                    }
                }
            }

            return $res;
        }

        return $result;
    }

    public function upgrader_source_selection($source, $remote_source, $upgrader, $hook_extra = null) {
        global $wp_filesystem;

        // Exit if not our plugin
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin) {
            return $source;
        }

        // The destination folder name we want
        $proper_folder_name = dirname($this->basename); // "wp-neodb"
        
        // The current folder name where files are extracted
        $current_folder_name = basename(untrailingslashit($source));

        // If it's already correct, do nothing
        if ($current_folder_name === $proper_folder_name) {
            return $source;
        }

        // Construct the new correct source path
        $new_source = trailingslashit(dirname($source)) . $proper_folder_name;

        // Move files from random github-name-folder to wp-neodb
        if ($wp_filesystem->move($source, $new_source)) {
            return trailingslashit($new_source);
        }

        // If move failed, return original source (better than crashing, though might still fail validation)
        return $source;
    }
}

// Minimal Parsedown implementation for basic Markdown to HTML conversion
// Needed because GitHub releases are in Markdown
if (!class_exists('Parsedown')) {
    class Parsedown {
        function text($text) {
            // Very basic markdown parsing
            $text = htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');
            
            // Headers
            $text = preg_replace('/^# (.*?)$/m', '<h1>$1</h1>', $text);
            $text = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $text);
            $text = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $text);
            
            // Bold
            $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
            
            // Italic
            $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
            
            // Configurable lists
            $text = preg_replace('/^\s*-\s(.*?)$/m', '<li>$1</li>', $text);
            $text = preg_replace('/(<li>.*?<\/li>)/s', '<ul>$1</ul>', $text);
            // Fix multiple uls
            $text = preg_replace('/<\/ul>\s*<ul>/', '', $text);
            
            // Links
            $text = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2">$1</a>', $text);
            
            // Newlines
            $text = nl2br($text);
            
            return $text;
        }
    }
}

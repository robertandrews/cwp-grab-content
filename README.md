# CWP Grab Content Plugin

This is a WordPress plugin that allows you to grab content from a URL and add it to your site.

## Plugin Information

- Plugin Name: CWP Grab Content
- Plugin URI: [http://www.contexthq.com](http://www.contexthq.com)
- Description: This plugin will grab the content from a URL and add it to your site.
- Version: 1.0.0
- Author: Robert Andrews
- Author URI: [http://www.contexthq.com](http://www.contexthq.com)

## Setup Instructions

1. Set the maximum execution time and time limit in the plugin using the following code:
    ```php
    ini_set('max_execution_time', 3000); // 300 seconds = 5 minutes
    set_time_limit(3000);
    ```

2. Override the default HTTP request timeout using the `http_request_timeout` filter:
    ```php
    // override timeout
    add_filter('http_request_timeout', function () {
        return 30000; // 300 seconds = 5 minutes
    });
    ```

3. Include the required autoload file:
    ```php
    require __DIR__ . '/vendor/autoload.php';
    ```

4. Register the Content Grabber page in the WordPress admin menu:
    ```php
    add_action('admin_menu', 'content_grabber_admin_menu');
    ```

5. Implement the `content_grabber_admin_menu` and `content_grabber_admin_page` functions to handle the content grabbing process and display the plugin page.

## Usage

1. Access the "Grab New" page in the WordPress admin menu.
2. Enter the URL of the article you want to grab in the provided input field.
3. Click the "Fetch Post/s" button to initiate the content grabbing process.

Note: The plugin uses either the WordPress API or Graby parsing to retrieve the content from the URL, depending on whether the URL is a WordPress post or not.

## License

This plugin is licensed under the [MIT License](LICENSE).

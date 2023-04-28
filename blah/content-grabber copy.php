<?php
/**
 * Plugin Name: WP Content Grabber
 * Plugin URI: http://www.contexthq.com
 * Description: This plugin will grab the content from a URL and add it to your site.
 * Version: 1.0.0
 * Author: Robert Andrews
 * Author URI: http://www.contexthq.com
 */

add_action('admin_menu', 'register_grabber_admin_page');
function register_grabber_admin_page()
{
    /**
     * Register the Content Grabber page
     * @return void
     */
    add_posts_page(
        'Grab New', // Page title
        'Grab New', // Menu title
        'manage_options', // Capability required to access the page
        'content-grabber', // Page slug
        'display_grabber_admin_page', // Callback function to display the page content
        2// Position of the menu item
    );
}

function display_grabber_admin_page()
{
    /**
     * Display the Content Grabber page content
     * @return void
     */
    ?>
<div class="wrap">
    <h2>Content Grabber</h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <p>Enter the URL of the article you want to copy:</p>
        <input type="hidden" name="action" value="grab_article_action">
        <?php wp_nonce_field('grab_article', 'grab_article_nonce');?>
        <input type="text" name="article_url" value="" size="100%" />
        <input type="submit" name="submit" value="Grab Article" class="button button-primary" />
    </form>
</div>
<?php
}

function grab_article()
{
    /**
     * Grab article from URL
     * @return void
     */

    // Check the nonce
    if (!isset($_POST['grab_article_nonce']) || !wp_verify_nonce($_POST['grab_article_nonce'], 'grab_article')) {
        print 'Sorry, your nonce did not verify.';
        exit;
    } else {
        // process form data
    }

    // Form was posted
    if (isset($_POST['submit'])) {
        $article_url = $_POST['article_url'];

        // Retrieve the page source code
        $response = wp_remote_get($article_url);
        $body = wp_remote_retrieve_body($response);

        // Check for eg. /wp-json/wp/v2/{something}/ID in article source
        $posts_endpoint = find_post_endpoint($body);
        if ($posts_endpoint) {
            $posts_response = api_call_posts($post_endpoint, $article_url);
        }

        // Wordpress: Check if REST API is open
        if (wp_api_exists($article_url)) {
            // TODO: Look first directly for eg. /wp-json/wp/v2/{something}/ID

            // Otherwise, try to *find* the ID first...

            // Get post's ID
            $wp_post_id = wp_get_post_id_from_url($article_url);
            if ($wp_post_id) {
                // Grab the post, add it
                $post_json = wp_get_json_post($article_url, $wp_post_id);
                if ($post_json) {
                    add_new_post_from_remote($post_json);
                }
            } else {
                print_r('No post ID found');
            }
        } else {
            print_r('API not found');}

    }
}
add_action('admin_post_grab_article_action', 'grab_article');

function find_post_endpoint($body)
{
    if (preg_match('/wp-json/wp/v2/(?!(media|categories|tags)/)\w+/\d+', $body, $matches)) {
        $post_endpoint = $matches[1];
        echo 'The post endpoint is: ' . $post_endpoint;
        return $post_endpoint;
    } else {
        echo 'No post endpoint found in body.';
    }

}

function wp_api_exists($article_url)
{
    /**
     * Check if the URL has a WP JSON API endpoint
     * @param string $article_url
     * @return bool
     */
    $domain = parse_url($article_url, PHP_URL_SCHEME) . '://' . parse_url($article_url, PHP_URL_HOST);
    $api_base_url = $domain . '/wp-json';
    $response = wp_remote_get($api_base_url);
    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code === 200) {
        return true;
    }
    return false;
}

function api_call_posts($post_endpoint, $article_url)
{

    // API parameters
    $api_params = '_embed=wp:term,wp:featuredmedia,author';

    // If $post_endpoint is a relative URL, make full URL
    if (strpos($post_endpoint, 'http') !== 0) {
        $domain = parse_url($article_url, PHP_URL_SCHEME) . '://' . parse_url($article_url, PHP_URL_HOST);
        $api_url_to_call = $domain . $post_endpoint . '?' . $api_params;
    } else {
        $api_url_to_call = $post_endpoint . '?' . $api_params;
    }

    // Call the API, /posts
    $response = wp_remote_get($api_base_url);

    if (!is_wp_error($response) && 200 === wp_remote_retrieve_response_code($response)) {

        // Send post JSON back
        $posts_json = json_decode(wp_remote_retrieve_body($response));
        return $posts_json;

    } else {
        // Handle error.
        return false;
    }

}

function wp_get_post_id_from_url($article_url)
{
    /**
     * Get the WordPress post ID from the URL
     * @param string $article_url
     * @return int
     */
    // use wp_remote_get to retrieve the page source code
    $response = wp_remote_get($article_url);
    $body = wp_remote_retrieve_body($response);

    // use a regular expression to find the post ID in the source code
    $pattern = '/\/wp-json\/wp\/v2\/posts\/(\d+)/';
    preg_match($pattern, $body, $matches);
    $post_id = $matches[1];

    return $post_id;
}

function wp_get_json_post($article_url, $wp_post_id)
{
    /**
     * Get the post data from the WP JSON API
     * @param string $article_url
     * @return object
     */

    // Generate /posts endpoint URL, eg. https://techcrunch.com/wp-json/wp/v2/posts/2525617?_embed=wp:term
    $domain = parse_url($article_url, PHP_URL_SCHEME) . '://' . parse_url($article_url, PHP_URL_HOST);
    $api_base_url = $domain . '/wp-json';
    $api_endpoint = '/wp/v2/posts';
    $api_params = '_embed=wp:term,wp:featuredmedia,author';
    $api_url = $api_base_url . $api_endpoint . '/' . $wp_post_id . '?' . $api_params;

    // use wp_remote_get to retrieve the post data
    $response = wp_remote_get($api_url);
    /*
    echo '<pre>';
    print_r($response);
    echo '</pre>';
     */

    if (!is_wp_error($response) && 200 === wp_remote_retrieve_response_code($response)) {

        $post_json = json_decode(wp_remote_retrieve_body($response));

        return $post_json;

    } else {
        // Handle error.
    }

}

function add_new_post_from_remote($post_json)
{

    // Get slug
    if ($post_json->slug) {
        $slug = $post_json->slug;
    } else {
        $slug = '';
    }
    // Get title
    if ($post_json->title->rendered) {
        $title = html_entity_decode($post_json->title->rendered, ENT_QUOTES, 'UTF-8');
    } else {
        $title = '';
    }
    // Get date
    if ($post_json->date) {
        $post_date = $post_json->date;
    } else {
        $post_json = date('Y-m-d H:i:s');
    }
    // Get excerpt
    if ($post_json->excerpt->rendered) {
        $excerpt = html_entity_decode($post_json->excerpt->rendered, ENT_QUOTES, 'UTF-8');
    } else {
        $excerpt = '';
    }
    // Get content
    if ($post_json->content->rendered) {
        $content = html_entity_decode($post_json->content->rendered, ENT_QUOTES, 'UTF-8');
    } else {
        $content = '';
    }
    if ($post_json->_embedded->author) {
        // $author_name = $post_json->_embedded->author[0]->name;
        $author_object = $post_json->_embedded->author[0];
        $assigned_author_id = assign_author($author_object);
    }

    // Add post to WordPress
    $created_post_id = wp_insert_post(array(
        'post_type' => 'post',
        'post_author' => $assigned_author_id,
        'post_name' => $slug,
        'post_title' => $title,
        'post_date' => $post_date,
        'post_excerpt' => $excerpt,
        'post_content' => $content,
        'post_status' => 'publish',
    ));

    if ($created_post_id) {
        // print_r('Post added');
        wp_get_featured_media($post_json, $created_post_id);
    } else {
        // print_r('Post not added');
    }

    // Go to post edit page for the new post
    // wp_redirect(admin_url('post.php?post=' . $post_id . '&action=edit'));

}

function assign_author($author_object)
{
    /**
     * Get (or generate new) author ID from the found author name
     * @param string $author_name
     * @return int
     */

    // Search for user matching the discovered name
    $matched_users = get_users(array(
        'search' => $author_object->name,
        'search_columns' => array('display_name'),
    ));

    // Found: get the id
    if (!empty($matched_users)) {
        $user = $matched_users[0]; // Assume the first user is the one we want
        $user_id = $user->ID;
        do_my_log("User found: " . $user_id);
    } else {
        do_my_log("User not found - creating...");

        // Create user from found author name
        $user_id = wp_create_user(sanitize_title($author_object->name), wp_generate_password());

        // Add more specific profile details
        $author_name_parts = explode(' ', $author_object->name);
        $first_name = $author_name_parts[0];
        $last_name = end($author_name_parts);
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $author_object->name,
            'nickname' => $author_object->name,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'role' => 'contributor',
            // 'description' => $author_object->description,
        ));
    }

    return $user_id;

}

function wp_get_featured_media($post_json, $created_post_id)
{
    if ($post_json->_embedded->{'wp:featuredmedia'}) {
        // print_r('Featured image found...');
        $file_url = $post_json->_embedded->{'wp:featuredmedia'}[0]->media_details->sizes->full->source_url;
        print_r($file_url);
        // Download file and add it to the post
        $upload_dir = wp_upload_dir();
        $image_data = wp_remote_get($file_url);
        $filename = basename($file_url);
        if (wp_mkdir_p($upload_dir['path'])) {
            $file = $upload_dir['path'] . '/' . $filename;
        } else {
            $file = $upload_dir['basedir'] . '/' . $filename;
        }
        file_put_contents($file, $image_data['body']);
        $wp_filetype = wp_check_filetype($filename, null);
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit',
        );
        $attach_id = wp_insert_attachment($attachment, $file, $created_post_id);
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata($attach_id, $file);
        $res1 = wp_update_attachment_metadata($attach_id, $attach_data);
        $res2 = set_post_thumbnail($created_post_id, $attach_id);
        // re-save the post to update the post thumbnail
        $post = array(
            'ID' => $created_post_id,
            // 'post_content' => $post_json->content->rendered,
        );
        wp_update_post($post);
        wp_redirect(admin_url('post.php?post=' . $created_post_id . '&action=edit'));

    } else {
        // print_r('No featured image found');
        // Try a /media/ look-up
        if ($post_json->featured_media) {
            $featured_media_id = $post_json->featured_media;
            $api_endpoint_media = '/wp/v2/media';
            $api_url = $api_base_url . $api_endpoint_media . '/' . $featured_media_id;
            $response = wp_remote_get($api_url);
        }
    }

}
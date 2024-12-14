<?php
/**
 * Plugin Name: CWP Grab Content
 * Plugin URI: http://www.contexthq.com
 * Description: A plugin to grab content from a URL and add it to your WordPress site. It allows you to quickly fetch and display content from external sources for integration into your WordPress site.
 * Version: 1.0.0
 * Author: Robert Andrews
 * Author URI: http://www.contexthq.com
 * License: GPL2
 * Text Domain: cwp-grab-content
 * Domain Path: /languages
 */

ini_set('max_execution_time', 3000); // 300 seconds = 5 minutes
set_time_limit(3000);
// override timeout
add_filter('http_request_timeout', function () {
    return 30000; // 300 seconds = 5 minutes
});

require __DIR__ . '/vendor/autoload.php';
use Graby\Graby;

add_action('admin_menu', 'content_grabber_admin_menu');
function content_grabber_admin_menu()
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
        'content_grabber_admin_page', // Callback function to display the page content
        2// Position of the menu item
    );
}

function content_grabber_admin_page()
{
    /**
     * Display the Content Grabber page content
     * @return void
     */

    // If this was a -submitting form, process it
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // Get the content of the 'content_url' input field
        $content_url = esc_url_raw($_POST['content_url']);

        // If no error
        if (!is_wp_error($content_url)) {

            // Get content from either WordPress API discovery or Graby parsing
            $post_package = get_article_content($content_url);

            // Add new post
            if ($post_package) {
                echo "we have a post package, lets add a post...\n";
                $created_post_id = content_grabber_add_post($post_package, $content_url);
                /*
            if ($created_post_id) {
            return $created_post_id;
            wp_redirect(admin_url('post.php?post=' . $created_post_id . '&action=edit'));

            }
             */
            } else {
                // echo "Could not add new post.";
            }

        }

    }
    ?>
<div class="wrap">
    <h2>Content Grabber</h2>
    <form method="post" action="">
        <table class="form-table" width="100%">
            <tr>
                <th>Aricle/s URL</th>
                <td>
                    <input type="text" name="content_url" class="regular-text" required style="width:100%">
                </td>
            </tr>
        </table>
        <?php submit_button('Fetch Post/s');?>
    </form>
</div>
<?php
}

function get_article_content($content_url)
{

    // 🔻 Retrieve the page source code
    $response = wp_remote_get($content_url);
    $body = wp_remote_retrieve_body($response);

    // Two options for getting the post content:

    // 1. WordPress - Look for WordPress API endpoint for individual post, eg. /wp-json/wp/v2/posts/2525617
    if ($wp_post_api_url = wp_find_post_api_url($body)) {

        print_r("WordPress discovered, found post endpoint " . $wp_post_api_url . "\n");

        $path = parse_url($wp_post_api_url, PHP_URL_PATH);
        $segments = explode('/', $path);
        // Get post type
        $post_type = $segments[4];
        // Get post ID
        $post_id = $segments[5];

        // Call /posts/ for a single post
        $post_json = wp_do_api_posts($post_type, $post_id, $content_url);

        // Package-up /posts/ response to set as new WP post
        $post_package = package_wp_post($post_json);

        // print_r($post_json);

        return $post_package;

    } else {
        // 2. Not WordPress: Use Graby to parse the page

        echo "Could not find WordPress, using Graby.\n";

        // Use Graby to parse the article
        $graby = new Graby();
        $result = $graby->fetchContent($content_url);

        // Package-up Graby response to set as new WP post
        $post_package = package_graby_post($result);

        return $post_package;

    }

}

function wp_find_post_api_url($body)
{
    // 1. Some post markup contains the actual endpoint
    // eg. /wp-json/wp/v2/posts/2525617 & /wp-json/wp/v2/news/1337292
    if (preg_match('/\/wp-json\/wp\/v2\/(?!.*media|categories|tags).*\/\d+(?=")/', $body, $matches)) {
        $wp_post_api_url = $matches[0];
        // echo 'The post endpoint is: ' . $wp_post_api_url;
        return $wp_post_api_url;
        // 2. Others hide it for security, even if the WP-API is still accessible
        // This hides the endpoint, but we can try to guess it if we can find the post ID
    } elseif (preg_match('/postid-(\d{3,})/', $body, $matches)) {
        $post_id = $matches[1];
        $wp_post_api_url = '/wp-json/wp/v2/posts/' . $post_id;
        // print_r($wp_post_api_url);
        return $wp_post_api_url;
        // 3. Failed to find or generate the endpoint
    } else {
        echo 'No post endpoint found in body.';
        return false;
    }
}

function wp_find_post_id_body($body)
{

    if (preg_match('/postid-(\d{3,})/', $body, $matches)) {
        $post_id = $matches[1];
        // echo "Post ID found: " . $post_id;
    } else {
        // echo "No post ID found";
    }

}

function wp_do_api_posts($post_type, $post_id, $content_url)
{

    // Construct API URL
    // eg. http://www.techcrunch.com
    $domain = parse_url($content_url, PHP_URL_SCHEME) . '://' . parse_url($content_url, PHP_URL_HOST);
    // eg. http: //www.techcrunch.com/wp-json/wp/v2/posts/2525617
    $posts_endpoint = trailingslashit($domain) . 'wp-json/wp/v2/' . trailingslashit($post_type);
    // for single post call
    if ($post_id) {
        $posts_endpoint .= $post_id;
        $posts_endpoint .= '?_embed';
    }

    // Call the API, /posts
    $response = wp_remote_get($posts_endpoint);
    if (!is_wp_error($response) && 200 === wp_remote_retrieve_response_code($response)) {
        // Send post JSON back
        $posts_json = json_decode(wp_remote_retrieve_body($response));
        return $posts_json;
    } else {
        // Handle error.
        return false;
    }

}

function package_wp_post($posts_json)
{

    $post_data = array();

    // Get slug
    $post_data['slug'] = isset($posts_json->slug) ? $posts_json->slug : '';
    // Get title
    $post_data['title'] = isset($posts_json->title->rendered) ? html_entity_decode($posts_json->title->rendered, ENT_QUOTES, 'UTF-8') : '';
    // Get date
    $post_data['date'] = isset($posts_json->date) ? $posts_json->date : date('Y-m-d H:i:s');
    // Get excerpt
    // TODO: Strip HTML
    $post_data['excerpt'] = isset($posts_json->excerpt->rendered) ? strip_tags(html_entity_decode($posts_json->excerpt->rendered, ENT_QUOTES, 'UTF-8')) : '';
    // Get content
    $post_data['content'] = isset($posts_json->content->rendered) ? html_entity_decode($posts_json->content->rendered, ENT_QUOTES, 'UTF-8') : '';
    // Get author name
    $post_data['author_name'] = isset($posts_json->_embedded->author[0]->name)
    ? html_entity_decode($posts_json->_embedded->author[0]->name, ENT_QUOTES, 'UTF-8')
    : (isset($posts_json->yoast_head_json->author) ? html_entity_decode($posts_json->yoast_head_json->author, ENT_QUOTES, 'UTF-8') : '');
    // Get featured image URL
    // TODO: If $posts_json->_embedded->{'wp:featuredmedia'}[0]->data->status = 401 (forbidden),,
    // run a /media/ call instead to get the image
    $post_data['feat_img_url'] = isset($posts_json->_embedded->{'wp:featuredmedia'}[0]->media_details->sizes->full->source_url) ? $posts_json->_embedded->{'wp:featuredmedia'}[0]->media_details->sizes->full->source_url : '';

    // Set all the WordPress $post_json wp:term terms matching taxonomy "topic" to the $post_data['topics'] array
    if (isset($posts_json->_embedded->{'wp:term'})) {
        $post_data['topics'] = array();
        foreach ($posts_json->_embedded->{'wp:term'} as $term) {
            foreach ($term as $topic) {
                if ($topic->taxonomy == 'topic') {
                    $post_data['topics'][] = $topic->name;
                }
            }
        }
    }

    // Set all the WordPress $post_json wp:term terms matching taxonomy "series" to the $post_data['series'] array
    if (isset($posts_json->_embedded->{'wp:term'})) {
        $post_data['series'] = array();
        foreach ($posts_json->_embedded->{'wp:term'} as $term) {
            foreach ($term as $series) {
                if ($series->taxonomy == 'series') {
                    $post_data['series'][] = $series->name;
                }
            }
        }
    }

    // Set all the WordPress $post_json wp:term terms matching taxonomy "companies" to the $post_data['companies'] array
    if (isset($posts_json->_embedded->{'wp:term'})) {
        $post_data['companies'] = array();
        foreach ($posts_json->_embedded->{'wp:term'} as $term) {
            foreach ($term as $company) {
                if ($company->taxonomy == 'company') {
                    $post_data['companies'][] = $company->name;
                }
            }
        }
    }

    return $post_data;

}

function package_graby_post($result)
{

    $post_data = array();

    // Get title
    $post_data['title'] = isset($result['title']) ? $result['title'] : '';
    // Get date
    if (isset($result['date'])) {
        $timestamp = strtotime($result['date']);
        $formatted_date = date('Y-m-d\TH:i:s', $timestamp);
        $post_data['date'] = $formatted_date;
    } else {
        $post_data['date'] = date('Y-m-d H:i:s');
    }
    // Get excerpt
    $post_data['excerpt'] = isset($result['summary']) ? $result['summary'] : '';
    // Get content
    $post_data['content'] = isset($result['html']) ? $result['html'] : '';
    // Get author name
    $post_data['author_name'] = isset($result['authors'][0]) ? $result['authors'][0] : '';

    // Get featured image URL
    if ($result['image']) {

        // Strip any image sizing parameters, eg. ...lXbI.jpeg?width=960
        $url_parts = parse_url($result['image']);
        if (isset($url_parts['scheme']) && isset($url_parts['host']) && isset($url_parts['path'])) {
            $url = $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path'];
        }

        // echo $url;

        $post_data['feat_img_url'] = $url = $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path'];

    }

    /*
    echo '<pre>';
    print_r($post_data);
    echo '</pre>';
     */

    return $post_data;

}

function content_grabber_add_post($post_data, $content_url)
{

    // Get author name
    if ($post_data['author_name'] != '') {
        $assigned_author_id = assign_author($post_data['author_name']);
    } else {
        $assigned_author_id = 1;
    }

    // Add post to WordPress
    $created_post_id = wp_insert_post(array(
        'post_type' => 'post',
        'post_author' => $assigned_author_id,
        'post_name' => isset($post_data['slug']) ? $post_data['slug'] : '',
        'post_title' => $post_data['title'],
        'post_date' => isset($post_data['date']) ? $post_data['date'] : '',
        'post_excerpt' => isset($post_data['excerpt']) ? $post_data['excerpt'] : '',
        'post_content' => isset($post_data['content']) ? $post_data['content'] : '',
        'post_status' => 'publish',
    ));

    // Add post extras
    if ($created_post_id) {

        // Set source publication
        wp_set_post_source($created_post_id, $content_url);
        do_my_log("wp_set_post_source");

        // Add article source URL
        update_post_meta($created_post_id, 'source_url', $content_url);
        do_my_log("updated source_url");

        // Add date obtained
        update_post_meta($created_post_id, 'date_obtained', date('Y-m-d H:i:s'));
        do_my_log("updated date");

        wp_set_post_topics($created_post_id, $post_data);
        do_my_log("added topics");

        wp_set_post_series($created_post_id, $post_data);
        do_my_log("added series");

        wp_set_post_companies($created_post_id, $post_data);
        do_my_log("added companies");

        // Add featured image
        if ($post_data['feat_img_url']) {
            wp_add_featured_media($post_data['feat_img_url'], $created_post_id);
        }

        $post = array(
            'ID' => $created_post_id,
        );
        wp_update_post($post);

        return $created_post_id;

    } else {
        // Did not create post
    }
}

function assign_author($found_author_name)
{
    /**
     * Get (or generate new) author ID from the found author name
     * @param string $author_name
     * @return int
     */

    // Search for user matching the discovered name
    $matched_users = get_users(array(
        'search' => $found_author_name,
        'search_columns' => array('display_name'),
    ));

    // Found matching user, get her ID
    if (!empty($matched_users)) {
        $user = $matched_users[0]; // Assume the first user is the one we want
        $user_id = $user->ID;
        // do_my_log("User found: " . $user_id);
    } else {
        // User not found - create
        // do_my_log("User not found - creating...");

        // Create user from found author name
        $user_id = wp_create_user(sanitize_title($found_author_name), wp_generate_password());

        // Add more specific profile details
        $author_name_parts = explode(' ', $found_author_name);
        $first_name = $author_name_parts[0];
        $last_name = end($author_name_parts);
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $found_author_name,
            'nickname' => $found_author_name,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'role' => 'contributor',
        ));
    }

    if ($user_id) {
        return $user_id;
    } else {
        return false;
    }

}

function wp_add_featured_media($file_url, $created_post_id)
{

    // Download file and add it to the post
    $upload_dir = wp_upload_dir();
    $image_data = wp_remote_get($file_url);
    do_my_log("got image_data");
    $filename = basename($file_url);
    if (wp_mkdir_p($upload_dir['path'])) {
        $file = $upload_dir['path'] . '/' . $filename;
    } else {
        $file = $upload_dir['basedir'] . '/' . $filename;
    }
    file_put_contents($file, $image_data['body']);
    do_my_log("put image_data");
    $wp_filetype = wp_check_filetype($filename, null);
    do_my_log("checked file type");
    $attachment = array(
        'post_mime_type' => $wp_filetype['type'], // Is this really necessary?
        'post_title' => sanitize_file_name($filename),
        'post_content' => '',
        'post_status' => 'inherit',
    );
    $attach_id = wp_insert_attachment($attachment, $file, $created_post_id);
    do_my_log("inserted attachment " . $attach_id);
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $attach_data = wp_generate_attachment_metadata($attach_id, $file);
    do_my_log("generated attachment metadata");
    $res1 = wp_update_attachment_metadata($attach_id, $attach_data);
    do_my_log("updated attachment metadata");
    $res2 = set_post_thumbnail($created_post_id, $attach_id);
    do_my_log("set post thumbnail");
    // echo $res2;

    // re-save the post to update the post thumbnail
    $post = array(
        'ID' => $created_post_id,
        // 'post_content' => $post_json->content->rendered,
    );
    // wp_update_post($post);

}

function wp_set_post_source($created_post_id, $content_url)
{

    // Set the new post's source to a slug matching the site's hose

    $host = parse_url($content_url, PHP_URL_HOST); // Get host name
    $pieces = explode(".", $host); // Split host name by dot
    $source_slug = ($pieces[0] === 'www') ? $pieces[1] : $pieces[0]; // Get the first portion of the host name, or the second if the first is "www"
    // echo $source; // Output: "theguardian"

    wp_set_object_terms($created_post_id, $source_slug, 'source', true);

    if ($source_slug == 'themap') {
        // If the source is "themap", set the source to "map"
        if (term_exists('news-uk', 'client')) {
            wp_set_object_terms($created_post_id, 'news-uk', 'client', true);
        }
    }

}

function wp_set_post_topics($created_post_id, $post_data)
{

    // If there are any topics and the taxonomy "topic" exists, add them
    if (isset($post_data['topics']) && taxonomy_exists('topic')) {
        foreach ($post_data['topics'] as $topic) {
            // If the term exists, set it by ID
            if (term_exists($topic, 'topic')) {
                $term = get_term_by('name', $topic, 'topic');
                echo "term exists\n";
                wp_set_post_terms($created_post_id, $term->term_id, 'topic', true);
                echo "term set\n";
            } else {
                // If the term doesn't exist, create it and set it by ID
                $term = wp_insert_term($topic, 'topic');
                echo "term created\n";
                wp_set_post_terms($created_post_id, $term['term_id'], 'topic', true);
                echo "term set\n";
            }
        }
    }

}

function wp_set_post_series($created_post_id, $post_data)
{

    // If there are any series and the taxonomy "series" exists, add them
    if (isset($post_data['series']) && taxonomy_exists('series')) {
        foreach ($post_data['series'] as $series) {
            // If the term exists, set it by ID
            if (term_exists($series, 'series')) {
                $term = get_term_by('name', $series, 'series');
                echo "term exists\n";
                wp_set_object_terms($created_post_id, $term->term_id, 'series', true);
                echo "term set\n";
            } else {
                // If the term doesn't exist, create it and set it by ID
                $term = wp_insert_term($series, 'series');
                echo "term created\n";
                wp_set_object_terms($created_post_id, $term['term_id'], 'series', true);
                echo "term set\n";
            }
        }
    }

}

function wp_set_post_companies($created_post_id, $post_data)
{

    // If there are any companies and the taxonomy "company" exists, add them
    if (isset($post_data['companies']) && taxonomy_exists('company')) {
        echo "companies exist\n";
        foreach ($post_data['companies'] as $company) {
            // If the term exists, set it by ID
            if (term_exists($company, 'company')) {
                $term = get_term_by('name', $company, 'company');
                echo "term exists\n";
                wp_set_post_terms($created_post_id, $term->term_id, 'company', true);
                echo "term set\n";
            } else {
                // If the term doesn't exist, create it and set it by ID
                $term = wp_insert_term($company, 'company');
                echo "term created\n";
                wp_set_post_terms($created_post_id, $term['term_id'], 'company', true);
                echo "term set\n";
            }
        }
    } else {
        echo "companies don't exist\n";
    }

}
<?php
/**
 * Plugin Name: WP Content Grabber
 * Plugin URI: http://www.contexthq.com
 * Description: This plugin will grab the content from a URL and add it to your site.
 * Version: 1.0.0
 * Author: Robert Andrews
 * Author URI: http://www.contexthq.com
 */

require __DIR__ . '/vendor/autoload.php';

use Graby\Graby;

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
        <input type="hidden" name="action" value="grab_content_action">
        <?php wp_nonce_field('grab_content', 'grab_content_nonce');?>
        <input type="text" name="content_url" value="" size="100%" />
        <input type="submit" name="submit" value="Grab Content" class="button button-primary" />
    </form>
</div>
<?php
}

function display_json_post_selection_page($content_url)
{
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }
    json_post_selection_table($content_url);
}

function json_post_selection_table($content_url)
{
    $response = wp_remote_get($content_url);
    $body = wp_remote_retrieve_body($response);
    $posts = json_decode($body);
    if (!empty($posts)) {
        echo '<div class="wrap">';
        echo '<h2>Remote Posts</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="import_json_posts_action">';
        echo '<input type="hidden" name="content_url" value="' . esc_attr($content_url) . '">';
        wp_nonce_field('import_json_posts', 'import_json_posts_nonce');
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>
        <tr>
            <th><input type="checkbox" id="select-all"></th>
            <th>Date</th>
            <th>Title</th>
            <th>Author</th>
            <th>Categories</th>
            <th>Tags</th>
            <th>Featured Image</th>
        </tr>
    </thead>';
        echo '<tbody>';
        foreach ($posts as $key => $post) {
            $title = $post->title->rendered;
            $author = $post->_embedded->author[0]->name;
            $categories = implode(', ', array_map(function ($category) {
                return $category->name;
            }, $post->_embedded->{"wp:term"}[0]));
            $tags = implode(', ', array_map(function ($tag) {
                return $tag->name;
            }, $post->_embedded->{"wp:term"}[1]));
            $featured_image = $post->_embedded->{"wp:featuredmedia"}[0]->media_details->sizes->thumbnail->source_url;
            $date = date("M d, Y", strtotime($post->date));
            echo '<tr>';
            echo '<td><input type="checkbox" name="selected_posts[]" value="' . esc_attr($key) . '"></td>';
            echo '<td>' . $date . '</td>';
            echo '<td><a href="' . $post->link . '">' . $title . '</a></td>';
            echo '<td>' . $author . '</td>';
            echo '<td>' . $categories . '</td>';
            echo '<td>' . $tags . '</td>';
            echo '<td><img src="' . $featured_image . '" width="60" height="60" /></td>';
            echo '</tr>';
        }
        echo '</tbody>
</table>';
        echo '<input type="submit" name="submit" value="Import Selected Posts" class="button button-primary" />';
        echo '</form>';
        echo '</div>';
        echo '<script>
            document.getElementById("select-all").addEventListener("click", function() {
                var checkboxes = document.querySelectorAll("input[type=checkbox]");
                for (var i = 0; i < checkboxes.length; i++) {
                    checkboxes[i].checked = this.checked;
                }
            });
        </script>';
    }
}

function import_json_posts()
{
    // Check the nonce
    if (!isset($_POST['import_json_posts_nonce']) || !wp_verify_nonce($_POST['import_json_posts_nonce'], 'import_json_posts')) {
        print 'Sorry, your nonce did not verify.';
        exit;
    } else {
        // process form data
    }

    // Form was posted
    if (isset($_POST['submit'])) {
        $content_url = $_POST['content_url'];
        $selected_posts = isset($_POST['selected_posts']) ? $_POST['selected_posts'] : array();

        $response = wp_remote_get($content_url);
        $body = wp_remote_retrieve_body($response);
        $posts = json_decode($body);

        foreach ($selected_posts as $key) {
            if (isset($posts[$key])) {
                $post_data = package_wp_post($posts[$key]);
                if ($post_data) {
                    add_new_post($post_data, $content_url, false);
                } else {
                    echo "Could not add new post.";
                }
            }
        }

        wp_redirect(admin_url('edit.php'));
        exit;
    }
}
add_action('admin_post_import_json_posts_action', 'import_json_posts');

function grab_content()
{
    // Check the nonce
    if (!isset($_POST['grab_content_nonce']) || !wp_verify_nonce($_POST['grab_content_nonce'], 'grab_content')) {
        print 'Sorry, your nonce did not verify.';
        exit;
    } else {
        // process form data
    }

    // Form was posted
    if (isset($_POST['submit'])) {
        $content_url = $_POST['content_url'];

        if (strpos($content_url, 'wp-json') === false) {
            // The string 'wp-json' was NOT found in $string
            // Do something here
            grab_article($content_url);
        } else {
            // The string 'wp-json' was found in $string
            // Redirect to JSON post selection admin page
            $admin_url = add_query_arg(array('page' => 'json_post_selection', 'content_url' => urlencode($content_url)), admin_url('admin.php'));
            wp_redirect($admin_url);
            exit;
        }
    }
}
add_action('admin_post_grab_content_action', 'grab_content');

// Register the JSON post selection admin page
add_action('admin_menu', 'register_json_post_selection_admin_page');
function register_json_post_selection_admin_page()
{
    add_submenu_page(
        null, // hidden from the menu
        'JSON Post Selection',
        'JSON Post Selection',
        'manage_options',
        'json_post_selection',
        'display_json_post_selection_page_callback'
    );
}

// Callback function to display the JSON post selection admin page
function display_json_post_selection_page_callback()
{
    if (isset($_GET['content_url'])) {
        $content_url = urldecode($_GET['content_url']);
        display_json_post_selection_page($content_url);
    } else {
        echo 'No content URL provided.';
    }
}

function grab_article($article_url)
{
    /**
     * Grab article from URL
     * @return void
     */

    // 🔻 Retrieve the page source code
    $response = wp_remote_get($article_url);
    if ($response['body']) {
        $response = $response['body'];
    }
    $body = wp_remote_retrieve_body($response);

    // 👉🏻 WordPress? Use any discovered /posts/ URL, eg. /wp-json/wp/v2/posts/2525617
    if ($wp_post_api_url = wp_find_post_api_url($body)) {

        $path = parse_url($wp_post_api_url, PHP_URL_PATH);
        $segments = explode('/', $path);
        // Get post type
        $post_type = $segments[4];
        // Get post ID
        $post_id = $segments[5];

        // Call /posts/ for a single post
        $posts_json = wp_call_api_posts($post_type, $post_id, $article_url);

        // print_r($posts_json);

        // Package-up /posts/ response to set as new WP post
        $post_package = package_wp_post($posts_json);

    } else {
        // 👉🏻 Not WordPress: Use Graby to parse the page

        // print_r("Could not find posts API URL.");

        $graby = new Graby();

        $result = $graby->fetchContent($article_url);

        /*
        echo '<pre>';
        print_r($result);
        echo '</pre>';
         */

        $post_package = package_graby_post($result);

    }

    // Add new post
    if ($post_package) {
        add_new_post($post_package, $article_url);
    } else {
        echo "Could not add new post.";
    }

}

function grab_from_json()
{
    $url = 'https://example.com/wp-json/wp/v2/posts';

    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        echo 'Error fetching posts: ' . $response->get_error_message();
    } else {
        $body = wp_remote_retrieve_body($response);
        $posts = json_decode($body);

        foreach ($posts as $post) {
            echo '<h2>' . $post->title->rendered . '</h2>';
        }
    }

}

function wp_find_post_api_url($body)
{
    // 1. Some post markup contains the actual endpoint
    // eg. /wp-json/wp/v2/posts/2525617 & /wp-json/wp/v2/news/1337292
    if (preg_match('/\/wp-json\/wp\/v2\/(?!.*media|categories|tags).*\/\d+/', $body, $matches)) {
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
        // echo 'No post endpoint found in body.';
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

function wp_call_api_posts($post_type, $post_id, $article_url)
{

    // Construct API URL
    // eg. http://www.techcrunch.com
    $domain = parse_url($article_url, PHP_URL_SCHEME) . '://' . parse_url($article_url, PHP_URL_HOST);
    // eg. http: //www.techcrunch.com/wp-json/wp/v2/posts/2525617
    $posts_endpoint = trailingslashit($domain) . 'wp-json/wp/v2/' . trailingslashit($post_type);
    // for single post call
    if ($post_id) {
        $posts_endpoint .= $post_id;
        $posts_endpoint .= '?_embed=wp:term,wp:featuredmedia,author';
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
    $post_data['excerpt'] = isset($posts_json->excerpt->rendered) ? html_entity_decode($posts_json->excerpt->rendered, ENT_QUOTES, 'UTF-8') : '';
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

    echo '<pre>';
    print_r($post_data);
    echo '</pre>';

    return $post_data;

}

function add_new_post($post_data, $article_url, $redirect = true)
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

    if ($created_post_id) {
        if ($post_data['feat_img_url']) {
            wp_add_featured_media($post_data['feat_img_url'], $created_post_id);
        }

        wp_set_post_source($created_post_id, $article_url);

        update_post_meta($created_post_id, 'source_url', $article_url);
        update_post_meta($created_post_id, 'date_obtained', date('Y-m-d H:i:s'));

        if ($redirect) {
            wp_redirect(admin_url('post.php?post=' . $created_post_id . '&action=edit'));
            exit;
        }
    } else {
        print_r('New post not added');
        print_r($created_post_id);
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

}

function wp_set_post_source($created_post_id, $article_url)
{

    $host = parse_url($article_url, PHP_URL_HOST); // Get host name
    $pieces = explode(".", $host); // Split host name by dot
    $source_slug = ($pieces[0] === 'www') ? $pieces[1] : $pieces[0]; // Get the first portion of the host name, or the second if the first is "www"

    // echo $source; // Output: "theguardian"

    wp_set_object_terms($created_post_id, $source_slug, 'source', true);

}
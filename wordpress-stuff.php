<?php

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

        // 🔻 Retrieve the page source code
        $response = wp_remote_get($article_url);
        $body = wp_remote_retrieve_body($response);

        // WordPress: Use any discovered /posts/ URL
        $posts_endpoint = wp_find_post_endpoint($body);
        if ($posts_endpoint) {
            $posts_json = wp_call_posts($post_endpoint);
            if ($posts_json) {
                $created_post_id = add_grabbed_post($posts_json);
                if ($created_post_id) {
                    // print_r('New post added');
                    wp_redirect(admin_url('post.php?post=' . $created_post_id . '&action=edit'));
                } else {
                    // print_r('New post not added');
                }
            } else {
                // print_r('No posts found');
            }
        } else {
            // print_r('No post endpoint found');
        }

    }
}
add_action('admin_post_grab_article_action', 'grab_article');

function wp_find_post_endpoint($body)
{
    // eg. /wp-json/wp/v2/posts/2525617 & /wp-json/wp/v2/news/1337292
    if (preg_match('/wp-json/wp/v2/(?!(media|categories|tags)/)\w+/\d+', $body, $matches)) {
        $post_endpoint = $matches[1];
        echo 'The post endpoint is: ' . $post_endpoint;
        return $post_endpoint;
    } else {
        echo 'No post endpoint found in body.';
    }

}

function add_grabbed_post($post_json)
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
    // Get author name
    if ($post_json->_embedded->author) {
        // $author_name = $post_json->_embedded->author[0]->name;
        $found_author_name = $post_json->_embedded->author[0]->name;
        $assigned_author_id = assign_author($found_author_name);
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
        wp_add_featured_media($post_json, $created_post_id);

        return $created_post_id;

    } else {
        // print_r('Post not added');
        return false;
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
        'search' => $found_author_name->name,
        'search_columns' => array('display_name'),
    ));

    // Found matching user, get her ID
    if (!empty($matched_users)) {
        $user = $matched_users[0]; // Assume the first user is the one we want
        $user_id = $user->ID;
        // do_my_log("User found: " . $user_id);
    } else {
        // do_my_log("User not found - creating...");

        // Create user from found author name
        $user_id = wp_create_user(sanitize_title($found_author_name->name), wp_generate_password());

        // Add more specific profile details
        $author_name_parts = explode(' ', $found_author_name->name);
        $first_name = $author_name_parts[0];
        $last_name = end($author_name_parts);
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $found_author_name->name,
            'nickname' => $found_author_name->name,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'role' => 'contributor',
            // 'description' => $found_author_name->description,
        ));
    }

    if ($user_id) {
        return $user_id;
    } else {
        return false;
    }

}

function wp_add_featured_media($post_json, $created_post_id)
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
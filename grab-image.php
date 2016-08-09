<?php
/**
 * @package grab-image
 * Plugin Name: Grab Image
 * Version: 1.8
 * Description: Grab images of img tags are re-uploads them to be located on the site.
 * Author: Niteco
 * Author URI: http://niteco.se/
 * Plugin URI: PLUGIN SITE HERE
 * Text Domain: grab-image
 * Domain Path: /languages
 */

defined('ALLOW_UNFILTERED_UPLOADS') or define('ALLOW_UNFILTERED_UPLOADS', true);

// no limit time
ini_set('max_execution_time', 300);
error_reporting(E_ERROR);

// start up the engine
add_action('admin_menu'                 , 'grab_image_page'                 );
add_action('wp_ajax_download_image'     , 'ajax_download_image_post'        );
add_action('wp_ajax_grab_image'         , 'ajax_grab_image_post'            );
add_action('wp_ajax_attach_image'       , 'ajax_attach_image_post'          );
add_action('wp_ajax_search_image'       , 'ajax_search_image_post'          );
add_action('wp_ajax_restore_image'      , 'ajax_restore_featured_image'      );

// require helper
require_once dirname(__FILE__). '/libs/helper.php';

/**
 * define new menu page parameters
 */
function grab_image_page() {
    add_menu_page( 'Grab Image', 'Grab Image', 'activate_plugins', 'grab-image', 'grab_image_run', '');
}

/**
 * plugin page
 */
function grab_image_run() {
    if (!current_user_can('manage_options'))  {
        wp_die( __('You do not have sufficient permissions to access this page.') );
    } else {
        require_once dirname(__FILE__). '/views/page.php';
    }
}

/**
 * @package download-image
 * ajax function to download image
 */
function ajax_download_image_post() {
    $id = intval($_REQUEST['id']);
    $post = get_post($id);
    if (empty($post)) {
        echo 'Wrong post ID';
        wp_die();
    }

    // call helper class
    $helper = new grabimage_helper();

    // get all attachments of post
    $attachments = get_posts(array(
        'post_type'      => 'attachment',
        'posts_per_page' => -1,
        'post_parent'    => $post->ID,
        'exclude'        => get_post_thumbnail_id($post->ID)
    ));

    // get thumbnail of post
    $thumbnail = get_post(get_post_thumbnail_id($post->ID));
    if (!empty($thumbnail)) {
        $attachments[] = $thumbnail;
    }

    $count = 0;
    if ($attachments) {
        foreach ($attachments as $attachment) {
            $attachment_url = wp_get_attachment_image_url($attachment->ID, 'full');

            // ignore s3 image
            if (strpos($attachment_url, 'wp-uploads.interactives.dk') !== false) {
                continue;
            }

            // ignore not local image
            if (strpos($attachment_url, $_SERVER['SERVER_NAME']) === false) {
                continue;
            }

            $original_domain = "http://sarahlouise.dk/wp-content/";
            $original_url = $helper->original_image_url($attachment_url, $original_domain);

            // init file
            $file = array();
            $file['name'] = $helper->clean_basename($helper->basename($original_url));

            // get uploads path
            $time = current_time('mysql');
            if (substr($attachment->post_date, 0, 4) > 0) {
                $time = $attachment->post_date;
            }
            $uploads = wp_upload_dir($time);

            // check if image exist on hard disk
            if (!file_exists($uploads['path']. '/'. $file['name'])) {
                $file['file'] = $uploads['path']. '/'. $file['name'];
                $filetype = wp_check_filetype($file['file']);
                $file['type'] = $filetype['type'];
                $file['tmp_name'] = download_url($original_url);
                $file['error'] = 0;

                // download image file
                $overrides = array('test_form'=>false);
                $file = wp_handle_sideload( $file, $overrides, $time );

                if ( isset($file['error']) ) {
                    echo "error, <a href='{$original_url}' target='_blank'>{$original_url}</a> can't be downloaded<br/>";
                    continue;
                } else {
                    $count++;
                    echo "sucess, <a href='{$original_url}' target='_blank'>{$original_url}</a> is downloaded<br/>";
                }
                $file = $file['file'];
            } else {
                echo "sucess, <a href='{$original_url}' target='_blank'>{$original_url}</a> existed<br/>";
                $file = $uploads['path']. '/'. $file['name'];
            }

            /*
             * update attachment metadata
             * s3 offload will upload and remove on hard disk if it 's turned on
             */
            wp_update_attachment_metadata( $attachment->ID, wp_generate_attachment_metadata( $attachment->ID, $file ) );
        }
    }
    echo 'Downloaded '. $count. ' new image';
    wp_die();
}

/**
 * @package grab-image
 * ajax function to grab image
 */
function ajax_grab_image_post() {
    $id = intval($_REQUEST['id']);
    $post = get_post($id);
    if (empty($post)) {
        echo 'Wrong post ID';
        wp_die();
    }

    // call helper class
    $helper = new grabimage_helper();

    // extract image tag from post_content
    $array = $helper->extract_image($post->post_content);

    $search = $replace = [];
    $count = 0;
    if (is_array($array) && count($array) > 0) {
        foreach ($array as $tag => $url) {
            // refine url
            $url = $helper->reconstruct_url($url);

            // real url
            $url = $helper->real_filename($url);

            // check if attachment id exist
            $attachment_id = $helper->get_attachment_id($url);

            if (empty($attachment_id)) {
                // init file
                $file = array();
                $file['name'] = $helper->clean_basename($helper->basename($url));

                // get uploads path
                $time = current_time('mysql');
                if (substr($post->post_date, 0, 4) > 0) {
                    $time = $post->post_date;
                }
                $uploads = wp_upload_dir($time);

                // check if image exist on hard disk
                if (!file_exists($uploads['path']. '/'. $file['name'])) {
                    $exist = false;

                    // sideload image
                    $attachment_id = $helper->media_download_sideload($url, $post->ID);
                } else {
                    $exist = true;

                    // insert attachment
                    $attachment_id = $helper->media_update_sideload($url, $post->ID);
                }

                // ignore if it has any error
                if (is_wp_error($attachment_id)) {
                    continue;
                }
            }

            // ignore if empty attachment_id
            if (empty($attachment_id)) {
                continue;
            }

            // search a|img tag
            $search[] = $tag;

            // replace with
            $image = wp_get_attachment_image_src($attachment_id, 'full');
            $id = $attachment_id;
            $src = $image[0];
            $replace[] = "<a href=\"{$src}\" rel=\"attachment wp-att-{$id}\">"
                . "<img class=\"alignnone size-full wp-image-{$id}\" src=\"{$src}\" alt=\"{$post->post_title}\" />"
                . "</a>";

            // return message
            $exist = ($exist ? 'did exist' : 'didn\'t exist');
            echo "<a href=\"".get_edit_post_link($attachment_id)."\">{$url}</a> <b>{$exist}</b> already.<br/>";
        }

        // update post data search | replace
        if (count($search) > 0 && count($replace) > 0) {
            $post_content = str_replace($search, $replace, $post->post_content);
            $my_post = [
                'ID'           => $post->ID,
                'post_content' => $post_content,
            ];
            wp_update_post($my_post);
        }

        $count = count($search);
    }

    // set post thumbnail if not exist
    $helper->set_post_thumbnail($post->ID);

    echo 'Found '. $count. ' new image';
    wp_die();
}

/**
 * @package grab-image
 * ajax function to attach image
 */
function ajax_attach_image_post() {
    $id = intval($_REQUEST['id']);
    $post = get_post($id);
    if (empty($post)) {
        echo 'Wrong post ID';
        wp_die();
    }

    // call helper class
    $helper = new grabimage_helper();

    // extract image tag from post content
    $array = $helper->extract_image($post->post_content, false);

    $search = $replace = [];
    $count = 0;
    if (is_array($array) && count($array) > 0) {
        foreach ($array as $tag => $url) {
            // get attachment ID from url
            $attachment_id = $helper->get_attachment_id($url);
            if (empty($attachment_id)) {
                continue;
            }

            // get post attachment
            $attachments = get_attached_media('image', $post->ID);

            // ignore if image was attachment
            if (isset($attachments[$attachment_id])) {
                continue;
            }

            wp_update_post([
                'ID' => $attachment_id,
                'post_parent' => $post->ID,
            ]);

            // search a|img tag
            $search[] = $tag;

            // replace with
            $image = wp_get_attachment_image_src($attachment_id, 'full');
            $id = $attachment_id;
            $src = $image[0];
            $replace[] = "<a href=\"{$src}\" rel=\"attachment wp-att-{$id}\">"
                . "<img class=\"alignnone size-full wp-image-{$id}\" src=\"{$src}\" alt=\"{$post->post_title}\" />"
                . "</a>";

            echo "success ; {$url} ; {$attachment_id} <br/>";
        }

        // update post data
        if (count($search) > 0 && count($replace) > 0) {
            $post_content = str_replace($search, $replace, $post->post_content);
            $my_post = [
                'ID'           => $post->ID,
                'post_content' => $post_content,
            ];
            wp_update_post($my_post);
        }

        $count = count($search);
    }

    echo 'Attached '. $count. ' image';
    wp_die();
}

/**
 * @package grab-image
 * ajax function to search / replace image
 */
function ajax_search_image_post() {
    $id = intval($_REQUEST['id']);
    $post = get_post($id);
    if (empty($post)) {
        echo 'Wrong post ID';
        wp_die();
    }

    $search_str = (string) $_REQUEST['search'];
    $replace_str = (string) $_REQUEST['replace'];

    // call helper class
    $helper = new grabimage_helper();

    // extract image tag from post_content
    $array = $helper->extract_image($post->post_content, false);

    $search = $replace = [];
    $count = 0;
    if (is_array($array) && count($array) > 0) {
        foreach ($array as $tag => $url) {
            if (strpos($url, $search_str) === false) {
                continue;
            }

            $search[] = $tag;
            $replace[] = str_replace($search_str, $replace_str, $tag);

            echo "success ; {$url} <br/>";
        }

        // update post data
        if (count($search) > 0 && count($replace) > 0) {
            $post_content = str_replace($search, $replace, $post->post_content);
            $my_post = [
                'ID'           => $post->ID,
                'post_content' => $post_content,
            ];
            wp_update_post($my_post);
        }

        $count = count($search);
    }

    echo 'Searched / Replaced '. $count. ' image';
    wp_die();
}

/**
 * @package grab-image
 * ajax function to restore old featured images
 */
function ajax_restore_featured_image() {
    $post_id = intval($_REQUEST['id']);
    $post = get_post($post_id);
    if (empty($post)) {
        echo 'Wrong post ID';
        wp_die();
    }

    // call helper class
    $helper = new grabimage_helper();

    // original featured image url
    $original_url = urldecode($_REQUEST['url']);

    // current featured image url
    $current_url = get_the_post_thumbnail_url($post_id, 'full');

    // get attachment_id from original featured image url
    $attachment_id = $helper->get_attachment_id($original_url);

    // if empty the download
    if (empty($attachment_id)) {
        $attachment_id = $helper->media_download_sideload($original_url, $post_id);
    }

    // if correct then set as post featured image
    if (!is_wp_error($attachment_id)) {
        set_post_thumbnail($post, $attachment_id);
    }

    // check post thumbnail exist or not
    $helper->set_post_thumbnail($post->ID);

    // new featured image url
    $new_url = get_the_post_thumbnail_url($post_id, 'full');

    if (empty($new_url)) {
        echo '<span class="label label-danger">Error</span> no featured image was added';
    } else {
        if ($helper->compare_basename($current_url, $new_url)) {
            echo 'Nothing to do';
        } else {
            if (empty($current_url)) {
                echo '<span class="label label-success">Success</span> featured image was added';
            } else {
                echo '<span class="label label-success">Success</span> featured image was restored';
            }
        }
    }

    wp_die();
}
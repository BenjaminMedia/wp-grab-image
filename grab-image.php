<?php
/**
 * @package grab-image
 * Plugin Name: grab-image
 * Version: 0.8
 * Description: Grabs images of img tags are re-uploads them to be located on the site.
 * Author: Niteco
 * Author URI: http://niteco.se/
 * Plugin URI: PLUGIN SITE HERE
 * Text Domain: grab-image
 * Domain Path: /languages
 */

define('ALLOW_UNFILTERED_UPLOADS', true);

// no limit time
ini_set('max_execution_time', 300);
error_reporting(E_ERROR);

// start up the engine
add_action('admin_menu'                 , 'grab_image_page'                 );
add_action('wp_ajax_grab_image'         , 'ajax_grab_image_post'            );
add_action('wp_ajax_attach_image'       , 'ajax_attach_image_post'          );
add_action('wp_ajax_search_image'       , 'ajax_search_image_post'          );
add_action('wp_ajax_restore_image'      , 'ajax_restore_feature_image'      );

// require helper
require_once dirname(__FILE__). '/helper.php';

/**
 * define new menu page parameters
 */
function grab_image_page() {
    add_menu_page( 'Grab images', 'Grab images', 'activate_plugins', 'grab-image', 'grab_image_run', '');
}

/**
 * plugin page
 */
function grab_image_run() {
    if (!current_user_can('manage_options'))  {
        wp_die( __('You do not have sufficient permissions to access this page.') );
    } else { ?>

    <!-- Output for Plugin Options Page -->
    <div class="wrap">
        <?php
            if (isset($_GET['action']) && !empty($_GET['action'])) {
                echo '<div class="updated below-h2" id="message">';
                switch ($_GET['action']) {
                    case 'grab':
                        echo '<h2>Grab images</h2> <p>Images are being grabbed !</p>';
                        break;

                    case 'attach':
                        echo '<h2>Attach images</h2> <p>Images are being attached !</p>';
                        break;

                    case 'search':
                        echo '<h2>Search / Replace images</h2> <p>Images are being searched and replaced !</p>';
                        break;

                    case 'restore':
                        echo '<h2>Restore feature images</h2> <p>Restore old feature images of frutimian.no !</p>';
                        break;
                }
                echo '</div>';
            }
        ?>
        <p>
            <ul class="nav nav-pills">
                <li class="nav-item">
                    <a href="?page=grab-image&amp;action=grab" class="nav-link <?php echo (@$_GET['action'] == 'grab' ? 'active' : ''); ?>">Grab images</a>
                </li>
                <li class="nav-item">
                    <a href="?page=grab-image&amp;action=attach" class="nav-link <?php echo (@$_GET['action'] == 'attach' ? 'active' : ''); ?>">Attach images</a>
                </li>
                <li class="nav-item">
                    <a href="?page=grab-image&amp;action=search" class="nav-link <?php echo (@$_GET['action'] == 'search' ? 'active' : ''); ?>">Search / Replace images</a>
                </li>
                <li class="nav-item">
                    <a href="?page=grab-image&amp;action=restore" class="nav-link <?php echo (@$_GET['action'] == 'restore' ? 'active' : ''); ?>">Restore feature images</a>
                </li>
                <li class="nav-item">
                    <button id="box-status" class="btn btn-warning" style="display: none;"><span class="fa fa-refresh fa-refresh-animate"></span> Loading...</button>
                </li>
            </ul>
        </p>
    </div>
    <!-- End Output for Plugin Options Page -->

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.0.0-alpha/css/bootstrap.min.css">
        <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css">
        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.0.0-beta1/jquery.min.js"></script>
        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.0.0-alpha/js/bootstrap.min.js"></script>
        <style type="text/css">
            .nav-link {
                cursor: pointer !important;
            }
            .fa-refresh-animate {
                -animation: spin .7s infinite linear;
                -webkit-animation: spin2 .7s infinite linear;
            }
            @-webkit-keyframes spin2 {
                from { -webkit-transform: rotate(0deg);}
                to { -webkit-transform: rotate(360deg);}
            }
            @keyframes spin {
                from { transform: scale(1) rotate(0deg);}
                to { transform: scale(1) rotate(360deg);}
            }
        </style>

    <?php
        if (isset($_GET['action']) && !empty($_GET['action'])) {
            $action = trim($_GET['action']);
            switch ($action) {
                case 'grab':
                case 'attach':
                case 'search':
                case 'restore':
                    $posts = get_posts([
                        'posts_per_page' => 100000,
                        'post_status' => 'any',
                        'orderby' => 'ID',
                        'order'   => 'ASC',
                    ]);

                    if ($action == 'restore') {
                        // list of old post_id with thumbnail
                        $helper = new grabimage_helper();
                        $thumbnail = $helper->get_old_thumbnail();
                    }
                    ?>
                    <script type="text/javascript">
                        jQuery(document).ready(function () {
                            var post = jQuery(".post");
                            var index = -1;

                            /**
                             * click stop button
                             */
                            jQuery('#button-stop').click(function () {
                                index = post.length;
                            });

                            /**
                             * click start button
                             */
                            jQuery('#button-start').click(function () {
                                function doNext() {
                                    if (++index >= post.length) {
                                        jQuery('#box-status').hide();
                                        return;
                                    } else {
                                        jQuery('#box-status').show();
                                    }

                                    var current = post.eq(index);
                                    var id = current.attr('rel');
                                    var data = {
                                        'action': '<?php echo $action . '_image'; ?>',
                                        'id': id,
                                        <?php if ($action == 'search') { ?>
                                        'search': search,
                                        'replace': replace,
                                        <?php } ?>
                                    };

                                    console.log(id);
                                    jQuery('#result-' + id).html('Loading ...');
                                    jQuery.ajax({
                                        url: ajaxurl,
                                        cache: false,
                                        data: data,
                                        method: 'POST',
                                        success: function(msg) {
                                            console.log(msg);
                                            jQuery('#result-' + id).html(msg);
                                            doNext();
                                        },
                                        error: function (msg) {
                                            console.log(msg);
                                            jQuery('#result-' + id).html('Error ... Restart in 10s');
                                            --index;
                                            setTimeout(doNext, 10000);
                                        }
                                    });
                                }

                                <?php if ($action == 'search') { ?>
                                    var search = jQuery('#input-search').val();
                                    var replace = jQuery('#input-replace').val();
                                    if (search == '' || replace == '') {
                                        alert('You must enter search and replace input !');
                                        return false;
                                    } else {
                                        if (confirm("Are you sure you want to search '" + search + "' and replace to '" + replace + "'") != true) {
                                            return false;
                                        }
                                    }
                                <?php } ?>

                                doNext();
                            });
                        });
                    </script>
                    <table class="table table-striped">
                        <tr>
                            <th colspan="4">
                                <button class="btn btn-primary" id="button-start">Start</button>
                                <button class="btn btn-danger" id="button-stop">Stop</button>
                            </th>
                        </tr>
                        <?php if ($action == 'search') { ?>
                        <tr>
                            <th colspan="4">
                                <label for="input-search">Search</label> <input class="input" type="text" id="input-search" />
                                <label for="input-replace">Replace</label> <input class="" type="text" id="input-replace" />
                            </th>
                        </tr>
                        <?php } ?>
                        <tr>
                            <th>#</th>
                            <th>Post</th>
                            <?php if ($action == 'restore') { ?>
                                <th>Old feature image</th>
                            <?php } ?>
                            <th>Result</th>
                        </tr>

                        <?php foreach ($posts as $i => $post) { ?>
                            <tr>
                                <td><?php echo ($i + 1); ?></td>
                                <td class="post" rel="<?php echo $post->ID; ?>"><a href="<?php echo get_edit_post_link($post->ID); ?>" target="_blank"><?php echo $post->ID; ?></a></td>
                                <?php
                                if ($action == 'restore') {
                                    echo '<td>';
                                    if (isset($thumbnail[$post->ID])) {
                                        $thumb_url = $thumbnail[$post->ID]['thumb_url'];
                                        $attachment_url = $thumbnail[$post->ID]['attachment_url'];
                                        if (!empty($thumb_url)) {
                                            echo $thumb_url;
                                            echo '<br/>';
                                        }
                                        if (!empty($attachment_url)) {
                                            echo "<a href='{$attachment_url}' target='_blank'>{$attachment_url}</a>";
                                        }
                                    }
                                    echo '</td>';
                                }
                                ?>
                                <td id="result-<?php echo $post->ID; ?>"></td>
                            </tr>
                        <?php } ?>

                    </table>
                        <?php
                    break;
            }
        }
    }
}

/**
 * @package grab-image
 * ajax function to grab image
 */
function ajax_grab_image_post() {
    $id = intval($_REQUEST['id']);
    $post = get_post($id);

    // call helper class
    $helper = new grabimage_helper();

    $array = $helper->extract_image($post->post_content);
    $search = $replace = [];
    $count = 0;
    if (is_array($array) && count($array) > 0) {
        foreach ($array as $tag => $url) {
            // refine url
            $url = $helper->reconstruct_url($url);

            // real url
            $url = $helper->real_filename($url);

            // init file
            $file = array();
            $file['name'] = $helper->clean_filename(basename($url));

            // check file exist on hard disk or not
            $time = current_time('mysql');
            if (substr($post->post_date, 0, 4) > 0) {
                $time = $post->post_date;
            }
            $uploads = wp_upload_dir($time);
            $filename = $file['name'];

            // check if image exist
            if (!file_exists($uploads['path']. '/'. $filename)) {
                $exist = false;

                // build up array like PHP file upload
                $file['file'] = $uploads['path']. '/'. $file['name'];
                $filetype = wp_check_filetype($file['file']);
                $file['type'] = $filetype['type'];
                $file['tmp_name'] = download_url($helper->reencode_url($url));
                $file['error'] = 0;

                // check ignore file
                $ignore = false;

                // ignore 404 file
                if (is_wp_error($file['tmp_name'])) {
                    @unlink($file['tmp_name']);
                    $ignore = true;
                    // return new WP_Error('grabfromurl', 'Could not download image from remote source');
                }

                // ignore empty file
                if (!$ignore) {
                    $file['size'] = filesize($file['tmp_name']);
                    if ($file['size'] <= 0) {
                        $ignore = true;
                    }
                }

                // continue if ignore
                if ($ignore) {
                    echo "error ; {$url} <br/>";
                    continue;
                }

                // sideload image
                $attachmentId = media_handle_sideload($file, $post->ID);
            } else {
                $exist = true;

                // insert to db
                $file['file'] = $uploads['path']. '/'. $file['name'];
                $file['url'] = $uploads['url'] . "/$filename";
                $filetype = wp_check_filetype($file['file']);
                $file['type'] = $filetype['type'];
                $file['error'] = 0;

                $type = $file['type'];
                $file = $file['file'];
                $title = preg_replace('/\.[^.]+$/', '', basename($file));
                $content = '';

                // Use image exif/iptc data for title and caption defaults if possible.
                if ( $image_meta = @wp_read_image_metadata($file) ) {
                    if ( trim( $image_meta['title'] ) && ! is_numeric( sanitize_title( $image_meta['title'] ) ) )
                        $title = $image_meta['title'];
                    if ( trim( $image_meta['caption'] ) )
                        $content = $image_meta['caption'];
                }

                // Construct the attachment array.
                $attachment = array(
                    'post_mime_type' => $type,
                    'guid' => $url,
                    'post_parent' => $post->ID,
                    'post_title' => $title,
                    'post_content' => $content,
                );

                // This should never be set as it would then overwrite an existing attachment.
                unset( $attachment['ID'] );

                // Save the attachment metadata
                $attachmentId = wp_insert_attachment($attachment, $file, $post->ID);
            }

            // ignore if it has any error
            if (is_wp_error($attachmentId)) {
                continue;
            }

            // search a|img tag
            $search[] = $tag;

            // replace with
            $image = wp_get_attachment_image_src($attachmentId, 'full');
            $id = $attachmentId;
            $src = $image[0];
            $replace[] = "<a href=\"{$src}\" rel=\"attachment wp-att-{$id}\">"
                . "<img class=\"alignnone size-full wp-image-{$id}\" src=\"{$src}\" alt=\"{$post->post_title}\" />"
                . "</a>";

            $exist = ($exist ? 'did exist' : 'didn\'t exist');
            echo "<a href=\"".get_edit_post_link($attachmentId)."\">{$url}</a> was successfully uploaded. It <b>{$exist}</b> already.<br/>";
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

    // call helper class
    $helper = new grabimage_helper();

    $array = $helper->extract_image($post->post_content, false);
    $search = $replace = [];
    $count = 0;
    if (is_array($array) && count($array) > 0) {
        foreach ($array as $tag => $url) {
            $attachmentId = $helper->get_attachment_id($url);
            if (empty($attachmentId)) {
                continue;
            }

            // get post attachment
            $attachments = get_attached_media('image', $post->ID);

            // ignore if image was attachment
            if (isset($attachments[$attachmentId])) {
                continue;
            }

            wp_update_post([
                'ID' => $attachmentId,
                'post_parent' => $post->ID,
            ]);

            // search a|img tag
            $search[] = $tag;

            // replace with
            $image = wp_get_attachment_image_src($attachmentId, 'full');
            $id = $attachmentId;
            $src = $image[0];
            $replace[] = "<a href=\"{$src}\" rel=\"attachment wp-att-{$id}\">"
                . "<img class=\"alignnone size-full wp-image-{$id}\" src=\"{$src}\" alt=\"{$post->post_title}\" />"
                . "</a>";

            echo "success ; {$url} ; {$attachmentId} <br/>";
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

    echo 'Attach '. $count. ' image';
    wp_die();
}

/**
 * @package grab-image
 * ajax function to search / replace image
 */
function ajax_search_image_post() {
    $id = intval($_REQUEST['id']);
    $post = get_post($id);
    $search_str = (string) $_REQUEST['search'];
    $replace_str = (string) $_REQUEST['replace'];

    // call helper class
    $helper = new grabimage_helper();

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

    echo 'Search / Replace '. $count. ' image';
    wp_die();
}

/**
 * @package grab-image
 * ajax function to restore old feature images of frutimian.no
 */
function ajax_restore_feature_image() {
    $old = $new = '';

    $id = intval($_REQUEST['id']);
    $post = get_post($id);
    if (empty($post)) {
        echo 'Wrong post ID';
        wp_die();
    }

    $helper = new grabimage_helper();
    $thumbnail = $helper->get_old_thumbnail();
    if (isset($thumbnail[$id])) {
        $thumb = $thumbnail[$id];
        if (!empty($thumb['attachment_url'])) {
            // old feature image url
            $old = $thumb['attachment_url'];
            $attachment_id = $helper->get_attachment_id($thumb['attachment_url']);
            if (!empty($attachment_id)) {
                set_post_thumbnail($post, $attachment_id);
            }
        }
    }

    // check post thumbnail exist or not
    $helper->set_post_thumbnail($post->ID);

    // new feature image url
    $new = get_the_post_thumbnail_url($id);

    if (empty($old)) {
        if (empty($new)) {
            echo 'No feature image was added';
        } else {
            echo $new;
            echo '<br/>';
            echo 'Success adding';
        }
    } else {
        if (empty($new)) {
            echo 'No feature image was added';
        } else {
            echo $new;
            echo '<br/>';
            if (basename($old) == basename($new)) {
                echo 'Same feature images';
            } else {
                echo 'Success restoring';
            }
        }

    }

    wp_die();
}
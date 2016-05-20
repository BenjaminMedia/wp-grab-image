<?php
/**
 * @package grab-image
 * Plugin Name: grab-image
 * Version: 0.3
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

// start up the engine
add_action('admin_menu'             , 'grab_image_page'     );
add_action('wp_ajax_grab_image'     , 'grab_image_post'     );
add_action('wp_ajax_attach_image'   , 'attach_image_post'   );
add_action('wp_ajax_search_image'   , 'search_image_post'   );

// require helper
require_once 'helper.php';

/**
 * define new menu page parameters
 */
function grab_image_page() {
    add_menu_page( 'Grab image', 'Grab image', 'activate_plugins', 'grab-image', 'grab_image_run', '');
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
                        echo '<h2>Grab image</h2> <p>Images are being grabbed !</p>';
                        break;

                    case 'attach':
                        echo '<h2>Attach image</h2> <p>Images are being attached !</p>';
                        break;

                    case 'search':
                        echo '<h2>Search / Replace image</h2> <p>Images are being searched and replaced !</p>';
                        break;
                }
                echo '</div>';
            }
        ?>
        <p>
            <a href="?page=grab-image&amp;action=grab" class="btn btn-primary <?php echo ($_GET['action'] == 'grab' ? 'btn-danger' : ''); ?>">Grab images</a>
            <a href="?page=grab-image&amp;action=attach" class="btn btn-primary <?php echo ($_GET['action'] == 'attach' ? 'btn-danger' : ''); ?>">Attach images</a>
            <a href="?page=grab-image&amp;action=search" class="btn btn-primary <?php echo ($_GET['action'] == 'search' ? 'btn-danger' : ''); ?>">Search / Replace images</a>
        </p>
    </div>
    <!-- End Output for Plugin Options Page -->

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.0.0-alpha/css/bootstrap.min.css">
        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.0.0-beta1/jquery.min.js"></script>
        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.0.0-alpha/js/bootstrap.min.js"></script>

    <?php
        if (isset($_GET['action']) && !empty($_GET['action'])) {
            switch ($_GET['action']) {
                case 'grab':
                case 'attach':
                case 'search':
                    $posts = get_posts([
                        'posts_per_page' => 100000,
                        'post_status' => 'any',
                        'orderby' => 'ID',
                        'order'   => 'ASC',
                    ]);
                    ?>
                    <script type="text/javascript">
                        jQuery(document).ready(function () {
                            var post = jQuery(".post");
                            var index = -1;

                            /**
                             * click stop button
                             */
                            jQuery('#stop-grab').click(function () {
                                index = post.length;
                            });

                            /**
                             * click start button
                             */
                            jQuery('#start-grab').click(function () {
                                function doNext() {

                                    if (++index >= post.length) {
                                        jQuery('#status-grab').html('Finish ...');
                                        return;
                                    } else {
                                        jQuery('#status-grab').html('Loading ...');
                                    }

                                    var current = post.eq(index);
                                    var id = current.attr('rel');
                                    var data = {
                                        'action': '<?php echo $_GET['action'] . '_image'; ?>',
                                        'id': id,
                                        <?php if ($_GET['action'] == 'search') { ?>
                                        'search': search,
                                        'replace': replace,
                                        <?php } ?>
                                    };

                                    console.log(id);
                                    jQuery('#image-' + id).html('Loading ...');
                                    jQuery.ajax({
                                        url: ajaxurl,
                                        cache: false,
                                        data: data,
                                        method: 'POST',
                                        success: function(msg) {
                                            jQuery('#image-' + id).html(msg);
                                            doNext();
                                        },
                                        error: function (msg) {
                                            jQuery('#image-' + id).html('Error ...');
                                            --index;
                                            setTimeout(doNext, 3000);
                                        }
                                    });
                                }

                                <?php if ($_GET['action'] == 'search') { ?>
                                var search = $('#input-search').val();
                                var replace = $('#input-replace').val();
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
                            <th colspan="3">
                                <button class="btn btn-primary" id="start-grab">Start</button>
                                <button class="btn btn-danger" id="stop-grab">Stop</button>
                                <span id="status-grab"></span>
                            </th>
                        </tr>
                        <?php if ($_GET['action'] == 'search') { ?>
                        <tr>
                            <th colspan="3">
                                <label for="input-search">Search</label> <input class="input" type="text" id="input-search" />
                                <label for="input-replace">Replace</label> <input class="" type="text" id="input-replace" />
                            </th>
                        </tr>
                        <?php } ?>
                        <tr>
                            <th>#</th>
                            <th>Post</th>
                            <th>Image</th>
                        </tr>
                    <?php
                    foreach ($posts as $i => $post) {
                        ?>
                        <tr>
                            <td><?php echo ($i + 1); ?></td>
                            <td class="post" rel="<?php echo $post->ID; ?>"><a href="<?php echo get_permalink($post->ID); ?>" target="_blank"><?php echo $post->ID; ?></a></td>
                            <td id="image-<?php echo $post->ID; ?>"></td>
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
function grab_image_post() {
    $id = intval($_REQUEST['id']);
    $post = get_post($id);

    $array = extract_image($post->post_content);
    $search = $replace = [];
    $count = 0;
    if (is_array($array) && count($array) > 0) {
        $thumb = 0;
        foreach ($array as $tag => $url) {
            // refine url
            $url = reconstruct_url($url);

            // real url
            $url = real_filename($url);

            // init file
            $file = array();
            $file['name'] = clean_filename(basename($url));

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
                $file['tmp_name'] = download_url(reencode_url($url));
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

            // create the thumbnails
            $attach_data = wp_generate_attachment_metadata( $attachmentId,  get_attached_file($attachmentId));

            // update metadata
            wp_update_attachment_metadata( $attachmentId,  $attach_data);

            // search a|img tag
            $search[] = $tag;

            // replace with
            $image = wp_get_attachment_image_src($attachmentId, 'full');
            $id = $attachmentId;
            $src = $image[0];
            $width = $image[1];
            $height = $image[2];
            $replace[] = "<a href=\"{$src}\" rel=\"attachment wp-att-{$id}\">"
                . "<img class=\"alignnone size-full wp-image-{$id}\" src=\"{$src}\" alt=\"{$post->post_title}\" width=\"{$width}\" height=\"{$height}\" />"
                . "</a>";

            // set post thumbnail
            if ($thumb == 0) {
                set_post_thumbnail($post, $attachmentId);
                $thumb = 1;
            }

            $exist = ($exist ? '' : 'none-exist');
            echo "success ; {$url} ; {$attachmentId} ; {$exist} <br/>";
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

    echo 'Found '. $count. ' new image';
    wp_die();
}

/**
 * @package grab-image
 * ajax function to attach image
 */
function attach_image_post() {
    $id = intval($_REQUEST['id']);
    $post = get_post($id);

    $array = extract_image($post->post_content, false);
    $search = $replace = [];
    $count = 0;
    if (is_array($array) && count($array) > 0) {
        foreach ($array as $tag => $url) {
            $attachmentId = get_attachment_id($url);
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
            $width = $image[1];
            $height = $image[2];
            $replace[] = "<a href=\"{$src}\" rel=\"attachment wp-att-{$id}\">"
                . "<img class=\"alignnone size-full wp-image-{$id}\" src=\"{$src}\" alt=\"{$post->post_title}\" width=\"{$width}\" height=\"{$height}\" />"
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
function search_image_post() {
    $id = intval($_REQUEST['id']);
    $post = get_post($id);
    $search_str = (string) $_REQUEST['search'];
    $replace_str = (string) $_REQUEST['replace'];

    $array = extract_image($post->post_content, false);
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
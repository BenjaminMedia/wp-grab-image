<?php
if (!class_exists('simple_html_dom_node')) require_once dirname(__FILE__). '/simple_html_dom.php';
require_once dirname(__FILE__). '/snoopy.class.php';
if (!class_exists('XML2Array')) require_once dirname(__FILE__). '/XML2Array.php';

class grabimage_helper
{
    /**
     * basename function for utf8
     * @param $url
     *
     * @return mixed
     */
    public function basename($url) {
        $temp = explode('/', $url);

        return $temp[count($temp) - 1];
    }

    /**
     * basepath function for utf8
     * @param $url
     *
     * @return string
     */
    public function basepath($url) {
        $i    = strpos( $url, "/uploads" );
        $str  = substr( $url, $i + 9);

        return $str;
    }

    /**
     * refine url
     * @param $url
     * @return string
     */
    public function reconstruct_url($url) {
        $url_parts = parse_url($url);
        $constructed_url = $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path'];

        return $constructed_url;
    }

    /**
     * encode url for unicode
     * @param $url
     * @return mixed
     */
    public function reencode_url($url) {
        $temp = explode('/', $url);
        $temp2 = $temp[count($temp) - 1];
        $temp[count($temp) - 1] = urlencode($temp2);

        return implode('/', $temp);
    }

    /**
     * make basename clean
     * @param $file
     * @return string
     */
    public function clean_basename($file) {
        $path = pathinfo($file);
        if (isset($path['extension'])) {
            $new_filename = preg_replace('/.' . $path['extension'] . '$/', '', $file);
            $file = substr(sanitize_title($new_filename), 0, 128) . '.' . $path['extension'];
        }

        return $file;
    }

    /**
     * make basepath clean
     * @param $basepath
     *
     * @return string
     */
    public function clean_basepath($basepath) {
        $tmp = explode('/', $basepath);

        $file = $tmp[count($tmp) - 1];
        $path = pathinfo($file);
        if (isset($path['extension'])) {
            $new_filename = preg_replace('/.' . $path['extension'] . '$/', '', $file);
            $file = substr(sanitize_title($new_filename), 0, 128) . '.' . $path['extension'];
        }
        $tmp[count($tmp) - 1] = $file;

        return implode('/', $tmp);
    }

    /**
     * check if image url exist or not
     * @param $url
     * @return bool
     */
    public function exist_filename($url) {
        $tmp = download_url($this->reencode_url($url));
        if (!is_wp_error($tmp)) {
            @unlink($tmp);
            return true;
        }
        @unlink($tmp);

        return false;
    }

    /**
     * get original image url (remove size)
     * @param $file
     * @return string
     */
    public function real_filename($file) {
        // remove size string
        if (preg_match("/^(https?:\/\/.*)\-[0-9]+x[0-9]+\.(jpg|jpeg|png)$/", $file, $m)) {
            $url = $m[1].'.'.$m[2];

            // check file exist or not
            if ($this->exist_filename($url)) {
                $file = $url;
            }
        }

        return $file;
    }

    /**
     * get image file name exclude size
     * @param $file
     *
     * @return string
     */
    public function exclude_size($file) {
        // remove size string
        if (preg_match('/^(.*)\-[0-9]+x[0-9]+\.(jpg|jpeg|png)$/', $file, $m)) {
            $file = $m[1].'.'.$m[2];
        }

        return $file;
    }

    /**
     * compare basename of original and current images
     * @param $first
     * @param $second
     *
     * @return bool
     */
    public function compare_basename($first, $second) {
        $first = $this->clean_basename($this->basename(trim($first)));
        $second = $this->clean_basename($this->basename(trim($second)));

        return ($first == $second);
    }

    /**
     * compare basepath of original and current images
     * @param $first
     * @param $second
     *
     * @return bool
     */
    public function compare_basepath($first, $second) {
        $first = $this->clean_basepath($this->basepath(trim($first)));
        $second = $this->clean_basepath($this->basepath(trim($second)));

        return ($first == $second);
    }

    /**
     * extract all a|img tag from post_content
     * @param $str
     * @return array
     */
    public function extract_image($str, $check_ignore = true) {
        $a_pattern = "/<a[^>]*>(<img [^><]*\/?>)<\/a>/";
        $img_pattern = "/<img[^>]+>/i";

        preg_match_all($a_pattern, $str, $a_tags);
        $str = preg_replace($a_pattern, '', $str);
        preg_match_all($img_pattern, $str, $img_tags);

        $tags = array_merge($a_tags[0], $img_tags[0]);
        $srcs = array();
        foreach ($tags as $tag) {
            // get the source string
            preg_match('/src="([^"]*)"/i', $tag , $result);
            // can not regex src
            if (!isset($result[1]) || empty($result[1])) {
                continue;
            }
            $url = $result[1];

            if ($check_ignore) {
                // ignore s3 image
                if (strpos($url, 'wp-uploads.interactives.dk') !== false) {
                    continue;
                }

                // ignore local image
                /*if (strpos($url, $_SERVER['SERVER_NAME']) !== false) {
                    continue;
                }*/

                // ignore relative image
                if (strpos($url, 'http') === false) {
                    continue;
                }
            }

            $srcs[$tag] = $url;
        }

        return $srcs;
    }

    /**
     * Get an attachment ID given a URL.
     *
     * @param string $url
     * @param bool $check_base
     *
     * @return int Attachment ID on success, 0 on failure
     */
    public function get_attachment_id($url, $check_base = false) {
        $attachment_id = 0;

        if ($check_base) {
            $dir = wp_upload_dir();
            if (false !== strpos($this->basename($url), $dir['baseurl'] . '/')) { // Is URL in uploads directory?
                return $attachment_id;
            }
        }

        // check by basepath first
        $path = $this->basepath($url);
        $path2 = rawurldecode($path);

        // exclude image size
        $path_x = $this->exclude_size($path);
        $path2_x = $this->exclude_size($path2);

        $temp = explode('/', $path);
        if (count($temp) == 3) {
            $query_args = array(
                'post_type'   => 'attachment',
                'post_status' => 'inherit',
                'fields'      => 'ids',
                'meta_query'  => array(
                    'relation' => 'OR',
                    array(
                        'value'   => $path,
                        'compare' => 'LIKE',
                        'key'     => '_wp_attachment_metadata',
                    ),
                    array(
                        'value'   => $path2,
                        'compare' => 'LIKE',
                        'key'     => '_wp_attachment_metadata',
                    ),
                    array(
                        'value'   => $path_x,
                        'compare' => 'LIKE',
                        'key'     => '_wp_attachment_metadata',
                    ),
                    array(
                        'value'   => $path2_x,
                        'compare' => 'LIKE',
                        'key'     => '_wp_attachment_metadata',
                    ),
                )
            );
        } else {
            $query_args = array(
                'post_type'   => 'attachment',
                'post_status' => 'inherit',
                'fields'      => 'ids',
                'meta_query'  => array(
                    'relation' => 'OR',
                    array(
                        'value'   => $path,
                        'compare' => 'LIKE',
                        'key'     => 'amazonS3_info',
                    ),
                    array(
                        'value'   => $path2,
                        'compare' => 'LIKE',
                        'key'     => 'amazonS3_info',
                    ),
                    array(
                        'value'   => $path_x,
                        'compare' => 'LIKE',
                        'key'     => '_wp_attachment_metadata',
                    ),
                    array(
                        'value'   => $path2_x,
                        'compare' => 'LIKE',
                        'key'     => '_wp_attachment_metadata',
                    ),
                )
            );
        }

        $query = new WP_Query( $query_args );
        $file = basename($path);
        $file2 = basename($path2);

        if ( $query->have_posts() ) {
            foreach ( $query->posts as $post_id ) {
                $meta = wp_get_attachment_metadata( $post_id );
                $original_file       = basename( $meta['file'] );
                $cropped_image_files = wp_list_pluck( $meta['sizes'], 'file' );
                if (
                    $file == $original_file
                    ||  in_array($file , $cropped_image_files)
                    ||  $file2 == $original_file
                    ||  in_array($file2 , $cropped_image_files)
                ) {
                    $attachment_id = $post_id;
                    break;
                }
            }
        }

        // search basename
        if (empty($attachment_id)) {
            $attachment_id = $this->get_attachment_id_by_basename($url);
        }

        return $attachment_id;
    }

    public function get_attachment_id_by_basename($url, $check_base = false) {
        $attachment_id = 0;

        if ($check_base) {
            $dir = wp_upload_dir();
            if (false !== strpos($this->basename($url), $dir['baseurl'] . '/')) { // Is URL in uploads directory?
                return $attachment_id;
            }
        }

        // check by basepath first
        $path = $this->basename($url);
        $path2 = rawurldecode($path);

        // exclude image size
        $path_x = $this->exclude_size($path);
        $path2_x = $this->exclude_size($path2);

        $temp = explode('/', $path);
        if (count($temp) == 3) {
            $query_args = array(
                'post_type'   => 'attachment',
                'post_status' => 'inherit',
                'fields'      => 'ids',
                'meta_query'  => array(
                    'relation' => 'OR',
                    array(
                        'value'   => $path,
                        'compare' => 'LIKE',
                        'key'     => '_wp_attachment_metadata',
                    ),
                    array(
                        'value'   => $path2,
                        'compare' => 'LIKE',
                        'key'     => '_wp_attachment_metadata',
                    ),
                    array(
                        'value'   => $path_x,
                        'compare' => 'LIKE',
                        'key'     => '_wp_attachment_metadata',
                    ),
                    array(
                        'value'   => $path2_x,
                        'compare' => 'LIKE',
                        'key'     => '_wp_attachment_metadata',
                    ),
                )
            );
        } else { global $wpdb;
            $query_args = array(
                'post_type'   => 'attachment',
                'post_status' => 'inherit',
                'fields'      => 'ids',
                'meta_query'  => array(
                    'relation' => 'OR',
                    array(
                        'value'   => $wpdb->_escape($path),
                        'compare' => 'LIKE',
                        'key'     => 'amazonS3_info',
                    ),
                    array(
                        'value'   => $path2,
                        'compare' => 'LIKE',
                        'key'     => 'amazonS3_info',
                    ),
                    array(
                        'value'   => $path_x,
                        'compare' => 'LIKE',
                        'key'     => '_wp_attachment_metadata',
                    ),
                    array(
                        'value'   => $path2_x,
                        'compare' => 'LIKE',
                        'key'     => '_wp_attachment_metadata',
                    ),
                )
            );
        }

        $query = new WP_Query( $query_args );
        $file = basename($path);
        $file2 = basename($path2);

        if ( $query->have_posts() ) {
            foreach ( $query->posts as $post_id ) {
                $meta = wp_get_attachment_metadata( $post_id );
                $original_file       = basename( $meta['file'] );
                $cropped_image_files = wp_list_pluck( $meta['sizes'], 'file' );
                if (
                    $file == $original_file
                    ||  in_array($file , $cropped_image_files)
                    ||  $file2 == $original_file
                    ||  in_array($file2 , $cropped_image_files)
                ) {
                    $attachment_id = $post_id;
                    break;
                }
            }
        }

        return $attachment_id;
    }

    /**
     *
     * set thumbnail for post from attachments
     * @param $id
     *
     * @return bool
     */
    public function set_post_thumbnail($id) {
        $post = get_post($id);
        $thumbnail_url = get_the_post_thumbnail_url($id, 'full');
        $has_thumb = false;

        // check thumbnail empty or not
        if (!empty($thumbnail_url)) {
            // check file thumbnail exist or not
            if (!$this->exist_filename($thumbnail_url)) {
                $attachment_id = $this->get_attachment_id($thumbnail_url);
                if (!empty($attachment_id)) {
                    set_post_thumbnail($id, $attachment_id);
                    $has_thumb = true;
                }
            } else {
                $has_thumb = true;
            }
        }

        // post does not have thumbnail or thumbnail has error
        // set first image in post_content as thumbnail
        if (!$has_thumb) {
            $images = $this->extract_image($post->post_content, false);
            foreach ($images as $tag => $url) {
                $attachment_id = $this->get_attachment_id($url);
                if (!empty($attachment_id)) {
                    set_post_thumbnail($id, $attachment_id);
                    $has_thumb = true;
                    break;
                }
            }
        }

        return $has_thumb;
    }

    /**
     * Download an url to local using snoopy class
     * @param $url
     *
     * @return string|WP_Error
     */
    public function download_url( $url) {
        //WARNING: The file is not automatically deleted, The script must unlink() the file.
        if (! $url)
            return new WP_Error('http_no_url', __('Invalid URL Provided.'));

        $tmpfname = wp_tempnam($url);
        if (! $tmpfname)
            return new WP_Error('http_no_file', __('Could not create Temporary file.'));

        // download file by snoopy
        $snoopy = new Snoopy;

        // need an proxy?:
        // $snoopy->proxy_host = "my.proxy.host";
        // $snoopy->proxy_port = "8080";

        // set browser and referer:
        $snoopy->agent = "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)";
        $snoopy->referer = "http://www.google.com/";

        // set some cookies:
        $snoopy->cookies["SessionID"] = '238472834723489';
        $snoopy->cookies["favoriteColor"] = "blue";

        // set an raw-header:
        $snoopy->rawheaders["Pragma"] = "no-cache";

        // set some internal variables:
        $snoopy->maxredirs = 2;
        $snoopy->offsiteok = false;
        $snoopy->expandlinks = false;

        // fetch url
        $snoopy->fetch($url);

        $headers = array();
        while(list($key, $val) = each($snoopy->headers)){
            $headers[$key] = $val;
        }

        if (strpos($snoopy->response_code, '200') === false) {
            return new WP_Error('http_404', trim($snoopy->error));
        } else {
            file_put_contents($tmpfname, $snoopy->results);
        }

        return $tmpfname;
    }

    /**
     * download image from url
     * insert to post as attachment
     *
     * @param $url
     * @param $post_id
     *
     * @return bool|int|object
     */
    public function media_download_sideload($url, $post_id) {
        $post = get_post($post_id);

        // check file exist on hard disk or not
        $time = current_time('mysql');
        if (substr($post->post_date, 0, 4) > 0) {
            $time = $post->post_date;
        }
        $uploads = wp_upload_dir($time);

        $file = array();
        $file['name'] = $this->clean_basename($this->basename($url));

        // build up array like PHP file upload
        $file['file'] = $uploads['path']. '/'. $file['name'];
        $filetype = wp_check_filetype($file['file']);
        $file['type'] = $filetype['type'];
        $file['tmp_name'] = $this->download_url($this->reencode_url($url));
        $file['error'] = 0;
        $file['url'] = $this->reencode_url($url);

        // check ignore file
        $ignore = false;

        // ignore 404 file
        if (is_wp_error($file['tmp_name'])) {
            @unlink($file['tmp_name']);
            $ignore = true;
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
            echo "error ; <a href='{$url}'>{$url}</a><br/>";
            return false;
        }

        // sideload image
        $attachment_id = media_handle_sideload($file, $post->ID);

        return $attachment_id;
    }

    /**
     * insert to post as attachment
     *
     * @param $url
     * @param $post_id
     *
     * @return int
     */
    public function media_update_sideload($url, $post_id) {
        $post = get_post($post_id);

        // check file exist on hard disk or not
        $time = current_time('mysql');
        if (substr($post->post_date, 0, 4) > 0) {
            $time = $post->post_date;
        }
        $uploads = wp_upload_dir($time);

        // insert to db
        $file = array();
        $file['name'] = $this->clean_basename($this->basename($url));
        $file['file'] = $uploads['path']. '/'. $file['name'];
        $file['url'] = $uploads['url']. '/'. $file['name'];
        $filetype = wp_check_filetype($file['file']);
        $file['type'] = $filetype['type'];
        $file['error'] = 0;

        $type = $file['type'];
        $file = $file['file'];
        $title = preg_replace('/\.[^.]+$/', '', $this->basename($file));
        $content = '';

        // use image exif/iptc data for title and caption defaults if possible.
        if ($image_meta = @wp_read_image_metadata($file)) {
            if (trim($image_meta['title']) && !is_numeric(sanitize_title($image_meta['title'])))
                $title = $image_meta['title'];
            if (trim($image_meta['caption']))
                $content = $image_meta['caption'];
        }

        // construct the attachment array.
        $attachment = array(
            'post_mime_type' => $type,
            'guid' => $url,
            'post_parent' => $post->ID,
            'post_title' => $title,
            'post_content' => $content,
        );

        // this should never be set as it would then overwrite an existing attachment.
        unset($attachment['ID']);

        // save the attachment metadata
        $attachment_id = wp_insert_attachment($attachment, $file, $post->ID);

        // update attachment metadata
        if ( !is_wp_error($attachment_id) )
            wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $file ) );

        return $attachment_id;
    }

    /**
     * get original image url when you move to new host
     * @param $url
     * @param $original_host
     *
     * @return string
     */
    public function get_original_image_url( $url, $original_host ) {
        $i    = strpos( $url, "/uploads" );
        $part = substr( $url, $i );
        $return = $original_host . $part;

        return $return;
    }

    /**
     * get original post url when you move to new host
     * @param $url
     * @param $original_host
     *
     * @return string
     */
    public function get_original_post_url($url, $original_host) {
        $home_url = get_home_url();
        $return = $original_host . str_replace($home_url, '', $url);

        return $return;
    }

    public function media_download($url) {
        preg_match('/uploads\/([0-9]*)\/([0-9]*)\/[0-9]*\/(.*)/', $url, $m);

        $dir = get_home_path(). "/wp-content/uploads/{$m[1]}/{$m[2]}";
        $file = get_home_path(). "/wp-content/uploads/{$m[1]}/{$m[2]}/{$m[3]}";
        $link = home_url(). "/wp-content/uploads/{$m[1]}/{$m[2]}/{$m[3]}";;

        if (!file_exists($dir)) {
            if (!wp_mkdir_p($dir)) {
                return false;
            }
        }

        // file exists
        if (file_exists($file)) {
            echo "<a href='$link' target='_blank'>Success, file exists, doesn't need to download</a>";
            return true;
        } else {
            $tmp = download_url($url);
            if (!is_wp_error($tmp)) {
                if (rename($tmp, $file)) {
                    echo "<a href='$link' target='_blank'>Success, file was downloaded</a>";
                    return true;
                } else {
                    echo "<a href='$link' target='_blank'>Error, file wasn't downloaded</a>";
                    return false;
                }
            } else {
                echo "<a href='$link' target='_blank'>Error, file wasn't downloaded</a>";
                return false;
            }
        }
    }
}

/**************************************************
 * fix for wordpress 4.4 under
 **************************************************/

global $wp_version;
if($wp_version < "4.4")
{
    if (!function_exists('get_the_post_thumbnail_url')) {
        function get_the_post_thumbnail_url( $post = null, $size = 'post-thumbnail' ) {
            $post_thumbnail_id = get_post_thumbnail_id( $post );
            if ( ! $post_thumbnail_id ) {
                return false;
            }
            return wp_get_attachment_image_url( $post_thumbnail_id, $size );
        }
    }

    if (!function_exists('wp_get_attachment_image_url')) {
        function wp_get_attachment_image_url( $attachment_id, $size = 'thumbnail', $icon = false ) {
            $image = wp_get_attachment_image_src( $attachment_id, $size, $icon );
            return isset( $image['0'] ) ? $image['0'] : false;
        }
    }
}

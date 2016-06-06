<?php
require_once dirname(__FILE__). '/snoopy.class.php';

class grabimage_helper
{
    /**
     * basename function for utf8
     * @param $url
     *
     * @return mixed
     */
    public function basename($url)
    {
        $temp = explode('/', $url);

        return $temp[count($temp) - 1];
    }

    /**
     * refine url
     * @param $url
     * @return string
     */
    public function reconstruct_url($url)
    {
        $url_parts = parse_url($url);
        $constructed_url = $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path'];

        return $constructed_url;
    }

    /**
     * encode url for unicode
     * @param $url
     * @return mixed
     */
    public function reencode_url($url)
    {
        $temp = explode('/', $url);
        $temp2 = $temp[count($temp) - 1];
        $temp[count($temp) - 1] = urlencode($temp2);

        return implode('/', $temp);
    }

    /**
     * make filename clean
     * @param $file
     * @return string
     */
    public function clean_filename($file) {
        $path = pathinfo($file);
        if (isset($path['extension'])) {
            $new_filename = preg_replace('/.' . $path['extension'] . '$/', '', $file);
            $file = substr(sanitize_title($new_filename), 0, 128) . '.' . $path['extension'];
        }

        return $file;
    }

    /**
     * check if image url exist or not
     * @param $url
     * @return bool
     */
    public function exist_filename($url)
    {
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
    public function real_filename($file)
    {
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
     * compare basename of original and current featured images
     * @param $first
     * @param $second
     *
     * @return bool
     */
    public function compare_basename($first, $second)
    {
        $first = $this->clean_filename($this->basename(trim($first)));
        $second = $this->clean_filename($this->basename(trim($second)));

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

                // ignore niteco image
                if (strpos($url, $_SERVER['SERVER_NAME']) !== false) {
                    continue;
                }

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

        $file = $this->basename($url);
        $file2 = $this->clean_filename($file);
        $query_args = array(
            'post_type'   => 'attachment',
            'post_status' => 'inherit',
            'fields'      => 'ids',
            'meta_query'  => array(
                'relation' => 'OR',
                array(
                    'value'   => $file,
                    'compare' => 'LIKE',
                    'key'     => '_wp_attachment_metadata',
                ),
                array(
                    'value'   => $file2,
                    'compare' => 'LIKE',
                    'key'     => '_wp_attachment_metadata',
                ),
            )
        );
        $query = new WP_Query( $query_args );
        if ( $query->have_posts() ) {
            foreach ( $query->posts as $post_id ) {
                $meta = wp_get_attachment_metadata( $post_id );
                $original_file       = $this->basename( $meta['file'] );
                $cropped_image_files = wp_list_pluck( $meta['sizes'], 'file' );
                if (   $original_file === $file  || in_array($file , $cropped_image_files)
                    || $original_file === $file2 || in_array($file2, $cropped_image_files)) {
                    $attachment_id = $post_id;
                    break;
                }
            }
        }

        return $attachment_id;
    }

    /**
     * generate json file for mapping data
     * @throws Exception
     */
    public function get_old_thumbnail()
    {
        /**************************************************
        $file = dirname(__FILE__). "/frutimian.wordpress.xml";
        if (file_exists($file)) {
            require_once dirname( __FILE__ ) . '/XML2Array.php';

            $xml       = file_get_contents( $file );
            $a         = XML2Array::createArray( $xml );
            $items     = $a["rss"]["channel"]["item"];
            $map       = [ ];
            $thumbnail = [ ];

            for ( $i = 0; $i < count( $items ); $i ++ ) {
                $item       = $items[ $i ];
                $id         = $item["wp:post_id"];
                $map["$id"] = $i;
            }

            for ( $i = 0; $i < count( $items ); $i ++ ) {
                $item       = $items[ $i ];
                $id         = $item["wp:post_id"];
                $post_title = $item["title"];
                $post_type  = $item["wp:post_type"]["@cdata"];

                if ( $post_type != "post" ) {
                    continue;
                }

                $postmeta = $item["wp:postmeta"];
                foreach ( $postmeta as $meta ) {
                    if ( $meta["wp:meta_key"]["@cdata"] == "_thumbnail_id" ) {
                        $thumb_id = $meta["wp:meta_value"]["@cdata"];
                        $thumb    = $items[ $map["$thumb_id"] ];

                        $thumb_url      = $thumb["guid"]["@value"];
                        $attachment_url = $thumb["wp:attachment_url"]["@cdata"];

                        $thumbnail["$id"] = array(
                            "post_title"     => $post_title,
                            "thumb_id"       => $thumb_id,
                            "thumb_url"      => $thumb_url,
                            "attachment_url" => $attachment_url,
                        );
                    }
                }
            }

            file_put_contents(dirname(__FILE__). '/thumbnail.json', json_encode($thumbnail));
        }
        /**************************************************/

        $file = dirname(__FILE__) . '/thumbnail.json';
        if (file_exists($file)) {
            $json = file_get_contents($file);
            return json_decode($json, true);
        } else {
            return array();
        }

    }

    /**
     * set thumbnail for post from attachments
     * @param $id
     */
    public function set_post_thumbnail($id)
    {
        $thumbnail_url = get_the_post_thumbnail_url($id, 'full');
        if (!$thumbnail_url) {
            $attachments = get_attached_media('image', $id);
            if (count($attachments) > 0) {
                ksort($attachments);
                foreach ($attachments as $key => $value) {
                    set_post_thumbnail($id, $key);
                    break;
                }
            }
        }
    }

    /**
     * Download an url to local using snoopy class
     *
     * @param $url
     * @param int $timeout
     *
     * @return string|WP_Error
     */
    public function download_url( $url, $timeout = 300 ) {
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
    public function media_handle_sideload($url, $post_id)
    {
        $post = get_post($post_id);

        // check file exist on hard disk or not
        $time = current_time('mysql');
        if (substr($post->post_date, 0, 4) > 0) {
            $time = $post->post_date;
        }
        $uploads = wp_upload_dir($time);

        $file = array();
        $file['name'] = $this->clean_filename($this->basename($url));

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
            echo "error ; <a href='{$url}'>{$url}</a> <br/>";
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
    public function media_insert_sideload($url, $post_id)
    {
        $post = get_post($post_id);

        // check file exist on hard disk or not
        $time = current_time('mysql');
        if (substr($post->post_date, 0, 4) > 0) {
            $time = $post->post_date;
        }
        $uploads = wp_upload_dir($time);

        // insert to db
        $file = array();
        $file['name'] = $this->clean_filename($this->basename($url));
        $file['file'] = $uploads['path']. '/'. $file['name'];
        $file['url'] = $uploads['url']. '/'. $file['name'];
        $filetype = wp_check_filetype($file['file']);
        $file['type'] = $filetype['type'];
        $file['error'] = 0;

        $type = $file['type'];
        $file = $file['file'];
        $title = preg_replace('/\.[^.]+$/', '', $this->basename($file));
        $content = '';

        // Use image exif/iptc data for title and caption defaults if possible.
        if ($image_meta = @wp_read_image_metadata($file)) {
            if (trim($image_meta['title']) && !is_numeric(sanitize_title($image_meta['title'])))
                $title = $image_meta['title'];
            if (trim($image_meta['caption']))
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
        unset($attachment['ID']);

        // Save the attachment metadata
        $attachment_id = wp_insert_attachment($attachment, $file, $post->ID);

        return $attachment_id;
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

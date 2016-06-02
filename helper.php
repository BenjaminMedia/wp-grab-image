<?php
require_once dirname(__FILE__). '/snoopy.class.php';

class grabimage_helper
{
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
        $temp = basename($url);
        $temp2 = urlencode($temp);

        return str_replace($temp, $temp2, $url);
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
     * @param string $url
     * @return int Attachment ID on success, 0 on failure
     */
    public function get_attachment_id( $url ) {
        $attachment_id = 0;
        $dir = wp_upload_dir();
        // if ( false !== strpos( basename( $url ), $dir['baseurl'] . '/' ) ) { // Is URL in uploads directory?
            $file = basename( $url );
            $query_args = array(
                'post_type'   => 'attachment',
                'post_status' => 'inherit',
                'fields'      => 'ids',
                'meta_query'  => array(
                    array(
                        'value'   => $file,
                        'compare' => 'LIKE',
                        'key'     => '_wp_attachment_metadata',
                    ),
                )
            );
            $query = new WP_Query( $query_args );
            if ( $query->have_posts() ) {
                foreach ( $query->posts as $post_id ) {
                    $meta = wp_get_attachment_metadata( $post_id );
                    $original_file       = basename( $meta['file'] );
                    $cropped_image_files = wp_list_pluck( $meta['sizes'], 'file' );
                    if ( $original_file === $file || in_array( $file, $cropped_image_files ) ) {
                        $attachment_id = $post_id;
                        break;
                    }
                }
            }
        // }
        return $attachment_id;
    }

    /**
     * Downloads a url to a local temporary file using the WordPress HTTP Class.
     * Please note, That the calling function must unlink() the file.
     *
     * @since 2.5.0
     *
     * @param string $url the URL of the file to download
     * @param int $timeout The timeout for the request to download the file default 300 seconds
     * @return mixed WP_Error on failure, string Filename on success.
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
        //$snoopy->proxy_host = "my.proxy.host";
        //$snoopy->proxy_port = "8080";

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
     * generate json file for mapping data
     * @throws Exception
     */
    public function get_old_thumbnail()
    {
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

        $file = dirname(__FILE__) . '/thumbnail.json';
        if (file_exists($file)) {
            $json = file_get_contents($file);
            return json_decode($json, true);
        } else {
            return array();
        }

    }

    /**
     * set thumbnail for post from attachmentS
     * @param $id
     */
    public function set_post_thumbnail($id)
    {
        $thumbnail_url = get_the_post_thumbnail_url($id);
        if (!$thumbnail_url || !$this->exist_filename($thumbnail_url)) {
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
}
